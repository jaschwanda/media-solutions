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

// https://premium.wpmudev.org/blog/wordpress-admin-tables/
// https://premiumcoding.com/wordpress-tutorial-how-to-extend-wp-list-table/
// http://wordpress.stackexchange.com/questions/109955/custom-table-column-sortable-by-taxonomy-query
// https://fullstackgeek.blogspot.com/2019/08/calculate-directory-size-in-php.html

if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

require_once(plugin_dir_path(__DIR__) . 'usi-wordpress-solutions/usi-wordpress-solutions-static.php');

final class USI_Media_Solutions_Folder_List extends WP_List_Table {

   const VERSION = '1.3.0 (2023-06-30)';

   private $all_categories = null;
   private $page_hook = null;

   function __construct() {

      if (!empty($_GET['page']) && (USI_Media_Solutions::MENUFOLDER == $_GET['page'])) {
         add_action('admin_head', [$this, 'action_admin_head']);
         add_filter('page_row_actions', [$this, 'filter_media_row_actions'], 10, 2);
         add_filter('set-screen-option', [$this, 'filter_set_screen_options'], 10, 3);
      }

      add_action('admin_menu', [$this, 'action_admin_menu']);

   } // __construct();

   function action_admin_head() {

      $columns = [
         'cb'          => 3, 
         'folder_id'   => 5, 
         'folder'      => 20, 
         'description' => 20, 
         'files'       => 10, 
         'size'        => 10, 
         'owner'       => 10, 
      ];

      echo USI_WordPress_Solutions_Static::column_style($columns, 'overflow:hidden; text-overflow:ellipsis; white-space:nowrap;');

   } // action_admin_head();

   function action_admin_menu() {

      $text = __('Upload Folders', USI_Media_Solutions::TEXTDOMAIN);

      $this->page_hook = add_media_page(
         $text, // Text displayed in title tags of page when menu is selected;
         $text, // Text displayed in menu bar;
         USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'view-folders'), // The capability required to enable page;
         USI_Media_Solutions::MENUFOLDER, // Unique slug to of this menu; 
         [$this, 'render_page'] // Function called to render page content;
      );

