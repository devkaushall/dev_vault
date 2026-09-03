import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

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
    mount: [{ hostPath: './plugins/realestate-platform', vfsPath: '/wordpress/wp-content/plugins/realestate-platform' }, { hostPath: './scripts/phase9-runner.php', vfsPath: '/phase9-runner.php' }],
    blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php' }] },
  });
  const code = `<?php require '/phase9-runner.php';`;
  const output = await client.playground.run({ code });
  const text = new TextDecoder().decode(output.bytes);
  const start = text.indexOf('{');
  const json = start >= 0 ? text.slice(start) : JSON.stringify({ status: 'FAIL', raw: text });
  results[runtime.php] = JSON.parse(json);
  await client[Symbol.asyncDispose]();
}
fs.writeFileSync('verification-results/phase9-runtime-matrix.json', JSON.stringify({ status: Object.values(results).every((result) => result.status === 'PASS') ? 'PASS' : 'FAIL', runtimes: results }, null, 2) + '\n');
console.log(JSON.stringify({ status: Object.values(results).every((result) => result.status === 'PASS') ? 'PASS' : 'FAIL', runtimes: results }, null, 2));
if (Object.values(results).some((result) => result.status !== 'PASS')) process.exitCode = 1;
