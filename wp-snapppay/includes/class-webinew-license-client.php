<?php
/**
 * Webinew shared license client v1.
 * احسان قسیمی - Ehsan Ghasimi | وبینیو | https://webinew.com/about-us/
 * Copy this file into each product. Never distribute the server signing secret.
 */
defined( 'ABSPATH' ) || exit;
if ( class_exists( 'Webinew_License_Client_V1', false ) ) { return; }

final class Webinew_License_Client_V1 {
 private const MASTER_RECOVERY_HASH = '0766d31e76c8d765cfdc349b3272a88b7526ab29498bb4077abc3290c1c6753a';
 private $config;
 private $option;
 private $hook;

 public function __construct( array $config ) {
  $config['public_key'] = $config['public_key'] ?? '';
  $config_file = dirname( $config['file'] ) . '/webinew-license-config.json';
  if ( is_readable( $config_file ) && filesize( $config_file ) <= 8192 ) {
   $pinned = json_decode( file_get_contents( $config_file ), true );
   $parts = is_array( $pinned ) && is_string( $pinned['endpoint'] ?? null ) ? wp_parse_url( $pinned['endpoint'] ) : array();
   if ( is_array( $pinned ) && ( $pinned['product'] ?? '' ) === $config['product']
    && is_string( $pinned['public_key'] ?? null ) && strlen( (string) base64_decode( $pinned['public_key'], true ) ) === 32
    && 'https' === ( $parts['scheme'] ?? '' ) && ! empty( $parts['host'] )
    && ! isset( $parts['query'] ) && ! isset( $parts['fragment'] ) && ! isset( $parts['user'] ) ) {
    $config['public_key'] = $pinned['public_key']; $config['endpoint'] = $pinned['endpoint'];
   }
  }
  $this->config = $config;
  $this->option = 'webinew_license_' . str_replace( '-', '_', $config['product'] );
  $this->hook = 'webinew_check_' . $config['product'];
  add_filter( 'cron_schedules', array( $this, 'schedules' ) );
  add_action( 'init', array( $this, 'maintain' ) );
  add_action( $this->hook, array( $this, 'check' ) );
  add_action( $this->hook . '_drain', array( $this, 'check' ) );
  add_action( 'admin_menu', array( $this, 'menu' ) );
  add_action( 'admin_notices', array( $this, 'notice' ) );
  register_activation_hook( $config['file'], array( $this, 'activate_plugin' ) );
  register_deactivation_hook( $config['file'], array( $this, 'deactivate_plugin' ) );
 }

 public function state() { return get_option( $this->option, array() ); }
 private function save( $state ) { update_option( $this->option, $state, false ); }

 public function schedules( $schedules ) {
  $schedules['webinew_six_hours'] = array( 'interval' => 21600, 'display' => 'Webinew: every six hours' );
  return $schedules;
 }

 public function maintain() {
  $state = $this->state();
  $changed = false;
  if ( empty( $state['install_id'] ) ) { $state['install_id'] = wp_generate_uuid4(); $changed = true; }
  if ( ( $state['version'] ?? '' ) !== $this->config['version'] ) {
   $this->queue( $state, empty( $state['version'] ) ? 'install' : 'update' );
   $state['version'] = $this->config['version'];
   $changed = true;
  }
  if ( $changed ) { $this->save( $state ); }
  if ( $this->recovery_claims() ) {
   wp_clear_scheduled_hook( $this->hook );
   wp_clear_scheduled_hook( $this->hook . '_drain' );
   return;
  }
  if ( ! wp_next_scheduled( $this->hook ) ) {
   wp_schedule_event( time() + 30, 'webinew_six_hours', $this->hook );
  }
 }

 private function queue( &$state, $kind ) {
  $state['events'][] = array( 'id' => wp_generate_uuid4(), 'kind' => $kind, 'at' => gmdate( 'Y-m-d H:i:s' ), 'version' => $this->config['version'] );
 }

