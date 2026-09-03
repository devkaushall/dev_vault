import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.env.PHP_VERSION || '8.3';
const memory = process.env.PHP_MEMORY || '256M';
const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [{ hostPath: './plugins/realestate-platform', vfsPath: '/workspace/plugin' }],
});

let text = '';
try {
  const code = `<?php
chdir('/workspace/plugin');
ini_set('memory_limit', '${memory}');
$_SERVER['argv'] = array('phpstan', 'analyse', '--configuration=/workspace/plugin/phpstan.neon', '--no-progress');
$source = file_get_contents('/workspace/plugin/vendor/phpstan/phpstan/phpstan');
$source = preg_replace('/^#![^\\n]*\\n/', '', (string) $source);
$source = preg_replace('/^<\\?php/', '', (string) $source);
$source = str_replace('__DIR__', "'/workspace/plugin/vendor/phpstan/phpstan'", (string) $source);
eval(ltrim((string) $source));`;
  const result = await cli.playground.run({ code });
  text = new TextDecoder().decode(result.bytes);
} catch (error) {
  const bytes = error?.response?.bytes;
  text = bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error);
}
fs.mkdirSync('verification-results', { recursive: true });
fs.writeFileSync(`verification-results/phpstan-${php}-${memory}.log`, text);
console.log(text);
await cli[Symbol.asyncDispose]();
if (/Allowed memory size|Fatal error|Parse error|Internal error/i.test(text)) process.exitCode = 2;
