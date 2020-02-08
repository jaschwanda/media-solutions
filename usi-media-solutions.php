<?php // ------------------------------------------------------------------------------------------------------------------------ //
defined('ABSPATH') or die('Accesss not allowed.');
/* 
Plugin Name: Media-Solutions
Plugin URI: https://www.usi2solve.com
Description: The Media-Solutions plugin enables WordPress media to be stored and organized via user created upload folders, tags and categories. The Media-Solutions plugin is developed and maintained by Universal Solutions.
Version: 1.0.0 (2016-08-27)
Author: Jim Schwanda
Author URI: https://www.usi2solve.com/leader
*/

// Media Manager
// 'unique-id-prefix'
// USI_Media_Manager

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

define('usi_ms_version', '1.0.0 (2016-08-27)');

class USI_Media_Solutions {

   const VERSION = 'usi_ms_version';

}

if (false === ($usi_ms_options = get_option('usi-ms-options'))) {
   add_option('usi-ms-options', usi_MM_settings_defaults());
}

function usi_MM_add_ajax_javascript() {
   wp_enqueue_script('ajax_custom_script', plugin_dir_url(__FILE__) . 'usi-media-solutions.js', array('jquery'));
} // usi_MM_add_ajax_javascript();

function usi_MM_attachment_category_filter() {
   global $usi_ms_options;
   $screen = get_current_screen();
   if (!empty($usi_ms_options['folder'])) {
      if ('upload' == $screen->id) {
         global $wpdb;
         $rows = $wpdb->get_results("SELECT `post_title` FROM `{$wpdb->posts}` " .
            " WHERE (`post_type` = 'usi-ms-upload-folder') ORDER BY `post_title`", OBJECT_K);
         $guid = (isset($_GET['guid']) ? 'http' . ($_SERVER['HTTPS'] ? 's' : '') . '://' . 
            $_SERVER['SERVER_NAME'] . rawurldecode($_GET['guid']) : '');
         $guid = (isset($_GET['guid']) ? rawurldecode($_GET['guid']) : '');
         $html = '<select class="postform" id="guid" name="guid"><option value="%">View all upload folders</option>';
         $ignore = (!empty($usi_ms_options['allow_root']) ? '': '/');
         foreach ($rows as $row) {
            if ($ignore == $row->post_title) continue;
            $html .= '<option ' . (($row->post_title == $guid) ? 'selected="selected" ' : '') . 'value="' . 
               $row->post_title . '">' . $row->post_title . '</option>';
         }
         echo $html . '</select>'; 
      }
   }
   if (!empty($usi_ms_options['category'])) {
       if ('upload' == $screen->id) {
          $dropdown_options = array('show_option_all' => __('View all categories', 'usi-media-solutions'), 'hide_empty' => false, 'hierarchical' => true, 'orderby' => 'name');
          wp_dropdown_categories($dropdown_options);
      }
   }
} // usi_MM_attachment_category_filter();

function usi_MM_attachment_register_taxonomy() {
   global $usi_ms_options;
   if (!empty($usi_ms_options['category'])) register_taxonomy_for_object_type('category', 'attachment');
   if (!empty($usi_ms_options['folder'])) {
      global $wpdb;
      if (0 == $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE (`post_type` = 'usi-ms-upload-folder')")) {
         usi_MM_create_folder_post(0, 'Root Folder', '/', 'Root Folder');
      }
   }
   if (!empty($usi_ms_options['tag'])) register_taxonomy_for_object_type('post_tag', 'attachment');
} // usi_MM_attachment_register_taxonomy();

function usi_MM_create_folder_menu_add() {

   //const VERSION = '1.0.0 (2016-08-27)';

   global $usi_ms_options, $usi_MM_upload_folders_hook;

   if (!empty($usi_ms_options['folder'])) {

      $usi_MM_upload_folders_hook = add_media_page(
         'usi-MM-upload-folders', // Text displayed in title tags of page when menu is selected;
         'Upload Folders', // Text displayed in menu bar;
         'upload_files', // The capability required to enable page;
         /* lower case for option; */ 'usi-mm-upload-folders-page', // Menu page slug name;
         'usi_MM_upload_folders_page' // Function called to render page content;
      );

      add_action('load-' . $usi_MM_upload_folders_hook, 'usi_MM_upload_folders_screen_options_load');
      add_filter('get_user_option_manage' . $usi_MM_upload_folders_hook . 'hidden', 'usi_MM_folder_list_table::get_hidden_columns', 10, 3); 
      add_filter('manage_' . $usi_MM_upload_folders_hook . '_columns', 'usi_MM_folder_list_table::get_columns_static', 0);

      add_media_page(
         'usi-MM-create-folders', // Text displayed in title tags of page when menu is selected;
         'Create Folders', // Text displayed in menu bar;
         usi_is_role_equal_or_greater(!empty($usi_ms_options['capability_create_folder']) ? $usi_ms_options['capability_create_folder'] : null), // The capability required to enable page;
         'usi-MM-create-folders-page', // Menu page slug name;
         'usi_MM_create_folder_page' // Function called to render page content;
      );

      remove_submenu_page('upload.php', 'usi-MM-create-folders-page');

   }

   add_media_page(
      'usi-MM-reload-media', // Text displayed in title tags of page when menu is selected;
      'Reload Media', // Text displayed in menu bar;
      'read', // The capability required to enable page;
      'usi-MM-reload-media-page', // Menu page slug name;
      'usi_MM_reload_media_page' // Function called to render page content;
   );

   remove_submenu_page('upload.php', 'usi-MM-reload-media-page');

} // usi_MM_create_folder_menu_add();
 
