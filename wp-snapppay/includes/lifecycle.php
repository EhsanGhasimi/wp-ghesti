<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-webinew-license-client.php';

final class WP_Snapppay_Lifecycle {
 const VERSION = '1.3.2';
 const PARENT_PLUGIN = 'webinew-plugin/webinew.php';
 private static $client;

 public static function init() {
  self::$client = new Webinew_License_Client_V1( array(
   'product' => 'wp-snapppay', 'name' => 'wp-ghesti', 'version' => self::VERSION,
   'file' => dirname( __DIR__ ) . '/wp-snapppay.php',
   'endpoint' => 'https://www.develop.webinew.com/wp-json/webinew-licenses/v1/',
  ) );
  add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
  add_action( 'admin_init', static function () { wp_clear_scheduled_hook( 'wp_snapppay_register_site' ); } );
 }

 public static function allowed() { return self::$client->allowed(); }

 public static function parent_active() {
  if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
  return is_plugin_active( self::PARENT_PLUGIN );
 }

 public static function notices() {
  if ( ! current_user_can( 'activate_plugins' ) ) { return; }
  if ( ! self::parent_active() ) {
   echo '<div class="notice notice-error"><p>افزونه wp-ghesti برای اجرا به فعال بودن افزونه اصلی آژانس دیجیتال مارکتینگ وبینیو نیاز دارد. لطفاً افزونه webinew-plugin را نصب و فعال کنید.</p></div>';
  }
 }
}




