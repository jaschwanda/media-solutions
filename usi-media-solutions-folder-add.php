<?php // ------------------------------------------------------------------------------------------------------------------------ //

defined('ABSPATH') or die('Accesss not allowed.');

/*
Media-Solutions is free software: you can redistribute it and/or modify it under the terms of the GNU General Public 
License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 
Media-Solutions is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License along with Media-Solutions. If not, see 
https://github.com/jaschwanda/media-solutions/blob/master/LICENSE.md

Copyright (c) 2020 by Jim Schwanda.
*/

require_once(plugin_dir_path(__DIR__) . 'usi-media-solutions/usi-media-solutions.php');

require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-custom-post.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-settings.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-static.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-versions.php');

class USI_Media_Solutions_Folder_Add extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.2.7 (2021-11-03)';

   private $text = array();

   function __construct() {

      if (is_admin()) {

         global $pagenow;

         switch ($pagenow) {

         case 'admin.php':
         case 'options.php':

            $this->options = get_option(USI_Media_Solutions::PREFIX . '-options-folder');

            $this->text['page_header'] = __('Add Upload Folder', USI_Media_Solutions::TEXTDOMAIN);

            parent::__construct(
               array(
                  'capability'  => USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'create-folders'), 
                  'name'        => $this->text['page_header'], 
                  'prefix'      => USI_Media_Solutions::PREFIX . '-folders-add',
                  'text_domain' => USI_Media_Solutions::TEXTDOMAIN,
                  'options'     => & $this->options,
                  'hide'        => true,
                  'page'        => 'menu',
                  'no_settings_link' => true
               )
            );

            break;

         }

      } // is_admin();

   } // __construct();

   public static function delete_folder($folder) {

      $folder = '/' . $folder;

      $path   = trim($_SERVER['DOCUMENT_ROOT'], '/') . $folder;

      $post   = USI_WordPress_Solutions_Custom_Post::get_post_by(USI_Media_Solutions::POSTFOLDER, 'post_title', $folder);

      if (!empty($post->ID)) wp_delete_post($post->ID, true);

      USI_WordPress_Solutions_Static::remove_directory($path);

   } // delete_folder();

   function fields_sanitize($input) {

      return(self::make_folder($input, $this->page_slug));

   } // fields_sanitize();

   public static function make_folder($input, $page_slug = null) {

      $notice_arg  = null;

      $notice_text = null;

      $notice_type = 'notice-error';

      $parent_id   = (int)(!empty($input['folder']['parent']) ? $input['folder']['parent'] : 0);

      $folder      = sanitize_file_name($input['folder']['folder']);

      $description = sanitize_text_field($input['folder']['description']);

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
            // Only right trim the root as the leading '/' is needed on linux systems;
            $root   = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
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
                  $notice_text = 'Folder %s post could not be created.';
               } else {
                  $notice_text = 'Folder %s has been created.';
                  $notice_type = 'notice-success';
                  $parent_id   = $post_id;
               }
            }
         }
      }

      if ($page_slug) {

         if ($notice_text) {
            add_settings_error(
               $page_slug, 
               $notice_type,
               sprintf(__($notice_text, USI_Media_Solutions::TEXTDOMAIN), $notice_arg),
               $notice_type
            );
         }

         update_user_option($user_id, USI_Media_Solutions::USERFOLDER, $parent_id);

      }

      return($input);

   } // make_folder();

   function page_render($options = null) {
      parent::page_render($this->text);
   } // page_render();

   function section_footer() {
      echo '<p class="submit">' . PHP_EOL;
      submit_button($this->text['page_header'], 'primary', 'submit', false); 
      echo ' &nbsp; <a class="button button-secondary" href="admin.php?page=' . USI_Media_Solutions::MENUFOLDER . '">' .
         __('Back To Folders', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . '</p>';
   } // section_footer();

   function sections() {

      global $wpdb;

      $this->options['folder']['parent'] = USI_Media_Solutions_Folder::get_user_fold_id();

      $folders = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "')" .
         " ORDER BY `post_title`", ARRAY_N);

      $sections = array(
         'folder' => array(
            'footer_callback' => array($this, 'section_footer'),
            'localize_labels' => 'yes',
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

      return($sections);

   } // sections();

} // Class USI_Media_Solutions_Folder_Add;

new USI_Media_Solutions_Folder_Add();

// --------------------------------------------------------------------------------------------------------------------------- // ?>