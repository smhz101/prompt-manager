<?php
/**
 * Forensic Watermarking System for Prompt Manager
 * Embeds invisible watermarks to track image usage
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerWatermark {
    
    private $watermark_cache_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->watermark_cache_dir = $upload_dir['basedir'] . '/prompt-manager-watermarks/';
        
        $this->setup_watermark_directory();
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
            return false;
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
        
        if ($watermarked_path) {
            // Log watermark creation
            $this->log_watermark_creation($user_id, $post_id, $watermark_id, $image_path);
            return $watermarked_path;
        }
        
        return false;
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
     * Create watermarked image using multiple techniques
     */
    private function create_watermarked_image($image_path, $watermark_id, $user_id, $post_id) {
        $cached_path = $this->get_cached_watermark_path($image_path, $watermark_id);
        
        // Try different watermarking methods
        $methods = array(
            'steganography' => array($this, 'apply_steganographic_watermark'),
            'metadata' => array($this, 'apply_metadata_watermark'),
            'pixel_modification' => array($this, 'apply_pixel_watermark'),
            'frequency_domain' => array($this, 'apply_frequency_watermark')
        );
        
        foreach ($methods as $method_name => $method) {
            if (call_user_func($method, $image_path, $cached_path, $watermark_id, $user_id, $post_id)) {
                return $cached_path;
            }
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
        $watermark_data = $this->prepare_watermark_data($watermark_id, $user_id, $post_id);
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
                
                // Modify LSB of green channel
                if ($data_index < $data_length) {
                    $g = ($g & 0xFE) | intval($binary_data[$data_index]);
                    $data_index++;
                }
                
                // Modify LSB of blue channel
                if ($data_index < $data_length) {
                    $b = ($b & 0xFE) | intval($binary_data[$data_index]);
                    $data_index++;
                }
                
                $new_color = imagecolorallocate($image, $r, $g, $b);
                imagesetpixel($image, $x, $y, $new_color);
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
     * Apply metadata watermark
     */
    private function apply_metadata_watermark($source_path, $output_path, $watermark_id, $user_id, $post_id) {
        if (!function_exists('exif_read_data')) {
            return false;
        }
        
        // Copy original file
        if (!copy($source_path, $output_path)) {
            return false;
        }
        
        // Prepare watermark metadata
        $watermark_data = array(
            'UserComment' => base64_encode($watermark_id),
            'Artist' => 'PM_' . $user_id,
            'Copyright' => 'Post_' . $post_id,
            'Software' => 'PromptManager_' . time()
        );
        
        // Use Imagick if available for better metadata handling
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($output_path);
                
                foreach ($watermark_data as $key => $value) {
                    $imagick->setImageProperty('exif:' . $key, $value);
                }
                
                $imagick->writeImage($output_path);
                $imagick->destroy();
                return true;
            } catch (Exception $e) {
                error_log('Prompt Manager: Metadata watermark failed: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Apply pixel modification watermark
     */
    private function apply_pixel_watermark($source_path, $output_path, $watermark_id, $user_id, $post_id) {
        $editor = wp_get_image_editor($source_path);
        if (is_wp_error($editor)) {
            return false;
        }
        
        // Get image dimensions
        $size = $editor->get_size();
        $width = $size['width'];
        $height = $size['height'];
        
        // Create watermark pattern based on ID
        $pattern = $this->generate_pixel_pattern($watermark_id, $width, $height);
        
        // Apply subtle modifications to specific pixels
        $image_resource = $editor->get_image();
        if (!$image_resource) {
            return false;
        }
        
        foreach ($pattern as $point) {
            $x = $point['x'];
            $y = $point['y'];
            $modification = $point['mod'];
            
            if ($x < $width && $y < $height) {
                $rgb = imagecolorat($image_resource, $x, $y);
                
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Apply subtle modification (±1 to avoid detection)
                $r = max(0, min(255, $r + $modification));
                $g = max(0, min(255, $g + $modification));
                $b = max(0, min(255, $b + $modification));
                
                $new_color = imagecolorallocate($image_resource, $r, $g, $b);
                imagesetpixel($image_resource, $x, $y, $new_color);
            }
        }
        
        // Save the modified image
        $saved = $editor->save($output_path);
        return !is_wp_error($saved);
    }
    
    /**
     * Apply frequency domain watermark (DCT-based)
     */
    private function apply_frequency_watermark($source_path, $output_path, $watermark_id, $user_id, $post_id) {
        // This is a simplified version - full DCT implementation would be more complex
        if (!extension_loaded('gd')) {
            return false;
        }
        
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        // Load image
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Apply frequency domain modifications
        // This is a simplified approach - real DCT would require more complex math
        $watermark_strength = 2; // Very subtle
        $pattern_seed = crc32($watermark_id);
        
        for ($y = 0; $y < $height; $y += 8) {
            for ($x = 0; $x < $width; $x += 8) {
                // Process 8x8 blocks (DCT block size)
                if (($x + $y + $pattern_seed) % 64 == 0) {
                    // Apply subtle modification to this block
                    for ($by = 0; $by < 8 && ($y + $by) < $height; $by++) {
                        for ($bx = 0; $bx < 8 && ($x + $bx) < $width; $bx++) {
                            $rgb = imagecolorat($image, $x + $bx, $y + $by);
                            
                            $r = ($rgb >> 16) & 0xFF;
                            $g = ($rgb >> 8) & 0xFF;
                            $b = $rgb & 0xFF;
                            
                            // Apply frequency domain modification
                            $mod = (($bx + $by) % 2 == 0) ? $watermark_strength : -$watermark_strength;
                            
                            $r = max(0, min(255, $r + $mod));
                            $g = max(0, min(255, $g + $mod));
                            $b = max(0, min(255, $b + $mod));
                            
                            $new_color = imagecolorallocate($image, $r, $g, $b);
                            imagesetpixel($image, $x + $bx, $y + $by, $new_color);
                        }
                    }
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
        }
        
        imagedestroy($image);
        return $success;
    }
    
    /**
     * Prepare watermark data string
     */
    private function prepare_watermark_data($watermark_id, $user_id, $post_id) {
        $data = array(
            'id' => $watermark_id,
            'user' => $user_id,
            'post' => $post_id,
            'time' => time(),
            'version' => PROMPT_MANAGER_VERSION
        );
        
        return json_encode($data);
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
     * Generate pixel modification pattern
     */
    private function generate_pixel_pattern($watermark_id, $width, $height) {
        $pattern = array();
        $seed = crc32($watermark_id);
        mt_srand($seed);
        
        // Generate 100 random points for modification
        for ($i = 0; $i < 100; $i++) {
            $pattern[] = array(
                'x' => mt_rand(0, $width - 1),
                'y' => mt_rand(0, $height - 1),
                'mod' => mt_rand(-1, 1) // Subtle modification
            );
        }
        
        return $pattern;
    }
    
    /**
     * Log watermark creation
     */
    private function log_watermark_creation($user_id, $post_id, $watermark_id, $image_path) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        $wpdb->insert(
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
    }
    
    /**
     * Extract watermark from image
     */
    public function extract_watermark($image_path) {
        $methods = array(
            'steganography' => array($this, 'extract_steganographic_watermark'),
            'metadata' => array($this, 'extract_metadata_watermark'),
            'pixel_analysis' => array($this, 'extract_pixel_watermark')
        );
        
        foreach ($methods as $method_name => $method) {
            $result = call_user_func($method, $image_path);
            if ($result) {
                return array(
                    'method' => $method_name,
                    'data' => $result
                );
            }
        }
        
        return false;
    }
    
    /**
     * Extract steganographic watermark
     */
    private function extract_steganographic_watermark($image_path) {
        $image_info = getimagesize($image_path);
        if (!$image_info) {
            return false;
        }
        
        // Load image
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($image_path);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Extract LSB data
        $binary_data = '';
        $max_data_length = 1000; // Reasonable limit
        
        for ($y = 0; $y < $height && strlen($binary_data) < $max_data_length * 8; $y++) {
            for ($x = 0; $x < $width && strlen($binary_data) < $max_data_length * 8; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Extract LSBs
                $binary_data .= ($r & 1);
                $binary_data .= ($g & 1);
                $binary_data .= ($b & 1);
            }
        }
        
        imagedestroy($image);
        
        // Convert binary to string and try to decode JSON
        $extracted_string = $this->binary_to_string($binary_data);
        $watermark_data = json_decode($extracted_string, true);
        
        return $watermark_data ? $watermark_data : false;
    }
    
    /**
     * Extract metadata watermark
     */
    private function extract_metadata_watermark($image_path) {
        if (!function_exists('exif_read_data')) {
            return false;
        }
        
        try {
            $exif_data = exif_read_data($image_path);
            
            if ($exif_data && isset($exif_data['UserComment'])) {
                $watermark_id = base64_decode($exif_data['UserComment']);
                
                return array(
                    'watermark_id' => $watermark_id,
                    'artist' => $exif_data['Artist'] ?? null,
                    'copyright' => $exif_data['Copyright'] ?? null,
                    'software' => $exif_data['Software'] ?? null
                );
            }
        } catch (Exception $e) {
            error_log('Prompt Manager: Metadata extraction failed: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Extract pixel watermark (simplified analysis)
     */
    private function extract_pixel_watermark($image_path) {
        // This would require more sophisticated analysis
        // For now, return false as this is complex to implement
        return false;
    }
    
    /**
     * Convert binary to string
     */
    private function binary_to_string($binary) {
        $string = '';
        $length = strlen($binary);
        
        for ($i = 0; $i < $length; $i += 8) {
            $byte = substr($binary, $i, 8);
            if (strlen($byte) == 8) {
                $char = chr(bindec($byte));
                if (ord($char) >= 32 && ord($char) <= 126) { // Printable ASCII
                    $string .= $char;
                } else {
                    break; // Stop at non-printable characters
                }
            }
        }
        
        return $string;
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
     * Create watermark database table
     */
    public function create_watermark_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
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
     * Get watermark information by ID
     */
    public function get_watermark_info($watermark_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE watermark_id = %s",
            $watermark_id
        ));
    }
    
    /**
     * Get all watermarks for a user
     */
    public function get_user_watermarks($user_id, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Get all watermarks for a post
     */
    public function get_post_watermarks($post_id, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_watermarks';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d ORDER BY created_at DESC LIMIT %d",
            $post_id,
            $limit
        ));
    }

    /**
     * Cleanup old watermark cache files
     */
    public function cleanup_old_cache() {
        $cache_duration = 7; // days - could be made configurable
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
