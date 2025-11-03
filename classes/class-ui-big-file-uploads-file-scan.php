<?php
/**
 * Ui_Big_File_Uploads_File_Scan
 *
 * @package UrlImageImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Lists files using a Breadth-First search algorithm to allow for time limits and resume across multiple requests.
 */
// Legacy wrapper for PSR-4 migration. Use UrlImageImporter\FileScan\UiBigFileUploadsFileScan instead.
// Ensure the PSR-4 class is loaded before extending it
if (!class_exists('UrlImageImporter\FileScan\UiBigFileUploadsFileScan')) {
    // Force autoloader to load the class
    class_exists('UrlImageImporter\FileScan\UiBigFileUploadsFileScan', true);
}

class Ui_Big_File_Uploads_File_Scan extends \UrlImageImporter\FileScan\UiBigFileUploadsFileScan {

	/**
	 *  Scan file flag.
	 *
	 * @var ''
	 *  */
	public $is_done = false; // Scan file flag.

	/**
	 *  File paths left.
	 *
	 * @var ''
	 *  */
	public $paths_left = array(); // File paths left.

	/**
	 *  Total files scanned.
	 *
	 * @var ''
	 *  */
	public $file_count = 0; // Total files scanned.

	/**
	 *  Total file types found.
	 *
	 * @var ''
	 *  */
	public $type_list = array(); // Total file types found.

	/**
	 *  File excluded.
	 *
	 * @var ''
	 *  */
	public $exclusions = array(); // File excluded.

	/**
	 *  Root path of the site.
	 *
	 * @var ''
	 *  */
	protected $root_path; // Root path of the site.

	/**
	 *  Timeout for the scan.
	 *
	 * @var ''
	 *  */
	protected $timeout; // Timeout for the scan.

	/**
	 *  Start time of the scan.
	 *
	 * @var ''
	 *  */
	protected $start_time; // Start time of the scan.

	/**
	 * Object of the current file.
	 *
	 * @var ''
	 *  */
	protected $instance; // Object of the current file.

	/**
	 *  Number of the records.
	 *
	 * @var ''
	 *  */
	protected $insert_rows = 500; // Number of the records.

	/**
	 * Ui_Big_File_Uploads_File_Scan constructor.
	 *
	 * @param string $root_path  The full path of the directory to iterate.
	 * @param float  $timeout    Timeout in seconds.
	 * @param array  $paths_left Provide as returned if continuing the filelist after a timeout.
	 */
	public function __construct( $root_path, $timeout = 25.0, $paths_left = array() ) {
		$this->root_path  = rtrim( $root_path, '/' );
		$this->timeout    = $timeout;
		$this->paths_left = $paths_left;
	$this->instance   = null; // BigFileUploads dependency removed
	}

	/**
	 * Runs over the site's files.
	 */
	public function start() {
		$this->start_time = microtime( true );

		// If just starting reset the local DB list storage.
		if ( empty( $this->paths_left ) ) {
			update_site_option(
				'uimptr_file_scan',
				array(
					'scan_finished' => false,
					'types'         => array(),
				)
			);
		} else {
			$this->type_list = get_site_option( 'uimptr_file_scan' );
		}

		$this->get_files();

		$this->flush_to_db();

		if ( empty( $this->paths_left ) ) {
			// So we are done. Say so.
			$this->is_done = true;

			$this->type_list['scan_finished'] = time();
			update_site_option( 'uimptr_file_scan', $this->type_list );
		}
	}

	/**
	 * Get total count of files scanned so far.
	 *
	 * @return int
	 */
	public function get_total_files() {
		if ( isset( $this->type_list['types'] ) ) {
			return array_sum( wp_list_pluck( $this->type_list['types'], 'files' ) );
		} else {
			return 0;
		}
	}

	/**
	 * Get total size of files scanned so far.
	 *
	 * @return int
	 */
	public function get_total_size() {
		if ( isset( $this->type_list['types'] ) ) {
			return array_sum( wp_list_pluck( $this->type_list['types'], 'size' ) );
		} else {
			return 0;
		}
	}

