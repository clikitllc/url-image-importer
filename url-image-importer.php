<?php
/**
 *
 * Plugin Name: URL Image Importer
 * Description: A plugin to import multiple images into the WordPress Media Library from URLs.
 * Version: 1.0.3
 * Author: Infinite Uploads
 * Author URI: https://infiniteuploads.com
 * Text Domain: url-image-importer
 * License: GPL2
 *
 * @package UrlImageImporter
 * @version 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

error_log( 'URL Image Importer: Plugin file loaded' );
$upload_dir = wp_upload_dir();

define( 'UIMPTR_PATH', plugin_dir_path( __FILE__ ) );
define( 'UIMPTR_VERSION', 1.0 );
define( 'UPLOADBLOGSDIR', $upload_dir['path'] );

// Composer autoload for PSR-4 classes
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    error_log('URL Image Importer: Composer autoloader loaded successfully');
} else {
    error_log('URL Image Importer: ERROR - Composer autoloader not found at ' . __DIR__ . '/vendor/autoload.php');
}

// Legacy class aliases for compatibility
if (class_exists('UrlImageImporter\FileScan\FileScan')) {
    class_alias('UrlImageImporter\FileScan\FileScan', 'Big_File_Uploads_File_Scan');
}
if (class_exists('UrlImageImporter\BigFileUploads\ReviewNotice')) {
    class_alias('UrlImageImporter\BigFileUploads\ReviewNotice', 'Big_File_Uploads_Review_Notice');
}
/**
 * Plugin menu page callback.
 */
function uimptr_admin_menu() {
	add_media_page(
		'Import Images from URLs',
		'Import Images',
		'upload_files',
		'import-images-url',
		'uimptr_import_images_url_page'
	);
}
add_action( 'admin_menu', 'uimptr_admin_menu' );

/**
 * Enqueue scripts and styles
 */
