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

class USI_Media_Solutions_Reload extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.1.1 (2020-02-19)';

   private $meta = null;

   private $text = array();

   function __construct() {

      $this->options = get_option(USI_Media_Solutions::PREFIX . '-options-reload');

      $id   = !empty($_REQUEST['id']) ? $_REQUEST['id'] : 0;

      $this->back = get_post_meta($id, '_wp_attachment_backup_sizes');

      $this->file = get_post_meta($id, '_wp_attachment_file');

      $this->meta = get_post_meta($id, '_wp_attachment_metadata'); 

      $this->post = get_post($id); 

      if (isset($this->back)) usi_log(__METHOD__.':'.__LINE__.':$back=' . print_r($this->back, true));

      if (isset($this->file)) usi_log(__METHOD__.':'.__LINE__.':$file=' . print_r($this->file, true));

      if (isset($this->meta)) usi_log(__METHOD__.':'.__LINE__.':$meta=' . print_r($this->meta, true));

      if (isset($this->post)) usi_log(__METHOD__.':'.__LINE__.':$post=' . print_r($this->post, true));

      $this->text['page_header'] = __('Reload Media File', USI_Media_Solutions::TEXTDOMAIN);

      parent::__construct(
         array(
         // 'debug'       => 'usi_log',
            'capability'  => USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'reload-media'), 
            'name'        => $this->text['page_header'], 
            'prefix'      => USI_Media_Solutions::PREFIX . '-reload',
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

      update_user_option($user_id, USI_Media_Solutions::USERFOLDER, $parent_id);

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

      $sections = array(
         'settings' => array(
            'footer_callback' => array($this, 'section_footer'),
            'localize_labels' => 'yes',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'settings' => array(
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
         ), // settings;

      );

      $guid   = $this->post->guid;
      $length = strlen($guid);
      while ($length && ('/' != $guid[--$length]));
      $base   = substr($guid, 0, $length + 1);

      if (isset($this->back[0])) foreach ($this->back[0] as $key => $value) {
         $file = $value['file'];
         $sections['settings']['settings'][$key] = array(
            'label' => $key, 
            'type' => 'checkbox', 
            'notes' => '<a href="' . $base . $value['file'] . '" target="_blank">b-' . $value['file'] . '</a>',
         );
      }

      if (isset($this->meta[0]['sizes'])) foreach ($this->meta[0]['sizes'] as $key => $value) {
         $file = $value['file'];
         $sections['settings']['settings'][$key] = array(
            'label' => $key, 
            'type' => 'checkbox', 
            'notes' => '<a href="' . $base . $value['file'] . '" target="_blank">m-' . $value['file'] . '</a>',
         );
      }

      return($sections);

   } // sections();

} // Class USI_Media_Solutions_Reload;

new USI_Media_Solutions_Reload();

// --------------------------------------------------------------------------------------------------------------------------- // ?>