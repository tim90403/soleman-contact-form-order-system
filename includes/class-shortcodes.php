<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	const TAG = 'scfos_field';

	public function hooks() {
		add_shortcode( self::TAG, array( $this, 'render_field' ) );
	}

	/**
	 * [scfos_field id="bank_code"]
	 * [scfos_field field="contact_mail" fallback="—"]
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_field( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => '',
				'field'    => '',
				'fallback' => '',
			),
			$atts,
			self::TAG
		);

		$field_id = '' !== $atts['id'] ? $atts['id'] : $atts['field'];
		$field_id = sanitize_key( $field_id );

		if ( '' === $field_id ) {
			return '';
		}

		$value = Submission_Context::get( $field_id );

		if ( '' === $value ) {
			return esc_html( (string) $atts['fallback'] );
		}

		return esc_html( $value );
	}
}