function uimptr_admin_styles() {
	// Debug: Log page check
	error_log( 'uimptr_admin_styles called. Page: ' . ( isset( $_GET['page'] ) ? $_GET['page'] : 'not set' ) );
	
	if ( isset( $_GET['page'] ) && 'import-images-url' === $_GET['page'] ) {
		error_log( 'Loading UIMPTR scripts on import-images-url page' );
		wp_enqueue_style( 'uimptr-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), '', UIMPTR_VERSION );
		wp_enqueue_style( 'uimptr-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), '', UIMPTR_VERSION );
		wp_enqueue_script( 'uimptr-chartjs', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), '', UIMPTR_VERSION, true );
		wp_enqueue_script( 'bfu-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), '', UIMPTR_VERSION, true );
		wp_enqueue_script( 'uimptr-js', plugins_url( 'assets/js/admin.js', __FILE__ ), '', UIMPTR_VERSION, true );
	}
	$data                            = array();
		$data['strings']             = array(
			'leave_confirm'      => esc_html__( 'Are you sure you want to leave this tab? The current bulk action will be canceled and you will need to continue where it left off later.', 'url-image-importer' ),
			'ajax_error'         => esc_html__( 'Too many server errors. Please try again.', 'url-image-importer' ),
			'leave_confirmation' => esc_html__( 'If you leave this page the sync will be interrupted and you will have to continue where you left off later.', 'url-image-importer' ),
		);
		$data['ajax_url']            = admin_url( 'admin-ajax.php' );
		$data['local_types']         = uimptr_get_filetypes( true );
		$data['default_upload_size'] = wp_max_upload_size();
		$data['uimptr_nonce']        = wp_create_nonce( 'ajax-nonce' );
		wp_localize_script( 'uimptr-js', 'bfu_data', $data );
		
		// Add AJAX data for import functionality
		$ajax_url = admin_url( 'admin-ajax.php' );
		$uimptr_ajax_data = array(
			'ajax_url' => $ajax_url,
			'nonce' => wp_create_nonce( 'uimptr_ajax' )
		);
		wp_localize_script( 'uimptr-js', 'uimptr_ajax', $uimptr_ajax_data );
		error_log( 'URL Image Importer: AJAX URL set to: ' . $ajax_url );
		
		// Add inline script to verify AJAX object is loaded
		$inline_script = '
		jQuery(document).ready(function($) {
			console.log("URL Image Importer scripts loaded");
			console.log("uimptr_ajax object available:", typeof uimptr_ajax !== "undefined");
			if (typeof uimptr_ajax !== "undefined") {
				console.log("uimptr_ajax contents:", uimptr_ajax);
			} else {
				console.error("ERROR: uimptr_ajax object is not defined!");
			}
		});
		';
		wp_add_inline_script( 'uimptr-js', $inline_script );
}
add_action( 'admin_enqueue_scripts', 'uimptr_admin_styles' );

/**
 * Handle XML file import
 */
function uimptr_handle_xml_import() {
	if ( !isset( $_FILES['xml_file'] ) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK ) {
		return array( 'errors' => 1, 'messages' => array( 'No file uploaded or upload error occurred.' ) );
	}

	$uploaded_file = $_FILES['xml_file'];
	$file_extension = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );

	if ( $file_extension !== 'xml' ) {
		return array( 'errors' => 1, 'messages' => array( 'Please upload a valid XML file.' ) );
	}

	// Move uploaded file to temporary location
	$temp_file = wp_tempnam( $uploaded_file['name'] );
	if ( !move_uploaded_file( $uploaded_file['tmp_name'], $temp_file ) ) {
		return array( 'errors' => 1, 'messages' => array( 'Failed to process uploaded file.' ) );
	}

	// Import options
	$options = array(
		'images_only' => isset( $_POST['images_only'] ),
		'force_reimport' => isset( $_POST['force_reimport'] )
	);

	// Process XML import
	$xml_importer = new \UrlImageImporter\Importer\WordPressXmlImporter();
	$results = $xml_importer->process_xml_import( $temp_file, $options );

	// Clean up temporary file
	unlink( $temp_file );

	return $results;
}

/**
 * Import Image Form HTML
 */
function uimptr_import_images_url_page() {
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html( 'You do not have sufficient permissions to access this page.' ) );
	}

	$results = array();

	// Handle URL Import
	if ( isset( $_POST['image_urls'] ) ) {
		check_admin_referer( 'uimptr-form-field', '_wpnonce_select_form' );
		$image_urls = array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['image_urls'] ) ) ) );

		foreach ( $image_urls as $image_url ) {
			if ( filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
				$attachment_id = uimptr_import_image_from_url( $image_url );

				if ( is_wp_error( $attachment_id ) ) {
					$results[] = '<div class="error"><p>' . esc_html( $attachment_id->get_error_message() ) . ' (URL: ' . esc_url( $image_url ) . ')</p></div>';
				} else {
					$results[] = '<div class="updated"><p>Image imported successfully from ' . esc_url( $image_url ) . '! <a href="' . esc_url( get_edit_post_link( $attachment_id ) ) . '">Edit Image</a></p></div>';
				}
			} else {
				$results[] = '<div class="error"><p>Invalid URL: ' . esc_url( $image_url ) . '</p></div>';
			}
		}
	}

	// Handle XML Import
	if ( isset( $_POST['xml_import_submit'] ) && isset( $_POST['_wpnonce_xml_import'] ) && wp_verify_nonce( $_POST['_wpnonce_xml_import'], 'uimptr-xml-import' ) ) {
		$xml_results = uimptr_handle_xml_import();
		if ( $xml_results ) {
			$results[] = '<div class="updated"><p><strong>XML Import Results:</strong><br/>' .
				'Imported: ' . intval( $xml_results['imported'] ) . ' images<br/>' .
				'Skipped: ' . intval( $xml_results['skipped'] ) . ' items<br/>' .
				'Errors: ' . intval( $xml_results['errors'] ) . ' items</p></div>';
			
			if ( !empty( $xml_results['messages'] ) && $xml_results['errors'] > 0 ) {
				$error_messages = array_filter( $xml_results['messages'], function( $msg ) {
					return strpos( $msg, 'Failed' ) !== false || strpos( $msg, 'Error' ) !== false;
				});
				if ( !empty( $error_messages ) ) {
					$results[] = '<div class="error"><p><strong>Import Errors:</strong></p><ul>';
					foreach ( array_slice( $error_messages, 0, 5 ) as $message ) {
						$results[] = '<li>' . esc_html( $message ) . '</li>';
					}
					if ( count( $error_messages ) > 5 ) {
						$results[] = '<li>... and ' . ( count( $error_messages ) - 5 ) . ' more errors</li>';
					}
					$results[] = '</ul></div>';
				}
			}
		}
	}

	if ( !empty( $results ) ) {
		$allowed_tags = array(
			'div' => array(
				'class' => array(),
			),
			'p'   => array(),
			'a'   => array(
				'href' => array(),
			),
		);
		foreach ( $results as $result ) {
			echo wp_kses( $result, $allowed_tags );
		}
	}

	?>
	<div id="container" class="wrap">
		<h1>
			<img src="<?php echo esc_url( plugins_url( 'assets/img/infiniteuploads.svg', __FILE__ ) ); ?>" height="50"> 
			<?php echo esc_html( 'URL Image Importer' ); ?>
		</h1>
		
	</div>
	
	<!-- Import Method Tabs -->
	<div class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<a href="#url-import" class="nav-tab nav-tab-active" id="url-tab">URL Import</a>
		<a href="#xml-import" class="nav-tab" id="xml-tab">WordPress XML Import</a>
		<a href="#csv-import" class="nav-tab" id="csv-tab">CSV Import</a>
	</div>

	<!-- URL Import Form -->
	<div id="url-import" class="import-method">
		<form method="post">
			<?php wp_nonce_field( 'uimptr-form-field', '_wpnonce_select_form' ); ?>
			<div class="card upload">
				<div class="card-header">
					<div class="d-flex align-items-center">
						<h5 class="m-0 mr-auto p-0"><?php echo esc_html( 'Image URLs (one per line)' ); ?></h5>
					</div>
				</div>
				<div class="card-body p-md-1">
					<div class="row justify-content-center mb-3 mt-3">
						<div class="col text-center">
							<textarea name="image_urls" id="image_urls" class="large-text" rows="10"></textarea>
						</div>
					</div>
					<div class="row mb-3">
						<div class="col text-center">
							<label style="display: inline-flex; align-items: center; font-size: 14px; cursor: pointer;">
								<input type="checkbox" name="url_preserve_dates" id="url_preserve_dates" style="margin-right: 8px;">
								<?php esc_html_e( 'Preserve original dates (if available) instead of importing as current date', 'url-image-importer' ); ?>
							</label>
						</div>
					</div>
					<div class="row justify-right-right mb-2 btn-row">
						<div class="col-md-12 col-md-5 col-xl-4 text-center">
							<button type="button" id="start-url-import" class="button button-primary"><?php esc_html_e( 'Import Images from URLs', 'url-image-importer' ); ?></button>
						</div>
					</div>
					
					<!-- Progress Bar for URL Import -->
					<div id="url-progress-container" style="display: none; margin-top: 20px;">
						<div class="progress-info">
							<span id="url-progress-text">Starting import...</span>
							<span id="url-progress-count" style="float: right;">0/0</span>
						</div>
						<div class="progress-bar-container" style="background: #f1f1f1; border-radius: 4px; height: 20px; margin: 10px 0;">
							<div id="url-progress-bar" style="background: #0073aa; height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s;"></div>
						</div>
						<div class="progress-stats" style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 14px;">
							<span style="color: #28a745; margin-right: 15px;"><strong>✓ Success:</strong> <span id="url-success-count">0</span></span>
							<span style="color: #dc3545; margin-right: 15px;"><strong>✗ Failed:</strong> <span id="url-failed-count">0</span></span>
							<span style="color: #6c757d;"><strong>⊘ Skipped:</strong> <span id="url-skipped-count">0</span></span>
						</div>
						<div class="progress-actions">
							<button type="button" id="cancel-url-import" class="button button-secondary" style="color: #d63384; border-color: #d63384;" title="<?php esc_attr_e( 'Stop the import process immediately', 'url-image-importer' ); ?>"><?php esc_html_e( '⏹ Stop Import', 'url-image-importer' ); ?></button>
						</div>
						<div id="url-import-results" style="margin-top: 15px;"></div>
					</div>
				</div>
			</div>
		</form>
	</div>

	<!-- XML Import Form -->
	<div id="xml-import" class="import-method" style="display: none;">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'uimptr-xml-import', '_wpnonce_xml_import' ); ?>
			<div class="card upload">
				<div class="card-header">
					<div class="d-flex align-items-center">
						<h5 class="m-0 mr-auto p-0"><?php echo esc_html( 'WordPress XML Export File' ); ?></h5>
					</div>
				</div>
				<div class="card-body p-md-1">
					<div class="row justify-content-center mb-3 mt-3">
						<div class="col">
							<p><?php esc_html_e( 'Upload a WordPress XML export file to import images from another WordPress site.', 'url-image-importer' ); ?></p>
							<input type="file" name="xml_file" id="xml_file" accept=".xml" required />
							<p class="description">
								<?php esc_html_e( 'Select a .xml file exported from WordPress (Tools → Export → All content).', 'url-image-importer' ); ?>
							</p>
						</div>
					</div>
					
					<div class="row mb-3">
						<div class="col">
							<h6><?php esc_html_e( 'Import Options:', 'url-image-importer' ); ?></h6>
							<label>
								<input type="checkbox" name="images_only" value="1" checked />
								<?php esc_html_e( 'Import images only (skip other attachment types)', 'url-image-importer' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="force_reimport" value="1" />
								<?php esc_html_e( 'Force re-import (import even if files already exist)', 'url-image-importer' ); ?>
							</label><br />
							<label>
								<input type="checkbox" name="xml_preserve_dates" id="xml_preserve_dates" />
								<?php esc_html_e( 'Preserve original dates instead of importing as current date', 'url-image-importer' ); ?>
							</label>
						</div>
					</div>
					
					<div class="row justify-right-right mb-2 btn-row">
						<div class="col-md-12 col-md-5 col-xl-4 text-center">
							<button type="button" id="start-xml-import" class="button button-primary"><?php esc_html_e( 'Import from XML File', 'url-image-importer' ); ?></button>
						</div>
					</div>
					
					<!-- Progress Bar for XML Import -->
					<div id="xml-progress-container" style="display: none; margin-top: 20px;">
						<div class="progress-info">
							<span id="xml-progress-text">Processing XML file...</span>
							<span id="xml-progress-count" style="float: right;">0/0</span>
						</div>
						<div class="progress-bar-container" style="background: #f1f1f1; border-radius: 4px; height: 20px; margin: 10px 0;">
							<div id="xml-progress-bar" style="background: #0073aa; height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s;"></div>
						</div>
						<div class="progress-stats" style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 14px;">
							<span style="color: #28a745; margin-right: 15px;"><strong>✓ Success:</strong> <span id="xml-success-count">0</span></span>
							<span style="color: #dc3545; margin-right: 15px;"><strong>✗ Failed:</strong> <span id="xml-failed-count">0</span></span>
							<span style="color: #6c757d;"><strong>⊘ Skipped:</strong> <span id="xml-skipped-count">0</span></span>
						</div>
						<div class="progress-actions">
							<button type="button" id="cancel-xml-import" class="button button-secondary" style="color: #d63384; border-color: #d63384;" title="<?php esc_attr_e( 'Stop the import process immediately', 'url-image-importer' ); ?>"><?php esc_html_e( '⏹ Stop Import', 'url-image-importer' ); ?></button>
						</div>
						<div id="xml-import-results" style="margin-top: 15px;"></div>
					</div>
				</div>
			</div>
		</form>
	</div>

	<!-- CSV Import Section -->
	<div id="csv-import" class="import-method" style="display: none;">
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'uimptr-csv-import', '_wpnonce_csv_import' ); ?>
			
			<div class="container">
				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<label for="csv_file"><?php esc_html_e( 'Select CSV File:', 'url-image-importer' ); ?></label>
							<input type="file" id="csv_file" name="csv_file" accept=".csv" class="form-control" required>
							<small class="form-text text-muted">
								<?php esc_html_e( 'Upload a CSV file containing image URLs and metadata.', 'url-image-importer' ); ?>
							</small>
							<div style="text-align: center; margin-top: 10px;">
								<a href="#" id="download-sample-csv" class="button button-secondary"><?php esc_html_e( 'Download Sample CSV', 'url-image-importer' ); ?></a>
							</div>
						</div>
					</div>
				</div>
				
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label>
								<input type="checkbox" name="csv_images_only" value="1">
								<?php esc_html_e( 'Import Images Only', 'url-image-importer' ); ?>
							</label>
							<small class="form-text text-muted"><?php esc_html_e( 'Skip non-image files during import', 'url-image-importer' ); ?></small>
						</div>
						<div class="form-group">
							<label>
								<input type="checkbox" name="csv_preserve_dates" id="csv_preserve_dates">
								<?php esc_html_e( 'Preserve Original Dates', 'url-image-importer' ); ?>
							</label>
							<small class="form-text text-muted"><?php esc_html_e( 'Keep original dates instead of importing as current date', 'url-image-importer' ); ?></small>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label>
								<input type="checkbox" name="csv_force_reimport" value="1">
								<?php esc_html_e( 'Force Re-import', 'url-image-importer' ); ?>
							</label>
							<small class="form-text text-muted"><?php esc_html_e( 'Re-import files that already exist', 'url-image-importer' ); ?></small>
						</div>
					</div>
				</div>
				
				<div class="row justify-right-right mb-2 btn-row">
					<div class="col-md-12 col-md-5 col-xl-4 text-center">
						<button type="button" id="start-csv-import" class="button button-primary"><?php esc_html_e( 'Import from CSV File', 'url-image-importer' ); ?></button>
					</div>
				</div>
				
				<!-- Progress Bar for CSV Import -->
				<div id="csv-progress-container" style="display: none; margin-top: 20px;">
					<div class="progress-wrapper">
						<div class="progress-info">
							<span id="csv-progress-text">Starting import...</span>
							<span id="csv-progress-count" style="float: right;">0/0</span>
						</div>
						<div class="progress-bar-container">
							<div class="progress-bar" id="csv-progress-bar" style="width: 0%"></div>
						</div>
						<div class="progress-stats" style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 14px;">
							<span style="color: #28a745; margin-right: 15px;"><strong>✓ Success:</strong> <span id="csv-success-count">0</span></span>
							<span style="color: #dc3545; margin-right: 15px;"><strong>✗ Failed:</strong> <span id="csv-failed-count">0</span></span>
							<span style="color: #6c757d;"><strong>⊘ Skipped:</strong> <span id="csv-skipped-count">0</span></span>
						</div>
						<div class="progress-actions">
							<button type="button" id="cancel-csv-import" class="button button-secondary" style="color: #d63384; border-color: #d63384;" title="<?php esc_attr_e( 'Stop the import process immediately', 'url-image-importer' ); ?>"><?php esc_html_e( '⏹ Stop Import', 'url-image-importer' ); ?></button>
						</div>
						<div id="csv-import-results" style="margin-top: 15px;"></div>
					</div>
				</div>
			</div>
		</form>
	</div>

	<!-- Import Preview Modal -->
	<div id="import-preview-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
		<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; width: 90%; max-width: 800px; max-height: 80%; border-radius: 8px; overflow: hidden;">
			<div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f9f9f9;">
				<h3 style="margin: 0; display: inline-block;">Import Preview</h3>
				<button type="button" id="close-preview" style="float: right; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
			</div>
			<div id="preview-content" style="padding: 20px; max-height: 400px; overflow-y: auto;">
				<!-- Preview content will be loaded here -->
			</div>
			<div style="padding: 20px; border-top: 1px solid #ddd; text-align: right; background: #f9f9f9;">
				<button type="button" id="cancel-import-preview" class="button">Cancel</button>
				<button type="button" id="confirm-import" class="button button-primary" style="margin-left: 10px;">Import Selected Items</button>
			</div>
		</div>
	</div>

	<style>
	.spinner {
		border: 4px solid #f3f3f3;
		border-top: 4px solid #0073aa;
		border-radius: 50%;
		width: 30px;
		height: 30px;
		animation: spin 2s linear infinite;
		margin: 0 auto;
	}
	
	@keyframes spin {
		0% { transform: rotate(0deg); }
		100% { transform: rotate(360deg); }
	}
	
	#import-preview-modal .notice {
		margin: 10px 0;
	}
	
	.url-checkbox, .xml-checkbox, .csv-checkbox {
		transform: scale(1.2);
		margin-right: 10px !important;
	}
	</style>

	<script>
	jQuery(document).ready(function($) {
		// Define AJAX data if not already available
		if (typeof uimptr_ajax === 'undefined') {
			window.uimptr_ajax = {
				ajax_url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				nonce: '<?php echo wp_create_nonce( 'uimptr_ajax' ); ?>'
			};
		}
		
		console.log('uimptr_ajax initialized:', uimptr_ajax);
		
		var importCanceled = false;
		var currentImportType = '';
		
		// Tab switching
		$('.nav-tab').click(function(e) {
			e.preventDefault();
			
			// Remove active class from all tabs
			$('.nav-tab').removeClass('nav-tab-active');
			$('.import-method').hide();
			
			// Add active class to clicked tab
			$(this).addClass('nav-tab-active');
			
			// Show corresponding form
			var target = $(this).attr('href');
			$(target).show();
		});
		
		// URL Import with Preview
		$('#start-url-import').click(function() {
			var urls = $('#image_urls').val().trim();
			if (!urls) {
				alert('<?php esc_html_e( 'Please enter at least one image URL.', 'url-image-importer' ); ?>');
				return;
			}
			
			var urlList = urls.split('\n').filter(function(url) {
				return url.trim() !== '';
			}).map(function(url) {
				return url.trim();
			});
			
			// Show preview first
			showUrlPreview(urlList);
		});
		
		// XML Import with Preview
		$('#start-xml-import').click(function() {
			console.log('XML Import button clicked');
			var xmlFile = $('#xml_file')[0].files[0];
			if (!xmlFile) {
				alert('<?php esc_html_e( 'Please select an XML file to import.', 'url-image-importer' ); ?>');
				return;
			}
			
			console.log('XML File selected:', xmlFile.name, xmlFile.size, 'bytes');
			// Show XML preview
			showXmlPreview(xmlFile);
		});
		
		// Global variables for tracking active imports
		var activeImportBatchId = null;
		var activeImportType = null;
		var previewData = null;
		
		// Preview modal handlers
		$('#close-preview, #cancel-import-preview').click(function() {
			$('#import-preview-modal').hide();
			previewData = null;
		});
		
		$('#confirm-import').click(function() {
			if (!previewData) return;
			
			var selectedItems = [];
			
			if (previewData.type === 'url') {
				$('.url-checkbox:checked').each(function() {
					selectedItems.push({
						url: $(this).data('url'),
						metadata: {}
					});
				});
			} else if (previewData.type === 'xml') {
				$('.xml-checkbox:checked').each(function() {
					var index = $(this).data('index');
					selectedItems.push(previewData.urls[index]);
				});
			} else if (previewData.type === 'csv') {
				$('.csv-checkbox:checked').each(function() {
					var index = $(this).data('index');
					selectedItems.push(previewData.urls[index]);
				});
			}
			
			if (selectedItems.length === 0) {
				alert('<?php esc_html_e( 'Please select at least one item to import.', 'url-image-importer' ); ?>');
				return;
			}
			
			// Hide preview and start import
			$('#import-preview-modal').hide();
			
			// Generate batch ID
			activeImportBatchId = previewData.type + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
			activeImportType = previewData.type;
			
			// Show appropriate progress container and reset stats
			resetStats(previewData.type);
			$('#' + previewData.type + '-progress-container').show();
			$('#start-' + previewData.type + '-import').prop('disabled', true);
			
			// Start the import
			updateProgress(previewData.type, 0, selectedItems.length, '<?php esc_html_e( 'Starting import...', 'url-image-importer' ); ?>');
			processBatchImport(activeImportBatchId, selectedItems, 0, previewData.type);
			
			previewData = null;
		});
		
		// Cancel buttons with confirmation
		$('#cancel-url-import').click(function() {
			if (!confirm('<?php esc_html_e( 'Are you sure you want to stop the import? This will cancel all remaining imports.', 'url-image-importer' ); ?>')) {
				return;
			}
			
			if (activeImportBatchId && activeImportType === 'url') {
				stopImport(activeImportBatchId, 'url');
			} else {
				cancelImport('url');
			}
		});
		
		$('#cancel-xml-import').click(function() {
			if (!confirm('<?php esc_html_e( 'Are you sure you want to stop the import? This will cancel all remaining imports.', 'url-image-importer' ); ?>')) {
				return;
			}
			
			if (activeImportBatchId && activeImportType === 'xml') {
				stopImport(activeImportBatchId, 'xml');
			} else {
				cancelImport('xml');
			}
		});
		
		function startUrlImport(urls) {
			resetStats('url');
			$('#url-progress-container').show();
			$('#start-url-import').prop('disabled', true);
			
			// Generate batch ID and set active import tracking
			activeImportBatchId = 'url-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
			activeImportType = 'url';
			
			updateProgress('url', 0, urls.length, '<?php esc_html_e( 'Starting URL import...', 'url-image-importer' ); ?>');
			
			// Convert URLs to the format expected by batch processor
			var urlsData = urls.map(function(url) {
				return { url: url, metadata: {} };
			});
			
			// Use the batch import system for consistent stop functionality
			processBatchImport(activeImportBatchId, urlsData, 0, 'url');
		}
		
		function processBatchImport(batchId, urls, startIndex, type) {
			// Check if we should preserve dates based on the checkbox for this import type
			var preserveDates = $('#' + type + '_preserve_dates').is(':checked');
			
			// Check force reimport setting based on import type
			var forceReimport = false;
			if (type === 'xml') {
				forceReimport = $('#xml-import input[name="force_reimport"]:checked').length > 0;
			} else if (type === 'csv') {
				forceReimport = $('#csv-import input[name="csv_force_reimport"]:checked').length > 0;
			}
			// URL imports don't have force_reimport option, so it stays false
			
			$.ajax({
				url: uimptr_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'uimptr_batch_import',
					nonce: uimptr_ajax.nonce,
					batch_id: batchId,
					start_index: startIndex,
					batch_size: 3, // Smaller batch for URL imports
					urls: JSON.stringify(urls),
					preserve_dates: preserveDates,
					force_reimport: forceReimport
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						
						updateProgress(type, data.processed, data.total, '<?php esc_html_e( 'Processed:', 'url-image-importer' ); ?> ' + data.processed + '/' + data.total, data.stats);
						
						if (data.is_complete) {
							// Show results
							var allResults = data.results || [];
							var allErrors = data.errors || [];
							var results = [];
							
							allResults.forEach(function(result) {
								results.push('<div class="notice notice-success"><p><?php esc_html_e( 'Success:', 'url-image-importer' ); ?> ' + result.url + '</p></div>');
							});
							
							allErrors.forEach(function(error) {
								results.push('<div class="notice notice-error"><p>' + error + '</p></div>');
							});
							
							finishImport(type, allResults.length, allErrors.length, results);
						} else {
							// Continue with next batch
							setTimeout(function() {
								processBatchImport(batchId, urls, data.next_index, type);
							}, 200);
						}
					} else {
						// Handle cancellation or error
						var errorMsg = response.data;
						if (typeof errorMsg === 'object' && errorMsg.message) {
							errorMsg = errorMsg.message;
						}
						
						$('#' + type + '-progress-text').text('<?php esc_html_e( 'Import stopped:', 'url-image-importer' ); ?> ' + errorMsg);
						$('#start-' + type + '-import').prop('disabled', false);
						$('#cancel-' + type + '-import').prop('disabled', false).text('<?php esc_html_e( 'Cancel Import', 'url-image-importer' ); ?>');
						activeImportBatchId = null;
						activeImportType = null;
					}
				},
				error: function() {
					$('#' + type + '-progress-text').text('<?php esc_html_e( 'Network error occurred', 'url-image-importer' ); ?>');
					$('#start-' + type + '-import').prop('disabled', false);
					$('#cancel-' + type + '-import').prop('disabled', false).text('<?php esc_html_e( 'Cancel Import', 'url-image-importer' ); ?>');
					activeImportBatchId = null;
					activeImportType = null;
				}
			});
		}
		
		function startXmlImport() {
			resetStats('xml');
			$('#xml-progress-container').show();
			$('#start-xml-import').prop('disabled', true);
			
			updateProgress('xml', 0, 1, '<?php esc_html_e( 'Uploading and processing XML file...', 'url-image-importer' ); ?>');
			
			var formData = new FormData();
			formData.append('action', 'uimptr_process_xml_import');
			formData.append('xml_file', $('#xml_file')[0].files[0]);
			formData.append('images_only', $('#xml-import input[name="images_only"]:checked').val() || '');
			formData.append('force_reimport', $('#xml-import input[name="force_reimport"]:checked').val() || '');
			formData.append('nonce', uimptr_ajax.nonce);
			
			$.ajax({
				url: uimptr_ajax.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success && response.data.urls) {
						// Store the batch ID for potential cancellation
						var xmlBatchId = response.data.batch_id || 'xml-' + Date.now();
						activeImportBatchId = xmlBatchId;
						activeImportType = 'xml';
						
						// Start importing the URLs from XML using batch system
						updateProgress('xml', 0, response.data.urls.length, '<?php esc_html_e( 'Starting import from XML...', 'url-image-importer' ); ?>');
						processBatchImport(xmlBatchId, response.data.urls, 0, 'xml');
					} else {
						finishImport('xml', 0, 1, ['<div class="notice notice-error"><p>' + (response.data || '<?php esc_html_e( 'Failed to process XML file', 'url-image-importer' ); ?>') + '</p></div>']);
					}
				},
				error: function() {
					finishImport('xml', 0, 1, ['<div class="notice notice-error"><p><?php esc_html_e( 'Network error processing XML file', 'url-image-importer' ); ?></p></div>']);
				}
			});
		}
		

		
		function resetStats(type) {
			$('#' + type + '-success-count').text('0');
			$('#' + type + '-failed-count').text('0');
			$('#' + type + '-skipped-count').text('0');
		}

		function updateProgress(type, current, total, message, stats) {
			var percent = total > 0 ? Math.round((current / total) * 100) : 0;
			
			$('#' + type + '-progress-bar').css('width', percent + '%');
			$('#' + type + '-progress-text').text(message);
			$('#' + type + '-progress-count').text(current + '/' + total);
			
			// Update stats if provided
			if (stats) {
				if (typeof stats.success !== 'undefined') {
					$('#' + type + '-success-count').text(stats.success);
				}
				if (typeof stats.failed !== 'undefined') {
					$('#' + type + '-failed-count').text(stats.failed);
				}
				if (typeof stats.skipped !== 'undefined') {
					$('#' + type + '-skipped-count').text(stats.skipped);
				}
			}
		}
		
		function stopImport(batchId, type) {
			// Disable the cancel button and show stopping message
			$('#cancel-' + type + '-import').prop('disabled', true).text('<?php esc_html_e( 'Stopping...', 'url-image-importer' ); ?>');
			$('#' + type + '-progress-text').text('<?php esc_html_e( 'Stopping import, please wait...', 'url-image-importer' ); ?>');
			
			// Send stop command to server
			$.ajax({
				url: uimptr_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'uimptr_cancel_import',
					nonce: uimptr_ajax.nonce,
					batch_id: batchId
				},
				success: function(response) {
					if (response.success) {
						$('#' + type + '-progress-text').text('<?php esc_html_e( 'Import stopped by user.', 'url-image-importer' ); ?>');
						$('#cancel-' + type + '-import').text('<?php esc_html_e( 'Cancel Import', 'url-image-importer' ); ?>');
						$('#start-' + type + '-import').prop('disabled', false);
						activeImportBatchId = null;
						activeImportType = null;
					} else {
						$('#' + type + '-progress-text').text('<?php esc_html_e( 'Failed to stop import. Please refresh the page.', 'url-image-importer' ); ?>');
					}
				},
				error: function() {
					$('#' + type + '-progress-text').text('<?php esc_html_e( 'Network error while stopping import.', 'url-image-importer' ); ?>');
				},
				complete: function() {
					$('#cancel-' + type + '-import').prop('disabled', false);
				}
			});
		}
		
		function cancelImport(type) {
			importCanceled = true;
			$('#' + type + '-progress-text').text('<?php esc_html_e( 'Import canceled by user.', 'url-image-importer' ); ?>');
			$('#cancel-' + type + '-import').prop('disabled', true);
			$('#start-' + type + '-import').prop('disabled', false);
			activeImportBatchId = null;
			activeImportType = null;
		}
		
		function checkIfCancelled(batchId) {
			// This will be checked on the server side during batch processing
			// The client-side check is mainly for immediate UI feedback
			return false; // Server handles the real cancellation check
		}
		
		// Preview Functions
		function showUrlPreview(urls) {
			var previewHtml = '<h4>URLs to Import (' + urls.length + ' total)</h4>';
			previewHtml += '<div style="margin: 15px 0;"><label><input type="checkbox" id="select-all-urls" checked> Select All</label></div>';
			previewHtml += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
			
			urls.forEach(function(url, index) {
				var filename = url.split('/').pop().split('?')[0];
				previewHtml += '<div style="margin: 5px 0; padding: 10px; border-bottom: 1px solid #eee;">';
				previewHtml += '<label style="display: block; cursor: pointer;">';
				previewHtml += '<input type="checkbox" class="url-checkbox" data-url="' + url + '" checked style="margin-right: 10px;">';
				previewHtml += '<strong>' + filename + '</strong><br>';
				previewHtml += '<small style="color: #666; word-break: break-all;">' + url + '</small>';
				previewHtml += '</label></div>';
			});
			
			previewHtml += '</div>';
			previewHtml += '<p style="margin-top: 15px; color: #666;"><em>Review the URLs above and uncheck any you don\'t want to import.</em></p>';
			
			previewData = { type: 'url', urls: urls };
			$('#preview-content').html(previewHtml);
			$('#import-preview-modal').show();
			
			// Select All functionality
			$('#select-all-urls').change(function() {
				$('.url-checkbox').prop('checked', this.checked);
			});
		}
		
		function showXmlPreview(xmlFile) {
			$('#preview-content').html('<div style="text-align: center; padding: 40px;"><div class="spinner"></div><p>Analyzing XML file...</p></div>');
			$('#import-preview-modal').show();
			
			// Debug the uimptr_ajax object
			console.log('uimptr_ajax object:', uimptr_ajax);
			if (typeof uimptr_ajax === 'undefined') {
				console.error('ERROR: uimptr_ajax object is not defined!');
				$('#preview-content').html('<div style="color: red; text-align: center; padding: 40px;">Error: AJAX configuration missing. Please reload the page.</div>');
				return;
			}
			
			var formData = new FormData();
			formData.append('action', 'uimptr_process_xml_import');
			formData.append('xml_file', xmlFile);
			formData.append('images_only', $('#xml-import input[name="images_only"]:checked').val() || '');
			formData.append('force_reimport', $('#xml-import input[name="force_reimport"]:checked').val() || '');
			formData.append('xml_preserve_dates', $('#xml_preserve_dates').is(':checked') ? '1' : '');
			formData.append('nonce', uimptr_ajax.nonce);
			
			console.log('Starting XML Preview AJAX call to:', uimptr_ajax.ajax_url);
			console.log('Nonce:', uimptr_ajax.nonce);
			console.log('File object:', xmlFile);
			
			$.ajax({
				url: uimptr_ajax.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				timeout: 30000, // 30 second timeout
				success: function(response) {
					console.log('XML Preview Response:', response);
					if (response.success) {
						var urls = response.data.urls;
						var count = response.data.count;
						
						var previewHtml = '<h4>XML File Analysis (' + count + ' items found)</h4>';
						previewHtml += '<div style="margin: 15px 0;"><label><input type="checkbox" id="select-all-xml" checked> Select All</label></div>';
						previewHtml += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
						
						if (count > 0) {
							urls.forEach(function(item, index) {
								var filename = item.url.split('/').pop().split('?')[0];
								var title = item.metadata && item.metadata.title ? item.metadata.title : filename;
								
								previewHtml += '<div class="checkbox-item">';
								previewHtml += '<label style="display: block; cursor: pointer;">';
								previewHtml += '<input type="checkbox" class="xml-checkbox" data-index="' + index + '" checked>';
								previewHtml += '<div class="item-title">' + title + '</div>';
								previewHtml += '<div class="item-url">' + item.url + '</div>';
								if (item.metadata && item.metadata.date) {
									previewHtml += '<div class="item-meta">Date: ' + item.metadata.date + '</div>';
								}
								previewHtml += '</label></div>';
							});
						} else {
							previewHtml += '<p>No importable items found in the XML file.</p>';
						}
						
						previewHtml += '</div>';
						previewHtml += '<p style="margin-top: 15px; color: #666;"><em>Review the items above and uncheck any you don\'t want to import.</em></p>';
						
						previewData = { 
							type: 'xml', 
							urls: urls, 
							batch_id: response.data.batch_id 
						};
						
						$('#preview-content').html(previewHtml);
						
						// Select All functionality
						$('#select-all-xml').change(function() {
							$('.xml-checkbox').prop('checked', this.checked);
						});
						
					} else {
						$('#preview-content').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
					}
				},
				error: function(xhr, status, error) {
					console.error('XML Preview AJAX Error:', {xhr: xhr, status: status, error: error});
					var errorMessage = 'Network error while processing XML file.';
					if (status === 'timeout') {
						errorMessage = 'Request timed out. Please try a smaller XML file.';
					} else if (xhr.status === 0) {
						errorMessage = 'Network connection failed. Please check your internet connection.';
					} else if (xhr.status >= 400) {
						errorMessage = 'Server error (' + xhr.status + '). Please check server logs.';
					}
					$('#preview-content').html('<div class="notice notice-error"><p>' + errorMessage + '</p><p><small>Details: ' + status + ' - ' + error + '</small></p></div>');
				}
			});
		}
		
		function finishImport(type, imported, errors, results) {
			var message = '<?php esc_html_e( 'Import completed!', 'url-image-importer' ); ?> ' + 
				'<?php esc_html_e( 'Imported:', 'url-image-importer' ); ?> ' + imported + ', ' +
				'<?php esc_html_e( 'Errors:', 'url-image-importer' ); ?> ' + errors;
				
			if (importCanceled) {
				message = '<?php esc_html_e( 'Import canceled.', 'url-image-importer' ); ?> ' + 
					'<?php esc_html_e( 'Imported:', 'url-image-importer' ); ?> ' + imported + ', ' +
					'<?php esc_html_e( 'Errors:', 'url-image-importer' ); ?> ' + errors;
			}
			
			$('#' + type + '-progress-text').text(message);
			$('#' + type + '-import-results').html(results.slice(0, 10).join(''));
			
			if (results.length > 10) {
				$('#' + type + '-import-results').append('<div class="notice notice-info"><p><?php esc_html_e( 'Showing first 10 results. Total:', 'url-image-importer' ); ?> ' + results.length + '</p></div>');
			}
			
			$('#start-' + type + '-import').prop('disabled', false);
			$('#cancel-' + type + '-import').prop('disabled', false).text('<?php esc_html_e( 'Cancel Import', 'url-image-importer' ); ?>');
			
			// Clear active import tracking
			activeImportBatchId = null;
			activeImportType = null;
		}
		
		// CSV Import functionality
		$('#start-csv-import').click(function() {
			console.log('CSV Import button clicked');
			var csvFile = $('#csv_file')[0].files[0];
			if (!csvFile) {
				alert('<?php esc_html_e( 'Please select a CSV file to import.', 'url-image-importer' ); ?>');
				return;
			}
			
			console.log('CSV File selected:', csvFile.name, csvFile.size, 'bytes');
			showCsvPreview(csvFile);
		});
		
		$('#cancel-csv-import').click(function() {
			if (!confirm('<?php esc_html_e( 'Are you sure you want to stop the import? This will cancel all remaining imports.', 'url-image-importer' ); ?>')) {
				return;
			}
			
			if (activeImportBatchId && activeImportType === 'csv') {
				stopImport(activeImportBatchId, 'csv');
			} else {
				cancelImport('csv');
			}
		});
		
		// Download sample CSV
		$('#download-sample-csv').click(function(e) {
			e.preventDefault();
			var csvContent = 'url,title,description,alt_text,date\n';
			csvContent += 'https://picsum.photos/800/600?random=1,Scenic Landscape,Beautiful mountain landscape with crystal clear lake reflection,Mountain landscape with lake reflection,2024-01-15\n';
			csvContent += 'https://picsum.photos/600/800?random=2,Portrait Photography,Professional portrait with natural lighting and soft background,Professional portrait photo,2024-02-20\n';
			csvContent += 'https://picsum.photos/1200/400?random=3,Panoramic View,Wide panoramic view of city skyline during golden hour,City skyline panorama,2024-03-10\n';
			csvContent += 'https://picsum.photos/500/500?random=4,Abstract Art,Modern abstract composition with vibrant colors and geometric shapes,Abstract geometric art,2024-04-05\n';
			csvContent += 'https://picsum.photos/800/800?random=5,Nature Photography,Close-up macro shot of dewdrops on flower petals,Dewdrops on flower macro,2024-05-12';
			
			var blob = new Blob([csvContent], { type: 'text/csv' });
			var url = window.URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.setAttribute('hidden', '');
			a.setAttribute('href', url);
			a.setAttribute('download', 'sample-import.csv');
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
		});
		
		function showCsvPreview(csvFile) {
			$('#preview-content').html('<div style="text-align: center; padding: 40px;"><div class="spinner"></div><p>Analyzing CSV file...</p></div>');
			$('#import-preview-modal').show();
			
			var formData = new FormData();
			formData.append('action', 'uimptr_process_csv_import');
			formData.append('csv_file', csvFile);
			formData.append('images_only', $('#csv-import input[name="csv_images_only"]:checked').val() || '');
			formData.append('force_reimport', $('#csv-import input[name="csv_force_reimport"]:checked').val() || '');
			formData.append('csv_preserve_dates', $('#csv_preserve_dates').is(':checked') ? '1' : '');
			formData.append('nonce', uimptr_ajax.nonce);
			
			console.log('Starting CSV Preview AJAX call to:', uimptr_ajax.ajax_url);
			
			$.ajax({
				url: uimptr_ajax.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				timeout: 30000,
				success: function(response) {
					console.log('CSV Preview Response:', response);
					if (response.success) {
						var urls = response.data.urls;
						var count = response.data.count;
						
						var previewHtml = '<h4>CSV File Analysis (' + count + ' items found)</h4>';
						previewHtml += '<div style="margin: 15px 0;"><label><input type="checkbox" id="select-all-csv" checked> Select All</label></div>';
						previewHtml += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
						
						if (count > 0) {
							urls.forEach(function(item, index) {
								var filename = item.url.split('/').pop().split('?')[0];
								var title = item.metadata && item.metadata.title ? item.metadata.title : filename;
								
								previewHtml += '<div class="checkbox-item">';
								previewHtml += '<label style="display: block; cursor: pointer;">';
								previewHtml += '<input type="checkbox" class="csv-checkbox" data-index="' + index + '" checked>';
								previewHtml += '<div class="item-title">' + title + '</div>';
								previewHtml += '<div class="item-url">' + item.url + '</div>';
								if (item.metadata && item.metadata.description) {
									previewHtml += '<div class="item-meta">Description: ' + item.metadata.description + '</div>';
								}
								if (item.metadata && item.metadata.date) {
									previewHtml += '<div class="item-meta">Date: ' + item.metadata.date + '</div>';
								}
								previewHtml += '</label></div>';
							});
						} else {
							previewHtml += '<p>No importable items found in the CSV file.</p>';
						}
						
						previewHtml += '</div>';
						previewHtml += '<p style="margin-top: 15px; color: #666;"><em>Review the items above and uncheck any you don\'t want to import.</em></p>';
						
						previewData = { 
							type: 'csv', 
							urls: urls, 
							batch_id: response.data.batch_id 
						};
						
						$('#preview-content').html(previewHtml);
						
						// Select All functionality
						$('#select-all-csv').change(function() {
							$('.csv-checkbox').prop('checked', this.checked);
						});
						
					} else {
						$('#preview-content').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
					}
				},
				error: function(xhr, status, error) {
					console.error('CSV Preview AJAX Error:', {xhr: xhr, status: status, error: error});
					var errorMessage = 'Network error while processing CSV file.';
					if (status === 'timeout') {
						errorMessage = 'Request timed out. Please try a smaller CSV file.';
					} else if (xhr.status === 0) {
						errorMessage = 'Network connection failed. Please check your internet connection.';
					} else if (xhr.status >= 400) {
						errorMessage = 'Server error (' + xhr.status + '). Please check server logs.';
					}
					$('#preview-content').html('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
				}
			});
		}
	});
	</script>
	<?php
	require_once UIMPTR_PATH . '/templates/scan-start.php';
	require_once UIMPTR_PATH . '/templates/modal-subscribe.php';
	require_once UIMPTR_PATH . '/templates/modal-scan.php';

	$dismissed = get_user_option( 'bfu_subscribe_notice_dismissed', get_current_user_id() );
	if ( ! $dismissed ) {
		require_once UIMPTR_PATH . '/templates/modal-subscribe.php';
	}
	$scan_results = get_site_option( 'uimptr_file_scan' );
	if ( isset( $scan_results['scan_finished'] ) && $scan_results['scan_finished'] ) {
		if ( isset( $scan_results['types'] ) ) {
			$total_files   = array_sum( wp_list_pluck( $scan_results['types'], 'files' ) );
			$total_storage = array_sum( wp_list_pluck( $scan_results['types'], 'size' ) );
		} else {
			$total_files   = 0;
			$total_storage = 0;
		}
		require_once UIMPTR_PATH . '/templates/scan-results.php';
	} else {
		require_once UIMPTR_PATH . '/templates/scan-start.php';
	}
	require_once UIMPTR_PATH . '/templates/modal-upgrade.php';
	require_once UIMPTR_PATH . '/templates/footer.php';
}

