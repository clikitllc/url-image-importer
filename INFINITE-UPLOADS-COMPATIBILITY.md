# Infinite Uploads Compatibility

## Problem
With Infinite Uploads plugin active, temporary XML files were being uploaded to cloud storage (S3, etc.) which is unnecessary and potentially expensive for temporary processing files.

## Solution Implementation

### 1. Local-Only Temporary Storage
- **Primary Location**: System temp directory (`get_temp_dir()`) - usually `/tmp`
- **Fallback Location**: Plugin directory `/temp` subfolder
- **Bypasses**: WordPress uploads directory entirely to avoid cloud sync

### 2. Infinite Uploads Integration
```php
// Automatically excludes temp files from cloud uploads
add_filter( 'infinite_uploads_exclude_file', 'uimptr_exclude_temp_files_from_cloud', 10, 2 );
```

### 3. File Path Strategy
```php
function uimptr_get_local_temp_dir() {
    // 1. Try system temp directory (recommended)
    $wp_temp_dir = get_temp_dir();
    if ( is_writable( $wp_temp_dir ) ) {
        return $wp_temp_dir . 'url-image-importer-temp';
    }
    
    // 2. Fallback to plugin directory
    return UIMPTR_PATH . '/temp';
}
```

### 4. Security Measures
- `.htaccess` prevents direct access
- `index.php` adds extra protection
- Files auto-delete after 2 hours
- Unique random filenames prevent guessing

### 5. Cleanup Strategy
- Hourly WordPress cron cleanup
- Manual cleanup on import completion
- Manual cleanup on import cancellation
- No cloud storage cleanup needed (files never uploaded)

## Benefits
- ✅ **Cost Savings**: No unnecessary cloud storage usage
- ✅ **Performance**: Faster local file access
- ✅ **Security**: Files never leave the server
- ✅ **Reliability**: No dependency on cloud connectivity
- ✅ **Automatic**: Zero configuration required

## File Locations
- **Development**: `/tmp/url-image-importer-temp/`
- **Production**: System temp or plugin `/temp/` directory
- **Never**: WordPress uploads directory (cloud storage)

## Compatibility
- Works with or without Infinite Uploads
- Gracefully handles various server configurations
- Automatic fallback for different temp directory permissions
- Filter hooks prevent accidental cloud uploads

The implementation ensures that XML import files are stored locally only and never uploaded to cloud storage, regardless of Infinite Uploads configuration.