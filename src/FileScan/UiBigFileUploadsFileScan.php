<?php
namespace UrlImageImporter\FileScan;

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Lists files using a Breadth-First search algorithm to allow for time limits and resume across multiple requests.
 */
class UiBigFileUploadsFileScan {
    public $is_done = false;
    public $paths_left = array();
    public $file_count = 0;
    public $type_list = array();
    public $exclusions = array();
    protected $root_path;
    protected $timeout;
    protected $start_time;
    protected $instance;
    protected $insert_rows = 500;

    public function __construct( $root_path, $timeout = 25.0, $paths_left = array() ) {
        $this->root_path  = rtrim( $root_path, '/' );
        $this->timeout    = $timeout;
        $this->paths_left = $paths_left;
        $this->instance   = null; // Remove dependency on BigFileUploads for now
    }

    public function start() {
        $this->start_time = microtime( true );
        if ( empty( $this->paths_left ) ) {
            // Starting a fresh scan - reset all data
            $this->type_list = array(
                'scan_finished' => false,
                'types'         => array(),
            );
            update_site_option( 'uimptr_file_scan', $this->type_list );
        } else {
            // Continuing an existing scan - load cached data
            $cached = get_site_option( 'uimptr_file_scan' );
            $this->type_list = is_array( $cached ) ? $cached : array( 'types' => array() );
        }
        $this->get_files();
        $this->flush_to_db();
        if ( empty( $this->paths_left ) ) {
            $this->is_done = true;
            $this->type_list['scan_finished'] = time();
            update_site_option( 'uimptr_file_scan', $this->type_list );
        }
    }

    public function get_total_files() {
        if ( isset( $this->type_list['types'] ) ) {
            return array_sum( wp_list_pluck( $this->type_list['types'], 'files' ) );
        } else {
            return 0;
        }
    }

    public function get_total_size() {
        if ( isset( $this->type_list['types'] ) ) {
            return array_sum( wp_list_pluck( $this->type_list['types'], 'size' ) );
        } else {
            return 0;
        }
    }

    protected function get_files() {
        $paths = ( empty( $this->paths_left ) ) ? array( $this->root_path ) : $this->paths_left;
        while ( ! empty( $paths ) ) {
            $path = array_pop( $paths );
            if ( preg_match( '/\.\.(\/|\\\\|$)/', $path ) ) {
                continue;
            }
            if ( 0 !== strpos( $path, $this->root_path ) ) {
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
            if ( $this->has_exceeded_timelimit() ) {
                break;
            }
        }
        $this->is_done = false;
    }

    protected function is_excluded( $path ) {
        $exclusions = apply_filters( 'uimptr_sync_exclusions', $this->exclusions );
        foreach ( $exclusions as $string ) {
            if ( false !== strpos( $path, $string ) ) {
                return true;
            }
        }
        return false;
    }

    protected function get_file_info( $item ) {
        $file         = array();
        $file['size'] = filesize( $item );
        $file['type'] = $this->get_file_type( $item ); // Use local method instead
        if ( empty( $file['size'] ) ) {
            return false;
        }
        return $file;
    }

    /**
     * Get the file type category for a given filename.
     *
     * @param string $filename
     * @return string
     */
    public function get_file_type( $filename ) {
        $extensions = [
            'image'    => [ 'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'tif', 'tiff', 'ico', 'svg', 'svgz', 'webp' ],
            'audio'    => [ 'aac', 'ac3', 'aif', 'aiff', 'flac', 'm3a', 'm4a', 'm4b', 'mka', 'mp1', 'mp2', 'mp3', 'ogg', 'oga', 'ram', 'wav', 'wma' ],
            'video'    => [ '3g2', '3gp', '3gpp', 'asf', 'avi', 'divx', 'dv', 'flv', 'm4v', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'mpv', 'ogm', 'ogv', 'qt', 'rm', 'vob', 'wmv', 'webm' ],
            'document' => [
                'log', 'asc', 'csv', 'tsv', 'txt', 'doc', 'docx', 'docm', 'dotm', 'odt', 'pages', 'pdf', 'xps', 'oxps', 'rtf', 'wp', 'wpd', 'psd', 'xcf', 'swf', 'key', 'ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'ppsm', 'sldx', 'sldm', 'odp', 'numbers', 'ods', 'xls', 'xlsx', 'xlsm', 'xlsb',
            ],
            'archive'  => [ 'bz2', 'cab', 'dmg', 'gz', 'rar', 'sea', 'sit', 'sqx', 'tar', 'tgz', 'zip', '7z', 'data', 'bin', 'bak' ],
            'code'     => [ 'css', 'htm', 'html', 'php', 'js', 'md' ],
        ];

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

    protected function relative_path( $item ) {
        $pos = strpos( $item, $this->root_path );
        if ( 0 === $pos ) {
            return substr_replace( $item, '', $pos, strlen( $this->root_path ) );
        }
        return $item;
    }

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

    protected function flush_to_db() {
        update_site_option( 'uimptr_file_scan', $this->type_list );
    }

    protected function has_exceeded_timelimit() {
        $current_time = microtime( true );
        $time_diff    = number_format( $current_time - $this->start_time, 2 );
        $has_exceeded_timelimit = ! empty( $this->timeout ) && ( $time_diff > $this->timeout );
        return $has_exceeded_timelimit;
    }
}
