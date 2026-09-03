import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.env.PHP_VERSION || '8.3';
const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [
    { hostPath: process.env.PLUGIN_PATH || './plugins/realestate-platform', vfsPath: '/wordpress/wp-content/plugins/realestate-platform' },
    { hostPath: './scripts/phase7-runner.php', vfsPath: '/workspace/runner.php' },
  ],
  blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php' }] },
});

try {
  const response = await cli.playground.run({ scriptPath: '/workspace/runner.php' });
  const text = new TextDecoder().decode(response.bytes);
  const json = text.slice(text.indexOf('{'));
  const result = JSON.parse(json);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phase7-runtime-${php}.json`, JSON.stringify(result, null, 2) + '\n');
  console.log(JSON.stringify(result, null, 2));
  if (result.status !== 'PASS') process.exitCode = 1;
} catch (error) {
  const bytes = error?.response?.bytes;
  const text = bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error);
  console.error(text);
  process.exitCode = 1;
} finally {
  await cli[Symbol.asyncDispose]();
}
