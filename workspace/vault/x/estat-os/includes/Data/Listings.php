<?php
/**
 * Listing repository: create, update, read, completeness and cover sync.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Data;

use EstatOS\Audit\AuditLog;
use EstatOS\Search\SearchIndex;
use EstatOS\Settings\Settings;
use EstatOS\Support\Format;
use EstatOS\Support\Sanitize;
use EstatOS\Support\Vocabulary;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * All listing writes go through save() so validation, derived values, indexing
 * and audit logging can never be skipped by a different entry point.
 */
final class Listings {

	/**
	 * Meta key holding the office verification state.
	 *
	 * Writing it requires the `estat_verify_listings` capability, so that an
	 * agent cannot award their own listing the office's badge of trust.
	 *
	 * @var string
	 */
	/**
	 * The most any money field may hold.
	 *
	 * One hundred billion in the site's currency: far beyond any single
	 * property, and well inside what a float stores exactly.
	 */
	public const MAX_MONEY = 100000000000.0;

	/**
	 * Fields that count things, and the most each may hold.
	 *
	 * @return array<string,int>
	 */
	public static function counted_fields(): array {
		return array(
			'bedrooms'   => 50,
			'bathrooms'  => 50,
			'balconies'  => 30,
			'parking'    => 50,
			'floor'      => 200,
			'total_floors' => 200,
		);
	}

