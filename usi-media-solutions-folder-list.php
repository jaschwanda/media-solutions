<?php // ------------------------------------------------------------------------------------------------------------------------ //

// http://wordpress.stackexchange.com/questions/109955/custom-table-column-sortable-by-taxonomy-query

if (!class_exists('USI_List_Table')) {
   require_once('usi-list-table.php');
}

class USI_Media_Solutions_Folder_List extends USI_List_Table {

   const VERSION = '1.1.0 (2020-02-08)';

   public function __construct() {
      parent::__construct(
         array(
            'singular' => __('Folder', USI_Media_Solutions::TEXTDOMAIN), 
            'plural' => __('Folders', USI_Media_Solutions::TEXTDOMAIN),
            'ajax' => false
         )
      );
   } // __construct();

   public function column_default($item, $column_name) {
      switch($column_name) {
      case 'display_name':
      case 'post_content':
         return($item[$column_name]);
      case 'id': return($item['ID']);
      case 'post_title':
         return('<a href="upload.php?guid=' . rawurlencode($item[$column_name]) . '">' .  $item[$column_name] . '</a>');
      }
      return(print_r($item, true)) ;
   } // column_default();
 
   public function get_columns() {
      return(self::get_columns_static());
   } // get_columns();
 
   public static function get_columns_static() {
      return(
         array(
            'post_title' => 'Folder',
            'post_content' => 'Description',
            'display_name' => 'Author',
            'id' => 'Id',
         )
      );
   } // get_columns_static();
 
   public static function get_hidden_columns() {
      global $usi_MM_upload_folders_hook;
      return((array)get_user_option('manage' . $usi_MM_upload_folders_hook . 'columnshidden'));
   } // get_hidden_columns();
 
   public function get_sortable_columns() {
      return(
         array(
            'display_name' => array('display_name', false),
            'id' => array('ID', false),
            'post_content' => array('post_content', false),
            'post_title' => array('post_title', false),
         )
      );
   } // get_sortable_columns();

   public function no_items() {
      _e('No upload folders have been created.');
   } // no_items():

   public function prepare_items() {
      global $wpdb;

      $columns  = $this->get_columns();
      $hidden   = $this->get_hidden_columns();
      $sortable = $this->get_sortable_columns();
      $this->_column_headers = array($columns, $hidden, $sortable);

      $current_page = $this->get_pagenum();
      $total_items = $wpdb->get_var(
         "SELECT COUNT(*) FROM `{$wpdb->posts}`" .
         " WHERE (`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "') OR (`post_type` = 'usi-ms-upload-folder')"
      );
      $SAFE_per_page = (int)$this->get_items_per_page('usi_mm_option_upload_folders_per_page', 20);
      $SAFE_offset = ($current_page - 1) * $SAFE_per_page;
 
      $this->set_pagination_args(array('total_items' => $total_items, 'per_page' => $SAFE_per_page));

      $SAFE_order = (!empty($_REQUEST['order']) && ('desc' == $_REQUEST['order'])) ? 'desc' : 'asc';
      $order_by = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'post_title';
      switch ($order_by) {
      default: $SAFE_order_by = 'post_title'; break;
      case 'display_name':
      case 'ID':
      case 'post_content': $SAFE_order_by = $order_by; break;
      }

      $this->items = $wpdb->get_results(
         "SELECT `{$wpdb->posts}`.`ID`, `{$wpdb->posts}`.`post_content`, `{$wpdb->posts}`.`post_title`, `{$wpdb->users}`.`display_name` FROM `{$wpdb->posts}`" .
         " INNER JOIN `{$wpdb->users}` ON (`{$wpdb->users}`.`ID` = `{$wpdb->posts}`.`post_author`)" .
         " WHERE (`{$wpdb->posts}`.`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "') OR (`{$wpdb->posts}`.`post_type` = 'usi-ms-upload-folder')" .
         " ORDER BY $SAFE_order_by $SAFE_order LIMIT $SAFE_offset, $SAFE_per_page", 
         ARRAY_A
      );

   } // prepare_items():

} // Class USI_Media_Solutions_Folder_List;

function usi_MM_post_clauses($clauses, $wp_query){
   global $pagenow;
   if ('upload.php' == $pagenow) {
      if (isset($_GET['orderby'])) {
         if ('guid' == $_GET['orderby']) {
            // Not sure why this is needed, a WP bug perhaps?
            $clauses['orderby'] = str_replace('post_date', 'guid', $clauses['orderby']);
         }
      }
   }
    return($clauses);
} // usi_MM_post_clauses();

function usi_MM_posts_where($where, $wp_query) {
   global $pagenow, $wpdb;
   if ('upload.php' == $pagenow) {
      if (isset($_GET['guid'])) {
         $guid = 'http' . ($_SERVER['HTTPS'] ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . rawurldecode($_GET['guid']);
         $where .= " AND ({$wpdb->posts}.`guid` LIKE '$guid%')";
      }
   }
   return($where);
} // usi_MM_posts_where();

function usi_MM_upload_folders_page() {
   global $usi_mm_options;
   $folders = new USI_Media_Solutions_Folder_List();
   $folders->prepare_items();
?>
  <div class="wrap">
    <h2>
    <?php
    $title = __('Upload Folders');
    echo esc_html($title);
    if (current_user_can(USI_WordPress_Solutions_Capabilities::capability_slug(USI_Media_Solutions::PREFIX, 'create-folders'))) { ?>
      <a href="admin.php?page=usi-media-folder-add-settings" class="add-new-h2"><?php echo esc_html_x('Add Upload Folder', 'folder'); ?></a><?php
    }
    ?>
    </h2>
    <div class="meta-box-sortables ui-sortable">
      <form method="post">                
<?php $folders->display(); ?>
      </form>
    </div>
  </div>
<?php

} // usi_MM_upload_folders_page();

function usi_MM_upload_folders_screen_options_load() {
   $args = array(
      'label' => 'Number of upload folders per page',
      'default' => 20,
      'option' => 'usi_mm_option_upload_folders_per_page'
   );
   add_screen_option('per_page', $args);
} // usi_MM_upload_folders_screen_options_load()

function usi_MM_upload_folders_screen_options_set($status, $option, $value) {
   if ('usi_mm_option_upload_folders_per_page' == $option) return($value);
   return($status);
} // usi_MM_upload_folders_screen_options_set();

add_filter('posts_clauses', 'usi_MM_post_clauses', 10, 2);
add_filter('posts_where', 'usi_MM_posts_where', 10, 2 );

// This filter fires early, doing it in the class is too late;
add_filter('set-screen-option', 'usi_MM_upload_folders_screen_options_set', 10, 3);

// --------------------------------------------------------------------------------------------------------------------------- // ?>