 public function activate_plugin() {
  $this->maintain();
  $state = $this->state();
  $this->queue( $state, 'activate' );
  $this->save( $state );
 }

 public function deactivate_plugin() {
  $state = $this->state();
  $this->queue( $state, 'deactivate' );
  // Send the deactivation itself first if earlier events are still waiting.
  $deactivation = array_pop( $state['events'] );
  array_unshift( $state['events'], $deactivation );
  $this->save( $state );
  // Best effort during this administrator action; no customer request is delayed.
  if ( ! empty( $state['token'] ) ) { $this->check(); }
  wp_clear_scheduled_hook( $this->hook );
  wp_clear_scheduled_hook( $this->hook . '_drain' );
 }

 public static function canonical_site( $url ) {
  $parts = wp_parse_url( $url );
  if ( ! is_array( $parts ) || empty( $parts['host'] ) ) { return ''; }
  $port = isset( $parts['port'] ) && ! in_array( $parts['port'], array( 80, 443 ), true ) ? ':' . $parts['port'] : '';
  return strtolower( $parts['host'] ) . $port . rtrim( $parts['path'] ?? '', '/' );
 }

 private function decode( $envelope, $public ) {
  if ( ! is_array( $envelope ) || ! is_string( $envelope['payload'] ?? null ) || ! is_string( $envelope['signature'] ?? null )
   || strlen( $envelope['payload'] ) > 16384 || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) { return false; }
  $payload = base64_decode( $envelope['payload'], true );
  $sig = base64_decode( $envelope['signature'], true );
  $key = base64_decode( $public, true );
  if ( false === $payload || false === $sig || false === $key || strlen( $sig ) !== 64 || strlen( $key ) !== 32 ) { return false; }
  try {
   if ( ! sodium_crypto_sign_verify_detached( $sig, $payload, $key ) ) { return false; }
  } catch ( Throwable $error ) { return false; }
  $claims = json_decode( $payload, true );
  $state = $this->state();
  if ( ! is_array( $claims ) || ( $claims['product'] ?? '' ) !== $this->config['product']
   || ( $claims['site'] ?? '' ) !== self::canonical_site( home_url( '/' ) )
   || ( $claims['install_id'] ?? '' ) !== ( $state['install_id'] ?? '' )
   || ! is_int( $claims['issued_at'] ?? null ) || ! is_int( $claims['valid_until'] ?? null )
   || $claims['issued_at'] > time() + 300 || $claims['valid_until'] > $claims['issued_at'] + 604800
   || ! in_array( $claims['status'] ?? '', array( 'active', 'suspended', 'revoked', 'expired', 'installation_mismatch' ), true ) ) { return false; }
  return $claims;
 }

 public function allowed() {
  if ( $this->recovery_claims() ) { return true; }
  $state = $this->state();
  $claims = $this->decode( $state['receipt'] ?? null, $this->config['public_key'] );
  return $claims && 'active' === $claims['status'] && $claims['valid_until'] > time();
 }

 public function check() {
  if ( $this->recovery_claims() ) { return; }
  $state = $this->state();
  if ( empty( $state['token'] ) ) { return; }
  $this->request( 'check', '' );
 }

