<?php
/**
 * Elementor widget definitions.
 *
 * This file is only loaded when Elementor is active.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Integrations\Elementor;

use Elementor\Controls_Manager;
use EstatOS\Data\Agents;
use EstatOS\Data\Listings;
use EstatOS\Data\PostTypes;
use EstatOS\Data\Projects;
use EstatOS\Forms\Forms;
use EstatOS\Forms\Renderer;
use EstatOS\Frontend\Components;
use EstatOS\Support\Vocabulary;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of widget classes.
 */
final class Widgets {

	/**
	 * All widget class names.
	 *
	 * @return string[]
	 */
	public static function classes(): array {
		return array(
			PropertySearchWidget::class,
			PropertyDirectoryWidget::class,
			PropertyCardWidget::class,
			PropertySingleWidget::class,
			ProjectDirectoryWidget::class,
			ProjectSingleWidget::class,
			AgentDirectoryWidget::class,
			AgentSingleWidget::class,
			FormWidget::class,
			LeadCtaWidget::class,
			MapWidget::class,
			GalleryWidget::class,
			CompareWidget::class,
			FavoritesWidget::class,
			InsightsWidget::class,
			DocumentsWidget::class,
			VisitSchedulerWidget::class,
		);
	}

	/**
	 * Choices helper: published posts of a type.
	 *
	 * @param string $post_type Post type.
	 * @return array<int|string,string>
	 */
	public static function post_choices( string $post_type ): array {
		$posts   = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$choices = array( '' => __( 'Use the current page', 'estat-os' ) );
		foreach ( $posts as $post ) {
			$choices[ (string) $post->ID ] = $post->post_title;
		}
		return $choices;
	}
}

/**
 * Property search widget.
 */
