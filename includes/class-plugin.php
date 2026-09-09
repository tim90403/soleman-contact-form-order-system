<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	const FIELD_BANK_CODE    = 'bank_code';
	const FIELD_CONTACT_MAIL = 'contact_mail';
	const BANK_CODE_LABEL    = '匯款末5碼';
	const PAYMENT_LABEL      = '確認款項';

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Email_Template|null
	 */
	public $email_template;

	/**
	 * @var Payment_Status|null
	 */
	public $payment_status;

	/**
	 * @var Submission_Repository|null
	 */
	public $submissions;

	/**
	 * @var Email_Sender|null
	 */
	public $email_sender;

	/**
	 * @var Rest_Api|null
	 */
	public $rest_api;

	/**
	 * @var Submissions_UI|null
	 */
	public $submissions_ui;

	/**
	 * @var Shortcodes|null
	 */
	public $shortcodes;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->includes();

		if ( ! $this->dependencies_met() ) {
			add_action( 'admin_notices', array( $this, 'missing_dependencies_notice' ) );
			return;
		}

		$this->boot();
	}

	private function includes() {
		require_once SCFOS_PATH . 'includes/class-submission-context.php';
		require_once SCFOS_PATH . 'includes/class-submission-repository.php';
		require_once SCFOS_PATH . 'includes/class-payment-status.php';
		require_once SCFOS_PATH . 'includes/class-email-template.php';
		require_once SCFOS_PATH . 'includes/class-email-sender.php';
		require_once SCFOS_PATH . 'includes/class-rest-api.php';
		require_once SCFOS_PATH . 'includes/class-submissions-ui.php';
		require_once SCFOS_PATH . 'includes/class-shortcodes.php';
	}

	/**
	 * @return bool
	 */
	private function include_document_class() {
		if ( ! class_exists( '\Elementor\Core\DocumentTypes\PageBase' ) ) {
			return false;
		}

		require_once SCFOS_PATH . 'documents/class-email-template-document.php';
		return true;
	}

	/**
	 * @return bool
	 */
	private function include_widget_class() {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return false;
		}

		require_once SCFOS_PATH . 'widgets/class-form-field-widget.php';
		return true;
	}

	/**
	 * @return bool
	 */
	private function dependencies_met() {
		$has_elementor = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin', false );
		$has_pro       = defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin', false );

		return $has_elementor && $has_pro;
	}

	public function missing_dependencies_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Soleman Contact Form Order System 需要安裝並啟用 Elementor 與 PRO Elements。', 'soleman-contact-form-order-system' );
		echo '</p></div>';
	}

	private function boot() {
		$this->payment_status  = new Payment_Status();
		$this->submissions     = new Submission_Repository();
		$this->email_template  = new Email_Template();
		$this->email_sender    = new Email_Sender( $this->email_template, $this->submissions );
		$this->rest_api        = new Rest_Api( $this->payment_status, $this->submissions, $this->email_sender );
		$this->submissions_ui  = new Submissions_UI( $this->payment_status );
		$this->shortcodes      = new Shortcodes();

		$this->payment_status->hooks();
		$this->email_template->hooks();
		$this->rest_api->hooks();
		$this->submissions_ui->hooks();
		$this->shortcodes->hooks();

		add_action( 'elementor/documents/register', array( $this, 'register_document' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * @param \Elementor\Core\Documents_Manager $documents_manager Documents manager.
	 */
	public function register_document( $documents_manager ) {
		if ( ! $this->include_document_class() ) {
			return;
		}

		$documents_manager->register_document_type(
			Email_Template::DOCUMENT_TYPE,
			Documents\Email_Template_Document::get_class_full_name()
		);
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! $this->include_widget_class() ) {
			return;
		}

		$widgets_manager->register( new Widgets\Form_Field_Widget() );
	}

	public static function activate() {
		require_once SCFOS_PATH . 'includes/class-payment-status.php';
		Payment_Status::create_table();

		require_once SCFOS_PATH . 'includes/class-email-template.php';
		Email_Template::register_post_type_static();
		Email_Template::ensure_template_exists();

		flush_rewrite_rules();
	}
}
