<?php
/*
##################################################
PHP class for Typesetter CMS addon 'Hide Admin UI'
Author: J. Krausz
Date: 2018-01-15
Version 1.1
##################################################
*/

defined('is_running') or die('Not an entry point...');

class HideAdminUI{

  public static function GetHead() {
    global $page, $addonRelativeCode, $config;
    if( !\gp\tool::LoggedIn() || !$page->title ){ // FYI: !$page->title means we're on an admin page
      return;
    }

    // Config, might become an Admin Page in future versions
    $addon_config = array(

      // The keyboard shortcut
      'keys' => array( 
        array( 'label' => 'Ctrl', 'js_condition' => 'e.ctrlKey' ),      // e.ctrlKey | e.altKey | e.shiftKey
        array( 'label' => 'H',    'js_condition' => 'e.which == 72' ),  // see http://keycode.info to customize
      ),

      // Options
      'auto_hide_below'         => 992,   // auto-hides UI when viewport width is less than this px value, false = disable
      'prevent_browser_default' => true,  // true = prevents default browser shortcut (e.g. [Ctrl]+[H] = toggle history panel)

      // Icons: Use FontAwesome icons or custom images, e.g. '<img src="' . $addonRelativeCode. '/hide_icon.svg" />'
      'hide_icon'               => '<i class=\"fa fa-minus-circle\"></i>', 
      'show_icon'               => '<i class=\"fa fa-plus-circle\"></i>',  

    );


    // Internationalization
    include 'HideAdminUI_i18n.inc';
    $i18n = !empty($HideAdminUI_i18n[$config['language']]) ? $HideAdminUI_i18n[$config['language']] : $HideAdminUI_i18n['en'];

    $keyboard_shortcut = array();
    $keyboard_js_conditions = array();
    foreach( $addon_config['keys'] as $key ){
      $keyboard_shortcut[] = !empty($i18n[$key['label']]) ? $i18n[$key['label']] : $key['label'];
      $js_conditions[] = $key['js_condition'];
    }
    $keyboard_shortcut  = '[' . implode(']+[', $keyboard_shortcut) . ']';
    $js_conditions      = implode(' && ', $js_conditions);

    $js_prevent_default = $addon_config['prevent_browser_default'] ? "\n" . '    e.preventDefault(); e.stopPropagation();' : '';

    $hide_ui_title = $i18n['Hide Admin UI'] . ' ' . $keyboard_shortcut;
    $show_ui_title = $i18n['Show Admin UI'] . ' ' . $keyboard_shortcut;

    // Output CSS and JS
    $page->css_admin[] =  $addonRelativeCode . '/HideAdminUI.css';

    $page->head_script .= "\n\n/* Hide Admin UI */\n" 
      . 'var HideAdminUI = {' . "\n"
      . '  toggle  : function(){ $("html").toggleClass("override_admin_style"); },' . "\n"
      . '  show    : function(){ $("html").removeClass("override_admin_style"); },' . "\n"
      . '  hide    : function(){ $("html").addClass("override_admin_style"); }' . "\n"
      . '};' . "\n\n";

    $page->jQueryCode .= "\n\n/* Hide Admin UI */\n" 
      . '$("<li class=\"hide-admin-ui\" '
      . 'title=\"' . $hide_ui_title . '\"'
      . '><a>' . $addon_config['hide_icon'] . '</a></li>")' . "\n" 
      . '  .on("click", HideAdminUI.hide)' . "\n" 
      . '  .prependTo("#admincontent_panel > ul:not(.panel_tabs)");'
      . "\n\n"
      . '$("<div class=\"show-admin-ui\" '
      . 'title=\"' . $show_ui_title . '\"'
      . '>' . $addon_config['show_icon'] . '</div>")' . "\n" 
      . '  .on("click", HideAdminUI.show).appendTo("body");'. "\n"
      . "\n"
      . '$(document).on("keydown", function(e){' . "\n"
      . '  if( e.which == 27 ){ HideAdminUI.show(); } /* 27 = [Esc] key */' . "\n"
      . '  if( ' . $js_conditions . ' ){' . "\n"
      . '    HideAdminUI.toggle();' . $js_prevent_default  . "\n"
      . '  }' . "\n"
      . '});' . "\n\n";

      if( $addon_config['auto_hide_below'] && is_numeric($addon_config['auto_hide_below']) ){
        $page->jQueryCode .= '$(window).on("load resize", function(e){' . "\n"
          . '  var ww = $(window).width();' . "\n"
          . '  if( ww <= ' . $addon_config['auto_hide_below'] . ' ){' . "\n"
          . '    HideAdminUI.hide();' . "\n"
          . '  }else{' . "\n"
          . '    HideAdminUI.show();' . "\n"
          . '  }' . "\n"
          . '});' . "\n\n";
      }
  }

}
