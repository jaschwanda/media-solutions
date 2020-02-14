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

final class USI_Media_Solutions_Install {

   const VERSION = '1.1.0 (2020-02-08)';

   private function __construct() {
   } // __construct();

   static function init() {
      $file = str_replace('-install', '', __FILE__);
      register_activation_hook($file, array(__CLASS__, 'hook_activation'));
      register_deactivation_hook($file, array(__CLASS__, 'hook_deactivation'));
   } // init();

   static function hook_activation() {
usi_log(__METHOD__);
// see if this can be made automatic in the capabilities class;
      if (!current_user_can('activate_plugins')) return;

      check_admin_referer('activate-plugin_' . (isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : ''));

      USI_WordPress_Solutions_Capabilities::init(USI_Media_Solutions::PREFIX, USI_Media_Solutions::$capabilities);

   } // hook_activation();

   static function hook_deactivation() {

      if (!current_user_can('activate_plugins')) return;

      check_admin_referer('deactivate-plugin_' . (isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : ''));

   } // hook_deactivation();

} // Class USI_Media_Solutions_Install;

USI_Media_Solutions_Install::init();

// --------------------------------------------------------------------------------------------------------------------------- // ?>
