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

require_once('usi-media-solutions-folder-add.php');
require_once('usi-media-solutions-folder-list.php');

class USI_Media_Solutions_Folder {

   const VERSION = '1.1.0 (2020-02-08)';

   function __construct() {

      add_action('add_attachment', array($this, 'action_add_attachment'));
      add_action('admin_menu', array($this, 'action_admin_menu'));
      add_action('post-upload-ui', array($this, 'action_post_upload_ui'));

      add_filter('wp_get_attachment_url', array($this, 'filter_wp_get_attachment_url'), 10, 2);
      add_filter('wp_handle_upload', array($this, 'filter_wp_handle_upload'), 2);
      add_filter('wp_handle_upload_prefilter', array($this, 'filter_wp_handle_upload_prefilter'), 2);

   } // __construct();
 
   function filter_wp_handle_upload($file){
      usi_log(__METHOD__.':'.__LINE__);
      remove_filter('upload_dir', array($this, 'filter_upload_dir'));
      return($file);
   } // filter_wp_handle_upload();
 
   function filter_wp_handle_upload_prefilter($file){
      usi_log(__METHOD__.':'.__LINE__);
      add_filter('upload_dir', array($this, 'filter_upload_dir'));
      return($file);
   } // filter_wp_handle_upload_prefilter();

function action_add_attachment($id) { 
   $post = get_post($id);
   add_post_meta($id, USI_Media_Solutions::MEDIAPATH, $post->guid, true);
} // action_add_attachment();

function filter_wp_get_attachment_url($url, $post_id) {
   if ($path = get_post_meta($post_id, USI_Media_Solutions::MEDIAPATH, true)) {
      //usi_log(__METHOD__.':'.__LINE__.':url=' . $url . ' path=' . $path);
      return($path);
   }
   return($url);
} // filter_wp_get_attachment_url();

   function filter_upload_dir($path){    
      if (!empty($path['error'])) return($path);
      global $wpdb;
      $folder_id = (int)self::get_user_folder_id();
      usi_log(__METHOD__.':'.__LINE__.':path='. print_r($path, true) . ' folder_id=' . $folder_id);
      if (0 < $folder_id) {
         $row = $wpdb->get_row($wpdb->prepare("SELECT `post_title` FROM `{$wpdb->posts}` WHERE (`ID` = %d) LIMIT 1", 
            $folder_id), OBJECT);
      usi_log(__METHOD__.':'.__LINE__.':row='. print_r($row, true));
         if ($row) {
/*
'subdir'  (string) Subdirectory if uploads use year/month folders option is on.
'basedir' (string) Path without subdir.
'path'    (string) Base directory and subdirectory or full path to upload directory.
'baseurl' (string) URL path without subdir.
'url'     (string) Base URL and subdirectory or absolute URL to upload directory.
'error'   (string|false) False or error message.
https://www.vudoos.local/portal/puck/wp-content/uploads/D:/Users/JAS/My Web Sites/Clients/www.Vudoos.org/public_html/media/docuemnts/1.jpg
(
    [subdir]  => /2020/02
    [basedir] => D:\Users\JAS\My Web Sites\Clients\www.Vudoos.org\public_html\portal\puck/wp-content/uploads
    [path]    => D:\Users\JAS\My Web Sites\Clients\www.Vudoos.org\public_html\portal\puck/wp-content/uploads/2020/02
    [baseurl] => https://www.vudoos.local/portal/puck/wp-content/uploads
    [url]     => https://www.vudoos.local/portal/puck/wp-content/uploads/2020/02
    [error] => 
)
(
    [subdir]  => 
    [basedir] => D:/Users/JAS/My Web Sites/Clients/www.Vudoos.org/public_html
    [path]    => D:/Users/JAS/My Web Sites/Clients/www.Vudoos.org/public_html/media/docuemnts
    [baseurl] => https://www.vudoos.local
    [url]     => https://www.vudoos.local/media/docuemnts
    [error] => 
)
*/
            $path['subdir']  = '';
            $path['basedir'] = $_SERVER['DOCUMENT_ROOT'];
            $path['path']    = $path['basedir'] . $row->post_title;
            $path['baseurl'] = 'http' . ($_SERVER['HTTPS'] ? 's' : '') . '://' . $_SERVER['SERVER_NAME'];
            $path['url']     = $path['baseurl'] . $row->post_title;
         }
      }
      usi_log(__METHOD__.':'.__LINE__.':path='. print_r($path, true));
      return($path);
   } // filter_upload_dir();

   function action_admin_menu() {

      $usi_MM_upload_folders_hook = add_media_page(
         'usi-MM-upload-folders', // Text displayed in title tags of page when menu is selected;
         'Upload Folders', // Text displayed in menu bar;
         USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'view-folders'), // The capability required to enable page;
         /* lower case for option; */ 'usi-mm-upload-folders-page', // Menu page slug name;
         'usi_MM_upload_folders_page' // Function called to render page content;
      );

   } // action_admin_menu();

   function action_post_upload_ui() {

      $folder_id     = self::get_user_folder_id();

      $folders       = self::get_folders();

      $folders_title = esc_attr('Upload Folder', USI_Media_Solutions::TEXTDOMAIN);

      if (empty(USI_Media_Solutions::$options['preferences']['organize-allow-root'])) {
         if (1 >= count($folders)) $folders_title .= ' (' . esc_attr('Cannot upload to root folder', USI_Media_Solutions::TEXTDOMAIN) . ')';
         unset($folders[0]);
      }

      $folders_html  = USI_WordPress_Solutions_Settings::fields_render_select(null, $folders, $folder_id);

      echo '<div id="poststuff">' . PHP_EOL;
      $this->action_post_upload_ui_postbox(1, $folders_title, $folders_html);
      echo '</div><!--poststuff-->' . PHP_EOL;
   } // action_post_upload_ui();

   function action_post_upload_ui_postbox($index, $title, $html) {
      echo
      '  <div id="postbox-container-' . $index . '" class="postbox-container" style="float:left; margin-right:10px; text-align:left; width:30%;">' . PHP_EOL .
      '    <div class="meta-box-sortables">' . PHP_EOL .
      '      <div class="postbox">' . PHP_EOL .
      '        <h3 class="hndle" style="cursor:default;"><span style="cursor:text;">' . $title . '</span></h3>' . PHP_EOL .
      '        <div class="inside">' . PHP_EOL .
      '          ' . $html . PHP_EOL .
      '        </div><!--inside-->' . PHP_EOL .
      '      </div><!--postbox-->' . PHP_EOL .
      '    </div><!--meta-box-sortables-->' . PHP_EOL .
      '  </div><!--postbox-container-' . $index . '-->' . PHP_EOL;
   } // action_post_upload_ui_postbox();

   public static function get_folders() {

      global $wpdb;

      $folders   = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "') OR (`post_type` = 'usi-ms-upload-folder')" .
         " ORDER BY `post_title`", ARRAY_N);

      return($folders);

   } // get_folders();

   public static function get_user_folder_id() {
      $user_id = get_current_user_id();
      $folder_id = get_user_option(USI_Media_Solutions::USERFOLDER, $user_id);
usi_log(__METHOD__.':'.__LINE__.':user_id=' . $user_id . ' folder_id=' . $folder_id);
return($folder_id);
      return(get_user_option(USI_Media_Solutions::USERFOLDER, get_current_user_id()));

   } // get_user_folder_id();

} // Class USI_Media_Solutions_Folder;

new USI_Media_Solutions_Folder();

// --------------------------------------------------------------------------------------------------------------------------- // ?>
