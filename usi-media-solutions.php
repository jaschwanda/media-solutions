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
Text Domain:       usi-media-solutions
Version:           2.0.0
*/

if (!class_exists('USI')) goto END_OF_FILE;

class USI_Media_Solutions {

   const VERSION = '2.0.0 (2024-06-16)';

   const NAME       = 'Media-Solutions';
   const PREFIX     = 'usi-media';
   const TEXTDOMAIN = 'usi-media-solutions';

   const MEDIAFOLD  = 'usi-media-fold';
   const MENUFOLDER = 'usi-media-folders-list';
   const POSTFOLDER = 'usi-media-folders';
   const USERFOLDER = 'usi-media-options-folder';

   const DEBUG_OFF     = 0x12000000;
   const DEBUG_FOLDER  = 0x12000001;
   const DEBUG_MANAGE  = 0x12000002;
   const DEBUG_SANITZ  = 0x12000004;

   const OK_IMAGES     = ['gif', 'jpg', 'jpeg', 'png'];

   public static $capabilities = [
      'create-categories' => 'Create Categories|administrator',
      'view-folders' => 'View Upload Folders|administrator|editor',
      'create-folders' => 'Create Upload Folders|administrator',
      'create-tags' => 'Create Tags|administrator',
      'manage-media' => 'Manage Media|administrator',
      'delete-media' => 'Delete Media|administrator',
      'reload-media' => 'Reload Media|administrator',
      'rename-media' => 'Rename Media|administrator',
   ];

   public static $debug   = 0;

   public static $options = [];

   function __construct() {

      if (empty(USI_Media_Solutions::$options)) {

         $defaults['preferences']['organize-allow-default'] =
         $defaults['preferences']['organize-category']   =
         $defaults['preferences']['organize-folder']     =
         $defaults['preferences']['organize-allow-root'] =
         $defaults['preferences']['organize-tag']        = 
         $defaults['preferences']['library-author']      =
         $defaults['preferences']['library-show-notes']  = 
         $defaults['preferences']['library-show-upload'] =
         $defaults['preferences']['library-show-fold']   =
         $defaults['preferences']['library-show-size']   = false;

         $defaults['uploads']['upload-max-filesize']     =
         $defaults['uploads']['post-max-size']           = 0;
         $defaults['uploads']['delete-backups']          = false;

         $defaults['debug']['debug-ip'] = '';
         $defaults['debug']['debug-file-info']     =
         $defaults['debug']['debug-filter-folder'] = false;

         USI_Media_Solutions::$options = get_option(self::PREFIX . '-options', $defaults);

      }

      add_action('init', [$this, 'action_init']);

      if (!empty(self::$options['preferences']['library-author'])) {
         add_action('restrict_manage_posts', [$this, 'action_restrict_manage_posts']);
      }

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

      $args = [
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
         'supports'           => ['author', 'title']
      ];
   
      register_post_type(self::POSTFOLDER, $args);

   } // action_init();

   function action_restrict_manage_posts() {
      global $pagenow;
      if ('upload.php' == $pagenow) {
         $author = filter_input(INPUT_GET, 'author', FILTER_SANITIZE_STRING);
         $args   = [
            'name'               => 'author',
            'option_none_value'  => 0,
            'selected'           => (int)$author > 0 ? $author : 0,
            'show_option_none'   => __('All Authors', self::TEXTDOMAIN),
         ];
         wp_dropdown_users($args);
         echo ' &nbsp; ';
      }
   } // action_restrict_manage_posts();

   public static function folder_create_post($parent_id, $folder, $path_folder, $description) {

      $post = [
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
      ];

      $post_id = wp_insert_post($post, true);

      return $post_id;

   } // folder_create_post()

} // Class USI_Media_Solutions;

new USI_Media_Solutions();

if (is_admin() && !defined('WP_UNINSTALL_PLUGIN')) {
   new USI_Media_Solutions_Settings();
}

if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
   new USI_Media_Solutions_Folder();
}

END_OF_FILE: // -------------------------------------------------------------------------------------------------------------- // ?>