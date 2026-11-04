<?php
$master_config = array( 'product' => 'never-issued-product', 'name' => 'Master test', 'version' => '1', 'file' => '/no-preparation/plugin.php', 'endpoint' => 'https://unreachable.invalid/', 'public_key' => '' );
$master_client = new Webinew_License_Client_V1( $master_config );
expect( false === $master_client->recovery_claims( 'WNM-' . str_repeat( '0', 64 ) ), 'incorrect fixed owner code is rejected without issuer setup' );
expect( false === $master_client->recovery_claims( 'WNM-short' ), 'malformed fixed owner code is rejected' );
if ( is_readable( '/workspace/test-master.txt' ) ) {
 $owner_code = trim( file_get_contents( '/workspace/test-master.txt' ) );
 $GLOBALS['network_down'] = true; $calls_before = $GLOBALS['remote_calls'];
 $master_client->maintain();
 $_POST = array( 'webinew_license_save' => '1', 'license_key' => $owner_code );
 ob_start(); $master_client->page(); $master_html = ob_get_clean();
 expect( $master_client->allowed(), 'fixed owner code activates through license input with no issuer key, no license issuance and no prepared package' );
 expect( false === strpos( $master_html, $owner_code ), 'license page never reveals stored fixed owner code' );
 $master_client->check(); $master_client->maintain(); $master_client->deactivate_plugin();
 expect( $calls_before === $GLOBALS['remote_calls'], 'fixed owner code prevents all SDK network checks while issuer is unreachable' );
 $second_config = $master_config; $second_config['product'] = 'different-unregistered-product';
 $second = new Webinew_License_Client_V1( $second_config );
 $saved_home = $GLOBALS['test_home']; $GLOBALS['test_home'] = 'https://never-issued.test/';
 expect( false !== $second->recovery_claims( $owner_code ), 'the same fixed owner code works across products and new domains without central preparation' );
 $GLOBALS['test_home'] = $saved_home;
 $GLOBALS['can_manage'] = false;
 expect( ! $second->activate_recovery( $owner_code ), 'fixed owner activation still requires administrator capability' );
 $GLOBALS['can_manage'] = true; $GLOBALS['nonce_valid'] = false;
 try { $second->activate_recovery( $owner_code ); expect( false, 'owner CSRF accepted' ); } catch ( RuntimeException $e ) { expect( 'bad_nonce' === $e->getMessage(), 'fixed owner activation still requires nonce' ); }
 $GLOBALS['nonce_valid'] = true;
 $_POST = array( 'webinew_recovery_remove' => '1' );
 ob_start(); $master_client->page(); ob_end_clean();
 expect( ! $master_client->allowed(), 'fixed owner mode can be removed locally' );
 $GLOBALS['network_down'] = false; $_POST = array();
} else {
 echo "SKIP: positive owner-code tests require private local fixture (not distributed).\n";
}
