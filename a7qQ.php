<?php
/**
 * Promotional Notices Handler
 *
 * @package UrlImageImporter\Admin
 */

namespace UrlImageImporter\Admin;

/**
 * Class PromoNotices
 *
 * Handles promotional notices and upgrade prompts.
 */
class PromoNotices {

	/**
	 * PromoNotices instance.
	 *
	 * @var PromoNotices
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return PromoNotices
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_promo_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_promo_scripts' ) );
		add_action( 'wp_ajax_uimptr_handle_promo_action', array( $this, 'handle_promo_action' ) );
	}

	/**
	 * Enqueue scripts for promotional notices.
	 */
	public function enqueue_promo_scripts( $hook ) {
		// Only load on our plugin pages
		if ( strpos( $hook, 'import-images-url' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'uimptr-promo-notices',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/promo-notices.js',
			array( 'jquery' ),
			'1.0.3',
			true
		);

		wp_localize_script(
			'uimptr-promo-notices',
			'uimptrPromo',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				   'nonce'   => wp_create_nonce( 'uimptr_promo_action' ),
			)
		);
	}

	/**
	 * Display promotional notices.
	 */
	public function display_promo_notices() {
		$screen = get_current_screen();
		
		// Only show on our plugin pages
		if ( ! $screen || strpos( $screen->id, 'import-images-url' ) === false ) {
			return;
		}

		// Check if user has capability
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Show upgrade notice
		$this->display_upgrade_notice();

		// Show feature highlight notice
		$this->display_feature_notice();
	}

	/**
	 * Display upgrade to Pro notice.
	 */
	private function display_upgrade_notice() {
		$notice_id = 'uimptr_upgrade_notice';
		$dismissed = get_user_option( $notice_id . '_dismissed', get_current_user_id() );

		// Show notice every 30 days after dismissal
		if ( $dismissed && ( time() - $dismissed ) < ( 30 * DAY_IN_SECONDS ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible uimptr-promo-notice" data-notice-id="<?php echo esc_attr( $notice_id ); ?>">
			<div style="display: flex; align-items: center; padding: 10px 0;">
				<div style="flex: 1;">
					<h3 style="margin: 0 0 10px 0;">
						🚀 <?php esc_html_e( 'Upgrade to Big File Form Uploads Pro!', 'url-image-importer' ); ?>
					</h3>
					<p style="margin: 0 0 10px 0;">
						<?php esc_html_e( 'Take your file uploads to the next level with advanced features:', 'url-image-importer' ); ?>
					</p>
					<ul style="margin: 0 0 10px 20px; list-style: disc;">
						<li><?php esc_html_e( 'Frontend form uploads with drag & drop', 'url-image-importer' ); ?></li>
						<li><?php esc_html_e( 'Direct uploads to cloud storage (AWS S3, Google Cloud, etc.)', 'url-image-importer' ); ?></li>
						<li><?php esc_html_e( 'Custom upload forms with shortcodes', 'url-image-importer' ); ?></li>
						<li><?php esc_html_e( 'Advanced file type restrictions', 'url-image-importer' ); ?></li>
						<li><?php esc_html_e( 'Email notifications and user management', 'url-image-importer' ); ?></li>
					</ul>
				</div>
				<div style="margin-left: 20px;">
					<button 
						type="button" 
						class="button button-primary button-hero" 
						data-action="upgrade" 
						data-link="https://infiniteuploads.com/big-file-form-uploads/?utm_source=url_image_importer&utm_medium=plugin&utm_campaign=upgrade_notice"
						style="white-space: nowrap;"
					>
						<?php esc_html_e( 'Learn More →', 'url-image-importer' ); ?>
					</button>
					<br><br>
					<button 
						type="button" 
						class="button" 
						data-action="dismiss"
						style="white-space: nowrap;"
					>
						<?php esc_html_e( 'Maybe Later', 'url-image-importer' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display feature highlight notice.
	 */
	private function display_feature_notice() {
		$notice_id = 'uimptr_feature_notice';
		$dismissed = get_user_option( $notice_id . '_dismissed', get_current_user_id() );

		// Show notice every 60 days after dismissal
		if ( $dismissed && ( time() - $dismissed ) < ( 60 * DAY_IN_SECONDS ) ) {
			return;
		}

		// Don't show if upgrade notice is showing
		$upgrade_dismissed = get_user_option( 'uimptr_upgrade_notice_dismissed', get_current_user_id() );
		if ( ! $upgrade_dismissed || ( time() - $upgrade_dismissed ) < ( 30 * DAY_IN_SECONDS ) ) {
			return;
		}

		?>
		<div class="notice notice-success is-dismissible uimptr-promo-notice" data-notice-id="<?php echo esc_attr( $notice_id ); ?>">
			<div style="display: flex; align-items: center; padding: 10px 0;">
				<div style="flex: 1;">
					<h3 style="margin: 0 0 10px 0;">
						💡 <?php esc_html_e( 'Did You Know?', 'url-image-importer' ); ?>
					</h3>
					<p style="margin: 0;">
						<?php esc_html_e( 'You can import hundreds of images at once using CSV files! Perfect for bulk migrations and product imports.', 'url-image-importer' ); ?>
						<a href="https://infiniteuploads.com/docs/url-image-importer/?utm_source=url_image_importer&utm_medium=plugin&utm_campaign=feature_notice" target="_blank">
							<?php esc_html_e( 'Learn more →', 'url-image-importer' ); ?>
						</a>
					</p>
				</div>
				<div style="margin-left: 20px;">
					<button 
						type="button" 
						class="button" 
						data-action="dismiss"
					>
						<?php esc_html_e( 'Got it!', 'url-image-importer' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX promo action (dismiss or upgrade click).
	 */
	public function handle_promo_action() {
		check_ajax_referer( 'uimptr_promo_action', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( $_POST['notice_id'] ) : '';
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';

		if ( empty( $notice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid notice ID' ) );
		}

		// Record dismissal with timestamp
		update_user_option( get_current_user_id(), $notice_id . '_dismissed', time() );

		// Track action type for analytics (optional)
		if ( 'upgrade' === $action_type ) {
			update_user_option( get_current_user_id(), $notice_id . '_clicked', time() );
		}

		wp_send_json_success( array( 'message' => 'Notice dismissed' ) );
	}

	/**
	 * Get Pro upgrade URL.
	 *
	 * @param string $source Source identifier for tracking.
	 * @return string
	 */
	public static function get_upgrade_url( $source = 'plugin' ) {
		return add_query_arg(
			array(
				'utm_source'   => 'url_image_importer',
				'utm_medium'   => 'plugin',
				'utm_campaign' => sanitize_key( $source ),
			),
			'https://infiniteuploads.com/big-file-form-uploads/'
		);
	}
}
