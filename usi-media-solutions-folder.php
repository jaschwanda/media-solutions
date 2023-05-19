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

require_once('usi-media-solutions-folder-add.php');
require_once('usi-media-solutions-folder-list.php');
require_once('usi-media-solutions-manage.php');

class USI_Media_Solutions_Folder {

   const VERSION = '1.2.7 (2021-11-03)';

   private static $fold_id   = 0;
   private static $post_id   = 0;
   private static $post_fold = null;
   private static $uploads   = null;

   function __construct() {

      self::$post_id   = 0;
      self::$post_fold = null;
      self::$uploads   = self::get_default_upload_folder();

      add_action('add_attachment', [$this, 'action_add_attachment']);
      add_action('admin_print_styles-upload.php', [$this, 'action_admin_print_styles_upload']);
      add_action('delete_attachment', [$this, 'action_delete_attachment']);
      add_action('init', [$this, 'action_init']);
      add_action('manage_media_custom_column', [$this, 'action_manage_media_custom_column'], 10, 2);
      add_action('post-upload-ui', [$this, 'action_post_upload_ui']);

      add_filter('attachment_link', [$this, 'filter_attachment_link'], 20, 2 );
      add_filter('manage_media_columns', [$this, 'filter_manage_media_columns']);
      add_filter('manage_upload_sortable_columns', [$this, 'filter_manage_upload_sortable_columns']);
      add_filter('media_row_actions', [$this, 'filter_media_row_actions'], 10, 2);
      add_filter('posts_where' , [$this, 'filter_posts_where']);
      add_filter('upload_dir', [$this, 'filter_upload_dir']);
      add_filter('wp_get_attachment_url', [$this, 'filter_wp_get_attachment_url'], 10, 2);

      $this->manage_slug = USI_Media_Solutions::PREFIX . '-manage-settings';

   } // __construct();

   function action_add_attachment($post_id) {
      // IF upload folder given;
      if (!empty(self::$fold_id)) {
         $post = get_post(self::$post_id = $post_id);
         $path = '/' . trim(trim(dirname(str_replace(get_home_url(), '', $post->guid)), '\\'), '/');
         self::$post_fold = ['fold_id' => self::$fold_id, 'path' => $path];
         add_post_meta($post_id, USI_Media_Solutions::MEDIAFOLD, self::$post_fold, true);
         $this->log_folder($post_id, 'default', $path);
      } // ENDIF upload folder given;
   } // action_add_attachment();

   function action_admin_print_styles_upload() {

      $columns = [
         'cb'          => 3, 
         'title'       => 25, 
         'guid'        => 17, 
         'size'        => 10, 
         'author'      => 15, 
         'parent'      => 15, 
         'comments'    => 15, 
         'date'        => 15, 
      ];

      echo USI_WordPress_Solutions_Static::column_style($columns);

   } // action_admin_print_styles_upload();

   function action_delete_attachment($post_id) {
      $upload_path = wp_get_upload_dir();
      if (empty($this->meta)) $this->meta = get_post_meta($post_id, '_wp_attachment_metadata', true); 
      if (!empty($this->meta['sizes'])) foreach ($this->meta['sizes'] as $name => $value) {
         $base = $value['file'];
         wp_delete_file($upload_path['path'] . DIRECTORY_SEPARATOR . $base);
      }
   } // action_delete_attachment();

   function action_init() {
      self::$fold_id = isset($_REQUEST['fold_id']) ? $_REQUEST['fold_id'] : self::get_user_fold_id();
   } // action_init();

   function action_manage_media_custom_column($column, $id) {
      if ('guid' == $column) {
         $fold = get_post_field('guid', $id);
         $tokens = explode('/', $fold);
         unset($tokens[count($tokens) - 1]);
         unset($tokens[0]);
         unset($tokens[1]);
         unset($tokens[2]);
         $folder = '/' . implode('/', $tokens);
         echo '<a href="upload.php?guid=' . rawurlencode($folder) . '"&attachment-filter>' .  $folder . '</a>';
      } else if ('size' === $column) {
         echo @ self::size_format(filesize(get_attached_file($id)));
      }
   } // action_manage_media_custom_column();

