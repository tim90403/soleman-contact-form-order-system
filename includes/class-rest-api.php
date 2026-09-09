<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Api {

	const NAMESPACE = 'scfos/v1';

	/**
	 * @var Payment_Status
	 */
	private $payment_status;

	/**
	 * @var Submission_Repository
	 */
	private $submissions;

	/**
	 * @var Email_Sender
	 */
	private $email_sender;

	/**
	 * @param Payment_Status        $payment_status Payment status service.
	 * @param Submission_Repository $submissions Submission repository.
	 * @param Email_Sender          $email_sender Email sender.
	 */
	public function __construct( Payment_Status $payment_status, Submission_Repository $submissions, Email_Sender $email_sender ) {
		$this->payment_status = $payment_status;
		$this->submissions    = $submissions;
		$this->email_sender   = $email_sender;
	}

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/submissions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_submissions_extra' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/submissions/(?P<id>\d+)/payment',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'toggle_payment' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_submissions_extra( $request ) {
		$ids_raw = (string) $request->get_param( 'ids' );
		$ids     = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );

		$extras   = $this->submissions->get_extra_for_ids( $ids );
		$statuses = $this->payment_status->get_statuses( $ids );
		$items    = array();

		foreach ( $ids as $id ) {
			$extra    = isset( $extras[ $id ] ) ? $extras[ $id ] : array(
				'id'           => $id,
				'bank_code'    => '',
				'contact_mail' => '',
			);
			$items[] = array(
				'id'           => $id,
				'bank_code'    => $extra['bank_code'],
				'contact_mail' => $extra['contact_mail'],
				'is_confirmed' => ! empty( $statuses[ $id ] ),
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Toggle payment confirmation.
	 * Unconfirmed -> confirmed: send email, then mark confirmed.
	 * Confirmed -> unconfirmed: mark only, no email.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_payment( $request ) {
		$id = (int) $request['id'];

		if ( ! $this->submissions->exists( $id ) ) {
			return new \WP_Error(
				'scfos_not_found',
				__( '找不到此表單提交紀錄。', 'soleman-contact-form-order-system' ),
				array( 'status' => 404 )
			);
		}

		$is_confirmed = $this->payment_status->is_confirmed( $id );

		if ( $is_confirmed ) {
			$this->payment_status->set_confirmed( $id, false );

			return rest_ensure_response(
				array(
					'id'           => $id,
					'is_confirmed' => false,
					'email_sent'   => false,
					'message'      => __( '已復歸為尚未收款。', 'soleman-contact-form-order-system' ),
				)
			);
		}

		$send_result = $this->email_sender->send_confirmation( $id );
		if ( is_wp_error( $send_result ) ) {
			return $send_result;
		}

		$this->payment_status->set_confirmed( $id, true );

		return rest_ensure_response(
			array(
				'id'           => $id,
				'is_confirmed' => true,
				'email_sent'   => true,
				'message'      => __( '已確認收款並寄出信件。', 'soleman-contact-form-order-system' ),
			)
		);
	}
}