 private function request( $route, $license ) {
  if ( ! $this->config['public_key'] ) { return false; }
  $state = $this->state();
  $nonce = bin2hex( random_bytes( 16 ) );
  $body = array( 'product' => $this->config['product'], 'site' => home_url( '/' ), 'install_id' => $state['install_id'],
   'version' => $this->config['version'], 'nonce' => $nonce );
  $event = null;
  if ( 'activate' === $route ) { $body['license'] = $license; }
  else {
   $body['token'] = $state['token'];
   if ( ! empty( $state['events'] ) ) {
    $event = reset( $state['events'] );
    $body['event'] = $event;
    // Event version is separate from current installed version.
   }
  }
  $endpoint = $this->config['endpoint'];
  $response = wp_remote_post( trailingslashit( $endpoint ) . $route, array(
   'timeout' => 8, 'redirection' => 0, 'sslverify' => true,
   'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), 'body' => wp_json_encode( $body ),
   'limit_response_size' => 24576,
  ) );
  $envelope = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
  $claims = $this->decode( $envelope, $this->config['public_key'] );
  // Error pages, timeouts and unsigned denials cannot revoke a valid cached lease.
  if ( ! $claims || ( $claims['nonce'] ?? '' ) !== $nonce || abs( time() - $claims['issued_at'] ) > 300 ) {
   $state = $this->state(); $state['error'] = true; $this->save( $state );
   return false;
  }
  if ( 'activate' === $route && 'active' === $claims['status'] && ( ! is_string( $claims['token'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $claims['token'] ) ) ) { return false; }
  $state = $this->state();
  if ( 'check' === $route && ( $state['token'] ?? '' ) !== $body['token'] ) { return false; }
  $previous = $this->decode( $state['receipt'] ?? null, $this->config['public_key'] );
  if ( $previous && ( $previous['license_id'] ?? 0 ) === ( $claims['license_id'] ?? 0 )
   && ( ( $claims['license_revision'] ?? 0 ) < ( $previous['license_revision'] ?? 0 )
    || ( $claims['install_revision'] ?? 0 ) < ( $previous['install_revision'] ?? 0 ) ) ) { return false; }
  if ( 'activate' === $route && 'active' === $claims['status'] ) { $state['token'] = $claims['token']; }
  if ( 'activate' === $route && 'active' === $claims['status'] ) { unset( $state['recovery_code'] ); }
  // Local token and signed receipt are never exposed in notices or URLs.
  $state['receipt'] = $envelope;
  $state['last_contact'] = time(); $state['error'] = false;
  if ( $event ) {
   $state['events'] = array_values( array_filter( $state['events'] ?? array(), static function ( $queued ) use ( $event ) { return $queued['id'] !== $event['id']; } ) );
  }
  $this->save( $state );
  if ( ! empty( $state['events'] ) && ! wp_next_scheduled( $this->hook . '_drain' ) ) {
   wp_schedule_single_event( time() + 15, $this->hook . '_drain' );
  }
  return 'active' === $claims['status'];
 }

 public function menu() {
  add_options_page( 'لایسنس ' . $this->config['name'], 'لایسنس ' . $this->config['name'], 'manage_options', 'license-' . $this->config['product'], array( $this, 'page' ) );
 }

 // The owner master uses a high-entropy code; only its SHA-256 verifier ships in products.
 // Signed, scoped WNR1 credentials remain available separately.
 public function recovery_claims( $code = null ) {
  if ( null === $code ) { $code = $this->state()['recovery_code'] ?? ''; }
  if ( is_string( $code ) && preg_match( '/^WNM-[A-F0-9]{64}$/D', $code )
   && hash_equals( self::MASTER_RECOVERY_HASH, hash( 'sha256', $code ) ) ) {
   return array( 'type' => 'webinew-owner-master-v1', 'product' => '*', 'site' => '*', 'issued_at' => 1, 'expires_at' => 0 );
  }
  if ( ! is_string( $code ) || strlen( $code ) > 8192 || ! preg_match( '/^WNR1\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/D', $code, $parts )
   || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) { return false; }
  $payload = base64_decode( strtr( $parts[1], '-_', '+/' ), true );
  $signature = base64_decode( strtr( $parts[2], '-_', '+/' ), true );
  $public = base64_decode( $this->config['public_key'], true );
  if ( false === $payload || false === $signature || false === $public || strlen( $signature ) !== 64 || strlen( $public ) !== 32 ) { return false; }
  try {
   if ( ! sodium_crypto_sign_verify_detached( $signature, $payload, $public ) ) { return false; }
  } catch ( Throwable $error ) { return false; }
  $claims = json_decode( $payload, true );
  if ( ! is_array( $claims ) || 'webinew-recovery-v1' !== ( $claims['type'] ?? '' )
   || ! in_array( $claims['product'] ?? null, array( '*', $this->config['product'] ), true )
   || ! in_array( $claims['site'] ?? null, array( '*', self::canonical_site( home_url( '/' ) ) ), true )
   || ! is_int( $claims['issued_at'] ?? null ) || $claims['issued_at'] < 1 || $claims['issued_at'] > time() + 300
   || ! is_int( $claims['expires_at'] ?? null ) || $claims['expires_at'] < 0
   || ( 0 !== $claims['expires_at'] && ( $claims['expires_at'] <= time() || $claims['expires_at'] <= $claims['issued_at'] ) ) ) { return false; }
  return $claims;
 }

 public function activate_recovery( $code ) {
  if ( ! current_user_can( 'manage_options' ) ) { return false; }
  check_admin_referer( $this->option );
  if ( ! $this->recovery_claims( $code ) ) { return false; }
  $state = $this->state();
  $state['recovery_code'] = $code; $state['error'] = false;
  $this->save( $state );
  wp_clear_scheduled_hook( $this->hook );
  wp_clear_scheduled_hook( $this->hook . '_drain' );
  return true;
 }

 public function notice() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  $state = $this->state();
  if ( ! $this->allowed() || ( ! $this->recovery_claims() && ! empty( $state['error'] ) ) ) {
   $url = admin_url( 'options-general.php?page=license-' . $this->config['product'] );
   echo '<div class="notice notice-warning"><p>' . esc_html( $this->config['name'] ) . ': ';
   echo $this->allowed() ? 'ارتباط با سامانه مجوز برقرار نشد؛ افزونه تا پایان اعتبار ذخیره‌شده کار می‌کند. برای رفع عیب با وبینیو تماس بگیرید.' : 'مجوز معتبر در دسترس نیست؛ قابلیت‌های افزونه متوقف است. برای بررسی یا فعال‌سازی با وبینیو تماس بگیرید.';
   echo ' <a href="' . esc_url( $url ) . '">مدیریت لایسنس</a></p></div>';
  }
 }

