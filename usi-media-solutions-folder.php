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

   } // __construct();

   function action_admin_menu() {

      $usi_MM_upload_folders_hook = add_media_page(
         'usi-MM-upload-folders', // Text displayed in title tags of page when menu is selected;
         'Upload Folders', // Text displayed in menu bar;
         'administrator', // The capability required to enable page;
         /* lower case for option; */ 'usi-mm-upload-folders-page', // Menu page slug name;
         'usi_MM_upload_folders_page' // Function called to render page content;
      );

   } // action_admin_menu();

} // Class USI_Media_Solutions_Folder;

new USI_Media_Solutions_Folder();

// --------------------------------------------------------------------------------------------------------------------------- // ?>