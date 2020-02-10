<?php // ------------------------------------------------------------------------------------------------------------------------ //

defined('ABSPATH') or die('Accesss not allowed.');

/* 
Author:            Jim Schwanda
Author URI:        https://www.usi2solve.com/leader
Description:       The Media-Solutions plugin enables WordPress media to be stored and organized via user created upload folders, tags and categories. The Media-Solutions plugin is developed and maintained by Universal Solutions.
Donate link:       https://www.usi2solve.com/donate/media-solutions
License:           GPL-3.0
License URI:       https://github.com/jaschwanda/media-solutions/blob/master/LICENSE.md
Plugin Name:       Media-Solutions
Plugin URI:        https://github.com/jaschwanda/media-solutions
Requires at least: 5.0
Requires PHP:      5.6.25
Tested up to:      5.3.2
Text Domain:       usi-media-solutions
Version:           1.1.0
*/

/*
Media-Solutions is free software: you can redistribute it and/or modify it under the terms of the GNU General Public 
License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 
Media-Solutions is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License along with Media-Solutions. If not, see 
https://github.com/jaschwanda/media-solutions/blob/master/LICENSE.md

Copyright (c) 2020 by Jim Schwanda.
*/

// Folder organize code only load if option given;
// Test user roles on create folder and upload media;
// Create folder page should only avaiable when create folder options active;
// Orgnie by folder must create folder root post to seed add folder;

// Test upload media;
// Delete media
// Reload media

// Add file size to library columns
// Select tags on Add New page.
// Usage report
// PHPMailer - PHP email creation and transport class.

// https://codex.wordpress.org/Plugin_API/Filter_Reference

// http://shibashake.com/wordpress-theme/add-admin-columns-in-wordpress
// http://technet.weblineindia.com/web/technical-guide-to-wordpress-settings-api/
// http://technet.weblineindia.com/web/wordpress-settings-api-simple-implementation-example/
// http://www.wpbeginner.com/wp-tutorials/how-to-create-custom-post-types-in-wordpress/

class USI_Media_Solutions {

   const VERSION = '1.1.0 (2020-02-08)';

   const NAME       = 'Media-Solutions';
   const POSTFOLDER = 'usi-media-folders';
   const PREFIX     = 'usi-media';
   const TEXTDOMAIN = 'usi-media-solutions';

   public static $capabilities = array(
      'create-folders' => 'Create Upload Folders',
   );

   public static $options = array();

   function __construct() {

      if (empty(USI_Media_Solutions::$options)) {
         $defaults['preferences']['organize-category']   =
         $defaults['preferences']['organize-folder']     =
         $defaults['preferences']['organize-allow-root'] =
         $defaults['preferences']['organize-tag']        =
         $defaults['capabilities']['create-folders']     = false;
         USI_Media_Solutions::$options = get_option(self::PREFIX . '-options', $defaults);
      }

      add_action('init', array($this, 'action_init'));

   } // __construct();

   private static function action_admin_notices() {
      global $pagenow;
      if ('plugins.php' == $pagenow) {
        $text = sprintf(
           __('The %s plugin is required for the %s plugin to run properly.', self::TEXTDOMAIN), 
           '<b>WordPress-Solutions</b>',
           '<b>Media-Solutions</b>'
        );
        echo '<div class="notice notice-warning is-dismissible"><p>' . $text . '</p></div>';
      }
   } // action_admin_notices();

   function action_init() {

      $args = array(
         'capability_type'    => 'post',
         'has_archive'        => true,
         'hierarchical'       => true,
         'labels'             => null,
         'menu_position'      => null,
         'public'             => false,
         'publicly_queryable' => false,
         'query_var'          => false,
         'show_in_menu'       => false,
         'show_in_admin_bar'  => false,
         'show_ui'            => false,
         'supports'           => array('author', 'title')
      );
   
      register_post_type(self::POSTFOLDER, $args);

   } // action_init();

   public static function folder_create_post($parent_id, $folder, $path_folder, $description) {

      $post = array(
         'comment_status'=> 'closed',
         'guid'          => $_SERVER['SERVER_NAME'] . $path_folder,
         'ping_status'   => 'closed',
         'post_author'   => get_current_user_id(),
         'post_content'  => $description,
         'post_name'     => $folder,
         'post_parent'   => $parent_id,
         'post_status'   => 'publish',
         'post_title'    => $path_folder,
         'post_type'     => self::POSTFOLDER,
      );

      $post_id = wp_insert_post($post, true);

      return($post_id);

   } // folder_create_post()

} // Class USI_Media_Solutions;

new USI_Media_Solutions();

if (!function_exists('usi_is_role_equal_or_greater')) { 
   function usi_is_role_equal_or_greater($required_role) {
      $required_role = strtolower($required_role);
      if (($user = wp_get_current_user()) && !empty($user->roles[0])) {
         $user_role = strtolower($user->roles[0]);
         $roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
         $required_found = $user_found = false;
         foreach ($roles as $role) {
            // Return user role so function can be used to set menu capabilites;
            if ($user_found = $user_found || ($user_role == $role)) return($user_role);
            if ($required_found = $required_found || ($required_role == $role)) return(false);
         }
      }
      return(false);
   } // usi_is_role_equal_or_greater();
}

if (is_admin() && !defined('WP_UNINSTALL_PLUGIN')) {
   add_action('init', 'add_thickbox');
   if (is_dir(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions')) {
      require_once('usi-media-solutions-settings.php');
      if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
         require_once('usi-media-solutions-folder.php');
      }
      if (!empty(USI_Media_Solutions::$options['updates']['git-update'])) {
         require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-update.php');
         new USI_WordPress_Solutions_Update_GitHub(__FILE__, 'jaschwanda', 'media-solutions');
      }
   } else {
      add_action('admin_notices', array('USI_Media_Solutions', 'action_admin_notices'));
   }
}

// --------------------------------------------------------------------------------------------------------------------------- // ?>