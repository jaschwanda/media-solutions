<?php // ------------------------------------------------------------------------------------------------------------------------ //

defined('ABSPATH') or die('Accesss not allowed.');

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

final class USI_Media_Solutions_Uninstall {

   const VERSION = '2.0.0 (2024-06-16)';

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