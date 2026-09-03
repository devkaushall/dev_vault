<?php
/** Actual A-pass/B-database-fail/C-stop/retry verification. Disposable DB only. */
use Mayfair\RealEstatePlatform\Contracts\DatabaseInterface;
use Mayfair\RealEstatePlatform\Database\WpDatabase;
use Mayfair\RealEstatePlatform\Logging\OptionLogger;
use Mayfair\RealEstatePlatform\Migration\MigrationRunner;

$dir = sys_get_temp_dir() . '/rep-migration-fixtures';
wp_mkdir_p( $dir );
$template = <<<'PHP'
<?php
return new class implements \Mayfair\RealEstatePlatform\Contracts\MigrationInterface {
 public function id(): string { return '%s'; }
 public function up( \Mayfair\RealEstatePlatform\Contracts\DatabaseInterface $db ): void { %s }
};
PHP;
file_put_contents( "$dir/A.php", sprintf( $template, 'A', '$db->query("CREATE TABLE IF NOT EXISTS " . $db->prefix() . "rep_test_a (id BIGINT PRIMARY KEY)");' ) );
file_put_contents( "$dir/B.php", sprintf( $template, 'B', 'if (!get_option("rep_allow_b")) { $r=$db->query("THIS IS INTENTIONALLY INVALID SQL"); if (false === $r) { throw new \\RuntimeException("controlled database failure"); } } $db->query("CREATE TABLE IF NOT EXISTS " . $db->prefix() . "rep_test_b (id BIGINT PRIMARY KEY)");' ) );
file_put_contents( "$dir/C.php", sprintf( $template, 'C', '$db->query("CREATE TABLE IF NOT EXISTS " . $db->prefix() . "rep_test_c (id BIGINT PRIMARY KEY)");' ) );
delete_option( 'realestate_platform_applied_migrations' ); delete_option( 'realestate_platform_db_version' ); delete_option( 'rep_allow_b' );
$db = new WpDatabase(); $runner = new MigrationRunner( $db, new OptionLogger(), $dir ); $first_failed = false;
try { $runner->run(); } catch ( RuntimeException $e ) { $first_failed = true; }
global $wpdb; $exists = static fn( string $name ): bool => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $name ) ) === $wpdb->prefix . $name;
$after_failure = array( 'applied' => get_option( 'realestate_platform_applied_migrations', array() ), 'version' => get_option( 'realestate_platform_db_version' ), 'a' => $exists( 'rep_test_a' ), 'b' => $exists( 'rep_test_b' ), 'c' => $exists( 'rep_test_c' ) );
update_option( 'rep_allow_b', true ); $runner->run();
$after_retry = array( 'applied' => get_option( 'realestate_platform_applied_migrations', array() ), 'version' => get_option( 'realestate_platform_db_version' ), 'a' => $exists( 'rep_test_a' ), 'b' => $exists( 'rep_test_b' ), 'c' => $exists( 'rep_test_c' ) );
$pass = $first_failed && $after_failure['applied'] === array( 'A' ) && $after_failure['version'] === 'A' && $after_failure['a'] && ! $after_failure['b'] && ! $after_failure['c'] && $after_retry['applied'] === array( 'A', 'B', 'C' ) && $after_retry['version'] === 'C' && $after_retry['a'] && $after_retry['b'] && $after_retry['c'];
echo wp_json_encode( array( 'status' => $pass ? 'PASS' : 'FAIL', 'first_failed' => $first_failed, 'after_failure' => $after_failure, 'after_retry' => $after_retry, 'fixture_checksums' => array_map( 'hash_file', array_fill( 0, 3, 'sha256' ), array( "$dir/A.php", "$dir/B.php", "$dir/C.php" ) ) ), JSON_PRETTY_PRINT );
if ( ! $pass ) { exit( 1 ); }
