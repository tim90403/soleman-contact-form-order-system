<?php
namespace Soleman_Contact_Form_Order_System;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Template {

	const CPT            = 'scfos_email_tpl';
	const DOCUMENT_TYPE  = 'scfos_email_template';
	const OPTION_ENABLED = 'scfos_email_enabled';
	const OPTION_SUBJECT = 'scfos_email_subject';
	const OPTION_POST_ID = 'scfos_email_template_id';
	const MENU_SLUG      = 'scfos-email-template';

	public function hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'elementor/utils/is_post_support', array( $this, 'filter_elementor_post_support' ), 10, 3 );
	}

	public function register_post_type() {
		self::register_post_type_static();
		self::ensure_template_exists();
	}

	public static function register_post_type_static() {
		if ( post_type_exists( self::CPT ) ) {
			return;
		}

		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( '表單系統信件版型', 'soleman-contact-form-order-system' ),
					'singular_name' => __( '信件版型', 'soleman-contact-form-order-system' ),
					'edit_item'     => __( '編輯信件版型', 'soleman-contact-form-order-system' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'elementor' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => true,
			)
		);

		add_post_type_support( self::CPT, 'elementor' );
	}

	/**
	 * @param bool   $is_supported Whether Elementor supports the post.
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function filter_elementor_post_support( $is_supported, $post_id, $post_type ) {
		if ( self::CPT === $post_type ) {
			return true;
		}
		return $is_supported;
	}

	/**
	 * Ensure a single Elementor-editable template post exists.
	 *
	 * @return int
	 */
	public static function ensure_template_exists() {
		$post_id = (int) get_option( self::OPTION_POST_ID, 0 );

		if ( $post_id && get_post( $post_id ) && self::CPT === get_post_type( $post_id ) ) {
			self::prepare_elementor_meta( $post_id );
			return $post_id;
		}

		$existing = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			$post_id = (int) $existing[0];
			update_option( self::OPTION_POST_ID, $post_id, false );
			self::prepare_elementor_meta( $post_id );
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => __( '收款確認信件版型', 'soleman-contact-form-order-system' ),
				'post_status' => 'publish',
				'post_type'   => self::CPT,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		update_option( self::OPTION_POST_ID, (int) $post_id, false );
		self::prepare_elementor_meta( (int) $post_id );

		if ( ! get_option( self::OPTION_SUBJECT ) ) {
			update_option( self::OPTION_SUBJECT, __( '收款確認通知', 'soleman-contact-form-order-system' ), false );
		}

		return (int) $post_id;
	}

	/**
	 * @param int $post_id Template post ID.
	 */
	public static function prepare_elementor_meta( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( ! get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		}

		if ( ! get_post_meta( $post_id, '_elementor_template_type', true ) ) {
			update_post_meta( $post_id, '_elementor_template_type', self::DOCUMENT_TYPE );
		}

		if ( ! get_post_meta( $post_id, '_wp_page_template', true ) ) {
			update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
		}
	}

	/**
	 * @return int
	 */
	public function get_template_id() {
		return self::ensure_template_exists();
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
		if ( '' === $subject ) {
			$subject = __( '收款確認通知', 'soleman-contact-form-order-system' );
		}
		return $subject;
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

		update_option( self::OPTION_ENABLED, $enabled, false );
		update_option( self::OPTION_SUBJECT, $subject, false );

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
			SCFOS_VERSION
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$template_id = $this->get_template_id();
		$edit_url    = '';

		if ( $template_id && class_exists( '\Elementor\Plugin' ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $template_id );
			if ( $document ) {
				$edit_url = $document->get_edit_url();
			} else {
				$edit_url = admin_url( 'post.php?post=' . $template_id . '&action=elementor' );
			}
		}

		settings_errors( 'scfos_email_settings' );
		?>
		<div class="wrap scfos-email-template-wrap">
			<h1><?php echo esc_html__( '表單系統信件版型', 'soleman-contact-form-order-system' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( '設定收款確認信件的主旨、啟用狀態，並以 Elementor 編輯信件版型。全站僅使用此一份啟用中的版型。', 'soleman-contact-form-order-system' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'scfos_save_email_settings', 'scfos_email_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( '啟用信件版型', 'soleman-contact-form-order-system' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="scfos_email_enabled" value="1" <?php checked( $this->is_enabled() ); ?> />
								<?php echo esc_html__( '啟用後，按下「確認收款」時會以此版型寄出信件。', 'soleman-contact-form-order-system' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="scfos_email_subject"><?php echo esc_html__( '信件主旨', 'soleman-contact-form-order-system' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="scfos_email_subject" name="scfos_email_subject" value="<?php echo esc_attr( $this->get_subject() ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Elementor 版型', 'soleman-contact-form-order-system' ); ?></th>
						<td>
							<?php if ( $edit_url ) : ?>
								<a class="button button-primary button-hero scfos-edit-elementor" href="<?php echo esc_url( $edit_url ); ?>">
									<?php echo esc_html__( '使用 Elementor 編輯信件版型', 'soleman-contact-form-order-system' ); ?>
								</a>
								<p class="description">
									<?php echo esc_html__( '可在版型中加入「表單動態欄位」元件，或使用 shortcode 帶入欄位值。', 'soleman-contact-form-order-system' ); ?>
									<br />
									<code>[scfos_field id="bank_code"]</code>
									<code>[scfos_field id="contact_mail"]</code>
									<code>[scfos_field id="欄位ID" fallback="無資料"]</code>
								</p>
							<?php else : ?>
								<p class="notice notice-warning inline"><?php echo esc_html__( '無法開啟 Elementor 編輯器，請確認 Elementor 已啟用。', 'soleman-contact-form-order-system' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( '儲存設定', 'soleman-contact-form-order-system' ) ); ?>
			</form>
		</div>
		<?php
	}
}