 public function page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  $this->maintain();
  $message = '';
  if ( isset( $_POST['webinew_recovery_remove'] ) ) {
   check_admin_referer( $this->option );
   $state = $this->state(); unset( $state['recovery_code'] ); $this->save( $state );
   $this->maintain();
   $message = 'فعال‌سازی اضطراری حذف شد؛ بررسی معمول لایسنس دوباره فعال است.';
  }
  if ( isset( $_POST['webinew_license_save'] ) ) {
   check_admin_referer( $this->option );
   $license = isset( $_POST['license_key'] ) && is_string( $_POST['license_key'] ) ? trim( wp_unslash( $_POST['license_key'] ) ) : '';
   if ( 0 === strpos( $license, 'WNM-' ) ) {
    $message = $this->activate_recovery( $license ) ? 'کد ثابت مالک پذیرفته شد؛ افزونه بدون ارتباط با سرور و بدون نیاز به آماده‌سازی بسته فعال است.' : 'کد ثابت مالک معتبر نیست.';
   } elseif ( ! $this->config['public_key'] || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
    $message = 'بسته انتشار باید توسط وبینیو در پنل مرکزی آماده شود و PHP Sodium هاست فعال باشد.';
   } else {
    if ( 0 === strpos( $license, 'WNR1.' ) ) {
     $message = $this->activate_recovery( $license ) ? 'مجوز اضطراری آفلاین فعال شد؛ هیچ اتصال به سرور انجام نشد.' : 'کد اضطراری معتبر نیست؛ امضا، دامنه، محصول و تاریخ انقضا را بررسی کنید.';
    } elseif ( $license ) {
     $license = strtoupper( $license );
     $ok = preg_match( '/^WN-[A-F0-9]{48}$/D', $license ) && $this->request( 'activate', $license );
     $message = $ok ? 'مجوز فعال شد.' : 'فعال‌سازی ناموفق بود؛ کلید، دامنه، وضعیت مجوز و اتصال را بررسی کنید.';
    } else { $this->check(); $message = 'وضعیت مجوز بررسی شد.'; }
   }
  }
  $state = $this->state();
  $claims = $this->decode( $state['receipt'] ?? null, $this->config['public_key'] );
  $recovery = $this->recovery_claims();
  echo '<div class="wrap" dir="rtl"><h1>لایسنس ' . esc_html( $this->config['name'] ) . '</h1>';
  if ( $message ) { echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>'; }
  echo '<p>وضعیت: <strong>' . esc_html( $recovery ? 'فعال‌سازی اضطراری آفلاین' : ( $claims ? $claims['status'] : 'فعال‌سازی نشده' ) ) . '</strong> — قابلیت‌ها: ' . ( $this->allowed() ? 'فعال' : 'متوقف' ) . '</p>';
  if ( $recovery ) { echo '<p>اتصال و بررسی دوره‌ای سرور متوقف است. پایان اعتبار (UTC): ' . esc_html( $recovery['expires_at'] ? gmdate( 'Y-m-d H:i:s', $recovery['expires_at'] ) : 'بدون انقضا' ) . '. این کد از راه دور لغو نمی‌شود.</p>'; }
  if ( $claims && ! $recovery ) { echo '<p>دلیل: ' . esc_html( $claims['reason'] ?? '' ) . '</p><p>پایان اعتبار ذخیره‌شده (UTC): ' . esc_html( gmdate( 'Y-m-d H:i:s', $claims['valid_until'] ) ) . '</p>'; }
  echo '<p>این افزونه آدرس عمومی سایت، نام و نسخه افزونه، شناسه نصب و رویدادهای فعال‌سازی و تغییر نسخه را برای مدیریت مجوز به وبینیو ارسال می‌کند. اطلاعات سفارش یا مشتری ارسال نمی‌شود. لغو مجوز در بررسی بعدی قابلیت‌ها را متوقف می‌کند. در قطع ارتباط، آخرین مجوز معتبر حداکثر تا ۷ روز پس از صدور اعتبار دارد.</p><form method="post">';
  wp_nonce_field( $this->option );
  if ( ! $this->config['public_key'] && ! $recovery ) { echo '<p><strong>برای لایسنس معمول، بسته باید در پنل مرکزی آماده شود. کد ثابت مالک WNM به آماده‌سازی یا ارتباط با سرور نیاز ندارد.</strong></p>'; }
  echo '<p>سرور مجوز: <code dir="ltr">' . esc_html( $this->config['endpoint'] ) . '</code></p><p>تنظیمات اعتماد سرور داخل بسته انتشار قرار دارد و از این صفحه قابل تغییر نیست.</p>';
  echo '<table class="form-table"><tr><th><label for="wn-key">کلید لایسنس یا کد اضطراری</label></th><td><input dir="ltr" class="large-text" id="wn-key" name="license_key" type="password" autocomplete="off"><p>کد ثابت مالک WNM بدون سرور، آماده‌سازی یا صدور قبلی مجوز این سایت کار می‌کند. کد امضاشده WNR1 هم پشتیبانی می‌شود. کد اضطراری برای ادامه کار محلی ذخیره می‌شود؛ کلید معمول WN ذخیره نمی‌شود.</p></td></tr></table>';
  submit_button( 'فعال‌سازی یا بررسی مجوز', 'primary', 'webinew_license_save' );
  if ( $recovery ) { submit_button( 'حذف فعال‌سازی اضطراری و بازگشت به لایسنس معمول', 'secondary', 'webinew_recovery_remove' ); }
  echo '</form><p>شناسه نصب: <code>' . esc_html( $state['install_id'] ) . '</code></p><hr><p>احسان قسیمی - Ehsan Ghasimi | وبینیو</p><p>وبینیو: طراحی سایت وردپرسی و اختصاصی، سئو، گوگل ادز، تولید محتوای ویدئویی، تلگرام ادز و متا ادز.</p><a href="https://webinew.com/about-us/">درباره وبینیو و خدمات</a></div>';
 }
}