      add_action('load-' . $this->page_hook, [$this, 'action_load_screen_options']);
   
   } // action_admin_menu();

   function action_load_screen_options() {

      $option = 'per_page';

      $args = [
         'label'   => __('Folders per page', USI_Media_Solutions::TEXTDOMAIN),
         'default' => 20,
         'option'  => $option,
      ];

      add_screen_option($option, $args);

      parent::__construct( 
         [
            'singular' => __('folder', USI_Media_Solutions::TEXTDOMAIN), 
            'plural'   => __('folders', USI_Media_Solutions::TEXTDOMAIN),
            'ajax'     => false,
         ] 
      );

   } // action_load_screen_options();

   function column_cb($item) {

      return('<input class="usi-media-folders-list"' .
         ' data-id="' . esc_attr($item['folder_id']) . '"' .
         ' data-name="' . esc_attr($item['folder']) . '"' .
         ' name="folder_id[]" type="checkbox" value="' . $item['folder_id'] .'" />'
      );

    } // column_cb();

   function column_default($item, $column_name) {

      switch($column_name) { 
      case 'files':
      case 'folder_id':
      case 'description':
      case 'owner':
      case 'size':
         return($item[$column_name]);
      case 'folder':
         return('<a href="upload.php?guid=' . rawurlencode($item[$column_name]) . '">' .  $item[$column_name] . '</a>');
      default:
         return(print_r($item, true)); //Show the whole array for troubleshooting purposes
      }

   } // column_default();

   function column_folder($item) {
      $actions = [];
      return('<a href="upload.php?guid=' . rawurlencode($item['folder']) . '">' .  $item['folder'] . '</a>' . ' ' . $this->row_actions($actions));

   } // column_variable();

   function filter_media_row_actions($actions, $object) {
      $new_actions = [];
      $new_actions = $actions;
      return($new_actions);
   } // filter_media_row_actions()

   function filter_set_screen_options($status, $option, $value) {

      if ('per_page' == $option) return($value);

      return($status);

   } // filter_set_screen_options();

   function get_bulk_actions() {

      return(
         [
            'delete' => __('Delete', USI_Media_Solutions::TEXTDOMAIN),
         ]
      );

   } // get_bulk_actions();

   function get_columns() {

      return(
         [
            'cb' => '<input type="checkbox" />',
            'folder_id' => __('ID', USI_Media_Solutions::TEXTDOMAIN),
            'folder' => __('Folder', USI_Media_Solutions::TEXTDOMAIN),
            'description' => __('Description', USI_Media_Solutions::TEXTDOMAIN),
            'files' => __('Files', USI_Media_Solutions::TEXTDOMAIN),
            'size' => __('Size', USI_Media_Solutions::TEXTDOMAIN),
            'owner' => __('Owner', USI_Media_Solutions::TEXTDOMAIN),
         ]
      );

    } // get_columns();

   public function get_columns_hidden() {

      return((array)get_user_option('manage' . $this->page_hook . 'columnshidden'));

   } // get_columns_hidden();

   function get_columns_sortable() {

      return(
         [
            'folder_id'   => ['folder_id', true],
            'folder'      => ['folder', true],
            'description' => ['description', true],
            'owner'       => ['owner', true],
         ]
      );

   } // get_columns_sortable();

   // Returns array: number of files, total size of folder;
   function get_folder_info($folder) {
      $file_count  = 0;
      $total_size  = 0;
      @ $folder_list = scandir($folder);

      if (!empty($folder_list)) {
         foreach ($folder_list as $key => $file_name) {
            if ($file_name != ".." && $file_name != ".") {
               if (is_dir($folder . DIRECTORY_SEPARATOR . $file_name)) {
                  $folder_info = $this->get_folder_info($folder . DIRECTORY_SEPARATOR . $file_name);
                  if (!empty($folder_info[1])) {
                     $file_count += $folder_info[0];
                     $total_size += $folder_info[1];
                  }
               } else if (is_file($folder . DIRECTORY_SEPARATOR. $file_name)) {
                  $file_count++;
                  $total_size += filesize($folder. DIRECTORY_SEPARATOR. $file_name);
               }
            }
         }
      }
      return([$file_count, $total_size]);
   } // get_folder_info();

   function get_list() {

      global $wpdb;

      $paged = (int)(isset($_GET['paged']) ? $_GET['paged'] : 1);

      $SAFE_where = " WHERE (`{$wpdb->posts}`.`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "')";

      if (empty(USI_Media_Solutions::$options['preferences']['organize-allow-root'])) {
         $SAFE_where .= " AND (`{$wpdb->posts}`.`post_title` <> '/')";
      }

      if (!empty($_POST['s'])) {
         $SAFE_where .= $wpdb->prepare(" AND (`{$wpdb->posts}`.`post_title` = %s)", $_POST['s']);
      }

      $WILD_orderby = (isset($_GET['orderby']) ? $_GET['orderby'] : '');
      switch ($WILD_orderby) {
      default: $SAFE_orderby = '`folder_id`'; break;
      case 'description': 
      case 'files': 
      case 'owner': 
      case 'size': 
      case 'folder': $SAFE_orderby = $WILD_orderby;
      }
      $SAFE_order   = (isset($_GET['order'])) ? (('desc' == strtolower($_GET['order'])) ? 'DESC' : '') : '';

      $current_page = $this->get_pagenum();
      $SAFE_perpage = (int)$this->get_items_per_page('per_page', 20);
      $SAFE_skip    = (int)($SAFE_perpage * ($paged - 1));

      $count_of_records = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->posts}`" . $SAFE_where);

      $this->items = $wpdb->get_results(
         "SELECT `{$wpdb->posts}`.`ID` AS `folder_id`, `{$wpdb->posts}`.`post_content` AS `description`," .
         "`{$wpdb->posts}`.`post_title` AS `folder`, `{$wpdb->users}`.`display_name` AS `owner` FROM `{$wpdb->posts}`" .
         " INNER JOIN `{$wpdb->users}` ON (`{$wpdb->users}`.`ID` = `{$wpdb->posts}`.`post_author`)" .
         "$SAFE_where ORDER BY $SAFE_orderby $SAFE_order LIMIT $SAFE_skip, $SAFE_perpage", 
         ARRAY_A
      );

      foreach ($this->items as $row => $fields) {
         $folder_info = $this->get_folder_info($_SERVER['DOCUMENT_ROOT'] . '/' . $fields['folder']);
         $this->items[$row]['files'] = number_format($folder_info[0]);
         $this->items[$row]['size']  = USI_Media_Solutions_Folder::size_format($folder_info[1]);

      }

      $this->set_pagination_args(
         [
            'total_items' => $count_of_records,
            'per_page'    => $SAFE_perpage,
            'total_pages' => ceil($count_of_records / $SAFE_perpage),
         ]
      );

   } // get_list();

   function no_items() {

      _e('No folders have been configured.', USI_Media_Solutions::TEXTDOMAIN);

    } // no_items();

   function prepare_items() {

      $columns  = $this->get_columns();
      $hidden   = $this->get_columns_hidden();
      $sortable = $this->get_columns_sortable();

      $this->_column_headers = [$columns, $hidden, $sortable];

      $this->get_list();

   } //prepare_items():

   function render_page() {

      global $wpdb;

      $action = $this->current_action();

      if ('delete' == $action) {
      } else {
         $message = null;
      }
?>

<!-- usi-media-solutions:render_page:begin ---------------------------------------------------------------------------------- -->
<div class="wrap">
  <h2><?php 
   _e('Upload Folders', USI_Media_Solutions::TEXTDOMAIN); 
   if (USI_WordPress_Solutions_Capabilities::current_user_can(USI_Media_Solutions::PREFIX, 'create-folders'))
      echo ' <a class="add-new-h2" href="admin.php?page=usi-media-folders-add-settings">' . 
         __('Add Upload Folder', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
  ?></h2>
  <?php if (!empty($message)) echo $message . PHP_EOL;?>
  <form action="" method="post" name="usi-media-folders-list">
    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'];?>">
<?php
      $this->prepare_items(); 
      $this->search_box('search', 'search_id');
      $this->display(); 
?>
  </form>
</div>
<div id="usi-media-folders-list-confirm" style="display:none;"></div>
<script>
jQuery(document).ready(
   function($) {

      var text_confirm_prefix = '<?php _e("Please confirm that you want to delete the following folder(s)", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_confirm_suffix = '<?php _e("This deletion is permanent and cannot be reversed", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_cancel = '<?php _e("Cancel", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_delete = '<?php _e("Delete", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_ok     = '<?php _e("Ok", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_please_action   = '<?php _e("Please select a bulk action before you click the Apply button.", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_please_folder   = '<?php _e("Please select one or more folders before you click the Apply button.", USI_Media_Solutions::TEXTDOMAIN);?>';

      function do_action() {

         var ids     = $('.usi-media-folders-list');
         var id_list = '';
         var text    = '';

         if ('delete' != $('#bulk-action-selector-top').val()) {
            text = text_please_action;
         } else {
            var delete_count = 0;
            for (var i = 0; i < ids.length; i++) {
               if (ids[i].checked) {
                  id_list += (id_list.length ? ',' : '') + ids[i].getAttribute('data-id');
                  text += (delete_count++ ? '<br/>' : '') + ids[i].getAttribute('data-name');
               }
            }
            if (!delete_count) {
               text = text_please_folder;
            }
         }

         return(show_confirmation(delete_count, id_list, text));

      } // do_action();

      function show_confirmation(count_of_folders, id, text) {

         var html = '<p>';

         if (count_of_folders) {
            html += text_confirm_prefix + ':</p><p>' + text + '</p><p>' + text_confirm_suffix + '.';
         } else {
            html += text;
         }

         html += '</p><hr/><p>';

         if (count_of_folders) html += 
            '<a class="button" href="?page=usi-media-folders-list&action=delete&folder_id=' +
            id + '">' + text_delete + '</a> &nbsp; ';

         html += '<a class="button" href="" onclick="tb_remove()">' +
            (count_of_folders ? text_cancel : text_ok) + '</a>';

         $('#usi-media-folders-list-confirm').html(html);

         tb_show('Media-Solutions', '#TB_inline?width=500&height=300&inlineId=usi-media-folders-list-confirm', null);

         return(false);

      } // show_confirmation();

      $('#doaction').click(do_action); 

      $('#doaction2').click(do_action); 

      $('.usi-media-folders-list-delete-link').click(
         function(event) {
            var obj = event.target;
            var id = obj.getAttribute('data-id');
            var text = obj.getAttribute('data-name');
            return(show_confirmation(1, id, text));
         }
      );

   }
);
</script>
<!-- usi-media-solutions:render_page:end ------------------------------------------------------------------------------------ -->
<?php
   } // render_page();

} // Class USI_Media_Solutions_Folder_List;

new USI_Media_Solutions_Folder_List();

// --------------------------------------------------------------------------------------------------------------------------- // ?>