function usi_MM_create_folder_page() {
?>
  <div class="wrap">
    <h2>Upload Folders</h2>
    <?php settings_errors(); ?>
    <form action="options.php" method="POST">
      <?php settings_fields('usi-MM-create-folder-settings-group'); ?>
      <?php do_settings_sections('usi-MM-create-folder-settings'); ?>
      <?php submit_button('Create Upload Folder'); ?>
    </form>
  </div>
<?php
} // usi_MM_create_folder_page();

function usi_MM_create_folder_post($parent_id, $folder, $path_folder, $description) {
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
      'post_type'     => 'usi-ms-upload-folder',
   );
   $post_id = wp_insert_post($post, true);
   //usi_history('usi_MM_create_folder_post:post_id=' . $post_id);
   return($post_id);
} // usi_MM_create_folder_post()
 
function usi_MM_create_folder_settings() {
   if (false == get_option('usi-ms-options-create-folder')) {
      add_option('usi-ms-options-create-folder', 
         apply_filters('usi_MM_create_folder_settings_defaults', usi_MM_create_folder_settings_defaults()));
   }

   add_settings_section('usi-MM-create-folder-section', null, 
      'usi_MM_create_folder_settings_section_callback', 'usi-MM-create-folder-settings');

   add_settings_field('usi-MM-create-folder-parent', 'Parent', 'usi_MM_create_folder_settings_field_callback', 
      'usi-MM-create-folder-settings', 'usi-MM-create-folder-section', 
      array('field' => 'parent', 'label_for' => 'usi-MM-create-folder-parent'));

   add_settings_field('usi-MM-create-folder-folder', 'Folder', 'usi_MM_create_folder_settings_field_callback', 
      'usi-MM-create-folder-settings', 'usi-MM-create-folder-section', 
      array('field' => 'folder', 'label_for' => 'usi-MM-create-folder-folder'));

   add_settings_field('usi-MM-create-folder-description', 'Description', 'usi_MM_create_folder_settings_field_callback', 
      'usi-MM-create-folder-settings', 'usi-MM-create-folder-section', 
      array('field' => 'description', 'label_for' => 'usi-MM-create-folder-description'));

   register_setting('usi-MM-create-folder-settings-group', 
      'usi-ms-options-create-folder', 'usi_MM_create_folder_settings_validate');

   add_filter('option_page_capability_usi-MM-create-folder-settings-group', 'usi_MM_create_folder_settings_capabilities');
} // usi_MM_create_folder_settings();

function usi_MM_create_folder_settings_capabilities($capability) {
   global $usi_ms_options;
   // Return capability everyone has or only admins;
   return(usi_is_role_equal_or_greater($usi_ms_options['capability_create_folder']) ? 'read' : 'manage_capabilites');
} // usi_MM_create_folder_settings_capabilities();

function usi_MM_create_folder_settings_defaults() {
   $defaults = array(
      'description' => 'description',
      'folder' => 'folder',
      'parent' => 0
   );
   return(apply_filters('usi_MM_create_folder_settings_defaults', $defaults));
} // end usi_MM_create_folder_settings_defaults
 
function usi_MM_create_folder_settings_field_callback($args) {
   $field = $args['field'];
   $options = get_option('usi-ms-options-create-folder');
   if ('parent' != $field) {
      $errors = get_settings_errors();
      $value = (isset($errors[0]['setting']) && 
         (($errors[0]['setting'] == 'usi-MM-create-folder-description') || ($errors[0]['setting'] == 'usi-MM-create-folder-folder')))
         ? esc_attr($options[$field]) : '';
      echo '<input id="usi-MM-create-folder-' . $field . '" name="usi-ms-options-create-folder[' . 
         $field . ']" type="text" value="' . $value . '" />';
   } else {
      global $wpdb;
      $user_id = get_current_user_id();
      $folder_id =  (int)get_user_option('usi-ms-options-upload-folder', $user_id);
      $rows = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = 'usi-ms-upload-folder') ORDER BY `post_title`", OBJECT_K);
      $html = '<select class="regular-text" id="usi-MM-create-folder-parent" name="usi-ms-options-create-folder[parent]">';
      foreach ($rows as $row) {
         $html .= '<option ' . (($row->ID == $folder_id) ? 'selected="selected" ' : '') . 'value="' . 
            $row->ID . '">' . $row->post_title . '</option>';
      }
      echo $html . '</select>'; 
   }
} // usi_MM_create_folder_settings_field_callback();

