<?php
/**
 * Comprehensive Image Blocker - Prevents ALL direct access to NSFW images
 * Handles every possible way images can be accessed
 */

// Load WordPress with multiple fallback paths
$wp_load_paths = array(
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '../../../../../wp-load.php',
    '../../../../../../wp-load.php'
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists(__DIR__ . '/' . $path)) {
        require_once __DIR__ . '/' . $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded || !defined('ABSPATH')) {
    http_response_code(403);
    die('Access denied - WordPress not loaded');
}

// Get the requested file
$requested_file = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';

if (!$requested_file) {
    http_response_code(404);
    die('File not found');
}

// Security check - prevent directory traversal
if (strpos($requested_file, '..') !== false || strpos($requested_file, '/') === 0) {
    http_response_code(403);
    die('Access denied - Invalid file path');
}

// Get full path to the requested file
$upload_dir = wp_upload_dir();
$file_path = $upload_dir['basedir'] . '/' . $requested_file;

// Verify file exists and is actually an image
if (!file_exists($file_path) || !is_file($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Check if it's actually an image file
$allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff');
$file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_types)) {
    http_response_code(403);
    die('Access denied - Invalid file type');
}

// Get attachment ID from file path
$file_url = $upload_dir['baseurl'] . '/' . $requested_file;
$attachment_id = attachment_url_to_postid($file_url);

// If we can't find attachment ID, try alternative methods
if (!$attachment_id) {
    $attachment_id = get_attachment_id_from_filename($requested_file);
}

if (!$attachment_id) {
    // If we still can't find the attachment, serve the file normally
    // (it might be a non-WordPress image)
    serve_file($file_path);
    exit;
}

// Check if this image belongs to an NSFW prompt
$post_id = get_comprehensive_prompt_id_for_attachment($attachment_id);

if (!$post_id || !is_prompt_nsfw($post_id)) {
    // Not an NSFW image, serve normally
    serve_file($file_path);
    exit;
}

// This is an NSFW image - check if user can view it
if (!is_user_logged_in()) {
    // User not logged in - serve blurred version or block
    serve_blurred_or_block($post_id, $attachment_id);
    exit;
}

// User is logged in - serve original file
serve_file($file_path);

/**
 * Serve a file with proper headers and caching
 */
function serve_file($file_path) {
    // Verify file still exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found');
    }
    
    $mime_type = get_file_mime_type($file_path);
    $file_size = filesize($file_path);
    
    // Set appropriate headers
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $file_size);
    header('Accept-Ranges: bytes');
    
    // Cache headers for logged-in users
    if (is_user_logged_in()) {
        header('Cache-Control: private, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    } else {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Handle range requests for large files
    if (isset($_SERVER['HTTP_RANGE'])) {
        handle_range_request($file_path, $file_size, $mime_type);
    } else {
        readfile($file_path);
    }
}

/**
 * Handle HTTP range requests for large files
 */
function handle_range_request($file_path, $file_size, $mime_type) {
    $range = $_SERVER['HTTP_RANGE'];
    
    if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = intval($matches[1]);
        $end = $matches[2] ? intval($matches[2]) : $file_size - 1;
        
        if ($start > $end || $start >= $file_size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $file_size);
            die('Range not satisfiable');
        }
        
        $length = $end - $start + 1;
        
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
        header('Content-Length: ' . $length);
        
        $file = fopen($file_path, 'rb');
        fseek($file, $start);
        echo fread($file, $length);
        fclose($file);
    }
}

/**
 * Serve blurred version or block access completely
 */
