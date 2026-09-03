import {runCLI} from '@wp-playground/cli';
const cli=await runCLI({command:'server',php:'8.3',wp:'6.4',mount:[{hostPath:'./plugins/realestate-platform',vfsPath:'/wordpress/wp-content/plugins/realestate-platform'}],blueprint:{steps:[{step:'activatePlugin',pluginPath:'/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php'}]}});
const code=String.raw`<?php
require '/wordpress/wp-load.php'; wp_set_current_user(1); update_option('realestate_platform_settings_general',['operating_mode'=>'standalone']); $content=\Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->services()->get('content'); $content->initialize(); do_action('rest_api_init');
$id=wp_insert_post(['post_type'=>'project','post_title'=>'Diagnostic','post_status'=>'publish']);
$server=rest_get_server(); $routes=$server->get_routes(); $route='/wp/v2/projects/(?P<id>[\\d]+)';
$endpoint_summary=[]; foreach($routes[$route] as $i=>$endpoint){$endpoint_summary[$i]=['methods'=>$endpoint['methods']??null,'arg_keys'=>array_keys($endpoint['args']??[])];} $controller=(new WP_REST_Posts_Controller('project')); $item_schema=$controller->get_item_schema(); $schema=null; foreach($routes[$route] as $endpoint){if(!empty($endpoint['methods']['POST'])){$schema=$endpoint['args']['meta']??null;break;}}
$req=new WP_REST_Request('POST','/wp/v2/projects/'.$id); $req->set_body_params(['meta'=>['rep_latitude'=>['nested'=>'bad']]]);
$direct=$schema ? rest_validate_value_from_schema($req->get_param('meta'),$schema,'meta') : null;
$resp=rest_do_request($req);
echo wp_json_encode(['wp'=>get_bloginfo('version'),'methods'=>get_class_methods(WP_REST_Request::class),'route_found'=>isset($routes[$route]),'endpoints'=>$endpoint_summary,'controller_meta_schema'=>$item_schema['properties']['meta']??null,'meta_schema'=>$schema,'params_before'=>$req->get_params(),'direct_validation'=>is_wp_error($direct)?['code'=>$direct->get_error_code(),'message'=>$direct->get_error_message(),'data'=>$direct->get_error_data()]:$direct,'response'=>['status'=>$resp->get_status(),'data'=>$resp->get_data()],'stored'=>get_post_meta($id,'rep_latitude',true)],JSON_PRETTY_PRINT);`;
const response=await cli.playground.run({code}); console.log(new TextDecoder().decode(response.bytes)); await cli[Symbol.asyncDispose]();
