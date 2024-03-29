<?php // ------------------------------------------------------------------------------------------------------------------------ //

defined('ABSPATH') or die('Accesss not allowed.');

/*
Media-Solutions is free software: you can redistribute it and/or modify it under the terms of the GNU General Public 
License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 
Media-Solutions is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied 
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License along with Media-Solutions. If not, see 
https://github.com/jaschwanda/media-solutions/blob/master/LICENSE.md

Copyright (c) 2023 by Jim Schwanda.
*/

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

require_once WP_PLUGIN_DIR . '/usi-wordpress-solutions/usi-wordpress-solutions-uninstall.php';

require_once 'usi-media-solutions.php';

final class USI_Media_Solutions_Uninstall {

   const VERSION = '1.3.1 (2023-07-10)';

   private function __construct() {
   } // __construct();

   static function uninstall() {

      USI_WordPress_Solutions_Uninstall::uninstall(
         'capabilities' => USI_Media_Solutions::$capabilities,
         'prefix' => USI_Media_Solutions::PREFIX,
      );

   } // uninstall();

} // Class USI_Media_Solutions_Uninstall;

USI_Media_Solutions_Uninstall::uninstall();

// --------------------------------------------------------------------------------------------------------------------------- // ?>