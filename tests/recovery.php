<?php
require_once '/workspace/webinew-license-manager/includes/recovery.php';
$recovery_secret = base64_decode( get_option( 'wnlm_signing_key' ), true );
$recovery_config = array( 'product' => 'recovery-test', 'name' => 'Recovery test', 'version' => '1.0.0', 'file' => '/recovery-test/plugin.php', 'endpoint' => 'https://offline.test/api/', 'public_key' => WNLM_Core::public_key() );
$recovery_client = new Webinew_License_Client_V1( $recovery_config );
$recovery_client->maintain();
$master = WNLM_Recovery_Code::issue( '*', '*', 0, $recovery_secret );
$before_calls = $GLOBALS['remote_calls'];
$GLOBALS['network_down'] = true;
expect( $recovery_client->activate_recovery( $master ) && $recovery_client->allowed(), 'perpetual recovery master activates without server contact' );
$recovery_client->maintain(); $recovery_client->check(); $recovery_client->deactivate_plugin();
expect( $GLOBALS['remote_calls'] === $before_calls && ! wp_next_scheduled( 'webinew_check_recovery-test' ), 'recovery suppresses cron checks, event draining and deactivation network requests' );
$other_config = $recovery_config; $other_config['product'] = 'another-product';
$other_client = new Webinew_License_Client_V1( $other_config );
$old_home = $GLOBALS['test_home']; $GLOBALS['test_home'] = 'https://another.test/';
expect( false !== $other_client->recovery_claims( $master ), 'master works for another product and domain from the same issuer' );
$GLOBALS['test_home'] = $old_home;
$limited = WNLM_Recovery_Code::issue( 'recovery-test', $old_home, 30, $recovery_secret );
expect( false !== $recovery_client->recovery_claims( $limited ) && false === $other_client->recovery_claims( $limited ), 'scoped recovery supports longer offline periods and rejects another product' );
$GLOBALS['test_home'] = 'https://wrong.test/';
expect( false === $recovery_client->recovery_claims( $limited ), 'scoped recovery rejects another site' );
$GLOBALS['test_home'] = $old_home;
$segments = explode( '.', $master );
$altered = json_decode( base64_decode( strtr( $segments[1], '-_', '+/' ) ), true );
$altered['site'] = 'wrong.test';
$tampered = 'WNR1.' . rtrim( strtr( base64_encode( json_encode( $altered ) ), '+/', '-_' ), '=' ) . '.' . $segments[2];
expect( false === $recovery_client->recovery_claims( $tampered ), 'recovery rejects altered claims without a valid new signature' );
$sign_recovery = static function ( $changes ) use ( $recovery_secret ) {
 $claims = array_merge( array( 'type' => 'webinew-recovery-v1', 'product' => '*', 'site' => '*', 'issued_at' => time() - 3600, 'expires_at' => 0 ), $changes );
 $payload = json_encode( $claims );
 return 'WNR1.' . rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' ) . '.' . rtrim( strtr( base64_encode( sodium_crypto_sign_detached( $payload, $recovery_secret ) ), '+/', '-_' ), '=' );
};
expect( false === $recovery_client->recovery_claims( $sign_recovery( array( 'expires_at' => time() - 1 ) ) ), 'expired emergency code is rejected' );
expect( false === $recovery_client->recovery_claims( $sign_recovery( array( 'issued_at' => time() + 1000 ) ) ), 'future-dated emergency code is rejected' );
expect( false === $recovery_client->recovery_claims( $sign_recovery( array( 'type' => 'online-lease' ) ) ), 'recovery cannot reinterpret another signed message type' );
expect( false === $recovery_client->recovery_claims( $sign_recovery( array( 'expires_at' => '0' ) ) ), 'recovery requires strictly typed expiry claims' );
expect( false === $recovery_client->recovery_claims( 'WNR1.' . str_repeat( 'a', 9000 ) . '.bad' ), 'oversized recovery codes are rejected' );
$foreign = WNLM_Recovery_Code::issue( '*', '*', 0, sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() ) );
expect( false === $recovery_client->recovery_claims( $foreign ), 'a master from another signing authority is rejected' );
$unprepared_config = $recovery_config; $unprepared_config['public_key'] = ''; $unprepared_config['product'] = 'unprepared';
$unprepared_client = new Webinew_License_Client_V1( $unprepared_config );
expect( false === $unprepared_client->recovery_claims( $master ), 'unprepared products cannot accept recovery via a database-inserted trust key' );
$state = $recovery_client->state(); $state['recovery_code'] = $tampered;
update_option( 'webinew_license_recovery_test', $state );
expect( ! $recovery_client->allowed(), 'editing stored recovery data cannot bypass signature verification' );
$GLOBALS['can_manage'] = false;
expect( ! $recovery_client->activate_recovery( $master ), 'non-admin cannot install a recovery credential' );
try { WNLM_Recovery::backup(); expect( false, 'unauthorized signing backup' ); } catch ( RuntimeException $e ) { expect( true, 'private signing backup requires admin capability' ); }
try { WNLM_Recovery::generate(); expect( false, 'unauthorized recovery issuance' ); } catch ( RuntimeException $e ) { expect( true, 'recovery issuance requires admin capability' ); }
$GLOBALS['can_manage'] = true; $GLOBALS['nonce_valid'] = false;
try { $recovery_client->activate_recovery( $master ); expect( false, 'CSRF activation' ); } catch ( RuntimeException $e ) { expect( $e->getMessage() === 'bad_nonce', 'recovery activation checks nonce' ); }
try { WNLM_Recovery::backup(); expect( false, 'CSRF signing backup' ); } catch ( RuntimeException $e ) { expect( $e->getMessage() === 'bad_nonce', 'private signing backup checks nonce' ); }
try { WNLM_Recovery::generate(); expect( false, 'CSRF recovery issuance' ); } catch ( RuntimeException $e ) { expect( $e->getMessage() === 'bad_nonce', 'recovery issuance checks nonce' ); }
$GLOBALS['nonce_valid'] = true;
$_POST = array( 'recovery_product' => '*', 'recovery_site' => '*', 'recovery_days' => '0' );
$issued_master = WNLM_Recovery::generate();
expect( false !== $recovery_client->recovery_claims( $issued_master ), 'central admin issuer generates SDK-compatible master code' );
$backup = WNLM_Recovery::backup();
expect( $backup['secret_key'] === base64_encode( $recovery_secret ) && $backup['public_key'] === WNLM_Core::public_key(), 'backup retains existing signing authority for offline issuance without key rotation' );
$audit_text = json_encode( $wpdb->get_results( 'SELECT detail FROM wp_wnlm_audit' ) );
expect( false === strpos( $audit_text, $issued_master ) && false === strpos( $audit_text, $backup['secret_key'] ), 'audit trail never stores bearer recovery codes or private signing keys' );
$_POST = array( 'license_key' => $master, 'webinew_license_save' => '1' );
ob_start(); $recovery_client->page(); $recovery_html = ob_get_clean();
expect( $recovery_client->allowed() && strpos( $recovery_html, 'فعال‌سازی اضطراری آفلاین' ) !== false && strpos( $recovery_html, $master ) === false, 'existing license input accepts recovery and never displays the stored code' );
$_POST = array( 'webinew_recovery_remove' => '1' );
ob_start(); $recovery_client->page(); ob_end_clean();
expect( ! $recovery_client->allowed() && wp_next_scheduled( 'webinew_check_recovery-test' ), 'admin can remove recovery and restore normal scheduled licensing' );
expect( $GLOBALS['remote_calls'] === $before_calls, 'all emergency activation and removal operations remain offline' );
$GLOBALS['network_down'] = false; $_POST = array();
