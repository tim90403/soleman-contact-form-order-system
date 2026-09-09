<?php
namespace Soleman_Contact_Form_Order_System\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Soleman_Contact_Form_Order_System\Plugin;
use Soleman_Contact_Form_Order_System\Submission_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Field_Widget extends Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'scfos-form-field';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return esc_html__( '表單動態欄位', 'soleman-contact-form-order-system' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	/**
	 * @return array
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * @return array
	 */
	public function get_keywords() {
		return array( 'form', 'field', 'submission', 'email', 'dynamic' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( '內容', 'soleman-contact-form-order-system' ),
			)
		);

		$this->add_control(
			'field_id',
			array(
				'label'       => esc_html__( '表單欄位 ID', 'soleman-contact-form-order-system' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => Plugin::FIELD_BANK_CODE,
				'placeholder' => 'bank_code',
				'description' => esc_html__( '填入 Elementor Form 的 Field ID，例如 bank_code、contact_mail。', 'soleman-contact-form-order-system' ),
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label'       => esc_html__( '無資料時顯示', 'soleman-contact-form-order-system' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => esc_html__( '編輯預覽或欄位空白時的替代文字。', 'soleman-contact-form-order-system' ),
			)
		);

		$this->add_control(
			'html_tag',
			array(
				'label'   => esc_html__( 'HTML 標籤', 'soleman-contact-form-order-system' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'span',
				'options' => array(
					'span'   => 'span',
					'div'    => 'div',
					'p'      => 'p',
					'h1'     => 'H1',
					'h2'     => 'H2',
					'h3'     => 'H3',
					'h4'     => 'H4',
					'strong' => 'strong',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( '樣式', 'soleman-contact-form-order-system' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( '文字顏色', 'soleman-contact-form-order-system' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .scfos-form-field' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .scfos-form-field',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$field_id  = isset( $settings['field_id'] ) ? (string) $settings['field_id'] : '';
		$fallback  = isset( $settings['fallback'] ) ? (string) $settings['fallback'] : '';
		$tag       = isset( $settings['html_tag'] ) ? (string) $settings['html_tag'] : 'span';
		$allowed   = array( 'span', 'div', 'p', 'h1', 'h2', 'h3', 'h4', 'strong' );

		if ( ! in_array( $tag, $allowed, true ) ) {
			$tag = 'span';
		}

		$value = Submission_Context::get( $field_id );

		if ( '' === $value ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				$value = $fallback !== '' ? $fallback : sprintf( '[%s]', $field_id ? $field_id : 'field' );
			} else {
				$value = $fallback;
			}
		}

		printf(
			'<%1$s class="scfos-form-field">%2$s</%1$s>',
			tag_escape( $tag ),
			esc_html( $value )
		);
	}
}