function usi_MM_create_folder_settings_section_callback() {
} // usi_MM_create_folder_settings_section_callback()

function usi_MM_create_folder_settings_validate($wild) {
   $safe = array();
   $description = $safe['description'] = sanitize_text_field($wild['description']);
   $folder = $safe['folder'] = sanitize_file_name($wild['folder']);
   $parent_id = $safe['parent'] = (int)$wild['parent'];
   if (empty($folder)) {
      add_settings_error('usi-MM-create-folder-folder', esc_attr('error'), 'Please enter a valid folder.', 'error');   
   } else if (empty($description)) {
      add_settings_error('usi-MM-create-folder-description', esc_attr('error'), 'Please enter a valid description.', 'error');   
   } else if (1 > $parent_id) {
      add_settings_error('usi-MM-create-folder-parent', esc_attr('error'), 'Please select a parent folder.', 'error');   
   } else {
      global $wpdb;
      $row = $wpdb->get_row($wpdb->prepare("SELECT `post_title` AS `path` FROM `{$wpdb->posts}` WHERE (`ID` = %d) LIMIT 1", 
         $parent_id), OBJECT);
      if (empty($row)) {
         add_settings_error('usi-MM-create-folder-parent', esc_attr('error'), 'Could not find path for parent folder. [sql=' .
            $wpdb->last_query . ']', 'error');   
      } else {
         $root = trim($_SERVER['DOCUMENT_ROOT'], '/');
         $path = trim($row->path, '/');
         $folder = trim($folder, '/');
         $path_folder = '/' . $path . (strlen($path) ? '/' : '') . $folder;
         ob_start();
         $status = wp_mkdir_p($root . $path_folder);
         $output = ob_get_contents();
         ob_end_clean();
         $folder_message = '<span style="font-family:courier new;"> ' . $path_folder . ' </span>';
         if (!$status) {
            add_settings_error('usi-MM-create-folder-parent', esc_attr('error'), 
               __('Folder', 'usi-media-solutions') . $folder_message . __('could not be created.', 'usi-media-solutions'), 'error');   
         } else {
            $post_id = usi_MM_create_folder_post($parent_id, $folder, $path_folder, $description);
            if (0 < $post_id) {
               add_settings_error('usi-MM-create-folder-parent', esc_attr('updated'), 
                  __('Folder', 'usi-media-solutions') . $folder_message . __('has been created.', 'usi-media-solutions'), 'updated');   
               $parent_id = $post_id;
            } else {
               add_settings_error('usi-MM-create-folder-parent', esc_attr('error'), 
                  __('Folder', 'usi-media-solutions') . $folder_message . __('post could not be created.', 'usi-media-solutions'), 'error');   
            }
         }
      }
   }
   update_user_option(get_current_user_id(), 'usi-ms-options-upload-folder', $parent_id);
   return($safe);
} // usi_MM_create_folder_settings_validate

function usi_MM_get_active() {
   if ($user_id = get_current_user_id()) {
      $usi_mm_option_active = get_user_option('usi_mm_option_active', $user_id);
      if ('plus' != $usi_mm_option_active) {
         update_user_option($user_id, 'usi_mm_option_active', $usi_mm_option_active = 'standard');
      }
   } else {
      $usi_mm_option_active = 'standard';
   }
   return($usi_mm_option_active);
} // usi_MM_get_active();
 
function usi_MM_handle_upload($file){
    remove_filter('upload_dir', 'usi_MM_upload_dir');
    return($file);
} // usi_MM_handle_upload();
 
function usi_MM_handle_upload_prefilter($file){
    add_filter('upload_dir', 'usi_MM_upload_dir');
    return($file);
} // usi_MM_handle_upload_prefilter();

function usi_MM_install() {
} // usi_MM_install();

function usi_MM_library_columns_sortable($columns) {
   global $usi_ms_options;
   if (!empty($usi_ms_options['folder'])) $columns['guid'] = 'guid';
   return($columns);
} // usi_MM_library_columns_sortable();

function usi_MM_library_columns($input) {
   global $usi_ms_options;
   $ith = 0;
   $output = array();
   $skip_author = false;
   foreach ($input as $key => $value) {
      if (3 == $ith++) if (!empty($usi_ms_options['folder'])) $output['guid'] = 'Upload Folder';
      if ('author' == $key) {
         $skip_author = true;
      } else {
         if ($skip_author && ('parent' == $key)) {
            $skip_author = false;
            $output['author'] = 'Author';         
         } 
         $output[$key] = $value;
      }
   }
   return($output);
} // usi_MM_library_columns();

