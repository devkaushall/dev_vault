<?php
/**
 * The visual form builder.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Admin\Screens;

use EstatOS\Forms\FieldTypes;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Style;
use EstatOS\Forms\Templates;
use EstatOS\Forms\Submissions;
use EstatOS\Support\Icon;

defined( 'ABSPATH' ) || exit;

/**
 * A focused layout builder: rows, columns and fields, with responsive widths.
 * The definition is edited as structured data by the JavaScript builder and
 * posted back as JSON, which the server re-validates from scratch.
 */
final class FormsScreen {

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'estat_manage_forms' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage forms.', 'estat-os' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable

		if ( 'submissions' === $view ) {
			self::submissions( $edit_id );
			return;
		}
		if ( $edit_id > 0 ) {
			self::builder( $edit_id );
			return;
		}
		self::form_list();
	}

	/**
	 * The list of forms.
	 *
	 * @return void
	 */
	private static function form_list(): void {
		$forms = Forms::all( 100 );

		Partials::card_open(
			__( 'Start with a ready-made form', 'estat-os' ),
			__( 'Pick the one closest to what you need. It arrives complete and working — you can change anything afterwards.', 'estat-os' )
		);
		?>
		<div class="estat-template-grid">
			<?php foreach ( Templates::all() as $key => $template ) : ?>
				<form method="post" class="estat-template-card" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-forms' ) ); ?>">
					<?php wp_nonce_field( 'estat_create_form', 'estat_nonce' ); ?>
					<input type="hidden" name="estat_action" value="create_form" />
					<input type="hidden" name="template" value="<?php echo esc_attr( (string) $key ); ?>" />
					<input type="hidden" name="name" value="<?php echo esc_attr( (string) $template['label'] ); ?>" />

					<span class="estat-template-icon"><?php echo Icon::render( (string) $template['icon'], 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="estat-template-name"><?php echo esc_html( (string) $template['label'] ); ?></span>
					<span class="estat-template-desc"><?php echo esc_html( (string) $template['description'] ); ?></span>
					<span class="estat-template-best"><?php echo esc_html( (string) $template['best_for'] ); ?></span>

					<button type="submit" class="button button-primary"><?php esc_html_e( 'Use this one', 'estat-os' ); ?></button>
				</form>
			<?php endforeach; ?>
		</div>
		<?php
		Partials::card_close();

		Partials::card_open( __( 'Or start from scratch', 'estat-os' ), __( 'Only if none of the ready-made ones fit.', 'estat-os' ) );
		?>
		<form method="post" class="estat-form-admin estat-inline-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-forms' ) ); ?>">
			<?php wp_nonce_field( 'estat_create_form', 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="create_form" />
			<label class="screen-reader-text" for="estat-new-form"><?php esc_html_e( 'Form name', 'estat-os' ); ?></label>
			<input type="text" id="estat-new-form" name="name" required placeholder="<?php esc_attr_e( 'For example: Property enquiry', 'estat-os' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Create an empty form', 'estat-os' ); ?></button>
		</form>
		<?php
		Partials::card_close();

		if ( ! $forms ) {
			Partials::empty_state(
				array(
					'icon'       => 'form',
					'title'      => __( 'No forms yet', 'estat-os' ),
					'message'    => __( 'A form is how someone on your website reaches your office. Every message it receives becomes an enquiry automatically.', 'estat-os' ),
					'reassuring' => true,
					'steps'      => array(
						__( 'Create a form using the button above.', 'estat-os' ),
						__( 'Drag the boxes you want people to fill in.', 'estat-os' ),
						__( 'Copy its shortcode onto any page of your website.', 'estat-os' ),
					),
				)
			);
			return;
		}

		Partials::card_open( __( 'Your forms', 'estat-os' ) );
		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Form', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'How to place it', 'estat-os' ) . '</th>';
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'estat-os' ) . '</span></th>';
		echo '</tr></thead><tbody>';
		foreach ( $forms as $form ) {
			$id = (int) $form['id'];
			echo '<tr>';
			echo '<th scope="row" data-label="' . esc_attr__( 'Form', 'estat-os' ) . '"><a href="' . esc_url( admin_url( 'admin.php?page=estat-forms&edit=' . $id ) ) . '">' . esc_html( (string) $form['name'] ) . '</a></th>';
			echo '<td data-label="' . esc_attr__( 'How to place it', 'estat-os' ) . '">';
			echo '<code class="estat-copyable">[estat_form id="' . esc_html( (string) $id ) . '"]</code><br />';
			echo '<span class="estat-muted">' . esc_html__( 'Or use the "Estat Form" block in Elementor and pick this form.', 'estat-os' ) . '</span>';
			echo '</td>';
			echo '<td data-label="' . esc_attr__( 'Responses', 'estat-os' ) . '">';
			echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=estat-forms&edit=' . $id ) ) . '">' . esc_html__( 'Design', 'estat-os' ) . '</a> ';
			echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=estat-forms&view=submissions&edit=' . $id ) ) . '">' . esc_html__( 'Responses', 'estat-os' ) . '</a> ';
			echo '<form method="post" class="estat-inline-form" action="' . esc_url( admin_url( 'admin.php?page=estat-forms' ) ) . '">';
			wp_nonce_field( 'estat_duplicate_form_' . $id, 'estat_nonce' );
			echo '<input type="hidden" name="estat_action" value="duplicate_form" /><input type="hidden" name="form_id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<button type="submit" class="button button-small">' . esc_html__( 'Duplicate', 'estat-os' ) . '</button>';
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		Partials::card_close();
	}

	/**
	 * The builder for one form.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	/**
	 * The seven stages of building a form, in the order the office works.
	 *
	 * @return array<string,string>
	 */
	public static function stages(): array {
		return array(
			'container' => __( 'Container', 'estat-os' ),
			'layout'    => __( 'Layout', 'estat-os' ),
			'content'   => __( 'Content', 'estat-os' ),
			'styling'   => __( 'Styling', 'estat-os' ),
			'advanced'  => __( 'Advanced', 'estat-os' ),
			'backend'   => __( 'Where it goes', 'estat-os' ),
			'done'      => __( 'Finish', 'estat-os' ),
		);
	}

	/**
	 * The tab strip across the top of the builder.
	 *
	 * @return void
	 */
	private static function stage_bar(): void {
		?>
		<div class="estat-stage-bar" role="tablist" aria-label="<?php esc_attr_e( 'Steps for building this form', 'estat-os' ); ?>">
			<?php $index = 0; ?>
			<?php foreach ( self::stages() as $key => $label ) : ?>
				<?php $index++; ?>
				<button
					type="button"
					class="estat-stage-tab<?php echo 'container' === $key ? ' is-active' : ''; ?>"
					role="tab"
					id="estat-stage-tab-<?php echo esc_attr( $key ); ?>"
					aria-controls="estat-stage-<?php echo esc_attr( $key ); ?>"
					aria-selected="<?php echo 'container' === $key ? 'true' : 'false'; ?>"
					data-stage="<?php echo esc_attr( $key ); ?>"
				>
					<span class="estat-stage-number"><?php echo esc_html( (string) $index ); ?></span>
					<span class="estat-stage-name"><?php echo esc_html( $label ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Open one stage panel.
	 *
	 * @param string $key   Stage key.
	 * @param string $title Stage title.
	 * @param string $note  Short explanation.
	 * @return void
	 */
	private static function stage_open( string $key, string $title, string $note = '' ): void {
		?>
		<section
			class="estat-stage"
			id="estat-stage-<?php echo esc_attr( $key ); ?>"
			role="tabpanel"
			aria-labelledby="estat-stage-tab-<?php echo esc_attr( $key ); ?>"
			data-stage="<?php echo esc_attr( $key ); ?>"
			<?php echo 'container' === $key ? '' : 'hidden'; ?>
		>
			<h2 class="estat-stage-title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $note ) : ?>
				<p class="estat-stage-note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
			<div class="estat-stage-body">
		<?php
	}

	/**
	 * Close a stage panel.
	 *
	 * @return void
	 */
	private static function stage_close(): void {
		echo '</div></section>';
	}

	/**
	 * Tell the office that this stage is edited in the middle column.
	 *
	 * @param string $where What to look at.
	 * @return void
	 */
	private static function stage_focus_hint( string $where ): void {
		?>
		<p class="estat-stage-hint">
			<?php
			printf(
				/* translators: %s: the name of the area to look at. */
				esc_html__( 'You do this in "%s", in the middle of this screen.', 'estat-os' ),
				esc_html( $where )
			);
			?>
		</p>
		<?php
	}

	/**
	 * A styling slider.
	 *
	 * Every styling control posts under settings[style][...], so the whole
	 * appearance travels as one group and can never collide with a business
	 * setting.
	 *
	 * @param string $label Label.
	 * @param string $key   Style key.
	 * @param int    $value Current value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @param string $unit  Unit suffix.
	 * @return void
	 */
	private static function style_range( string $label, string $key, int $value, int $min, int $max, string $unit = '' ): void {
		$id = 'estat-style-' . $key;
		?>
		<div class="estat-style-row">
			<label class="estat-style-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div class="estat-style-control">
				<input
					type="range"
					class="estat-builder-range estat-style-input"
					id="<?php echo esc_attr( $id ); ?>"
					name="settings[style][<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( (string) $value ); ?>"
					min="<?php echo esc_attr( (string) $min ); ?>"
					max="<?php echo esc_attr( (string) $max ); ?>"
					step="1"
					data-style="<?php echo esc_attr( $key ); ?>"
					data-unit="<?php echo esc_attr( $unit ); ?>"
				/>
				<output class="estat-style-value"><?php echo esc_html( $value . $unit ); ?></output>
			</div>
		</div>
		<?php
	}

	/**
	 * A styling colour picker, with an explicit "use my theme" state.
	 *
	 * @param string $label Label.
	 * @param string $key   Style key.
	 * @param string $value Current hex colour, or an empty string.
	 * @return void
	 */
	private static function style_colour( string $label, string $key, string $value ): void {
		$id  = 'estat-style-' . $key;
		$set = '' !== $value;
		?>
		<div class="estat-style-row">
			<label class="estat-style-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div class="estat-style-control">
				<input
					type="color"
					class="estat-style-colour"
					id="<?php echo esc_attr( $id ); ?>"
					value="<?php echo esc_attr( $set ? $value : '#2271b1' ); ?>"
					data-style="<?php echo esc_attr( $key ); ?>"
					<?php echo $set ? '' : 'disabled'; ?>
				/>
				<label class="estat-style-inherit">
					<input
						type="checkbox"
						class="estat-style-toggle"
						data-style="<?php echo esc_attr( $key ); ?>"
						<?php checked( $set ); ?>
					/>
					<?php esc_html_e( 'Set a colour', 'estat-os' ); ?>
				</label>
				<input
					type="hidden"
					class="estat-style-input"
					name="settings[style][<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					data-style="<?php echo esc_attr( $key ); ?>"
				/>
			</div>
		</div>
		<?php
	}

	/**
	 * A styling dropdown.
	 *
	 * @param string        $label   Label.
	 * @param string        $key     Style key.
	 * @param string        $value   Current value.
	 * @param array<string> $choices Value => label.
	 * @param string        $help    Optional help text.
	 * @return void
	 */
	private static function style_choice( string $label, string $key, string $value, array $choices, string $help = '' ): void {
		$id = 'estat-style-' . $key;
		?>
		<div class="estat-style-row">
			<label class="estat-style-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<div class="estat-style-control">
				<select
					class="estat-style-input"
					id="<?php echo esc_attr( $id ); ?>"
					name="settings[style][<?php echo esc_attr( $key ); ?>]"
					data-style="<?php echo esc_attr( $key ); ?>"
				>
					<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
						<option value="<?php echo esc_attr( (string) $choice_value ); ?>" <?php selected( (string) $choice_value, $value ); ?>>
							<?php echo esc_html( (string) $choice_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php if ( '' !== $help ) : ?>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function builder( int $form_id ): void {
		$form = Forms::get( $form_id );
		if ( ! $form ) {
			Partials::empty_state(
				array(
					'icon'    => 'search',
					'title'   => __( 'That form could not be found', 'estat-os' ),
					'message' => __( 'It may have been deleted, or the link you followed may be out of date.', 'estat-os' ),
					'action'  => admin_url( 'admin.php?page=estat-forms' ),
					'label'   => __( 'Back to forms', 'estat-os' ),
				)
			);
			return;
		}
		$settings = $form['settings'];
		$style    = isset( $settings['style'] ) ? (array) $settings['style'] : Style::defaults();
		?>
		<form method="post" class="estat-form-admin estat-builder-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-forms&edit=' . $form_id ) ); ?>">
			<?php wp_nonce_field( 'estat_save_form_' . $form_id, 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="save_form" />
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<input type="hidden" name="definition" id="estat-definition" value="<?php echo esc_attr( (string) wp_json_encode( $form['definition'] ) ); ?>" />

			<?php self::stage_bar(); ?>

			<div class="estat-studio">

				<!-- ------------------------------------------- Left: the controls -->
				<div class="estat-studio-panel">

					<?php self::stage_open( 'container', __( 'Container', 'estat-os' ), __( 'The box the whole form sits in.', 'estat-os' ) ); ?>
						<?php
						Partials::field(
							array(
								'name'     => 'name',
								'label'    => __( 'Form name', 'estat-os' ),
								'value'    => (string) $form['name'],
								'required' => true,
								'help'     => __( 'Only your office sees this name. Visitors never see it.', 'estat-os' ),
							)
						);
						self::style_range( __( 'Space between the boxes', 'estat-os' ), 'field_gap', (int) $style['field_gap'], 0, 60, 'px' );
						self::style_choice(
							__( 'Writing style', 'estat-os' ),
							'font',
							(string) $style['font'],
							wp_list_pluck( Style::fonts(), 'label' ),
							__( 'Leave this on "Same as the rest of the site" unless the form looks out of place.', 'estat-os' )
						);
						?>
					<?php self::stage_close(); ?>

					<?php self::stage_open( 'layout', __( 'Layout', 'estat-os' ), __( 'How many columns each row has, and how wide they are.', 'estat-os' ) ); ?>
						<p class="description">
							<?php esc_html_e( 'The rows are in the middle of this screen. Pick a column pattern, drag the spacing slider, and choose how the columns line up.', 'estat-os' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Use the Computer, Tablet and Phone buttons above the rows to set a different width for each screen size.', 'estat-os' ); ?>
						</p>
						<?php self::stage_focus_hint( __( 'Rows and columns', 'estat-os' ) ); ?>
					<?php self::stage_close(); ?>

					<?php self::stage_open( 'content', __( 'Content', 'estat-os' ), __( 'The boxes visitors fill in.', 'estat-os' ) ); ?>
						<?php
						Partials::field(
							array(
								'name'    => 'settings[submit_label]',
								'label'   => __( 'Button text', 'estat-os' ),
								'value'   => (string) $settings['submit_label'],
								'example' => __( 'Send enquiry', 'estat-os' ),
							)
						);
						?>
						<div class="estat-field-palette" id="estat-field-palette">
							<p class="estat-builder-sub"><?php esc_html_e( 'Add a box', 'estat-os' ); ?></p>
							<div class="estat-palette-common">
								<?php
								$all_types = FieldTypes::all();
								foreach ( FieldTypes::common() as $type ) :
									if ( ! isset( $all_types[ $type ] ) ) {
										continue;
									}
									?>
									<button
										type="button"
										class="button estat-add-field estat-palette-chip"
										data-type="<?php echo esc_attr( $type ); ?>"
										data-label="<?php echo esc_attr( (string) $all_types[ $type ]['label'] ); ?>"
									>
										<?php echo esc_html( (string) $all_types[ $type ]['label'] ); ?>
									</button>
								<?php endforeach; ?>
							</div>

							<details class="estat-palette-more">
								<summary><?php esc_html_e( 'More field types', 'estat-os' ); ?></summary>

								<label class="screen-reader-text" for="estat-palette-search"><?php esc_html_e( 'Search field types', 'estat-os' ); ?></label>
								<input
									type="search"
									id="estat-palette-search"
									class="estat-palette-search"
									placeholder="<?php esc_attr_e( 'Search for a box, for example "date"', 'estat-os' ); ?>"
								/>

								<p class="estat-palette-empty" hidden><?php esc_html_e( 'No field type matches that. Try a shorter word.', 'estat-os' ); ?></p>

								<?php foreach ( FieldTypes::groups() as $group ) : ?>
									<div class="estat-palette-group">
										<p class="estat-palette-group-title"><?php echo esc_html( (string) $group['label'] ); ?></p>
										<div class="estat-palette-group-items">
											<?php
											foreach ( (array) $group['types'] as $type ) :
												if ( ! isset( $all_types[ $type ] ) ) {
													continue;
												}
												?>
												<button
													type="button"
													class="button estat-add-field estat-palette-chip"
													data-type="<?php echo esc_attr( $type ); ?>"
													data-label="<?php echo esc_attr( (string) $all_types[ $type ]['label'] ); ?>"
												>
													<?php echo esc_html( (string) $all_types[ $type ]['label'] ); ?>
												</button>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</details>
						</div>
						<p class="description">
							<?php esc_html_e( 'Click a box in the middle to change its label, its example text and whether it is required.', 'estat-os' ); ?>
						</p>
					<?php self::stage_close(); ?>

					<?php self::stage_open( 'styling', __( 'Styling', 'estat-os' ), __( 'Colours, corners and the button. Leave a colour empty to use your theme.', 'estat-os' ) ); ?>
						<p class="estat-builder-sub"><?php esc_html_e( 'The boxes', 'estat-os' ); ?></p>
						<?php
						self::style_colour( __( 'Label colour', 'estat-os' ), 'label_colour', (string) $style['label_colour'] );
						self::style_colour( __( 'Text colour', 'estat-os' ), 'input_colour', (string) $style['input_colour'] );
						self::style_colour( __( 'Box background', 'estat-os' ), 'input_bg', (string) $style['input_bg'] );
						self::style_colour( __( 'Border colour', 'estat-os' ), 'border_colour', (string) $style['border_colour'] );
						self::style_colour( __( 'Colour when clicked into', 'estat-os' ), 'focus_colour', (string) $style['focus_colour'] );
						self::style_colour( __( 'Help text colour', 'estat-os' ), 'help_colour', (string) $style['help_colour'] );
						self::style_colour( __( 'Warning colour', 'estat-os' ), 'error_colour', (string) $style['error_colour'] );

						self::style_range( __( 'Label size', 'estat-os' ), 'label_size', (int) $style['label_size'], 10, 28, 'px' );
						self::style_range( __( 'Text size', 'estat-os' ), 'input_size', (int) $style['input_size'], 12, 28, 'px' );
						self::style_choice(
							__( 'Label thickness', 'estat-os' ),
							'label_weight',
							(string) $style['label_weight'],
							array(
								'400' => __( 'Normal', 'estat-os' ),
								'500' => __( 'Slightly bold', 'estat-os' ),
								'600' => __( 'Bold', 'estat-os' ),
								'700' => __( 'Very bold', 'estat-os' ),
							)
						);
						self::style_range( __( 'Corner roundness', 'estat-os' ), 'radius', (int) $style['radius'], 0, 40, 'px' );
						self::style_range( __( 'Border thickness', 'estat-os' ), 'border_width', (int) $style['border_width'], 0, 6, 'px' );
						self::style_range( __( 'Space inside, top and bottom', 'estat-os' ), 'input_pad_y', (int) $style['input_pad_y'], 2, 30, 'px' );
						self::style_range( __( 'Space inside, left and right', 'estat-os' ), 'input_pad_x', (int) $style['input_pad_x'], 2, 40, 'px' );
						?>

						<p class="estat-builder-sub"><?php esc_html_e( 'The button', 'estat-os' ); ?></p>
						<?php
						self::style_colour( __( 'Button colour', 'estat-os' ), 'button_bg', (string) $style['button_bg'] );
						self::style_colour( __( 'Button text colour', 'estat-os' ), 'button_text', (string) $style['button_text'] );
						self::style_colour( __( 'Button colour on hover', 'estat-os' ), 'button_hover_bg', (string) $style['button_hover_bg'] );
						self::style_range( __( 'Button corner roundness', 'estat-os' ), 'button_radius', (int) $style['button_radius'], 0, 40, 'px' );
						self::style_range( __( 'Button height', 'estat-os' ), 'button_pad_y', (int) $style['button_pad_y'], 4, 30, 'px' );
						self::style_range( __( 'Button width padding', 'estat-os' ), 'button_pad_x', (int) $style['button_pad_x'], 6, 60, 'px' );
						self::style_range( __( 'Button text size', 'estat-os' ), 'button_size', (int) $style['button_size'], 12, 28, 'px' );
						self::style_choice( __( 'Button width', 'estat-os' ), 'button_width', (string) $style['button_width'], Style::button_widths() );
						self::style_choice(
							__( 'Button position', 'estat-os' ),
							'button_align',
							(string) $style['button_align'],
							array(
								'left'   => __( 'Left', 'estat-os' ),
								'center' => __( 'Middle', 'estat-os' ),
								'right'  => __( 'Right', 'estat-os' ),
							)
						);
						?>
					<?php self::stage_close(); ?>

					<?php self::stage_open( 'advanced', __( 'Advanced', 'estat-os' ), __( 'Rules for individual boxes.', 'estat-os' ) ); ?>
						<p class="description">
							<?php esc_html_e( 'Click any box in the middle of this screen to open its settings. There you can set an exact width for each screen size, and choose to show it only when another box has been answered.', 'estat-os' ); ?>
						</p>
						<?php
						Partials::field(
							array(
								'name'  => 'settings[rate_limit]',
								'type'  => 'number',
								'label' => __( 'Maximum submissions per visitor', 'estat-os' ),
								'value' => (int) $settings['rate_limit'],
								'min'   => 1,
								'max'   => 60,
								'help'  => __( 'Within ten minutes. This stops automated spam.', 'estat-os' ),
							)
						);
						Partials::field(
							array(
								'name'           => 'settings[require_consent]',
								'type'           => 'checkbox',
								'label'          => __( 'Ask for consent', 'estat-os' ),
								'checkbox_label' => __( 'Require a tick box before the form can be sent', 'estat-os' ),
								'value'          => (bool) $settings['require_consent'],
							)
						);
						?>
						<?php self::stage_focus_hint( __( 'A box in the form', 'estat-os' ) ); ?>
					<?php self::stage_close(); ?>

					<?php self::stage_open( 'backend', __( 'Where it goes', 'estat-os' ), __( 'What the office does with each submission. Changing the look never changes any of this.', 'estat-os' ) ); ?>
						<?php
						Partials::field(
							array(
								'name'           => 'settings[create_lead]',
								'type'           => 'checkbox',
								'label'          => __( 'Create an enquiry', 'estat-os' ),
								'checkbox_label' => __( 'Add every submission to the Enquiries inbox', 'estat-os' ),
								'value'          => (bool) $settings['create_lead'],
							)
						);
						Partials::field(
							array(
								'name'           => 'settings[store_submission]',
								'type'           => 'checkbox',
								'label'          => __( 'Keep a copy', 'estat-os' ),
								'checkbox_label' => __( 'Store the full response so you can look at it later', 'estat-os' ),
								'value'          => (bool) $settings['store_submission'],
							)
						);
						Partials::field(
							array(
								'name'           => 'settings[notify]',
								'type'           => 'checkbox',
								'label'          => __( 'Email the office', 'estat-os' ),
								'checkbox_label' => __( 'Send an email whenever this form is used', 'estat-os' ),
								'value'          => (bool) $settings['notify'],
							)
						);
						Partials::field(
							array(
								'name'  => 'settings[notify_email]',
								'type'  => 'email',
								'label' => __( 'Send those emails to', 'estat-os' ),
								'value' => (string) $settings['notify_email'],
								'help'  => __( 'Leave empty to use the address in Office Settings.', 'estat-os' ),
							)
						);
						Partials::field(
							array(
								'name'  => 'settings[success_message]',
								'type'  => 'textarea',
								'rows'  => 2,
								'label' => __( 'Message after sending', 'estat-os' ),
								'value' => (string) $settings['success_message'],
							)
						);
						Partials::field(
							array(
								'name'  => 'settings[error_message]',
								'type'  => 'textarea',
								'rows'  => 2,
								'label' => __( 'Message if something is wrong', 'estat-os' ),
								'value' => (string) $settings['error_message'],
							)
						);
						Partials::field(
							array(
								'name'  => 'settings[redirect_url]',
								'type'  => 'url',
								'label' => __( 'Send them to a page afterwards', 'estat-os' ),
								'value' => (string) $settings['redirect_url'],
								'help'  => __( 'Optional. Leave empty to show the message instead.', 'estat-os' ),
							)
						);
						?>
					<?php self::stage_close(); ?>

					<?php self::stage_open( 'done', __( 'Finish', 'estat-os' ), __( 'Save the form, then put it on a page.', 'estat-os' ) ); ?>
						<p><?php esc_html_e( 'Paste this into any page or post:', 'estat-os' ); ?></p>
						<code class="estat-copyable">[estat_form id="<?php echo esc_html( (string) $form_id ); ?>"]</code>
						<p class="description">
							<?php esc_html_e( 'In Elementor, add the "Estat Form" block and choose this form. The same form can be used on as many pages as you like.', 'estat-os' ); ?>
						</p>
						<p class="description">
							<?php esc_html_e( 'Responses arrive in Enquiries. Nothing is sent anywhere until a visitor fills the form in.', 'estat-os' ); ?>
						</p>
					<?php self::stage_close(); ?>

				</div>

				<!-- ------------------------------------------ Middle: the canvas -->
				<div class="estat-studio-canvas">
					<div class="estat-studio-canvas-head">
						<span class="estat-studio-canvas-title"><?php esc_html_e( 'Rows and columns', 'estat-os' ); ?></span>
						<span class="description"><?php esc_html_e( 'Drag a box to move it. Click a box to change it.', 'estat-os' ); ?></span>
					</div>
					<div class="estat-builder" id="estat-builder"
						data-definition="<?php echo esc_attr( (string) wp_json_encode( $form['definition'] ) ); ?>">
						<p class="description"><?php esc_html_e( 'Loading the designer…', 'estat-os' ); ?></p>
					</div>
					<noscript>
						<p class="notice notice-warning"><?php esc_html_e( 'The visual designer needs JavaScript. Your existing form still works exactly as it is.', 'estat-os' ); ?></p>
					</noscript>
				</div>

				<!-- ------------------------------------------- Right: the preview -->
				<aside class="estat-studio-preview">
					<div class="estat-studio-preview-inner">
						<div class="estat-studio-canvas-head">
							<span class="estat-studio-canvas-title"><?php esc_html_e( 'What visitors will see', 'estat-os' ); ?></span>
							<span class="description"><?php esc_html_e( 'Updates as you work.', 'estat-os' ); ?></span>
						</div>
						<div class="estat-form-preview" id="estat-form-preview" aria-live="polite">
							<p class="description"><?php esc_html_e( 'Loading the preview…', 'estat-os' ); ?></p>
						</div>

						<div class="estat-form-actions-admin">
							<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save form', 'estat-os' ); ?></button>
							<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=estat-forms' ) ); ?>"><?php esc_html_e( 'Back to forms', 'estat-os' ); ?></a>
						</div>
					</div>
				</aside>
			</div>
		</form>

		<form method="post" class="estat-delete-form" action="<?php echo esc_url( admin_url( 'admin.php?page=estat-forms' ) ); ?>">
			<?php wp_nonce_field( 'estat_delete_form_' . $form_id, 'estat_nonce' ); ?>
			<input type="hidden" name="estat_action" value="delete_form" />
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<details class="estat-danger">
				<summary><?php esc_html_e( 'Delete this form', 'estat-os' ); ?></summary>
				<p><?php esc_html_e( 'The responses you have already received are kept. Only the form itself is removed.', 'estat-os' ); ?></p>
				<label for="estat-confirm-delete"><?php esc_html_e( 'Type DELETE in capital letters to confirm.', 'estat-os' ); ?></label>
				<input type="text" id="estat-confirm-delete" name="confirm" autocomplete="off" />
				<button type="submit" class="button estat-danger-button"><?php esc_html_e( 'Delete form', 'estat-os' ); ?></button>
			</details>
		</form>
		<?php
	}

	/**
	 * Responses received through a form.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private static function submissions( int $form_id ): void {
		if ( ! current_user_can( 'estat_view_submissions' ) ) {
			wp_die( esc_html__( 'You do not have permission to see form responses.', 'estat-os' ) );
		}
		$results = Submissions::query( array( 'form_id' => $form_id, 'per_page' => 30 ) );

		Partials::card_open( __( 'Responses', 'estat-os' ) );
		if ( ! $results['items'] ) {
			Partials::empty_state(
				array(
					'icon'       => 'inbox',
					'title'      => __( 'No responses yet', 'estat-os' ),
					'message'    => __( 'Nobody has filled in this form so far. Check that it is placed on a page people actually visit.', 'estat-os' ),
					'reassuring' => true,
					'action'     => admin_url( 'admin.php?page=estat-forms' ),
					'label'      => __( 'Back to forms', 'estat-os' ),
				)
			);
			Partials::card_close();
			return;
		}
		echo '<table class="widefat estat-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'When', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'What they sent', 'estat-os' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Enquiry', 'estat-os' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $results['items'] as $row ) {
			$payload = json_decode( (string) $row['payload'], true );
			$payload = is_array( $payload ) ? $payload : array();
			echo '<tr>';
			echo '<td data-label="' . esc_attr__( 'When', 'estat-os' ) . '">' . esc_html( get_date_from_gmt( (string) $row['created_at'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'What they sent', 'estat-os' ) . '"><dl class="estat-payload">';
			foreach ( $payload as $key => $value ) {
				echo '<dt>' . esc_html( (string) $key ) . '</dt><dd>' . esc_html( is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value ) . '</dd>';
			}
			echo '</dl></td>';
			echo '<td data-label="' . esc_attr__( 'Enquiry', 'estat-os' ) . '">';
			if ( (int) $row['lead_id'] > 0 ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=estat-enquiries#lead-' . (int) $row['lead_id'] ) ) . '">' . esc_html__( 'Open enquiry', 'estat-os' ) . '</a>';
			} else {
				echo '<span class="estat-muted">—</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		Partials::card_close();
	}
}