   function action_post_upload_ui() {

      $folders       = self::get_folders();

      $folder_title  = esc_attr('Upload Folder', USI_Media_Solutions::TEXTDOMAIN);

      if (1 >= count($folders)) $folder_title .= ' (' . esc_attr('No upload folders have been created', USI_Media_Solutions::TEXTDOMAIN) . ')';

      $folder_html_id = USI_Media_Solutions::POSTFOLDER . '-id';

      $user_fold_id   = isset($_REQUEST['fold_id']) ? $_REQUEST['fold_id'] : self::get_user_fold_id();

      $folder_html    = USI_WordPress_Solutions_Settings::fields_render_select(' id="' . $folder_html_id . '"', $folders, $user_fold_id);

      echo '<div id="poststuff">' . PHP_EOL;
      $this->action_post_upload_ui_postbox(1, $folder_title, $folder_html);
      echo 
      '</div><!--poststuff-->' . PHP_EOL .
      '  <script>' . PHP_EOL .
      '  jQuery(document).ready(function($) {' . PHP_EOL .
      "     var url = 'media-new.php?fold_id=';" . PHP_EOL .
      "     $('#{$folder_html_id}').change(function(){window.location.href = url + $(this).val()});" . PHP_EOL .
      '  });' . PHP_EOL .
      '  </script>' . PHP_EOL;

      update_user_option(get_current_user_id(), USI_Media_Solutions::USERFOLDER, $user_fold_id);

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

   function filter_attachment_link($link, $post_id) {
      // IF upload folder given;
      if ($fold = self::get_fold($post_id)) {
         $meta = get_post_meta($post_id, '_wp_attachment_metadata', true);
         if (!empty($meta['file'])) {
            $path = get_home_url() . $fold['path'] . ($fold['path'] ? '/' : '') . basename($meta['file']);
            $this->log_folder($post_id, $link, $path);
            return($path);
         }
      } // ENDIF upload folder given;
      return($link);
   } // filter_attachment_link()

   function filter_manage_media_columns($input) {
      $ith    = 0;
      $output = [];
      foreach ($input as $key => $value) {
         if (2 == $ith++) {
            if (!empty(USI_Media_Solutions::$options['preferences']['library-show-fold'])) {
               $output['guid'] = __('Upload Folder', USI_Media_Solutions::TEXTDOMAIN);
            }
            if (!empty(USI_Media_Solutions::$options['preferences']['library-show-size'])) {
               $output['size'] = __('File Size', USI_Media_Solutions::TEXTDOMAIN);
            }
         }
         $output[$key] = $value;
      }
      if (empty(USI_Media_Solutions::$options['preferences']['library-show-parent'])) {
         unset($output['parent']);
      }
      if (empty(USI_Media_Solutions::$options['preferences']['library-show-notes'])) {
         unset($output['comments']);
      }
      return($output);
   } // filter_manage_media_columns();

   function filter_manage_upload_sortable_columns($columns) {
      $columns['guid'] = 'guid';
      return($columns);
   } // filter_manage_upload_sortable_columns();

   function filter_media_row_actions($actions, $object) {
      $actions['manage_media'] = '<a href="' . admin_url('admin.php?page=' . $this->manage_slug . '&id=' . 
         $object->ID) . '">' . __('Manage', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
      return($actions);
   } // filter_media_row_actions()

   function filter_posts_where($where) {
      if (!empty($_REQUEST['guid'])) {
         global $wpdb;
         $home   = get_home_url();
         $guid   = $_REQUEST['guid'];
         $where .= " AND (`{$wpdb->posts}`.`guid` REGEXP '{$home}{$guid}/[^/]+$')";
      }
      return($where);
   } // filter_posts_where();

   function filter_upload_dir($path) {
      // IF no upload error;
      if (empty($path['error'])) {
         global $post;
         // IF for a post;
         if (!empty($post->ID)) {
            if ($fold = self::get_fold($post->ID)) {
               $path['subdir']  = '';
               $path['basedir'] = $_SERVER['DOCUMENT_ROOT'];
               $path['path']    = $path['basedir'] . $fold['path'];
               $path['baseurl'] = get_home_url();
               $path['url']     = $path['baseurl'] . $fold['path'];
            }
         // ELSEIF upload folder given;
         } else if (0 < self::$fold_id) {
            global $wpdb;
            $folder = $wpdb->get_row(
               $wpdb->prepare(
                  "SELECT `post_title` FROM `{$wpdb->posts}` WHERE (`ID` = %d) LIMIT 1", 
                  self::$fold_id
               ), 
               OBJECT
            );
            if ($folder) {
               $path['subdir']  = '';
               $path['basedir'] = $_SERVER['DOCUMENT_ROOT'];
               $path['path']    = $path['basedir'] . $folder->post_title;
               $path['baseurl'] = get_home_url();
               $path['url']     = $path['baseurl'] . $folder->post_title;
            }
         } // ENDIF upload folder given;
      } // ENDIF no upload error;
      return($path);
   } // filter_upload_dir();

   function filter_wp_get_attachment_url($url, $post_id) {
      // IF upload folder given;
      if ($fold = self::get_fold($post_id)) {
         $meta = get_post_meta($post_id, '_wp_attachment_metadata', true);
         if (!empty($meta['file'])) {
            $path = get_home_url() . $fold['path'] . ($fold['path'] ? '/' : '') . basename($meta['file']);
            $this->log_folder($post_id, $url, $path);
            return($path);
         }
      } // ENDIF upload folder given;
      return($url);
   } // filter_wp_get_attachment_url();

   public static function get_default_upload_folder() {
      $length = strlen(get_site_url());
      $url    = wp_get_upload_dir()['url'] ?? null;
      return($length < strlen($url) ? substr($url, $length) : 'Default Upload Folder');
   } // get_default_upload_folder();

   public static function get_fold($post_id) {
      if (self::$post_id == $post_id) return(self::$post_fold);
      self::$post_id   = $post_id;
      self::$post_fold = get_post_meta(self::$post_id, USI_Media_Solutions::MEDIAFOLD, true);
      self::$fold_id   = !empty(self::$post_fold['fold_id']) ? self::$post_fold['fold_id'] : 0;
      return(self::$post_fold);
   } // get_fold();

   public static function get_folders() {
      global $wpdb;
      $folders   = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "')" .
         " ORDER BY `post_title`", ARRAY_N);
      if (empty(USI_Media_Solutions::$options['preferences']['organize-allow-root'])) {
         unset($folders[0]);
      }
      if (!empty(USI_Media_Solutions::$options['preferences']['organize-allow-default'])) {
         array_unshift($folders, [0 => 0, 1 => self::$uploads]);
      }
      return($folders);
   } // get_folders();

   public static function get_user_fold_id() {
      return(get_user_option(USI_Media_Solutions::USERFOLDER, get_current_user_id()));
   } // get_user_fold_id();

   private function log_folder($post_id, $from, $to) {
      if (USI_Media_Solutions::$debug & USI_Media_Solutions::DEBUG_FILTER_FOLDER) {
         // Log everything unless debug-post-id given in which case only log debug-postid;
         if (empty(USI_Media_Solutions::$options['debug']['debug-post-id']) 
            || ($post_id == USI_Media_Solutions::$options['debug']['debug-post-id'])) {
            usi::log2(':post_id=' . $post_id . ' ' . $from . ' => ' . $to);
         }
      }
   } // log_folder();

   public static function set_fold_id($fold_id) {
      self::$fold_id = $fold_id;
   } // set_fold_id();

   public static function size_format($bytes) {
      return(str_replace(['.0 B', ' B'], ' bytes', size_format($bytes, 1)));
   } // size_format();

} // Class USI_Media_Solutions_Folder;

new USI_Media_Solutions_Folder();

// --------------------------------------------------------------------------------------------------------------------------- // ?>
