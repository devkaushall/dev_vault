<?php
/**
 * SEO structured data.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Frontend;

use EstatOS\Data\Agents;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Conservative schema: only what the plugin actually knows, only when enabled,
 * and never a price when the office has chosen "price on request".
 */
final class Schema {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 20 );
	}

	/**
	 * Print the JSON-LD block.
	 *
	 * @return void
	 */
	public static function output(): void {
		if ( ! Settings::get( 'enable_schema' ) ) {
			return;
		}
		$data = null;

		if ( is_singular( PostTypes::LISTING ) ) {
			$data = self::listing( (int) get_the_ID() );
		} elseif ( is_singular( PostTypes::AGENT ) ) {
			$data = self::agent( (int) get_the_ID() );
		} elseif ( is_front_page() ) {
			$data = self::organization();
		}

		if ( ! $data ) {
			return;
		}

		/**
		 * Filter the structured data before it is printed. Return an empty
		 * array from another SEO plugin to suppress it entirely.
		 *
		 * @param array<string,mixed> $data Structured data.
		 */
		$data = (array) apply_filters( 'estat_schema_data', $data );
		if ( ! $data ) {
			return;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Listing schema.
	 *
	 * @param int $listing_id Listing ID.
	 * @return array<string,mixed>|null
	 */
	private static function listing( int $listing_id ): ?array {
		$listing = Listings::to_array( $listing_id, false );
		if ( ! $listing ) {
			return null;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'RealEstateListing',
			'name'     => $listing['title'],
			'url'      => $listing['url'],
		);
		if ( '' !== (string) $listing['cover'] ) {
			$schema['image'] = $listing['cover'];
		}
		$excerpt = get_the_excerpt( $listing_id );
		if ( '' !== $excerpt ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}
		if ( null !== $listing['price'] && (float) $listing['price'] > 0 ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (float) $listing['price'],
				'priceCurrency' => (string) Settings::get( 'currency', 'INR' ),
				'availability'  => 'available' === $listing['availability'] ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
			);
		}
		if ( (float) $listing['area_sqft'] > 0 ) {
			$schema['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => (float) $listing['area_sqft'],
				'unitCode' => 'FTK',
			);
		}
		if ( (int) $listing['bedrooms'] > 0 ) {
			$schema['numberOfRooms'] = (int) $listing['bedrooms'];
		}
		if ( ! empty( $listing['locality'] ) ) {
			$schema['address'] = array(
				'@type'           => 'PostalAddress',
				'addressLocality' => (string) $listing['locality'][0],
			);
		}
		return $schema;
	}

	/**
	 * Agent schema.
	 *
	 * @param int $agent_id Agent ID.
	 * @return array<string,mixed>|null
	 */
	private static function agent( int $agent_id ): ?array {
		$agent = Agents::to_array( $agent_id );
		if ( ! $agent ) {
			return null;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'RealEstateAgent',
			'name'     => $agent['name'],
			'url'      => $agent['url'],
		);
		if ( '' !== (string) $agent['phone'] ) {
			$schema['telephone'] = $agent['phone'];
		}
		if ( '' !== (string) $agent['photo'] ) {
			$schema['image'] = $agent['photo'];
		}
		return $schema;
	}

	/**
	 * Organization schema for the home page.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function organization(): ?array {
		$name = (string) Settings::get( 'office_name', '' );
		if ( '' === $name ) {
			return null;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'RealEstateAgent',
			'name'     => $name,
			'url'      => home_url( '/' ),
		);
		$phone = (string) Settings::get( 'phone', '' );
		if ( '' !== $phone ) {
			$schema['telephone'] = $phone;
		}
		$address = (string) Settings::get( 'address', '' );
		if ( '' !== $address ) {
			$schema['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => $address );
		}
		return $schema;
	}
}
