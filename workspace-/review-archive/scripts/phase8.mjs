import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.env.PHP_VERSION || '8.3';
const pluginPath = process.env.PLUGIN_PATH || './plugins/realestate-platform';
const adapterFiles = fs.readdirSync(`${pluginPath}/src/Elementor`).filter((file) => file.endsWith('.php'));
const forbidden = /\$wpdb|\b(?:INSERT|UPDATE|DELETE)\s+INTO\b|rep_(?:leads|site_visits|notification_events)/i;
const staticChecks = {
  adapter_files_present: adapterFiles.length >= 5,
  adapter_has_no_direct_sql_or_workflow_tables: adapterFiles.every((file) => !forbidden.test(fs.readFileSync(`${pluginPath}/src/Elementor/${file}`, 'utf8'))),
};
if (Object.values(staticChecks).some((value) => !value)) {
  console.error(JSON.stringify({ status: 'FAIL', static: staticChecks }, null, 2));
  process.exit(1);
}

const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [
    { hostPath: pluginPath, vfsPath: '/wordpress/wp-content/plugins/realestate-platform' },
    { hostPath: './scripts/phase8-runner.php', vfsPath: '/workspace/runner.php' },
  ],
  blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php' }] },
});

try {
  const response = await cli.playground.run({ scriptPath: '/workspace/runner.php' });
  const text = new TextDecoder().decode(response.bytes);
  const json = text.slice(text.indexOf('{'));
  const result = JSON.parse(json);
  result.static = staticChecks;
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phase8-runtime-${php}.json`, JSON.stringify(result, null, 2) + '\n');
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
