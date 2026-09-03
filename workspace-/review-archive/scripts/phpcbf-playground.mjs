import { runCLI } from '@wp-playground/cli';

const php = process.env.PHP_VERSION || '8.3';
const target = process.env.PHPCBF_TARGET || '/workspace/plugin/src/ImportExport/ExportSerializer.php';
const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [{ hostPath: './plugins/realestate-platform', vfsPath: '/workspace/plugin' }],
});
try {
  const code = `<?php
chdir('/workspace/plugin');
$_SERVER['argv'] = array('phpcbf', '--standard=/workspace/plugin/phpcs.xml', '${target}');
include '/workspace/plugin/vendor/squizlabs/php_codesniffer/bin/phpcbf';`;
  const result = await cli.playground.run({ code });
  console.log(new TextDecoder().decode(result.bytes));
} catch (error) {
  const bytes = error?.response?.bytes;
  console.error(bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error));
} finally {
  await cli[Symbol.asyncDispose]();
}