function serve_blurred_or_block($post_id, $attachment_id) {
    // Try to get blurred version
    $blurred_id = null;
    
    if ($attachment_id == get_post_thumbnail_id($post_id)) {
        // Featured image
        $blurred_id = get_post_meta($post_id, '_blurred_image_id', true);
    } else {
        // Attached image - check mapping
        $blurred_mapping = get_post_meta($post_id, '_blurred_image_mapping', true);
        if (is_array($blurred_mapping)) {
            foreach ($blurred_mapping as $mapping) {
                if ($mapping['original_id'] == $attachment_id) {
                    $blurred_id = $mapping['blurred_id'];
                    break;
                }
            }
        }
    }
    
    // Serve blurred version if available
    if ($blurred_id) {
        $blurred_path = get_attached_file($blurred_id);
        if ($blurred_path && file_exists($blurred_path)) {
            // Add headers to indicate this is a blurred version
            header('X-Prompt-Manager: blurred-version');
            header('X-Login-Required: true');
            serve_file($blurred_path);
            return;
        }
    }
    
    // No blurred version available - serve fallback or block completely
    $fallback_path = dirname(__DIR__) . '/assets/fallback-blur.png';
    if (file_exists($fallback_path)) {
        header('X-Prompt-Manager: fallback-blur');
        header('X-Login-Required: true');
        serve_file($fallback_path);
    } else {
        // Complete block with informative message
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>\n<html>\n<head>\n    <title>Access Denied</title>\n    <style>\n        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }\n        .container { background: white; padding: 30px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n        .icon { font-size: 48px; margin-bottom: 20px; }\n        h1 { color: #333; margin-bottom: 10px; }\n        p { color: #666; margin-bottom: 20px; }\n        .login-btn { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }\n        .login-btn:hover { background: #005a87; }\n    </style>\n</head>\n<body>\n    <div class="container">\n        <div class="icon">🔒</div>\n        <h1>NSFW Content - Login Required</h1>\n        <p>This image contains NSFW content and requires login to view.</p>\n        <a href="' . wp_login_url() . '" class="login-btn">Login to View</a>\n    </div>\n</body>\n</html>';
    }
}

/**
 * Get attachment ID from filename (alternative method)
 */
function get_attachment_id_from_filename($filename) {
    global $wpdb;
    
    // Remove any size suffixes (e.g., -150x150, -300x200)
    $base_filename = preg_replace('/-\d+x\d+(?=\.[^.]*$)/', '', $filename);
    
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_wp_attached_file' 
         AND meta_value LIKE %s",
        '%' . $wpdb->esc_like($base_filename)
    ));
    
    return $attachment_id ? intval($attachment_id) : false;
}

/**
 * Get prompt post ID for attachment (comprehensive check)
 */
function get_comprehensive_prompt_id_for_attachment($attachment_id) {
    // Check direct parent
    $attachment = get_post($attachment_id);
    if ($attachment && $attachment->post_parent) {
        $parent_post = get_post($attachment->post_parent);
        if ($parent_post && $parent_post->post_type === 'prompt' && is_prompt_nsfw($parent_post->ID)) {
            return $parent_post->ID;
        }
    }
    
    // Check featured image
    $prompts = get_posts(array(
        'post_type' => 'prompt',
        'meta_query' => array(
            array(
                'key' => '_thumbnail_id',
                'value' => $attachment_id
            ),
            array(
                'key' => '_nsfw',
                'value' => '1'
            )
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));
    
    if (!empty($prompts)) {
        return $prompts[0];
    }
    
    // Check content mentions
    global $wpdb;
    $prompts_with_image = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM $wpdb->posts p
         INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
         WHERE p.post_type = 'prompt'
         AND pm.meta_key = '_nsfw'
         AND pm.meta_value = '1'
         AND p.post_content LIKE %s
         LIMIT 1",
        '%wp-image-' . $attachment_id . '%'
    ));
    
    if ($prompts_with_image) {
        return intval($prompts_with_image);
    }
    
    // Check protected images list
    $prompts_with_protected = get_posts(array(
        'post_type' => 'prompt',
        'meta_query' => array(
            array(
                'key' => '_nsfw',
                'value' => '1'
            ),
            array(
                'key' => '_protected_image_ids',
                'value' => serialize(strval($attachment_id)),
                'compare' => 'LIKE'
            )
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));
    
    if (!empty($prompts_with_protected)) {
        return $prompts_with_protected[0];
    }
    
    return false;
}

/**
 * Get file MIME type
 */
function get_file_mime_type($file_path) {
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        return $mime_type;
    }
    
    // Fallback to extension-based detection
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_types = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff'
    );
    
    return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
}

/**
 * Check if prompt is NSFW
 */
function is_prompt_nsfw($post_id) {
    return (bool) get_post_meta($post_id, '_nsfw', true);
}
