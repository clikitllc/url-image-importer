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
$upload_dir = wp_upload_dir();

define( 'UIMPTR_PATH', plugin_dir_path( __FILE__ ) );
define( 'UIMPTR_VERSION', 1.0 );
define( 'UPLOADBLOGSDIR', $upload_dir['path'] );
require_once UIMPTR_PATH . '/classes/class-ui-big-file-uploads-file-scan.php';
if ( ! class_exists( 'UrlBigFileUploads' ) ) {
	require_once UIMPTR_PATH . '/classes/tuxedo_big_file_uploads.php';
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
	if ( isset( $_GET['page'] ) && 'import-images-url' === $_GET['page'] ) {
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
}
add_action( 'admin_enqueue_scripts', 'uimptr_admin_styles' );

/**
 * Import Image Form HTML
 */
function uimptr_import_images_url_page() {
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html( 'You do not have sufficient permissions to access this page.' ) );
	}

	if ( isset( $_POST['image_urls'] ) ) {
		check_admin_referer( 'uimptr-form-field', '_wpnonce_select_form' );
		$image_urls = array_map( 'trim', explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['image_urls'] ) ) ) );
		$results    = array();

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
	<form method="post">
		<?php
			wp_nonce_field( 'uimptr-form-field', '_wpnonce_select_form' )
		?>
		<div class="card upload">
			<div class="card-header">
				<div class="d-flex align-items-center">
					<h5 class="m-0 mr-auto p-0"> <?php echo esc_html( 'Image URLs (one per line)' ); ?></h5>
				</div>
			</div>
			<div class="card-body p-md-1">
				<div class="row justify-content-center mb-3 mt-3">
					<div class="col text-center">
						<textarea name="image_urls" id="image_urls" class="large-text" rows="10" required></textarea>
					</div>
				</div>
				<div class="row justify-right-right mb-2 btn-row">
					<div class="col-md-12 col-md-5 col-xl-4 text-center">
						<?php submit_button( 'Import Images' ); ?>
					</div>
				</div>
			</div>
		</div>
	</form>
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
function uimptr_import_image_from_url( $image_url ) {
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
	$filename   = basename( parse_url( $image_url, PHP_URL_PATH ) );

	if ( ! $filename ) {
		$filename = 'imported_image_' . time() . '.jpg';
	}

	$file_path = $upload_dir['path'] . '/' . $filename;
	file_put_contents( $file_path, $image_data );

	$file_type = wp_check_filetype( $filename, null );

	$attachment = array(
		'post_mime_type' => $file_type['type'],
		'post_title'     => sanitize_file_name( $filename ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$attachment_id = wp_insert_attachment( $attachment, $file_path );

	if ( ! is_wp_error( $attachment_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );
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
		wp_die( 'Nonce Varification Failed!' );
	}
	if ( isset( $_POST['remaining_dirs'] ) && is_array( $_POST['remaining_dirs'] ) ) {
		foreach ( sanitize_text_field( wp_unslash( $_POST['remaining_dirs'] ) ) as $dir ) {
			$realpath = realpath( $path . $dir );
			if ( 0 === strpos( $realpath, $path ) ) {
				$remaining_dirs[] = $dir;
			}
		}
	}
	$file_scan = new Ui_Big_File_Uploads_File_Scan( $path, 20, $remaining_dirs );
	$file_scan->start();
	$file_count     = number_format_i18n( $file_scan->get_total_files() );
	$file_size      = size_format( $file_scan->get_total_size(), 2 );
	$remaining_dirs = $file_scan->paths_left;
	$is_done        = $file_scan->is_done;

	$data = compact( 'file_count', 'file_size', 'is_done', 'remaining_dirs' );

	wp_send_json_success( $data );
}
add_action( 'wp_ajax_uimptr_bfu_file_scan', 'uimptr_ajax_file_scan' );

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