/**
 * Function to import the image from a URL
 *
 * @param url $image_url URL of the image to import.
 * */
function uimptr_import_image_from_url( $image_url, $batch_id = null, $metadata = array(), $preserve_dates = false ) {
	// Check for stop command if batch_id is provided
	if ( $batch_id ) {
		$cancel_flag = get_transient( "uimptr_cancel_{$batch_id}" );
		if ( $cancel_flag ) {
			return new WP_Error( 'import_cancelled', 'Import was cancelled by user' );
		}
	}
	
	$response = wp_remote_get( $image_url );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'image_download_failed', 'Failed to download image.' );
	}

	$image_data = wp_remote_retrieve_body( $response );
	$image_type = wp_remote_retrieve_header( $response, 'content-type' );

	if ( empty( $image_data ) || strpos( $image_type, 'image/' ) !== 0 ) {
		return new WP_Error( 'invalid_image', 'The provided URL is not a valid image.' );
	}

	$upload_dir = wp_upload_dir();
	$filename_url_path = is_string( $image_url ) ? parse_url( $image_url, PHP_URL_PATH ) : false;
	$filename   = '';
	if ( $filename_url_path && is_string( $filename_url_path ) ) {
		$filename = basename( $filename_url_path );
	}

	if ( ! $filename ) {
		$filename = 'imported_image_' . time() . '.jpg';
	}

	$file_path = $upload_dir['path'] . '/' . $filename;
	file_put_contents( $file_path, $image_data );

	$file_type = wp_check_filetype( $filename, null );

	// Use metadata from XML if available, otherwise fall back to filename
	$title = !empty($metadata['title']) ? sanitize_text_field($metadata['title']) : sanitize_file_name( $filename );
	$description = !empty($metadata['description']) ? sanitize_textarea_field($metadata['description']) : '';
	$date = !empty($metadata['date']) ? $metadata['date'] : null;

	$attachment = array(
		'post_mime_type' => $file_type['type'],
		'post_title'     => $title,
		'post_content'   => $description,
		'post_excerpt'   => $description, // This becomes the caption
		'post_status'    => 'inherit',
	);
	
	// Set the original date if available and preserve_dates is enabled
	if ( $preserve_dates && $date ) {
		// Handle various date formats from XML/CSV
		$timestamp = strtotime( $date );
		if ( $timestamp !== false ) {
			$formatted_date = date( 'Y-m-d H:i:s', $timestamp );
			$attachment['post_date'] = $formatted_date;
			$attachment['post_date_gmt'] = get_gmt_from_date( $formatted_date );
			
			// Debug log to verify dates are being set correctly
			error_log( "URL Image Importer: Preserving original date for {$title}: {$formatted_date} (original: {$date})" );
		} else {
			error_log( "URL Image Importer: Failed to parse date: {$date}" );
		}
	} elseif ( !$preserve_dates ) {
		// Use current date (default WordPress behavior) - images will appear at top
		error_log( "URL Image Importer: Using current date for {$title} (will appear at top)" );
	}

	$attachment_id = wp_insert_attachment( $attachment, $file_path );

	if ( ! is_wp_error( $attachment_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );
		
		// Set alt text from alt_text field, or fall back to title if available
		$alt_text = '';
		if ( !empty($metadata['alt_text']) ) {
			$alt_text = sanitize_text_field($metadata['alt_text']);
		} elseif ( !empty($metadata['title']) ) {
			$alt_text = sanitize_text_field($metadata['title']);
		}
		
		if ( !empty($alt_text) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}
	}

	return $attachment_id;
}

