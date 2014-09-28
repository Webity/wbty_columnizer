<?php
/**
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access.
defined('_JEXEC') or die;

/**
 * Email cloack plugin class.
 *
 * @package     Joomla.Plugin
 * @subpackage  Content.wbty_columnizer
 */
class plgContentWbty_columnizer extends JPlugin
{
    var $script_added = false;
    
    public function onContentPrepare($context, &$row, &$params, $page = 0)
    {
        // Don't run this plugin when the content is being indexed
        if ($context == 'com_finder.indexer') {
            return true;
        }

        if (is_object($row)) {
            return $this->_scan($row->text);
        }
        return $this->_scan($row);
    }

    protected function _scan(&$text)
    {
        $parts = array();
        $parts[] = '(<[^\/]{0,}>{wbty_columnizer}(.*))';
        $parts[] = '(?!<[A-Za-z])(.*)';
        $parts[] = '(<[^\/]{0,}>{\/wbty_columnizer}(.*))';
        $parts[] = '(<[A-Za-z])?';

        $new_text = preg_replace_callback('/'.implode($parts).'/siU',
                      array(get_class($this),'_buildColumnizer'),
                      $text, -1, $count);
        // tends to throw "too many backlinks" error, so this skips it on error
        // currently working on a fix to help performance

        if ($new_text) {
            $text = $new_text;
        }

        $text = preg_replace_callback('/<[^\/]{0,}>{column[\s]?break}(.*)(<[a-zA-Z])/siU',
                      array(get_class($this),'_buildColumnBreak'),
                      $text);

        
        $text = preg_replace('/(<[^\/]{0,}>{wbty_columnizer}(.*))(<[A-Za-z])/siU', '$3', $text);
        $text = preg_replace('/(<[^\/]{0,}>{\/wbty_columnizer}(.*))(<[A-Za-z])/siU', '$3', $text);

        return true;
    }
    
    protected function _buildColumnizer($matches) {
        $this->matches = $matches;

        $jversion = new JVersion();
        $above3 = version_compare($jversion->getShortVersion(), '3.0', 'ge');
        if ($above3) {
            JHTML::_('jquery.framework');
        } else {
            JHTML::script('plg_content_wbty_columnizer/jquery-1.8.3.js', false, true);
        }
        JHTML::script('plg_content_wbty_columnizer/jquery.columnizer.js', false, true);

        if (!$this->col_id) {
            $this->col_id = 1;
        } else {
            $this->col_id++;
        }
        
        $cols = $this->params->get('cols', 2);
        
        $document =& JFactory::getDocument();

        if ( strpos( $matches[3] , '{columnbreak}' ) !== false || strpos( $matches[3] , '{column break}' ) !== false ) {
            $manual_breaks = 'true';
        } else {
            $manual_breaks = 'false';
        }
        
        ob_start();
?>

    jQuery(window).load(function($) {
        var wbty_columnizer<?php echo $this->col_id; ?> = function() {
            if (jQuery('.wbty_columnizer .column').length) {
                jQuery('.wbty_columnizer .column p').unwrap();
            }
            if (jQuery('.top-col-2').length) {
                jQuery('.top-col-2').show();
            }
            if (jQuery('.wbty-columnizer-added').length) {
                jQuery('.wbty-columnizer-added').remove();
            }
            
            jQuery('#wbty_columnizer<?php echo $this->col_id; ?>').each(function() {
            
                var wbty_width = jQuery('body').width();
                jQuery(this).hide();
                var wbty_target = jQuery(this).data('target');
                
                if (wbty_width < 960) {
                    jQuery(wbty_target).html(jQuery(this).html());
                    jQuery(wbty_target + ' .top-col-2').each(function() {
                        $newel = jQuery(this).clone();
                        $newel.removeClass('top-col-2').addClass('wbty-columnizer-added');
                        jQuery(wbty_target).prepend($newel);
                        jQuery(this).hide();
                    });
                } else {
                    jQuery(this).columnize({
                        manualBreaks: <?php echo $manual_breaks; ?>,
                        columns: 2, 
                        target: wbty_target, 
                        doneFunc: function() {
                            var olcount = 0;
                            jQuery(wbty_target + ' .column').each(function() {
                                if (!jQuery(this).hasClass('last')) {
                                    jQuery(this).width(jQuery(this).width()-30);
                                    jQuery(this).css('margin-right', '30px');
                                }
                                if (olcount && (jQuery(this).children().first().is('ol'))) {
                                    jQuery(this).children().first().attr('start', olcount);
                                }
                                if (jQuery(this).children().last().is('ol')) {
                                    olcount = jQuery(this).children().last().find('li').length + 1;
                                } else {
                                    olcount = 0;
                                }
                            });
                            jQuery(wbty_target + ' .top-col-2').each(function() {
                                $newel = jQuery(this).clone();
                                $newel.removeClass('top-col-2').addClass('wbty-columnizer-added');
                                jQuery('.last.column').prepend($newel);
                                jQuery(this).hide();
                            });
                        }
                    });
                }
            });
        }
        wbty_columnizer<?php echo $this->col_id; ?>();
        jQuery(window).resize(function() {
            wbty_columnizer<?php echo $this->col_id; ?>();
        });
    });
<?php
        $script = ob_get_clean();
        $document->addScriptDeclaration($script);
        
        $return = $matches[3];
        
        $return = '<div class="wbty_columnizer" id="wbty_columnizer'.$this->col_id.'" data-target="#wbty_columns'.$this->col_id.'">'. $return .'</div><div id="wbty_columns'.$this->col_id.'"></div>';

        if (isset($matches[6])) {
            $return .= $matches[6];
        }

        return $return;
    }

    protected function _buildColumnBreak($matches) {
        return '<div class="columnbreak"></div>' . $matches[2];
    }
}
