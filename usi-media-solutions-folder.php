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

      add_action('admin_menu', array($this, 'action_admin_menu'));
      add_action('post-upload-ui', array($this, 'action_post_upload_ui'));

   } // __construct();

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

      $folder_id    = self::get_user_folder_id();

      $folders      = self::get_folders();

      if (empty(USI_Media_Solutions::$options['preferences']['organize-allow-root'])) unset($folders[0]);

      $folders_html = USI_WordPress_Solutions_Settings::fields_render_select(null, $folders, $folder_id);

      echo '<div id="poststuff">' . PHP_EOL;
      $this->action_post_upload_ui_postbox(1, $folders_html);
      echo '</div><!--poststuff-->' . PHP_EOL;
   } // action_post_upload_ui();

   function action_post_upload_ui_postbox($index, $html) {
      echo
      '  <div id="postbox-container-' . $index . '" class="postbox-container" style="float:left; margin-right:10px; text-align:left; width:30%;">' . PHP_EOL .
      '    <div class="meta-box-sortables">' . PHP_EOL .
      '      <div class="postbox">' . PHP_EOL .
      '        <h3 class="hndle" style="cursor:default;"><span style="cursor:text;">' . esc_attr('Upload Folder', 'wp_admin_style') . '</span></h3>' . PHP_EOL .
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

      return(get_user_option(USI_Media_Solutions::USERFOLDER, get_current_user_id()));

   } // get_user_folder_id();

} // Class USI_Media_Solutions_Folder;

new USI_Media_Solutions_Folder();

// --------------------------------------------------------------------------------------------------------------------------- // ?>