function usi_MM_library_folder_column($column, $id) {
   if ('guid' == $column) {
      $guid = get_post_field('guid', $id);
      $tokens = explode('/', $guid);
      unset($tokens[count($tokens) - 1]);
      unset($tokens[0]);
      unset($tokens[1]);
      unset($tokens[2]);
      $folder = '/' . implode('/', $tokens);
      echo '<a href="upload.php?guid=' . rawurlencode($folder) . '">' .  $folder . '</a>';
   }  
} // usi_MM_library_folder_column();

function usi_MM_media_page_callback() {
   $user_id = get_current_user_id();
   if (empty($_POST['item'])) wp_die('Internal error, item not given');
   switch ($_POST['item']) {
   case 'category':
      if (empty($_POST['id'])) wp_die('Internal error, id not given');
      if (empty($_POST['value'])) wp_die('Internal error, value not given');
      $id = (int)$_POST['id'];
      $tokens = explode(',', get_user_option('usi_mm_option_file_categories', $user_id));
      switch ($_POST['value']) {
      case 'false':
         for ($ith = 0; $ith < count($tokens); $ith++) {
            if ($tokens[$ith] == $id) {
               unset($tokens[$ith]);
               break;
            }
         }
         break;
      case 'true':
         for ($ith = 0; $ith < count($tokens); $ith++) {
            if ($tokens[$ith] == $id) wp_die('good');
         }
         $tokens[] = $id;
         break;
      default:
         wp_die('Internal error, value not valid');
      }
      sort($tokens);
      $option = null;
      foreach ($tokens as $token) {
         $option .= ($option ? ',' : '') . $token;
      }
      update_user_option($user_id, 'usi_mm_option_file_categories', $option);
      break;
   case 'folder':
      if (!isset($_POST['value'])) wp_die('Internal error, value not given');
      $folder_id = (int)$_POST['value'];
      update_user_option($user_id, 'usi-ms-options-upload-folder', $folder_id);
      break;
   default:
      wp_die('Internal error, item is invalid');
   }
   wp_die('good');
} // usi_MM_media_page_callback();

function usi_MM_media_row_action($actions, $object) {
   if (isset($actions['edit'])) $actions['reload_media'] = '<a href="' . 
      admin_url('upload.php?page=usi-MM-reload-media-page&id=' . $object->ID) . '">' . __('Reload', 'usi-media-solutions') . '</a>';
   return($actions);
} // usi_MM_media_row_action()

function usi_MM_plugin_action_links($links, $file) {
   static $this_plugin;
   if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);
   if ($file == $this_plugin) $links[] = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-media.php">Settings</a>';
   return($links);
} // usi_MM_plugin_action_links();

function usi_MM_post_upload_ui() {
   global $usi_ms_options;
  ?>
<div id="poststuff">
<?php
   global $wpdb;
   if (!empty($usi_ms_options['folder'])) {
      $user_id = get_current_user_id();
      $folder_id =  (int)get_user_option('usi-ms-options-upload-folder', $user_id);
      $rows = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = 'usi-ms-upload-folder') ORDER BY `post_title`", OBJECT_K);
      $html = '<select class="regular-text" id="usi_MM_upload_folder"><option value="0">-- Default Upload Folder --</option>';
      $ignore = (!empty($usi_ms_options['allow_root']) ? '': '/');
      foreach ($rows as $row) {
         if ($ignore == $row->post_title) continue;
         $html .= '<option ' . (($row->ID == $folder_id) ? 'selected="selected" ' : '') . 'value="' . 
            $row->ID . '">' . $row->post_title . '</option>';
      }
      $html .= '</select>'; 
  ?>
  <div id="postbox-container-1" class="postbox-container" style="float:left; margin-right:10px; text-align:left; width:30%;">
    <div class="meta-box-sortables">
      <div class="postbox">
        <h3 class="hndle" style="cursor:default;"><span style="cursor:text;"><?php esc_attr_e('Upload Folder', 'wp_admin_style');?></span></h3>
        <div class="inside">
          <?php echo $html;?>
        </div><!--inside-->
      </div><!--postbox-->
    </div><!--meta-box-sortables-->
  </div><!--postbox-container-1-->
<?php
   }
   if (!empty($usi_ms_options['category'])) {
      $user_id = get_current_user_id();
      $selected_categories = explode(',', get_user_option('usi_mm_option_file_categories', $user_id));
  ?>
  <div id="postbox-container-2" class="postbox-container" style="float:left; margin-right:10px; text-align:left; width:30%;">
    <div class="meta-box-sortables">
      <div class="postbox">
        <h3 class="hndle" style="cursor:default;"><span style="cursor:text;">Categories</span></h3>
        <div class="inside">
          <div class="categorydiv">
            <ul id="category-tabs" class="category-tabs">
              <li id="category-all-tab" class="tabs"><a href="#category-all">All Categories</a></li>
              <li id="category-pop-tab" class="hide-if-no-js"><a href="#category-pop">Most Used</a></li>
            </ul>

            <div id="category-pop" class="tabs-panel" style="display:none;">
              <ul id="categorychecklist-pop" class="categorychecklist form-no-clear" >
                <?php usi_MM_post_upload_ui_terms($selected_categories); ?>
              </ul>
            </div>

            <div id="category-all" class="tabs-panel">
              <input type="hidden" name="post_category[]" value="0" />
              <ul id="categorychecklist" data-wp-lists="list:category" class="categorychecklist form-no-clear">
                <?php wp_terms_checklist($post->ID, array('selected_cats' => $selected_categories, 'taxonomy' => 'category')); ?>
              </ul>
            </div>
          </div>
        </div><!--inside-->
      </div><!--postbox-->
    </div><!--meta-box-sortables-->
  </div><!--postbox-container-2-->
  <?php
   }
   if (!empty($usi_ms_options['tag'])) {
  ?>
  <div id="postbox-container-3" class="postbox-container" style="float:left; text-align:left; width:30%;">
    <div class="meta-box-sortables">
      <div class="postbox">
        <h3 class="hndle" style="cursor:default;"><span style="cursor:text;">Tags</span></h3>
        <div class="inside">
        </div><!--inside-->
      </div><!--postbox-->
    </div><!--meta-box-sortables-->
  </div><!--postbox-container-3-->
<?php
   }
  ?>
