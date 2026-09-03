import {runCLI} from '@wp-playground/cli';
import fs from 'node:fs';
const [tool,...args]=process.argv.slice(2);
const paths={phpunit:'vendor/phpunit/phpunit/phpunit',phpstan:'vendor/phpstan/phpstan/phpstan',phpcs:'vendor/squizlabs/php_codesniffer/bin/phpcs',phpcbf:'vendor/squizlabs/php_codesniffer/bin/phpcbf'};
if(!paths[tool]) throw new Error('unknown tool');
const original='plugins/realestate-platform/'+paths[tool];
const stripped=original+'.arena-run.php';
fs.writeFileSync(stripped,fs.readFileSync(original,'utf8').replace(/^#![^\n]*\n/,''));
const argv=JSON.stringify([tool,...args]);
fs.writeFileSync('.tool-runner.php',`<?php chdir('/workspace/plugins/realestate-platform'); $_SERVER['argv']=$GLOBALS['argv']=${argv}; $_SERVER['argc']=$GLOBALS['argc']=count($GLOBALS['argv']); require '/workspace/plugins/realestate-platform/${paths[tool]}.arena-run.php';`);
const c=await runCLI({command:'server',php:process.env.PHP_VERSION||'8.3',wp:'6.4',mount:[{hostPath:'.',vfsPath:'/workspace'}]});
try { const r=await c.playground.run({scriptPath:'/workspace/.tool-runner.php'}); console.log(new TextDecoder().decode(r.bytes)); console.error(r.errors); process.exitCode=r.exitCode; }
catch(e){console.error(e?.response?.errors||e);process.exitCode=1;}
finally{await c[Symbol.asyncDispose]();fs.rmSync('.tool-runner.php',{force:true});fs.rmSync(stripped,{force:true});}
