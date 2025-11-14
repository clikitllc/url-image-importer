<?php
/**
 * Quick test script to verify SVG import functionality
 * Run this from WordPress admin or via WP CLI
 */

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    // If running standalone, load WordPress
    require_once('../../../../../wp-load.php');
}

// Test SVG URL (simple test SVG)
$test_svg_url = 'data:image/svg+xml;base64,' . base64_encode('
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="40" fill="blue" stroke="black" stroke-width="2"/>
  <text x="50" y="55" text-anchor="middle" font-family="Arial" font-size="14" fill="white">TEST</text>
</svg>
');

echo "<h2>Testing SVG Import</h2>\n";

// Check if SVG mime type is allowed
$allowed_mimes = get_allowed_mime_types();
echo "<p><strong>SVG allowed in WordPress:</strong> ";
if (in_array('image/svg+xml', $allowed_mimes) || isset($allowed_mimes['svg'])) {
    echo "✅ Yes</p>\n";
} else {
    echo "❌ No</p>\n";
}

// Test the import function
if (function_exists('uimptr_import_image_from_url')) {
    echo "<p><strong>Testing SVG import...</strong></p>\n";
    
    $result = uimptr_import_image_from_url($test_svg_url, null, array('title' => 'Test SVG Import'));
    
    if (is_wp_error($result)) {
        echo "<p>❌ <strong>Import failed:</strong> " . $result->get_error_message() . "</p>\n";
        echo "<p><strong>Error code:</strong> " . $result->get_error_code() . "</p>\n";
    } else {
        echo "<p>✅ <strong>Import successful!</strong> Attachment ID: " . $result . "</p>\n";
        $url = wp_get_attachment_url($result);
        echo "<p><strong>Image URL:</strong> <a href='" . esc_url($url) . "' target='_blank'>" . esc_url($url) . "</a></p>\n";
    }
} else {
    echo "<p>❌ <strong>Import function not found</strong></p>\n";
}

echo "<p><em>Test completed. You can delete this file after testing.</em></p>\n";
?>