</div><!--poststuff-->
<script>
jQuery(document).ready(function($) {
   $(':checkbox').change(
      function() {
         if ('in-category-' == this.id.substr(0, 12)) {
            var request = { 'action' : 'usi_action_media_page_callback', 'item' : 'category', 
               'id' : this.value, 'value' : this.checked };
            $.post(ajaxurl, request, function(response) {
               if ('good' != response) alert(response);
            });
            $('#in-popular-category-' + this.value).prop('checked', this.checked);
         } else if ('in-popular-category-' == this.id.substr(0, 20)) {
            var request = { 'action' : 'usi_action_media_page_callback', 'item' : 'category', 
               'id' : this.value, 'value' : this.checked };
            $.post(ajaxurl, request, function(response) {
               if ('good' != response) alert(response);
               if ('good' != response) alert(response);
            });
            $('#in-category-' + this.value).prop('checked', this.checked);
         }
      }
   );

   $('#usi_MM_upload_folder').change(
      function() {
         var request = { 'action' : 'usi_action_media_page_callback', 'item' : 'folder', 
            'value' : $(this).val() };
         $.post(ajaxurl, request, function(response) {
            if ('good' != response) alert(response);
         });
      }
   );

   $('#category-all-tab').click(
      function() {
         $('#category-all').show();
         $('#category-pop').hide();
         $('#category-all-tab').addClass('tabs');
         $('#category-all-tab').removeClass('hide-if-no-js');
         $('#category-pop-tab').addClass('hide-if-no-js');
         $('#category-pop-tab').removeClass('tabs');
      }
   );

   $('#category-pop-tab').click(
      function() {
         $('#category-all').hide();
         $('#category-pop').show();
         $('#category-all-tab').addClass('hide-if-no-js');
         $('#category-all-tab').removeClass('tabs');
         $('#category-pop-tab').addClass('tabs');
         $('#category-pop-tab').removeClass('hide-if-no-js');
      }
   );
});
</script>
<?php
} // usi_MM_post_upload_ui();

