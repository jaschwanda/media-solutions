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
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-popup.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-popup-action.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-settings.php');
require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-versions.php');

class USI_Media_Solutions_Manage extends USI_WordPress_Solutions_Settings {

   const VERSION = '1.2.8 (2022-10-05)';

   protected $is_tabbed = true;

   private $ok_delete   = true;
   private $ok_reload   = true;
   private $ok_rename   = true;

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

   private $head  = null;  // File name and link in header;

   private $text  = [];

   function __construct() {

      if (is_admin()) {

         global $pagenow;

         switch ($pagenow) {

         case 'admin.php':
         case 'options.php':

            $this->options = get_option(USI_Media_Solutions::PREFIX . '-options-manage');

            if (!empty($_REQUEST['id'])) $this->load($_REQUEST['id']);

            $this->text['page_header'] = __('Manage Media', USI_Media_Solutions::TEXTDOMAIN);

            parent::__construct(
               [
                  'capability'  => USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'manage-media'), 
                  'name'        => $this->text['page_header'], 
                  'prefix'      => USI_Media_Solutions::PREFIX . '-manage',
                  'text_domain' => USI_Media_Solutions::TEXTDOMAIN,
                  'options'     => & $this->options,
                  'hide'        => true,
                  'page'        => 'menu',
                  'query'       => '&id=' . $this->id,
                  'no_settings_link' => true, // Supress plugin page settings link for this page;
               ]
            );

            break;

         }

      } // is_admin();

   } // __construct();

   function fields_sanitize($input) {
usi::log('$input=', $input);
usi::log('$_REQUEST=', $_REQUEST);
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

            } else if (pathinfo($_FILES['usi-media-reload']['name'], PATHINFO_EXTENSION) != pathinfo($this->file, PATHINFO_EXTENSION)) {

               $notice_type = 'notice-error';
               $notice_text = 'File not reloaded - new file must have same file type/extension as original file.';

            } else { // ELSE upload is premitted (there are no associated files);

               // Delete the existing file;
               wp_delete_file($this->path . DIRECTORY_SEPARATOR . $this->base);

               // Load the name the reloaded file should take on in case it is different;
               $_FILES['usi-media-reload']['name'] = $this->base;

               $overrides = ['test_form' => false];
               $time      = empty($this->fold) ? dirname($this->file) : null;
               $status    = wp_handle_upload($_FILES['usi-media-reload'], $overrides, $time);

               if (!empty($status['error'])) {
                  $notice_type = 'notice-error';
                  $notice_text = $status['error'];
               } else {
                  $notice_type = 'notice-success';
                  $notice_text = 'File reloaded successfully.';
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

      $log        = USI_WordPress_Solutions_Diagnostics::get_log(USI_Media_Solutions::$options);

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

      if (get_current_user_id() && (USI_Media_Solutions::DEBUG_MANAGE == (USI_Media_Solutions::DEBUG_MANAGE & $log))) {
         usi::log2(__METHOD__.'()~'.__LINE__.':',
            '\nfile=', $this->file, 
            '\nbase=', $this->base, 
            '\nlink=', $this->link,
            '\npath=', $this->path,
            '\nfold=', $this->fold, 
            '\nback='. $this->back, 
            '\nmeta=', $this->meta,
            '\npost=', $this->post
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
      $this->ok_rename = USI_WordPress_Solutions_Capabilities::current_user_can(USI_Media_Solutions::PREFIX, 'rename-media');

      $sections = [

         'files' => [
            'label' => 'Delete',
            'localize_labels' => 'yes',
            'localize_notes' => 0, // Nothing;
            'header_callback' => [$this, 'section_header'],
            'footer_callback' => [$this, 'section_footer'],
            'settings' => [
               'id' => [
                  'class' => 'hidden', 
                  'type' => 'hidden', 
               ],
            ], // settings;
         ], // files;

      ];

      if ($this->ok_reload) {

         $sections['reload'] = [
            'label' => 'Reload',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'header_callback' => [$this, 'section_header'],
            'footer_callback' => [$this, 'section_footer'],
            'settings' => [
            ], // settings;
         ]; // reload;

      }

      $base  = $this->base;
      $files = []; // List of files added to list to prevent duplicates;

      // Load default base file;
      if (!empty($this->meta['file'])) {
         $base = basename($this->meta['file']);
         $files[$base] = true;
      }
      $this->head = '<a href="' . $this->link . '/' . $base . '" target="_blank">' . $this->file . '</a>';
      $this->text['page_header'] .= ' - ' . $this->head;

      if (!empty($this->meta['sizes'])) foreach ($this->meta['sizes'] as $name => $value) {
         $base = $value['file'];
         if (empty($files[$base])) {
            $this->count++;
            $files[$base] = true;
            $sections['files']['settings'][$name] = [
               'attr'  => 'data-info="' . esc_attr($base) . '"',
               'f-class' => $this->prefix . ' usi-popup-checkbox',
               'label' => $name, 
               'readonly' => !$this->ok_delete,
               'type' => 'checkbox', 
               'notes' => '&nbsp; <a href="' . $this->link . '/' . $base . '" target="_blank">' . $base . '</a>',
            ];
            unset($this->options['files'][$name]); // Clear option in case select was left over;
         }
      }

      if (!empty($this->back)) foreach ($this->back as $name => $value) {
         $base = $value['file'];
         if (empty($files[$base])) {
            $this->count++;
            $files[$base] = true;
            $sections['files']['settings'][$name] = [
               'attr'  => 'data-info="' . esc_attr($base) . '" " usi-popup-info=" &nbsp; &nbsp; ' . esc_attr($base) . '"',
               'f-class' => $this->prefix . ' usi-popup-checkbox',
               'label' => $name, 
               'type' => 'checkbox', 
               'notes' => '&nbsp; <a href="' . $this->link . '/' . $base . '" target="_blank">' . $base . '</a>',
            ];
            unset($this->options['files'][$name]); // Clear option in case select was left over;
         }
      }

      if ($this->ok_reload) {
         if (!isset($this->post) || !$this->count || ('image/' != substr($this->post->post_mime_type, 0, 6))) {
            $sections['reload']['settings']['file'] = [
               'alt_html' => '<input class="' . $this->prefix . '" data-info="jim.jpg" data-key="jim" type="file" name="usi-media-reload" value="">', 
               'name' => 'usi-media-reload', 
               'type' => 'null', 
            ];
         }
      }

      if ($this->ok_rename) {

         $sections['rename'] = [
            'label' => 'Rename',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'header_callback' => [$this, 'section_header'],
            'footer_callback' => [$this, 'section_footer'],
            'settings' => [
            ], // settings;
         ]; // rename;

         if (0 == $this->count) {
            $this->options['rename']['old-name'] = pathinfo($this->file)['basename'];
            $sections['rename']['settings'] = [
               'old-name' => [
                  'attr' => 'style="font-family:courier new;"',
                  'f-class' => 'large-text', 
                  'label' => 'Current file name', 
                  'readonly' => true,
                  'type' => 'text', 
               ],
               'new-name' => [
                  'attr' => 'style="font-family:courier new;"',
                  'f-class' => 'large-text', 
                  'label' => 'New file name', 
                  'type' => 'text', 
               ],
            ];
         }
      }

      return($sections);

   } // sections();

   public static function column_cb($args) {
      $id_field = $args['id_field'] ?? null;
      $info     = $args['info']     ?? null;
      $post     = $args['post']     ?? null;
      $id       = $args['id']       ?? null;
      return(
         '<input class="usi-popup-checkbox" name="' . $id_field . '[' . $id . ']" type="checkbox" ' .
         'usi-popup-id="' . $id . '" usi-popup-info="' . $info . '" value="' . $id .'" />'
      );
   } // column_cb();

   function section_footer() {

      if ('files' == $this->active_tab) {

         $button   = 'Delete Media';
         $disabled = ($this->ok_delete && $this->count ? '' : ' disabled');
/*
         $popup = USI_WordPress_Solutions_Popup::build(
            [
               'accept' => __('Delete', USI_Media_Solutions::TEXTDOMAIN),
               'cancel' => __('Cancel', USI_Media_Solutions::TEXTDOMAIN),
               'choice' => __('Please select one or more media files before you click the Delete Media button.', USI_Media_Solutions::TEXTDOMAIN),
               'height' => 300,
               'id'     => 'usi-media-popup',
               'list'   => '.' . $this->prefix,
               'ok'     => __('Ok', USI_Media_Solutions::TEXTDOMAIN),
               'pass'   => 1,
               'prefix' => __('Please confirm that you want to delete the following media:', USI_Media_Solutions::TEXTDOMAIN),
               'submit' => '#submit',
               'suffix' => __('This deletion is permanent and cannot be reversed.', USI_Media_Solutions::TEXTDOMAIN),
               'title'  => __('Delete Media', USI_Media_Solutions::TEXTDOMAIN),
               'type'   => 'inline',
               'width'  => 500,
            ]
         );
*/
         $args = [
            'actions' => [
               'delete' => [
                  'head' => __('Please confirm that you want to delete the following media:', USI_Media_Solutions::TEXTDOMAIN),
                  'foot' => __('This deletion is permanent and cannot be reversed.', USI_Media_Solutions::TEXTDOMAIN),
                  'work' => __('Delete', USI_Media_Solutions::TEXTDOMAIN),
               ],
            ],
            'cancel' => __('Cancel', USI_Media_Solutions::TEXTDOMAIN),
            'errors' => [
               'select_item' => __('Please select at least one file before you click the Delete button.', USI_Media_Solutions::TEXTDOMAIN),
            ],
            'height' => '300px',
            'id'     => 'usi-media-popup',
            'invoke' => '#submit',
            'method' => 'custom',
            'title'  => __('Delete Media', USI_Media_Solutions::TEXTDOMAIN),
            'width'  => '500px',
         ];

         USI_WordPress_Solutions_Popup_Action::build($args);


      } else if ('reload' == $this->active_tab) {

         $button   = 'Reload Media';
         $disabled = ($this->count ? ' disabled' : '');
/*
         $popup = USI_WordPress_Solutions_Popup::build(
            [
               'accept' => __('Reload', USI_Media_Solutions::TEXTDOMAIN),
               'cancel' => __('Cancel', USI_Media_Solutions::TEXTDOMAIN),
               'choice' => __('Please choose a media file before you click the Reload Media button.', USI_Media_Solutions::TEXTDOMAIN),
               'height' => 300,
               'id'     => 'usi-media-popup',
               'list'   => '.' . $this->prefix,
               'ok'     => __('Ok', USI_Media_Solutions::TEXTDOMAIN),
               'prefix' =>  __('Please confirm that you want to reload the file:', USI_Media_Solutions::TEXTDOMAIN) .
                   '</p><p> &nbsp ' . $this->head . '</p><p>' . __('with the file:', USI_Media_Solutions::TEXTDOMAIN),
               'submit' => '#submit',
               'suffix' => __('This reload is permanent and cannot be reversed.', USI_Media_Solutions::TEXTDOMAIN),
               'title'  => __('Reload Media', USI_Media_Solutions::TEXTDOMAIN),
               'type'   => 'inline',
               'width'  => 500,
            ]
         );
*/
      } else if ('rename' == $this->active_tab) {

         $button   = 'Rename Media';
         $disabled = ($this->count ? ' disabled' : '');
/*
         $popup = USI_WordPress_Solutions_Popup::build(
            [
               'accept' => __('Rename', USI_Media_Solutions::TEXTDOMAIN),
               'cancel' => __('Cancel', USI_Media_Solutions::TEXTDOMAIN),
               'choice' => __('Please enter a new name before you click the Rename Media button.', USI_Media_Solutions::TEXTDOMAIN),
               'height' => 300,
               'id'     => 'usi-media-popup',
               'list'   => '.' . $this->prefix,
               'ok'     => __('Ok', USI_Media_Solutions::TEXTDOMAIN),
               'prefix' =>  __('Please confirm that you want to rename the file:', USI_Media_Solutions::TEXTDOMAIN) .
                   '</p><p> &nbsp ' . $this->head . '</p><p>' . __('with the file:', USI_Media_Solutions::TEXTDOMAIN),
               'submit' => '#submit',
               'suffix' => __('This rename is permanent and cannot be reversed.', USI_Media_Solutions::TEXTDOMAIN),
               'title'  => __('Rename Media', USI_Media_Solutions::TEXTDOMAIN),
               'type'   => 'inline',
               'width'  => 500,
            ]
         );
*/
      }

//      echo  $popup['inline'];
      echo '    <p class="submit">' . PHP_EOL;
      submit_button(__($button, USI_Media_Solutions::TEXTDOMAIN), 'primary' . $disabled, 'submit', false/*, $popup['anchor']*/);
      echo ' &nbsp; <a class="button button-secondary" href="upload.php">' .
         __('Back To Library', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . 
         ' &nbsp; <a class="button button-secondary" href="upload.php?page=' . USI_Media_Solutions::MENUFOLDER . '">' .
         __('Back To Folders', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . '    </p>' . PHP_EOL;

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
      } else if ('reload' == $this->active_tab) {
         if ($this->count) {
            $head = 'You must delete all thumbnails and associated files before you can reload this file.';
         } else {
            $head = 'You can reload the current file providing the new file is the same type as the existing file.';
         }
      } else if ('rename' == $this->active_tab) {
         if ($this->count) {
            $head = 'You must delete all thumbnails and associated files before you can rename this file.';
         } else {
            $head = 'You can rename the current file providing the new file has the same extension as the existing file.';
         }
      }
      echo '    <p>' . __($head, USI_Media_Solutions::TEXTDOMAIN) . '</p>' . PHP_EOL;
   } // section_header();

} // Class USI_Media_Solutions_Manage;

new USI_Media_Solutions_Manage();

// --------------------------------------------------------------------------------------------------------------------------- // ?>