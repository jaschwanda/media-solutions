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

// https://makitweb.com/programmatically-file-upload-from-custom-plugin-in-wordpress/

require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-log.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-settings.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-versions.php');

class USI_Media_Solutions_Manage extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.1.3 (2020-03-14)';

   protected $is_tabbed = true;

   private $ok_delete   = true;
   private $ok_reload   = true;

   private $count = 0;
   private $id    = 0;

   private $base  = null;  // File name with no folder components;
   private $back  = null;  // Backup file information, if any;
   private $file  = null;  // File with upload folder components, if any;
   private $fold  = null;  // Custom upload folder, if any;
   private $link  = null;  // URL link to file location minus base file name;
   private $meta  = null;  // Post meta data;
   private $path  = null;  // Disk path to file location minus base file name;
   private $post  = null;  // Post information;

   private $text  = array();

   function __construct() {

      if (!empty($_REQUEST['id'])) $this->load($_REQUEST['id']);

      $this->text['page_header'] = __('Manage Media', USI_Media_Solutions::TEXTDOMAIN);

      parent::__construct(
         array(
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

      $this->options = get_option($this->option_name);

   } // __construct();

   function fields_sanitize($input) {

      // IF submit generated by user (not sure why we need this but sometimes
      // this function is called twice, first time before the user is known);
      if (get_current_user_id()) {

         // Load post meta data if not already loaded;
         if (!$this->id) $this->load($input['files']['id']);
 
         // Clear notice variables;
         $notice_list = $notice_text = $notice_type = null;

         // IF file upload;
         if (!empty($_FILES) && USI_WordPress_Solutions_Capabilities::current_user_can(USI_Media_Solutions::PREFIX, 'reload-media')) {

            // IF there are associated files (should not get here if associated files exist);
            if (!empty($this->back) || !empty($this->meta['sizes'])) {

               $notice_type = 'notice-error';
               $notice_text = 'File not reloaded - all thumbnail and associated files must be delete before this file can be reloaded.';

            } else if (pathinfo ($_FILES['usi-media-reload']['name'], PATHINFO_EXTENSION) != pathinfo ($this->file, PATHINFO_EXTENSION)) {

               $notice_type = 'notice-error';
               $notice_text = 'File not reloaded - new file must have same file type/extension as original file.';

            } else { // ELSE upload is premitted (there are no associated files);

               // Delete the existing file;
               wp_delete_file($this->path . DIRECTORY_SEPARATOR . $this->base);

               // Load the name the reloaded file should take on in case it is different;
               $_FILES['usi-media-reload']['name'] = $this->base;

               $overrides = array('test_form' => false);
               $time      = empty($this->fold) ? dirname($this->file) : null;
               $status    = wp_handle_upload($_FILES['usi-media-reload'], $overrides, $time);

               if (!empty($status['error'])) {
                  $notice_type = 'notice-error';
                  $notice_text = $status['error'];
               } else {
                  $notice_type = 'notice-success';
                  $notice_text = 'File reloaded successfully.';
               }

               if ('image/' == substr($type, 0, 6)) {
                  // (__METHOD__.':'.__LINE__.':file is image');
                  if (in_array(substr($type, 6), USI_Media_Solutions::OK_IMAGES)) {
                     // (__METHOD__.':'.__LINE__.':image is OK');
                  }
               }
            } // ENDIF upload is premitted (there are no associated files);

         // ELSEIF user can delete files;
         } else if (USI_WordPress_Solutions_Capabilities::current_user_can(USI_Media_Solutions::PREFIX, 'delete-media')) {

            $update_back = $update_meta = false;

            foreach ($input['files'] as $name => $value) {
               $delete_file = null;
               if (!empty($this->back[$name])) {
                  $update_back  = true;
                  $delete_file  = $this->back[$name]['file'];
                  $notice_list .= ($notice_list ? ', ' : '') . $delete_file;
                  unset($this->back[$name]);
               }
               if (!empty($this->meta['sizes'][$name])) {
                  $update_meta  = true;
                  $delete_file  = $this->meta['sizes'][$name]['file'];
                  $notice_list .= ($notice_list ? ', ' : '') . $delete_file;
                  unset($this->meta['sizes'][$name]);
               }
               if ($delete_file) wp_delete_file($this->path . DIRECTORY_SEPARATOR . $delete_file);
            }

            if ($update_back) update_post_meta($this->id, '_wp_attachment_backup_sizes', $this->back);

            if ($update_meta) update_post_meta($this->id, '_wp_attachment_metadata', $this->meta);

            if ($notice_list) {
               $notice_type = 'notice-success';
               $notice_text = ' deleted.';
            } else {
               $notice_type = 'notice-error';
               $notice_text = 'No files have been deleted.';
            }

         } // ELSEIF user can delete files;

         // Display file administrator notice if any;
         if ($notice_type) {
            add_settings_error($this->page_slug, $notice_type, $notice_list . __($notice_text, USI_Media_Solutions::TEXTDOMAIN), $notice_type);
         }

      } // ENDIF submit generated by user;

      return($input);

   } // fields_sanitize();

   private function load($id) {

      $this->id   = $id;

      $this->back = get_post_meta($id, '_wp_attachment_backup_sizes', true);

      $this->file = get_post_meta($id, '_wp_attached_file', true);

      $this->fold = USI_Media_Solutions_Folder::get_fold($id);

      $this->meta = get_post_meta($id, '_wp_attachment_metadata', true); 

      $this->post = get_post($id); 

      $guid       = $this->post->guid;
      $length     = strlen($guid);
      while ($length && ('/' != $guid[--$length]));
      $this->base = substr($guid, $length + 1);
      $this->link = substr($guid, 0, $length);

      $length     = strlen($this->file);
      while ($length && ('/' != $this->file[--$length]));
      $subdir     = substr($this->file, 0, $length);

      if (!empty($this->fold)) {
         $this->path = $_SERVER['DOCUMENT_ROOT']  . ($subdir ? DIRECTORY_SEPARATOR . $subdir : ''); 
      } else {
         $upload_dir = wp_get_upload_dir();
         $this->path = $upload_dir['basedir']  . ($subdir ? DIRECTORY_SEPARATOR . $subdir : ''); 
      }

      if (DIRECTORY_SEPARATOR === '\\') $this->path = str_replace('/', DIRECTORY_SEPARATOR, $this->path);

      if (get_current_user_id()) {
         usi::log2(
            '\2nfile=', $this->file, 
            '\2nbase=', $this->base, 
            '\2nlink=', $this->link,
            '\2npath=', $this->path,
            '\2nfold=', $this->fold, 
            '\2nback='. $this->back, 
            '\2nmeta=', $this->meta,
            '\2npost=', $this->post
         );
      }

   } // load();

   function page_render($options = null) {
      if ('reload' == $this->active_tab) $this->enctype = ' enctype="multipart/form-data"';
      parent::page_render($this->text);
   } // page_render();

   function sections() {

      if (!is_object($this->post)) return;

      $this->options['files']['id'] = !empty($_REQUEST['id']) ? $_REQUEST['id'] : 0;

      $this->ok_delete = USI_WordPress_Solutions_Capabilities::current_user_can(USI_Media_Solutions::PREFIX, 'delete-media');
      $this->ok_reload = USI_WordPress_Solutions_Capabilities::current_user_can(USI_Media_Solutions::PREFIX, 'reload-media');

      $sections = array(

         'files' => array(
            'label' => 'Delete',
            'localize_labels' => 'yes',
            'localize_notes' => 0, // Nothing;
            'header_callback' => array($this, 'section_header'),
            'footer_callback' => array($this, 'section_footer'),
            'settings' => array(
               'id' => array(
                  'class' => 'hidden', 
                  'type' => 'hidden', 
               ),
            ), // settings;
         ), // files;

      );

      if ($this->ok_reload) {

         $sections['reload'] = array(
            'label' => 'Reload',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'header_callback' => array($this, 'section_header'),
            'footer_callback' => array($this, 'section_footer'),
            'settings' => array(
            ), // settings;
         ); // reload;

      }

      $base  = $this->base;
      $files = array(); // List of files added to list to prevent duplicates;

      // Load default base file;
      if (!empty($this->meta['file'])) {
         $base = basename($this->meta['file']);
         $files[$base] = true;
      }
      $this->text['page_header'] .= ' - <a href="' . $this->link . '/' . $base . '" target="_blank">' . $this->file . '</a>';

      if (!empty($this->meta['sizes'])) foreach ($this->meta['sizes'] as $name => $value) {
         $base = $value['file'];
         if (empty($files[$base])) {
            $this->count++;
            $files[$base] = true;
            $sections['files']['settings'][$name] = array(
               'label' => $name, 
               'readonly' => !$this->ok_delete,
               'type' => 'checkbox', 
               'notes' => '&nbsp; <a href="' . $this->link . '/' . $base . '" target="_blank">' . $base . '</a>',
            );
            unset($this->options['files'][$name]); // Clear option in case select was left over;
         }
      }

      if (!empty($this->back)) foreach ($this->back as $name => $value) {
         $base = $value['file'];
         if (empty($files[$base])) {
            $this->count++;
            $files[$base] = true;
            $sections['files']['settings'][$name] = array(
               'label' => $name, 
               'type' => 'checkbox', 
               'notes' => '&nbsp; <a href="' . $this->link . '/' . $base . '" target="_blank">' . $base . '</a>',
            );
            unset($this->options['files'][$name]); // Clear option in case select was left over;
         }
      }

      if ($this->ok_reload) {
         if (!isset($this->post) || !$this->count || ('image/' != substr($this->post->post_mime_type, 0, 6))) {
            $sections['reload']['settings']['file'] = array(
               'alt_html' => '<input type="file" name="usi-media-reload" value="">', 
               'name' => 'usi-media-reload', 
               'type' => 'null', 
            );
         }
      }

      return($sections);

   } // sections();

   function section_footer() {
      if ('files' == $this->active_tab) {
         $button   = 'Delete Media';
         $disabled = ($this->ok_delete && $this->count ? '' : ' disabled');
      } else {
         $button   = 'Reload Media';
         $disabled = ($this->count ? ' disabled' : '');
      }
      echo '<p class="submit">' . PHP_EOL;
      submit_button(__($button, USI_Media_Solutions::TEXTDOMAIN), 'primary' . $disabled, 'submit', false); 
      echo ' &nbsp; <a class="button button-secondary" href="upload.php">' .
         __('Back To Library', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . 
         ' &nbsp; <a class="button button-secondary" href="upload.php?page=' . USI_Media_Solutions::MENUFOLDER . '">' .
         __('Back To Folders', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . '</p>';
   } // section_footer();

   function section_header() {
      if ('files' == $this->active_tab) {
         if ($this->count) {
            $head = $this->ok_delete 
               ? 'You can permanently delete the following thumbnails and associated files to free up space in your file system. Go to the <a href="upload.php">media library</a> to permanently delete all these files in one step.'
               : 'You do not have permission to delete the following thumbnails and associated files. Go to the <a href="upload.php">media library</a> to permanently delete all these files in one step.';
         } else {
            $head = 'Go to the <a href="upload.php">media library</a> to permanently delete this file.';
         }
      } else {
         if ($this->count) {
            $head = 'You must delete all thumbnails and associated files before you can reload this file.';
         } else {
            return;
         }
      }
      echo '<p>' . __($head, USI_Media_Solutions::TEXTDOMAIN) . '</p>' . PHP_EOL;
   } // section_header();

} // Class USI_Media_Solutions_Manage;

new USI_Media_Solutions_Manage();

// --------------------------------------------------------------------------------------------------------------------------- // ?>