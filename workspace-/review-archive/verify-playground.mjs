import { runCLI } from '@wp-playground/cli';
const cli = await runCLI({command:'server',php:'8.1',wp:'6.4',login:true,mount:[{hostPath:'./plugins/realestate-platform',vfsPath:'/wordpress/wp-content/plugins/realestate-platform'}],blueprint:{steps:[{step:'activatePlugin',pluginPath:'/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php'}]}});
console.log('URL',cli.serverUrl);
console.log('HOME', (await cli.playground.request({url:'/'})).httpStatusCode);
console.log('REST', (await cli.playground.request({url:'/wp-json/realestate-platform/v1/status'})).httpStatusCode);
await cli[Symbol.asyncDispose]();
