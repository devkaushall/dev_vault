import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const pluginPath = process.env.PLUGIN_PATH || './package-install/realestate-platform';
const runtimes = [
  { php: '8.1', wp: '6.4' },
  { php: '8.2', wp: '6.4' },
  { php: '8.3', wp: '6.4' },
];
const results = {};
for (const runtime of runtimes) {
  const client = await runCLI({
    command: 'server',
    php: runtime.php,
    wp: runtime.wp,
    mount: [
      { hostPath: pluginPath, vfsPath: '/wordpress/wp-content/plugins/realestate-platform' },
      { hostPath: './scripts/phase9-runner.php', vfsPath: '/phase9-runner.php' },
    ],
    blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php' }] },
  });
  try {
    const output = await client.playground.run({ code: '<?php require \'/phase9-runner.php\';' });
    const text = new TextDecoder().decode(output.bytes);
    const start = text.indexOf('{');
    results[runtime.php] = JSON.parse(start >= 0 ? text.slice(start) : JSON.stringify({ status: 'FAIL', raw: text }));
  } finally {
    await client[Symbol.asyncDispose]();
  }
}
const result = {
  status: Object.values(results).every((runtime) => runtime.status === 'PASS') ? 'PASS' : 'FAIL',
  package_path: pluginPath,
  runtimes: results,
};
fs.mkdirSync('verification-results', { recursive: true });
fs.writeFileSync('verification-results/phase9-package-runtime-matrix.json', JSON.stringify(result, null, 2) + '\n');
console.log(JSON.stringify(result, null, 2));
if (result.status !== 'PASS') process.exitCode = 1;
