<?php
/**
 * Form storage: definitions, settings, duplication and safe deletion.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Forms;

use EstatOS\Audit\AuditLog;
use EstatOS\Install\Schema;
use EstatOS\Support\Sanitize;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A form definition is a small JSON document: rows -> columns -> fields.
 * Visual design and business behaviour are stored separately so changing the
 * layout can never break lead creation, notifications or webhooks.
 */
final class Forms {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Default business settings for a form.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return array(
			'create_lead'      => true,
			'lead_source'      => 'website',
			'notify'           => true,
			'notify_email'     => '',
			'success_message'  => __( 'Thank you. We have received your enquiry and will contact you soon.', 'estat-os' ),
			'error_message'    => __( 'Sorry, we could not send your enquiry. Please check the form and try again.', 'estat-os' ),
			'submit_label'     => __( 'Send enquiry', 'estat-os' ),
			'redirect_url'     => '',
			'store_submission' => true,
			'require_consent'  => false,
			'listing_field'    => '',
			'project_field'    => '',
			'agent_field'      => '',
			'rate_limit'       => 5,
			'style'            => Style::defaults(),
		);
	}

	/**
	 * A sensible starter form definition (two columns, then a message).
	 *
	 * @return array<string,mixed>
	 */
	public static function starter_definition(): array {
		return array(
			'rows' => array(
				array(
					'columns' => array(
						array( 'width' => 50, 'fields' => array( self::field( 'name', 'name', __( 'Your name', 'estat-os' ), true, __( 'For example: Priya Sharma', 'estat-os' ) ) ) ),
						array( 'width' => 50, 'fields' => array( self::field( 'phone', 'phone', __( 'Phone number', 'estat-os' ), true, __( 'For example: +91 98765 43210', 'estat-os' ) ) ) ),
					),
				),
				array(
					'columns' => array(
						array( 'width' => 100, 'fields' => array( self::field( 'email', 'email', __( 'Email address', 'estat-os' ), false, 'name@example.com' ) ) ),
					),
				),
				array(
					'columns' => array(
						array( 'width' => 100, 'fields' => array( self::field( 'message', 'message', __( 'What would you like to know?', 'estat-os' ), false, __( 'I would like to visit this property this weekend.', 'estat-os' ) ) ) ),
					),
				),
			),
		);
	}

	/**
	 * Build one field definition.
	 *
	 * @param string $type        Field type.
	 * @param string $key         Stable field key.
	 * @param string $label       Label.
	 * @param bool   $required    Required.
	 * @param string $placeholder Placeholder/example.
	 * @return array<string,mixed>
	 */
	public static function field( string $type, string $key, string $label, bool $required = false, string $placeholder = '' ): array {
		return array(
			'id'          => $key,
			'type'        => $type,
			'label'       => $label,
			'placeholder' => $placeholder,
			'help'        => '',
			'required'    => $required,
			'default'     => '',
			'options'     => array(),
			'width'       => 100,
			'width_tablet'=> 100,
			'width_mobile'=> 100,
			'rows'        => 4,
			'size'        => 'md',
			'visible'     => array( 'desktop' => true, 'tablet' => true, 'mobile' => true ),
			'condition'   => array( 'field' => '', 'operator' => 'is', 'value' => '' ),
		);
	}

	/**
	 * Sanitize a whole definition document.
	 *
	 * @param mixed $definition Raw definition (array or JSON string).
	 * @return array<string,mixed>
	 */
	public static function sanitize_definition( $definition ): array {
		if ( is_string( $definition ) ) {
			$decoded    = json_decode( $definition, true );
			$definition = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $definition ) ) {
			$definition = array();
		}
		$rows_in = isset( $definition['rows'] ) && is_array( $definition['rows'] ) ? $definition['rows'] : array();
		$rows    = array();
		$used    = array();

		foreach ( array_slice( $rows_in, 0, 50 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$columns_in = isset( $row['columns'] ) && is_array( $row['columns'] ) ? $row['columns'] : array();
			$columns    = array();
			foreach ( array_slice( $columns_in, 0, 4 ) as $column ) {
				if ( ! is_array( $column ) ) {
					continue;
				}
				$fields_in = isset( $column['fields'] ) && is_array( $column['fields'] ) ? $column['fields'] : array();
				$fields    = array();
				foreach ( array_slice( $fields_in, 0, 30 ) as $field ) {
					$clean = self::sanitize_field( is_array( $field ) ? $field : array(), $used );
					if ( $clean ) {
						$used[]   = $clean['id'];
						$fields[] = $clean;
					}
				}
				$columns[] = array(
					'width'        => Sanitize::int( $column['width'] ?? 100, 10, 100 ),
					'width_tablet' => Sanitize::int( $column['width_tablet'] ?? 100, 10, 100 ),
					'width_mobile' => Sanitize::int( $column['width_mobile'] ?? 100, 10, 100 ),
					'fields'       => $fields,
				);
			}
			$rows[] = array(
				'heading'    => Sanitize::text( $row['heading'] ?? '' ),
				'gap'        => Sanitize::int( $row['gap'] ?? 16, 0, 80 ),
				'gap_mobile' => Sanitize::int( $row['gap_mobile'] ?? 12, 0, 80 ),
				'align'      => Sanitize::choice( $row['align'] ?? 'stretch', array( 'stretch', 'start', 'center', 'end' ), 'stretch' ),
				'columns'    => $columns,
			);
		}

		return array( 'rows' => $rows );
	}

	/**
	 * Sanitize one field, guaranteeing a unique stable id.
	 *
	 * @param array<string,mixed> $field Raw field.
	 * @param string[]            $used  Already used ids.
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_field( array $field, array $used ): ?array {
		$type = Sanitize::text( $field['type'] ?? 'text' );
		if ( ! FieldTypes::exists( $type ) ) {
			return null;
		}
		$id = sanitize_key( (string) ( $field['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = $type . '_' . substr( md5( (string) wp_rand() . microtime() ), 0, 6 );
		}
		while ( in_array( $id, $used, true ) ) {
			$id .= '_' . wp_rand( 1, 99 );
		}

		$options_in = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$options    = array();
		foreach ( array_slice( $options_in, 0, 100 ) as $option ) {
			if ( is_array( $option ) ) {
				$value = Sanitize::text( $option['value'] ?? '' );
				$label = Sanitize::text( $option['label'] ?? $value );
			} else {
				$value = Sanitize::text( $option );
				$label = $value;
			}
			if ( '' !== $value ) {
				$options[] = array( 'value' => $value, 'label' => $label );
			}
		}

		$visible = isset( $field['visible'] ) && is_array( $field['visible'] ) ? $field['visible'] : array();
		$cond    = isset( $field['condition'] ) && is_array( $field['condition'] ) ? $field['condition'] : array();

		return array(
			'id'           => $id,
			'type'         => $type,
			'label'        => Sanitize::text( $field['label'] ?? '' ),
			'placeholder'  => Sanitize::text( $field['placeholder'] ?? '' ),
			'help'         => Sanitize::text( $field['help'] ?? '' ),
			'required'     => Sanitize::bool( $field['required'] ?? false ),
			'default'      => Sanitize::text( $field['default'] ?? '' ),
			'options'      => $options,
			'width'        => Sanitize::int( $field['width'] ?? 100, 10, 100 ),
			'width_tablet' => Sanitize::int( $field['width_tablet'] ?? 100, 10, 100 ),
			'width_mobile' => Sanitize::int( $field['width_mobile'] ?? 100, 10, 100 ),
			'rows'         => Sanitize::int( $field['rows'] ?? 4, 2, 20 ),
			'size'         => Sanitize::choice( $field['size'] ?? 'md', array( 'sm', 'md', 'lg' ), 'md' ),
			'content'      => Sanitize::textarea( $field['content'] ?? '' ),
			'visible'      => array(
				'desktop' => Sanitize::bool( $visible['desktop'] ?? true ),
				'tablet'  => Sanitize::bool( $visible['tablet'] ?? true ),
				'mobile'  => Sanitize::bool( $visible['mobile'] ?? true ),
			),
			'condition'    => array(
				'field'    => sanitize_key( (string) ( $cond['field'] ?? '' ) ),
				'operator' => Sanitize::choice( $cond['operator'] ?? 'is', array( 'is', 'is_not', 'contains', 'filled', 'empty' ), 'is' ),
				'value'    => Sanitize::text( $cond['value'] ?? '' ),
			),
		);
	}

	/**
	 * Sanitize form business settings.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $settings ): array {
		if ( is_string( $settings ) ) {
			$decoded  = json_decode( $settings, true );
			$settings = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$d = self::default_settings();
		return array(
			'create_lead'      => Sanitize::bool( $settings['create_lead'] ?? $d['create_lead'] ),
			'lead_source'      => Sanitize::choice( $settings['lead_source'] ?? $d['lead_source'], array( 'website', 'form', 'phone', 'walkin', 'api', 'other' ), 'website' ),
			'notify'           => Sanitize::bool( $settings['notify'] ?? $d['notify'] ),
			'notify_email'     => Sanitize::email( $settings['notify_email'] ?? '' ),
			'success_message'  => Sanitize::textarea( $settings['success_message'] ?? $d['success_message'] ),
			'error_message'    => Sanitize::textarea( $settings['error_message'] ?? $d['error_message'] ),
			'submit_label'     => Sanitize::text( $settings['submit_label'] ?? $d['submit_label'] ),
			'redirect_url'     => Sanitize::url( $settings['redirect_url'] ?? '' ),
			'store_submission' => Sanitize::bool( $settings['store_submission'] ?? $d['store_submission'] ),
			'require_consent'  => Sanitize::bool( $settings['require_consent'] ?? $d['require_consent'] ),
			'listing_field'    => sanitize_key( (string) ( $settings['listing_field'] ?? '' ) ),
			'project_field'    => sanitize_key( (string) ( $settings['project_field'] ?? '' ) ),
			'agent_field'      => sanitize_key( (string) ( $settings['agent_field'] ?? '' ) ),
			'rate_limit'       => Sanitize::int( $settings['rate_limit'] ?? $d['rate_limit'], 1, 60 ),

			// How the form LOOKS. Kept inside settings so no table change is
			// needed, but validated by its own class and unable to affect any
			// of the keys above.
			'style'            => Style::sanitize( $settings['style'] ?? array() ),
		);
	}

	/**
	 * Create or update a form.
	 *
	 * @param array<string,mixed> $input   Raw input.
	 * @param int                 $form_id Existing form ID or 0.
	 * @return int|WP_Error
	 */
	public static function save( array $input, int $form_id = 0 ) {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return new WP_Error( 'estat_no_storage', __( 'The office database is not ready yet.', 'estat-os' ) );
		}
		$name = Sanitize::text( $input['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'estat_invalid_name', __( 'Please give this form a name, for example "Property enquiry".', 'estat-os' ) );
		}

		$table = Schema::table( 'estat_forms' );
		$now   = current_time( 'mysql', true );
		$row   = array(
			'name'       => $name,
			'updated_at' => $now,
			'status'     => Sanitize::choice( $input['status'] ?? 'active', array( 'active', 'inactive' ), 'active' ),
			'definition' => (string) wp_json_encode( self::sanitize_definition( $input['definition'] ?? array() ) ),
			'settings'   => (string) wp_json_encode( self::sanitize_settings( $input['settings'] ?? array() ) ),
		);

		if ( $form_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $row, array( 'id' => $form_id ) );
		} else {
			$row['created_at'] = $now;
			$row['slug']       = self::unique_slug( $name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $row );
			$form_id = (int) $wpdb->insert_id;
		}

		AuditLog::record( 'form.saved', 'form', $form_id, array() );
		wp_cache_delete( 'estat_form_' . $form_id, 'estat' );
		return $form_id;
	}

	/**
	 * Build a unique website address for a form.
	 *
	 * @param string $name Form name.
	 * @return string
	 */
	private static function unique_slug( string $name ): string {
		global $wpdb;
		$base  = sanitize_title( $name );
		$base  = '' !== $base ? $base : 'form';
		$slug  = $base;
		$table = Schema::table( 'estat_forms' );
		$i     = 2;
		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug ) );
			if ( ! $exists ) {
				return $slug;
			}
			$slug = $base . '-' . $i;
			++$i;
			if ( $i > 200 ) {
				return $base . '-' . wp_rand( 1000, 9999 );
			}
		}
	}

	/**
	 * Get a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array<string,mixed>|null Decoded form.
	 */
	public static function get( int $form_id ): ?array {
		global $wpdb;
		if ( ! Schema::healthy() || $form_id <= 0 ) {
			return null;
		}
		$cached = wp_cache_get( 'estat_form_' . $form_id, 'estat' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$table = Schema::table( 'estat_forms' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $form_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['definition'] = self::sanitize_definition( $row['definition'] );
		$row['settings']   = self::sanitize_settings( $row['settings'] );
		wp_cache_set( 'estat_form_' . $form_id, $row, 'estat', HOUR_IN_SECONDS );
		return $row;
	}

	/**
	 * List forms.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( int $limit = 100 ): array {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return array();
		}
		$table = Schema::table( 'estat_forms' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, slug, status, created_at, updated_at FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, min( 500, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Duplicate a form including its layout and behaviour.
	 *
	 * @param int $form_id Form ID.
	 * @return int|WP_Error New form ID.
	 */
	public static function duplicate( int $form_id ) {
		$form = self::get( $form_id );
		if ( ! $form ) {
			return new WP_Error( 'estat_not_found', __( 'That form could not be found.', 'estat-os' ) );
		}
		return self::save(
			array(
				/* translators: %s: form name */
				'name'       => sprintf( __( '%s (copy)', 'estat-os' ), $form['name'] ),
				'status'     => $form['status'],
				'definition' => $form['definition'],
				'settings'   => $form['settings'],
			)
		);
	}

	/**
	 * Delete a form. Submissions are kept and simply detached.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function delete( int $form_id ): bool {
		global $wpdb;
		if ( ! Schema::healthy() ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Schema::table( 'estat_forms' ), array( 'id' => $form_id ), array( '%d' ) );
		wp_cache_delete( 'estat_form_' . $form_id, 'estat' );
		AuditLog::record( 'form.deleted', 'form', $form_id, array() );
		return true;
	}

	/**
	 * Flatten a definition into an ordered list of fields.
	 *
	 * @param array<string,mixed> $definition Definition.
	 * @return array<int,array<string,mixed>>
	 */
	public static function flatten( array $definition ): array {
		$fields = array();
		foreach ( (array) ( $definition['rows'] ?? array() ) as $row ) {
			foreach ( (array) ( $row['columns'] ?? array() ) as $column ) {
				foreach ( (array) ( $column['fields'] ?? array() ) as $field ) {
					$fields[] = $field;
				}
			}
		}
		return $fields;
	}

	/**
	 * Create the default enquiry form on activation, once.
	 *
	 * @return void
	 */
	public static function seed(): void {
		if ( ! Schema::healthy() ) {
			return;
		}
		if ( self::all( 1 ) ) {
			return;
		}
		self::save(
			array(
				'name'       => __( 'Property enquiry', 'estat-os' ),
				'definition' => self::starter_definition(),
				'settings'   => self::default_settings(),
			)
		);
	}
}