/**
 * Scan files to analyze storage usage by file type.
 */
function uimptr_ajax_file_scan() {
	$path           = uimptr_get_upload_dir_root();
	$remaining_dirs = array();
	$nonce          = isset( $_POST['js_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['js_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ajax-nonce' ) ) {
		error_log('URL Image Importer: Nonce verification failed during scan.');
		wp_die( 'Nonce Varification Failed!' );
	}
	try {
		if ( isset( $_POST['remaining_dirs'] ) ) {
			$dirs_raw = wp_unslash( $_POST['remaining_dirs'] );
			$dirs_arr = is_array($dirs_raw) ? $dirs_raw : explode(',', (string)$dirs_raw);
			foreach ( $dirs_arr as $dir ) {
				$dir = sanitize_text_field( $dir );
				$realpath = realpath( $path . $dir );
				if ( 0 === strpos( $realpath, $path ) ) {
					$remaining_dirs[] = $dir;
				}
			}
		}
		error_log('URL Image Importer: Starting scan with path ' . $path . ' and dirs: ' . print_r($remaining_dirs, true));
		$file_scan = new \UrlImageImporter\FileScan\FileScan( $path, 20, $remaining_dirs );
		$file_scan->start();
		$file_count     = number_format_i18n( $file_scan->get_total_files() );
		$file_size      = size_format( $file_scan->get_total_size(), 2 );
		$remaining_dirs = $file_scan->paths_left;
		$is_done        = $file_scan->is_done;

		$data = compact( 'file_count', 'file_size', 'is_done', 'remaining_dirs' );
		error_log('URL Image Importer: Scan finished. Data: ' . print_r($data, true));
		wp_send_json_success( $data );
	} catch (Throwable $e) {
		error_log('URL Image Importer: Scan failed with error: ' . $e->getMessage());
		wp_send_json_error(['error' => $e->getMessage()]);
	}
}
add_action( 'wp_ajax_uimptr_bfu_file_scan', 'uimptr_ajax_file_scan' );

/**
 * AJAX handler for single URL import
 */
function uimptr_ajax_import_single_url() {
	check_ajax_referer( 'uimptr_ajax', 'nonce' );
	
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( 'Permission denied' );
	}
	
	$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
	$metadata = isset( $_POST['metadata'] ) ? json_decode( stripslashes( $_POST['metadata'] ), true ) : array();
	$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : null;
	
	if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		wp_send_json_error( 'Invalid URL provided' );
	}
	
	// Check if we should preserve dates
	$preserve_dates = isset( $_POST['preserve_dates'] ) && $_POST['preserve_dates'] === 'true';
	
	// Pass metadata to the import function so it handles dates properly during initial creation
	$attachment_id = uimptr_import_image_from_url( $url, $batch_id, $metadata, $preserve_dates );
	
	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( $attachment_id->get_error_message() );
	}
	
	// Metadata (including dates) is now handled in uimptr_import_image_from_url()
	
	wp_send_json_success( array( 
		'attachment_id' => $attachment_id,
		'edit_link' => get_edit_post_link( $attachment_id )
	) );
}
add_action( 'wp_ajax_uimptr_import_single_url', 'uimptr_ajax_import_single_url' );

