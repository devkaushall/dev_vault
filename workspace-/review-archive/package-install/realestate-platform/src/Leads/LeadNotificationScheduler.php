<?php
/** Cron adapter for the lead notification outbox. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Leads;

final class LeadNotificationScheduler {
	public const HOOK = 'realestate_platform_lead_notifications';

	public function __construct( private LeadNotificationService $notifications ) {}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	public function run(): void {
		$this->notifications->dispatch( 25 );
	}
}
