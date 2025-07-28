<?php
/**
 * WebP Image Handler for Prompt Manager
 * Handles WebP-specific functionality and fallbacks
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerWebPHandler {
    
    public function __construct() {
        add_filter('wp_image_editors', array($this, 'prioritize_image_editors'));
        add_action('admin_notices', array($this, 'webp_support_notice'));
    }
    
    /**
     * Prioritize image editors that support WebP
     */
    public function prioritize_image_editors($editors) {
        // If Imagick supports WebP, prioritize it
        if (class_exists('Imagick') && $this->imagick_supports_webp()) {
            $editors = array_diff($editors, array('WP_Image_Editor_Imagick'));
            array_unshift($editors, 'WP_Image_Editor_Imagick');
        }
        
        return $editors;
    }
    
    /**
     * Check if Imagick supports WebP
     */
    private function imagick_supports_webp() {
        if (!class_exists('Imagick')) {
            return false;
        }
        
        try {
            $imagick = new Imagick();
            $formats = $imagick->queryFormats('WEBP');
            return in_array('WEBP', $formats);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if GD supports WebP
     */
    public function gd_supports_webp() {
        if (!function_exists('gd_info')) {
            return false;
        }
        
        $gd_info = gd_info();
        return isset($gd_info['WebP Support']) && $gd_info['WebP Support'];
    }
    
    /**
     * Get WebP support status
     */
    public function get_webp_support_status() {
        $support = array(
            'gd' => $this->gd_supports_webp(),
            'imagick' => $this->imagick_supports_webp(),
            'overall' => false
        );
        
        $support['overall'] = $support['gd'] || $support['imagick'];
        
        return $support;
    }
    
    /**
     * Display WebP support notice in admin
     */
    public function webp_support_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'prompt') {
            return;
        }
        
        $support = $this->get_webp_support_status();
        
        if (!$support['overall']) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Prompt Manager:</strong> WebP support is not available. WebP images will be converted to JPEG when blurred. Consider enabling WebP support in your server configuration for better performance.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Convert WebP to JPEG if needed
     */
    public function convert_webp_if_needed($image_path, $target_format = 'jpeg') {
        if (!$this->get_webp_support_status()['overall']) {
            return $this->webp_to_jpeg($image_path);
        }
        
        return $image_path;
    }
    
    /**
     * Convert WebP to JPEG using available methods
     */
    private function webp_to_jpeg($webp_path) {
        if (!file_exists($webp_path)) {
            return false;
        }
        
        $path_info = pathinfo($webp_path);
        $jpeg_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.jpg';
        
        // Try Imagick first
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($webp_path);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $imagick->writeImage($jpeg_path);
                $imagick->destroy();
                return $jpeg_path;
            } catch (Exception $e) {
                error_log('Prompt Manager: Imagick WebP conversion failed: ' . $e->getMessage());
            }
        }
        
        // Try GD as fallback
        if (function_exists('imagecreatefromwebp')) {
            try {
                $image = imagecreatefromwebp($webp_path);
                if ($image !== false) {
                    $success = imagejpeg($image, $jpeg_path, 85);
                    imagedestroy($image);
                    if ($success) {
                        return $jpeg_path;
                    }
                }
            } catch (Exception $e) {
                error_log('Prompt Manager: GD WebP conversion failed: ' . $e->getMessage());
            }
        }
        
        return false;
    }
    
    /**
     * Get optimal blur settings based on image type and size
     */
    public function get_optimal_blur_settings($image_path) {
        $image_info = getimagesize($image_path);
        if (!$image_info) {
            return array(
                'iterations' => 3,
                'blur_factor' => 0.15,
                'quality' => 'medium'
            );
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        // Adjust settings based on image size and type
        if ($width > 2000 || $height > 2000) {
            // Large images - more aggressive blur
            return array(
                'iterations' => 4,
                'blur_factor' => 0.12,
                'quality' => 'high'
            );
        } elseif ($width < 500 || $height < 500) {
            // Small images - gentler blur
            return array(
                'iterations' => 2,
                'blur_factor' => 0.2,
                'quality' => 'low'
            );
        } else {
            // Medium images - standard blur
            return array(
                'iterations' => 3,
                'blur_factor' => 0.15,
                'quality' => 'medium'
            );
        }
    }
}

// Initialize WebP handler
new PromptManagerWebPHandler();