/**
 * AJAX handler for XML processing (extract URLs)
 */
function uimptr_ajax_process_xml_import() {
	// Debug logging
	error_log( 'XML Import AJAX endpoint called' );
	error_log( 'POST data: ' . print_r( $_POST, true ) );
	error_log( 'FILES data: ' . print_r( $_FILES, true ) );
	
	check_ajax_referer( 'uimptr_ajax', 'nonce' );
	
	if ( ! current_user_can( 'upload_files' ) ) {
		error_log( 'XML Import: Permission denied for user' );
		wp_send_json_error( 'Permission denied' );
	}
	
	if ( !isset( $_FILES['xml_file'] ) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK ) {
		wp_send_json_error( 'No file uploaded or upload error occurred.' );
	}
	
	$uploaded_file = $_FILES['xml_file'];
	$file_extension = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );
	
	if ( $file_extension !== 'xml' ) {
		wp_send_json_error( 'Please upload a valid XML file.' );
	}
	
	// Store the file temporarily
	$temp_file_result = uimptr_store_temp_file( $uploaded_file );
	if ( is_wp_error( $temp_file_result ) ) {
		wp_send_json_error( $temp_file_result->get_error_message() );
	}
	
	// Read and parse XML content
	$xml_content = file_get_contents( $temp_file_result['path'] );
	if ( $xml_content === false ) {
		wp_send_json_error( 'Failed to read uploaded file.' );
	}
	
	// Check if we should preserve dates
	$preserve_dates = isset( $_POST['xml_preserve_dates'] ) && $_POST['xml_preserve_dates'];
	
	// Parse XML and extract URLs from content
	$urls_data = uimptr_extract_urls_from_xml_content( $xml_content, $preserve_dates );
	
	if ( is_wp_error( $urls_data ) ) {
		// Clean up temp file on error
		if ( file_exists( $temp_file_result['path'] ) ) {
			unlink( $temp_file_result['path'] );
		}
		wp_send_json_error( $urls_data->get_error_message() );
	}
	
	// Store file info for batch processing
	$batch_id = $temp_file_result['file_id'];
	
	wp_send_json_success( array( 
		'urls' => $urls_data,
		'count' => count( $urls_data ),
		'batch_id' => $batch_id
	) );
}
add_action( 'wp_ajax_uimptr_process_xml_import', 'uimptr_ajax_process_xml_import' );
error_log( 'URL Image Importer: Registered wp_ajax_uimptr_process_xml_import action' );

