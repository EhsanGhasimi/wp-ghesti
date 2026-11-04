<?php
/**
 * Plugin Name: wp-ghesti
 * Description: افزودن درصد قابل تنظیم به سفارش‌های پرداخت اقساطی اسنپ‌پی در ووکامرس؛ محصول وبینیو، ارائه‌دهنده خدمات طراحی سایت، سئو، گوگل ادز، تولید محتوای ویدئویی، تلگرام ادز و متا ادز.
 * Version: 1.3.2
 * Author: احسان قسیمی - Ehsan Ghasimi | وبینیو
 * Author URI: https://webinew.com/about-us/
 * Plugin URI: https://webinew.com/
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, webinew-plugin
 * Text Domain: wp-snapppay
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/lifecycle.php';
require_once __DIR__ . '/includes/notification.php';
WP_Snapppay_Lifecycle::init();

final class WP_Snapppay {
 const OPTION = 'wp_snapppay_settings';
 private static $request_method = null;

 public static function boot() {
  if ( ! WP_Snapppay_Lifecycle::parent_active() || ! WP_Snapppay_Lifecycle::allowed() ) {
   return;
  }
  if ( ! class_exists( 'WooCommerce' ) ) {
   add_action( 'admin_notices', static function () {
    echo '<div class="notice notice-error"><p>افزونه wp-ghesti به ووکامرس نیاز دارد.</p></div>';
   } );
   return;
  }
  add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
  WP_Snapppay_Notification::init();
  add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'capture_classic' ) );
  add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'fee' ), 30 );
  add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ) );
  add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'blocks' ) );
  add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'capture_store_request' ), 10, 3 );
 }

 public static function settings() {
  return wp_parse_args( get_option( self::OPTION, array() ), array(
   'enabled' => 'yes', 'percent' => '13', 'gateway' => '', 'label' => 'هزینه پرداخت اقساطی اسنپ‌پی',
  ) );
 }

 public static function gateways() {
  return WC()->payment_gateways()->payment_gateways();
 }

 public static function targets() {
  $settings = self::settings();
  if ( '' !== $settings['gateway'] ) {
   return array( $settings['gateway'] );
  }
  $ids = array();
  foreach ( self::gateways() as $id => $gateway ) {
   // Inspect technical identifiers only; a customer-facing title is not reliable.
   $identifier = strtolower( preg_replace( '/[^a-z0-9]/i', '', $id . get_class( $gateway ) ) );
   if ( false !== strpos( $identifier, 'snappay' ) || false !== strpos( $identifier, 'snapppay' ) ) {
    $ids[] = $id;
   }
  }
  return $ids;
 }

 public static function menu() {
  add_submenu_page( 'woocommerce', 'تنظیمات اقساط', 'wp-ghesti', 'manage_woocommerce', 'wp-snapppay', array( __CLASS__, 'page' ) );
 }

 public static function page() {
  if ( ! current_user_can( 'manage_woocommerce' ) ) {
   return;
  }
  $gateways = self::gateways();
  if ( isset( $_POST['wp_snapppay_save'] ) ) {
   check_admin_referer( 'wp_snapppay_save' );
   $percent = isset( $_POST['percent'] ) ? wc_format_decimal( wp_unslash( $_POST['percent'] ) ) : '';
   $gateway = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : '';
   if ( ! is_numeric( $percent ) || (float) $percent < 0 || (float) $percent > 100 || ( '' !== $gateway && ! isset( $gateways[ $gateway ] ) ) ) {
    echo '<div class="notice notice-error"><p>درصد باید عددی بین صفر و ۱۰۰ باشد و درگاه انتخابی معتبر باشد.</p></div>';
   } else {
    $label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
    update_option( self::OPTION, array(
     'enabled' => isset( $_POST['enabled'] ) ? 'yes' : 'no',
     'percent' => wc_format_decimal( $percent, 4 ),
     'gateway' => $gateway,
     'label' => '' !== $label ? $label : 'هزینه پرداخت اقساطی اسنپ‌پی',
    ) );
    WP_Snapppay_Notification::save();
    echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
   }
  }
  $s = self::settings();
  ?>
  <div class="wrap" dir="rtl">
   <h1>تنظیمات wp-ghesti</h1>
   <p>مبلغ اضافه بر اساس جمع کالاها پس از تخفیف محاسبه می‌شود؛ هزینه ارسال، مالیات و سایر هزینه‌ها در مبنا نیستند. به این هزینه مالیات جداگانه افزوده نمی‌شود.</p>
   <form method="post">
    <?php wp_nonce_field( 'wp_snapppay_save' ); ?>
    <table class="form-table" role="presentation">
     <tr><th scope="row">فعال‌سازی</th><td><label><input type="checkbox" name="enabled" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>> افزودن هزینه برای درگاه انتخاب‌شده</label></td></tr>
     <tr><th scope="row"><label for="snapppay-percent">درصد افزایش</label></th><td><input id="snapppay-percent" name="percent" type="number" min="0" max="100" step="0.0001" required value="<?php echo esc_attr( $s['percent'] ); ?>"> ٪</td></tr>
     <tr><th scope="row"><label for="snapppay-gateway">درگاه پرداخت اقساطی</label></th><td>
      <select id="snapppay-gateway" name="gateway"><option value="">تشخیص خودکار اسنپ‌پی</option>
       <?php foreach ( $gateways as $id => $gateway ) : ?>
        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['gateway'], $id ); ?>><?php echo esc_html( wp_strip_all_tags( $gateway->get_title() ) . ' (' . $id . ')' ); ?></option>
       <?php endforeach; ?>
      </select>
      <p class="description">در صورت وجود چند روش اسنپ‌پی، درگاه اقساطی را دقیقاً انتخاب کنید. این افزونه، درگاه پرداخت ایجاد نمی‌کند.</p>
      <p>شناسه‌های فعلی مشمول هزینه: <code><?php echo esc_html( implode( ', ', self::targets() ) ?: 'درگاهی شناسایی نشد؛ درگاه را از فهرست انتخاب کنید.' ); ?></code></p>
     </td></tr>
     <tr><th scope="row"><label for="snapppay-label">عنوان هزینه</label></th><td><input id="snapppay-label" class="regular-text" name="label" value="<?php echo esc_attr( $s['label'] ); ?>"></td></tr>
    </table>
    <?php WP_Snapppay_Notification::editor(); ?>
    <?php submit_button( 'ذخیره تنظیمات', 'primary', 'wp_snapppay_save' ); ?>
   </form>
   <hr>
   <h2>درباره توسعه‌دهنده و وبینیو</h2>
   <p><strong>توسعه‌دهنده:</strong> احسان قسیمی - <span dir="ltr">Ehsan Ghasimi</span> | <strong>مجموعه:</strong> وبینیو</p>
   <p>وبینیو آژانس دیجیتال مارکتینگ است و در زمینه طراحی سایت‌های وردپرسی و اختصاصی، سئو، مدیریت کمپین‌های گوگل ادز، تولید محتوای ویدئویی و تبلیغات تلگرام و متا فعالیت می‌کند.</p>
   <p><a href="https://webinew.com/about-us/" target="_blank" rel="noopener noreferrer">معرفی مجموعه و خدمات وبینیو</a></p>
   <p>برای مدیریت مجوز، آدرس عمومی سایت، نام و نسخه افزونه و رویدادهای نصب و به‌روزرسانی به سامانه وبینیو ارسال می‌شود. اطلاعات مشتریان و سفارش‌ها ارسال نمی‌شود. این افزونه به فعال بودن افزونه اصلی وبینیو و مجوز معتبر نیاز دارد. در صورت لغو مجوز، قابلیت‌های افزونه متوقف می‌شود.</p>
  </div>
  <?php
 }

 public static function capture_classic( $data ) {
  parse_str( $data, $posted );
  if ( WC()->session && isset( $posted['payment_method'] ) && is_string( $posted['payment_method'] ) ) {
   WC()->session->set( 'chosen_payment_method', sanitize_text_field( $posted['payment_method'] ) );
  }
 }

 public static function fee( $cart ) {
  if ( ( is_admin() && ! wp_doing_ajax() ) || ! WC()->session ) {
   return;
  }
  $s = self::settings();
  $method = null !== self::$request_method ? self::$request_method : WC()->session->get( 'chosen_payment_method', '' );
  if ( 'yes' !== $s['enabled'] || ! in_array( $method, self::targets(), true ) || (float) $s['percent'] <= 0 ) {
   return;
  }
  $amount = round( max( 0, (float) $cart->get_cart_contents_total() ) * (float) $s['percent'] / 100, wc_get_price_decimals() );
  if ( $amount > 0 ) {
   $cart->add_fee( $s['label'], $amount, false );
  }
 }

 public static function scripts() {
  if ( ! is_checkout() || is_order_received_page() || is_checkout_pay_page() ) {
   return;
  }
  wp_enqueue_script( 'wp-snapppay-classic', plugins_url( 'assets/classic.js', __FILE__ ), array( 'jquery', 'wc-checkout' ), '1.0.0', true );
  if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
   wp_enqueue_script( 'wp-snapppay-blocks', plugins_url( 'assets/blocks.js', __FILE__ ), array( 'wp-data', 'wc-blocks-checkout' ), '1.0.0', true );
  }
 }

 public static function blocks() {
  if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
   woocommerce_store_api_register_update_callback( array(
    'namespace' => 'wp-snapppay',
    'callback' => static function ( $data ) {
     if ( WC()->session && isset( $data['payment_method'] ) && is_string( $data['payment_method'] ) ) {
      $method = sanitize_text_field( $data['payment_method'] );
      if ( '' === $method || isset( self::gateways()[ $method ] ) ) {
       WC()->session->set( 'chosen_payment_method', $method );
      }
     }
    },
   ) );
  }
 }

 public static function capture_store_request( $response, $handler, $request ) {
  // Use the final submitted method during core's own totals/order calculation.
  // No order or customer fields need to be rewritten by this extension.
  self::$request_method = null;
  if ( preg_match( '#^/wc/store/v[0-9]+/checkout/?$#', $request->get_route() ) && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
   $method = $request->get_param( 'payment_method' );
   if ( is_string( $method ) ) {
    self::$request_method = sanitize_text_field( $method );
   }
  }
  return $response;
 }
}

add_action( 'plugins_loaded', array( 'WP_Snapppay', 'boot' ) );
add_action( 'before_woocommerce_init', static function () {
 if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
 }
} );



