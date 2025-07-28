<?php
/**
 * Enhanced Image Blur Processing for Prompt Manager
 * Handles blur generation with heavy blur settings and queue management
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerImageBlur {
    
    public function __construct() {
        add_action('wp_ajax_process_blur_batch', array($this, 'ajax_process_blur_batch'));
        add_action('wp_ajax_stop_blur_processing', array($this, 'ajax_stop_blur_processing'));
        
        // Hook into post save to queue blur processing
        add_action('save_post', array($this, 'queue_blur_processing'), 20, 2);
        
        // Background processing hook
        add_action('wp_ajax_nopriv_process_blur_queue', array($this, 'process_blur_queue'));
        add_action('wp_ajax_process_blur_queue', array($this, 'process_blur_queue'));
    }
    
    /**
     * Queue blur processing instead of running immediately
     */
    public function queue_blur_processing($post_id, $post) {
        if ($post->post_type !== 'prompt') {
            return;
        }
        
        $is_nsfw = get_post_meta($post_id, '_nsfw', true);
        
        if ($is_nsfw) {
            // Mark as queued for processing
            update_post_meta($post_id, '_blur_queued', 1);
            update_post_meta($post_id, '_blur_queue_time', time());
            
            // Schedule background processing
            wp_schedule_single_event(time() + 5, 'prompt_manager_process_blur', array($post_id));
        }
    }
    
    /**
     * Generate blurred images for a post with heavy blur settings
     */
    public function generate_blurred_images($post_id) {
        // Check if processing should stop
        if (get_post_meta($post_id, '_blur_stop_requested', true)) {
            $this->cleanup_processing_meta($post_id);
            return array('success' => false, 'message' => 'Processing stopped by user');
        }
        
        $images_to_process = $this->get_post_images($post_id);
        $processed_images = array();
        $total_images = count($images_to_process);
        
        if (empty($images_to_process)) {
            update_post_meta($post_id, '_nsfw_blurred', 1);
            update_post_meta($post_id, '_blur_processing_complete', 1);
            $this->cleanup_processing_meta($post_id);
            return array('success' => true, 'processed' => 0, 'total' => 0);
        }
        
        // Mark as processing
        update_post_meta($post_id, '_blur_processing', 1);
        update_post_meta($post_id, '_blur_processing_complete', 0);
        update_post_meta($post_id, '_blur_total_images', $total_images);
        update_post_meta($post_id, '_blur_processed_images', 0);
        update_post_meta($post_id, '_blur_queued', 0);
        
        $processed_count = 0;
        
        foreach ($images_to_process as $image_id) {
            // Check if stop was requested
            if (get_post_meta($post_id, '_blur_stop_requested', true)) {
                $this->cleanup_processing_meta($post_id);
                return array('success' => false, 'message' => 'Processing stopped by user', 'processed' => $processed_count, 'total' => $total_images);
            }
            
            $blurred_id = $this->create_blurred_version($image_id, $post_id);
            
            if ($blurred_id) {
                $processed_images[] = array(
                    'original_id' => $image_id,
                    'blurred_id' => $blurred_id
                );
                $processed_count++;
                
                // Update progress
                update_post_meta($post_id, '_blur_processed_images', $processed_count);
            }
            
            // Small delay to prevent server overload
            usleep(100000); // 0.1 second
        }
        
        // Save mapping and mark as complete
        update_post_meta($post_id, '_blurred_image_mapping', $processed_images);
        update_post_meta($post_id, '_protected_image_ids', array_column($processed_images, 'original_id'));
        update_post_meta($post_id, '_nsfw_blurred', 1);
        update_post_meta($post_id, '_blur_processing_complete', 1);
        $this->cleanup_processing_meta($post_id);
        
        return array(
            'success' => true,
            'processed' => $processed_count,
            'total' => $total_images,
            'images' => $processed_images
        );
    }
    
    /**
     * Stop blur processing
     */
    public function stop_blur_processing($post_id) {
        update_post_meta($post_id, '_blur_stop_requested', 1);
        return true;
    }
    
    /**
     * Cleanup processing metadata
     */
    private function cleanup_processing_meta($post_id) {
        delete_post_meta($post_id, '_blur_processing');
        delete_post_meta($post_id, '_blur_stop_requested');
        delete_post_meta($post_id, '_blur_queued');
        delete_post_meta($post_id, '_blur_queue_time');
    }
    
    /**
     * Get all images associated with a post
     */
    private function get_post_images($post_id) {
        $images = array();
        
        // Featured image
        $featured_id = get_post_thumbnail_id($post_id);
        if ($featured_id) {
            $images[] = $featured_id;
        }
        
        // Images in post content
        $post = get_post($post_id);
        if ($post && $post->post_content) {
            // Extract wp-image classes
            preg_match_all('/wp-image-(\d+)/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $images = array_merge($images, array_map('intval', $matches[1]));
            }
            
            // Extract attachment URLs and convert to IDs
            $upload_dir = wp_upload_dir();
            $upload_url = $upload_dir['baseurl'];
            
            preg_match_all('/src=["\']([^"\']*' . preg_quote($upload_url, '/') . '[^"\']*)["\']/', $post->post_content, $url_matches);
            if (!empty($url_matches[1])) {
                foreach ($url_matches[1] as $url) {
                    $attachment_id = attachment_url_to_postid($url);
                    if ($attachment_id) {
                        $images[] = $attachment_id;
                    }
                }
            }
        }
        
        // Attached media
        $attached_images = get_attached_media('image', $post_id);
        foreach ($attached_images as $attachment) {
            $images[] = $attachment->ID;
        }
        
        // Remove duplicates and invalid IDs
        $images = array_unique(array_filter($images));
        
        // Filter out already blurred images
        $images = array_filter($images, function($id) {
            return !get_post_meta($id, '_is_blurred_image', true);
        });
        
        return array_values($images);
    }
    
    /**
     * Create blurred version of an image with heavy blur
     */
    private function create_blurred_version($image_id, $post_id) {
        $original_path = get_attached_file($image_id);
        if (!$original_path || !file_exists($original_path)) {
            return false;
        }
        
        // Get original image info
        $original_info = wp_get_attachment_metadata($image_id);
        $image_info = getimagesize($original_path);
        
        if (!$image_info) {
            return false;
        }
        
        // Create blurred version with SAME dimensions
        $blurred_path = $this->generate_blurred_image_path($original_path, $post_id);
        
        if ($this->apply_heavy_blur_effect($original_path, $blurred_path, $image_info)) {
            // Insert blurred image as attachment
            $blurred_id = $this->insert_blurred_attachment($blurred_path, $post_id, $image_id);
            
            if ($blurred_id) {
                // Copy metadata to maintain same dimensions
                $blurred_metadata = $original_info;
                if ($blurred_metadata) {
                    // Update file path in metadata
                    $blurred_metadata['file'] = str_replace(
                        basename($original_path),
                        basename($blurred_path),
                        $blurred_metadata['file']
                    );
                    wp_update_attachment_metadata($blurred_id, $blurred_metadata);
                }
                
                // Mark as blurred image
                update_post_meta($blurred_id, '_is_blurred_image', 1);
                update_post_meta($blurred_id, '_original_image_id', $image_id);
                
                return $blurred_id;
            }
        }
        
        return false;
    }
    
    /**
     * Apply heavy blur effect maintaining original dimensions
     */
    private function apply_heavy_blur_effect($source_path, $output_path, $image_info) {
        $width = $image_info[0];
        $height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        // Get blur settings
        $settings = get_option('prompt_manager_options', array());
        $blur_intensity = $settings['blur_intensity'] ?? 'extreme';
        $blur_iterations = $settings['blur_iterations'] ?? 25;
        $blur_factor = $settings['blur_factor'] ?? 0.02;
        
        // Adjust blur settings based on intensity
        switch ($blur_intensity) {
            case 'light':
                $blur_iterations = 10;
                $blur_factor = 0.05;
                break;
            case 'medium':
                $blur_iterations = 15;
                $blur_factor = 0.03;
                break;
            case 'heavy':
                $blur_iterations = 25;
                $blur_factor = 0.02;
                break;
            case 'extreme':
                $blur_iterations = 35;
                $blur_factor = 0.015;
                break;
        }
        
        // Load source image
        switch ($mime_type) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($source_path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $source = imagecreatefromwebp($source_path);
                } else {
                    return false;
                }
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Create output image with SAME dimensions
        $blurred = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG/GIF
        if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
            imagealphablending($blurred, false);
            imagesavealpha($blurred, true);
            $transparent = imagecolorallocatealpha($blurred, 255, 255, 255, 127);
            imagefill($blurred, 0, 0, $transparent);
        }
        
        // Copy source to output
        imagecopy($blurred, $source, 0, 0, 0, 0, $width, $height);
        
        // Apply multiple blur iterations for HEAVY blur effect
        for ($i = 0; $i < $blur_iterations; $i++) {
            imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
        }
        
        // Apply additional blur effects for extreme blurring
        imagefilter($blurred, IMG_FILTER_SELECTIVE_BLUR);
        
        // Apply additional effects to make content unrecognizable
        if ($blur_intensity === 'extreme') {
            // Apply brightness reduction
            imagefilter($blurred, IMG_FILTER_BRIGHTNESS, -30);
            
            // Apply contrast reduction
            imagefilter($blurred, IMG_FILTER_CONTRAST, -20);
            
            // Apply additional gaussian blur passes
            for ($i = 0; $i < 10; $i++) {
                imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
        
        // Save blurred image
        $success = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $success = imagejpeg($blurred, $output_path, 85);
                break;
            case 'image/png':
                $success = imagepng($blurred, $output_path, 9);
                break;
            case 'image/gif':
                $success = imagegif($blurred, $output_path);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    $success = imagewebp($blurred, $output_path, 85);
                }
                break;
        }
        
        // Clean up memory
        imagedestroy($source);
        imagedestroy($blurred);
        
        return $success;
    }
    
    /**
     * Generate path for blurred image
     */
    private function generate_blurred_image_path($original_path, $post_id) {
        $path_info = pathinfo($original_path);
        $upload_dir = wp_upload_dir();
        
        // Create blurred images directory
        $blur_dir = $upload_dir['basedir'] . '/prompt-manager-blurred/';
        if (!file_exists($blur_dir)) {
            wp_mkdir_p($blur_dir);
        }
        
        $filename = $path_info['filename'] . '_blur_' . $post_id . '_' . time() . '.' . $path_info['extension'];
        
        return $blur_dir . $filename;
    }
    
    /**
     * Insert blurred image as WordPress attachment
     */
    private function insert_blurred_attachment($file_path, $post_id, $original_id) {
        $filename = basename($file_path);
        
        // Get original attachment data
        $original_post = get_post($original_id);
        
        $attachment = array(
            'guid' => wp_upload_dir()['url'] . '/' . $filename,
            'post_mime_type' => wp_get_image_mime($file_path),
            'post_title' => 'Blurred: ' . ($original_post ? $original_post->post_title : 'Image'),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        );
        
        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
        
        if ($attach_id) {
            // Generate metadata
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
        }
        
        return $attach_id;
    }
    
    /**
     * Get blur processing status
     */
    public function get_blur_status($post_id) {
        return array(
            'processing' => (bool) get_post_meta($post_id, '_blur_processing', true),
            'complete' => (bool) get_post_meta($post_id, '_blur_processing_complete', true),
            'queued' => (bool) get_post_meta($post_id, '_blur_queued', true),
            'total_images' => (int) get_post_meta($post_id, '_blur_total_images', true),
            'processed_images' => (int) get_post_meta($post_id, '_blur_processed_images', true),
            'blurred' => (bool) get_post_meta($post_id, '_nsfw_blurred', true),
            'stop_requested' => (bool) get_post_meta($post_id, '_blur_stop_requested', true)
        );
    }
    
    /**
     * Cleanup blurred images for a post
     */
    public function cleanup_blurred_images($post_id) {
        $mapping = get_post_meta($post_id, '_blurred_image_mapping', true);
        
        if (is_array($mapping)) {
            foreach ($mapping as $map) {
                if (isset($map['blurred_id'])) {
                    // Delete the blurred attachment
                    wp_delete_attachment($map['blurred_id'], true);
                }
            }
        }
        
        // Clean up metadata
        delete_post_meta($post_id, '_blurred_image_mapping');
        delete_post_meta($post_id, '_protected_image_ids');
        delete_post_meta($post_id, '_nsfw_blurred');
        $this->cleanup_processing_meta($post_id);
        delete_post_meta($post_id, '_blur_processing_complete');
        delete_post_meta($post_id, '_blur_total_images');
        delete_post_meta($post_id, '_blur_processed_images');
    }
    
    /**
     * AJAX handler for batch blur processing
     */
    public function ajax_process_blur_batch() {
        if (!wp_verify_nonce($_POST['nonce'], 'blur_batch_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $result = $this->generate_blurred_images($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message'] ?? 'Blur processing failed');
        }
    }
    
    /**
     * AJAX handler for stopping blur processing
     */
    public function ajax_stop_blur_processing() {
        if (!wp_verify_nonce($_POST['nonce'], 'stop_blur_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $this->stop_blur_processing($post_id);
        wp_send_json_success('Processing stop requested');
    }
    
    /**
     * Background processing of blur queue
     */
    public function process_blur_queue() {
        // Find queued posts
        $queued_posts = get_posts(array(
            'post_type' => 'prompt',
            'meta_query' => array(
                array(
                    'key' => '_blur_queued',
                    'value' => '1'
                )
            ),
            'posts_per_page' => 1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_blur_queue_time',
            'order' => 'ASC'
        ));
        
        if (!empty($queued_posts)) {
            $post_id = $queued_posts[0]->ID;
            $this->generate_blurred_images($post_id);
        }
        
        wp_die(); // Required for AJAX
    }
}

// Initialize blur processor
new PromptManagerImageBlur();