function usi_MM_post_upload_ui_terms($checked_terms) {
   $terms = get_terms('category', array('orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false));
   $tax = get_taxonomy('category');
   foreach ((array)$terms as $term) {
      $id = "popular-category-$term->term_id";
      $checked = in_array($term->term_id, $checked_terms) ? 'checked="checked"' : '';
      ?>
  <li id="<?php echo $id; ?>" class="popular-category">
    <label class="selectit">
      <input id="in-<?php echo $id; ?>" type="checkbox" <?php echo $checked; ?> value="<?php echo (int)$term->term_id; ?>" <?php disabled(!current_user_can($tax->cap->assign_terms)); ?> />
     <?php echo esc_html(apply_filters('the_category', $term->name)); ?>
    </label>
  </li>
      <?php
   }
} // usi_MM_post_upload_ui_terms();

function usi_MM_register_folder_post() {
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

   register_post_type('usi-ms-upload-folder', $args);
} // usi_MM_register_folder_post();
 
function usi_MM_reload_media_page() {
   $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
   if (!current_user_can('edit_post', $id)) wp_die(__('You do not have sufficient permissions to access this page.'));
   $absolute_path = wp_normalize_path(get_attached_file($id));
   $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'] . '/', '', $absolute_path);
   $server = 'http' . (isset($_SERVER_['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'];

   $is_valid_nonce = isset($_POST['usi_MM_reload_media']) && wp_verify_nonce($_POST['usi_MM_reload_media'], basename(__FILE__));

   if ($is_valid_nonce) {
      $post_mime_type = get_post_mime_type($id);
      $path_info = pathinfo($absolute_path);
      $upload_folder = $path_info['dirname'];
      $meta = get_post_meta($id);
      $metadata = (isset($meta['_wp_attachment_metadata']) && isset($meta['_wp_attachment_metadata'][0]) 
         ? unserialize($meta['_wp_attachment_metadata'][0]) : null);
      $name = $_FILES['reload']['name'];
      if ('' == $name) { // 'Please select a file'
         add_settings_error('usi-MM-reload-media', esc_attr('error'), 'File not given, please select a file.', 'error');
      } else if (($type = $_FILES['reload']['type']) != $post_mime_type) {
         add_settings_error('usi-MM-reload-media', esc_attr('error'), 'The reload file (' . $name . 
            ') must be the same type (' . $post_mime_type . ') as the original file.', 'error');
      } else {
         switch ($type) {
         case 'application/pdf':
         case 'application/vnd.ms-excel':
         case 'text/csv': 
            @ $status = move_uploaded_file($_FILES['reload']['tmp_name'], $absolute_path);
            if ($status) {
               add_settings_error('usi-MM-reload-media', esc_attr('success'), 'Reload ' . $_FILES['reload']['name'], 'updated');
            } else {
               $error = error_get_last();
               add_settings_error('usi-MM-reload-media', esc_attr('error'), $error['message'], 'error');
            }
            break;
         case 'image/gif':
         case 'image/jpeg':
         case 'image/png':
            @ $status = move_uploaded_file($_FILES['reload']['tmp_name'], $absolute_path);
            if ($status) {
               wp_generate_attachment_metadata($id, $absolute_path);
               add_settings_error('usi-MM-reload-media', esc_attr('success'), 'not yet done ' . $_FILES['reload']['name'], 'updated');
            } else {
               $error = error_get_last();
               add_settings_error('usi-MM-reload-media', esc_attr('error'), $error['message'], 'error');
            }
            break;
         default:
            add_settings_error('usi-MM-reload-media', esc_attr('error'), 'Cannot reload files of type ' . $type . '.', 'error');
         }
      }   
   }
/*
         usi_history('usi_MM_reload_media_page:' . 
            ' id=' . $id . PHP_EOL . 
            ' absolute_path=' . $absolute_path . PHP_EOL . 
            ' relative_path=' . $relative_path . PHP_EOL . 
            ' server=' . $server . PHP_EOL . 
            ' is_valid_nonce=' . $is_valid_nonce . PHP_EOL . 
            ' post_mime_type=' . $post_mime_type . PHP_EOL . 
            ' path_info=' . print_r($path_info, true) . PHP_EOL . 
            ' upload_folder=' . $upload_folder . PHP_EOL . 
            ' $_FILES=' . print_r($_FILES, true) . PHP_EOL . 
//            ' $_SERVER=' . print_r($_SERVER, true) . PHP_EOL . 
//            ' thumb_src=' . print_r($thumb_src, true) . PHP_EOL . 
//            ' meta=' . print_r($meta, true) . PHP_EOL .
            ' metadata=' . print_r($metadata, true) . PHP_EOL .
            ''
            );
*/
?>
  <div class="wrap">
    <h2>Reload Media</h2>
    <?php settings_errors(); ?>
    <p id="async-upload-wrap">      
      <form action="" enctype="multipart/form-data" method="post">
        <?php wp_nonce_field(basename(__FILE__), 'usi_MM_reload_media'); ?>   
        <div id="titlediv">
          <input id="title" name="post_title" readonly type="text" value="<?php echo $relative_path; ?>">
        </div>
        <div id="edit-slug-box" class="hide-if-no-js">
          <strong>Permalink:</strong>
          <span id="sample-permalink" tabindex="-1"><?php echo $server; ?>/?attachment_id=<?php echo $id; ?></span>
          <span id="view-post-btn"><a href="<?php echo $server; ?>/?attachment_id=<?php echo $id; ?>" class="button button-small">View Attachment Page</a></span>
          <input id="shortlink" type="hidden" value="<?php echo $server; ?>/?p=<?php echo $id; ?>"><a href="#" class="button button-small" onclick="prompt('URL:', jQuery('#shortlink').val()); return(false);">Get Shortlink</a>
        </div>
        <br />
        <label class="screen-reader-text" for="async-upload">Upload</label>
        <input type="file" name="reload" id="reload" />
        <input type="submit" name="html-upload" id="html-upload" class="button" value="Reload" />
      </form>
    </p>
  </div>
<?php
} // usi_MM_reload_media_page();

function usi_MM_settings_defaults() {
   $defaults = array(
      'allow_root' => false,
      'capability_create_folder' => 'administrator',
      'category' => false,
      'folder' => false,
      'tag' => false,
      'version' => usi_ms_version
   );
   return(apply_filters('usi_MM_settings_defaults', $defaults));
} // end usi_MM_settings_defaults

function usi_MM_settings_init() {
   global $usi_ms_options;

   add_settings_section('usi_MM_settins_section', 'Media-Solutions Settings', null, 'media');

   add_settings_field('usi-MM-options', 'Organization', 'usi_MM_settings_organize_callback', 
      'media', 'usi_MM_settins_section');

   if (!empty($usi_ms_options['folder'])) {
      add_settings_field('usi-MM-options-capabilities', 'Capabilities', 'usi_MM_settings_capability_callback', 
         'media', 'usi_MM_settins_section');
   }

   register_setting('media', 'usi-ms-options', 'usi_MM_settings_sanitize');

} // usi_MM_settings_init();

function usi_MM_settings_organize_callback($args) {
   global $usi_ms_options;
   echo  '<table style="border:0; border-collapse:collapse; border-spacing:0; padding:0;">' .
      '<tr><td><input type="checkbox" id="usi-ms-options-category" name="usi-ms-options[category]" value="1" ' . 
      checked(1, (!empty($usi_ms_options['category']) ? $usi_ms_options['category'] : null), false) . '/></td><td>' .   
      '<label for="usi-ms-options-category">Organize media with <b>Categories</b></label></td></tr>' .

      '<tr><td style="vertical-align:top;"><input type="checkbox" id="usi-ms-options-folder" name="usi-ms-options[folder]" value="1" ' . 
      checked(1, (!empty($usi_ms_options['folder']) ? $usi_ms_options['folder'] : ''), false) . '/></td><td>' .   
      '<label for="usi-ms-options-folder">Organize media with <b>Folders</b></label><br />';
      if (!empty($usi_ms_options['folder'])) {
         echo '<input type="checkbox" id="usi-ms-options-allow_root" name="usi-ms-options[allow_root]" value="1" ' .
            checked(1, (!empty($usi_ms_options['allow_root']) ? $usi_ms_options['allow_root'] : null), false) . '/><label for="usi-ms-options-allow_root"> ' .
            'Allow root folder uploads <i>(Not recommended)</i></label>';
      } else {
         echo '<i>Other options will be given if this is checked</i>';
      }
      echo '</td></tr>' .

      '<tr><td><input type="checkbox" id="usi-ms-options-tag" name="usi-ms-options[tag]" value="1" ' . 
      checked(1, (!empty($usi_ms_options['tag']) ? $usi_ms_options['tag'] : null), false) . '/></td><td>' .
      '<label for="usi-ms-options-tag">Organize media with <b>Tags</b></label></td></tr>' . 

      '</table>' .

      '<input type="hidden" name="usi-ms-options[version]" value="' . $usi_ms_options['version'] . '" />';
} // usi_MM_settings_organize_callback();

function usi_MM_settings_capability_callback($args) {
   global $usi_ms_options;
   echo '<select name="usi-ms-options[capability_create_folder]">';
   wp_dropdown_roles($usi_ms_options['capability_create_folder']);
   echo '</select><br /><i>Only users with the above role or higher have permission to create upload folders</i>';
} // usi_MM_settings_capability_callback();

function usi_MM_settings_sanitize($value) {
   $value['version'] = usi_ms_version;
   return($value);
} // usi_MM_settings_sanitize();
 
function usi_MM_upload_dir($path){    
   if (!empty($path['error'])) return($path);
   global $usi_ms_options;
   global $wpdb;
   $folder_id = (int)get_user_option('usi-ms-options-upload-folder', get_current_user_id());
   if (0 < $folder_id) {
      $row = $wpdb->get_row($wpdb->prepare("SELECT `post_title` FROM `{$wpdb->posts}` WHERE (`ID` = %d) LIMIT 1", 
         $folder_id), OBJECT);
      if ($row) {
         $path['basedir'] = $_SERVER['DOCUMENT_ROOT'];
         $path['baseurl'] = 'http' . ($_SERVER['HTTPS'] ? 's' : '') . '://' . $_SERVER['SERVER_NAME'];
         $path['subdir']  = '';
         $path['path'] = $path['basedir'] . $row->post_title;
         $path['url'] = $path['baseurl'] . $row->post_title;

/*
    $customdir = $row->post_title;
    $path['path']    = str_replace($path['subdir'], '', $path['path']); //remove default subdir (year/month)
    $path['url']     = str_replace($path['subdir'], '', $path['url']);      
    $path['subdir']  = $customdir;
    $path['path']   .= $customdir; 
    $path['url']    .= $customdir;  
*/

      }
//usi_history('usi_MM_upload_dir:row=' . print_r($row, true) . PHP_EOL . 'path=' . print_r($path, true));
   }
   return($path);
} // usi_MM_upload_dir();

add_action('add_attachment', 'usi_MM_add_attachment');
add_action('admin_enqueue_scripts', 'usi_MM_add_ajax_javascript');
add_action('admin_init', 'usi_MM_create_folder_settings');
add_action('admin_init', 'usi_MM_settings_init');
add_action('admin_menu', 'usi_MM_create_folder_menu_add');
add_action('init', 'usi_MM_attachment_register_taxonomy');
add_action('init', 'usi_MM_register_folder_post');
add_action('manage_media_custom_column', 'usi_MM_library_folder_column', 10, 2);
add_action('post-upload-ui', 'usi_MM_post_upload_ui');
add_action('restrict_manage_posts', 'usi_MM_attachment_category_filter');
add_action('wp_ajax_usi_action_media_page_callback', 'usi_MM_media_page_callback');

add_filter('manage_media_columns', 'usi_MM_library_columns');
add_filter('manage_upload_sortable_columns', 'usi_MM_library_columns_sortable');
add_filter('media_row_actions', 'usi_MM_media_row_action', 10, 2);
add_filter('plugin_action_links', 'usi_MM_plugin_action_links', 10, 2);
add_filter('wp_get_attachment_url', 'usi_MM_get_attachment_url', 10, 2);
if (!empty($usi_ms_options['folder'])) {
   add_filter('wp_handle_upload', 'usi_MM_handle_upload', 2);
   add_filter('wp_handle_upload_prefilter', 'usi_MM_handle_upload_prefilter', 2);
}

function usi_MM_add_attachment($id) { 
   global $usi_ms_options;
   if (!empty($usi_ms_options['folder'])) {
      $post = get_post($id);
      add_post_meta($id, 'usi-ms-path', $post->guid, true);
   }
   if (!empty($usi_ms_options['category'])) {
      $user_id = get_current_user_id();
      $categories =  get_user_option('usi_mm_option_file_categories', $user_id);
      $category_array = explode(',', $categories);
      if (count($category_array)) {
         wp_set_post_categories($id, $category_array, false);
      }
      //usi_history('usi_MM_add_attachment:id=' . $id . ' path=' . get_attached_file($id) . ' $categories=' . $categories);
   }
} // usi_MM_add_attachment();

function usi_MM_get_attachment_url($url, $post_id) {
   if ($path = get_post_meta($post_id, 'usi-ms-path', true)) return($path);
   return($url);
} // usi_MM_get_attachment_url();

register_activation_hook(__FILE__, 'usi_MM_install');

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

if (!empty($usi_ms_options['folder'])) {
   require_once('usi-media-folder-list.php');
}

function modify_post_mime_types($post_mime_types) {
   $post_mime_types['text/csv'] = array( __( 'CSV' ), __( 'Manage CSV' ), 
   _n_noop( 'CSV <span class="count">(%s)</span>', 'CSVs <span class="count">(%s)</span>' ) );
   $post_mime_types['application/pdf'] = array( __( 'PDFs' ), __( 'Manage PDFs1' ), 
   _n_noop( 'PDF2 <span class="count">(%s)</span>', 'PDF3s <span class="count">(%s)</span>' ) );
 $post_mime_types['application/vnd.ms-excel'] = array( __( 'XLSs' ), __( 'Manage XLSs' ), _n_noop( 'XLS <span class="count">(%s)</span>', 'XLSs <span class="count">(%s)</span>' ) );
/*
$post_mime_types['application/msword'] = array( __( 'DOCs' ), __( 'Manage DOCs' ), _n_noop( 'DOC <span class="count">(%s)</span>', 'DOC <span class="count">(%s)</span>' ) );
$post_mime_types['application/vnd.ms-excel'] = array( __( 'XLSs' ), __( 'Manage XLSs' ), _n_noop( 'XLS <span class="count">(%s)</span>', 'XLSs <span class="count">(%s)</span>' ) );
$post_mime_types['application/pdf'] = array( __( 'PDFs' ), __( 'Manage PDFs' ), _n_noop( 'PDF <span class="count">(%s)</span>', 'PDFs <span class="count">(%s)</span>' ) );
$post_mime_types['application/zip'] = array( __( 'ZIPs' ), __( 'Manage ZIPs' ), _n_noop( 'ZIP <span class="count">(%s)</span>', 'ZIPs <span class="count">(%s)</span>' ) );
		
http://wpsmackdown.com/add-remove-filetypes-wordpress-media-library/

'pdf' => 'application/pdf',
'swf' => 'application/x-shockwave-flash',
'mov|qt' => 'video/quicktime',
'flv' => 'video/x-flv',
'js' => 'application/javascript',
'avi' => 'video/avi',
'divx' => 'video/divx',*/
   return $post_mime_types;
}
add_filter('post_mime_types', 'modify_post_mime_types');
if (!function_exists('usi_history')) {
function usi_history($text) {
   global $wpdb;
   $SAFE_table_name = $wpdb->prefix . 'USI_history';
   $wpdb->insert($SAFE_table_name, 
      array(
         'time_stamp' => current_time('mysql'),
         'action' => $text,
      )
   );
}
} // ENDIF function_exists('usi_history');

// --------------------------------------------------------------------------------------------------------------------------- // ?>
