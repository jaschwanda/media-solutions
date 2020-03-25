<?php // ------------------------------------------------------------------------------------------------------------------------ //
//https://danielbachhuber.com/tip/add-a-custom-taxonomy-dropdown-filter-to-the-wordpress-media-library/
//https://stackoverflow.com/questions/49282747/wordpress-plugin-development-category-filter-in-media-library-makes-the-page-to
defined('ABSPATH') or die('Accesss not allowed.');

//add_action('add_attachment', 'usi_MM_add_attachment');
//add_action('admin_enqueue_scripts', 'usi_MM_add_ajax_javascript');
//add_action('admin_init', 'usi_MM_create_folder_settings');
//add_action('init', 'usi_MM_attachment_register_taxonomy');
//add_action('post-upload-ui', 'usi_MM_post_upload_ui');
//add_action('restrict_manage_posts', 'usi_MM_attachment_category_filter');
//add_action('wp_ajax_usi_action_media_page_callback', 'usi_MM_media_page_callback');

//add_filter('plugin_action_links', 'usi_MM_plugin_action_links', 10, 2);
//add_filter('post_mime_types', 'modify_post_mime_types');

/* 

function usi_MM_plugin_action_links($links, $file) {
   static $this_plugin;
   if (!$this_plugin) $this_plugin = plugin_basename(__FILE__);
   if ($file == $this_plugin) $links[] = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-media.php">Settings</a>';
   return($links);
} // usi_MM_plugin_action_links();


function usi_MM_reload_media_page() {
(__METHOD__.':'.__LINE__);
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



function usi_MM_attachment_register_taxonomy() {
//(__METHOD__.':'.__LINE__);
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-category'])) register_taxonomy_for_object_type('category', 'attachment');
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
      global $wpdb;
      if (0 == $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE (`post_type` = 'usi-media-folder')")) {
         usi_MM_create_folder_post(0, 'Root Folder', '/', 'Root Folder');
      }
   }
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-tag'])) register_taxonomy_for_object_type('post_tag', 'attachment');
} // usi_MM_attachment_register_taxonomy();

function usi_MM_media_page_callback() {
//(__METHOD__.':'.__LINE__);
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

function usi_MM_post_upload_ui() {
//(__METHOD__.':'.__LINE__);
  ?>
<div id="poststuff">
<?php
   global $wpdb;
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
      $user_id = get_current_user_id();
      $folder_id =  (int)get_user_option('usi-ms-options-upload-folder', $user_id);
      $rows = $wpdb->get_results("SELECT `ID`, `post_title` FROM `{$wpdb->posts}` " .
         " WHERE (`post_type` = 'usi-media-folder') ORDER BY `post_title`", OBJECT_K);
      $html = '<select class="regular-text" id="usi_MM_upload_folder"><option value="0">-- Default Upload Folder --</option>';
      $ignore = (!empty(USI_Media_Solutions::$options['preferences']['organize-allow-root']) ? '': '/');
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
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-category'])) {
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
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-tag'])) {
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
//(__METHOD__.':'.__LINE__);
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

function usi_MM_add_attachment($id) { 
//(__METHOD__.':'.__LINE__);
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
      $post = get_post($id);
      add_post_meta($id, 'usi-ms-path', $post->guid, true);
   }
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-category'])) {
      $user_id = get_current_user_id();
      $categories =  get_user_option('usi_mm_option_file_categories', $user_id);
      $category_array = explode(',', $categories);
      if (count($category_array)) {
         wp_set_post_categories($id, $category_array, false);
      }
   }
} // usi_MM_add_attachment();


if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
   require_once('usi-media-solutions-folder-list.php');
}

function modify_post_mime_types($post_mime_types) {
//(__METHOD__.':'.__LINE__);
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
'divx' => 'video/divx',* /
   return $post_mime_types;
}

function usi_MM_add_ajax_javascript() {
//(__METHOD__.':'.__LINE__);
   wp_enqueue_script('ajax_custom_script', plugin_dir_url(__FILE__) . 'usi-media-solutions.js', array('jquery'));
} // usi_MM_add_ajax_javascript();

function usi_MM_attachment_category_filter() {
//(__METHOD__.':'.__LINE__);
   $screen = get_current_screen();
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-folder'])) {
      if ('upload' == $screen->id) {
         global $wpdb;
         $rows = $wpdb->get_results("SELECT `post_title` FROM `{$wpdb->posts}` " .
            " WHERE (`post_type` = 'usi-media-folder') ORDER BY `post_title`", OBJECT_K);
         $guid = (isset($_GET['guid']) ? 'http' . ($_SERVER['HTTPS'] ? 's' : '') . '://' . 
            $_SERVER['SERVER_NAME'] . rawurldecode($_GET['guid']) : '');
         $guid = (isset($_GET['guid']) ? rawurldecode($_GET['guid']) : '');
         $html = '<select class="postform" id="guid" name="guid"><option value="%">View all upload folders</option>';
         $ignore = (!empty(USI_Media_Solutions::$options['preferences']['organize-allow-root']) ? '': '/');
         foreach ($rows as $row) {
            if ($ignore == $row->post_title) continue;
            $html .= '<option ' . (($row->post_title == $guid) ? 'selected="selected" ' : '') . 'value="' . 
               $row->post_title . '">' . $row->post_title . '</option>';
         }
         echo $html . '</select>'; 
      }
   }
   if (!empty(USI_Media_Solutions::$options['preferences']['organize-category'])) {
       if ('upload' == $screen->id) {
          $dropdown_options = array('show_option_all' => __('View all categories', USI_Media_Solutions::TEXTDOMAIN), 'hide_empty' => false, 'hierarchical' => true, 'orderby' => 'name');
          wp_dropdown_categories($dropdown_options);
      }
   }
} // usi_MM_attachment_category_filter();
 
function usi_MM_get_active() {
//(__METHOD__.':'.__LINE__);
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

function usi_MM_settings_defaults() {
//(__METHOD__.':'.__LINE__);
   $defaults = array(
      'allow_root' => false,
      'capability_create_folder' => 'administrator',
      'category' => false,
      'folder' => false,
      'tag' => false,
      'version' => USI_Media_Solutions::VERSION
   );
   return(apply_filters('usi_MM_settings_defaults', $defaults));
} // end usi_MM_settings_defaults

*/
// --------------------------------------------------------------------------------------------------------------------------- // ?>
