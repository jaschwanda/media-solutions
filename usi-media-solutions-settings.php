<?php // ------------------------------------------------------------------------------------------------------------------------ //

defined('ABSPATH') or die('Accesss not allowed.');

/*
Sports-Solutions is free software: you can redistribute it and/or modify it under the terms of the GNU General Public 
License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 
Sports-Solutions is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License along with Sports-Solutions. If not, see 
https://github.com/jaschwanda/sports-solutions/blob/master/LICENSE.md

Copyright (c) 2020 by Jim Schwanda.
*/

require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-capabilities.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-settings.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-updates.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-versions.php');

class USI_Media_Solutions_Settings extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.1.0 (2020-02-08)';

   protected $is_tabbed = true;

   function __construct() {

      parent::__construct(
         array(
            'name' => USI_Media_Solutions::NAME, 
            'prefix' => USI_Media_Solutions::PREFIX, 
            'text_domain' => USI_Media_Solutions::TEXTDOMAIN,
            'options' => USI_Media_Solutions::$options,
            'capabilities' => USI_Media_Solutions::$capabilities,
         )
      );

   } // __construct();

   function config_section_header_preferences() {
      echo '<p>' . __('The Media-Solutions plugin enables WordPress media to be stored and organized via user created upload folders, tags and categories.', USI_Media_Solutions::TEXTDOMAIN) . '</p>' . PHP_EOL;
   } // config_section_header_preferences();

   function fields_sanitize($input) {
      if (empty($input['preferences']['organize-folder'])) {
         $input['preferences']['organize-allow-root'] = false;
      }
      return(parent::fields_sanitize($input));
   } // fields_sanitize();

   function filter_plugin_row_meta($links, $file) {
      if (false !== strpos($file, USI_Media_Solutions::TEXTDOMAIN)) {
         $links[0] = USI_WordPress_Solutions_Versions::link(
            $links[0], 
            USI_Media_Solutions::NAME, // Title;
            USI_Media_Solutions::VERSION, // Version;
            USI_Media_Solutions::TEXTDOMAIN, // Text domain;
            __DIR__ // Folder containing plugin or theme;
         );
         $links[] = '<a href="https://www.usi2solve.com/donate/sports-solutions" target="_blank">' . 
            __('Donate', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
      }
      return($links);
   } // filter_plugin_row_meta();

   function sections() {

      $readonly = empty(USI_Media_Solutions::$options['preferences']['organize-folder']);

      $sections = array(
         'preferences' => array(
            'header_callback' => array($this, 'config_section_header_preferences'),
            'label' => 'Preferences',
            'localize_labels' => 'yes',
            'localize_notes' => 2,
            'settings' => array(
               'organize-category' => array(
                  'type' => 'checkbox', 
                  'label' => 'Organize With Categories', 
               ),
               'organize-folder' => array(
                  'type' => 'checkbox', 
                  'label' => 'Organize With Folders', 
               ),
               'organize-allow-root' => array(
                  'type' => 'checkbox', 
                  'label' => 'Allow Root Folder Uploads', 
                  'prefix' => '<span style="display:inline-block; width:16px;"></span>',
                  'notes' => 'Not recommended',
                  'readonly' => $readonly,
               ),
               'organize-tag' => array(
                  'type' => 'checkbox', 
                  'label' => 'Organize With Tags', 
               ),
            ),
         ), // preferences;

         'capabilities' => new USI_WordPress_Solutions_Capabilities($this),

         'updates' => USI_WordPress_Solutions_Updates::section($this),

      );

      return($sections);

   } // sections();

} // Class USI_Media_Solutions_Settings;

new USI_Media_Solutions_Settings();

// --------------------------------------------------------------------------------------------------------------------------- // ?>