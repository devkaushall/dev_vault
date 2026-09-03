import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.argv[2] || '8.3';
const cli = await runCLI({
  command: 'server', php, wp: '6.4',
  mount: [{ hostPath: './plugins/realestate-platform', vfsPath: '/wordpress/wp-content/plugins/realestate-platform' }],
  blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php' }] }
});
const code = String.raw`<?php
require '/wordpress/wp-load.php';
wp_set_current_user(1);
update_option('realestate_platform_settings_general',['operating_mode'=>'standalone']);
$content=\Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->services()->get('content');
$content->initialize(); do_action('rest_api_init');
$results=[];
$record=function($name,$pass,$detail=[])use(&$results){$results[$name]=['pass'=>(bool)$pass,'detail'=>$detail];};
$snapshot=function($id){$p=get_post($id);$meta=get_post_meta($id);ksort($meta);return ['title'=>$p->post_title,'slug'=>$p->post_name,'status'=>$p->post_status,'featured_media'=>(int)get_post_thumbnail_id($id),'meta'=>$meta,'project_type'=>wp_get_object_terms($id,'project_type',['fields'=>'ids']),'insight_topic'=>wp_get_object_terms($id,'insight_topic',['fields'=>'ids'])];};
$request=function($method,$route,$params=null,$raw=null){$r=new WP_REST_Request($method,$route);if(null!==$params)$r->set_body_params($params);if(null!==$raw){$r->set_header('Content-Type','application/json');$r->set_body($raw);}return rest_do_request($r);};
$assert_error=function($name,$resp,$before,$after)use($record){$data=$resp->get_data();$encoded=wp_json_encode($data);$structured=is_array($data)&&isset($data['code'],$data['message']);$safe=!preg_match('/(?:Stack trace|Fatal error|\/wordpress\/|\/home\/|SQLSTATE|wpdb)/i',(string)$encoded);$record($name,$resp->get_status()>=400&&$resp->get_status()<500&&$structured&&$before===$after&&$safe,['status'=>$resp->get_status(),'code'=>$data['code']??null,'message'=>$data['message']??null,'no_mutation'=>$before===$after,'safe'=>$safe]);};
$project=wp_insert_post(['post_type'=>'project','post_title'=>'Project baseline','post_status'=>'publish']);
$insight=wp_insert_post(['post_type'=>'insight','post_title'=>'Insight baseline','post_status'=>'publish']);
$attachment=wp_insert_attachment(['post_title'=>'REST attachment','post_mime_type'=>'image/jpeg','post_status'=>'inherit']);
$valid=$request('POST','/wp/v2/projects/'.$project,['meta'=>['rep_price'=>1250000.5,'rep_latitude'=>28.6139,'rep_brochure'=>$attachment]]);$record('project_valid',$valid->get_status()>=200&&$valid->get_status()<300&&(float)get_post_meta($project,'rep_price',true)===1250000.5&&abs((float)get_post_meta($project,'rep_latitude',true)-28.6139)<0.00001&&(int)get_post_meta($project,'rep_brochure',true)===$attachment,['status'=>$valid->get_status()]);
$valid=$request('POST','/wp/v2/insights/'.$insight,['meta'=>['rep_reading_time'=>7,'rep_external_source'=>'https://example.com/source','rep_author_image'=>$attachment]]);$record('insight_valid',$valid->get_status()>=200&&$valid->get_status()<300&&(int)get_post_meta($insight,'rep_reading_time',true)===7&&get_post_meta($insight,'rep_external_source',true)==='https://example.com/source'&&(int)get_post_meta($insight,'rep_author_image',true)===$attachment,['status'=>$valid->get_status()]);
$cases=[
 ['project_wrong_scalar',$project,'projects',['meta'=>['rep_price'=>'abc']]],
 ['project_nested_object',$project,'projects',['meta'=>['rep_latitude'=>['nested'=>'bad']]]],
 ['project_unexpected_array',$project,'projects',['meta'=>['rep_price'=>[1,2]]]],
 ['project_unknown_meta',$project,'projects',['meta'=>['rep_unknown'=>'x']]],
 ['project_oversized',$project,'projects',['meta'=>['rep_address'=>str_repeat('A',65536)]]],
 ['project_invalid_numeric',$project,'projects',['meta'=>['rep_price'=>'NaN']]],
 ['project_invalid_coordinate',$project,'projects',['meta'=>['rep_latitude'=>91]]],
 ['project_invalid_url',$project,'projects',['meta'=>['rep_video'=>'javascript:alert(1)']]],
 ['project_invalid_attachment',$project,'projects',['meta'=>['rep_brochure'=>999999]]],
 ['project_invalid_taxonomy',$project,'projects',['project_type'=>[999999]]],
 ['insight_wrong_scalar',$insight,'insights',['meta'=>['rep_reading_time'=>'abc']]],
 ['insight_nested_object',$insight,'insights',['meta'=>['rep_reading_time'=>['nested'=>'bad']]]],
 ['insight_unexpected_array',$insight,'insights',['meta'=>['rep_reading_time'=>[1]]]],
 ['insight_unknown_meta',$insight,'insights',['meta'=>['rep_unknown'=>'x']]],
 ['insight_oversized',$insight,'insights',['meta'=>['rep_subtitle'=>str_repeat('A',2049)]]],
 ['insight_invalid_numeric',$insight,'insights',['meta'=>['rep_reading_time'=>-1]]],
 ['insight_invalid_url',$insight,'insights',['meta'=>['rep_external_source'=>'javascript:alert(1)']]],
 ['insight_invalid_attachment',$insight,'insights',['meta'=>['rep_author_image'=>999999]]],
 ['insight_invalid_taxonomy',$insight,'insights',['insight_topic'=>[999999]]]
];
foreach($cases as [$name,$id,$base,$params]){$before=$snapshot($id);$resp=$request('POST','/wp/v2/'.$base.'/'.$id,$params);$assert_error($name,$resp,$before,$snapshot($id));}
foreach([['project_null_optional_reset',$project,'projects','rep_price'],['insight_null_optional_reset',$insight,'insights','rep_reading_time']] as [$name,$id,$base,$field]){$resp=$request('POST','/wp/v2/'.$base.'/'.$id,['meta'=>[$field=>null]]);$record($name,$resp->get_status()>=200&&$resp->get_status()<300&&get_post_meta($id,$field,true)==='', ['status'=>$resp->get_status(),'reset'=>get_post_meta($id,$field,true)==='']);}
foreach([['project_missing_meta',$project,'projects'],['insight_missing_meta',$insight,'insights']] as [$name,$id,$base]){$before=$snapshot($id);$resp=$request('POST','/wp/v2/'.$base.'/'.$id,['title'=>get_the_title($id)]);$record($name,$resp->get_status()>=200&&$resp->get_status()<300&&$before===$snapshot($id),['status'=>$resp->get_status(),'unchanged'=>$before===$snapshot($id)]);}
foreach([['project_empty_optional',$project,'projects','rep_address'],['insight_empty_optional',$insight,'insights','rep_subtitle']] as [$name,$id,$base,$field]){$resp=$request('POST','/wp/v2/'.$base.'/'.$id,['meta'=>[$field=>'']]);$record($name,$resp->get_status()>=200&&$resp->get_status()<300&&get_post_meta($id,$field,true)==='', ['status'=>$resp->get_status()]);}
foreach([['project_malformed_json',$project,'projects'],['insight_malformed_json',$insight,'insights']] as [$name,$id,$base]){$before=$snapshot($id);$resp=$request('POST','/wp/v2/'.$base.'/'.$id,null,'{"meta":');$assert_error($name,$resp,$before,$snapshot($id));}
wp_set_current_user(0);foreach([['project_unauthorized',$project,'projects'],['insight_unauthorized',$insight,'insights']] as [$name,$id,$base]){$before=$snapshot($id);$resp=$request('POST','/wp/v2/'.$base.'/'.$id,['title'=>'Denied']);$assert_error($name,$resp,$before,$snapshot($id));}
$sub=wp_create_user('rest_subscriber','temporary-test-only','rest-subscriber@example.test');(new WP_User($sub))->set_role('subscriber');wp_set_current_user($sub);foreach([['project_insufficient_capability',$project,'projects'],['insight_insufficient_capability',$insight,'insights']] as [$name,$id,$base]){$before=$snapshot($id);$resp=$request('POST','/wp/v2/'.$base.'/'.$id,['title'=>'Denied']);$assert_error($name,$resp,$before,$snapshot($id));}
wp_set_current_user(1);foreach([['project_xss',$project,'projects','rep_address'],['insight_xss',$insight,'insights','rep_subtitle']] as [$name,$id,$base,$field]){$resp=$request('POST','/wp/v2/'.$base.'/'.$id,['meta'=>[$field=>'<script>alert(1)</script>Safe']]);$stored=get_post_meta($id,$field,true);$record($name,$resp->get_status()>=200&&$resp->get_status()<300&&!str_contains($stored,'<script')&&str_contains($stored,'Safe'),['status'=>$resp->get_status(),'stored'=>$stored]);}
$failed=array_keys(array_filter($results,fn($v)=>!$v['pass']));echo wp_json_encode(['status'=>$failed?'FAIL':'PASS','environment'=>['php'=>PHP_VERSION,'wordpress'=>get_bloginfo('version')],'failed'=>$failed,'checks'=>$results],JSON_PRETTY_PRINT);`;
const response=await cli.playground.run({code}); const text=new TextDecoder().decode(response.bytes); const json=text.slice(text.indexOf('{')); fs.mkdirSync('verification-results',{recursive:true}); fs.writeFileSync(`verification-results/rest-contract-php-${php}.json`,json); console.log(json); await cli[Symbol.asyncDispose](); if(JSON.parse(json).status!=='PASS')process.exitCode=1;
