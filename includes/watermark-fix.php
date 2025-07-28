<?php
/**
 * Fixed Watermarking System for Prompt Manager
 * Ensures watermarks are properly applied and functional
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerWatermarkFixed {
    
    private $watermark_cache_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->watermark_cache_dir = $upload_dir['basedir'] . '/prompt-manager-watermarks/';
        
        $this->setup_watermark_directory();
        $this->create_watermark_table();
    }
    
    /**
     * Setup watermark cache directory
     */
    private function setup_watermark_directory() {
        if (!file_exists($this->watermark_cache_dir)) {
            wp_mkdir_p($this->watermark_cache_dir);
        }
        
        // Protect directory
        $htaccess_content = "
# Prompt Manager Watermark Cache Protection
<Files *>
    Order Deny,Allow
    Deny from all
</Files>
";
        
        $htaccess_file = $this->watermark_cache_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    /**
     * Apply forensic watermark to image
     */
    public function apply_forensic_watermark($image_path, $user_id, $post_id) {
        if (!file_exists($image_path)) {
            error_log("Prompt Manager Watermark: Source image not found: $image_path");
            return false;
        }
        
        // Check if watermarking is enabled for this post
        if (!get_post_meta($post_id, '_watermark_enabled', true)) {
            return $image_path; // Return original if watermarking disabled
        }
        
        // Generate unique watermark identifier
        $watermark_id = $this->generate_watermark_id($user_id, $post_id);
        
        // Check if watermarked version already exists
        $cached_path = $this->get_cached_watermark_path($image_path, $watermark_id);
        if (file_exists($cached_path)) {
            return $cached_path;
        }
        
        // Create watermarked version
        $watermarked_path = $this->create_watermarked_image($image_path, $watermark_id, $user_id, $post_id);
        
        if ($watermarked_path && file_exists($watermarked_path)) {
            // Log watermark creation
            $this->log_watermark_creation($user_id, $post_id, $watermark_id, $image_path);
            error_log("Prompt Manager Watermark: Successfully created watermark: $watermarked_path");
            return $watermarked_path;
        }
        
        error_log("Prompt Manager Watermark: Failed to create watermark for: $image_path");
        return $image_path; // Return original if watermarking fails
    }
    
    /**
     * Generate unique watermark identifier
     */
    private function generate_watermark_id($user_id, $post_id) {
        $timestamp = time();
        $random = wp_generate_password(8, false);
        $data = $user_id . '|' . $post_id . '|' . $timestamp . '|' . $random;
        
        return hash('sha256', $data);
    }
    
    /**
     * Get cached watermark path
     */
    private function get_cached_watermark_path($original_path, $watermark_id) {
        $filename = basename($original_path);
        $file_info = pathinfo($filename);
        
        $cached_filename = $file_info['filename'] . '_wm_' . substr($watermark_id, 0, 16) . '.' . $file_info['extension'];
        
        return $this->watermark_cache_dir . $cached_filename;
    }
    
    /**
     * Create watermarked image using steganography
     */
    private function create_watermarked_image($image_path, $watermark_id, $user_id, $post_id) {
        $cached_path = $this->get_cached_watermark_path($image_path, $watermark_id);
        
        // Use steganographic watermarking (most reliable)
        if ($this->apply_steganographic_watermark($image_path, $cached_path, $watermark_id, $user_id, $post_id)) {
            return $cached_path;
        }
        
        // Fallback to simple copy with metadata
        if (copy($image_path, $cached_path)) {
            $this->add_metadata_watermark($cached_path, $watermark_id, $user_id, $post_id);
            return $cached_path;
        }
        
        return false;
    }
    
    /**
     * Apply steganographic watermark (LSB method)
     */
    private function apply_steganographic_watermark($source_path, $output_path, $watermark_id, $user_id, $post_id) {
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        // Load image based on type
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Prepare watermark data
        $watermark_data = json_encode(array(
            'id' => $watermark_id,
            'user' => $user_id,
            'post' => $post_id,
            'time' => time(),
            'version' => PROMPT_MANAGER_VERSION
        ));
        
        $binary_data = $this->string_to_binary($watermark_data);
        
        // Embed watermark using LSB technique
        $data_index = 0;
        $data_length = strlen($binary_data);
        
        for ($y = 0; $y < $height && $data_index < $data_length; $y++) {
            for ($x = 0; $x < $width && $data_index < $data_length; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Modify LSB of red channel
                if ($data_index < $data_length) {
                    $r = ($r & 0xFE) | intval($binary_data[$data_index]);
                    $data_index++;
                }
                
                $new_color = imagecolorallocate($image, $r, $g, $b);
                if ($new_color !== false) {
                    imagesetpixel($image, $x, $y, $new_color);
                }
            }
        }
        
        // Save watermarked image
        $success = false;
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $success = imagejpeg($image, $output_path, 95);
                break;
            case 'image/png':
                $success = imagepng($image, $output_path, 9);
                break;
            case 'image/gif':
                $success = imagegif($image, $output_path);
                break;
        }
        
        imagedestroy($image);
        return $success;
    }
    
    /**
     * Add metadata watermark as fallback
     */
    private function add_metadata_watermark($image_path, $watermark_id, $user_id, $post_id) {
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($image_path);
                $imagick->setImageProperty('exif:UserComment', base64_encode($watermark_id));
                $imagick->setImageProperty('exif:Artist', 'PM_' . $user_id);
                $imagick->setImageProperty('exif:Copyright', 'Post_' . $post_id);
                $imagick->writeImage($image_path);
                $imagick->destroy();
                return true;
            } catch (Exception $e) {
                error_log('Prompt Manager: Metadata watermark failed: ' . $e->getMessage());
            }
        }
        return false;
    }
    
    /**
     * Convert string to binary
     */
    private function string_to_binary($string) {
        $binary = '';
        $length = strlen($string);
        
        for ($i = 0; $i < $length; $i++) {
            $binary .= sprintf('%08b', ord($string[$i]));
        }
        
        return $binary;
    }
    
    /**
     * Log watermark creation
     */
    private function log_watermark_creation($user_id, $post_id, $watermark_id, $image_path) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'watermark_id' => $watermark_id,
                'user_id' => $user_id,
                'post_id' => $post_id,
                'image_path' => $image_path,
                'created_at' => current_time('mysql'),
                'ip_address' => $this->get_client_ip()
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Prompt Manager: Failed to log watermark creation: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Create watermark database table
     */
    public function create_watermark_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            watermark_id varchar(64) NOT NULL,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            image_path text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY watermark_id (watermark_id),
            KEY user_id (user_id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Cleanup old watermark cache files
     */
    public function cleanup_old_cache() {
        $cache_duration = 7; // days
        $cutoff_time = time() - ($cache_duration * 24 * 60 * 60);
        
        if (!is_dir($this->watermark_cache_dir)) {
            return 0;
        }
        
        $files = glob($this->watermark_cache_dir . '*');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}

// Replace the old watermark class
global $prompt_manager_watermark_fixed;
$prompt_manager_watermark_fixed = new PromptManagerWatermarkFixed();