// Test endpoint to verify AJAX is working
function uimptr_test_ajax_connection() {
	error_log( 'Test AJAX endpoint called successfully' );
	error_log( 'POST data: ' . print_r( $_POST, true ) );
	
	// Don't require nonce for testing
	if ( ! current_user_can( 'upload_files' ) ) {
		error_log( 'Test AJAX: Permission denied' );
		wp_send_json_error( 'Permission denied - user not logged in' );
	}
	
	wp_send_json_success( 'AJAX connection working' );
}
add_action( 'wp_ajax_uimptr_test_connection', 'uimptr_test_ajax_connection' );
error_log( 'URL Image Importer: Registered wp_ajax_uimptr_test_connection action' );

// CSV Import AJAX endpoint
function uimptr_ajax_process_csv_import() {
	// Debug logging
	error_log( 'CSV Import AJAX endpoint called' );
	error_log( 'POST data: ' . print_r( $_POST, true ) );
	error_log( 'FILES data: ' . print_r( $_FILES, true ) );
	
	check_ajax_referer( 'uimptr_ajax', 'nonce' );
	
	if ( ! current_user_can( 'upload_files' ) ) {
		error_log( 'CSV Import: Permission denied for user' );
		wp_send_json_error( 'Permission denied' );
	}
	
	if ( !isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
		wp_send_json_error( 'No file uploaded or upload error occurred.' );
	}
	
	$uploaded_file = $_FILES['csv_file'];
	$file_extension = strtolower( pathinfo( $uploaded_file['name'], PATHINFO_EXTENSION ) );
	
	if ( $file_extension !== 'csv' ) {
		wp_send_json_error( 'Please upload a valid CSV file.' );
	}
	
	// Store the file temporarily
	$temp_file_result = uimptr_store_temp_file( $uploaded_file );
	if ( is_wp_error( $temp_file_result ) ) {
		wp_send_json_error( $temp_file_result->get_error_message() );
	}
	
	// Read and parse CSV content
	$csv_content = file_get_contents( $temp_file_result['path'] );
	if ( $csv_content === false ) {
		wp_send_json_error( 'Failed to read uploaded file.' );
	}
	
	// Check if we should preserve dates
	$preserve_dates = isset( $_POST['csv_preserve_dates'] ) && $_POST['csv_preserve_dates'];
	
	// Parse CSV and extract URLs from content
	$urls_data = uimptr_extract_urls_from_csv_content( $csv_content, $preserve_dates );
	
	if ( is_wp_error( $urls_data ) ) {
		// Clean up temp file on error
		if ( file_exists( $temp_file_result['path'] ) ) {
			unlink( $temp_file_result['path'] );
		}
		wp_send_json_error( $urls_data->get_error_message() );
	}
	
	// Store file info for batch processing
	$batch_id = $temp_file_result['file_id'];
	
	wp_send_json_success( array( 
		'urls' => $urls_data,
		'count' => count( $urls_data ),
		'batch_id' => $batch_id
	) );
}
add_action( 'wp_ajax_uimptr_process_csv_import', 'uimptr_ajax_process_csv_import' );
error_log( 'URL Image Importer: Registered wp_ajax_uimptr_process_csv_import action' );

/**
 * AJAX handler for batch import with progress tracking
 */
function uimptr_ajax_batch_import() {
	check_ajax_referer( 'uimptr_ajax', 'nonce' );
	
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( 'Permission denied' );
	}
	
	$batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
	$start_index = intval( $_POST['start_index'] ?? 0 );
	$batch_size = intval( $_POST['batch_size'] ?? 5 ); // Process 5 URLs at a time
	$urls = json_decode( stripslashes( $_POST['urls'] ?? '[]' ), true );
	$preserve_dates = isset( $_POST['preserve_dates'] ) && $_POST['preserve_dates'] === 'true';
	$force_reimport = isset( $_POST['force_reimport'] ) && $_POST['force_reimport'] === 'true';
	
	if ( empty( $batch_id ) || empty( $urls ) ) {
		wp_send_json_error( 'Invalid batch data' );
	}
	
	// Check if import was cancelled
	$cancel_flag = get_transient( "uimptr_cancel_{$batch_id}" );
	if ( $cancel_flag ) {
		delete_transient( "uimptr_cancel_{$batch_id}" );
		wp_send_json_error( 'Import cancelled by user' );
	}
	
	$total_urls = count( $urls );
	$end_index = min( $start_index + $batch_size, $total_urls );
	$results = array();
	$errors = array();
	
	// Get cumulative counters from transients
	$batch_stats = get_transient( "uimptr_stats_{$batch_id}" ) ?: array(
		'success' => 0,
		'failed' => 0,
		'skipped' => 0
	);
	
	// Initialize batch counters
	$batch_success = 0;
	$batch_failed = 0;
	$batch_skipped = 0;
	
	// Process batch
	for ( $i = $start_index; $i < $end_index; $i++ ) {
		// Check for stop command before processing each URL
		$cancel_flag = get_transient( "uimptr_cancel_{$batch_id}" );
		if ( $cancel_flag ) {
			delete_transient( "uimptr_cancel_{$batch_id}" );
			wp_send_json_error( array( 
				'message' => 'Import stopped by user',
				'processed' => $i,
				'stopped_at' => $i
			) );
		}
		
		if ( !isset( $urls[$i] ) ) {
			$batch_skipped++;
			continue;
		}
		
		$url_data = $urls[$i];
		$url = $url_data['url'] ?? '';
		$metadata = $url_data['metadata'] ?? array();
		
		if ( empty( $url ) ) {
			$errors[] = "Empty URL at index {$i}";
			$batch_failed++;
			continue;
		}
		
		// Check if file already exists (unless force_reimport is enabled)
		$filename = basename( parse_url( $url, PHP_URL_PATH ) );
		if ( uimptr_attachment_exists( $filename ) && !$force_reimport ) {
			$batch_skipped++;
			continue;
		}
		
		// Import the image with metadata
		$attachment_id = uimptr_import_image_from_url( $url, $batch_id, $metadata, $preserve_dates );
		
		if ( is_wp_error( $attachment_id ) ) {
			$errors[] = "Failed to import {$url}: " . $attachment_id->get_error_message();
			$batch_failed++;
			continue;
		}
		
		$batch_success++;
		
		// Note: Metadata (title, description, date) is already set in uimptr_import_image_from_url()
		// No additional updates needed here to avoid overriding the original date
		
		$results[] = array(
			'url' => $url,
			'attachment_id' => $attachment_id,
			'edit_link' => get_edit_post_link( $attachment_id )
		);
		
		// Small delay to prevent overwhelming the server and ensure proper ordering
		usleep( 300000 ); // 0.3 second
	}
	
	// Update cumulative stats
	$batch_stats['success'] += $batch_success;
	$batch_stats['failed'] += $batch_failed;
	$batch_stats['skipped'] += $batch_skipped;
	
	$processed = $end_index;
	$progress = ( $processed / $total_urls ) * 100;
	$is_complete = $processed >= $total_urls;
	
	// Save updated stats
	if ( !$is_complete ) {
		set_transient( "uimptr_stats_{$batch_id}", $batch_stats, 3600 ); // 1 hour
	}
	
	// Clean up temporary file if import is complete
	if ( $is_complete ) {
		$temp_file_info = get_transient( "uimptr_temp_file_{$batch_id}" );
		if ( $temp_file_info && isset( $temp_file_info['path'] ) ) {
			if ( file_exists( $temp_file_info['path'] ) ) {
				unlink( $temp_file_info['path'] );
			}
			delete_transient( "uimptr_temp_file_{$batch_id}" );
		}
		
		// Clean up stats transient
		delete_transient( "uimptr_stats_{$batch_id}" );
	}
	
	wp_send_json_success( array(
		'batch_id' => $batch_id,
		'processed' => $processed,
		'total' => $total_urls,
		'progress' => round( $progress, 1 ),
		'is_complete' => $is_complete,
		'results' => $results,
		'errors' => $errors,
		'next_index' => $is_complete ? null : $end_index,
		'stats' => array(
			'success' => $batch_stats['success'],
			'failed' => $batch_stats['failed'],
			'skipped' => $batch_stats['skipped']
		)
	) );
}
add_action( 'wp_ajax_uimptr_batch_import', 'uimptr_ajax_batch_import' );

