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
				__( '信件尚未啟用，請先至「表單系統信件版型」啟用。', 'soleman-contact-form-order-system' )
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

		Submission_Context::set( $fields, $submission_id );
		$subject = do_shortcode( $this->email_template->get_subject() );
		$body    = $this->render_email_html( do_shortcode( $this->email_template->get_body() ) );
		Submission_Context::clear();

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new \WP_Error(
				'scfos_empty_template',
				__( '信件內文為空，請先至「表單系統信件版型」設定內文。', 'soleman-contact-form-order-system' )
			);
		}

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
	 * Wrap editable HTML body in the fixed email framework.
	 *
	 * @param string $content Resolved HTML body.
	 * @return string
	 */
	private function render_email_html( $content ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="color-scheme" content="light only" />
	<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;color:#222222;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;background-color:#ffffff;">
		<tr>
			<td align="center" style="padding:0;margin:0;">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:700px;border-collapse:collapse;margin:0 auto;background-color:#ffffff;">
					<tr>
						<td align="center" bgcolor="#1e73be" style="background-color:#1e73be;color:#ffffff;padding:48px 24px;text-align:center;font-size:36px;font-weight:700;line-height:1.3;">
							收款已確認
						</td>
					</tr>
					<tr>
						<td align="left" style="background-color:#ffffff;color:#222222;padding:32px 28px;font-size:16px;line-height:1.7;text-align:left;">
							<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}
