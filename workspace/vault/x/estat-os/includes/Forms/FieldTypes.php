<?php
/**
 * Registry of form field types.
 *
 * @package EstatOS
 */

declare( strict_types = 1 );

namespace EstatOS\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Field types are data, not classes, so new ones can be added with a filter.
 */
final class FieldTypes {

	/**
	 * All supported field types.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$types = array(
			'text'      => array( 'label' => __( 'Short text', 'estat-os' ), 'input' => 'text', 'options' => false ),
			'textarea'  => array( 'label' => __( 'Long text', 'estat-os' ), 'input' => 'textarea', 'options' => false ),
			'email'     => array( 'label' => __( 'Email address', 'estat-os' ), 'input' => 'email', 'options' => false ),
			'phone'     => array( 'label' => __( 'Phone number', 'estat-os' ), 'input' => 'tel', 'options' => false ),
			'number'    => array( 'label' => __( 'Number', 'estat-os' ), 'input' => 'number', 'options' => false ),
			'select'    => array( 'label' => __( 'Dropdown', 'estat-os' ), 'input' => 'select', 'options' => true ),
			'radio'     => array( 'label' => __( 'Choose one', 'estat-os' ), 'input' => 'radio', 'options' => true ),
			'checkbox'  => array( 'label' => __( 'Single tick box', 'estat-os' ), 'input' => 'checkbox', 'options' => false ),
			'checklist' => array( 'label' => __( 'Choose many', 'estat-os' ), 'input' => 'checklist', 'options' => true ),
			'date'      => array( 'label' => __( 'Date', 'estat-os' ), 'input' => 'date', 'options' => false ),
			'time'      => array( 'label' => __( 'Time', 'estat-os' ), 'input' => 'time', 'options' => false ),
			'datetime'  => array( 'label' => __( 'Date and time', 'estat-os' ), 'input' => 'datetime-local', 'options' => false ),
			'file'      => array( 'label' => __( 'File upload', 'estat-os' ), 'input' => 'file', 'options' => false ),
			'hidden'    => array( 'label' => __( 'Hidden value', 'estat-os' ), 'input' => 'hidden', 'options' => false ),
			'consent'   => array( 'label' => __( 'Consent tick box', 'estat-os' ), 'input' => 'checkbox', 'options' => false ),
			'url'       => array( 'label' => __( 'Website address', 'estat-os' ), 'input' => 'url', 'options' => false ),
			'name'      => array( 'label' => __( 'Full name', 'estat-os' ), 'input' => 'text', 'options' => false ),
			'address'   => array( 'label' => __( 'Address', 'estat-os' ), 'input' => 'textarea', 'options' => false ),
			'message'   => array( 'label' => __( 'Message', 'estat-os' ), 'input' => 'textarea', 'options' => false ),
			'property_type' => array( 'label' => __( 'Property type', 'estat-os' ), 'input' => 'select', 'options' => false, 'source' => 'property_type' ),
			'locality'  => array( 'label' => __( 'Locality', 'estat-os' ), 'input' => 'select', 'options' => false, 'source' => 'locality' ),
			'budget'    => array( 'label' => __( 'Budget', 'estat-os' ), 'input' => 'select', 'options' => true ),
			'property'  => array( 'label' => __( 'Choose a property', 'estat-os' ), 'input' => 'select', 'options' => false, 'source' => 'listing' ),
			'project'   => array( 'label' => __( 'Choose a society/project', 'estat-os' ), 'input' => 'select', 'options' => false, 'source' => 'project' ),
			'agent'     => array( 'label' => __( 'Choose a team member', 'estat-os' ), 'input' => 'select', 'options' => false, 'source' => 'agent' ),
			'heading'   => array( 'label' => __( 'Section heading', 'estat-os' ), 'input' => 'heading', 'options' => false ),
			'paragraph' => array( 'label' => __( 'Text block', 'estat-os' ), 'input' => 'paragraph', 'options' => false ),
			'submit'    => array( 'label' => __( 'Submit button', 'estat-os' ), 'input' => 'submit', 'options' => false ),
		);

		/**
		 * Filter available form field types.
		 *
		 * @param array<string,array<string,mixed>> $types Field types.
		 */
		return (array) apply_filters( 'estat_form_field_types', $types );
	}

	/**
	 * Whether a type exists.
	 *
	 * @param string $type Type key.
	 * @return bool
	 */
	/**
	 * The eight boxes an estate office actually uses most days.
	 *
	 * Showing all of them at once turns the palette into a wall nobody reads,
	 * so these come first and the rest live behind "More field types".
	 *
	 * @return string[]
	 */
	public static function common(): array {
		return array( 'name', 'phone', 'email', 'message', 'property_type', 'locality', 'budget', 'consent' );
	}

	/**
	 * The remaining types, sorted into small groups.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function groups(): array {
		$groups = array(
			'choices'  => array(
				'label' => __( 'Choices', 'estat-os' ),
				'types' => array( 'select', 'radio', 'checkbox', 'checklist' ),
			),
			'datetime' => array(
				'label' => __( 'Date and time', 'estat-os' ),
				'types' => array( 'date', 'time', 'datetime' ),
			),
			'property' => array(
				'label' => __( 'Property', 'estat-os' ),
				'types' => array( 'property', 'project', 'agent', 'address' ),
			),
			'advanced' => array(
				'label' => __( 'Advanced', 'estat-os' ),
				'types' => array( 'text', 'textarea', 'number', 'url', 'file', 'hidden', 'heading', 'paragraph', 'submit' ),
			),
		);

		/**
		 * Filter how field types are grouped in the builder palette.
		 *
		 * @param array<string,array<string,mixed>> $groups Groups.
		 */
		return (array) apply_filters( 'estat_form_field_groups', $groups );
	}

	public static function exists( string $type ): bool {
		return isset( self::all()[ $type ] );
	}

	/**
	 * Type definition.
	 *
	 * @param string $type Type key.
	 * @return array<string,mixed>
	 */
	public static function get( string $type ): array {
		$all = self::all();
		return $all[ $type ] ?? $all['text'];
	}

	/**
	 * Types that hold no submitted value.
	 *
	 * @return string[]
	 */
	public static function decorative(): array {
		return array( 'heading', 'paragraph', 'submit' );
	}
}
