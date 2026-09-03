import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';
const php = process.env.PHP_VERSION || '8.3';
const cli = await runCLI({ command: 'server', php, wp: '6.4', mount: [{ hostPath: './plugins/realestate-platform', vfsPath: '/workspace/plugin' }] });
try {
  const result = await cli.playground.run({ code: `<?php $GLOBALS['_composer_autoload_path'] = '/workspace/plugin/vendor/autoload.php'; $_SERVER['argv'] = array('phpunit', '--configuration=/workspace/plugin/phpunit.xml'); $source = file_get_contents('/workspace/plugin/vendor/phpunit/phpunit/phpunit'); $source = preg_replace('/^#![^\\n]*\\n/', '', (string) $source); $source = preg_replace('/^<\\?php/', '', (string) $source); eval((string) $source);` });
  const text = new TextDecoder().decode(result.bytes);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phpunit-${php}.log`, text);
  console.log(text);
  if (!text.includes('OK') && !text.includes('Tests:')) process.exitCode = 1;
} catch (error) {
  const bytes = error?.response?.bytes;
  const text = bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error);
  console.error(text);
  process.exitCode = 1;
} finally {
  await cli[Symbol.asyncDispose]();
}
