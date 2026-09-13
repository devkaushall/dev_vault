<?php
/**
 * Ready-made forms.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Most offices need the same handful of forms, so nobody should have to build
 * one from an empty screen. Each template is a complete, working form: the
 * fields, sensible labels, and what should happen with the information.
 *
 * Field ids are deliberately the same across every template — a "phone" box is
 * always called `phone`. That means reports, exports and any integration can
 * rely on the name whichever template the office started from.
 */
final class Templates {

	/**
	 * Field ids that mean the same thing in every template.
	 *
	 * Changing one of these would break saved submissions, so they are fixed.
	 */
	public const STANDARD_IDS = array(
		'name',
		'phone',
		'email',
		'message',
		'property_type',
		'locality',
		'budget',
		'consent',
		'visit_date',
		'visit_time',
		'address',
		'loan_amount',
	);

	/**
	 * Every ready-made form.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$templates = array(
			'enquiry'   => self::enquiry(),
			'visit'     => self::visit(),
			'contact'   => self::contact(),
			'sell'      => self::sell(),
			'valuation' => self::valuation(),
		);

		/**
		 * Filter the ready-made forms.
		 *
		 * @param array<string,array<string,mixed>> $templates Templates.
		 */
		return (array) apply_filters( 'estat_form_templates', $templates );
	}

	/**
	 * One template by key.
	 *
	 * @param string $key Template key.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $key ): ?array {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Build a row from a list of fields, laid out side by side.
	 *
	 * @param array<int,array<string,mixed>> $fields Fields for this row.
	 * @return array<string,mixed>
	 */
	private static function row( array $fields ): array {
		$count = max( 1, count( $fields ) );
		$width = (int) floor( 100 / $count );

		$columns = array();

		foreach ( $fields as $field ) {
			$columns[] = array(
				'width'        => $width,
				'width_tablet' => $count > 2 ? 50 : $width,
				// Side by side never works on a phone.
				'width_mobile' => 100,
				'fields'       => array( $field ),
			);
		}

		return array(
			'heading' => '',
			'gap'     => 16,
			'columns' => $columns,
		);
	}

	/**
	 * A field with the extras a template needs.
	 *
	 * @param string               $type     Field type.
	 * @param string               $id       Field id.
	 * @param string               $label    Label.
	 * @param bool                 $required Whether it must be filled in.
	 * @param string               $example  Placeholder.
	 * @param array<string,mixed>  $extra    Anything else to override.
	 * @return array<string,mixed>
	 */
	private static function field( string $type, string $id, string $label, bool $required = false, string $example = '', array $extra = array() ): array {
		return array_merge( Forms::field( $type, $id, $label, $required, $example ), $extra );
	}

	/**
	 * The choices offered for a budget, kept vague on purpose so the office
	 * can change them without anything breaking.
	 *
	 * @return string[]
	 */
	private static function budget_choices(): array {
		return array(
			__( 'Under 25 lakh', 'estat-os' ),
			__( '25 – 50 lakh', 'estat-os' ),
			__( '50 lakh – 1 crore', 'estat-os' ),
			__( '1 – 2 crore', 'estat-os' ),
			__( 'Above 2 crore', 'estat-os' ),
			__( 'Not sure yet', 'estat-os' ),
		);
	}

	/**
	 * Consent, worded so it is clear what is being agreed to.
	 *
	 * @return array<string,mixed>
	 */
	private static function consent_field(): array {
		return self::field(
			'consent',
			'consent',
			__( 'Permission to contact you', 'estat-os' ),
			true,
			'',
			array( 'help' => __( 'Tick to allow our office to contact you about this enquiry.', 'estat-os' ) )
		);
	}

	/**
	 * Property enquiry — the form that goes on a property page.
	 *
	 * @return array<string,mixed>
	 */
	private static function enquiry(): array {
		return array
		(
			'label'       => __( 'Property enquiry', 'estat-os' ),
			'icon'        => 'home',
			'description' => __( 'For a property page. Asks who they are and what they want to know, and records which property they were looking at.', 'estat-os' ),
			'best_for'    => __( 'Put this on every property page.', 'estat-os' ),
			'settings'    => array(
				'submit_label'    => __( 'Send enquiry', 'estat-os' ),
				'success_message' => __( 'Thank you. We have your enquiry and will call you shortly.', 'estat-os' ),
				'lead_source'     => 'website',
				'create_lead'     => true,
				'notify'          => true,
				'require_consent' => true,
			),
			'definition'  => array(
				'rows' => array(
					self::row(
						array(
							self::field( 'name', 'name', __( 'Your name', 'estat-os' ), true, __( 'For example: Priya Sharma', 'estat-os' ) ),
							self::field( 'phone', 'phone', __( 'Phone number', 'estat-os' ), true, __( 'For example: 98765 43210', 'estat-os' ) ),
						)
					),
					self::row(
						array(
							self::field( 'email', 'email', __( 'Email address', 'estat-os' ), false, 'name@example.com' ),
						)
					),
					self::row(
						array(
							self::field(
								'message',
								'message',
								__( 'What would you like to know?', 'estat-os' ),
								false,
								__( 'I would like to see this property this weekend.', 'estat-os' )
							),
						)
					),
					self::row( array( self::consent_field() ) ),
				),
			),
		);
	}

	/**
	 * Book a site visit.
	 *
	 * @return array<string,mixed>
	 */
	private static function visit(): array {
		return array(
			'label'       => __( 'Book a site visit', 'estat-os' ),
			'icon'        => 'car',
			'description' => __( 'Lets someone pick a day and time to see a property. The booking appears on your Site Visits screen.', 'estat-os' ),
			'best_for'    => __( 'Good on a property page next to the enquiry form.', 'estat-os' ),
			'settings'    => array(
				'submit_label'    => __( 'Request a visit', 'estat-os' ),
				'success_message' => __( 'Thank you. We will confirm your visit by phone.', 'estat-os' ),
				'lead_source'     => 'website',
				'create_lead'     => true,
				'notify'          => true,
				'require_consent' => true,
			),
			'definition'  => array(
				'rows' => array(
					self::row(
						array(
							self::field( 'name', 'name', __( 'Your name', 'estat-os' ), true, __( 'For example: Priya Sharma', 'estat-os' ) ),
							self::field( 'phone', 'phone', __( 'Phone number', 'estat-os' ), true, __( 'For example: 98765 43210', 'estat-os' ) ),
						)
					),
					self::row(
						array(
							self::field( 'date', 'visit_date', __( 'Which day suits you?', 'estat-os' ), true ),
							self::field( 'time', 'visit_time', __( 'Around what time?', 'estat-os' ), false ),
						)
					),
					self::row(
						array(
							self::field(
								'message',
								'message',
								__( 'Anything we should know?', 'estat-os' ),
								false,
								__( 'I can only visit after 5pm.', 'estat-os' )
							),
						)
					),
					self::row( array( self::consent_field() ) ),
				),
			),
		);
	}

	/**
	 * General contact.
	 *
	 * @return array<string,mixed>
	 */
	private static function contact(): array {
		return array(
			'label'       => __( 'Contact us', 'estat-os' ),
			'icon'        => 'inbox',
			'description' => __( 'A short general form for your Contact page. Asks only the essentials.', 'estat-os' ),
			'best_for'    => __( 'Put this on your Contact page.', 'estat-os' ),
			'settings'    => array(
				'submit_label'    => __( 'Send message', 'estat-os' ),
				'success_message' => __( 'Thank you for writing to us. We will reply soon.', 'estat-os' ),
				'lead_source'     => 'website',
				'create_lead'     => true,
				'notify'          => true,
				'require_consent' => false,
			),
			'definition'  => array(
				'rows' => array(
					self::row(
						array(
							self::field( 'name', 'name', __( 'Your name', 'estat-os' ), true, __( 'For example: Priya Sharma', 'estat-os' ) ),
							self::field( 'phone', 'phone', __( 'Phone number', 'estat-os' ), true, __( 'For example: 98765 43210', 'estat-os' ) ),
						)
					),
					self::row(
						array(
							self::field( 'email', 'email', __( 'Email address', 'estat-os' ), false, 'name@example.com' ),
						)
					),
					self::row(
						array(
							self::field( 'message', 'message', __( 'How can we help?', 'estat-os' ), true ),
						)
					),
				),
			),
		);
	}

	/**
	 * Sell or rent out a property — the office's supply side.
	 *
	 * @return array<string,mixed>
	 */
	private static function sell(): array {
		return array(
			'label'       => __( 'Sell or rent out a property', 'estat-os' ),
			'icon'        => 'key',
			'description' => __( 'For owners who want your office to sell or let their property. Collects what the property is and where.', 'estat-os' ),
			'best_for'    => __( 'Put this on a "Sell with us" page.', 'estat-os' ),
			'settings'    => array(
				'submit_label'    => __( 'Send details', 'estat-os' ),
				'success_message' => __( 'Thank you. One of our team will call you to discuss your property.', 'estat-os' ),
				'lead_source'     => 'website',
				'create_lead'     => true,
				'notify'          => true,
				'require_consent' => true,
			),
			'definition'  => array(
				'rows' => array(
					self::row(
						array(
							self::field( 'name', 'name', __( 'Your name', 'estat-os' ), true, __( 'For example: Priya Sharma', 'estat-os' ) ),
							self::field( 'phone', 'phone', __( 'Phone number', 'estat-os' ), true, __( 'For example: 98765 43210', 'estat-os' ) ),
						)
					),
					self::row(
						array(
							self::field(
								'select',
								'property_type',
								__( 'What kind of property is it?', 'estat-os' ),
								true,
								'',
								array(
									'options' => array(
										__( 'Apartment', 'estat-os' ),
										__( 'Independent house', 'estat-os' ),
										__( 'Villa', 'estat-os' ),
										__( 'Plot', 'estat-os' ),
										__( 'Shop', 'estat-os' ),
										__( 'Office', 'estat-os' ),
										__( 'Other', 'estat-os' ),
									),
								)
							),
							self::field( 'text', 'locality', __( 'Which locality?', 'estat-os' ), true, __( 'For example: Saket', 'estat-os' ) ),
						)
					),
					self::row(
						array(
							self::field(
								'select',
								'budget',
								__( 'What price do you have in mind?', 'estat-os' ),
								false,
								'',
								array(
									'options' => self::budget_choices(),
									'help'    => __( 'A rough idea is fine.', 'estat-os' ),
								)
							),
						)
					),
					self::row(
						array(
							self::field( 'address', 'address', __( 'Full address', 'estat-os' ), false, '', array( 'help' => __( 'Only our office sees this.', 'estat-os' ) ) ),
						)
					),
					self::row( array( self::consent_field() ) ),
				),
			),
		);
	}

	/**
	 * Free valuation request.
	 *
	 * @return array<string,mixed>
	 */
	private static function valuation(): array {
		return array(
			'label'       => __( 'Free property valuation', 'estat-os' ),
			'icon'        => 'chart',
			'description' => __( 'Offers to tell an owner what their property is worth. One of the strongest ways to find sellers.', 'estat-os' ),
			'best_for'    => __( 'Put this on a "What is my property worth?" page.', 'estat-os' ),
			'settings'    => array(
				'submit_label'    => __( 'Get my valuation', 'estat-os' ),
				'success_message' => __( 'Thank you. We will look at recent sales nearby and come back to you with a figure.', 'estat-os' ),
				'lead_source'     => 'website',
				'create_lead'     => true,
				'notify'          => true,
				'require_consent' => true,
			),
			'definition'  => array(
				'rows' => array(
					self::row(
						array(
							self::field( 'name', 'name', __( 'Your name', 'estat-os' ), true, __( 'For example: Priya Sharma', 'estat-os' ) ),
							self::field( 'phone', 'phone', __( 'Phone number', 'estat-os' ), true, __( 'For example: 98765 43210', 'estat-os' ) ),
						)
					),
					self::row(
						array(
							self::field( 'text', 'locality', __( 'Which locality is it in?', 'estat-os' ), true, __( 'For example: Saket', 'estat-os' ) ),
							self::field(
								'select',
								'property_type',
								__( 'What kind of property?', 'estat-os' ),
								true,
								'',
								array(
									'options' => array(
										__( 'Apartment', 'estat-os' ),
										__( 'Independent house', 'estat-os' ),
										__( 'Villa', 'estat-os' ),
										__( 'Plot', 'estat-os' ),
										__( 'Shop', 'estat-os' ),
										__( 'Office', 'estat-os' ),
									),
								)
							),
						)
					),
					self::row(
						array(
							self::field( 'number', 'area', __( 'Roughly how big is it, in square feet?', 'estat-os' ), false, '1200' ),
						)
					),
					self::row( array( self::consent_field() ) ),
				),
			),
		);
	}
}
