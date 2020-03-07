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

class USI_Media_Solutions_Manage extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.1.1 (2020-03-01)';

   protected $is_tabbed = true;

   private $id   = 0;

   private $back = null;
   private $file = null;
   private $many = true;
   private $meta = null;
   private $post = null;

   private $text = array();

   function __construct() {

      $this->options = get_option(USI_Media_Solutions::PREFIX . '-options-manage');

      if (!empty($_REQUEST['id'])) $this->load($_REQUEST['id']);

      $this->text['page_header'] = __('Manage Media', USI_Media_Solutions::TEXTDOMAIN);

      parent::__construct(
         array(
         // 'debug'       => 'usi_log',
            'capability'  => USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'manage-media'), 
            'name'        => $this->text['page_header'], 
            'prefix'      => USI_Media_Solutions::PREFIX . '-manage',
            'text_domain' => USI_Media_Solutions::TEXTDOMAIN,
            'options'     => & $this->options,
            'hide'        => true,
            'page'        => 'menu',
            'query'       => '&id=' . $this->id,
            'no_settings_link' => true
         )
      );

   } // __construct();

   function fields_sanitize($input) {

      if (!$this->id) $this->load($input['files']['id']);

// https://premium.wpmudev.org/blog/upload-file-functions/
// https://makitweb.com/programmatically-file-upload-from-custom-plugin-in-wordpress/
// https://pqina.nl/blog/uploading-files-to-wordpress-media-library/
// media-new.php processes the wordpress single file upload.
 
      if (!empty($_FILES)) {
         usi_log(__METHOD__.':'.__LINE__.':files=' . print_r($_FILES, true));
         // $upload = wp_upload_bits($_FILES['image']['name'], null, file_get_contents($_FILES['image']['tmp_name']));
      }

      $update_back = $update_meta = false;
      $upload_path = wp_get_upload_dir();

      foreach ($input['files'] as $name => $value) {
         $delete_file = null;
         if (!empty($this->back[$name])) {
            $update_back = true;
            $delete_file = $this->back[$name]['file'];
            unset($this->back[$name]);
         }
         if (!empty($this->meta['sizes'][$name])) {
            $update_meta = true;
            $delete_file = $this->meta['sizes'][$name]['file'];
            unset($this->meta['sizes'][$name]);
         }
         if ($delete_file) wp_delete_file($upload_path['path'] . DIRECTORY_SEPARATOR . $delete_file);
      }

      if ($update_back) update_post_meta($this->id, '_wp_attachment_backup_sizes', $this->back);

      if ($update_meta) update_post_meta($this->id, '_wp_attachment_metadata', $this->meta);

   } // fields_sanitize();

   private function load($id) {

      $this->id   = $id;

      $this->back = get_post_meta($id, '_wp_attachment_backup_sizes', true);

      $this->file = get_post_meta($id, '_wp_attachment_file', true);

      $this->meta = get_post_meta($id, '_wp_attachment_metadata', true); 

      $this->path = USI_Media_Solutions_Folder::get_path($id);

      $this->post = get_post($id); 

   // if (isset($this->back)) usi_log(__METHOD__.':'.__LINE__.':$back=' . print_r($this->back, true));
   // if (isset($this->file)) usi_log(__METHOD__.':'.__LINE__.':$file=' . print_r($this->file, true));
   // if (isset($this->meta)) usi_log(__METHOD__.':'.__LINE__.':$meta=' . print_r($this->meta, true));
   // if (isset($this->path)) usi_log(__METHOD__.':'.__LINE__.':$path=' . $this->path);
   // if (isset($this->post)) usi_log(__METHOD__.':'.__LINE__.':$post=' . print_r($this->post, true));

   } // load();

   function page_render($options = null) {
      if ('reload' == $this->active_tab) $this->enctype = ' enctype="multipart/form-data"';
      parent::page_render($this->text);
   } // page_render();

   function sections() {

      if (!is_object($this->post)) return;

      $this->options['files']['id'] = !empty($_REQUEST['id']) ? $_REQUEST['id'] : 0;

      $sections = array(

         'files' => array(
            'label' => 'Delete',
            'localize_labels' => 'yes',
            'localize_notes' => 0, // Nothing;
            'header_callback' => array($this, 'sections_files_header'),
            'footer_callback' => array($this, 'sections_files_footer'),
            'settings' => array(
               'id' => array(
                  'class' => 'hidden', 
                  'type' => 'hidden', 
               ),
            ), // settings;
         ), // files;

         'reload' => array(
            'label' => 'Reload',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'footer_callback' => array($this, 'sections_files_footer'),
            'settings' => array(
               'file' => array(
                  'label' => 'File', 
                  'type' => 'file', 
               ),
            ), // settings;
         ), // folder;

      );

      $guid   = $this->post->guid;
      $length = strlen($guid);
      while ($length && ('/' != $guid[--$length]));
      $base   = substr($guid, 0, $length + 1);
      $files  = array(); // List of files added to list to prevent duplicates;

      // Load default base file;
      if (!empty($this->meta['file'])) {
         $file = basename($this->meta['file']);
         $files[$file] = true;
      } else {
         $this->many = false;
      }
      $this->text['page_header'] .= ' - <a href="' . $guid . '" target="_blank">' . substr($guid, $length + 1) . '</a>';

      if (!empty($this->meta['sizes'])) foreach ($this->meta['sizes'] as $name => $value) {
         $file = $value['file'];
         if (empty($files[$file])) {
            $files[$file] = true;
            $sections['files']['settings'][$name] = array(
               'label' => $name, 
               'type' => 'checkbox', 
               'notes' => '&nbsp; <a href="' . $base . $file . '" target="_blank">' . $file . '</a>',
            );
         }
      }

      if (!empty($this->back)) foreach ($this->back as $name => $value) {
         $file = $value['file'];
         if (empty($files[$file])) {
            $files[$file] = true;
            $sections['files']['settings'][$name] = array(
               'label' => $name, 
               'type' => 'checkbox', 
               'notes' => '&nbsp; <a href="' . $base . $file . '" target="_blank">' . $file . '</a>',
            );
         }
      }

      return($sections);

   } // sections();

   function sections_files_footer() {
      $button = ('reload' == $this->active_tab ? 'Reload' : 'Delete') . ' Media';
      echo '<p class="submit">' . PHP_EOL;
      submit_button(__($button, USI_Media_Solutions::TEXTDOMAIN), 'primary', 'submit', false); 
      echo ' &nbsp; <a class="button button-secondary" href="upload.php">' .
         __('Back To Library', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . 
         ' &nbsp; <a class="button button-secondary" href="admin.php?page=usi-mm-upload-folders-page">' .
         __('Back To Folders', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . '</p>';
   } // sections_files_footer();

   function sections_files_header() {
      echo '<p>' . ($this->many 
         ? __('You can permanently delete the following thumbnails and associated files to free up space in your file system. Go to the <a href="upload.php">media library</a> to permanently delete this file and all of its thumbnails and associated files in one step.', USI_Media_Solutions::TEXTDOMAIN)
         : __('Go to the <a href="upload.php">media library</a> to permanently delete this file in one step.', USI_Media_Solutions::TEXTDOMAIN)) . 
         '</p>' . PHP_EOL;
   } // sections_files_header();

} // Class USI_Media_Solutions_Manage;

new USI_Media_Solutions_Manage();

// --------------------------------------------------------------------------------------------------------------------------- // ?>