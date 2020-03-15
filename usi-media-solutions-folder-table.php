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

if (!class_exists('WP_List_Table')) { require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php'); }

final class USI_Media_Solutions_Folder_Table_New extends WP_List_Table {

   const VERSION = '2.1.0 (2020-02-21)';

   private $all_categories = null;
   private $page_hook = null;
   private $page_slug = 'usi-media-folder-list';

   function __construct() {

      add_action('admin_head', array($this, 'action_admin_head'));
      add_action('admin_menu', array($this, 'action_admin_menu'));

      add_filter('set-screen-option', array($this, 'filter_set_screen_options'), 10, 3);

   } // __construct();

   function action_admin_head() {

      if($this->page_slug != ((isset($_GET['page'])) ? $_GET['page'] : '')) return;

      $columns = array(
         'id'       => 10, 
         'variable' => 15, 
         'value'    => 15, 
         'notes'    => 15, 
         'owner'    => 10, 
      );

      $hidden = $this->get_hidden_columns();

      foreach ($hidden as $hide) {
         unset($columns[$hide]);
      }

      $total = 0;
      foreach ($columns as $width) { 
         $total += $width;
      }

      echo '<style>' . PHP_EOL;
      foreach ($columns as $name => $width) { 
         $percent = number_format(100 * $width / $total, 1);
         echo '.wp-list-table .column-' . $name . '{overflow:hidden; text-overflow:ellipsis; white-space:nowrap; width:' . 
            $percent . '%;}' . PHP_EOL;
      }
      echo '</style>' . PHP_EOL;

   } // action_admin_head();

   function action_admin_menu() {

      $text = __('Upload Folders', USI_Media_Solutions::TEXTDOMAIN);

      $this->page_hook = add_media_page(
         $text, // Text displayed in title tags of page when menu is selected;
         $text, // Text displayed in menu bar;
         USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'view-folders'), // The capability required to enable page;
         $this->page_slug, // Unique slug to of this menu; 
         //USI_Media_Solutions::MENUFOLDER, // Menu page slug name;
         array($this, 'render_page') // Function called to render page content;
         //'usi_MM_upload_folders_page' // Function called to render page content;
      );

      add_action('load-' . $this->page_hook, array($this, 'action_load_screen_options'));
   
   } // action_admin_menu();

   function action_load_screen_options() {

      $option = 'per_page';

      $args = array(
         'label' => __('Variables per page', USI_Media_Solutions::TEXTDOMAIN),
         'default' => 20,
         'option' => $option,
      );

      add_screen_option($option, $args);

      parent::__construct( 
         array(
            'singular' => __('variable', USI_Media_Solutions::TEXTDOMAIN), 
            'plural' => __('variables', USI_Media_Solutions::TEXTDOMAIN),
            'ajax' => false,
         ) 
      );

   } // action_load_screen_options();

   function column_cb($item) {

      return('<input class="usi-media-folder-list"' .
         ' data-id="' . esc_attr($item['variable_id']) . '"' .
         ' data-name="' . esc_attr($item['variable']) . '"' .
         ' data-value="' . esc_attr($item['value']) . '"' .
         ' name="variable_id[]" type="checkbox" value="' . $item['variable_id'] .'" />'
      );

    } // column_cb();

   function column_default($item, $column_name) {

      switch($column_name) { 
      case 'variable_id':
      case 'notes':
      case 'owner':
      case 'value':
      case 'variable':
         return $item[$column_name];
      default:
         return(print_r($item, true)); //Show the whole array for troubleshooting purposes
      }

   } // column_default();

   function column_variable($item) {

      $actions = array();
/*
      if (USI_Media_Solutions_Admin::$variables_change || USI_Media_Solutions_Admin::$variables_edit) {
         $actions['edit'] = '<a href="options-general.php?page=usi-media-folder-list&variable_id=' .
            $item['variable_id'] . '">' . __('Edit', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
      }
      if (USI_Media_Solutions_Admin::$variables_delete) {
         $actions['delete'] = '<a' .
            ' class="thickbox usi-media-folder-list-delete-link"' .
            ' data-id="' . esc_attr($item['variable_id']) . '"' .
            ' data-name="' . esc_attr($item['variable']) . '"' .
            ' data-value="' . esc_attr($item['value']) . '"' .
            ' href=""' .
            '">' . __('Delete', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
      }
*/
      return($item['variable'] . ' ' . $this->row_actions($actions));

   } // column_variable();

   function filter_set_screen_options($status, $option, $value) {

      if ('per_page' == $option) return($value);

      return($status);

   } // filter_set_screen_options();

   function get_bulk_actions() {

      return(
         array(
            'delete' => __('Delete', USI_Media_Solutions::TEXTDOMAIN),
         )
      );

   } // get_bulk_actions();

   function get_columns() {

      return(
         array(
            'cb' => '<input type="checkbox" />',
            'variable_id' => __('ID', USI_Media_Solutions::TEXTDOMAIN),
            'variable' => __('Variable', USI_Media_Solutions::TEXTDOMAIN),
            'value' => __( 'Value', USI_Media_Solutions::TEXTDOMAIN),
            'notes' => __('Notes', USI_Media_Solutions::TEXTDOMAIN),
            'owner' => __('Owner', USI_Media_Solutions::TEXTDOMAIN),
         )
      );

    } // get_columns();

   public function get_hidden_columns() {

      return((array)get_user_option('manage' . $this->page_hook . 'columnshidden'));

   } // get_hidden_columns();

   function get_list() {

      global $wpdb;

      $paged = (int)(isset($_GET['paged']) ? $_GET['paged'] : 1);

      $SAFE_order = (isset($_GET['order'])) ? (('desc' == strtolower($_GET['order'])) ? 'DESC' : '') : '';
      $WILD_orderby = (isset($_GET['orderby']) ? $_GET['orderby'] : '');
      switch ($WILD_orderby) {
      default: $SAFE_orderby = 'variable_id` ' . $SAFE_order . ', `variable_id'; break;
      case 'notes': 
      case 'owner': 
      case 'variable': $SAFE_orderby = $WILD_orderby;
      }

      $SAFE_orderby = 'ORDER BY `' . $SAFE_orderby . '` ' . $SAFE_order;
      $SAFE_search = ((isset($_POST['s']) && ('' != $_POST['s'])) ? $wpdb->prepare(' AND (`variable` = %s)', $_POST['s']) : '');

      $current_page = $this->get_pagenum();
      $SAFE_per_page = (int)$this->get_items_per_page('per_page', 20);
      $SAFE_skip = (int)($SAFE_per_page * ($paged - 1));

      $SAFE_variables_table = $wpdb->prefix . 'USI_variables';
      $count_of_records = $wpdb->get_var("SELECT COUNT(*) FROM `$SAFE_variables_table` WHERE (`variable_id` > 1)$SAFE_search");

      $SAFE_users_table = $wpdb->prefix . 'users';
      $this->items = $wpdb->get_results(
         "SELECT `variable_id`, `variable`, `value`, `display_name` as `owner`, " .
         "`$SAFE_variables_table`.`notes` FROM `$SAFE_variables_table`" .
         " INNER JOIN `$SAFE_users_table` ON `$SAFE_users_table`.`ID` = `$SAFE_variables_table`.`user_id`" . 
         " WHERE (`variable_id` > 1)$SAFE_search $SAFE_orderby LIMIT $SAFE_skip,$SAFE_per_page", ARRAY_A);

      $this->set_pagination_args(
         array(
            'total_items' => $count_of_records,
            'per_page' => $SAFE_per_page,
            'total_pages' => ceil($count_of_records / $SAFE_per_page),
         )
      );

   } // get_list();

   function get_sortable_columns() {

      return(
         array(
            'variable_id' => array('variable_id', true),
            'variable' => array('variable', false),
            'notes' => array('notes', false),
            'owner' => array('owner', false),
         )
      );

   } // get_sortable_columns();

   function no_items() {

      _e('No folders have been configured.', USI_Media_Solutions::TEXTDOMAIN);

    } // no_items();

   function prepare_items() {

      $columns = $this->get_columns();
      $hidden = $this->get_hidden_columns();
      $sortable = $this->get_sortable_columns();
      $this->_column_headers = array($columns, $hidden, $sortable);
      $this->get_list();

   } //prepare_items():

   function render_page() {

      global $wpdb;

      $action = $this->current_action();

      if ('delete' == $action) {
/*
         if (USI_Media_Solutions_Admin::$variables_delete) {
            $SAFE_variable_table = $wpdb->prefix . 'USI_variables';
            $ids = isset($_REQUEST['variable_id']) ? explode(',', $_REQUEST['variable_id']) : array();
            $variables_deleted = count($ids);
            if (is_array($ids)) $ids = implode(',', $ids);
            if (!empty($ids)) {
               $wpdb->query("DELETE FROM `$SAFE_variable_table` WHERE (`variable_id` IN($ids))");
            } else {
               $variables_deleted = 0;
            }
            $delete_text = ((1 == $variables_deleted) ? __('One variable has been deleted', USI_Media_Solutions::TEXTDOMAIN) : 
               sprintf(__('%d variables have been deleted', USI_Media_Solutions::TEXTDOMAIN), $variables_deleted));
         } else {
            $delete_text =  __('You do not have permission to delete variables', USI_Media_Solutions::TEXTDOMAIN);
         }
         $message = '<div class="updated below-h2 notice is-dismissible" id="message"><p>' . $delete_text . '.</p>' .
            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' .
            __('Dismiss this notice', USI_Media_Solutions::TEXTDOMAIN) . '.</span></button></div>';
*/
      } else {
         $message = null;
      }
?>

<!-- usi-media-solutions:render_page:begin ---------------------------------------------------------------------------------- -->
<div class="wrap">
  <h2><?php 
   _e('Upload Folders', USI_Media_Solutions::TEXTDOMAIN); 
   if (current_user_can(USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'create-folders')))
      echo ' <a class="add-new-h2" href="admin.php?page=usi-media-folder-add-settings">' . 
         __('Add Upload Folder', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
  ?></h2>
  <?php if ($message) echo $message . PHP_EOL;?>
  <form action="" method="post" name="usi-media-folder-list">
    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'];?>">
<?php
      $this->prepare_items(); 
      $this->search_box('search', 'search_id');
      $this->display(); 
?>
  </form>
</div>
<div id="usi-media-folder-list-confirm" style="display:none;"></div>
<script>
jQuery(document).ready(
   function($) {

      var text_confirm_prefix = '<?php _e("Please confirm that you want to delete the following variable(s)", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_confirm_suffix = '<?php _e("This deletion is permanent and cannot be reversed", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_cancel = '<?php _e("Cancel", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_delete = '<?php _e("Delete", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_ok     = '<?php _e("Ok", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_please_action   = '<?php _e("Please select a bulk action before you click the Apply button.", USI_Media_Solutions::TEXTDOMAIN);?>';
      var text_please_variable = '<?php _e("Please select one or more variables before you click the Apply button.", USI_Media_Solutions::TEXTDOMAIN);?>';

      function do_action() {

         var ids = $('.usi-media-folder-list');
         var id_list = '';
         var text = '';

         if ('delete' != $('#bulk-action-selector-top').val()) {
            text = text_please_action;
         } else {
            var delete_count = 0;
            for (var i = 0; i < ids.length; i++) {
               if (ids[i].checked) {
                  id_list += (id_list.length ? ',' : '') + ids[i].getAttribute('data-id');
                  text += (delete_count++ ? '<br/>' : '') + ids[i].getAttribute('data-name') + ' = ' + ids[i].getAttribute('data-value');
               }
            }
            if (!delete_count) {
               text = text_please_variable;
            }
         }

         return(show_confirmation(delete_count, id_list, text));

      } // do_action();

      function show_confirmation(count_of_variables, id, text) {

         var html = '<p>';

         if (count_of_variables) {
            html += text_confirm_prefix + ':</p><p>' + text + '</p><p>' + text_confirm_suffix + '.';
         } else {
            html += text;
         }

         html += '</p><hr/><p>';

         if (count_of_variables) html += 
            '<a class="button" href="?page=usi-media-folder-list&action=delete&variable_id=' +
            id + '">' + text_delete + '</a> &nbsp; ';

         html += '<a class="button" href="" onclick="tb_remove()">' +
            (count_of_variables ? text_cancel : text_ok) + '</a>';

         $('#usi-media-folder-list-confirm').html(html);

         tb_show('Variable-Solutions', '#TB_inline?width=500&height=300&inlineId=usi-media-folder-list-confirm', null);

         return(false);

      } // show_confirmation();

      $('#doaction').click(do_action); 

      $('#doaction2').click(do_action); 

      $('.usi-media-folder-list-delete-link').click(
         function(event) {
            var obj = event.target;
            var id = obj.getAttribute('data-id');
            var text = obj.getAttribute('data-name') + ' = ' + obj.getAttribute('data-value');
            return(show_confirmation(1, id, text));
         }
      );

   }
);
</script>
<!-- usi-media-solutions:render_page:end ------------------------------------------------------------------------------------ -->
<?php
   } // render_page();

} // Class USI_Media_Solutions_Folder_Table_New;

new USI_Media_Solutions_Folder_Table_New();

// --------------------------------------------------------------------------------------------------------------------------- // ?>