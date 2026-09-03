import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const versions = process.argv.slice(2).length ? process.argv.slice(2) : ['8.1','8.2','8.3'];
for (const php of versions) {
  const cli = await runCLI({
    command: 'server', php, wp: '6.4',
    mount: [{hostPath:(process.env.PLUGIN_PATH || './plugins/realestate-platform'),vfsPath:'/wordpress/wp-content/plugins/realestate-platform'}],
    blueprint: {steps:[{step:'activatePlugin',pluginPath:'/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php'}]}
  });
  const code = String.raw`<?php
require '/wordpress/wp-load.php'; require_once ABSPATH.'wp-admin/includes/plugin.php';
$results=[]; $check=function($name,$ok,$detail='')use(&$results){$results[$name]=['pass'=>(bool)$ok,'detail'=>$detail];};
$check('active', is_plugin_active('realestate-platform/realestate-platform.php'));
$check('version', REALESTATE_PLATFORM_VERSION==='0.4.0');
$check('schema_option', get_option('realestate_platform_db_version')==='002', (string)get_option('realestate_platform_db_version'));
global $wpdb; $table=$wpdb->prefix.'rep_schema_migrations';
$check('table', $wpdb->get_var($wpdb->prepare('SELECT name FROM sqlite_master WHERE type=%s AND name=%s','table',$table))===$table, $table);
$row=$wpdb->get_row("SELECT * FROM {$table} WHERE migration_id='002'",ARRAY_A);
$check('migration_row', is_array($row) && strlen($row['checksum']??'')===64, json_encode($row));
$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
deactivate_plugins('realestate-platform/realestate-platform.php');
$check('deactivate_preserves', (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===$before);
activate_plugin('realestate-platform/realestate-platform.php'); activate_plugin('realestate-platform/realestate-platform.php');
$check('reactivate_idempotent', (int)(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}")===$before);
$admin=get_role('administrator'); $editor=get_role('editor'); $subscriber=get_role('subscriber');
foreach(\Mayfair\RealEstatePlatform\Capabilities\CapabilityManager::CAPS as $cap){$check('admin_cap_'.$cap,$admin->has_cap($cap));$check('editor_no_'.$cap,!$editor->has_cap($cap));$check('subscriber_no_'.$cap,!$subscriber->has_cap($cap));}
$check('unrelated_cap',$admin->has_cap('edit_posts'));
$s=new \Mayfair\RealEstatePlatform\Settings\SettingsManager(); $s->initializeDefaults();
$check('setting_default',$s->get('operating_mode')==='compatibility');
$admin_id=1; wp_set_current_user($admin_id); $check('setting_valid',$s->update('operating_mode','standalone') && $s->get('operating_mode')==='standalone');
$content=\Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->services()->get('content');$content->initialize();
foreach(['property','project','insight'] as $entity)$check('phase2_cpt_'.$entity,post_type_exists($entity));
foreach(['property_type','property_status','property_category','property_label','property_feature','property_amenity','location','project_type','insight_topic'] as $taxonomy)$check('phase2_tax_'.$taxonomy,taxonomy_exists($taxonomy));
$property_id=wp_insert_post(['post_type'=>'property','post_title'=>'Phase 2 Property','post_status'=>'publish']);$check('property_create',is_int($property_id)&&$property_id>0);update_post_meta($property_id,'rep_price',12500000.0);$check('property_read',(float)get_post_meta($property_id,'rep_price',true)===12500000.0);wp_update_post(['ID'=>$property_id,'post_title'=>'Updated Property']);$check('property_update',get_the_title($property_id)==='Updated Property');
$attachment_id=wp_insert_attachment(['post_title'=>'Floor plan','post_mime_type'=>'image/jpeg','post_status'=>'inherit']);update_post_meta($property_id,'rep_floor_plan',$attachment_id);$check('media_association',(int)get_post_meta($property_id,'rep_floor_plan',true)===$attachment_id);update_post_meta($property_id,'rep_latitude',128);$check('invalid_coordinate_rejected',get_post_meta($property_id,'rep_latitude',true)==='');
$project_id=wp_insert_post(['post_type'=>'project','post_title'=>'Phase 2 Project','post_status'=>'publish']);$insight_id=wp_insert_post(['post_type'=>'insight','post_title'=>'Phase 2 Insight','post_status'=>'publish']);$check('project_create',is_int($project_id)&&$project_id>0);$check('insight_create',is_int($insight_id)&&$insight_id>0);
$loc=wp_insert_term('Delhi','location');if(is_wp_error($loc))$loc=['term_id'=>(int)get_term_by('name','Delhi','location')->term_id];wp_set_object_terms($property_id,[(int)$loc['term_id']],'location');$check('taxonomy_assignment',has_term('Delhi','location',$property_id));
do_action('rest_api_init');$rest_property=rest_do_request(new WP_REST_Request('GET','/wp/v2/properties/'.$property_id));$check('property_rest_read',$rest_property->get_status()===200,(string)$rest_property->get_status());
$before_object=get_post_type_object('property');$content->initialize();$check('duplicate_registration_prevented',get_post_type_object('property')===$before_object);
$deleted=wp_delete_post($insight_id,true);$check('insight_delete',$deleted instanceof WP_Post && get_post($insight_id)===null);
try{$s->update('operating_mode','evil');$invalid=false;}catch(InvalidArgumentException $e){$invalid=true;}$check('setting_invalid',$invalid);
$sub=wp_create_user('phase1subscriber','disposable-pass','sub@example.test');(new WP_User($sub))->set_role('subscriber');wp_set_current_user($sub);$check('setting_permission',$s->update('operating_mode','migration')===false);$create_property=new WP_REST_Request('POST','/wp/v2/properties');$create_property->set_body_params(['title'=>'Unauthorized','status'=>'publish']);$check('property_rest_write_denied',rest_do_request($create_property)->get_status()===403);wp_set_current_user($admin_id);
$runner=\Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->services()->get('diagnostics');$diag=$runner->run();$check('diagnostics',count($diag)>=10 && !array_filter($diag,fn($r)=>!in_array($r->status,['PASS','WARN','FAIL'],true)));$check('remediation',!array_filter($diag,fn($r)=>!is_string($r->remediation)));
do_action('rest_api_init');
wp_set_current_user(0);$r=rest_do_request(new WP_REST_Request('GET','/realestate-platform/v1/status'));$check('rest_unauth',$r->get_status()===403,(string)$r->get_status());
wp_set_current_user($sub);$r=rest_do_request(new WP_REST_Request('GET','/realestate-platform/v1/status'));$check('rest_unauthorized',$r->get_status()===403,(string)$r->get_status());
wp_set_current_user($admin_id);$r=rest_do_request(new WP_REST_Request('GET','/realestate-platform/v1/status'));$data=$r->get_data();$check('rest_admin',$r->get_status()===200 && isset($data['diagnostics']) && !isset($data['settings']));
$server=rest_get_server();$routes=$server->get_routes();$check('rest_schema',isset($routes['/realestate-platform/v1/status']));
if(!post_type_exists('property'))register_post_type('property',['public'=>true]);if(!post_type_exists('project'))register_post_type('project',['public'=>true]);if(!post_type_exists('insight'))register_post_type('insight',['public'=>true]);if(!taxonomy_exists('mpd_location'))register_taxonomy('mpd_location',['property']);
update_option('active_plugins',array_merge((array)get_option('active_plugins',[]),['mayfair-core/mayfair-core.php','mayfair-forms-leads/forms.php']));
if(!class_exists('ACF')){class ACF{}} if(!class_exists('Elementor\\Plugin')){eval('namespace Elementor; class Plugin {}');} if(!class_exists('ElementorPro\\Plugin')){eval('namespace ElementorPro; class Plugin {}');} if(!class_exists('WooCommerce')){class WooCommerce{}}
$d=new \Mayfair\RealEstatePlatform\Compatibility\CompatibilityDetector();$snap=$d->snapshot();
foreach(['mayfair_core','mayfair_forms_leads','acf','elementor','elementor_pro','woocommerce'] as $key)$check('detect_'.$key,$snap[$key]===true,json_encode($snap));
$check('detect_cpts',count(array_intersect(['property','project','insight'],$snap['post_types']))===3);
$check('detect_taxonomy',in_array('mpd_location',$snap['taxonomies'],true));
$check('compat_mode',$d->recommendedMode()->value==='compatibility');
$check('no_rep_cpts',!post_type_exists('realestate_property'));
$sec='';$log=new \Mayfair\RealEstatePlatform\Logging\OptionLogger();$log->log('error','safe',['password'=>'x','api_key'=>'y','nested'=>['email'=>'z']],'security');$rows=get_option('realestate_platform_log',[]);$last=end($rows);$check('log_redaction',($last['context']['password']??'')==='[REDACTED]'&&($last['context']['nested']['email']??'')==='[REDACTED]');
$check('path_traversal',is_wp_error(\Mayfair\RealEstatePlatform\Security\Security::safePath('/safe','../x')));
$check('token',strlen(\Mayfair\RealEstatePlatform\Security\Security::token())===64);
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/wordpress/wp-content/plugins/realestate-platform'));$syntax=true;$syntaxDetail=[];foreach($files as $f){if($f->isFile()&&$f->getExtension()==='php'){try{token_get_all(file_get_contents($f->getPathname()),TOKEN_PARSE);}catch(ParseError $e){$syntax=false;$syntaxDetail[]=$f->getPathname().':'.$e->getMessage();}}}$check('syntax_all',$syntax,json_encode($syntaxDetail));
$check('php_runtime',PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION==='${php}',PHP_VERSION);
$check('wp_runtime',str_starts_with(get_bloginfo('version'),'6.4'),get_bloginfo('version'));
echo json_encode($results);`;
  const response = await cli.playground.run({code});
  const text = new TextDecoder().decode(response.bytes);
  let results; try { results=JSON.parse(text); } catch(e) { console.error(text,response.errors); throw e; }
  const failed=Object.entries(results).filter(([,v])=>!v.pass);
  console.log(JSON.stringify({php,exitCode:response.exitCode,total:Object.keys(results).length,failed},null,2));
  fs.mkdirSync('verification-results',{recursive:true}); fs.writeFileSync(`verification-results/php-${php}.json`,JSON.stringify(results,null,2));
  await cli[Symbol.asyncDispose]();
  if(failed.length) process.exitCode=1;
}
