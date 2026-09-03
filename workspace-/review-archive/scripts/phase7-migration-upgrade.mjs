import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';

const php = process.env.PHP_VERSION || '8.3';
const cli = await runCLI({
  command: 'server',
  php,
  wp: '6.4',
  mount: [{ hostPath: process.env.PLUGIN_PATH || './plugins/realestate-platform', vfsPath: '/wordpress/wp-content/plugins/realestate-platform' }],
});
const code = String.raw`<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin( 'realestate-platform/realestate-platform.php' );
global $wpdb;
$history = $wpdb->prefix . 'rep_schema_migrations';
$before = $wpdb->get_results( "SELECT migration_id,checksum FROM {$history} WHERE migration_id IN ('001','002','003') ORDER BY migration_id", ARRAY_A );
foreach ( array( 'rep_notification_events', 'rep_site_visit_history', 'rep_site_visits', 'rep_lead_assignment_history', 'rep_lead_status_history', 'rep_lead_requests', 'rep_leads' ) as $suffix ) {
  $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$suffix}" );
}
$wpdb->delete( $history, array( 'migration_id' => '004' ), array( '%s' ) );
update_option( 'realestate_platform_applied_migrations', array( '001', '002', '003' ), false );
update_option( 'realestate_platform_db_version', '003', false );
\Mayfair\RealEstatePlatform\Core\Lifecycle::maybeUpgrade();
$after = $wpdb->get_results( "SELECT migration_id,checksum FROM {$history} ORDER BY migration_id", ARRAY_A );
$checks = array(
  'version' => get_option( 'realestate_platform_db_version' ) === '004',
  'plugin_version' => get_option( 'realestate_platform_version' ) === REALESTATE_PLATFORM_VERSION,
  'history' => array_column( $after, 'migration_id' ) === array( '001', '002', '003', '004' ),
  'historical_checksums_unchanged' => array_slice( $after, 0, 3 ) === $before,
  'new_checksum' => strlen( (string) ( $after[3]['checksum'] ?? '' ) ) === 64,
);
foreach ( array( 'rep_leads', 'rep_lead_requests', 'rep_lead_status_history', 'rep_lead_assignment_history', 'rep_site_visits', 'rep_site_visit_history', 'rep_notification_events' ) as $suffix ) {
  $table = $wpdb->prefix . $suffix;
  $checks[ 'table_' . $suffix ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}
echo wp_json_encode( array( 'status' => in_array( false, $checks, true ) ? 'FAIL' : 'PASS', 'checks' => $checks, 'environment' => array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'database' => 'SQLite' ) ), JSON_PRETTY_PRINT );`;
try {
  const response = await cli.playground.run({ code });
  const text = new TextDecoder().decode(response.bytes);
  const json = text.slice(text.indexOf('{'));
  const result = JSON.parse(json);
  fs.mkdirSync('verification-results', { recursive: true });
  fs.writeFileSync(`verification-results/phase7-migration-upgrade-${php}.json`, JSON.stringify(result, null, 2) + '\n');
  console.log(JSON.stringify(result, null, 2));
  if (result.status !== 'PASS') process.exitCode = 1;
} catch (error) {
  const bytes = error?.response?.bytes;
  console.error(bytes ? new TextDecoder().decode(bytes instanceof Uint8Array ? bytes : Uint8Array.from(Object.values(bytes))) : String(error));
  process.exitCode = 1;
} finally {
  await cli[Symbol.asyncDispose]();
}
