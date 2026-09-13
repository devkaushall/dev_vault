<?php
/**
 * The Today screen: the office home page.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Audit\AuditLog;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Worklist;
use EstatOS\Install\Schema;
use EstatOS\Leads\Leads;
use EstatOS\Leads\Visits;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;
use EstatOS\Support\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * Answers one question: what needs my attention today?
 */
final class Today {

	/**
	 * The friendliest name we have for the signed-in person.
	 *
	 * @param object $user Current user.
	 * @return string
	 */
	private static function greeting_name( $user ): string {
		$first = isset( $user->first_name ) ? trim( (string) $user->first_name ) : '';
		if ( '' !== $first ) {
			return $first;
		}
		$display = isset( $user->display_name ) ? trim( (string) $user->display_name ) : '';
		return '' !== $display ? $display : __( 'there', 'estat-os' );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		/*
		 * Every other screen checks this before rendering. Today did not, and
		 * relied on the WordPress menu capability alone. That is usually
		 * enough, but it made this the one screen a direct call could reach.
		 */
		if ( ! current_user_can( 'estat_manage_listings' ) ) {
			wp_die( esc_html__( 'You do not have permission to see the office dashboard.', 'estat-os' ) );
		}

		$user     = wp_get_current_user();
		$listings = (array) wp_count_posts( PostTypes::LISTING );
		$live     = isset( $listings['publish'] ) ? (int) $listings['publish'] : 0;
		$drafts   = isset( $listings['draft'] ) ? (int) $listings['draft'] : 0;
		$leads    = Leads::counts();
		$visits   = Visits::counts();
		$team     = (int) wp_count_posts( PostTypes::AGENT )->publish;
		$health   = self::health();
		$needs    = self::listings_needing_information();

		?>
		<p class="estat-greeting">
			<?php
			printf(
				/* translators: %s: user's first name */
				esc_html__( 'Good day, %s.', 'estat-os' ),
				esc_html( self::greeting_name( $user ) )
			);
			?>
			<span class="estat-version"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Version %s', 'estat-os' ), ESTAT_VERSION ) ); ?></span>
		</p>

		<?php self::alerts( $leads, $visits, $needs ); ?>

		<?php self::worklist(); ?>

		<?php
		// An office still finding its feet gets a short list of first jobs.
		// It removes itself item by item as each one is done.
		SetupScreen::next_steps();
		?>

		<div class="estat-stats">
			<?php
			Partials::stat( __( 'Live on the website', 'estat-os' ), (string) $live, admin_url( 'admin.php?page=estat-listings&status=publish' ) );
			Partials::stat( __( 'Drafts', 'estat-os' ), (string) $drafts, admin_url( 'admin.php?page=estat-listings&status=draft' ) );
			Partials::stat( __( 'New enquiries', 'estat-os' ), (string) $leads['new'], admin_url( 'admin.php?page=estat-enquiries&status=new' ), $leads['new'] > 0 ? 'warn' : 'plain' );
			Partials::stat( __( 'Follow-ups due', 'estat-os' ), (string) $leads['due'], admin_url( 'admin.php?page=estat-enquiries&filter=followup' ), $leads['due'] > 0 ? 'warn' : 'plain' );
			Partials::stat( __( 'Visits today', 'estat-os' ), (string) $visits['today'], admin_url( 'admin.php?page=estat-visits' ) );
			Partials::stat( __( 'Upcoming visits', 'estat-os' ), (string) $visits['upcoming'], admin_url( 'admin.php?page=estat-visits' ) );
			Partials::stat( __( 'Team members', 'estat-os' ), (string) $team, admin_url( 'admin.php?page=estat-team' ) );
			Partials::stat( __( 'Listing readiness', 'estat-os' ), $health . '%', admin_url( 'admin.php?page=estat-listings' ), $health < 70 ? 'warn' : 'good' );
			?>
		</div>

		<div class="estat-columns">
			<div>
				<?php
				Partials::card_open( __( 'Quick actions', 'estat-os' ) );
				?>
				<div class="estat-quick-actions">
					<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-add-home' ) ); ?>"><?php esc_html_e( 'Add a Home', 'estat-os' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-enquiries' ) ); ?>"><?php esc_html_e( 'Open enquiries', 'estat-os' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-visits' ) ); ?>"><?php esc_html_e( 'Arrange a visit', 'estat-os' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-spreadsheet' ) ); ?>"><?php esc_html_e( 'Import a spreadsheet', 'estat-os' ); ?></a>
				</div>
				<?php
				Partials::card_close();

				Partials::card_open( __( 'Listings that need information', 'estat-os' ), __( 'A more complete listing gets more enquiries.', 'estat-os' ) );
				if ( ! $needs ) {
					echo '<p class="estat-good">' . esc_html__( 'Everything looks complete. Nice work.', 'estat-os' ) . '</p>';
				} else {
					echo '<ul class="estat-todo">';
					foreach ( $needs as $item ) {
						echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . (int) $item['id'] ) ) . '">' . esc_html( $item['title'] ) . '</a> <span class="estat-pill">' . esc_html( $item['score'] . '%' ) . '</span></li>';
					}
					echo '</ul>';
				}
				Partials::card_close();
				?>
			</div>
			<div>
				<?php
				Partials::card_open( __( 'Office health', 'estat-os' ) );
				self::health_checks();
				Partials::card_close();

				Partials::card_open( __( 'Recent activity', 'estat-os' ) );
				$recent = AuditLog::query( array( 'per_page' => 10 ) );
				if ( ! $recent['items'] ) {
					echo '<p class="description">' . esc_html__( 'Nothing has happened yet.', 'estat-os' ) . '</p>';
				} else {
					echo '<ul class="estat-activity">';
					foreach ( $recent['items'] as $entry ) {
						$who = (int) $entry['user_id'] > 0 ? get_userdata( (int) $entry['user_id'] ) : null;
						echo '<li><strong>' . esc_html( AuditLog::describe( (string) $entry['action'] ) ) . '</strong> ';
						echo '<span class="estat-muted">' . esc_html( self::ago( (string) $entry['created_at'] ) );
						if ( $who ) {
							echo ' · ' . esc_html( $who->display_name );
						}
						echo '</span></li>';
					}
					echo '</ul>';
				}
				Partials::card_close();
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Plain-language alerts at the top of the screen.
	 *
	 * @param array<string,int>          $leads  Lead counts.
	 * @param array<string,int>          $visits Visit counts.
	 * @param array<int,array<string,mixed>> $needs  Listings needing information.
	 * @return void
	 */
	/**
	 * The list of jobs waiting to be done.
	 *
	 * This is the part of the dashboard that answers "what should I do now?",
	 * which a wall of numbers never does on its own.
	 *
	 * @return void
	 */
	private static function worklist(): void {
		$tasks = Worklist::tasks();

		Partials::card_open(
			__( 'What to do next', 'estat-os' ),
			__( 'The most urgent things first. Tick them off from the top.', 'estat-os' )
		);

		if ( ! $tasks ) {
			?>
			<div class="estat-worklist-clear">
				<span class="estat-worklist-clear-icon"><?php echo Icon::render( 'check', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<p class="estat-worklist-clear-title"><?php esc_html_e( 'Nothing needs you right now.', 'estat-os' ); ?></p>
				<p class="description"><?php esc_html_e( 'No overdue follow-ups, no visits today, no unanswered enquiries. A good moment to add a property.', 'estat-os' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-add-home' ) ); ?>"><?php esc_html_e( 'Add a Home', 'estat-os' ); ?></a>
			</div>
			<?php
			Partials::card_close();
			return;
		}
		?>
		<ul class="estat-worklist">
			<?php foreach ( $tasks as $task ) : ?>
				<li class="estat-worklist-item is-<?php echo esc_attr( (string) $task['tone'] ); ?>">
					<span class="estat-worklist-icon"><?php echo Icon::render( (string) $task['icon'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="estat-worklist-text">
						<span class="estat-worklist-title"><?php echo esc_html( (string) $task['title'] ); ?></span>
						<?php if ( '' !== (string) $task['detail'] ) : ?>
							<span class="estat-worklist-detail"><?php echo esc_html( (string) $task['detail'] ); ?></span>
						<?php endif; ?>
					</span>
					<a class="button button-small estat-worklist-action" href="<?php echo esc_url( (string) $task['url'] ); ?>">
						<?php echo esc_html( (string) $task['action'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		Partials::card_close();
	}

	private static function alerts( array $leads, array $visits, array $needs ): void {
		$messages = array();
		if ( $leads['new'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of enquiries */
				_n( '%d new enquiry needs a response.', '%d new enquiries need a response.', $leads['new'], 'estat-os' ),
				$leads['new']
			);
		}
		if ( $leads['due'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of follow-ups */
				_n( '%d follow-up is due.', '%d follow-ups are due.', $leads['due'], 'estat-os' ),
				$leads['due']
			);
		}
		if ( $visits['today'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of visits */
				_n( '%d visit is scheduled today.', '%d visits are scheduled today.', $visits['today'], 'estat-os' ),
				$visits['today']
			);
		}
		if ( $needs ) {
			$messages[] = sprintf(
				/* translators: %d: number of listings */
				_n( '%d listing is missing important information.', '%d listings are missing important information.', count( $needs ), 'estat-os' ),
				count( $needs )
			);
		}
		if ( ! $messages ) {
			echo '<div class="estat-alerts estat-alerts-clear"><p>' . esc_html__( 'Nothing urgent today. A good time to add a new listing.', 'estat-os' ) . '</p></div>';
			return;
		}
		echo '<div class="estat-alerts"><ul>';
		foreach ( $messages as $message ) {
			echo '<li>' . esc_html( $message ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Average readiness across published listings.
	 *
	 * @return int
	 */
	public static function health(): int {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return 0;
		}
		$table = Schema::table( 'estat_index' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$avg = $wpdb->get_var( "SELECT AVG(completeness) FROM {$table} WHERE post_status = 'publish'" );
		return null === $avg ? 0 : (int) round( (float) $avg );
	}

	/**
	 * Listings below the readiness threshold.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function listings_needing_information(): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array();
		}
		$table = Schema::table( 'estat_index' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT listing_id, title, completeness FROM {$table} WHERE completeness < 70 ORDER BY completeness ASC LIMIT 8", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'    => (int) $row['listing_id'],
				'title' => (string) $row['title'],
				'score' => (int) $row['completeness'],
			);
		}
		return $out;
	}

	/**
	 * A short list of health checks in plain language.
	 *
	 * @return void
	 */
	private static function health_checks(): void {
		$checks = array();

		$checks[] = array(
			'ok'   => '' !== (string) Settings::get( 'office_name', '' ),
			'good' => __( 'Your office details are filled in.', 'estat-os' ),
			'bad'  => __( 'Add your office name and phone number in Office Settings.', 'estat-os' ),
			'url'  => admin_url( 'admin.php?page=estat-settings' ),
		);

		$index    = SearchIndex::status();
		$checks[] = array(
			'ok'   => $index['in_sync'],
			'good' => __( 'Property search is up to date.', 'estat-os' ),
			'bad'  => __( 'Property search needs refreshing. You can do that in Office Settings.', 'estat-os' ),
			'url'  => admin_url( 'admin.php?page=estat-settings#maintenance' ),
		);

		$checks[] = array(
			'ok'   => (bool) wp_next_scheduled( \EstatOS\Cron\Scheduler::DAILY ),
			'good' => __( 'Daily housekeeping is running.', 'estat-os' ),
			'bad'  => __( 'Daily housekeeping is not scheduled. Deactivate and activate the plugin to fix this.', 'estat-os' ),
			'url'  => '',
		);

		$checks[] = array(
			'ok'   => Schema::healthy(),
			'good' => __( 'Your office database is healthy.', 'estat-os' ),
			'bad'  => __( 'Some office tables are missing. Deactivate and activate the plugin to rebuild them.', 'estat-os' ),
			'url'  => '',
		);

		echo '<ul class="estat-health">';
		foreach ( $checks as $check ) {
			$class = $check['ok'] ? 'is-good' : 'is-warn';
			echo '<li class="' . esc_attr( $class ) . '">';
			echo esc_html( $check['ok'] ? $check['good'] : $check['bad'] );
			if ( ! $check['ok'] && '' !== $check['url'] ) {
				echo ' <a href="' . esc_url( $check['url'] ) . '">' . esc_html__( 'Fix this', 'estat-os' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Human "time ago" text.
	 *
	 * @param string $gmt_datetime GMT datetime.
	 * @return string
	 */
	private static function ago( string $gmt_datetime ): string {
		$time = strtotime( $gmt_datetime . ' UTC' );
		if ( ! $time ) {
			return '';
		}
		/* translators: %s: human readable time difference */
		return sprintf( __( '%s ago', 'estat-os' ), human_time_diff( $time, time() ) );
	}
}
