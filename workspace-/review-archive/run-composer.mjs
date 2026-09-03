import {runCLI} from '@wp-playground/cli';
const args=process.argv.slice(2);
const c=await runCLI({command:'server',php:'8.3',wp:'6.4',mount:[{hostPath:'.',vfsPath:'/workspace'}]});
const encoded=JSON.stringify(['composer.phar',...args]);
const code=`<?php chdir('/workspace/plugins/realestate-platform'); $_SERVER['argv']=$GLOBALS['argv']=${encoded}; $_SERVER['argc']=$GLOBALS['argc']=count($GLOBALS['argv']); putenv('COMPOSER_HOME=/tmp/composer-home'); require '/workspace/composer.phar';`;
try { const r=await c.playground.run({code}); console.log(new TextDecoder().decode(r.bytes)); console.error(r.errors); process.exitCode=r.exitCode; } finally { await c[Symbol.asyncDispose](); }
