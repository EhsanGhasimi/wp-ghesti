<?php
// Execute using tests/licensing-runner.mjs; no production WordPress data is used.
error_reporting( E_ALL );
require '/workspace/.validation/sodium_compat/autoload.php';
define( 'ABSPATH', '/wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['opts'] = array(); $GLOBALS['hooks'] = array(); $GLOBALS['scheduled'] = array();
$GLOBALS['test_home'] = 'https://example.test/shop/'; $GLOBALS['network_down'] = false;
$GLOBALS['remote_calls'] = 0; $GLOBALS['can_manage'] = true; $GLOBALS['nonce_valid'] = true;

function expect( $value, $message ) { if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); } echo "PASS: $message\n"; }
function get_option( $key, $default = false ) { return $GLOBALS['opts'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['opts'][ $key ] = $value; return true; }
function add_option( $key, $value, $unused = '', $autoload = null ) { return update_option( $key, $value ); }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_shortcode( $name, $callback ) { $GLOBALS['shortcodes'][ $name ] = $callback; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function wc_get_price_excluding_tax( $product ) { return $product->net; }
function wc_get_price_to_display( $product ) { return $product->display; }
function wc_get_price_decimals() { return 0; }
function wc_price( $amount ) { return number_format( $amount, 2, '.', '' ); }
function add_filter( $name, $callback ) { add_action( $name, $callback ); }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function wp_next_scheduled( $name ) { return $GLOBALS['scheduled'][ $name ] ?? false; }
function wp_schedule_event( $at, $interval, $name ) { $GLOBALS['scheduled'][ $name ] = $at; }
function wp_schedule_single_event( $at, $name ) { $GLOBALS['scheduled'][ $name ] = $at; }
function wp_clear_scheduled_hook( $name ) { unset( $GLOBALS['scheduled'][ $name ] ); }
function wp_generate_uuid4() { $x = bin2hex( random_bytes( 16 ) ); return substr( $x, 0, 8 ) . '-' . substr( $x, 8, 4 ) . '-4' . substr( $x, 13, 3 ) . '-a' . substr( $x, 17, 3 ) . '-' . substr( $x, 20 ); }
function home_url( $path = '' ) { return $GLOBALS['test_home']; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function get_current_user_id() { return 7; }
function is_ssl() { return $GLOBALS['ssl'] ?? true; }
function get_transient( $key ) { return get_option( 'transient_' . $key ); }
function set_transient( $key, $value, $ttl ) { return update_option( 'transient_' . $key, $value ); }
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function check_admin_referer( $action ) { if ( ! $GLOBALS['nonce_valid'] ) { throw new RuntimeException( 'bad_nonce' ); } }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function wp_unslash( $text ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_url_raw( $text, $protocols = array() ) { return $text; }
function absint( $number ) { return abs( (int) $number ); }
function admin_url( $path ) { return 'https://central.test/wp-admin/' . $path; }
function rest_url( $path ) { return 'https://central.test/wp-json/' . $path; }
function trailingslashit( $text ) { return rtrim( $text, '/' ) . '/'; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function submit_button( $title, $type = '', $name = '', $wrap = true ) { echo '<button>' . esc_html( $title ) . '</button>'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function paginate_links( $args ) { return ''; }
function wp_kses_post( $html ) { return $html; }
function add_options_page() {}
function add_menu_page() {}
function add_submenu_page() {}
function plugins_url( $path, $file ) { return 'http://127.0.0.1:4811/plugin/' . $path; }
function is_plugin_active( $plugin ) { return $GLOBALS['parent_active'] ?? true; }
function register_rest_route() {}
class WP_Error { public $code; public $data; public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; $this->data = $data; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_REST_Response { public $data; public $status; public $headers; public function __construct( $data, $status = 200, $headers = array() ) { $this->data = $data; $this->status = $status; $this->headers = $headers; } }
class Request { private $data; public function __construct( $data ) { $this->data = $data; } public function get_json_params() { return $this->data; } public function get_body() { return json_encode( $this->data ); } }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_post( $url, $args ) {
 ++$GLOBALS['remote_calls'];
 $GLOBALS['last_remote_url'] = $url;
 if ( $GLOBALS['network_down'] ) { return new WP_Error( 'timeout' ); }
 if ( isset( $GLOBALS['override_response'] ) ) { return $GLOBALS['override_response']; }
 $request = new Request( json_decode( $args['body'], true ) );
 $result = WNLM_API::guard( $request );
 if ( ! is_wp_error( $result ) ) { $result = substr( $url, -8 ) === 'activate' ? WNLM_API::activate( $request ) : WNLM_API::check( $request ); }
 if ( ! is_wp_error( $result ) && isset( $GLOBALS['transform_response'] ) ) { $result->data = ( $GLOBALS['transform_response'] )( $result->data ); }
 return is_wp_error( $result ) ? array( 'response' => array( 'code' => $result->data['status'] ), 'body' => '{}' )
  : array( 'response' => array( 'code' => $result->status ), 'body' => json_encode( $result->data ) );
}

// SQLite executes the handlers' SQL. Only MySQL's locking/IGNORE syntax is adapted.
class TestDB {
 public $prefix = 'wp_'; public $pdo; public $insert_id = 0; public $last_error = ''; public $fail_event = false;
 public function __construct() { $this->pdo = new PDO( 'sqlite::memory:' ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); }
 public function prepare( $sql, ...$args ) { $i = 0; return preg_replace_callback( '/%[ds]/', function ( $m ) use ( &$i, $args ) { $v = $args[ $i++ ]; return '%d' === $m[0] ? (string) (int) $v : $this->pdo->quote( (string) $v ); }, $sql ); }
 private function adapt( $sql ) { return str_replace( array( ' FOR UPDATE', 'INSERT IGNORE', 'START TRANSACTION' ), array( '', 'INSERT OR IGNORE', 'BEGIN TRANSACTION' ), $sql ); }
 public function query( $sql ) { try { if ( $this->fail_event && strpos( $sql, 'INTO wp_wnlm_events' ) !== false ) { return false; } $result = $this->pdo->exec( $this->adapt( $sql ) ); $this->insert_id = (int) $this->pdo->lastInsertId(); return $result; } catch ( Throwable $e ) { $this->last_error = $e->getMessage(); throw $e; } }
 public function get_row( $sql ) { $r = $this->pdo->query( $this->adapt( $sql ) )->fetch( PDO::FETCH_OBJ ); return $r ?: null; }
 public function get_results( $sql ) { return $this->pdo->query( $this->adapt( $sql ) )->fetchAll( PDO::FETCH_OBJ ); }
 public function get_var( $sql ) { return $this->pdo->query( $this->adapt( $sql ) )->fetchColumn(); }
 public function insert( $table, $data ) { $q = 'INSERT INTO ' . $table . ' (' . implode( ',', array_keys( $data ) ) . ') VALUES (' . implode( ',', array_fill( 0, count( $data ), '?' ) ) . ')'; $s = $this->pdo->prepare( $q ); $r = $s->execute( array_values( $data ) ); $this->insert_id = (int) $this->pdo->lastInsertId(); return $r ? 1 : false; }
 public function update( $table, $data, $where ) { $parts = array(); foreach ( $data as $k => $v ) { $parts[] = $k . '=?'; } $conditions = array(); foreach ( $where as $k => $v ) { $conditions[] = $k . '=?'; } $s = $this->pdo->prepare( 'UPDATE ' . $table . ' SET ' . implode( ',', $parts ) . ' WHERE ' . implode( ' AND ', $conditions ) ); return $s->execute( array_merge( array_values( $data ), array_values( $where ) ) ) ? $s->rowCount() : false; }
 public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
}
$wpdb = new TestDB();
foreach ( array(
 'products' => 'id INTEGER PRIMARY KEY, slug TEXT UNIQUE, title TEXT, created_at TEXT',
 'licenses' => 'id INTEGER PRIMARY KEY, product_id INTEGER, key_hash TEXT UNIQUE, key_hint TEXT, site TEXT, status TEXT, revision INTEGER DEFAULT 1, expires_at TEXT, customer TEXT, reason TEXT DEFAULT "", created_at TEXT',
 'installs' => 'id INTEGER PRIMARY KEY, license_id INTEGER UNIQUE, install_uuid TEXT, token_hash TEXT, version TEXT, status TEXT, revision INTEGER DEFAULT 1, reason TEXT DEFAULT "", first_seen TEXT, last_seen TEXT, last_update TEXT',
 'events' => 'id INTEGER PRIMARY KEY, install_id INTEGER, event_uuid TEXT, kind TEXT, version TEXT, occurred_at TEXT, received_at TEXT, UNIQUE(install_id,event_uuid)',
 'audit' => 'id INTEGER PRIMARY KEY, actor_id INTEGER, action TEXT, object_id INTEGER, detail TEXT, created_at TEXT',
) as $table => $schema ) { $wpdb->query( 'CREATE TABLE wp_wnlm_' . $table . ' (' . $schema . ')' ); }
require '/workspace/webinew-license-manager/includes/core.php';
require '/workspace/webinew-license-manager/includes/api.php';
require '/workspace/webinew-license-manager/includes/admin.php';
require '/workspace/webinew-license-manager/includes/view.php';
require '/workspace/webinew-license-manager/includes/packager.php';
require '/workspace/sdk/class-webinew-license-client.php';
$kp = sodium_crypto_sign_keypair();
update_option( 'wnlm_signing_key', base64_encode( sodium_crypto_sign_secretkey( $kp ) ) );
expect( strlen( base64_decode( WNLM_Core::public_key() ) ) === 32, 'Ed25519 public key derived from server secret' );
expect( WNLM_Core::site( 'https://EXAMPLE.test/shop/' ) === 'example.test/shop', 'canonical domain includes subsite path' );
expect( WNLM_Core::site( 'https://user:pass@example.test/' ) === '' && WNLM_Core::site( 'javascript:alert(1)' ) === '', 'malformed and credential-bearing site URLs rejected' );
$mutate = new ReflectionMethod( 'WNLM_Admin', 'mutate' ); $mutate->setAccessible( true );
$_POST = array( 'wnlm_action' => 'product', 'slug' => 'wp-snapppay', 'title' => 'Snappay' ); $mutate->invoke( null );
$_POST = array( 'wnlm_action' => 'license', 'product_id' => '1', 'site' => home_url(), 'customer' => 'Customer' );
$issued = $mutate->invoke( null ); preg_match( '/WN-[A-F0-9]{48}/', $issued, $match ); $license_key = $match[0];
expect( $wpdb->get_var( 'SELECT key_hash FROM wp_wnlm_licenses WHERE id=1' ) === hash( 'sha256', $license_key ), 'only license hash is stored centrally' );
$config = array( 'product' => 'wp-snapppay', 'name' => 'Snappay', 'version' => '1.1.0', 'file' => '/product.php', 'endpoint' => 'https://central.test/wp-json/webinew-licenses/v1/', 'public_key' => WNLM_Core::public_key() );
$client = new Webinew_License_Client_V1( $config ); $client->maintain();
$option = 'webinew_license_wp_snapppay';
$state = $client->state(); $state['public_key'] = WNLM_Core::public_key(); $state['endpoint'] = $config['endpoint']; update_option( $option, $state );
expect( ! $client->allowed() && $GLOBALS['remote_calls'] === 0, 'unlicensed capabilities blocked without frontend network calls' );
$request = new ReflectionMethod( 'Webinew_License_Client_V1', 'request' ); $request->setAccessible( true );
expect( $request->invoke( $client, 'activate', $license_key ) && $client->allowed(), 'domain-bound license activates and signed receipt permits operation' );
$original = $client->state();
expect( hash( 'sha256', $original['token'] ) === $wpdb->get_var( 'SELECT token_hash FROM wp_wnlm_installs WHERE id=1' ), 'installation token is hashed on the server' );
$client->check();
expect( count( $client->state()['events'] ) === 0, 'pending install event acknowledged and removed after valid response' );
$event = array( 'id' => wp_generate_uuid4(), 'kind' => 'update', 'version' => '1.0.9', 'at' => gmdate( 'Y-m-d H:i:s' ) );
$state = $client->state(); $state['events'] = array( $event ); update_option( $option, $state ); $client->check();
$state = $client->state(); $state['events'] = array( $event ); update_option( $option, $state ); $client->check();
expect( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM wp_wnlm_events WHERE event_uuid=%s', $event['id'] ) ) === 1, 'retried event is deduplicated' );
expect( $wpdb->get_var( $wpdb->prepare( 'SELECT version FROM wp_wnlm_events WHERE event_uuid=%s', $event['id'] ) ) === '1.0.9', 'queued event preserves its original version' );
$saved = $client->state();
$GLOBALS['network_down'] = true; $client->check();
expect( $client->allowed() && $client->state()['error'] && $client->state()['receipt'] === $saved['receipt'], 'network outage retains but never extends the signed lease' );
$GLOBALS['network_down'] = false;
$bad = $saved['receipt']; $bad['signature'] = base64_encode( str_repeat( 'x', 64 ) );
$GLOBALS['override_response'] = array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $bad ) ); $client->check();
expect( $client->allowed() && $client->state()['receipt'] === $saved['receipt'], 'forged signature is rejected without overriding cached license' );
$GLOBALS['override_response']['body'] = json_encode( $saved['receipt'] ); $client->check();
expect( $client->state()['error'], 'signed response replay with old challenge is rejected' );
unset( $GLOBALS['override_response'] );
$GLOBALS['test_home'] = 'https://copied.test/shop/';
expect( ! $client->allowed(), 'copying entire options database to another domain does not transfer license' );
expect( ! $request->invoke( $client, 'activate', $license_key ), 'license key cannot activate a different domain' );
$GLOBALS['test_home'] = 'https://example.test/shop/'; update_option( $option, $saved );
$clone = $saved; $clone['install_id'] = wp_generate_uuid4(); update_option( $option, $clone );
expect( ! $request->invoke( $client, 'activate', $license_key ) && ! $client->allowed(), 'second installation cannot replace the licensed installation UUID' );
update_option( $option, $saved );
$_POST = array( 'wnlm_action' => 'install_status', 'id' => '1', 'status' => 'suspended', 'reason' => 'Support hold' ); $mutate->invoke( null ); $client->check();
expect( ! $client->allowed(), 'central installation suspension stops product capabilities' );
$old_claims = json_decode( base64_decode( $saved['receipt']['payload'] ), true );
$GLOBALS['transform_response'] = static function ( $envelope ) use ( $old_claims ) {
 $fresh = json_decode( base64_decode( $envelope['payload'] ), true );
 $old_claims['nonce'] = $fresh['nonce']; $old_claims['issued_at'] = $fresh['issued_at'];
 return WNLM_Core::sign( $old_claims );
};
$client->check();
expect( ! $client->allowed(), 'older signed active decision cannot override newer suspension revision' );
unset( $GLOBALS['transform_response'] );
$GLOBALS['network_down'] = true; $client->check(); expect( ! $client->allowed(), 'outage cannot undo a signed suspension' ); $GLOBALS['network_down'] = false;
$_POST['status'] = 'active'; $_POST['reason'] = ''; $mutate->invoke( null ); $client->check(); expect( $client->allowed(), 'central reinstatement restores capabilities' );
$_POST = array( 'wnlm_action' => 'license_status', 'id' => '1', 'status' => 'revoked', 'reason' => 'Cancelled' ); $mutate->invoke( null ); $client->check(); expect( ! $client->allowed(), 'revoked license overrides active installation' );
$_POST['status'] = 'active'; $_POST['reason'] = ''; $mutate->invoke( null );
$wpdb->update( 'wp_wnlm_licenses', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'id' => 1 ) ); $client->check(); expect( ! $client->allowed(), 'expired license stops capabilities' );
$wpdb->update( 'wp_wnlm_licenses', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) ), array( 'id' => 1 ) ); $client->check();
$state = $client->state(); $claims = json_decode( base64_decode( $state['receipt']['payload'] ), true );
expect( $client->allowed() && $claims['valid_until'] <= time() + 3600, 'offline lease never outlives paid license expiration' );
$claims['issued_at'] = time() - 604800; $claims['valid_until'] = time() - 1; $state['receipt'] = WNLM_Core::sign( $claims ); update_option( $option, $state );
expect( ! $client->allowed(), 'offline lease expiration blocks without requiring a network call' );
$client->check();
$new_config = $config; $new_config['version'] = '1.2.0'; $new_client = new Webinew_License_Client_V1( $new_config ); $new_client->maintain(); $new_client->check();
expect( $wpdb->get_var( 'SELECT version FROM wp_wnlm_installs WHERE id=1' ) === '1.2.0', 'new product version and version-change log recorded' );
$state = $new_client->state(); $state['events'][] = array( 'id' => wp_generate_uuid4(), 'kind' => 'activate', 'version' => '1.2.0', 'at' => gmdate( 'Y-m-d H:i:s' ) ); update_option( $option, $state );
$wpdb->fail_event = true; $new_client->check(); expect( ! empty( $new_client->state()['events'] ), 'database write failure does not acknowledge pending event' );
$wpdb->fail_event = false; $new_client->check(); expect( empty( $new_client->state()['events'] ), 'event retries successfully after database recovery' );
expect( ! empty( $GLOBALS['hooks']['webinew_check_wp-snapppay_drain'] ), 'queue drain callback is registered outside admin_menu' );
$GLOBALS['can_manage'] = false;
try { $mutate->invoke( null ); expect( false, 'unauthorized mutation' ); } catch ( RuntimeException $e ) { expect( true, 'admin operations require administrator capability' ); }
$GLOBALS['can_manage'] = true; $GLOBALS['nonce_valid'] = false;
try { $mutate->invoke( null ); expect( false, 'CSRF accepted' ); } catch ( RuntimeException $e ) { expect( $e->getMessage() === 'bad_nonce', 'admin mutations require nonce validation' ); }
$GLOBALS['nonce_valid'] = true;
$GLOBALS['ssl'] = false; expect( is_wp_error( WNLM_API::guard( new Request( array() ) ) ), 'API rejects non-HTTPS requests' ); $GLOBALS['ssl'] = true;
expect( is_wp_error( WNLM_API::activate( new Request( array( 'site' => array() ) ) ) ), 'malformed JSON input rejected' );
$wpdb->update( 'wp_wnlm_licenses', array( 'reason' => '<script>alert(1)</script>' ), array( 'id' => 1 ) );
$_POST = array(); $_GET = array( 'page' => 'webinew-licenses-licenses' ); $_SERVER['REQUEST_METHOD'] = 'GET';
ob_start(); WNLM_Admin::page(); $html = ob_get_clean();
expect( strpos( $html, '<script>alert(1)</script>' ) === false && strpos( $html, '&lt;script&gt;' ) !== false, 'central dashboard escapes stored untrusted text' );
ob_start(); $new_client->page(); $html = ob_get_clean(); expect( strpos( $html, $license_key ) === false, 'client settings do not expose raw license key' );
expect( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_wnlm_audit' ) >= 6, 'license issuance and administrator status changes are audited' );
// Reissuing a license for the same UUID must select by credential, not an old row.
$_POST = array( 'wnlm_action' => 'license_status', 'id' => '1', 'status' => 'revoked', 'reason' => 'Reissued' ); $mutate->invoke( null );
$_POST = array( 'wnlm_action' => 'license', 'product_id' => '1', 'site' => home_url() );
$issued = $mutate->invoke( null ); preg_match( '/WN-[A-F0-9]{48}/', $issued, $match );
expect( $request->invoke( $new_client, 'activate', $match[0] ), 'replacement license can activate existing client UUID' );
$new_client->check();
expect( $new_client->allowed() && ! $new_client->state()['error'], 'heartbeat selects replacement installation by token instead of old revoked license' );
$_POST = array( 'wnlm_action' => 'license_expiry', 'id' => '2', 'expires' => gmdate( 'Y-m-d', time() + 86400 * 30 ) ); $mutate->invoke( null ); $new_client->check();
expect( $new_client->allowed() && $wpdb->get_var( 'SELECT expires_at FROM wp_wnlm_licenses WHERE id=2' ) !== null, 'administrator can renew existing license without changing its key' );
$state = $new_client->state(); $state['endpoint'] = 'https://attacker.test/'; $state['public_key'] = base64_encode( random_bytes( 32 ) ); update_option( $option, $state );
$new_client->check();
expect( $new_client->allowed() && $GLOBALS['last_remote_url'] === $config['endpoint'] . 'check', 'changing database options cannot replace pinned server URL or public key' );
$zip = new ZipArchive(); $zip->open( '/source.zip', ZipArchive::CREATE );
$sdk_content = file_get_contents( '/workspace/sdk/class-webinew-license-client.php' );
$zip->addFromString( 'wp-snapppay/wp-snapppay.php', '<?php /* Plugin Name: Snappay */' );
$zip->addFromString( 'wp-snapppay/includes/class-webinew-license-client.php', $sdk_content ); $zip->close();
WNLM_Packager::prepare( '/source.zip', 'wp-snapppay', '/prepared.zip' );
$zip->open( '/prepared.zip' ); $pinned_json = $zip->getFromName( 'wp-snapppay/webinew-license-config.json' ); $pinned = json_decode( $pinned_json, true );
expect( $pinned['public_key'] === WNLM_Core::public_key() && $pinned['product'] === 'wp-snapppay' && strpos( $pinned_json, get_option( 'wnlm_signing_key' ) ) === false, 'release packager embeds only public trust configuration, never private signing key' );
expect( $zip->getFromName( 'wp-snapppay/includes/class-webinew-license-client.php' ) === $sdk_content, 'release packager preserves SDK bytes' ); $zip->close();
$zip->open( '/unsafe.zip', ZipArchive::CREATE ); $zip->addFromString( '../evil.php', 'bad' ); $zip->addFromString( 'wp-snapppay/file.php', 'x' ); $zip->close();
try { WNLM_Packager::prepare( '/unsafe.zip', 'wp-snapppay', '/bad.zip' ); expect( false, 'unsafe ZIP accepted' ); }
catch ( RuntimeException $e ) { expect( true, 'release packager rejects ZIP path traversal' ); }
$generic = $config; unset( $generic['public_key'] ); $generic['file'] = '/generic-product/main.php';
$generic_client = new Webinew_License_Client_V1( $generic );
expect( ! $generic_client->allowed(), 'unprepared package cannot trust a public key inserted into database options' );
$calls = $GLOBALS['remote_calls']; $new_client->allowed(); $new_client->maintain();
expect( $GLOBALS['remote_calls'] === $calls, 'normal page checks and maintenance never perform network I/O' );
$GLOBALS['opts']['transient_wnlm_rate_' . hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown' )] = 120;
expect( is_wp_error( WNLM_API::guard( new Request( array() ) ) ), 'API applies request rate limit' );
// Exercise the actual shipping product bootstrap, not only the reusable client.
class WooCommerce {}
file_put_contents( '/workspace/wp-snapppay/webinew-license-config.json', wp_json_encode( array( 'product' => 'wp-snapppay', 'endpoint' => $config['endpoint'], 'public_key' => WNLM_Core::public_key() ) ) );
require '/workspace/wp-snapppay/wp-snapppay.php';
update_option( WP_Snapppay::OPTION, array( 'enabled' => 'yes', 'percent' => '13' ) );
$sample_product = (object) array( 'net' => 1000000, 'display' => 1000000 );
expect( '282500.00' === WP_Snapppay_Notification::amount( $sample_product ), 'installments include configured 13 percent surcharge before division by four' );
$sample_product->display = 1100000;
expect( '307500.00' === WP_Snapppay_Notification::amount( $sample_product ), 'installments use tax-exclusive fee base and displayed product tax without taxing the fee' );
update_option( WP_Snapppay::OPTION, array( 'enabled' => 'no', 'percent' => '13' ) );
expect( '275000.00' === WP_Snapppay_Notification::amount( $sample_product ), 'disabled surcharge is omitted from installments' );
update_option( WP_Snapppay::OPTION, array( 'enabled' => 'yes', 'percent' => '20' ) );
$variation_data = WP_Snapppay_Notification::variation( array( 'display_price' => 1100000 ), null, $sample_product );
expect( '325000.00' === $variation_data['wp_snapppay_installment'], 'variation response carries server-formatted installment with changed percentage' );
update_option( WP_Snapppay::OPTION, array() );
$GLOBALS['hooks'] = array(); $GLOBALS['parent_active'] = false; WP_Snapppay::boot();
expect( empty( $GLOBALS['hooks']['woocommerce_cart_calculate_fees'] ), 'shipping Snappay bootstrap blocks functionality when Webinew parent is inactive' );
$GLOBALS['parent_active'] = true; WP_Snapppay::boot();
expect( isset( $GLOBALS['shortcodes']['wp_snapppay_notice'] ), 'notification shortcode registers with valid license and parent' );
expect( strpos( WP_Snapppay_Notification::settings()['content'], 'https://wa.me/989361810430' ) !== false, 'notification default WhatsApp link uses international phone format' );
update_option( WP_Snapppay_Notification::OPTION, array( 'enabled' => 'no' ) );
expect( '' === WP_Snapppay_Notification::render(), 'disabled notification returns no markup' );
expect( ! empty( $GLOBALS['hooks']['woocommerce_cart_calculate_fees'] ), 'shipping Snappay bootstrap enables fee hooks with valid license and active parent' );
$GLOBALS['hooks'] = array(); $state = $new_client->state(); unset( $state['receipt'] ); update_option( $option, $state ); WP_Snapppay::boot();
expect( empty( $GLOBALS['hooks']['woocommerce_cart_calculate_fees'] ), 'shipping Snappay bootstrap blocks fee hooks without valid license' );
$_GET = array( 'page' => 'webinew-licenses-licenses', 'status' => 'active' ); $_POST = array();
ob_start(); WNLM_Admin::page(); $filtered_html = ob_get_clean();
expect( strpos( $filtered_html, 'مجوز #2' ) !== false && strpos( $filtered_html, 'مجوز #1' ) === false, 'admin status filter shows active licenses and omits revoked records' );
$_GET = array( 'page' => 'webinew-licenses' ); ob_start(); WNLM_Admin::page(); $overview_html = ob_get_clean();
expect( strpos( $overview_html, 'اولین مشتری را در چهار قدم متصل کنید' ) !== false && strpos( $overview_html, 'wnlm-public-key' ) === false, 'overview gives a start path without exposing advanced API details' );
$_GET = array( 'page' => 'webinew-licenses-help' ); ob_start(); WNLM_Admin::page(); $help_html = ob_get_clean();
expect( strpos( $help_html, 'wnlm-public-key' ) !== false && strpos( $help_html, 'wnlm-advanced' ) !== false, 'technical connection details remain available in the help disclosure' );
if ( defined( 'WNLM_UI_PREVIEW' ) ) {
 mkdir( '/ui' );
 $wpdb->update( 'wp_wnlm_products', array( 'title' => 'افزایش درصد اسنپ‌پی' ), array( 'id' => 1 ) );
 $wpdb->update( 'wp_wnlm_licenses', array( 'customer' => 'فروشگاه نمونه', 'reason' => 'بررسی وضعیت مجوز' ), array( 'id' => 1 ) );
 foreach ( array( 'overview', 'products', 'packages', 'licenses', 'activity', 'help' ) as $screen ) {
  $_GET = array( 'page' => 'overview' === $screen ? 'webinew-licenses' : 'webinew-licenses-' . $screen ); $_POST = array();
  ob_start(); WNLM_Admin::page(); $preview = ob_get_clean(); file_put_contents( '/ui/' . $screen . '.html', $preview );
 }
 foreach ( array( 'events', 'installs', 'licenses', 'audit' ) as $table ) { $wpdb->query( 'DELETE FROM wp_wnlm_' . $table ); }
 $_GET = array( 'page' => 'webinew-licenses' );
 ob_start(); WNLM_Admin::page(); file_put_contents( '/ui/empty.html', ob_get_clean() );
}
require __DIR__ . '/recovery.php';
require __DIR__ . '/fixed-master.php';
echo "ALL LICENSE INTEGRATION TESTS PASSED\n";
