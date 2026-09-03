<?php
/** Provider contract for asynchronous lead-workflow notifications. @package RealEstatePlatform */
declare(strict_types=1);
namespace Mayfair\RealEstatePlatform\Leads;

interface LeadNotificationProviderInterface {
	/** @param array<string,mixed> $event */
	public function send( array $event ): bool;
}