/**
 * AJAX handler for cancelling batch import
 */
function uimptr_ajax_cancel_import() {
	check_ajax_referer( 'uimptr_ajax', 'nonce' );
	
	$batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
	
	if ( empty( $batch_id ) ) {
		wp_send_json_error( 'Invalid batch ID' );
	}
	
	// Set cancel flag
	set_transient( "uimptr_cancel_{$batch_id}", true, 300 ); // 5 minutes
	
	// Clean up temporary file if this is an XML import
	$temp_file_info = get_transient( "uimptr_temp_file_{$batch_id}" );
	if ( $temp_file_info && isset( $temp_file_info['path'] ) ) {
		if ( file_exists( $temp_file_info['path'] ) ) {
			unlink( $temp_file_info['path'] );
		}
		delete_transient( "uimptr_temp_file_{$batch_id}" );
	}
	
	wp_send_json_success( array( 'message' => 'Import cancellation requested' ) );
}
add_action( 'wp_ajax_uimptr_cancel_import', 'uimptr_ajax_cancel_import' );

/**
 * Extract URLs and metadata from XML content
 */
function uimptr_extract_urls_from_xml_content( $xml_content, $preserve_dates = false ) {
	if ( empty( $xml_content ) ) {
		return new WP_Error( 'empty_content', 'XML content is empty' );
	}
	
	// Load XML
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $xml_content );
	
	if ( $xml === false ) {
		return new WP_Error( 'invalid_xml', 'Failed to parse XML file. Please ensure it\'s a valid WordPress export file.' );
	}
	
	// Register namespaces
	$xml->registerXPathNamespace( 'wp', 'http://wordpress.org/export/1.2/' );
	$xml->registerXPathNamespace( 'content', 'http://purl.org/rss/1.0/modules/content/' );
	
	// Find all attachment items in the XML
	$attachments = $xml->xpath( '//item[wp:post_type="attachment"]' );
	
	if ( empty( $attachments ) ) {
		return new WP_Error( 'no_attachments', 'No attachments found in the XML file.' );
	}
	
	$urls_data = array();
	$images_only = isset( $_POST['images_only'] ) && $_POST['images_only'];
	
	foreach ( $attachments as $attachment ) {
		// Extract attachment data
		$title = (string) $attachment->title;
		$guid = (string) $attachment->guid;
		
		// Get description from multiple possible sources
		$description = '';
		if ( isset( $attachment->children( 'content', true )->encoded ) ) {
			$description = (string) $attachment->children( 'content', true )->encoded;
		} elseif ( isset( $attachment->children( 'wp', true )->post_content ) ) {
			$description = (string) $attachment->children( 'wp', true )->post_content;
		} else {
			$description = (string) $attachment->description;
		}
		
		// Get post date from wp:post_date (preferred) or fallback to pubDate
		$post_date = '';
		if ( isset( $attachment->children( 'wp', true )->post_date ) ) {
			$post_date = (string) $attachment->children( 'wp', true )->post_date;
		} else {
			$post_date = (string) $attachment->pubDate;
		}
		
		// Get attachment URL from wp:attachment_url or guid
		$attachment_url = '';
		if ( isset( $attachment->children( 'wp', true )->attachment_url ) ) {
			$attachment_url = (string) $attachment->children( 'wp', true )->attachment_url;
		} else {
			$attachment_url = $guid;
		}
		
		// Skip if not an image URL (when images_only is checked)
		if ( $images_only && !uimptr_is_image_url( $attachment_url ) ) {
			continue;
		}
		
		// Skip if already exists (unless force_reimport is checked)
		$filename = basename( parse_url( $attachment_url, PHP_URL_PATH ) );
		if ( uimptr_attachment_exists( $filename ) && !isset( $_POST['force_reimport'] ) ) {
			continue;
		}
		
		// Debug log to track date extraction
		if ( !empty( $post_date ) ) {
			error_log( "URL Image Importer: Extracted date for {$title}: {$post_date}" );
		}
		
		$urls_data[] = array(
			'url' => $attachment_url,
			'metadata' => array(
				'title' => $title,
				'description' => $description,
				'date' => $post_date
			)
		);
	}
	
	// Only sort by date when preserving original dates
	if ( $preserve_dates ) {
		// Sort by date (newest first) to maintain chronological order in media library
		usort( $urls_data, function( $a, $b ) {
			$date_a = strtotime( $a['metadata']['date'] );
			$date_b = strtotime( $b['metadata']['date'] );
			
			// If dates can't be parsed, maintain original order
			if ( $date_a === false || $date_b === false ) {
				return 0;
			}
			
			// Sort newest first (descending order)
			$result = $date_b - $date_a;
			
			// Debug log to verify sorting
			if ( $result !== 0 ) {
				error_log( sprintf( 
					"URL Image Importer: Sorting %s (%s) vs %s (%s) = %d",
					$a['metadata']['title'] ?? 'Unknown',
					date( 'Y-m-d H:i:s', $date_a ),
					$b['metadata']['title'] ?? 'Unknown', 
					date( 'Y-m-d H:i:s', $date_b ),
					$result
				) );
			}
			
			return $result;
		});
	} else {
		error_log( "URL Image Importer: Not sorting - using current date for all imports (will appear at top)" );
	}
	
	return $urls_data;
}

/**
 * Extract URLs and metadata from XML file (legacy compatibility)
 */
function uimptr_extract_urls_from_xml( $xml_file_path ) {
	if ( !file_exists( $xml_file_path ) ) {
		return new WP_Error( 'file_not_found', 'XML file not found' );
	}
	
	$xml_content = file_get_contents( $xml_file_path );
	if ( $xml_content === false ) {
		return new WP_Error( 'file_read_error', 'Could not read XML file' );
	}
	
	return uimptr_extract_urls_from_xml_content( $xml_content );
}

/**
 * Extract URLs and metadata from CSV content
 */
function uimptr_extract_urls_from_csv_content( $csv_content, $preserve_dates = false ) {
	if ( empty( $csv_content ) ) {
		return new WP_Error( 'empty_content', 'CSV content is empty' );
	}
	
	// Parse CSV content
	$lines = str_getcsv( $csv_content, "\n" );
	if ( empty( $lines ) ) {
		return new WP_Error( 'invalid_csv', 'Failed to parse CSV file.' );
	}
	
	// Get header row
	$headers = str_getcsv( array_shift( $lines ) );
	$url_index = array_search( 'url', $headers );
	
	if ( $url_index === false ) {
		return new WP_Error( 'missing_url_column', 'CSV file must contain a "url" column.' );
	}
	
	// Find other column indexes
	$title_index = array_search( 'title', $headers );
	$description_index = array_search( 'description', $headers );
	$alt_text_index = array_search( 'alt_text', $headers );
	$date_index = array_search( 'date', $headers );
	
	$urls_data = array();
	$images_only = isset( $_POST['images_only'] ) && $_POST['images_only'];
	
	foreach ( $lines as $line_num => $line ) {
		if ( empty( trim( $line ) ) ) {
			continue; // Skip empty lines
		}
		
		$data = str_getcsv( $line );
		
		// Skip if not enough columns
		if ( count( $data ) <= $url_index ) {
			continue;
		}
		
		$url = trim( $data[$url_index] );
		
		// Skip empty URLs
		if ( empty( $url ) ) {
			continue;
		}
		
		// Skip if not an image URL (when images_only is checked)
		if ( $images_only && !uimptr_is_image_url( $url ) ) {
			continue;
		}
		
		// Skip if already exists (unless force_reimport is checked)
		$filename = basename( parse_url( $url, PHP_URL_PATH ) );
		if ( uimptr_attachment_exists( $filename ) && !isset( $_POST['force_reimport'] ) ) {
			continue;
		}
		
		// Extract metadata
		$metadata = array();
		
		if ( $title_index !== false && isset( $data[$title_index] ) ) {
			$metadata['title'] = trim( $data[$title_index] );
		}
		
		if ( $description_index !== false && isset( $data[$description_index] ) ) {
			$metadata['description'] = trim( $data[$description_index] );
		}
		
		if ( $alt_text_index !== false && isset( $data[$alt_text_index] ) ) {
			$metadata['alt_text'] = trim( $data[$alt_text_index] );
		}
		
		if ( $date_index !== false && isset( $data[$date_index] ) ) {
			$metadata['date'] = trim( $data[$date_index] );
		}
		
		$urls_data[] = array(
			'url' => $url,
			'metadata' => $metadata
		);
	}
	
	if ( empty( $urls_data ) ) {
		return new WP_Error( 'no_valid_urls', 'No valid URLs found in the CSV file.' );
	}
	
	return $urls_data;
}

/**
 * Check if URL is an image
 */
function uimptr_is_image_url( $url ) {
	$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff', 'ico' );
	$extension = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	return in_array( $extension, $image_extensions );
}

/**
 * Check if attachment already exists
 */
function uimptr_attachment_exists( $filename ) {
	global $wpdb;
	$result = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
		'%' . $wpdb->esc_like( $filename )
	) );
	return !empty( $result );
}

/**
 * Store uploaded file temporarily with proper cleanup
 * Uses local storage to avoid Infinite Uploads cloud storage
 */
