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
    private $notices = [];
    private $default_delay = 7; // Days to wait before showing again after "Maybe Later"

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
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'wp_ajax_uimptr_handle_promo_action', array( $this, 'handle_promo_action' ) );
        
        // Initialize promotional notices after WordPress is ready for translations
        add_action( 'init', array( $this, 'init_notices' ) );
	}

    /**
     * Initialize promotional notices
     * Note: These notices only show when Big File Uploads is NOT already active
     */
    public function init_notices() {
        // Big File Form Uploads promotion - only shows if the user doesn't already have it
        $this->add_notice([
            'id' => 'big_file_form_uploads_promo',
            'title' => __('Complete Your File Management Setup', 'url-image-importer'),
            'message' => __('You\'re importing images efficiently with URL Image Importer! Now add Big File Form Uploads to handle large file uploads from your website visitors - the perfect complement to your media workflow for better user experience.', 'url-image-importer'),
            'type' => 'info',
            'buttons' => [
                'primary' => [
                    'text' => __('Learn More & Get Big File Form Uploads', 'url-image-importer'),
                    'action' => 'link',
                    'link' => 'https://infiniteuploads.com/big-file-uploads/',
                    'type' => 'primary'
                ],
                'maybe_later' => [
                    'text' => __('Maybe Later', 'url-image-importer'),
                    'action' => 'delay',
                ],
                'dismiss' => [
                    'text' => __('Not Interested', 'url-image-importer'),
                    'action' => 'dismiss',
                ]
            ]
        ]);
    }

    /**
     * Add a new promotion notice
     *
     * @param  array  $notice  Notice configuration
     * @return void
     */
    public function add_notice( $notice ) {
        $default = [
            'id'          => '',
            'title'       => '',
            'message'     => '',
            'link'        => '',
            'link_text'   => __( 'Learn More', 'url-image-importer' ),
            'delay_days'  => $this->default_delay,
            'type'        => 'info', // info, warning, error, success
            'dismissible' => true,
            'buttons'     => [
                'primary'     => [],
                'secondary'   => [],
                'dismiss'     => [
                    'text'   => __( 'Dismiss', 'url-image-importer' ),
                    'action' => 'dismiss',
                ],
                'maybe_later' => [
                    'text'   => __( 'Maybe Later', 'url-image-importer' ),
                    'action' => 'delay',
                ],
            ],
        ];

        $notice          = wp_parse_args( $notice, $default );
        $this->notices[] = $notice;
    }

	/**
	 * Display promotional notices.
	 */
	public function display_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Don't show promotional notices if Big File Uploads is already active
        if ( function_exists('is_plugin_active') && is_plugin_active('tuxedo-big-file-uploads/tuxedo_big_file_uploads.php') ) {
            return;
        }

        // Only show on relevant admin pages
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, [
            'media_page_url-image-importer',
            'plugins',
            'dashboard'
        ] ) ) {
            return;
        }

        foreach ( $this->notices as $notice ) {
            if ( $this->should_display_notice( $notice ) ) {
                $this->render_notice( $notice );
            }
        }

        $this->enqueue_scripts();
    }

    /**
     * Check if notice should be displayed
     */
    private function should_display_notice( $notice ) {
        $user_id       = get_current_user_id();
        $notice_status = get_user_meta( $user_id, 'uimptr_notice_' . $notice['id'], true );

        if ( $notice_status === 'dismissed' || $notice_status === 'visited' ) {
            return false;
        }

        if ( $notice_status && is_array( $notice_status ) ) {
            if ( $notice_status['action'] === 'delay' &&
                 time() < $notice_status['show_after'] ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render individual notice
     */
    private function render_notice( $notice ) {
        ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> uimptr-notice is-dismissible"
             data-notice-id="<?php echo esc_attr( $notice['id'] ); ?>">
            <button type="button" class="notice-dismiss" data-action="dismiss">
                <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'url-image-importer' ); ?></span>
            </button>

            <div class="uimptr-notice-content">
                <div class="uimptr-notice-icon">
                    <span class="dashicons dashicons-upload" style="font-size: 24px; color: #0073aa;"></span>
                </div>
                <div class="uimptr-notice-text">
                    <h3 style="margin-top: 0;"><?php echo esc_html( $notice['title'] ); ?></h3>
                    <p><?php echo wp_kses_post( $notice['message'] ); ?></p>

                    <p class="uimptr-notice-actions">
                        <?php foreach ( $notice['buttons'] as $type => $button ): ?>
                            <?php if ( empty( $button ) || $type === 'dismiss' ) continue; ?>
                            <?php
                            $class = ( isset( $button['type'] ) && $button['type'] === 'primary' ) 
                                ? 'button-primary' 
                                : 'button-secondary';
                            ?>
                            <button type="button"
                                    class="button <?php echo esc_attr( $class ); ?>"
                                    data-action="<?php echo esc_attr( $button['action'] ); ?>"
                                    <?php if ( ! empty( $button['link'] ) ): ?>
                                    data-link="<?php echo esc_attr( $button['link'] ); ?>"
                                    <?php endif; ?>
                            >
                                <?php echo esc_html( $button['text'] ); ?>
                            </button>
                        <?php endforeach; ?>
                    </p>
                </div>
            </div>
        </div>

        <style>
        .uimptr-notice-content {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding-top: 12px;
        }
        .uimptr-notice-icon {
            flex-shrink: 0;
            margin-top: 5px;
        }
        .uimptr-notice-text {
            flex-grow: 1;
        }
        .uimptr-notice-actions .button {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        </style>
        <?php
    }

    /**
     * Enqueue necessary scripts
     */
    private function enqueue_scripts() {
        // Only enqueue if we have notices to show
        if ( empty( $this->notices ) ) {
            return;
        }

        wp_enqueue_script(
            'uimptr-promo-notices',
            plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/promo-notices.js',
            [ 'jquery' ],
            UIMPTR_VERSION,
            true
        );

        wp_localize_script( 'uimptr-promo-notices', 'uimptrPromo', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'uimptr_promo_nonce' ),
        ] );
    }

	/**
	 * Handle AJAX promo action (dismiss, delay, or link click).
	 */
	public function handle_promo_action() {
        check_ajax_referer( 'uimptr_promo_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $notice_id = sanitize_text_field( $_POST['notice_id'] );
        $action    = sanitize_text_field( $_POST['action_type'] );
        $user_id   = get_current_user_id();

        switch ( $action ) {
            case 'dismiss':
                update_user_meta( $user_id, 'uimptr_notice_' . $notice_id, 'dismissed' );
                break;

            case 'link':
                update_user_meta( $user_id, 'uimptr_notice_' . $notice_id, 'visited' );
                break;

            case 'delay':
                // Find the notice to get delay_days
                $notice = array_filter( $this->notices, function ( $n ) use ( $notice_id ) {
                    return $n['id'] === $notice_id;
                } );

                $notice = reset( $notice );

                if ( ! $notice || ! isset( $notice['delay_days'] ) ) {
                    $delay_days = $this->default_delay; // Fallback to default if not set
                } else {
                    $delay_days = $notice['delay_days'];
                }

                $show_after = time() + ( DAY_IN_SECONDS * $delay_days );
                update_user_meta( $user_id, 'uimptr_notice_' . $notice_id, [
                    'action'     => 'delay',
                    'show_after' => $show_after,
                ] );
                break;
        }

        wp_send_json_success();
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
