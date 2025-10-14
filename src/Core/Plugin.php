<?php
/**
 * Main Plugin Class
 *
 * @package UrlImageImporter
 */

namespace UrlImageImporter\Core;

use UrlImageImporter\Admin\AdminPage;
use UrlImageImporter\Importer\ImageImporter;
use UrlImageImporter\FileScan\FileScan;

/**
 * Main plugin class that bootstraps the entire plugin.
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.3';

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Admin page handler.
	 *
	 * @var AdminPage
	 */
	private $admin_page;

	/**
	 * Image importer handler.
	 *
	 * @var ImageImporter
	 */
	private $image_importer;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
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
	private function __construct() {
		$this->plugin_path = UIMPTR_PATH;
		$this->plugin_url  = \trailingslashit( \plugins_url( '', UIMPTR_PATH . 'url-image-importer.php' ) );
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 */
	private function init() {
		\add_action( 'init', array( $this, 'load_textdomain' ) );
		\add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		\add_filter( 'plugin_action_links_url-image-importer/url-image-importer.php', array( $this, 'plugin_action_links' ) );
		
		$this->init_components();
		$this->register_ajax_handlers();
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		$this->admin_page     = new AdminPage( $this->plugin_path, $this->plugin_url );
		$this->image_importer = new ImageImporter();
		
		// Initialize promotional notices
		\UrlImageImporter\Admin\PromoNotices::get_instance();
	}

	/**
	 * Register AJAX handlers.
	 */
	private function register_ajax_handlers() {
		\add_action( 'wp_ajax_uimptr_bfu_file_scan', array( $this, 'ajax_file_scan' ) );
		\add_action( 'wp_ajax_uimptr_subscribe_dismiss', array( $this, 'ajax_subscribe_dismiss' ) );
		\add_action( 'wp_ajax_uimptr_start_xml_import', array( $this, 'ajax_start_xml_import' ) );
		\add_action( 'wp_ajax_uimptr_get_import_progress', array( $this, 'ajax_get_import_progress' ) );
		\add_action( 'wp_ajax_uimptr_stop_import', array( $this, 'ajax_stop_import' ) );
		\add_action( 'wp_ajax_uimptr_start_csv_import', array( $this, 'ajax_start_csv_import' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		\load_plugin_textdomain( 'url-image-importer', false, dirname( \plugin_basename( $this->plugin_path . 'url-image-importer.php' ) ) . '/languages/' );
	}

	/**
	 * Add admin menu page.
	 */
	public function admin_menu() {
		\add_media_page(
			'Import Images from URLs',
			'Import Images',
			'upload_files',
			'import-images-url',
			array( $this->admin_page, 'render_page' )
		);
	}

	/**
	 * Enqueue admin styles and scripts.
	 */
	public function admin_styles() {
		if ( ! isset( $_GET['page'] ) || 'import-images-url' !== $_GET['page'] ) {
			return;
		}

		\wp_enqueue_style( 'uimptr-bootstrap', $this->plugin_url . 'assets/bootstrap/css/bootstrap.min.css', '', self::VERSION );
		\wp_enqueue_style( 'uimptr-styles', $this->plugin_url . 'assets/css/admin.css', '', self::VERSION );
		\wp_enqueue_script( 'uimptr-chartjs', $this->plugin_url . 'assets/js/Chart.min.js', '', self::VERSION, true );
		\wp_enqueue_script( 'bfu-bootstrap', $this->plugin_url . 'assets/bootstrap/js/bootstrap.bundle.min.js', '', self::VERSION, true );
		\wp_enqueue_script( 'uimptr-js', $this->plugin_url . 'assets/js/admin.js', '', self::VERSION, true );

		$this->localize_scripts();
	}

	/**
	 * Localize scripts with data.
	 */
	private function localize_scripts() {
		$data = array(
			'strings' => array(
				'leave_confirm'      => \esc_html__( 'Are you sure you want to leave this tab? The current bulk action will be canceled and you will need to continue where it left off later.', 'url-image-importer' ),
				'ajax_error'         => \esc_html__( 'Too many server errors. Please try again.', 'url-image-importer' ),
				'leave_confirmation' => \esc_html__( 'If you leave this page the sync will be interrupted and you will have to continue where you left off later.', 'url-image-importer' ),
			),
			'ajax_url'            => \admin_url( 'admin-ajax.php' ),
			   'local_types'         => \UrlImageImporter\FileScan\Utils::get_filetypes( true ),
			'default_upload_size' => \wp_max_upload_size(),
			'uimptr_nonce'        => \wp_create_nonce( 'ajax-nonce' ),
		);
		
		\wp_localize_script( 'uimptr-js', 'bfu_data', $data );
	}

	/**
	 * Add custom action links to plugin list.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$custom_links = array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				\esc_url( \admin_url( 'upload.php?page=import-images-url' ) ),
				\esc_html__( 'Settings', 'url-image-importer' )
			),
			'docs' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				\esc_url( 'https://infiniteuploads.com/docs/url-image-importer/?utm_source=url_image_importer&utm_medium=plugin&utm_campaign=plugin_links' ),
				\esc_html__( 'Docs', 'url-image-importer' )
			),
			'upgrade' => sprintf(
				'<a href="%s" target="_blank" style="color: #8D00B1; font-weight: bold;">%s</a>',
				\esc_url( \UrlImageImporter\Admin\PromoNotices::get_upgrade_url( 'plugin_links' ) ),
				\esc_html__( 'Go Pro', 'url-image-importer' )
			),
		);

		return array_merge( $custom_links, $links );
	}

	/**
	 * AJAX handler for file scanning.
	 */
	public function ajax_file_scan() {
		$nonce = isset( $_POST['js_nonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['js_nonce'] ) ) : '';
		if ( ! \wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
			\wp_die( 'Nonce Verification Failed!' );
		}

		$path           = \UrlImageImporter\FileScan\Utils::get_upload_dir_root();
		$remaining_dirs = array();

		if ( isset( $_POST['remaining_dirs'] ) && is_array( $_POST['remaining_dirs'] ) ) {
			foreach ( $_POST['remaining_dirs'] as $dir ) {
				$sanitized_dir = \sanitize_text_field( \wp_unslash( $dir ) );
				$realpath      = realpath( $path . $sanitized_dir );
				if ( $realpath && 0 === strpos( $realpath, $path ) ) {
					$remaining_dirs[] = $sanitized_dir;
				}
			}
		}

		$file_scan = new FileScan( $path, 20, $remaining_dirs );
		$file_scan->start();
		
		$file_count     = \number_format_i18n( $file_scan->get_total_files() );
		$file_size      = \size_format( $file_scan->get_total_size(), 2 );
		$remaining_dirs = $file_scan->get_paths_left();
		$is_done        = $file_scan->is_done();

		$data = compact( 'file_count', 'file_size', 'is_done', 'remaining_dirs' );
		\wp_send_json_success( $data );
	}

	/**
	 * AJAX handler for subscribe modal dismissal.
	 */
	public function ajax_subscribe_dismiss() {
		\update_user_option( \get_current_user_id(), 'bfu_subscribe_notice_dismissed', 1 );
		\wp_send_json_success();
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Get admin page instance.
	 *
	 * @return AdminPage
	 */
	public function get_admin_page() {
		return $this->admin_page;
	}

	/**
	 * AJAX handler to start XML import with progress tracking.
	 */
	public function ajax_start_xml_import() {
		// Set proper resource limits for production deployment
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			@ini_set( 'max_execution_time', 300 ); // 5 minutes max
			@ini_set( 'memory_limit', '512M' ); // Increase memory limit
		}
		
		// Verify nonce
		if ( ! \wp_verify_nonce( $_POST['nonce'], 'uimptr_ajax_nonce' ) ) {
			\wp_die( 'Security check failed' );
		}

		// Check user permissions
		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( 'Insufficient permissions' );
		}
		
		// Rate limiting: Check if user has started an import in the last 30 seconds
		$user_id = \get_current_user_id();
		$rate_limit_key = 'uimptr_rate_limit_' . $user_id;
		if ( \get_transient( $rate_limit_key ) ) {
			\wp_send_json_error( 'Please wait before starting another import.' );
		}
		\set_transient( $rate_limit_key, true, 30 ); // 30 second rate limit

		// Get stored media data from transient
		$stored_media_data = \get_transient( 'uimptr_xml_import_' . \get_current_user_id() );
		if ( ! $stored_media_data ) {
			\wp_send_json_error( 'Import session expired. Please upload the XML file again.' );
		}

		// Sanitize input
		$skip_existing = isset( $_POST['skip_existing'] ) ? \sanitize_text_field( $_POST['skip_existing'] ) : '1';
		
		// Initialize progress tracking with site-specific key BEFORE creating options
		$user_id = \get_current_user_id();
		$site_hash = substr( md5( \home_url() ), 0, 8 ); // Add site uniqueness
		$progress_key = 'uimptr_progress_' . $site_hash . '_' . $user_id;
		
		// Clean up any old stop file before starting new import (use system temp dir)
		$stop_file = sys_get_temp_dir() . '/uimptr_stop_' . $site_hash . '_' . $user_id . '.flag';
		if ( file_exists( $stop_file ) ) {
			@unlink( $stop_file );
		}
		
		$options = array(
			'skip_existing' => (bool) intval( $skip_existing ),
			'progress_key' => $progress_key, // Pass the key to the importer
		);
		
		$initial_progress = array(
			'total' => count( $stored_media_data ),
			'processed' => 0,
			'success' => 0,
			'failed' => 0,
			'skipped' => 0,
			'errors' => array(),
			'status' => 'in_progress',
			'stopped' => false
		);
		
		\set_transient( $progress_key, $initial_progress, 1800 ); // 30 minutes expiry (shorter for production)

		// Process the import with progress tracking
		$xml_importer = new \UrlImageImporter\Importer\WordPressXmlImporter();
		
		// Set up progress callback
		$progress_callback = function( $results, $status_text ) use ( $progress_key ) {
			// Get current progress to preserve the stopped flag
			$current_progress = \get_transient( $progress_key );
			$is_stopped = ( $current_progress && isset( $current_progress['stopped'] ) && $current_progress['stopped'] );
			
			$progress = array(
				'total' => $results['total'],
				'processed' => $results['processed'],
				'success' => $results['success'],
				'failed' => $results['failed'],
				'skipped' => $results['skipped'],
				'errors' => $results['errors'],
				'status' => $is_stopped ? 'stopped' : 'in_progress',
				'status_text' => $status_text,
				'stopped' => $is_stopped // Preserve the stopped flag!
			);
			\set_transient( $progress_key, $progress, 3600 );
		};

		$options['progress_callback'] = $progress_callback;
		
		try {
			$import_results = $xml_importer->process_xml_import( $stored_media_data, $options );
		} catch ( \Exception $e ) {
			// Log error and cleanup (only if WP_DEBUG is enabled)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'URL Image Importer Error: ' . $e->getMessage() );
			}
			\delete_transient( $progress_key );
			\delete_transient( 'uimptr_xml_import_' . \get_current_user_id() );
			\wp_send_json_error( 'Import failed: ' . $e->getMessage() );
		}
		
		// Check if import was stopped
		$current_progress = \get_transient( $progress_key );
		$was_stopped = $current_progress && isset( $current_progress['stopped'] ) && $current_progress['stopped'];
		
		// Update final progress
		$final_progress = array(
			'total' => $import_results['total'],
			'processed' => $import_results['processed'],
			'success' => $import_results['success'],
			'failed' => $import_results['failed'],
			'skipped' => $import_results['skipped'],
			'errors' => $import_results['errors'],
			'status' => $was_stopped ? 'stopped' : 'completed',
			'stopped' => $was_stopped
		);
		\set_transient( $progress_key, $final_progress, 3600 );
		
		// Clean up the media transient
		\delete_transient( 'uimptr_xml_import_' . \get_current_user_id() );

		\wp_send_json_success( $import_results );
	}

	/**
	 * AJAX handler to get import progress.
	 */
	public function ajax_get_import_progress() {
		// Check if user has an active import with site-specific key
		$user_id = \get_current_user_id();
		$site_hash = substr( md5( \home_url() ), 0, 8 );
		$progress_key = 'uimptr_progress_' . $site_hash . '_' . $user_id;
		$progress = \get_transient( $progress_key );
		
		if ( ! $progress ) {
			\wp_send_json_error( 'No active import found' );
		}
		
		\wp_send_json_success( $progress );
	}

	/**
	 * AJAX handler to stop import.
	 */
	public function ajax_stop_import() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'URL Image Importer: ajax_stop_import called' );
		}
		
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( $_POST['nonce'], 'uimptr_ajax_nonce' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'URL Image Importer: Nonce verification failed' );
			}
			\wp_send_json_error( 'Security check failed' );
		}

		// Check user permissions
		if ( ! \current_user_can( 'upload_files' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'URL Image Importer: Permission check failed' );
			}
			\wp_send_json_error( 'Insufficient permissions' );
		}

		$user_id = \get_current_user_id();
		$site_hash = substr( md5( \home_url() ), 0, 8 );
		$progress_key = 'uimptr_progress_' . $site_hash . '_' . $user_id;
		
		// USE FILE-BASED FLAG in system temp dir (bypasses ALL caching issues and S3)
		$stop_file = sys_get_temp_dir() . '/uimptr_stop_' . $site_hash . '_' . $user_id . '.flag';
		file_put_contents( $stop_file, time() );
		
		// Also update transient for progress display
		\delete_transient( $progress_key );
		\wp_cache_flush();
		
		$progress = array(
			'status' => 'stopped',
			'stopped' => true,
			'total' => 0,
			'processed' => 0,
			'success' => 0,
			'failed' => 0,
			'skipped' => 0,
			'status_text' => 'Import stopped by user'
		);
		
		\set_transient( $progress_key, $progress, 3600 );
		
		// Also write directly to options table as backup (bypasses object cache)
		global $wpdb;
		$option_name = '_transient_' . $progress_key;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
			ON DUPLICATE KEY UPDATE option_value = %s",
			$option_name,
			maybe_serialize( $progress ),
			maybe_serialize( $progress )
		) );
		
		// Force WordPress to flush any object cache multiple times to ensure it's written
		\wp_cache_flush();
		\wp_cache_flush(); // Flush twice for good measure
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'URL Image Importer: Stop signal set for key: ' . $progress_key );
			error_log( 'URL Image Importer: Stop file created: ' . $stop_file );
			error_log( 'URL Image Importer: Stop file exists: ' . ( file_exists( $stop_file ) ? 'YES' : 'NO' ) );
			// Verify transient was written
			$verify = \get_transient( $progress_key );
			error_log( 'URL Image Importer: Stop flag verified: ' . ( $verify && $verify['stopped'] ? 'YES' : 'NO' ) );
		}
		
		\wp_send_json_success( array( 
			'message' => 'Import stop signal sent',
			'progress_key' => $progress_key
		) );
	}

	/**
	 * AJAX handler to start CSV import with progress tracking.
	 */
	public function ajax_start_csv_import() {
		// Verify nonce
		if ( ! \wp_verify_nonce( $_POST['nonce'], 'uimptr_ajax_nonce' ) ) {
			\wp_send_json_error( 'Security check failed' );
		}

		// Check user permissions
		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( 'Insufficient permissions' );
		}

		// Rate limiting
		$user_id = \get_current_user_id();
		$rate_limit_key = 'uimptr_rate_limit_' . $user_id;
		if ( \get_transient( $rate_limit_key ) ) {
			\wp_send_json_error( 'Please wait before starting another import.' );
		}
		\set_transient( $rate_limit_key, true, 30 ); // 30 second rate limit

		// Get stored CSV data and mappings from transient
		$user_id   = \get_current_user_id();
		$site_hash = substr( md5( \home_url() ), 0, 8 );
		$csv_key   = 'uimptr_csv_' . $site_hash . '_' . $user_id;
		
		$stored_data = \get_transient( $csv_key );
		if ( ! $stored_data || ! isset( $stored_data['csv_data'] ) || ! isset( $stored_data['mappings'] ) ) {
			\wp_send_json_error( 'Import session expired. Please upload the CSV file again.' );
		}
		
		$stored_csv_data = $stored_data['csv_data'];
		$mappings = $stored_data['mappings'];

		// Sanitize input
		$skip_existing = isset( $_POST['skip_existing'] ) ? \sanitize_text_field( $_POST['skip_existing'] ) : '1';
		
		// Initialize progress tracking with site-specific key
		$user_id = \get_current_user_id();
		$site_hash = substr( md5( \home_url() ), 0, 8 );
		$progress_key = 'uimptr_progress_' . $site_hash . '_' . $user_id;
		
		// Clean up any old stop file before starting new import
		$stop_file = sys_get_temp_dir() . '/uimptr_stop_' . $site_hash . '_' . $user_id . '.flag';
		if ( file_exists( $stop_file ) ) {
			@unlink( $stop_file );
		}
		
		$options = array(
			'skip_existing' => (bool) intval( $skip_existing ),
			'progress_key' => $progress_key,
		);
		
		$initial_progress = array(
			'total' => count( $stored_csv_data ),
			'processed' => 0,
			'success' => 0,
			'failed' => 0,
			'skipped' => 0,
			'errors' => array(),
			'status' => 'in_progress',
			'stopped' => false
		);
		
		\set_transient( $progress_key, $initial_progress, 1800 );

		// Process the CSV import with progress tracking
		$csv_importer = new \UrlImageImporter\Importer\CsvImporter();
		
		// Set up progress callback
		$progress_callback = function( $results ) use ( $progress_key ) {
			// Get current progress to preserve the stopped flag
			$current_progress = \get_transient( $progress_key );
			$is_stopped = ( $current_progress && isset( $current_progress['stopped'] ) && $current_progress['stopped'] );
			
			$progress = array(
				'total' => $results['total'],
				'processed' => $results['processed'],
				'success' => $results['success'],
				'failed' => $results['failed'],
				'skipped' => $results['skipped'],
				'errors' => $results['errors'],
				'status' => $is_stopped ? 'stopped' : 'in_progress',
				'stopped' => $is_stopped
			);
			\set_transient( $progress_key, $progress, 3600 );
		};

		$options['progress_callback'] = $progress_callback;
		
		try {
			// Increase limits for large imports
			@ini_set( 'memory_limit', '512M' );
			@set_time_limit( 300 );
			
			$import_results = $csv_importer->process_csv_import( $stored_csv_data, $mappings, $options );
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'URL Image Importer Error: ' . $e->getMessage() );
			}
			\delete_transient( $progress_key );
			\delete_transient( $csv_key );
			\wp_send_json_error( 'Import failed: ' . $e->getMessage() );
		}
		
		// Check if import was stopped
		$current_progress = \get_transient( $progress_key );
		$was_stopped = $current_progress && isset( $current_progress['stopped'] ) && $current_progress['stopped'];
		
		// Update final progress
		$final_progress = array(
			'total' => $import_results['total'],
			'processed' => $import_results['processed'],
			'success' => $import_results['success'],
			'failed' => $import_results['failed'],
			'skipped' => $import_results['skipped'],
			'errors' => $import_results['errors'],
			'status' => $was_stopped ? 'stopped' : 'completed',
			'stopped' => $was_stopped
		);
		\set_transient( $progress_key, $final_progress, 3600 );
		
		// Clean up the CSV transient
		\delete_transient( $csv_key );

		\wp_send_json_success( $import_results );
	}
}