	/**
	 * Runs a breadth-first iteration on all files and gathers the relevant info for each one.
	 *
	 * @todo test what happens if some files have no read permissions.
	 */
	protected function get_files() {

		$paths = ( empty( $this->paths_left ) ) ? array( $this->root_path ) : $this->paths_left;

		while ( ! empty( $paths ) ) {
			$path = array_pop( $paths );

			// Skip ".." items.
			if ( preg_match( '/\.\.(\/|\\\\|$)/', $path ) ) {
				continue;
			}

			if ( 0 !== strpos( $path, $this->root_path ) ) {
				// Build the absolute path in case it's not the first iteration.
				$path = rtrim( $this->root_path, '/' ) . $path;
			}

			if ( $this->is_excluded( $path ) ) {
				continue;
			}

			$contents = defined( 'GLOB_BRACE' )
				? glob( trailingslashit( $path ) . '{,.}[!.,!..]*', GLOB_BRACE )
				: glob( trailingslashit( $path ) . '[!.,!..]*' );

			foreach ( $contents as $item ) {
				if ( is_link( $item ) || $this->is_excluded( $item ) ) {
					continue;
				} elseif ( is_file( $item ) ) {
					if ( is_readable( $item ) ) {
						$this->add_file( $this->get_file_info( $item ) );
					}
				} elseif ( is_dir( $item ) ) {
					if ( ! in_array( $item, $paths, true ) ) {
						$paths[] = $this->relative_path( $item );
					}
				}
			}
			$this->paths_left = $paths;

			// If we have exceed the imposed time limit, lets pause the iteration here.
			if ( $this->has_exceeded_timelimit() ) {
				break;
			}
		}

		$this->is_done = false;
	}

	/**
	 * Checks path against excluded pattern.
	 *
	 * @param string $path path to the file.
	 */
	protected function is_excluded( $path ) {
		/**
		 * Filters the built in list of file/directory exclusions that should not be synced to the Infinite Uploads cloud. Be specific it's a simple strpos() search for the strings.
		 *
		 * @param  {array}  $exclusions  A list of file or directory names in the format of `/do-not-sync-this-dir/` or `somefilename.ext`.
		 *
		 * @hook   uimptr_sync_exclusions
		 */
		$exclusions = apply_filters( 'uimptr_sync_exclusions', $this->exclusions );
		foreach ( $exclusions as $string ) {
			if ( false !== strpos( $path, $string ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks file health and returns as many info as it can.
	 *
	 * @param string $item The file to be investigated.
	 *
	 * @return mixed File info or false for failure.
	 */
	protected function get_file_info( $item ) {
		$file         = array();
		$file['size'] = filesize( $item );
	$file['type'] = is_object($this->instance) && method_exists($this->instance, 'get_file_type') ? $this->instance->get_file_type( $item ) : 'other';

		if ( empty( $file['size'] ) ) {
			return false;
		}

		return $file;
	}

	/**
	 * Returns rel path of file/dir, relative to site root.
	 *
	 * @param string $item File's absolute path.
	 *
	 * @return string
	 */
	protected function relative_path( $item ) {
		// Retrieve the relative to the site root path of the file.
		$pos = strpos( $item, $this->root_path );
		if ( 0 === $pos ) {
			return substr_replace( $item, '', $pos, strlen( $this->root_path ) );
		}

		return $item;
	}

	/**
	 * Add file details to internal storage.
	 *
	 * @param file $file file to be added.
	 */
	protected function add_file( $file ) {
		if ( ! $file ) {
			return;
		}

		if ( isset( $this->type_list['types'][ $file['type'] ] ) ) {
			$type = $this->type_list['types'][ $file['type'] ];
		} else {
			$type = (object) array(
				'size'  => 0,
				'files' => 0,
			);
		}

		$type->size += $file['size'];
		$type->files++;

		$this->type_list['types'][ $file['type'] ] = $type;
	}

	/**
	 * Write the queued file list to DB storage.
	 */
	protected function flush_to_db() {
		update_site_option( 'uimptr_file_scan', $this->type_list );
	}

	/**
	 * Checks if current iteration has exceeded the given time limit.
	 *
	 * @return bool True if we have exceeded the time limit, false if we haven't.
	 */
	protected function has_exceeded_timelimit() {
		$current_time = microtime( true );
		$time_diff    = number_format( $current_time - $this->start_time, 2 );

		$has_exceeded_timelimit = ! empty( $this->timeout ) && ( $time_diff > $this->timeout );

		return $has_exceeded_timelimit;
	}
}
