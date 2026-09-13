<?php
/**
 * Listings: the list, and the advanced editor organised into three chapters.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Data\Listings;
use EstatOS\Data\Meta;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;
use EstatOS\Settings\Settings;
use EstatOS\Support\Format;
use EstatOS\Support\Vocabulary;
use EstatOS\Data\Picker;

defined( 'ABSPATH' ) || exit;

/**
 * The list is a plain table with mobile-friendly stacking; the editor uses
 * three chapters rather than a wall of tabs.
 */
final class ListingsScreen {

	/**
	 * Render either the list or the editor.
	 *
	 * @return void
	 */
	public static function render(): void {
		// The editor below checks per-record permission; the list itself was
		// checking nothing at all and relied on the menu capability.
		if ( ! current_user_can( 'estat_manage_listings' ) ) {
			wp_die( esc_html__( 'You do not have permission to see listings.', 'estat-os' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		if ( $edit_id > 0 ) {
			self::editor( $edit_id );
			return;
		}
		self::listing_table();
	}

	/**
	 * The listing table with simple filters.
	 *
	 * @return void
	 */
	private static function listing_table(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'any';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable

		$query = new \WP_Query(
			array(
				'post_type'      => PostTypes::LISTING,
				'post_status'    => in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'trash' ), true ) ? $status : array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'paged'          => $paged,
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		?>
		<?php
		Partials::toolbar(
			array(
				'page'         => 'estat-listings',
				'search'       => $search,
				'search_label' => __( 'Search by name or locality', 'estat-os' ),
				'count'        => (int) $query->found_posts,
				'action'       => __( 'Add a Home', 'estat-os' ),
				'action_url'   => admin_url( 'admin.php?page=estat-add-home' ),
				'filters'      => array(
					array(
						'name'    => 'status',
						'label'   => __( 'Filter by state', 'estat-os' ),
						'value'   => $status,
						'options' => array(
							'any'     => __( 'All listings', 'estat-os' ),
							'publish' => __( 'On the website', 'estat-os' ),
							'draft'   => __( 'Drafts', 'estat-os' ),
							'trash'   => __( 'In the bin', 'estat-os' ),
						),
					),
				),
			)
		);
		?>
		<?php

		if ( ! $query->have_posts() ) {
			// A search that found nothing is a very different problem from
			// having no properties at all, and needs a different way out.
			$searching = '' !== $search || 'any' !== $status;

			Partials::empty_state(
				$searching
					? array(
						'icon'    => 'search',
						'title'   => __( 'Nothing matched', 'estat-os' ),
						'message' => __( 'No property matches what you searched for. Try a shorter word, or clear the filters to see everything.', 'estat-os' ),
						'action'  => admin_url( 'admin.php?page=estat-listings' ),
						'label'   => __( 'Clear the filters', 'estat-os' ),
					)
					: array(
						'icon'       => 'home',
						'title'      => __( 'No properties yet', 'estat-os' ),
						'message'    => __( 'This is where every property in your office will live. Adding the first one takes about a minute.', 'estat-os' ),
						'reassuring' => true,
						'steps'      => array(
							__( 'Add a home with its headline, price and locality.', 'estat-os' ),
							__( 'Add a photo or two so it looks inviting.', 'estat-os' ),
							__( 'Publish it when you are happy with it.', 'estat-os' ),
						),
						'action'     => admin_url( 'admin.php?page=estat-add-home' ),
						'label'      => __( 'Add a Home', 'estat-os' ),
						'hint'       => __( 'Already have a spreadsheet of properties? You can import it instead.', 'estat-os' ),
					)
			);
			return;
		}

		$may_bulk = current_user_can( 'estat_delete_listings' );

		if ( $may_bulk ) {
			Partials::bulk_open( 'estat-listings' );
		}
		?>
		<table class="widefat estat-table">
			<caption class="screen-reader-text"><?php esc_html_e( 'Your listings', 'estat-os' ); ?></caption>
			<thead>
				<tr>
					<?php
					if ( $may_bulk ) {
						Partials::bulk_header();
					}
					?>
					<th scope="col"><?php esc_html_e( 'Property', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Where', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'State', 'estat-os' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Readiness', 'estat-os' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'estat-os' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$id       = (int) get_the_ID();
					$data     = Listings::to_array( $id, true );
					$locality = ! empty( $data['locality'] ) ? implode( ', ', (array) $data['locality'] ) : '—';
					$score    = (int) $data['completeness'];
					?>
					<tr>
						<?php
						if ( $may_bulk ) {
							Partials::bulk_cell( $id, (string) get_the_title() );
						}
						?>
						<th scope="row" data-label="<?php esc_attr_e( 'Property', 'estat-os' ); ?>">
							<a class="row-title" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . $id ) ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
							<div class="estat-muted"><?php echo esc_html( Vocabulary::label( 'property_types', (string) $data['property_type'] ) . ' · ' . Vocabulary::label( 'offers', (string) $data['offer'] ) ); ?></div>
						</th>
						<td data-label="<?php esc_attr_e( 'Price', 'estat-os' ); ?>"><?php echo esc_html( (string) $data['price_display'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Where', 'estat-os' ); ?>"><?php echo esc_html( $locality ); ?></td>
						<td data-label="<?php esc_attr_e( 'State', 'estat-os' ); ?>">
							<?php
							$row_status = (string) get_post_status();
							if ( 'publish' === $row_status ) {
								esc_html_e( 'On the website', 'estat-os' );
							} elseif ( 'trash' === $row_status ) {
								esc_html_e( 'In the bin', 'estat-os' );
							} else {
								esc_html_e( 'Draft', 'estat-os' );
							}
							?>
							<div class="estat-muted"><?php echo esc_html( Vocabulary::label( 'availability', (string) $data['availability'] ) ); ?></div>
						</td>
						<td data-label="<?php esc_attr_e( 'Readiness', 'estat-os' ); ?>">
							<span class="estat-pill <?php echo $score >= 70 ? 'is-good' : 'is-warn'; ?>"><?php echo esc_html( $score . '%' ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Actions', 'estat-os' ); ?>">
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . $id ) ); ?>"><?php esc_html_e( 'Edit', 'estat-os' ); ?></a>
							<a class="button button-small" href="<?php echo esc_url( (string) get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'estat-os' ); ?></a>
							<?php
							if ( current_user_can( 'estat_delete_listings' ) ) {
								Partials::record_actions( $id, (string) get_post_status() );
							}
							?>
						</td>
					</tr>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</tbody>
		</table>
		<?php
		if ( $may_bulk ) {
			Partials::bulk_close();
		}

		$links = paginate_links(
			array(
				'total'     => (int) $query->max_num_pages,
				'current'   => $paged,
				'type'      => 'array',
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
			)
		);
		if ( is_array( $links ) ) {
			echo '<nav class="estat-pagination" aria-label="' . esc_attr__( 'Listing pages', 'estat-os' ) . '"><ul>';
			foreach ( $links as $link ) {
				echo '<li>' . wp_kses_post( $link ) . '</li>';
			}
			echo '</ul></nav>';
		}
	}

	/**
	 * The three-chapter editor.
	 *
	 * @param int $listing_id Listing ID.
	 * @return void
	 */
	private static function editor( int $listing_id ): void {
		$post = get_post( $listing_id );
		if ( ! $post || PostTypes::LISTING !== $post->post_type ) {
			Partials::empty_state(
				array(
					'icon'    => 'search',
					'title'   => __( 'That property could not be found', 'estat-os' ),
					'message' => __( 'It may have been deleted, or the link you followed may be out of date.', 'estat-os' ),
					'action'  => admin_url( 'admin.php?page=estat-listings' ),
					'label'   => __( 'Back to listings', 'estat-os' ),
				)
			);
			return;
		}
		if ( ! current_user_can( 'estat_edit_record', $listing_id ) && ! current_user_can( 'estat_manage_settings' ) ) {
			wp_die( esc_html__( 'You can only edit listings that belong to you.', 'estat-os' ) );
		}

		$schema     = Meta::listing_schema();
		$meta       = static fn( string $key ) => get_post_meta( $listing_id, $key, true );
		$readiness  = Listings::completeness( $listing_id );
		$localities = wp_get_object_terms( $listing_id, Taxonomies::LOCALITY, array( 'fields' => 'names' ) );
		$amenities  = wp_get_object_terms( $listing_id, Taxonomies::AMENITY, array( 'fields' => 'names' ) );
		$features   = wp_get_object_terms( $listing_id, Taxonomies::FEATURE, array( 'fields' => 'names' ) );
		$symbol     = (string) Settings::get( 'currency_symbol', '₹' );

		?>
		<div class="estat-editor-layout">
			<form method="post" class="estat-form-admin estat-editor" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-listings&edit=' . $listing_id ) ); ?>">
				<?php wp_nonce_field( 'estat_save_listing_' . $listing_id, 'estat_nonce' ); ?>
				<input type="hidden" name="estat_action" value="save_listing" />
				<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $listing_id ); ?>" />

				<details class="estat-chapter" open>
					<summary><h2><?php esc_html_e( 'Chapter 1 — The home', 'estat-os' ); ?></h2><p><?php esc_html_e( 'The information every buyer looks for first.', 'estat-os' ); ?></p></summary>
					<div class="estat-chapter-body">
						<?php
						Partials::field( array( 'name' => 'title', 'label' => __( 'Headline', 'estat-os' ), 'value' => $post->post_title, 'required' => true, 'help' => __( 'Say what it is and where, in a few words.', 'estat-os' ), 'example' => __( '3 BHK apartment with a park view', 'estat-os' ) ) );

						foreach ( array( '_estat_offer', '_estat_property_type', '_estat_price_type' ) as $key ) {
							$field = $schema[ $key ];
							Partials::field(
								array(
									'name'    => $key,
									'type'    => 'select',
									'label'   => (string) $field['label'],
									'help'    => (string) ( $field['help'] ?? '' ),
									'options' => call_user_func( array( Vocabulary::class, (string) $field['choices'] ) ),
									'value'   => $meta( $key ),
								)
							);
						}

						Partials::field( array( 'name' => '_estat_price', 'type' => 'number', 'label' => sprintf( /* translators: %s: currency symbol */ __( 'Price (%s)', 'estat-os' ), $symbol ), 'value' => $meta( '_estat_price' ), 'help' => (string) $schema['_estat_price']['help'], 'example' => '8500000', 'min' => 0, 'step' => 1000 ) );
						Partials::field( array( 'name' => '_estat_area', 'type' => 'number', 'label' => __( 'Size', 'estat-os' ), 'value' => $meta( '_estat_area' ), 'help' => __( 'Numbers only.', 'estat-os' ), 'example' => '1250', 'min' => 0 ) );
						Partials::field( array( 'name' => '_estat_area_unit', 'type' => 'select', 'label' => __( 'Measured in', 'estat-os' ), 'options' => Vocabulary::area_units(), 'value' => $meta( '_estat_area_unit' ) ) );

						foreach ( array( '_estat_bedrooms' => __( 'Bedrooms', 'estat-os' ), '_estat_bathrooms' => __( 'Bathrooms', 'estat-os' ) ) as $key => $label ) {
							Partials::field( array( 'name' => $key, 'type' => 'number', 'label' => $label, 'value' => $meta( $key ), 'min' => 0, 'max' => 500 ) );
						}

						// Where it is, is one of the first things any buyer asks, so it
						// belongs with the basics rather than further down the page.
						Partials::field( array( 'name' => 'locality', 'label' => __( 'Locality', 'estat-os' ), 'value' => is_wp_error( $localities ) ? '' : implode( ', ', $localities ), 'help' => __( 'Separate several with commas.', 'estat-os' ), 'required' => true ) );

						// Money that only some deals involve.
						Partials::extras_open(
							__( 'Rent, deposit and maintenance', 'estat-os' ),
							__( 'Only needed if this is a rental. Skip it for a sale.', 'estat-os' ),
							Partials::has_content( array( $meta( '_estat_rent' ), $meta( '_estat_deposit' ), $meta( '_estat_maintenance' ) ) )
						);
						Partials::field( array( 'name' => '_estat_rent', 'type' => 'number', 'label' => __( 'Monthly rent', 'estat-os' ), 'value' => $meta( '_estat_rent' ), 'help' => __( 'Only needed for rent or lease.', 'estat-os' ), 'min' => 0, 'step' => 500 ) );
						Partials::field( array( 'name' => '_estat_deposit', 'type' => 'number', 'label' => __( 'Security deposit', 'estat-os' ), 'value' => $meta( '_estat_deposit' ), 'min' => 0 ) );
						Partials::field( array( 'name' => '_estat_maintenance', 'type' => 'number', 'label' => __( 'Monthly maintenance', 'estat-os' ), 'value' => $meta( '_estat_maintenance' ), 'min' => 0 ) );
						Partials::extras_close();

						// Numbers a buyer sometimes asks about, but rarely up front.
						Partials::extras_open(
							__( 'More details about the building', 'estat-os' ),
							'',
							Partials::has_content( array( $meta( '_estat_area_type' ), $meta( '_estat_balconies' ), $meta( '_estat_parking' ), $meta( '_estat_floor' ), $meta( '_estat_total_floors' ), $meta( '_estat_age' ) ) )
						);
						Partials::field( array( 'name' => '_estat_area_type', 'type' => 'select', 'label' => __( 'Which area is this?', 'estat-os' ), 'options' => Vocabulary::area_types(), 'value' => $meta( '_estat_area_type' ) ) );
						foreach ( array( '_estat_balconies' => __( 'Balconies', 'estat-os' ), '_estat_parking' => __( 'Parking spaces', 'estat-os' ), '_estat_floor' => __( 'Which floor', 'estat-os' ), '_estat_total_floors' => __( 'Floors in the building', 'estat-os' ), '_estat_age' => __( 'Age in years', 'estat-os' ) ) as $key => $label ) {
							Partials::field( array( 'name' => $key, 'type' => 'number', 'label' => $label, 'value' => $meta( $key ), 'min' => 0, 'max' => 500 ) );
						}
						Partials::extras_close();
						?>
					</div>
				</details>

				<details class="estat-chapter">
					<summary><h2><?php esc_html_e( 'Chapter 2 — Story and extras', 'estat-os' ); ?></h2><p><?php esc_html_e( 'Photos, description, address and everything that helps a buyer decide.', 'estat-os' ); ?></p></summary>
					<div class="estat-chapter-body">
						<?php
						Partials::field( array( 'name' => 'description', 'type' => 'textarea', 'rows' => 8, 'label' => __( 'Describe the property', 'estat-os' ), 'value' => $post->post_content, 'help' => __( 'Write as if you were telling a friend about it. Mention the neighbourhood, the light, what is nearby.', 'estat-os' ) ) );
						Partials::field( array( 'name' => 'cover_id', 'type' => 'image', 'label' => __( 'Main photo', 'estat-os' ), 'value' => get_post_thumbnail_id( $listing_id ), 'help' => __( 'This is the photo people see first.', 'estat-os' ) ) );
						Partials::field( array( 'name' => '_estat_gallery', 'type' => 'gallery', 'label' => __( 'Photo gallery', 'estat-os' ), 'value' => (array) $meta( '_estat_gallery' ) ) );

						Partials::field( array( 'name' => 'amenities', 'label' => __( 'Amenities', 'estat-os' ), 'value' => is_wp_error( $amenities ) ? '' : implode( ', ', $amenities ), 'help' => __( 'For example: lift, parking, power backup.', 'estat-os' ) ) );
						Partials::field( array( 'name' => 'features', 'label' => __( 'Special features', 'estat-os' ), 'value' => is_wp_error( $features ) ? '' : implode( ', ', $features ), 'help' => __( 'Anything that makes this one different.', 'estat-os' ) ) );

						Partials::extras_open(
							__( 'Floor plans, brochures, video and 360° tour', 'estat-os' ),
							'',
							Partials::has_content( array( $meta( '_estat_floor_plans' ), $meta( '_estat_brochures' ), $meta( '_estat_video_url' ), $meta( '_estat_tour_url' ) ) )
						);

						Partials::field( array( 'name' => '_estat_floor_plans', 'type' => 'gallery', 'label' => __( 'Floor plans', 'estat-os' ), 'value' => (array) $meta( '_estat_floor_plans' ) ) );
						Partials::field( array( 'name' => '_estat_brochures', 'type' => 'gallery', 'label' => __( 'Brochures and documents', 'estat-os' ), 'value' => (array) $meta( '_estat_brochures' ) ) );
						Partials::field( array( 'name' => '_estat_video_url', 'type' => 'url', 'label' => __( 'Video link', 'estat-os' ), 'value' => $meta( '_estat_video_url' ), 'example' => 'https://youtu.be/…' ) );
						Partials::field( array( 'name' => '_estat_tour_url', 'type' => 'url', 'label' => __( '360° tour link', 'estat-os' ), 'value' => $meta( '_estat_tour_url' ) ) );
						Partials::extras_close();

						Partials::extras_open(
							__( 'Exact address and map position', 'estat-os' ),
							__( 'The locality above is enough for most listings. Add this to show a map on the property page.', 'estat-os' ),
							Partials::has_content( array( $meta( '_estat_address' ), $meta( '_estat_facing' ), $meta( '_estat_furnishing' ), $meta( '_estat_latitude' ), $meta( '_estat_longitude' ) ) )
						);
						Partials::field( array( 'name' => '_estat_address', 'type' => 'textarea', 'label' => __( 'Full address', 'estat-os' ), 'value' => $meta( '_estat_address' ), 'help' => (string) $schema['_estat_address']['help'] ) );
						Partials::field( array( 'name' => '_estat_facing', 'type' => 'select', 'label' => __( 'Which way does it face?', 'estat-os' ), 'options' => Vocabulary::facing(), 'value' => $meta( '_estat_facing' ) ) );
						Partials::field( array( 'name' => '_estat_furnishing', 'type' => 'select', 'label' => __( 'Furnishing', 'estat-os' ), 'options' => Vocabulary::furnishing(), 'value' => $meta( '_estat_furnishing' ) ) );
						Partials::field( array( 'name' => '_estat_latitude', 'label' => __( 'Map latitude', 'estat-os' ), 'value' => $meta( '_estat_latitude' ), 'example' => '28.6139', 'help' => __( 'Optional. Adding this shows a map on the property page.', 'estat-os' ) ) );
						Partials::field( array( 'name' => '_estat_longitude', 'label' => __( 'Map longitude', 'estat-os' ), 'value' => $meta( '_estat_longitude' ), 'example' => '77.2090' ) );
						Partials::extras_close();
						?>
					</div>
				</details>

				<details class="estat-chapter">
					<summary><h2><?php esc_html_e( 'Chapter 3 — Office information', 'estat-os' ); ?></h2><p><?php esc_html_e( 'Who is handling it, whether it is still available, and private notes.', 'estat-os' ); ?></p></summary>
					<div class="estat-chapter-body">
						<?php
						Partials::field( array( 'name' => '_estat_availability', 'type' => 'select', 'label' => __( 'Is it still available?', 'estat-os' ), 'options' => Vocabulary::availability(), 'value' => $meta( '_estat_availability' ) ) );
						Partials::field( array( 'name' => '_estat_construction', 'type' => 'select', 'label' => __( 'Construction stage', 'estat-os' ), 'options' => Vocabulary::construction(), 'value' => $meta( '_estat_construction' ) ) );

						if ( current_user_can( 'estat_verify_listings' ) ) {
							Partials::field( array( 'name' => '_estat_verification', 'type' => 'select', 'label' => __( 'Have we checked this property?', 'estat-os' ), 'options' => Vocabulary::verification(), 'value' => $meta( '_estat_verification' ) ) );
						}

						$agents = Picker::options( PostTypes::AGENT, __( 'Nobody yet', 'estat-os' ), (int) $meta( '_estat_agent_id' ) );
						Partials::field(
							array(
								'name'    => '_estat_agent_id',
								'type'    => 'select',
								'label'   => __( 'Who is handling it?', 'estat-os' ),
								'options' => $agents['options'],
								'value'   => $meta( '_estat_agent_id' ),
								'help'    => Picker::notice( $agents ),
							)
						);

						$projects = Picker::options( PostTypes::PROJECT, __( 'Not part of one', 'estat-os' ), (int) $meta( '_estat_project_id' ) );
						Partials::field(
							array(
								'name'    => '_estat_project_id',
								'type'    => 'select',
								'label'   => __( 'Society or project', 'estat-os' ),
								'options' => $projects['options'],
								'value'   => $meta( '_estat_project_id' ),
								'help'    => Picker::notice( $projects ),
							)
						);


						if ( Settings::get( 'show_regulatory' ) ) {
							Partials::field(
								array(
									'name'  => '_estat_regulatory_id',
									'label' => sprintf( /* translators: %s: regulatory label such as RERA */ __( '%s number', 'estat-os' ), (string) Settings::get( 'regulatory_label', 'RERA' ) ),
									'value' => $meta( '_estat_regulatory_id' ),
									'help'  => __( 'The official registration number, if this property has one.', 'estat-os' ),
								)
							);
						}

						Partials::field( array( 'name' => '_estat_featured', 'type' => 'checkbox', 'label' => __( 'Featured', 'estat-os' ), 'checkbox_label' => __( 'Show this property prominently on the website', 'estat-os' ), 'value' => $meta( '_estat_featured' ) ) );
						Partials::field( array( 'name' => '_estat_internal_notes', 'type' => 'textarea', 'label' => __( 'Private office notes', 'estat-os' ), 'value' => $meta( '_estat_internal_notes' ), 'help' => (string) $schema['_estat_internal_notes']['help'] ) );

						Partials::extras_open(
							__( 'Builder, possession date and your own reference', 'estat-os' ),
							__( 'Useful for new projects and for matching this listing to your own records.', 'estat-os' ),
							Partials::has_content( array( $meta( '_estat_developer' ), $meta( '_estat_possession_date' ), $meta( '_estat_external_id' ), $meta( '_estat_investment' ), $meta( '_estat_expiry_date' ) ) )
						);
						Partials::field( array( 'name' => '_estat_developer', 'label' => __( 'Builder or developer', 'estat-os' ), 'value' => $meta( '_estat_developer' ) ) );
						Partials::field( array( 'name' => '_estat_possession_date', 'type' => 'date', 'label' => __( 'Possession date', 'estat-os' ), 'value' => $meta( '_estat_possession_date' ) ) );
						Partials::field( array( 'name' => '_estat_investment', 'type' => 'checkbox', 'label' => __( 'Investment', 'estat-os' ), 'checkbox_label' => __( 'Mark this as a good investment opportunity', 'estat-os' ), 'value' => $meta( '_estat_investment' ) ) );
						Partials::field( array( 'name' => '_estat_expiry_date', 'type' => 'date', 'label' => __( 'Take it off the website on', 'estat-os' ), 'value' => $meta( '_estat_expiry_date' ), 'help' => __( 'Leave empty to keep it live. On this date it becomes a draft again — nothing is deleted.', 'estat-os' ) ) );
						Partials::field( array( 'name' => '_estat_external_id', 'label' => __( 'Your own reference number', 'estat-os' ), 'value' => $meta( '_estat_external_id' ), 'help' => (string) $schema['_estat_external_id']['help'] ) );
						Partials::extras_close();
						?>
					</div>
				</details>

				<div class="estat-form-actions-admin estat-sticky-actions">
					<button type="submit" name="estat_save_mode" value="draft" class="button button-secondary"><?php esc_html_e( 'Save as draft', 'estat-os' ); ?></button>
					<?php if ( current_user_can( 'estat_publish_listings' ) ) : ?>
						<button type="submit" name="estat_save_mode" value="publish" class="button button-primary"><?php echo 'publish' === $post->post_status ? esc_html__( 'Save changes', 'estat-os' ) : esc_html__( 'Put it on the website', 'estat-os' ); ?></button>
					<?php endif; ?>
					<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-listings' ) ); ?>"><?php esc_html_e( 'Back to listings', 'estat-os' ); ?></a>
				</div>
			</form>

			<aside class="estat-editor-side">
				<?php
				Partials::card_open( __( 'How ready is this listing?', 'estat-os' ) );
				?>
				<div class="estat-progress" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: percentage */ __( '%d percent complete', 'estat-os' ), (int) $readiness['score'] ) ); ?>">
					<div class="estat-progress-bar" style="width:<?php echo esc_attr( (string) (int) $readiness['score'] ); ?>%"></div>
				</div>
				<p class="estat-progress-value"><?php echo esc_html( $readiness['score'] . '%' ); ?></p>
				<?php if ( $readiness['missing'] ) : ?>
					<p class="description"><?php esc_html_e( 'Still missing:', 'estat-os' ); ?></p>
					<ul class="estat-missing">
						<?php foreach ( $readiness['missing'] as $missing ) : ?>
							<li><a href="#estat-<?php echo esc_attr( sanitize_html_class( (string) $missing['field'] ) ); ?>"><?php echo esc_html( (string) $missing['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="estat-good"><?php esc_html_e( 'Everything important is filled in.', 'estat-os' ); ?></p>
				<?php endif; ?>
				<?php
				Partials::card_close();

				Partials::card_open( __( 'How it looks on the website', 'estat-os' ) );
				echo '<div class="estat-preview">';
				echo \EstatOS\Frontend\Components::card( $listing_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</div>';
				echo '<p><a href="' . esc_url( (string) get_permalink( $listing_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the property page', 'estat-os' ) . '</a></p>';
				Partials::card_close();
				?>
			</aside>
		</div>
		<?php
	}

}
