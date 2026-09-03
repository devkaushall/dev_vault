import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';
const cli=await runCLI({command:'server',php:process.env.PHP_VERSION||'8.3',wp:'6.4',mount:[{hostPath:'./plugins/realestate-platform',vfsPath:'/workspace/plugin'},{hostPath:'./scripts/php-syntax-runner.php',vfsPath:'/workspace/syntax.php'}]});
const r=await cli.playground.run({scriptPath:'/workspace/syntax.php'});const text=new TextDecoder().decode(r.bytes);const json=text.slice(text.indexOf('{'));fs.mkdirSync('verification-results',{recursive:true});fs.writeFileSync(`verification-results/php-syntax-${process.env.PHP_VERSION||'8.3'}.json`,json);fs.writeFileSync('verification-results/php-syntax.json',json);console.log(json);await cli[Symbol.asyncDispose]();if(JSON.parse(json).status!=='PASS')process.exitCode=1;
