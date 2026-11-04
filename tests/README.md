# Tests

Run from the project root: `node tests/licensing-runner.mjs` (PHP 8.3), or append `7.4`.
The integration suite reads the current sources of both sibling projects, not the archived workspace.
Optional overrides: WEBINEW_LICENSE_PROJECT, WEBINEW_SNAPPAY_PROJECT, WEBINEW_VALIDATION_DIR.
The validation runtime uses local `.validation` only (or explicit WEBINEW_VALIDATION_DIR). Local Node dependencies and sodium_compat were copied and SHA256-verified. No files are loaded from the archived workspace.
For a portable setup, install `@php-wasm/cli` and `php-parser` into `.validation` and provide upstream paragonie/sodium_compat v1.22.0 at `.validation/sodium_compat-1.22.0`.
Run `npm install --prefix .validation --no-save --package-lock=false @php-wasm/cli php-parser` to install the Node dependencies.
In the wp-snapppay project, also run `node tests/checkout.test.cjs` from its root.
Tests use shims and SQLite. Verify production behavior with WordPress, MySQL, native Sodium, HTTPS, Cron, and the real payment gateway on staging.


Recovery coverage (tests/recovery.php): offline universal and scoped credentials, signature tampering, invalid issuer, expiry, future timestamps, strict types, credential size, missing trust, permission and nonce enforcement, audited issuance and backup, no secret logging, license form activation/removal, and zero network requests while recovery is active.


Fixed master tests use the owner's private .secrets/webinew-master-key.txt fixture in the licensing project (or WEBINEW_MASTER_TEST_FILE) without printing it. Positive tests are skipped when the owner's private fixture is absent; wrong/malformed-code tests always run. Fixtures are never packaged with the SDK or plugin.

