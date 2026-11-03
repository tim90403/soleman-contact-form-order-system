<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Template {

	const OPTION_ENABLED = 'scfos_email_enabled';
	const OPTION_SUBJECT = 'scfos_email_subject';
	const OPTION_BODY    = 'scfos_email_body';
	const MENU_SLUG      = 'scfos-email-template';

	const DEFAULT_SUBJECT = '【臺中不動產經紀人協會】收款確認通知';

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === (string) get_option( self::OPTION_ENABLED, '0' );
	}

	/**
	 * @return string
	 */
	public function get_subject() {
		$subject = (string) get_option( self::OPTION_SUBJECT, '' );
		if ( '' === trim( $subject ) ) {
			return self::DEFAULT_SUBJECT;
		}
		return $subject;
	}

	/**
	 * @return string
	 */
	public function get_body() {
		$body = (string) get_option( self::OPTION_BODY, '' );
		if ( '' === trim( $body ) ) {
			return self::get_default_body();
		}
		return $body;
	}

	/**
	 * @return string
	 */
	public static function get_default_body() {
		return '<ul>
 	<li>課程名稱：[scfos_field id="course_name"]</li>
 	<li>姓名：[scfos_field id="name"]</li>
 	<li>出生年月日：[scfos_field id="field_9346bf7"]</li>
 	<li>身分證字號：[scfos_field id="user_id"]</li>
 	<li>戶籍地址：[scfos_field id="field_6904c67"]</li>
 	<li>通訊地址：[scfos_field id="field_1983256"]</li>
 	<li>手機：[scfos_field id="field_7344879"]</li>
 	<li>Email：[scfos_field id="contact_mail"]</li>
 	<li>公司電話：[scfos_field id="field_1dbc759"]</li>
 	<li>Line ID：[scfos_field id="field_1f984e4"]</li>
 	<li>最高學歷：[scfos_field id="field_38a5b95"]</li>
 	<li>公司名稱：[scfos_field id="field_84a4797"]</li>
 	<li>匯款末5碼：[scfos_field id="bank_code"]</li>
</ul>';
	}

	public function register_menu() {
		add_menu_page(
			__( '表單系統信件版型', 'soleman-contact-form-order-system' ),
			__( '表單系統信件版型', 'soleman-contact-form-order-system' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-email-alt',
			58
		);
	}

	public function handle_settings_save() {
		if ( ! isset( $_POST['scfos_email_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scfos_email_settings_nonce'] ) ), 'scfos_save_email_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$enabled = isset( $_POST['scfos_email_enabled'] ) ? '1' : '0';
		$subject = isset( $_POST['scfos_email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['scfos_email_subject'] ) ) : '';
		$body    = isset( $_POST['scfos_email_body'] ) ? wp_kses_post( wp_unslash( $_POST['scfos_email_body'] ) ) : '';

		update_option( self::OPTION_ENABLED, $enabled, false );
		update_option( self::OPTION_SUBJECT, $subject, false );
		update_option( self::OPTION_BODY, $body, false );

		add_settings_error(
			'scfos_email_settings',
			'scfos_email_settings_saved',
			__( '設定已儲存。', 'soleman-contact-form-order-system' ),
			'success'
		);
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'scfos-email-template-admin',
			SCFOS_URL . 'assets/css/email-template-admin.css',
			array(),
			scfos_asset_version( 'assets/css/email-template-admin.css' )
		);

		wp_enqueue_editor();
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		settings_errors( 'scfos_email_settings' );
		?>
		<div class="wrap scfos-email-template-wrap">
			<h1><?php echo esc_html__( '表單系統信件版型', 'soleman-contact-form-order-system' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( '設定收款確認信件的啟用狀態、Email 標題與內文。信件會套用固定 HTML 框架（含藍底「收款已確認」標題），內文支援 shortcode。', 'soleman-contact-form-order-system' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'scfos_save_email_settings', 'scfos_email_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( '啟用信件', 'soleman-contact-form-order-system' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="scfos_email_enabled" value="1" <?php checked( $this->is_enabled() ); ?> />
								<?php echo esc_html__( '啟用後，按下「確認收款」時會寄出信件。', 'soleman-contact-form-order-system' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="scfos_email_subject"><?php echo esc_html__( 'Email 標題', 'soleman-contact-form-order-system' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="scfos_email_subject" name="scfos_email_subject" value="<?php echo esc_attr( $this->get_subject() ); ?>" placeholder="<?php echo esc_attr( self::DEFAULT_SUBJECT ); ?>" />
							<p class="description"><?php echo esc_html__( '可選填。空白時使用預設：', 'soleman-contact-form-order-system' ); ?><?php echo esc_html( self::DEFAULT_SUBJECT ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="scfos_email_body"><?php echo esc_html__( '內文', 'soleman-contact-form-order-system' ); ?></label></th>
						<td>
							<?php
							wp_editor(
								$this->get_body(),
								'scfos_email_body',
								array(
									'textarea_name' => 'scfos_email_body',
									'textarea_rows' => 18,
									'media_buttons' => false,
									'teeny'         => false,
									'quicktags'     => true,
									'tinymce'       => array(
										'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo,code',
										'toolbar2' => '',
									),
								)
							);
							?>
							<p class="description">
								<?php echo esc_html__( '支援 HTML。可用 shortcode 帶入表單欄位：', 'soleman-contact-form-order-system' ); ?>
								<code>[scfos_field id="course_name"]</code>
								<code>[scfos_field id="bank_code"]</code>
								<code>[scfos_field id="欄位ID"]</code>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( '儲存設定', 'soleman-contact-form-order-system' ) ); ?>
			</form>
		</div>
		<?php
	}
}
