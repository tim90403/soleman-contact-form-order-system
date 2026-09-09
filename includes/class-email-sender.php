<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Sender {

	/**
	 * @var Email_Template
	 */
	private $email_template;

	/**
	 * @var Submission_Repository
	 */
	private $submissions;

	/**
	 * @param Email_Template        $email_template Email template service.
	 * @param Submission_Repository $submissions Submission repository.
	 */
	public function __construct( Email_Template $email_template, Submission_Repository $submissions ) {
		$this->email_template = $email_template;
		$this->submissions    = $submissions;
	}

	/**
	 * Send confirmation email for a submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return true|\WP_Error
	 */
	public function send_confirmation( $submission_id ) {
		$submission_id = (int) $submission_id;

		if ( ! $this->email_template->is_enabled() ) {
			return new \WP_Error(
				'scfos_email_disabled',
				__( '信件版型尚未啟用，請先至「表單系統信件版型」啟用。', 'soleman-contact-form-order-system' )
			);
		}

		$fields = $this->submissions->get_fields( $submission_id );
		$to     = isset( $fields[ Plugin::FIELD_CONTACT_MAIL ] ) ? trim( (string) $fields[ Plugin::FIELD_CONTACT_MAIL ] ) : '';

		if ( '' === $to ) {
			return new \WP_Error(
				'scfos_missing_email',
				__( '找不到 contact_mail 欄位或信箱為空白，無法寄出信件。', 'soleman-contact-form-order-system' )
			);
		}

		if ( ! is_email( $to ) ) {
			return new \WP_Error(
				'scfos_invalid_email',
				sprintf(
					/* translators: %s: email address */
					__( 'contact_mail 信箱格式錯誤：%s', 'soleman-contact-form-order-system' ),
					$to
				)
			);
		}

		$template_id = $this->email_template->get_template_id();
		if ( ! $template_id ) {
			return new \WP_Error(
				'scfos_missing_template',
				__( '找不到信件版型。', 'soleman-contact-form-order-system' )
			);
		}

		$body = $this->render_template( $template_id, $fields, $submission_id );
		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new \WP_Error(
				'scfos_empty_template',
				__( '信件版型內容為空，請先以 Elementor 編輯版型。', 'soleman-contact-form-order-system' )
			);
		}

		Submission_Context::set( $fields, $submission_id );
		$subject = do_shortcode( $this->email_template->get_subject() );
		Submission_Context::clear();

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			return new \WP_Error(
				'scfos_mail_failed',
				__( '寄信失敗，請檢查網站郵件設定（例如 SMTP）。', 'soleman-contact-form-order-system' )
			);
		}

		return true;
	}

	/**
	 * @param int                   $template_id Template post ID.
	 * @param array<string, string> $fields Field values.
	 * @param int                   $submission_id Submission ID.
	 * @return string
	 */
	private function render_template( $template_id, array $fields, $submission_id ) {
		Submission_Context::set( $fields, $submission_id );

		$css  = '';
		$html = '';

		try {
			if ( class_exists( '\Elementor\Plugin' ) ) {
				$elementor = \Elementor\Plugin::$instance;

				// Ensure frontend mode for proper CSS generation.
				$elementor->frontend->register_styles();
				$elementor->frontend->enqueue_styles();

				$html = $elementor->frontend->get_builder_content_for_display( $template_id, true );

				$css_file = \Elementor\Core\Files\CSS\Post::create( $template_id );
				if ( $css_file ) {
					$css = $css_file->get_content();
				}
			}

			// Resolve shortcodes while submission context is still available.
			$html = do_shortcode( $html );
		} catch ( \Throwable $e ) {
			Submission_Context::clear();
			return '';
		}

		Submission_Context::clear();

		return $this->wrap_email_html( $html, $css );
	}

	/**
	 * @param string $content Inner HTML.
	 * @param string $css CSS content.
	 * @return string
	 */
	private function wrap_email_html( $content, $css ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( $site_name ); ?></title>
	<?php if ( $css ) : ?>
	<style type="text/css"><?php echo wp_strip_all_tags( $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
	<?php endif; ?>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}
