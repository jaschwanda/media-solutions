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

   private $id   = 0;

   private $back = null;
   private $file = null;
   private $meta = null;
   private $post = null;

   private $text = array();

   function __construct() {

      $this->options = get_option(USI_Media_Solutions::PREFIX . '-options-reload');

      if (!empty($_REQUEST['id'])) $this->load($_REQUEST['id']);

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

      if (!$this->id) $this->load($input['folder']['id']);

      $update_back = $update_meta = false;
      foreach ($input['files'] as $key => $value) {
         $delete_file = null;
         $delete_path = null;
         if (isset($this->back[0][$key])) {
            $delete_path = $this->back[0][$key]['file'];
            $delete_file = $key;
            $update_back = true;
//            unset($this->back[0][$key]);
         }
         if (isset($this->meta[0]['sizes'][$key])) {
            $delete_path = $this->meta[0]['sizes'][$key]['file'];
            $delete_file = $key;
            $update_meta = true;
//            unset($this->meta[0]['sizes'][$key]);
         }
         if ($delete_file) {
            usi_log(__METHOD__.':'.__LINE__.':file=' . $delete_file . ' path=' . $delete_path);
           //wp_delete_file($file);
//wp_delete_attachment( $post_id )
         }
      }
/*
      if ($update_back) {
         update_post_meta($this->id, '_wp_attachment_backup_sizes', $this->back);
         $back = get_post_meta($this->id, '_wp_attachment_backup_sizes');
$status = ($this->back == $back) ? 'good' : 'bad';
usi_log(__METHOD__.':'.__LINE__.'back:status=' . $status . PHP_EOL . print_r($back, true) . PHP_EOL . print_r($this->back, true));
      }

      if ($update_meta) {
         update_post_meta($this->id, '_wp_attachment_metadata', $this->meta);
         $meta = get_post_meta($this->id, '_wp_attachment_metadata');
$status = ($this->meta == $meta) ? 'good' : 'bad';
usi_log(__METHOD__.':'.__LINE__.'meta:status=' . $status . PHP_EOL . print_r($meta, true) . PHP_EOL . print_r($this->meta, true));
      }
*/
   } // fields_sanitize();

   private function load($id) {

      $this->id   = $id;

      $this->back = get_post_meta($id, '_wp_attachment_backup_sizes');

      $this->file = get_post_meta($id, '_wp_attachment_file');

      $this->meta = get_post_meta($id, '_wp_attachment_metadata'); 

      $this->post = get_post($id); 

/*
      if (isset($this->back)) usi_log(__METHOD__.':'.__LINE__.':$back=' . print_r($this->back, true));
      if (isset($this->file)) usi_log(__METHOD__.':'.__LINE__.':$file=' . print_r($this->file, true));
      if (isset($this->meta)) usi_log(__METHOD__.':'.__LINE__.':$meta=' . print_r($this->meta, true));
      if (isset($this->post)) usi_log(__METHOD__.':'.__LINE__.':$post=' . print_r($this->post, true));
*/

   } // load();

   function page_render($options = null) {
      parent::page_render($this->text);
   } // page_render();

   function sections() {

      if (!is_object($this->post)) return;

      $this->options['folder']['id'] = !empty($_REQUEST['id']) ? $_REQUEST['id'] : 0;

      $sections = array(
         'folder' => array(
            'localize_labels' => 'yes',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'settings' => array(
               'id' => array(
                  'label' => 'Id', 
                  'type' => 'hidden', 
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
         ), // folder;
         'files' => array(
            'header_callback' => array($this, 'sections_files_header'),
            'footer_callback' => array($this, 'sections_files_footer'),
         ), // files;

      );

      $guid   = $this->post->guid;
      $length = strlen($guid);
      while ($length && ('/' != $guid[--$length]));
      $base   = substr($guid, 0, $length + 1);

      if (isset($this->back[0])) foreach ($this->back[0] as $key => $value) {
         $file = $value['file'];
         $sections['files']['settings'][$key] = array(
            'label' => $key, 
            'type' => 'checkbox', 
            'notes' => '<a href="' . $base . $value['file'] . '" target="_blank">' . $value['file'] . '</a>',
         );
      }

      if (isset($this->meta[0]['sizes'])) foreach ($this->meta[0]['sizes'] as $key => $value) {
         $file = $value['file'];
         $sections['files']['settings'][$key] = array(
            'label' => $key, 
            'type' => 'checkbox', 
            'notes' => '<a href="' . $base . $value['file'] . '" target="_blank">' . $value['file'] . '</a>',
         );
      }

      return($sections);

   } // sections();

   function sections_files_footer() {
      echo '<p class="submit">' . PHP_EOL;
      submit_button($this->text['page_header'], 'primary', 'submit', false); 
      echo ' &nbsp; <a class="button button-secondary" href="admin.php?page=usi-mm-upload-folders-page">' .
         __('Back To Folders', USI_Media_Solutions::TEXTDOMAIN) . '</a>' . PHP_EOL . '</p>';
   } // sections_files_footer();

   function sections_files_header() {
      echo '<h2>' . __('Delete Associated Files', USI_Media_Solutions::TEXTDOMAIN) . '</h2>' . PHP_EOL;
   } // sections_files_header();

} // Class USI_Media_Solutions_Reload;

new USI_Media_Solutions_Reload();

// --------------------------------------------------------------------------------------------------------------------------- // ?>