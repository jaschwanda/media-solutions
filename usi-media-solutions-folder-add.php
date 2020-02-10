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

require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-settings.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-versions.php');

class USI_Media_Solutions_Folder_Add extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.1.0 (2020-02-08)';

   private $text = array();

   function __construct() {

      $this->options = get_option(USI_Media_Solutions::PREFIX . '-options-folder');

      $this->text['page_header'] = __('Add Upload Folder', USI_Media_Solutions::TEXTDOMAIN);

      parent::__construct(
         array(
         // 'debug'       => 'usi_log',
            'name'        => $this->text['page_header'], 
            'prefix'      => USI_Media_Solutions::PREFIX . '-folder-add',
            'text_domain' => USI_Media_Solutions::TEXTDOMAIN,
            'options'     => & $this->options,
            'hide'        => true,
            'page'        => 'menu',
            'no_settings_link' => true
         )
      );

   } // __construct();

   function fields_sanitize($input) {

      $notice_arg  = null;

      $notice_text = null;

      $notice_type = 'notice-error';

      $parent_id   = (int)(!empty($input['settings']['parent']) ? $input['settings']['parent'] : 0);

      $folder      = sanitize_file_name($input['settings']['folder']);

      $description = sanitize_text_field($input['settings']['description']);

      $user_id     = get_current_user_id();

      if (empty($folder)) {
         $notice_text = 'Please enter a valid folder.';
      } else if (empty($description)) {
         $notice_text = 'Please enter a valid description.';
      } else if (1 > $parent_id) {
         $notice_text = 'Please select a parent folder.';
      } else {
         global $wpdb;
         $post = $wpdb->get_row(
            $wpdb->prepare(
               "SELECT `post_title` AS `path` FROM `{$wpdb->posts}` WHERE (`ID` = %d) LIMIT 1", 
               $parent_id), 
            OBJECT
         );
         if (empty($post)) {
            $notice_arg  = '[sql=' . $wpdb->last_query . ']';
            $notice_text = 'Could not find path for parent folder. %s';
         } else {
            $root   = trim($_SERVER['DOCUMENT_ROOT'], '/');
            $path   = trim($post->path, '/');
            $folder = trim($folder, '/');
            $path_folder = '/' . $path . (!empty($path) ? '/' : '') . $folder;
            // Buffer output so we can hide PHP error messages from user and show WordPress notice;
            ob_start();
            $status = wp_mkdir_p($root . $path_folder);
            $output = ob_get_contents();
            ob_end_clean();
            $notice_arg = '<span style="font-family:courier new;"> ' . $path_folder . ' </span>';
            if (!$status) {
               $notice_text = 'Folder %s could not be created.';
            } else {
               $post_id = USI_Media_Solutions::folder_create_post($parent_id, $folder, $path_folder, $description);
               if (is_wp_error($post_id) || !$post_id) {
                  usi_log(__METHOD__.':post_id=' . print_r($post_id, true));
                  $notice_text = 'Folder %s post could not be created.';
               } else {
                  $notice_text = 'Folder %s has been created.';
                  $notice_type = 'notice-success';
                  $parent_id   = $post_id;
               }
            }
         }
      }

      if ($notice_text) {
         add_settings_error(
            $this->page_slug, 
            $notice_type,
            sprintf(__($notice_text, USI_Media_Solutions::TEXTDOMAIN), $notice_arg),
            $notice_type
         );
      }

      update_user_option($user_id, USI_Media_Solutions::PREFIX . '-options-parent', $parent_id);

   } // fields_sanitize();

   function page_render($options = null) {
      parent::page_render($this->text);
   } // page_render();

   function section_footer() {
      echo '<p class="submit">' . PHP_EOL;
      submit_button($this->text['page_header'], 'primary', 'submit', false); 
      echo ' &nbsp; <a class="button button-secondary" href="admin.php?page=usi-mm-upload-folders-page">' .
         __('Back To Folders', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . '</p>';
   } // section_footer();

   function sections() {

      global $wpdb;

      $user_id   = get_current_user_id();

      $parent_id = get_user_option(USI_Media_Solutions::PREFIX . '-options-parent', $user_id);

      $this->options['settings']['parent'] = $parent_id;

      $folders = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "') OR (`post_type` = 'usi-ms-upload-folder')" .
         " ORDER BY `post_title`", ARRAY_N);

      $sections = array(
         'settings' => array(
            'footer_callback' => array($this, 'section_footer'),
            'settings' => array(
               'parent' => array(
                  'f-class' => 'regular-text', 
                  'label' => 'Parent', 
                  'options' => $folders,
                  'type' => 'select', 
               ),
               'folder' => array(
                  'f-class' => 'regular-text', 
                  'label' => 'Folder', 
                  'type' => 'text', 
               ),
               'description' => array(
                  'f-class' => 'regular-text', 
                  'label' => 'Description', 
                  'type' => 'text', 
               ),
            ),
         ), // preferences;

      );

      foreach ($sections as $name => & $section) {
         foreach ($section['settings'] as $name => & $setting) {
            if (!empty($setting['label'])) $setting['label'] = __($setting['label'], USI_Media_Solutions::TEXTDOMAIN);
            if (!empty($setting['notes'])) $setting['notes'] = ' &nbsp; <i>' . 
               __($setting['notes'], USI_Media_Solutions::TEXTDOMAIN) . '</i>';
         }
      }
      unset($setting);

      return($sections);

   } // sections();

} // Class USI_Media_Solutions_Folder_Add;

new USI_Media_Solutions_Folder_Add();

// --------------------------------------------------------------------------------------------------------------------------- // ?>