<?php
namespace Soleman_Contact_Form_Order_System\Documents;

use Elementor\Core\DocumentTypes\PageBase;
use Elementor\Modules\PageTemplates\Module as Page_Templates_Module;
use Soleman_Contact_Form_Order_System\Email_Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Template_Document extends PageBase {

	/**
	 * @return array
	 */
	public static function get_properties() {
		$properties = parent::get_properties();

		$properties['cpt']                   = array( Email_Template::CPT );
		$properties['support_kit']           = true;
		$properties['support_wp_page_templates'] = true;
		$properties['show_in_library']       = false;
		$properties['show_in_finder']        = false;

		return $properties;
	}

	/**
	 * @return string
	 */
	public static function get_type() {
		return Email_Template::DOCUMENT_TYPE;
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return Email_Template::DOCUMENT_TYPE;
	}

	/**
	 * @return string
	 */
	public static function get_title() {
		return esc_html__( '表單系統信件版型', 'soleman-contact-form-order-system' );
	}

	/**
	 * @return string
	 */
	public static function get_plural_title() {
		return esc_html__( '表單系統信件版型', 'soleman-contact-form-order-system' );
	}

	/**
	 * @param array $data Document data.
	 * @return bool
	 */
	public function save( $data ) {
		if ( empty( $data['settings']['template'] ) ) {
			$data['settings']['template'] = Page_Templates_Module::TEMPLATE_CANVAS;
		}

		return parent::save( $data );
	}
}
