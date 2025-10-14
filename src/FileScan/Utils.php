<?php
namespace UrlImageImporter\FileScan;

/**
 * File scanning utilities for URL Image Importer
 */
class Utils {
    
    /**
     * Get file types
     *
     * @param bool $local Whether to get local types
     * @return array Array of file types
     */
    public static function get_filetypes( $local = true ) {
        $types = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg', 
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'bmp'  => 'image/bmp',
            'tiff' => 'image/tiff',
            'ico'  => 'image/x-icon'
        );
        
        return $types;
    }
    
    /**
     * Get upload directory root
     *
     * @return string Upload directory path
     */
    public static function get_upload_dir_root() {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] );
    }
}