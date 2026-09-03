import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.env.PHP_VERSION || '8.3';
const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [
    { hostPath: './plugins/realestate-platform', vfsPath: '/wordpress/wp-content/plugins/realestate-platform' },
    { hostPath: './scripts/phase9-integrity-runner.php', vfsPath: '/phase9-integrity-runner.php' },
  ],
  blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php' }] },
});
try {
  const result = await cli.playground.run({ code: '<?php require \'/phase9-integrity-runner.php\';' });
  const text = new TextDecoder().decode(result.bytes);
  const start = text.indexOf('{');
  const json = start >= 0 ? text.slice(start) : JSON.stringify({ status: 'FAIL', raw: text });
  const parsed = JSON.parse(json);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phase9-integrity-${php}.json`, JSON.stringify(parsed, null, 2) + '\n');
  console.log(JSON.stringify(parsed, null, 2));
  if (parsed.status !== 'PASS') process.exitCode = 1;
} catch (error) {
  const bytes = error?.response?.bytes;
  const text = bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phase9-integrity-${php}.log`, text);
  console.error(text);
  process.exitCode = 1;
} finally {
  await cli[Symbol.asyncDispose]();
}