function uimptr_store_temp_file( $uploaded_file ) {
	// Get local temp directory (bypasses cloud storage)
	$temp_dir = uimptr_get_local_temp_dir();
	
	// Create temp directory if it doesn't exist
	if ( ! file_exists( $temp_dir ) ) {
		wp_mkdir_p( $temp_dir );
		
		// Add .htaccess to prevent direct access
		$htaccess_content = "Order Deny,Allow\nDeny from all\n";
		file_put_contents( $temp_dir . '/.htaccess', $htaccess_content );
		
		// Add index.php for extra security
		file_put_contents( $temp_dir . '/index.php', '<?php // Silence is golden' );
	}
	
	// Verify directory is writable
	if ( ! is_writable( $temp_dir ) ) {
		return new WP_Error( 'temp_dir_not_writable', 'Temporary directory is not writable: ' . $temp_dir );
	}
	
	// Generate unique filename
	$temp_filename = 'xml_import_' . wp_generate_password( 12, false ) . '_' . time() . '.xml';
	$temp_file_path = $temp_dir . '/' . $temp_filename;
	
	// Move uploaded file to temp location
	if ( ! move_uploaded_file( $uploaded_file['tmp_name'], $temp_file_path ) ) {
		return new WP_Error( 'temp_file_error', 'Failed to store temporary file' );
	}
	
	// Store file info in transient for cleanup
	$file_info = array(
		'path' => $temp_file_path,
		'original_name' => $uploaded_file['name'],
		'created' => time()
	);
	
	$file_id = wp_generate_password( 16, false );
	set_transient( "uimptr_temp_file_{$file_id}", $file_info, 2 * HOUR_IN_SECONDS );
	
	return array(
		'file_id' => $file_id,
		'path' => $temp_file_path
	);
}

/**
 * Cleanup temporary files (local storage only)
 */
function uimptr_cleanup_temp_files() {
	// Get the current temp directory
	$temp_dir = uimptr_get_local_temp_dir();
	
	if ( ! file_exists( $temp_dir ) ) {
		return;
	}
	
	$files = glob( $temp_dir . '/xml_import_*.xml' );
	if ( ! $files ) {
		return;
	}
	
	$current_time = time();
	
	foreach ( $files as $file ) {
		$file_time = filemtime( $file );
		
		// Delete files older than 2 hours
		if ( $current_time - $file_time > 2 * HOUR_IN_SECONDS ) {
			unlink( $file );
		}
	}
}

/**
 * Get local temp directory (bypasses cloud storage)
 */
function uimptr_get_local_temp_dir() {
	// Try WordPress temp directory first (usually /tmp or similar)
	$wp_temp_dir = get_temp_dir();
	if ( is_writable( $wp_temp_dir ) ) {
		return $wp_temp_dir . 'url-image-importer-temp';
	}
	
	// Fallback to plugin directory
	return UIMPTR_PATH . '/temp';
}

/**
 * Prevent Infinite Uploads from processing our temp files
 */
function uimptr_exclude_temp_files_from_cloud( $exclude, $file ) {
	// Exclude our temp files from cloud uploads
	if ( strpos( $file, 'url-image-importer-temp' ) !== false ) {
		return true;
	}
	
	if ( strpos( $file, '/temp/xml_import_' ) !== false ) {
		return true;
	}
	
	return $exclude;
}

// Hook into Infinite Uploads if it's active
if ( function_exists( 'infinite_uploads_init' ) || class_exists( 'Infinite_Uploads' ) ) {
	add_filter( 'infinite_uploads_exclude_file', 'uimptr_exclude_temp_files_from_cloud', 10, 2 );
}

// Schedule cleanup
add_action( 'uimptr_cleanup_temp_files', 'uimptr_cleanup_temp_files' );

// Register cleanup schedule if not already scheduled
if ( ! wp_next_scheduled( 'uimptr_cleanup_temp_files' ) ) {
	wp_schedule_event( time(), 'hourly', 'uimptr_cleanup_temp_files' );
}

/**
 * Get root path of the uploads directory.
 */
function uimptr_get_upload_dir_root() {
	$upload_path = trim( get_option( 'upload_path' ) );

	if ( empty( $upload_path ) || 'wp-content/uploads' === $upload_path ) {
		$dir = UPLOADBLOGSDIR;
	} else {
		$dir = $upload_path;
	}
	// If multisite (and if not the main site in a post-MU network).
	if ( is_multisite() && ! ( is_main_network() && is_main_site() && defined( 'MULTISITE' ) ) ) {

		if ( get_site_option( 'ms_files_rewriting' ) && defined( 'UPLOADS' ) && ! ms_is_switched() ) {
			/*
			 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
			 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
			 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
			 *    there, and
			 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
			 *    the original blog ID.
			 *
			 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
			 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
			 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
			 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
			 */

			$dir = ABSPATH . untrailingslashit( UPLOADBLOGSDIR );
		}
	}

	$basedir = $dir;

	return $basedir;
}

/**
 * Update option after dismiss modal.
 */
function uimptr_ajax_subscribe_dismiss() {
	update_user_option( get_current_user_id(), 'bfu_subscribe_notice_dismissed', 1 );
	wp_send_json_success();
}
add_action( 'wp_ajax_uimptr_subscribe_dismiss', 'uimptr_ajax_subscribe_dismiss' );

/**
 * Get data array of filescan results.
 *
 * @param false $is_chart If data should be formatted for chart.
 * @return array
 */
function uimptr_get_filetypes( $is_chart = false ) {

	$results = get_site_option( 'uimptr_file_scan' );
	if ( isset( $results['types'] ) ) {
		$types = $results['types'];
	} else {
		$types = array();
	}

	$data = array();
	foreach ( $types as $type => $meta ) {
		$data[ $type ] = (object) array(
			'color' => uimptr_get_file_type_format( $type, 'color' ),
			'label' => uimptr_get_file_type_format( $type, 'label' ),
			'size'  => $meta->size,
			'files' => $meta->files,
		);
	}

	$chart = array();
	if ( $is_chart ) {
		foreach ( $data as $item ) {
			$chart['datasets'][0]['data'][]            = $item->size;
			$chart['datasets'][0]['backgroundColor'][] = $item->color;

			/*
			* translators: %s: Total Files
			* translators: %s: File name
			* translators: %s: Total Files
			* translators: %s: search term
			*/
			$chart['labels'][]                         = $item->label . ': ' . sprintf( _n( '%1$s file totalling %2$s', '%1$s files totalling %2$s', $item->files, 'url-image-importer' ), number_format_i18n( $item->files ), size_format( $item->size, 1 ) );
		}

		$total_size     = array_sum( wp_list_pluck( $data, 'size' ) );
		$total_files    = array_sum( wp_list_pluck( $data, 'files' ) );

		/*
		* translators: %s: Total Files
		* translators: %s: Total Size
		* translators: %s: Total Files
		* translators: %s: Total Size
		*/
		$chart['total'] = sprintf( _n( '%1$s / %2$s File', '%1$s / %2$s Files', $total_files, 'url-image-importer' ), size_format( $total_size, 2 ), number_format_i18n( $total_files ) );

		return $chart;
	}

	return $data;
}

/**
 * Get HTML format details for a filetype.
 *
 * @param string $type File type.
 * @param string $key File Index.
 *
 * @return mixed
 */
function uimptr_get_file_type_format( $type, $key ) {
	$labels = array(
		'image'    => array(
			'color' => '#26A9E0',
			'label' => esc_html__( 'Images', 'url-image-importer' ),
		),
		'audio'    => array(
			'color' => '#00A167',
			'label' => esc_html__( 'Audio', 'url-image-importer' ),
		),
		'video'    => array(
			'color' => '#C035E2',
			'label' => esc_html__( 'Video', 'url-image-importer' ),
		),
		'document' => array(
			'color' => '#EE7C1E',
			'label' => esc_html__( 'Documents', 'url-image-importer' ),
		),
		'archive'  => array(
			'color' => '#EC008C',
			'label' => esc_html__( 'Archives', 'url-image-importer' ),
		),
		'code'     => array(
			'color' => '#EFED27',
			'label' => esc_html__( 'Code', 'url-image-importer' ),
		),
		'other'    => array(
			'color' => '#F1F1F1',
			'label' => esc_html__( 'Other', 'url-image-importer' ),
		),
	);

	if ( isset( $labels[ $type ] ) ) {
		return $labels[ $type ][ $key ];
	} else {
		return $labels['other'][ $key ];
	}
}

/**
 * Get the file type category for a given extension.
 *
 * @param string $filename File name.
 * @return string
 */
function uimptr_get_file_type( $filename ) {
	$extensions = array(
		'image'    => array( 'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'tif', 'tiff', 'ico', 'svg', 'svgz', 'webp' ),
		'audio'    => array( 'aac', 'ac3', 'aif', 'aiff', 'flac', 'm3a', 'm4a', 'm4b', 'mka', 'mp1', 'mp2', 'mp3', 'ogg', 'oga', 'ram', 'wav', 'wma' ),
		'video'    => array( '3g2', '3gp', '3gpp', 'asf', 'avi', 'divx', 'dv', 'flv', 'm4v', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'mpv', 'ogm', 'ogv', 'qt', 'rm', 'vob', 'wmv', 'webm' ),
		'document' => array(
			'log',
			'asc',
			'csv',
			'tsv',
			'txt',
			'doc',
			'docx',
			'docm',
			'dotm',
			'odt',
			'pages',
			'pdf',
			'xps',
			'oxps',
			'rtf',
			'wp',
			'wpd',
			'psd',
			'xcf',
			'swf',
			'key',
			'ppt',
			'pptx',
			'pptm',
			'pps',
			'ppsx',
			'ppsm',
			'sldx',
			'sldm',
			'odp',
			'numbers',
			'ods',
			'xls',
			'xlsx',
			'xlsm',
			'xlsb',
		),
		'archive'  => array( 'bz2', 'cab', 'dmg', 'gz', 'rar', 'sea', 'sit', 'sqx', 'tar', 'tgz', 'zip', '7z', 'data', 'bin', 'bak' ),
		'code'     => array( 'css', 'htm', 'html', 'php', 'js', 'md' ),
	);

	$ext = preg_replace( '/^.+?\.([^.]+)$/', '$1', $filename );
	if ( ! empty( $ext ) ) {
		$ext = strtolower( $ext );
		foreach ( $extensions as $type => $exts ) {
			if ( in_array( $ext, $exts, true ) ) {
				return $type;
			}
		}
	}

	return 'other';
}