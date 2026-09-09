<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Submissions_UI {

	/**
	 * @var Payment_Status
	 */
	private $payment_status;

	/**
	 * @param Payment_Status $payment_status Payment status service.
	 */
	public function __construct( Payment_Status $payment_status ) {
		$this->payment_status = $payment_status;
	}

	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		if ( ! $this->is_submissions_page() ) {
			return;
		}

		wp_enqueue_style(
			'scfos-admin-submissions',
			SCFOS_URL . 'assets/css/admin-submissions.css',
			array(),
			SCFOS_VERSION
		);

		wp_enqueue_script(
			'scfos-admin-submissions',
			SCFOS_URL . 'assets/js/admin-submissions.js',
			array(),
			SCFOS_VERSION,
			true
		);

		wp_localize_script(
			'scfos-admin-submissions',
			'scfosAdmin',
			array(
				'restUrl'        => esc_url_raw( rest_url( Rest_Api::NAMESPACE . '/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'bankCodeLabel'  => Plugin::BANK_CODE_LABEL,
				'paymentLabel'   => Plugin::PAYMENT_LABEL,
				'bankCodeField'  => Plugin::FIELD_BANK_CODE,
				'confirmText'    => __( '確認收款', 'soleman-contact-form-order-system' ),
				'confirmedText'  => __( '已確認', 'soleman-contact-form-order-system' ),
				'loadingText'    => __( '處理中…', 'soleman-contact-form-order-system' ),
				'errorGeneric'   => __( '操作失敗，請稍後再試。', 'soleman-contact-form-order-system' ),
			)
		);
	}

	/**
	 * @return bool
	 */
	private function is_submissions_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return 'e-form-submissions' === $page;
	}
}
