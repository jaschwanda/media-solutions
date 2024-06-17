<?php // ------------------------------------------------------------------------------------------------------------------------ //

defined('ABSPATH') or die('Accesss not allowed.');

class USI_Media_Solutions_Settings extends USI_WordPress_Solutions_Settings {

   const VERSION = '2.0.0 (2024-06-16)';

   protected $is_tabbed = true;

   function __construct() {

      parent::__construct(
         [
            'name' => USI_Media_Solutions::NAME, 
            'prefix' => USI_Media_Solutions::PREFIX, 
            'text_domain' => USI_Media_Solutions::TEXTDOMAIN,
            'options' => USI_Media_Solutions::$options,
            'capabilities' => USI_Media_Solutions::$capabilities,
            'file' => str_replace('-settings', '', __FILE__), // Plugin main file, this initializes capabilities on plugin activation;
         ]
      );

   } // __construct();

   function fields_sanitize($input) {

      $input = parent::fields_sanitize($input);

      // IF organize by folders not used;
      if (empty($input['preferences']['organize-folder'])) {

         // Clear options that require organize by folder option;
         $input['preferences']['organize-allow-default'] = 
         $input['preferences']['organize-allow-root']    =
         $input['preferences']['library-show-fold']      = false;

      } else { // ELSE organize by folders in use;

         global $wpdb;

         // Add root folder post if there are no folder posts;
         if (0 == $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->posts}` WHERE " .
            "(`post_type` = '" . USI_Media_Solutions::POSTFOLDER . "')")) {
               USI_Media_Solutions::folder_create_post(0, 'Root Folder', '/', 'Root Folder');
         }

      } // ENDIF organize by folders in use;

      $post_max_size       = (int)$input['uploads']['post-max-size'];
      $upload_max_filesize = (int)$input['uploads']['upload-max-filesize'];

      // IF upload limits have changed;
      if ((intval(ini_get('post_max_size')) != $post_max_size) || 
      (intval(ini_get('upload_max_filesize')) != $upload_max_filesize)) {

         try {

            // Format new limit directives;
            $php_version = intval(phpversion());
            if ((5 != $php_version) && (7 > $php_version)) {
               throw new Exception(
                  __('Cannot change upload limits on systems not running PHP version 5 or 7+.', USI_Media_Solutions::TEXTDOMAIN)
               );
            }

            // Build .htacces path name;
            $root   = get_home_path();
            $path   = $root . '.htaccess';

            // Open .htacces file for reading;
            if (!is_resource($handle = fopen($path, 'r'))) {
               throw new Exception(
                  sprintf(__('Cannot open %s file for reading.', USI_Media_Solutions::TEXTDOMAIN), $path)
               );
            }

            // Read .htaccess file;
            $_htaccess = fread($handle, filesize($path));
            fclose($handle);

            // Build backup .htacces path name;
            $back   = $root . '.htaccess-' . date('Ymd-His');

            // Open backup .htacces file for writing;
            if (!is_resource($handle = fopen($back, 'w'))) {
               throw new Exception(
                  sprintf(__('Cannot open backup %s file for writing.', USI_Media_Solutions::TEXTDOMAIN), $path)
               );
            }

            // Write backup .htaccess file and close handle;
            $length = strlen($_htaccess);
            $bytes  = fwrite($handle, $_htaccess, $length);
            fclose($handle);

            // Remove existing limit directives, if any;
            $status = preg_match('/# BEGIN usi-media-solutions[\/\.\<\>\w\s]*# END usi-media-solutions\s*/', $_htaccess, $matches);
            if ($status) $_htaccess = str_replace($matches[0], '', $_htaccess);

            $php_version = intval(phpversion());

            $_htaccess   = ''
            . '# BEGIN usi-media-solutions' . PHP_EOL
            . ((7 >= $php_version) ? '<IfModule mod_php' . $php_version . '.c>' : '<IfModule php_module>') . PHP_EOL
            . 'php_value post_max_size ' . $post_max_size . 'M' . PHP_EOL
            . 'php_value upload_max_filesize ' . $upload_max_filesize . 'M' . PHP_EOL
            . '</IfModule>' . PHP_EOL
            .   '# END usi-media-solutions' . PHP_EOL . PHP_EOL 
            . $_htaccess
            ;

            // Open .htacces file for writing;
            if (!is_resource($handle = fopen($path, 'w'))) {
               throw new Exception(
                  sprintf(__('Cannot open %s file for writing.', USI_Media_Solutions::TEXTDOMAIN), $path)
               );
            }

            // Write .htaccess file and close handle;
            $length = strlen($_htaccess);
            $bytes  = fwrite($handle, $_htaccess, $length);
            fclose($handle);

            if ($length != $bytes) {
               throw new Exception(
                  sprintf(__('Cannot write file %s completely.', USI_Media_Solutions::TEXTDOMAIN), $path)
               );
            }

         } catch (Exception $e) {

            // Display file administrator notice;
            add_settings_error($this->page_slug, 'notice-error', $e->GetMessage(), 'notice-error');

         }

      // ELSEIF delete backups option is given;
      } else if (!empty($input['uploads']['delete-backups'])) {

         try {

            // Delete all .htaccess backups;
            array_map('unlink', glob(get_home_path() . '.htaccess-20*'));

         } catch (Exception $e) {

            // Display file administrator notice;
            add_settings_error($this->page_slug, 'notice-error', $e->GetMessage(), 'notice-error');

         }

      } // ENDIF delete backups option is given;

      // Clear delete backups option;
      $input['uploads']['delete-backups'] = false;

      return $input;

   } // fields_sanitize();

   function filter_plugin_row_meta($links, $file) {
      if (false !== strpos($file, USI_Media_Solutions::TEXTDOMAIN)) {
         $links[0] = USI_WordPress_Solutions_Versions::link(
            $links[0], // Original link text;
            USI_Media_Solutions::NAME, // Title;
            USI_Media_Solutions::VERSION, // Version;
            USI_Media_Solutions::TEXTDOMAIN, // Text domain;
            __DIR__ // Folder containing plugin or theme;
         );
         $links[] = '<a href="https://www.usi2solve.com/donate/media-solutions" target="_blank">' . 
            __('Donate', USI_Media_Solutions::TEXTDOMAIN) . '</a>';
      }
      return $links;
   } // filter_plugin_row_meta();

   function sections() {

      $readonly = empty(USI_Media_Solutions::$options['preferences']['organize-folder']);

      $this->options['uploads']['upload-max-filesize'] = intval(ini_get('upload_max_filesize'));
      $this->options['uploads']['post-max-size']       = intval(ini_get('post_max_size'));

      $sections = [
         'preferences' => [
            'header_callback' => [$this, 'sections_header', '      <p>' . __('The Media-Solutions plugin enables WordPress media to '
            . 'be stored and organized via user created upload folders, tags and categories.', USI_Media_Solutions::TEXTDOMAIN) 
            . '</p>' . PHP_EOL],
            'label' => 'Preferences',
            'localize_labels' => 'yes',
            'localize_notes' => 2, // &nbsp; <i>__()</i>;
            'settings' => [
               'organize-category' => [
                  'type' => 'checkbox', 
                  'label' => 'Organize With Categories', 
               ],
               'organize-tag' => [
                  'type' => 'checkbox', 
                  'label' => 'Organize With Tags', 
               ],
               'organize-folder' => [
                  'type' => 'checkbox', 
                  'label' => 'Organize With Folders', 
               ],
               'organize-allow-default' => [
                  'type' => 'checkbox', 
                  'label' => 'Allow Default Folder Uploads', 
                  'prefix' => '<span style="display:inline-block; width:16px;"></span>',
                  'notes' => 'Currently ' . USI_Media_Solutions_Folder::get_default_upload_folder(),
                  'readonly' => $readonly,
               ],
               'organize-allow-root' => [
                  'type' => 'checkbox', 
                  'label' => 'Allow Root Folder Uploads', 
                  'prefix' => '<span style="display:inline-block; width:16px;"></span>',
                  'notes' => 'Not recommended',
                  'readonly' => $readonly,
               ],
               'library-show-fold' => [
                  'type' => 'checkbox', 
                  'label' => 'Show Folder In Media Library', 
                  'prefix' => '<span style="display:inline-block; width:16px;"></span>',
                  'readonly' => $readonly,
               ],
               'library-show-size' => [
                  'type' => 'checkbox', 
                  'label' => 'Show <i>Size</i> In Media Library', 
               ],
               'library-show-parent' => [
                  'type' => 'checkbox', 
                  'label' => 'Show <i>Upload To</i> In Media Library', 
               ],
               'library-show-notes' => [
                  'type' => 'checkbox', 
                  'label' => 'Show <i>Comments</i> In Media Library', 
               ],
               'library-author' => [
                  'type' => 'checkbox', 
                  'label' => 'Show <i>Author</i> in Media Library',
               ],
            ],
         ], // preferences;

         'uploads' => [
            'header_callback' => [$this, 'sections_header_uploads'],
            'header_callback' => [$this, 'sections_header', '      <p>' . __('Upload limits are modified by setting the appropriate '
            . 'directives in the .htaccess file. A backup of the .htaccess file is made before it is modified. Although these '
            . 'modifications rarely causes any problems, it is recommended that you have the ability to access the .htaccess file '
            . 'with ssh or ftp to recover the original .htaccess file should the need arise.', USI_Media_Solutions::TEXTDOMAIN) 
            . '</p>' . PHP_EOL],
            'label' => 'Uploads',
            'localize_labels' => 'yes',
            'localize_notes' => 3, // <p class="description">__()</p>>;
            'not_tabbed' => 'preferences',
            'title' => 'Upload Limits',
            'settings' => [
               'upload-max-filesize' => [
                  'type' => 'number', 
                  'label' => 'upload_max_filesize', 
                  'notes' => 'Maximum size in megabytes of a single file upload.',
               ],
               'post-max-size' => [
                  'type' => 'number', 
                  'label' => 'post_max_size', 
                  'notes' => 'Maximum size in megabytes of all files uploaded at one time.',
               ],
               'delete-backups' => [
                  'type' => 'checkbox', 
                  'label' => 'Delete .htaccess backups', 
                  'notes' => 'For safety reasons backups are not deleted at the same time the upload limits are modified.' .
                     'If you want to delete the .htaccess backups then you must delete them in a second step after the upload limits are modified.',
               ],
            ],
         ], // uploads;

         'capabilities' => new USI_WordPress_Solutions_Capabilities($this),

         'diagnostics' => new USI_WordPress_Solutions_Diagnostics($this, 
            [
               'DEBUG_FOLDER' => [
                  'value' => USI_Media_Solutions::DEBUG_FOLDER,
                  'notes' => 'Log media folder operations.',
               ],
               'DEBUG_MANAGE' => [
                  'value' => USI_Media_Solutions::DEBUG_MANAGE,
                  'notes' => 'Log media manage operations.',
               ],
               'DEBUG_SANITZ' => [
                  'value' => USI_Media_Solutions::DEBUG_SANITZ,
                  'notes' => 'Log USI_Media_Solutions::fields_sanitize() method.',
               ],
            ],
         ), // diagnostics;

      ];

      return $sections;

   } // sections();

   function sections_header_uploads() {
      echo '      <p>' . __('Upload limits are modified by setting the appropriate directives in the .htaccess file. A backup of the ' .
         '.htaccess file is made before it is modified. Although these modifications rarely causes any problems, it is recommended that '.
         'you have the ability to access the .htaccess file with ssh or ftp to recover the original .htaccess file should ' .
         'the need arise.', USI_Media_Solutions::TEXTDOMAIN) . '</p>' . PHP_EOL;
   } // sections_header_uploads();

} // Class USI_Media_Solutions_Settings;

// --------------------------------------------------------------------------------------------------------------------------- // ?>