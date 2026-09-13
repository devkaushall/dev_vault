<?php
/**
 * Accessible HTML renderer for plugin forms.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Forms;

use EstatOS\Data\PostTypes;
use EstatOS\Data\Taxonomies;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Produces semantic, keyboard friendly markup with real labels, described-by
 * help text and inline error containers. No framework required.
 */
final class Renderer {

	/**
	 * Render a form.
	 *
	 * @param int                 $form_id Form ID.
	 * @param array<string,mixed> $context Context: listing_id, project_id, agent_id, class.
	 * @return string HTML.
	 */
	public static function render( int $form_id, array $context = array() ): string {
		$form = Forms::get( $form_id );
		if ( ! $form || 'active' !== $form['status'] ) {
			return '';
		}

		wp_enqueue_style( 'estat-forms' );
		wp_enqueue_script( 'estat-forms' );

		$settings   = $form['settings'];
		$definition = $form['definition'];
		$listing_id = isset( $context['listing_id'] ) ? absint( $context['listing_id'] ) : 0;
		$project_id = isset( $context['project_id'] ) ? absint( $context['project_id'] ) : 0;
		$agent_id   = isset( $context['agent_id'] ) ? absint( $context['agent_id'] ) : 0;
		$dom_id     = 'estat-form-' . $form_id . '-' . wp_rand( 1000, 9999 );
		$extra      = isset( $context['class'] ) ? sanitize_html_class( (string) $context['class'] ) : '';

		ob_start();
		?>
		<?php $style_css = Style::to_css( (array) ( $settings['style'] ?? array() ) ); ?>
		<div class="estat-form-wrap <?php echo esc_attr( $extra ); ?>">
			<form class="estat-form" id="<?php echo esc_attr( $dom_id ); ?>" method="post" novalidate
				style="<?php echo esc_attr( $style_css ); ?>"
				data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
				data-endpoint="<?php echo esc_url( rest_url( 'estat/v1/forms/' . $form_id . '/submit' ) ); ?>">
				<?php wp_nonce_field( 'estat_form_submit_' . $form_id, 'estat_nonce' ); ?>
				<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $listing_id ); ?>" />
				<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
				<input type="hidden" name="agent_id" value="<?php echo esc_attr( (string) $agent_id ); ?>" />
				<input type="hidden" name="idempotency_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>" />
				<input type="hidden" name="page_url" value="<?php echo esc_url( self::current_url() ); ?>" />
				<div class="estat-hp" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'estat-os' ); ?>
						<input type="text" name="estat_website" tabindex="-1" autocomplete="off" value="" />
					</label>
				</div>
				<input type="hidden" name="estat_ts" value="<?php echo esc_attr( (string) time() ); ?>" />

				<div class="estat-form-messages" role="status" aria-live="polite"></div>

				<?php foreach ( (array) $definition['rows'] as $row ) : ?>
					<?php if ( ! empty( $row['heading'] ) ) : ?>
						<h3 class="estat-form-section"><?php echo esc_html( (string) $row['heading'] ); ?></h3>
					<?php endif; ?>
					<?php
					$row_gap    = isset( $row['gap'] ) ? (int) $row['gap'] : 16;
					$row_gap_m  = isset( $row['gap_mobile'] ) ? (int) $row['gap_mobile'] : 12;
					$row_align  = isset( $row['align'] ) ? (string) $row['align'] : 'stretch';
					$row_styles = sprintf( '--gap:%dpx;--gap-m:%dpx', $row_gap, $row_gap_m );
					?>
					<div class="estat-row is-align-<?php echo esc_attr( $row_align ); ?>" style="<?php echo esc_attr( $row_styles ); ?>">
						<?php foreach ( (array) $row['columns'] as $column ) : ?>
							<div class="estat-col" style="--w:<?php echo esc_attr( (string) (int) $column['width'] ); ?>%;--wt:<?php echo esc_attr( (string) (int) $column['width_tablet'] ); ?>%;--wm:<?php echo esc_attr( (string) (int) $column['width_mobile'] ); ?>%">
								<?php
								foreach ( (array) $column['fields'] as $field ) {
									// Rendered field markup is escaped inside field().
									echo self::field( $field, $dom_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<?php if ( ! empty( $settings['require_consent'] ) ) : ?>
					<div class="estat-field estat-field-consent">
						<label for="<?php echo esc_attr( $dom_id ); ?>-consent">
							<input type="checkbox" id="<?php echo esc_attr( $dom_id ); ?>-consent" name="consent" value="1" required />
							<span><?php esc_html_e( 'I agree to be contacted about this enquiry.', 'estat-os' ); ?></span>
						</label>
					</div>
				<?php endif; ?>

				<?php
				$btn_style = (array) ( $settings['style'] ?? array() );
				$btn_align = in_array( (string) ( $btn_style['button_align'] ?? 'left' ), array( 'left', 'center', 'right' ), true ) ? (string) $btn_style['button_align'] : 'left';
				$btn_width = 'full' === ( $btn_style['button_width'] ?? 'auto' ) ? ' is-full' : '';
				?>
				<div class="estat-form-actions is-<?php echo esc_attr( $btn_align ); ?>">
					<button type="submit" class="estat-btn estat-btn-primary<?php echo esc_attr( $btn_width ); ?>"><?php echo esc_html( (string) $settings['submit_label'] ); ?></button>
					<span class="estat-form-spinner" hidden aria-hidden="true"></span>
				</div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render one field.
	 *
	 * @param array<string,mixed> $field  Field definition.
	 * @param string              $dom_id Form DOM id prefix.
	 * @return string HTML.
	 */
	private static function field( array $field, string $dom_id ): string {
		$type = (string) $field['type'];
		$id   = $dom_id . '-' . sanitize_html_class( (string) $field['id'] );
		$name = 'fields[' . (string) $field['id'] . ']';
		$def  = FieldTypes::get( $type );

		if ( 'heading' === $type ) {
			return '<h4 class="estat-form-heading">' . esc_html( (string) $field['label'] ) . '</h4>';
		}
		if ( 'paragraph' === $type ) {
			return '<p class="estat-form-paragraph">' . esc_html( (string) $field['content'] ) . '</p>';
		}
		if ( 'submit' === $type ) {
			return '';
		}
		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $field['default'] ) . '" />';
		}

		$required  = ! empty( $field['required'] );
		$help_id   = $id . '-help';
		$error_id  = $id . '-error';
		$described = array();
		if ( '' !== (string) $field['help'] ) {
			$described[] = $help_id;
		}
		$described[] = $error_id;

		$size    = in_array( (string) ( $field['size'] ?? 'md' ), array( 'sm', 'md', 'lg' ), true ) ? (string) $field['size'] : 'md';
		$classes = array( 'estat-field', 'estat-field-' . sanitize_html_class( $type ), 'estat-size-' . $size );
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( empty( $field['visible'][ $device ] ) ) {
				$classes[] = 'estat-hide-' . $device;
			}
		}

		$condition = '';
		if ( ! empty( $field['condition']['field'] ) ) {
			$condition = ' data-condition="' . esc_attr( (string) wp_json_encode( $field['condition'] ) ) . '"';
		}

		$attrs = array(
			'id'          => $id,
			'name'        => $name,
			'class'       => 'estat-input',
			'placeholder' => (string) $field['placeholder'],
			'style'       => 'width:100%',
		);
		if ( $required ) {
			$attrs['required']      = 'required';
			$attrs['aria-required'] = 'true';
		}
		$attrs['aria-describedby'] = implode( ' ', $described );

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="--fw:' . esc_attr( (string) (int) $field['width'] ) . '%"' . $condition . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'checkbox' !== $def['input'] && '' !== (string) $field['label'] ) {
			echo '<label class="estat-label" for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] );
			echo $required ? ' <span class="estat-req" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( '(required)', 'estat-os' ) . '</span>' : ' <span class="estat-opt">' . esc_html__( '(optional)', 'estat-os' ) . '</span>';
			echo '</label>';
		}

		switch ( $def['input'] ) {
			case 'textarea':
				echo '<textarea rows="' . esc_attr( (string) (int) $field['rows'] ) . '" ' . self::attrs( $attrs ) . '>' . esc_textarea( (string) $field['default'] ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;

			case 'select':
				echo '<select ' . self::attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<option value="">' . esc_html__( 'Please choose…', 'estat-os' ) . '</option>';
				foreach ( self::options_for( $field ) as $option ) {
					echo '<option value="' . esc_attr( (string) $option['value'] ) . '"' . selected( (string) $field['default'], (string) $option['value'], false ) . '>' . esc_html( (string) $option['label'] ) . '</option>';
				}
				echo '</select>';
				break;

			case 'radio':
			case 'checklist':
				$input_type = 'radio' === $def['input'] ? 'radio' : 'checkbox';
				echo '<div role="group" aria-labelledby="' . esc_attr( $id ) . '-group">';
				foreach ( self::options_for( $field ) as $index => $option ) {
					$oid = $id . '-' . $index;
					echo '<label class="estat-choice" for="' . esc_attr( $oid ) . '">';
					echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name . ( 'checkbox' === $input_type ? '[]' : '' ) ) . '" value="' . esc_attr( (string) $option['value'] ) . '"' . ( $required && 'radio' === $input_type ? ' required' : '' ) . ' />';
					echo '<span>' . esc_html( (string) $option['label'] ) . '</span></label>';
				}
				echo '</div>';
				break;

			case 'checkbox':
				echo '<label class="estat-choice" for="' . esc_attr( $id ) . '">';
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . ( $required ? ' required' : '' ) . ' />';
				echo '<span>' . esc_html( (string) $field['label'] ) . '</span></label>';
				break;

			case 'file':
				$attrs['type']   = 'file';
				$attrs['accept'] = '.jpg,.jpeg,.png,.pdf';
				echo '<input ' . self::attrs( $attrs ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;

			default:
				$attrs['type']  = (string) $def['input'];
				$attrs['value'] = (string) $field['default'];
				echo '<input ' . self::attrs( $attrs ) . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
		}

		if ( '' !== (string) $field['help'] ) {
			echo '<p class="estat-help" id="' . esc_attr( $help_id ) . '">' . esc_html( (string) $field['help'] ) . '</p>';
		}
		echo '<p class="estat-error" id="' . esc_attr( $error_id ) . '" role="alert"></p>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Options for a field, including dynamic property/project/agent lists.
	 *
	 * @param array<string,mixed> $field Field.
	 * @return array<int,array{value:string,label:string}>
	 */
	private static function options_for( array $field ): array {
		$type   = (string) $field['type'];
		$source = FieldTypes::get( $type )['source'] ?? '';
		if ( '' === $source ) {
			return (array) $field['options'];
		}
		// The office's own property types, so the form always matches the
		// vocabulary used everywhere else in the plugin.
		if ( 'property_type' === $source ) {
			$options = array();
			foreach ( Vocabulary::property_types() as $value => $label ) {
				$options[] = array( 'value' => (string) $value, 'label' => (string) $label );
			}
			return $options;
		}

		// Localities are a taxonomy, not a post type.
		if ( 'locality' === $source ) {
			$terms = get_terms(
				array(
					'taxonomy'   => Taxonomies::LOCALITY,
					'hide_empty' => false,
					'number'     => 300,
					'orderby'    => 'name',
				)
			);

			if ( is_wp_error( $terms ) ) {
				return array();
			}

			$options = array();
			foreach ( (array) $terms as $term ) {
				$options[] = array( 'value' => (string) $term->slug, 'label' => (string) $term->name );
			}
			return $options;
		}

		$map = array(
			'listing' => PostTypes::LISTING,
			'project' => PostTypes::PROJECT,
			'agent'   => PostTypes::AGENT,
		);
		$posts = get_posts(
			array(
				'post_type'      => $map[ $source ] ?? PostTypes::LISTING,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$options = array();
		foreach ( $posts as $post ) {
			$options[] = array( 'value' => (string) $post->ID, 'label' => $post->post_title );
		}
		return $options;
	}

	/**
	 * Build an escaped attribute string.
	 *
	 * @param array<string,string> $attrs Attributes.
	 * @return string
	 */
	private static function attrs( array $attrs ): string {
		$parts = array();
		foreach ( $attrs as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $parts );
	}

	/**
	 * Current page URL, safely rebuilt from server values.
	 *
	 * @return string
	 */
	private static function current_url(): string {
		$permalink = is_singular() ? get_permalink() : home_url( '/' );
		return is_string( $permalink ) ? $permalink : home_url( '/' );
	}
}
