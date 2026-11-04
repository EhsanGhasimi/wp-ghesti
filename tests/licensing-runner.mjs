import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const parent = path.dirname(root);
process.chdir(root);
const localValidation = path.join(root, '.validation');

const validation = process.env.WEBINEW_VALIDATION_DIR || localValidation;
const { PHP } = await import(pathToFileURL(path.join(validation, 'node_modules/@php-wasm/universal/index.js')));
const { loadNodeRuntime } = await import(pathToFileURL(path.join(validation, 'node_modules/@php-wasm/node/index.js')));
const php = new PHP(await loadNodeRuntime(process.argv[2] || '8.3', { emscriptenOptions: { processId: 901 } }));
function copyTree(local, virtual) {
 php.mkdirTree(virtual);
 for (const e of fs.readdirSync(local, { withFileTypes: true })) {
  if (e.isDirectory()) copyTree(path.join(local, e.name), `${virtual}/${e.name}`);
  else php.writeFile(`${virtual}/${e.name}`, fs.readFileSync(path.join(local, e.name)));
 }
}
try {
 const licenseRoot = process.env.WEBINEW_LICENSE_PROJECT || path.join(parent, 'webinew-license-manager');
 const snappayRoot = process.env.WEBINEW_SNAPPAY_PROJECT || path.join(parent, 'wp-snapppay');
 copyTree(path.join(licenseRoot, 'webinew-license-manager'), '/workspace/webinew-license-manager');
 copyTree(path.join(snappayRoot, 'wp-snapppay'), '/workspace/wp-snapppay');
 copyTree(path.join(licenseRoot, 'sdk'), '/workspace/sdk');
 copyTree(path.join(root, 'tests'), '/workspace/tests');
 const privateFixture = process.env.WEBINEW_MASTER_TEST_FILE || path.join(licenseRoot, '.secrets/webinew-master-key.txt');
 if (fs.existsSync(privateFixture)) php.writeFile('/workspace/test-master.txt', fs.readFileSync(privateFixture));
 copyTree(path.join(validation, 'sodium_compat-1.22.0'), '/workspace/.validation/sodium_compat');
 const result = await php.run({ scriptPath: '/workspace/tests/licensing.php' });
 console.log(result.text);
 if (result.errors) console.error(result.errors);
 process.exitCode = result.exitCode || (result.text.includes('ALL LICENSE INTEGRATION TESTS PASSED') ? 0 : 1);
} catch (e) {
 console.error(e.response?.text || e.message);
 process.exitCode = 1;
} finally { php.exit(); }