	public const VERIFICATION_KEY = '_estat_verification';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post_' . PostTypes::LISTING, array( __CLASS__, 'on_save_post' ), 20, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ), 10, 1 );
	}

	/**
	 * Create or update a listing from a raw input array.
	 *
	 * @param array<string,mixed> $input   Raw input (post fields plus meta keys without prefix or with).
	 * @param int                 $post_id Existing listing ID, 0 to create.
	 * @return int|WP_Error Listing ID or error.
	 */
	public static function save( array $input, int $post_id = 0 ) {
		$errors = self::validate( $input, $post_id );
		if ( ! empty( $errors ) ) {
			$error = new WP_Error();
			foreach ( $errors as $field => $message ) {
				$error->add( 'estat_invalid_' . $field, $message, array( 'field' => $field ) );
			}
			return $error;
		}

		$title = Sanitize::title( $input['title'] ?? '' );
		$status = Sanitize::choice( $input['status'] ?? 'draft', array( 'draft', 'publish', 'pending', 'private' ), 'draft' );
		if ( 'publish' === $status && ! current_user_can( 'estat_publish_listings' ) ) {
			$status = 'draft';
		}

		$postarr = array(
			'post_type'    => PostTypes::LISTING,
			'post_title'   => $title,
			'post_content' => Sanitize::html( $input['description'] ?? '' ),
			'post_excerpt' => Sanitize::textarea( $input['summary'] ?? '' ),
			'post_status'  => $status,
		);

		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );
			if ( ! $existing || PostTypes::LISTING !== $existing->post_type ) {
				return new WP_Error( 'estat_not_found', __( 'That listing could not be found.', 'estat-os' ) );
			}
			$postarr['ID'] = $post_id;

			// Snapshot what is there now, before any of it is overwritten.
			Undo::capture( $post_id );

			if ( ! isset( $input['description'] ) ) {
				unset( $postarr['post_content'] );
			}
			if ( ! isset( $input['summary'] ) ) {
				unset( $postarr['post_excerpt'] );
			}
			$result = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$listing_id = (int) $result;

		self::save_meta( $listing_id, $input );
		self::save_terms( $listing_id, $input );

		if ( isset( $input['cover_id'] ) ) {
			self::set_cover( $listing_id, Sanitize::int( $input['cover_id'] ) );
		}

		if ( Settings::get( 'practice_mode' ) && 0 === $post_id ) {
			update_post_meta( $listing_id, '_estat_practice', 1 );
		}

		self::refresh_derived( $listing_id );

		AuditLog::record(
			$post_id > 0 ? 'listing.updated' : 'listing.created',
			'listing',
			$listing_id,
			array( 'status' => $status )
		);
		if ( 'publish' === $status ) {
			AuditLog::record( 'listing.published', 'listing', $listing_id, array() );
		}

		return $listing_id;
	}

	/**
	 * Validate raw listing input.
	 *
	 * @param array<string,mixed> $input   Raw input.
	 * @param int                 $post_id Existing ID.
	 * @return array<string,string> Field => friendly message.
	 */
	public static function validate( array $input, int $post_id = 0 ): array {
		$errors = array();

		$title = trim( Sanitize::text( $input['title'] ?? '' ) );
		if ( 0 === $post_id && '' === $title ) {
			$errors['title'] = __( 'Please give this property a short headline, for example "3 BHK apartment near the metro".', 'estat-os' );
		} elseif ( '' !== $title && mb_strlen( $title ) < 5 ) {
			$errors['title'] = __( 'The headline is very short. Add a few more words so buyers understand it.', 'estat-os' );
		}

		$offer = Sanitize::text( $input['offer'] ?? $input['_estat_offer'] ?? '' );
		if ( '' !== $offer && ! in_array( $offer, Vocabulary::keys( 'offers' ), true ) ) {
			$errors['offer'] = __( 'Please choose sale, rent or lease.', 'estat-os' );
		}

		$price_type = Sanitize::text( $input['price_type'] ?? $input['_estat_price_type'] ?? '' );
		$price      = isset( $input['price'] ) ? Sanitize::float( $input['price'] ) : ( isset( $input['_estat_price'] ) ? Sanitize::float( $input['_estat_price'] ) : null );
		$rent       = isset( $input['rent'] ) ? Sanitize::float( $input['rent'] ) : ( isset( $input['_estat_rent'] ) ? Sanitize::float( $input['_estat_rent'] ) : null );

		$publishing = 'publish' === ( $input['status'] ?? '' );
		if ( $publishing && 'on_request' !== $price_type ) {
			if ( 'rent' === $offer || 'lease' === $offer ) {
				if ( ! $rent && ! $price ) {
					$errors['rent'] = __( 'Add the monthly rent, or choose "Price on request".', 'estat-os' );
				}
			} elseif ( ! $price ) {
				$errors['price'] = __( 'Add a price, or choose "Price on request".', 'estat-os' );
			}
		}

		/*
		 * Sanitize::float() floors at zero, so by this point a negative has
		 * already become 0 and a check on $price could never fire. Look at
		 * what was actually typed instead: turning "-500000" into "0" without
		 * a word is worse than refusing it.
		 */
		foreach ( array( 'price', 'rent' ) as $money_field ) {
			$typed = $input[ $money_field ] ?? $input[ '_estat_' . $money_field ] ?? null;

			if ( null === $typed || '' === $typed ) {
				continue;
			}

			if ( is_scalar( $typed ) && (float) str_replace( array( ',', ' ' ), '', (string) $typed ) < 0 ) {
				$errors[ $money_field ] = __( 'That cannot be a negative number.', 'estat-os' );
			}
		}

		/*
		 * Upper limits. These are not arbitrary tidiness: without them a
		 * mistyped price sorts above every real listing and breaks the price
		 * filter for everybody, and "999 bedrooms" reaches the public site.
		 * The ceilings are set far above any genuine property so a real,
		 * unusual entry is never blocked.
		 */
		foreach ( array( 'price' => $price, 'rent' => $rent ) as $money_field => $money ) {
			if ( null !== $money && $money > self::MAX_MONEY ) {
				$errors[ $money_field ] = __( 'That number looks too large. Please check it.', 'estat-os' );
			}
		}


		foreach ( self::counted_fields() as $count_field => $ceiling ) {
			$raw = $input[ $count_field ] ?? $input[ '_estat_' . $count_field ] ?? null;

			if ( null === $raw || '' === $raw ) {
				continue;
			}

			$value = Sanitize::int( $raw, 0 );

			if ( $value > $ceiling ) {
				$errors[ $count_field ] = sprintf(
					/* translators: 1: what is being counted, 2: the highest allowed number. */
					__( 'That is more %1$s than we can record. The most is %2$d.', 'estat-os' ),
					$count_field,
					$ceiling
				);
			}
		}

		$area = isset( $input['area'] ) ? Sanitize::float( $input['area'] ) : null;
		if ( null !== $area && $area > 100000000 ) {
			$errors['area'] = __( 'That size looks too large. Please check the number.', 'estat-os' );
		}

		$expiry = Sanitize::date( $input['expiry_date'] ?? $input['_estat_expiry_date'] ?? '' );
		if ( '' !== $expiry && strtotime( $expiry ) < strtotime( gmdate( 'Y-m-d' ) ) ) {
			$errors['expiry_date'] = __( 'The removal date is in the past. Please pick a future date.', 'estat-os' );
		}

		/**
		 * Filter listing validation errors.
		 *
		 * @param array<string,string> $errors Errors.
		 * @param array<string,mixed>  $input  Raw input.
		 */
		return (array) apply_filters( 'estat_validate_listing', $errors, $input );
	}

	/**
	 * Persist meta values present in the input.
	 *
	 * @param int                 $listing_id Listing ID.
	 * @param array<string,mixed> $input      Raw input.
	 * @return void
	 */
	private static function save_meta( int $listing_id, array $input ): void {
		$may_verify = current_user_can( 'estat_verify_listings' );
		$schema     = Meta::listing_schema();
		foreach ( $schema as $key => $field ) {
			if ( ! empty( $field['computed'] ) ) {
				continue;
			}
			if ( self::VERIFICATION_KEY === $key && ! $may_verify ) {
				// Only authorised staff may set the verification state. Anyone
				// else leaves whatever is already stored untouched.
				continue;
			}
			$short = substr( $key, strlen( '_estat_' ) );
			if ( array_key_exists( $key, $input ) ) {
				$raw = $input[ $key ];
			} elseif ( array_key_exists( $short, $input ) ) {
				$raw = $input[ $short ];
			} else {
				continue;
			}
			$value = Meta::sanitize_value( $raw, $field );
			if ( '' === $value || array() === $value ) {
				delete_post_meta( $listing_id, $key );
				continue;
			}
			update_post_meta( $listing_id, $key, $value );
		}

		if ( array_key_exists( 'highlights', $input ) || array_key_exists( Highlights::META_KEY, $input ) ) {
			$ticked = $input['highlights'] ?? $input[ Highlights::META_KEY ];
			Highlights::save( $listing_id, $ticked );
		}

	}

	/**
	 * Persist taxonomy selections.
	 *
	 * @param int                 $listing_id Listing ID.
	 * @param array<string,mixed> $input      Raw input.
	 * @return void
	 */
	private static function save_terms( int $listing_id, array $input ): void {
		$map = array(
			'locality'  => Taxonomies::LOCALITY,
			'features'  => Taxonomies::FEATURE,
			'amenities' => Taxonomies::AMENITY,
		);
		foreach ( $map as $input_key => $taxonomy ) {
			if ( ! array_key_exists( $input_key, $input ) ) {
				continue;
			}
			$value = $input[ $input_key ];
			if ( is_string( $value ) ) {
				$value = preg_split( '/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY );
			}
			if ( ! is_array( $value ) ) {
				$value = array();
			}
			$terms = array();
			foreach ( $value as $item ) {
				if ( is_numeric( $item ) ) {
					$terms[] = (int) $item;
					continue;
				}
				$name = Sanitize::text( $item );
				if ( '' === $name ) {
					continue;
				}
				$existing = term_exists( $name, $taxonomy );
				if ( is_array( $existing ) ) {
					$terms[] = (int) $existing['term_id'];
					continue;
				}
				$created = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $created ) ) {
					$terms[] = (int) $created['term_id'];
				}
			}
			wp_set_object_terms( $listing_id, $terms, $taxonomy, false );
		}
	}

	/**
	 * Set the cover image and keep the WordPress featured image in sync.
	 *
	 * @param int $listing_id   Listing ID.
	 * @param int $attachment_id Attachment ID (0 clears).
	 * @return void
	 */
	public static function set_cover( int $listing_id, int $attachment_id ): void {
		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			set_post_thumbnail( $listing_id, $attachment_id );
		} else {
			delete_post_thumbnail( $listing_id );
		}
	}

	/**
	 * Recalculate derived values: sqft, completeness and the search index row.
	 *
	 * @param int $listing_id Listing ID.
	 * @return void
	 */
	public static function refresh_derived( int $listing_id ): void {
		$area = (float) get_post_meta( $listing_id, '_estat_area', true );
		$unit = (string) get_post_meta( $listing_id, '_estat_area_unit', true );
		if ( $area > 0 ) {
			update_post_meta( $listing_id, '_estat_area_sqft', Format::to_sqft( $area, $unit ?: 'sqft' ) );
		} else {
			delete_post_meta( $listing_id, '_estat_area_sqft' );
		}

		$score = self::completeness( $listing_id );
		update_post_meta( $listing_id, '_estat_completeness', $score['score'] );

		SearchIndex::index_listing( $listing_id );
		wp_cache_delete( 'estat_stats', 'estat' );
	}

	/**
	 * Calculate how ready a listing is, with a list of what is missing.
	 *
	 * @param int $listing_id Listing ID.
	 * @return array{score:int,missing:array<int,array{field:string,label:string}>}
	 */
	public static function completeness( int $listing_id ): array {
		$post = get_post( $listing_id );
		if ( ! $post ) {
			return array( 'score' => 0, 'missing' => array() );
		}

		$checks = array(
			array(
				'field'  => 'title',
				'label'  => __( 'A clear headline', 'estat-os' ),
				'weight' => 10,
				'ok'     => mb_strlen( trim( $post->post_title ) ) >= 5,
			),
			array(
				'field'  => 'description',
				'label'  => __( 'A description of the property', 'estat-os' ),
				'weight' => 10,
				'ok'     => mb_strlen( wp_strip_all_tags( $post->post_content ) ) >= 80,
			),
			array(
				'field'  => 'cover_id',
				'label'  => __( 'A main photo', 'estat-os' ),
				'weight' => 15,
				'ok'     => (bool) get_post_thumbnail_id( $listing_id ),
			),
			array(
				'field'  => 'gallery',
				'label'  => __( 'At least three photos', 'estat-os' ),
				'weight' => 10,
				'ok'     => count( (array) get_post_meta( $listing_id, '_estat_gallery', true ) ) >= 3,
			),
			array(
				'field'  => 'price',
				'label'  => __( 'A price (or price on request)', 'estat-os' ),
				'weight' => 15,
				'ok'     => 'on_request' === get_post_meta( $listing_id, '_estat_price_type', true )
					|| (float) get_post_meta( $listing_id, '_estat_price', true ) > 0
					|| (float) get_post_meta( $listing_id, '_estat_rent', true ) > 0,
			),
			array(
				'field'  => 'area',
				'label'  => __( 'The size of the property', 'estat-os' ),
				'weight' => 10,
				'ok'     => (float) get_post_meta( $listing_id, '_estat_area', true ) > 0,
			),
			array(
				'field'  => 'locality',
				'label'  => __( 'The locality', 'estat-os' ),
				'weight' => 15,
				'ok'     => ! empty( wp_get_object_terms( $listing_id, Taxonomies::LOCALITY, array( 'fields' => 'ids' ) ) ),
			),
			array(
				'field'  => 'agent_id',
				'label'  => __( 'A team member in charge', 'estat-os' ),
				'weight' => 5,
				'ok'     => (int) get_post_meta( $listing_id, '_estat_agent_id', true ) > 0,
			),
			array(
				'field'  => 'bedrooms',
				'label'  => __( 'Number of bedrooms', 'estat-os' ),
				'weight' => 5,
				'ok'     => (int) get_post_meta( $listing_id, '_estat_bedrooms', true ) > 0
					|| in_array( (string) get_post_meta( $listing_id, '_estat_property_type', true ), array( 'plot', 'farm_land', 'warehouse', 'industrial', 'office', 'commercial_shop' ), true ),
			),
			array(
				'field'  => 'latitude',
				'label'  => __( 'A location on the map', 'estat-os' ),
				'weight' => 5,
				'ok'     => '' !== (string) get_post_meta( $listing_id, '_estat_latitude', true ),
			),
		);

		$total   = 0;
		$scored  = 0;
		$missing = array();
		foreach ( $checks as $check ) {
			$total += (int) $check['weight'];
			if ( $check['ok'] ) {
				$scored += (int) $check['weight'];
			} else {
				$missing[] = array( 'field' => (string) $check['field'], 'label' => (string) $check['label'] );
			}
		}

		$score = $total > 0 ? (int) round( ( $scored / $total ) * 100 ) : 0;
		return array( 'score' => $score, 'missing' => $missing );
	}

	/**
	 * Keep derived data fresh when a listing is saved through any route.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public static function on_save_post( int $post_id, WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}
		self::refresh_derived( $post_id );
	}

	/**
	 * Clean up the index when a listing is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_delete( int $post_id ): void {
		if ( PostTypes::LISTING !== get_post_type( $post_id ) ) {
			return;
		}
		SearchIndex::remove( $post_id );
		AuditLog::record( 'listing.deleted', 'listing', $post_id, array() );
	}

	/**
	 * How many whole days a listing has been published.
	 *
	 * @param int $listing_id Listing ID.
	 * @return int Zero when it has never been published.
	 */
	public static function days_listed( int $listing_id ): int {
		$post = get_post( $listing_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return 0;
		}

		// post_date_gmt is empty on some rows written directly by an import,
		// so fall back rather than treating 1970 as the listing date.
		$published = (string) ( $post->post_date_gmt ?? '' );

		if ( '' === $published || '0000-00-00 00:00:00' === $published ) {
			$published = (string) ( $post->post_date ?? '' );
		}

		if ( '' === $published ) {
			return 0;
		}

		$stamp = strtotime( $published );

		if ( ! $stamp ) {
			return 0;
		}

		$days = (int) floor( ( time() - $stamp ) / DAY_IN_SECONDS );

		// A clock skew or a future-dated post must not read as negative.
		return max( 0, $days );
	}

	/**
	 * Build a safe public representation of a listing.
	 *
	 * @param int  $listing_id Listing ID.
	 * @param bool $private    Include office-only information.
	 * @return array<string,mixed>
	 */
	public static function to_array( int $listing_id, bool $private = false ): array {
		$post = get_post( $listing_id );
		if ( ! $post || PostTypes::LISTING !== $post->post_type ) {
			return array();
		}

		$price_type = (string) get_post_meta( $listing_id, '_estat_price_type', true );
		$price      = (float) get_post_meta( $listing_id, '_estat_price', true );
		$rent       = (float) get_post_meta( $listing_id, '_estat_rent', true );
		$on_request = 'on_request' === $price_type;

		$data = array(
			'id'            => $listing_id,
			'title'         => $post->post_title,
			'url'           => get_permalink( $listing_id ),
			'status'        => $post->post_status,
			'offer'         => (string) get_post_meta( $listing_id, '_estat_offer', true ),
			'property_type' => (string) get_post_meta( $listing_id, '_estat_property_type', true ),
			'price'         => $on_request ? null : $price,
			'rent'          => $on_request ? null : $rent,
			'price_type'    => $price_type,
			'price_display' => $on_request
				? __( 'Price on request', 'estat-os' )
				: Format::money( $rent > 0 && in_array( get_post_meta( $listing_id, '_estat_offer', true ), array( 'rent', 'lease' ), true ) ? $rent : $price ),
			'area'          => (float) get_post_meta( $listing_id, '_estat_area', true ),
			'area_unit'     => (string) get_post_meta( $listing_id, '_estat_area_unit', true ),
			'area_sqft'     => (float) get_post_meta( $listing_id, '_estat_area_sqft', true ),
			'bedrooms'      => (int) get_post_meta( $listing_id, '_estat_bedrooms', true ),
			'bathrooms'     => (int) get_post_meta( $listing_id, '_estat_bathrooms', true ),
			'parking'       => (int) get_post_meta( $listing_id, '_estat_parking', true ),
			'floor'         => (int) get_post_meta( $listing_id, '_estat_floor', true ),
			'furnishing'    => (string) get_post_meta( $listing_id, '_estat_furnishing', true ),
			'availability'  => (string) get_post_meta( $listing_id, '_estat_availability', true ),
			'construction'  => (string) get_post_meta( $listing_id, '_estat_construction', true ),
			'verification'  => (string) get_post_meta( $listing_id, '_estat_verification', true ),
			'featured'      => (bool) get_post_meta( $listing_id, '_estat_featured', true ),
			'investment'    => (bool) get_post_meta( $listing_id, '_estat_investment', true ),
			'practice'      => (bool) get_post_meta( $listing_id, '_estat_practice', true ),
			'locality'      => wp_get_object_terms( $listing_id, Taxonomies::LOCALITY, array( 'fields' => 'names' ) ),
			'amenities'     => wp_get_object_terms( $listing_id, Taxonomies::AMENITY, array( 'fields' => 'names' ) ),
			'cover'         => get_the_post_thumbnail_url( $listing_id, 'large' ) ?: '',

			/*
			 * The cover's real pixel size, so a template can write width and
			 * height onto the tag. Without them the browser does not know how
			 * tall the image will be, lays the page out without it, and then
			 * shoves everything down when it arrives - the jump Google counts
			 * against the site as layout shift.
			 *
			 * Zero when there is no cover, which templates read as "do not
			 * write dimensions".
			 */
			'cover_width'   => 0,
			'cover_height'  => 0,

			/*
			 * How long this has been on the website. A buyer reads a new
			 * listing as "go and see it this weekend" and an old one as
			 * "there may be room to negotiate" - both useful, and neither
			 * knowable today.
			 *
			 * Counted from when it was published, not when the office first
			 * created the draft, because a property sitting in drafts for a
			 * month has not been on the market for a month.
			 */
			'days_listed'   => self::days_listed( $listing_id ),
			'project_id'    => (int) get_post_meta( $listing_id, '_estat_project_id', true ),
			'agent_id'      => (int) get_post_meta( $listing_id, '_estat_agent_id', true ),
			'latitude'      => (string) get_post_meta( $listing_id, '_estat_latitude', true ),
			'longitude'     => (string) get_post_meta( $listing_id, '_estat_longitude', true ),
			'highlights'    => Highlights::get( $listing_id ),
			'chips'         => Highlights::for_card( $listing_id ),

			/*
			 * These were collected by the editor, validated and stored, and
			 * then never handed to anybody: absent from this array in both
			 * modes, absent from REST, and unread by the templates. The
			 * office typed a deposit, a video link and a possession date and
			 * no visitor could ever see them.
			 *
			 * They are public because a buyer legitimately wants all of them.
			 * The genuinely internal ones stay below, under $private.
			 */
			'deposit'       => (float) get_post_meta( $listing_id, '_estat_deposit', true ),
			'maintenance'   => (float) get_post_meta( $listing_id, '_estat_maintenance', true ),
			'balconies'     => (int) get_post_meta( $listing_id, '_estat_balconies', true ),
			'total_floors'  => (int) get_post_meta( $listing_id, '_estat_total_floors', true ),
			'age'           => (int) get_post_meta( $listing_id, '_estat_age', true ),
			'facing'        => (string) get_post_meta( $listing_id, '_estat_facing', true ),
			'area_type'     => (string) get_post_meta( $listing_id, '_estat_area_type', true ),
			'video_url'     => (string) get_post_meta( $listing_id, '_estat_video_url', true ),
			'tour_url'      => (string) get_post_meta( $listing_id, '_estat_tour_url', true ),
			'developer'     => (string) get_post_meta( $listing_id, '_estat_developer', true ),
			'possession_date' => (string) get_post_meta( $listing_id, '_estat_possession_date', true ),
			'floor_plans'   => array_values( array_filter( array_map( 'absint', (array) get_post_meta( $listing_id, '_estat_floor_plans', true ) ) ) ),
			'brochures'     => array_values( array_filter( array_map( 'absint', (array) get_post_meta( $listing_id, '_estat_brochures', true ) ) ) ),
		);

		/*
		 * Ask for the real dimensions once. get_the_post_thumbnail_url()
		 * above gives only a URL, and a second lookup here is cheap because
		 * the attachment is already in the object cache by this point.
		 */
		$cover_id = (int) get_post_thumbnail_id( $listing_id );

		if ( $cover_id > 0 ) {
			$measured = wp_get_attachment_image_src( $cover_id, 'large' );

			if ( is_array( $measured ) && ! empty( $measured[1] ) && ! empty( $measured[2] ) ) {
				$data['cover_width']  = (int) $measured[1];
				$data['cover_height'] = (int) $measured[2];
			}
		}

		/*
		 * A registration number is public information by law in most Indian
		 * states, but only when the office has chosen to display it.
		 */
		if ( Settings::get( 'show_regulatory' ) ) {
			$data['regulatory_id'] = (string) get_post_meta( $listing_id, '_estat_regulatory_id', true );
		}

		if ( $private ) {
			$data['internal_notes'] = (string) get_post_meta( $listing_id, '_estat_internal_notes', true );
			$data['external_id']    = (string) get_post_meta( $listing_id, '_estat_external_id', true );
			$data['address']        = (string) get_post_meta( $listing_id, '_estat_address', true );
			$data['completeness']   = (int) get_post_meta( $listing_id, '_estat_completeness', true );
			$data['expiry_date']    = (string) get_post_meta( $listing_id, '_estat_expiry_date', true );
		}

		/**
		 * Filter the listing representation.
		 *
		 * @param array<string,mixed> $data       Listing data.
		 * @param int                 $listing_id Listing ID.
		 * @param bool                $private    Whether private fields are included.
		 */
		return (array) apply_filters( 'estat_listing_data', $data, $listing_id, $private );
	}

	/**
	 * Find a listing by its office reference number.
	 *
	 * @param string $external_id Reference number.
	 * @return int Listing ID or 0.
	 */
	public static function find_by_external_id( string $external_id ): int {
		$external_id = Sanitize::text( $external_id );
		if ( '' === $external_id ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'        => PostTypes::LISTING,
				'post_status'      => array( 'any' ),
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_estat_external_id',
				'meta_value'       => $external_id,
				'suppress_filters' => false,
				'no_found_rows'    => true,
			)
		);
		return $found ? (int) $found[0] : 0;
	}
}
