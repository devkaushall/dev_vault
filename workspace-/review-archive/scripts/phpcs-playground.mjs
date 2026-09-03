import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.env.PHP_VERSION || '8.3';
const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [{ hostPath: './plugins/realestate-platform', vfsPath: '/workspace/plugin' }],
});

try {
  const code = `<?php
chdir('/workspace/plugin');
$_SERVER['argv'] = array('phpcs', '--standard=/workspace/plugin/phpcs.xml', '--report=full');
include '/workspace/plugin/vendor/squizlabs/php_codesniffer/bin/phpcs';`;
  const result = await cli.playground.run({ code });
  const text = new TextDecoder().decode(result.bytes);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phpcs-${php}.log`, text);
  console.log(text);
  if (/ERRORS|WARNINGS|fatal error|Parse error/i.test(text)) process.exitCode = 1;
} catch (error) {
  const bytes = error?.response?.bytes;
  const text = bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phpcs-${php}.log`, text);
  console.error(text);
  process.exitCode = 1;
} finally {
  await cli[Symbol.asyncDispose]();
}
