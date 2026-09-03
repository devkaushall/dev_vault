import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';
const c = await runCLI({command:'server',php:'8.3',wp:'6.4',mount:[{hostPath:'./plugins/realestate-platform',vfsPath:'/wordpress/wp-content/plugins/realestate-platform'}],blueprint:{steps:[{step:'activatePlugin',pluginPath:'/wordpress/wp-content/plugins/realestate-platform/realestate-platform.php'}]}});
const code=String.raw`<?php
require '/wordpress/wp-load.php'; wp_set_current_user(1);
update_option('realestate_platform_settings_general',['operating_mode'=>'standalone']);
$m=\Mayfair\RealEstatePlatform\Core\Bootstrap::instance()->services()->get('content'); $m->initialize();
$w=new \Mayfair\RealEstatePlatform\Search\SearchIndexWriter(new \Mayfair\RealEstatePlatform\Fields\FieldRegistry());
$c=new \Mayfair\RealEstatePlatform\Search\SearchIndexConsistency(); $d=new \Mayfair\RealEstatePlatform\Diagnostics\SearchIndexCheck($c);
global $wpdb; $i=$wpdb->prefix.'rep_search_properties'; $b=$wpdb->prefix.'rep_search_terms';
$id=wp_insert_post(['post_type'=>'property','post_title'=>'Consistent','post_status'=>'publish']);
update_post_meta($id,'rep_city','Delhi'); $term=wp_insert_term('Villa','property_type')['term_id']; wp_set_object_terms($id,[$term],'property_type'); $w->synchronize($id);
$snapshot=function()use($wpdb,$i,$b){return [$wpdb->get_results("SELECT * FROM $i ORDER BY post_id",ARRAY_A),$wpdb->get_results("SELECT * FROM $b ORDER BY post_id,taxonomy,term_id",ARRAY_A)];};
$report=function($name)use($d,$snapshot,&$reports){$before=$snapshot();$diagnostic=$d->run();$after=$snapshot();$r=$diagnostic->details;$r['diagnostic_status']=$diagnostic->status;$r['read_only']=$before===$after;$reports[$name]=$r;return $r;};
$reports=[]; $healthy=$report('healthy');
$wpdb->delete($i,['post_id'=>$id]); $missing=$report('missing'); $w->synchronize($id);
$wpdb->update($i,['city'=>'Corrupt'],['post_id'=>$id]); $stale=$report('stale'); $w->synchronize($id);
$wpdb->query("INSERT INTO $i SELECT 999999,post_modified_gmt,title,slug,keyword_text,reference,country,state,city,locality,neighborhood,postal_code,currency,developer,rera,furnishing,possession,availability,construction_status,price,area,plot_area,bedrooms,bathrooms,floors,floor,parking,latitude,longitude,project_id,featured,verified,indexed_at FROM $i WHERE post_id=".(int)$id); $orphan=$report('orphaned'); $wpdb->delete($i,['post_id'=>999999]);
$wpdb->delete($b,['post_id'=>$id,'taxonomy'=>'property_type']); $tax=$report('taxonomy'); $w->synchronize($id);
$wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_status='draft' WHERE ID=%d",$id)); $visibility=$report('visibility'); $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_status='publish' WHERE ID=%d",$id));
$duplicate_insert=$wpdb->query("INSERT INTO $i SELECT * FROM $i WHERE post_id=".(int)$id); $dupes=$report('duplicates_prevented');
$checks=[
'healthy'=>$healthy['healthy']===true&&$healthy['diagnostic_status']==='PASS',
'missing'=>$missing['missing']===1&&!$missing['healthy'],
'stale'=>$stale['stale']===1&&!$stale['healthy'],
'orphaned'=>$orphan['orphaned']===1&&!$orphan['healthy'],
'taxonomy_mismatch'=>$tax['taxonomy_mismatches']===1&&!$tax['healthy'],
'visibility_mismatch'=>$visibility['visibility_mismatches']===1&&!$visibility['healthy'],
'duplicate_constraint'=>$duplicate_insert===false&&$dupes['duplicates']===0,
'read_only'=>!in_array(false,array_column($reports,'read_only'),true)
];
echo wp_json_encode(['status'=>in_array(false,$checks,true)?'FAIL':'PASS','checks'=>$checks,'duplicate_note'=>'Primary key prevents deliberate duplicate post_id insertion; checker reports zero under enforced schema.','reports'=>$reports],JSON_PRETTY_PRINT);
`;
const o=await c.playground.run({code}); const t=new TextDecoder().decode(o.bytes); const j=t.slice(t.indexOf('{')); fs.writeFileSync('verification-results/phase3-diagnostics.json',j); console.log(j); await c[Symbol.asyncDispose](); if(JSON.parse(j).status!=='PASS')process.exitCode=1;