final class PropertySearchWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_property_search';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Search', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-search';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Search', 'estat-os' ) ) );
		$this->add_control(
			'action',
			array(
				'label'       => __( 'Where should results be shown?', 'estat-os' ),
				'type'        => Controls_Manager::URL,
				'description' => __( 'Leave empty to use your main properties page.', 'estat-os' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$action   = isset( $settings['action']['url'] ) ? (string) $settings['action']['url'] : '';
		echo Components::search_form( array( 'action' => $action ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Property directory widget.
 */
final class PropertyDirectoryWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_property_directory';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Directory', 'estat-os' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Which properties?', 'estat-os' ) ) );
		$this->add_control(
			'offer',
			array(
				'label'   => __( 'Buy or rent', 'estat-os' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array( '' => __( 'Any', 'estat-os' ) ) + Vocabulary::offers(),
			)
		);
		$this->add_control(
			'property_type',
			array(
				'label'    => __( 'Property type', 'estat-os' ),
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'options'  => Vocabulary::property_types(),
			)
		);
		$this->add_control(
			'featured',
			array(
				'label'   => __( 'Only featured properties', 'estat-os' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);
		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order', 'estat-os' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'recent',
				'options' => array(
					'recent'     => __( 'Newest first', 'estat-os' ),
					'price_asc'  => __( 'Cheapest first', 'estat-os' ),
					'price_desc' => __( 'Most expensive first', 'estat-os' ),
					'featured'   => __( 'Featured first', 'estat-os' ),
				),
			)
		);
		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'How many to show', 'estat-os' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 1,
				'max'     => 60,
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		echo Components::directory( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'offer'         => (string) ( $settings['offer'] ?? '' ),
				'property_type' => (array) ( $settings['property_type'] ?? array() ),
				'featured'      => 'yes' === ( $settings['featured'] ?? '' ),
				'orderby'       => (string) ( $settings['orderby'] ?? 'recent' ),
				'per_page'      => (int) ( $settings['per_page'] ?? 12 ),
			)
		);
	}
}

/**
 * Single property card widget.
 */
final class PropertyCardWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_property_card';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Card', 'estat-os' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Property', 'estat-os' ) ) );
		$this->add_control(
			'listing_id',
			array(
				'label'   => __( 'Choose a property', 'estat-os' ),
				'type'    => Controls_Manager::SELECT2,
				'options' => Widgets::post_choices( PostTypes::LISTING ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$listing_id = (int) ( $settings['listing_id'] ?? 0 );
		if ( $listing_id <= 0 && is_singular( PostTypes::LISTING ) ) {
			$listing_id = (int) get_the_ID();
		}
		if ( $listing_id > 0 ) {
			echo Components::card( $listing_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/**
 * Property details widget.
 */
final class PropertySingleWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_property_single';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Details', 'estat-os' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Property', 'estat-os' ) ) );
		$this->add_control(
			'listing_id',
			array(
				'label'   => __( 'Choose a property', 'estat-os' ),
				'type'    => Controls_Manager::SELECT2,
				'options' => Widgets::post_choices( PostTypes::LISTING ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$listing_id = (int) ( $settings['listing_id'] ?? 0 );
		if ( $listing_id <= 0 && is_singular( PostTypes::LISTING ) ) {
			$listing_id = (int) get_the_ID();
		}
		$data = $listing_id > 0 ? Listings::to_array( $listing_id, false ) : array();
		if ( ! $data ) {
			return;
		}
		echo '<div class="estat-single-facts"><ul>';
		printf( '<li><strong>%s:</strong> %s</li>', esc_html__( 'Price', 'estat-os' ), esc_html( (string) $data['price_display'] ) );
		printf( '<li><strong>%s:</strong> %s</li>', esc_html__( 'Type', 'estat-os' ), esc_html( Vocabulary::label( 'property_types', (string) $data['property_type'] ) ) );
		if ( (int) $data['bedrooms'] > 0 ) {
			printf( '<li><strong>%s:</strong> %s</li>', esc_html__( 'Bedrooms', 'estat-os' ), esc_html( (string) (int) $data['bedrooms'] ) );
		}
		printf( '<li><strong>%s:</strong> %s</li>', esc_html__( 'Availability', 'estat-os' ), esc_html( Vocabulary::label( 'availability', (string) $data['availability'] ) ) );
		echo '</ul></div>';
	}
}

/**
 * Project directory widget.
 */
final class ProjectDirectoryWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_project_directory';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Societies & Projects', 'estat-os' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Projects', 'estat-os' ) ) );
		$this->add_control( 'limit', array( 'label' => __( 'How many to show', 'estat-os' ), 'type' => Controls_Manager::NUMBER, 'default' => 6, 'min' => 1, 'max' => 50 ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$posts    = get_posts(
			array(
				'post_type'      => PostTypes::PROJECT,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, min( 50, (int) ( $settings['limit'] ?? 6 ) ) ),
				'no_found_rows'  => true,
			)
		);
		if ( ! $posts ) {
			echo '<p class="estat-empty">' . esc_html__( 'No societies or projects have been added yet.', 'estat-os' ) . '</p>';
			return;
		}
		// One query for every count, rather than one per card. This runs on a
		// public page, so the difference is felt by visitors.
		$counts = Projects::listing_counts( wp_list_pluck( $posts, 'ID' ) );

		echo '<div class="estat-grid">';
		foreach ( $posts as $post ) {
			$listed = (int) ( $counts[ (int) $post->ID ] ?? 0 );
			echo '<article class="estat-card"><div class="estat-card-body">';
			echo '<h3 class="estat-card-title"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $post->post_title ) . '</a></h3>';
			echo '<p class="estat-card-facts">' . esc_html( sprintf( /* translators: %d: number of properties */ _n( '%d property listed', '%d properties listed', $listed, 'estat-os' ), $listed ) ) . '</p>';
			echo '</div></article>';
		}
		echo '</div>';
	}
}

/**
 * Single project widget.
 */
final class ProjectSingleWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_project_single';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Project Details', 'estat-os' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Project', 'estat-os' ) ) );
		$this->add_control( 'project_id', array( 'label' => __( 'Choose a project', 'estat-os' ), 'type' => Controls_Manager::SELECT2, 'options' => Widgets::post_choices( PostTypes::PROJECT ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$project_id = (int) ( $settings['project_id'] ?? 0 );
		if ( $project_id <= 0 && is_singular( PostTypes::PROJECT ) ) {
			$project_id = (int) get_the_ID();
		}
		if ( $project_id <= 0 ) {
			return;
		}
		echo Components::directory( array( 'project_id' => $project_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Team directory widget.
 */
final class AgentDirectoryWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_agent_directory';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Team Directory', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-person';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Team', 'estat-os' ) ) );
		$this->add_control( 'limit', array( 'label' => __( 'How many to show', 'estat-os' ), 'type' => Controls_Manager::NUMBER, 'default' => 8, 'min' => 1, 'max' => 60 ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		echo Components::agents( (int) ( $settings['limit'] ?? 8 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Single team member widget.
 */
final class AgentSingleWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_agent_single';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Team Member', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-person';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Team member', 'estat-os' ) ) );
		$this->add_control( 'agent_id', array( 'label' => __( 'Choose a person', 'estat-os' ), 'type' => Controls_Manager::SELECT2, 'options' => Widgets::post_choices( PostTypes::AGENT ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$agent_id = (int) ( $settings['agent_id'] ?? 0 );
		if ( $agent_id <= 0 && is_singular( PostTypes::AGENT ) ) {
			$agent_id = (int) get_the_ID();
		}
		$agent = $agent_id > 0 ? Agents::to_array( $agent_id ) : array();
		if ( ! $agent ) {
			return;
		}
		echo '<article class="estat-agent">';
		if ( '' !== (string) $agent['photo'] ) {
			echo '<img class="estat-agent-photo" src="' . esc_url( (string) $agent['photo'] ) . '" alt="' . esc_attr( (string) $agent['name'] ) . '" loading="lazy" />';
		}
		echo '<h3>' . esc_html( (string) $agent['name'] ) . '</h3>';
		if ( '' !== (string) $agent['role'] ) {
			echo '<p class="estat-agent-role">' . esc_html( (string) $agent['role'] ) . '</p>';
		}
		echo '</article>';
	}
}

/**
 * Estat Form widget: renders a form built once in the plugin.
 */
final class FormWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_form';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Estat Form', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/**
	 * Styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'estat-public', 'estat-forms' );
	}

	/**
	 * Scripts.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return array( 'estat-forms' );
	}

	/**
	 * Controls: choose a form and style the wrapper. The fields themselves are
	 * always designed in the plugin so one form can be reused on many pages.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$choices = array( '' => __( 'Choose a form…', 'estat-os' ) );
		foreach ( Forms::all( 100 ) as $form ) {
			$choices[ (string) $form['id'] ] = (string) $form['name'];
		}

		$this->start_controls_section( 'content', array( 'label' => __( 'Form', 'estat-os' ) ) );
		$this->add_control(
			'form_id',
			array(
				'label'       => __( 'Which form?', 'estat-os' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $choices,
				'default'     => '',
				'description' => __( 'Forms are built once under Forms in your office menu, then reused on any page.', 'estat-os' ),
			)
		);
		$this->add_control(
			'listing_id',
			array(
				'label'       => __( 'Attach to a property', 'estat-os' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => Widgets::post_choices( PostTypes::LISTING ),
				'description' => __( 'Leave empty on a property page and the enquiry is attached automatically.', 'estat-os' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => __( 'Appearance', 'estat-os' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'padding',
			array(
				'label'      => __( 'Inside spacing', 'estat-os' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .estat-form-wrap' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'background',
			array(
				'label'     => __( 'Background colour', 'estat-os' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .estat-form-wrap' => 'background-color:{{VALUE}};' ),
			)
		);
		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Button colour', 'estat-os' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .estat-btn-primary' => 'background-color:{{VALUE}};border-color:{{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'     => __( 'Space between fields', 'estat-os' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors' => array( '{{WRAPPER}} .estat-field' => 'margin-bottom:{{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$form_id  = (int) ( $settings['form_id'] ?? 0 );
		if ( $form_id <= 0 ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p class="estat-empty">' . esc_html__( 'Choose a form to display.', 'estat-os' ) . '</p>';
			}
			return;
		}
		$listing_id = (int) ( $settings['listing_id'] ?? 0 );
		if ( $listing_id <= 0 && is_singular( PostTypes::LISTING ) ) {
			$listing_id = (int) get_the_ID();
		}
		echo Renderer::render( $form_id, array( 'listing_id' => $listing_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Call-to-action widget.
 */
final class LeadCtaWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_lead_cta';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Contact Call to Action', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-call-to-action';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Message', 'estat-os' ) ) );
		$this->add_control( 'heading', array( 'label' => __( 'Heading', 'estat-os' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Interested in this property?', 'estat-os' ) ) );
		$this->add_control( 'button', array( 'label' => __( 'Button text', 'estat-os' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Talk to our team', 'estat-os' ) ) );
		$this->add_control( 'link', array( 'label' => __( 'Button link', 'estat-os' ), 'type' => Controls_Manager::URL ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$url      = isset( $settings['link']['url'] ) ? (string) $settings['link']['url'] : '';
		echo '<div class="estat-cta"><h2>' . esc_html( (string) ( $settings['heading'] ?? '' ) ) . '</h2>';
		if ( '' !== $url ) {
			echo '<a class="estat-btn estat-btn-primary" href="' . esc_url( $url ) . '">' . esc_html( (string) ( $settings['button'] ?? '' ) ) . '</a>';
		}
		echo '</div>';
	}
}

/**
 * Map widget.
 */
final class MapWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_map';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Map', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-google-maps';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Map', 'estat-os' ) ) );
		$this->add_control( 'listing_id', array( 'label' => __( 'Property', 'estat-os' ), 'type' => Controls_Manager::SELECT2, 'options' => Widgets::post_choices( PostTypes::LISTING ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$listing_id = (int) ( $settings['listing_id'] ?? 0 );
		if ( $listing_id <= 0 && is_singular( PostTypes::LISTING ) ) {
			$listing_id = (int) get_the_ID();
		}
		if ( $listing_id <= 0 ) {
			return;
		}
		$lat = (float) get_post_meta( $listing_id, '_estat_latitude', true );
		$lng = (float) get_post_meta( $listing_id, '_estat_longitude', true );
		echo Components::map( $lat, $lng, (string) get_the_title( $listing_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Gallery widget.
 */
final class GalleryWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_gallery';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Gallery', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-gallery-grid';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Gallery', 'estat-os' ) ) );
		$this->add_control( 'listing_id', array( 'label' => __( 'Property', 'estat-os' ), 'type' => Controls_Manager::SELECT2, 'options' => Widgets::post_choices( PostTypes::LISTING ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$listing_id = (int) ( $settings['listing_id'] ?? 0 );
		if ( $listing_id <= 0 && is_singular( PostTypes::LISTING ) ) {
			$listing_id = (int) get_the_ID();
		}
		$gallery = $listing_id > 0 ? (array) get_post_meta( $listing_id, '_estat_gallery', true ) : array();
		if ( ! $gallery ) {
			return;
		}
		echo '<div class="estat-gallery">';
		foreach ( array_slice( $gallery, 0, 30 ) as $attachment_id ) {
			echo wp_get_attachment_image( (int) $attachment_id, 'medium_large', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
}

/**
 * Comparison widget.
 */
final class CompareWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_compare';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Property Comparison', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-table';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		echo Components::compare(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Saved properties widget.
 */
final class FavoritesWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_favorites';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Saved Properties', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-heart';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		echo Components::favorites(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Insights widget (native WordPress posts).
 */
final class InsightsWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_insights';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Insights & Articles', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-post-list';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Articles', 'estat-os' ) ) );
		$this->add_control( 'limit', array( 'label' => __( 'How many to show', 'estat-os' ), 'type' => Controls_Manager::NUMBER, 'default' => 3, 'min' => 1, 'max' => 20 ) );
		$this->add_control( 'category', array( 'label' => __( 'Category name', 'estat-os' ), 'type' => Controls_Manager::TEXT, 'description' => __( 'Leave empty to show your latest articles.', 'estat-os' ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$args     = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 20, (int) ( $settings['limit'] ?? 3 ) ) ),
			'no_found_rows'  => true,
		);
		$category = trim( (string) ( $settings['category'] ?? '' ) );
		if ( '' !== $category ) {
			$args['category_name'] = sanitize_title( $category );
		}
		$posts = get_posts( $args );
		if ( ! $posts ) {
			return;
		}
		echo '<div class="estat-grid">';
		foreach ( $posts as $post ) {
			echo '<article class="estat-card"><div class="estat-card-body">';
			echo '<h3 class="estat-card-title"><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html( $post->post_title ) . '</a></h3>';
			echo '<p>' . esc_html( wp_trim_words( (string) get_the_excerpt( $post ), 20 ) ) . '</p>';
			echo '</div></article>';
		}
		echo '</div>';
	}
}

/**
 * Documents widget.
 */
final class DocumentsWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_documents';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Brochures & Documents', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-document-file';
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Documents', 'estat-os' ) ) );
		$this->add_control( 'listing_id', array( 'label' => __( 'Property', 'estat-os' ), 'type' => Controls_Manager::SELECT2, 'options' => Widgets::post_choices( PostTypes::LISTING ) ) );
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$listing_id = (int) ( $settings['listing_id'] ?? 0 );
		if ( $listing_id <= 0 && is_singular( PostTypes::LISTING ) ) {
			$listing_id = (int) get_the_ID();
		}
		$documents = $listing_id > 0 ? (array) get_post_meta( $listing_id, '_estat_brochures', true ) : array();
		if ( ! $documents ) {
			return;
		}
		echo '<ul class="estat-documents">';
		foreach ( array_slice( $documents, 0, 20 ) as $attachment_id ) {
			$url = wp_get_attachment_url( (int) $attachment_id );
			if ( ! $url ) {
				continue;
			}
			echo '<li><a href="' . esc_url( $url ) . '" rel="nofollow">' . esc_html( (string) get_the_title( (int) $attachment_id ) ) . '</a></li>';
		}
		echo '</ul>';
	}
}

/**
 * Visit scheduler widget: a plugin form configured to book a visit.
 */
final class VisitSchedulerWidget extends BaseWidget {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'estat_visit_scheduler';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Visit Request', 'estat-os' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-calendar';
	}

	/**
	 * Styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array( 'estat-public', 'estat-forms' );
	}

	/**
	 * Scripts.
	 *
	 * @return string[]
	 */
	public function get_script_depends(): array {
		return array( 'estat-forms' );
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$choices = array( '' => __( 'Choose a form…', 'estat-os' ) );
		foreach ( Forms::all( 100 ) as $form ) {
			$choices[ (string) $form['id'] ] = (string) $form['name'];
		}
		$this->start_controls_section( 'content', array( 'label' => __( 'Visit request', 'estat-os' ) ) );
		$this->add_control(
			'form_id',
			array(
				'label'       => __( 'Which form?', 'estat-os' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $choices,
				'description' => __( 'Build a form with a date field under Forms, then choose it here.', 'estat-os' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$form_id  = (int) ( $settings['form_id'] ?? 0 );
		if ( $form_id <= 0 ) {
			return;
		}
		$listing_id = is_singular( PostTypes::LISTING ) ? (int) get_the_ID() : 0;
		echo Renderer::render( $form_id, array( 'listing_id' => $listing_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
