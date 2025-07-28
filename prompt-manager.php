<?php
/**
 * Plugin Name: Prompt Manager
 * Description: Manage, publish, and protect AI-generated prompts with advanced NSFW content handling
 * Version: 2.2.0
 * Author: Your Name
 * Text Domain: prompt-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PROMPT_MANAGER_VERSION', '2.2.0');
define('PROMPT_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PROMPT_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROMPT_MANAGER_LOGS_DIR', PROMPT_MANAGER_PLUGIN_DIR . 'logs/');

// Include required files
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/template-functions.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/image-protection.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/image-blur.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/watermark-fix.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/nsfw-frontend.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/analytics.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/settings.php';
require_once PROMPT_MANAGER_PLUGIN_DIR . 'includes/blocks.php';

class PromptManager {
    
    private $settings;
    private $analytics;
    private $watermark;
    private $blur_processor;
    private $blocks;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers - ALL METHODS DEFINED
        add_action('wp_ajax_reblur_prompt', array($this, 'ajax_reblur_prompt'));
        add_action('wp_ajax_bulk_nsfw_action', array($this, 'ajax_bulk_nsfw_action'));
        add_action('wp_ajax_toggle_watermark', array($this, 'ajax_toggle_watermark'));
        add_action('wp_ajax_cleanup_prompt_database', array($this, 'ajax_cleanup_prompt_database'));
        add_action('wp_ajax_reset_prompt_settings', array($this, 'ajax_reset_prompt_settings'));
        add_action('wp_ajax_get_blur_status', array($this, 'ajax_get_blur_status'));
        add_action('wp_ajax_stop_blur_processing', array($this, 'ajax_stop_blur_processing'));
        add_action('wp_ajax_process_blur_batch', array($this, 'ajax_process_blur_batch'));
        add_action('wp_ajax_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        
        // Shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // Image protection hooks
        add_action('wp_ajax_nopriv_get_protected_image', array($this, 'serve_protected_image'));
        add_action('wp_ajax_get_protected_image', array($this, 'serve_protected_image'));
        add_action('template_redirect', array($this, 'intercept_image_requests'));
        
        // Enhanced block content filtering
        add_filter('render_block', array($this, 'filter_all_blocks'), 5, 2);
        add_filter('the_content', array($this, 'filter_post_content_comprehensive'), 5);
        
        // Jetpack specific filters
        add_filter('jetpack_photon_skip_image', array($this, 'skip_jetpack_photon'), 10, 3);
        add_action('wp_head', array($this, 'add_jetpack_protection_script'));
        
        // Initialize components
        $this->blocks = new PromptManagerBlocks();
        $this->settings = new PromptManagerSettings();
        $this->analytics = new PromptManagerAnalytics();
        $this->watermark = new PromptManagerWatermarkFixed();
        
        // Prevent direct image access
        add_action('init', array($this, 'setup_image_protection'));
        
        // Background processing
        add_action('prompt_manager_process_blur', array($this, 'background_process_blur'));
    }
    
    /**
     * Background processing of blur generation
     */
    public function background_process_blur($post_id) {
        $blur_processor = new PromptManagerImageBlur();
        $blur_processor->generate_blurred_images($post_id);
    }
    
    /**
     * Enhanced block filtering for all block types including Jetpack
     */
    public function filter_all_blocks($block_content, $block) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return $block_content;
        }
        
        // Only log once per page load to avoid duplicate entries
        static $logged_posts = array();
        if (!in_array($post->ID, $logged_posts)) {
            $this->analytics->log_image_access($post->ID, 'page_view', array(
                'block_type' => $block['blockName'],
                'user_logged_in' => is_user_logged_in(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $this->get_client_ip()
            ));
            $logged_posts[] = $post->ID;
        }
        
        // Always filter content to catch any images, regardless of block type
        $filtered_content = $this->replace_all_images_in_content($block_content, $post->ID);
        
        // Special handling for Jetpack tiled gallery
        if ($block['blockName'] === 'jetpack/tiled-gallery') {
            $filtered_content = $this->handle_jetpack_tiled_gallery($filtered_content, $post->ID, $block);
        }
        
        return $filtered_content;
    }
    
    /**
     * Handle Jetpack Tiled Gallery specifically
     */
    private function handle_jetpack_tiled_gallery($content, $post_id, $block) {
        // Extract image IDs from Jetpack tiled gallery block
        if (isset($block['attrs']['ids']) && is_array($block['attrs']['ids'])) {
            foreach ($block['attrs']['ids'] as $image_id) {
                $original_url = wp_get_attachment_url($image_id);
                $protected_url = $this->get_protected_url($image_id, $post_id);
                
                // Replace all occurrences of the original URL
                $content = str_replace($original_url, $protected_url, $content);
                
                // Also replace different sizes
                $sizes = array('thumbnail', 'medium', 'large', 'full');
                foreach ($sizes as $size) {
                    $image_data = wp_get_attachment_image_src($image_id, $size);
                    if ($image_data && $image_data[0] !== $protected_url) {
                        $content = str_replace($image_data[0], $protected_url, $content);
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Skip Jetpack Photon for NSFW images
     */
    public function skip_jetpack_photon($skip, $src, $tag) {
        // Extract attachment ID from src
        $attachment_id = attachment_url_to_postid($src);
        if ($attachment_id && $this->get_nsfw_post_for_attachment($attachment_id)) {
            return true; // Skip Photon processing for NSFW images
        }
        return $skip;
    }
    
    /**
     * Add JavaScript protection for Jetpack and other dynamic content
     */
    public function add_jetpack_protection_script() {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            // Protection for dynamically loaded images
            function protectDynamicImages() {
                var images = document.querySelectorAll('img[src*="<?php echo esc_js($this->get_upload_base_url()); ?>"]');
                images.forEach(function(img) {
                    var src = img.src;
                    var attachmentId = extractAttachmentId(src);
                    
                    if (attachmentId && !src.includes('prompt_image=')) {
                        // Replace with protected URL
                        var protectedUrl = '<?php echo home_url(); ?>?prompt_image=' + attachmentId + '&post_id=<?php echo $post->ID; ?>&nonce=<?php echo wp_create_nonce('prompt_image_dynamic'); ?>';
                        img.src = protectedUrl;
                        
                        // Also update srcset if present
                        if (img.srcset) {
                            img.srcset = protectedUrl;
                        }
                    }
                });
            }
            
            function extractAttachmentId(url) {
                // Try to extract from wp-image class first
                var img = document.querySelector('img[src="' + url + '"]');
                if (img && img.className) {
                    var match = img.className.match(/wp-image-(\d+)/);
                    if (match) return match[1];
                }
                
                return null;
            }
            
            // Run protection on page load
            document.addEventListener('DOMContentLoaded', protectDynamicImages);
            
            // Run protection when new content is loaded (for AJAX/dynamic content)
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        setTimeout(protectDynamicImages, 100);
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Jetpack specific protection
            if (typeof jQuery !== 'undefined') {
                jQuery(document).on('jetpack-lazy-images-load', protectDynamicImages);
                jQuery(document).on('jetpack-slideshow-loaded', protectDynamicImages);
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Comprehensive content filtering
     */
    public function filter_post_content_comprehensive($content) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return $content;
        }
        
        return $this->replace_all_images_in_content($content, $post->ID);
    }
    
    /**
     * Replace ALL images in content with protected URLs (enhanced)
     */
    private function replace_all_images_in_content($content, $post_id) {
        // Pattern 1: Images with wp-image class
        $content = preg_replace_callback(
            '/<img[^>]+wp-image-(\d+)[^>]*>/i',
            function($matches) use ($post_id) {
                return $this->replace_image_tag($matches[0], intval($matches[1]), $post_id);
            },
            $content
        );
        
        // Pattern 2: Jetpack data-id attributes
        $content = preg_replace_callback(
            '/<[^>]+data-id=["\'](\d+)["\'][^>]*>/i',
            function($matches) use ($post_id) {
                $attachment_id = intval($matches[1]);
                if ($this->get_nsfw_post_for_attachment($attachment_id)) {
                    $tag = $matches[0];
                    $original_url = wp_get_attachment_url($attachment_id);
                    $protected_url = $this->get_protected_url($attachment_id, $post_id);
                    return str_replace($original_url, $protected_url, $tag);
                }
                return $matches[0];
            },
            $content
        );
        
        // Pattern 3: Any img tag with src pointing to uploads directory
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        
        $content = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']*' . preg_quote($upload_url, '/') . '[^"\']*)["\'][^>]*>/i',
            function($matches) use ($post_id) {
                $img_tag = $matches[0];
                $img_url = $matches[1];
                
                $attachment_id = attachment_url_to_postid($img_url);
                if ($attachment_id && $this->get_nsfw_post_for_attachment($attachment_id)) {
                    return $this->replace_image_tag($img_tag, $attachment_id, $post_id);
                }
                
                return $img_tag;
            },
            $content
        );
        
        // Pattern 4: Background images and other CSS references
        $content = preg_replace_callback(
            '/url$$["\']?([^"\']*' . preg_quote($upload_url, '/') . '[^"\']*)["\']?$$/',
            function($matches) use ($post_id) {
                $img_url = $matches[1];
                $attachment_id = attachment_url_to_postid($img_url);
                
                if ($attachment_id && $this->get_nsfw_post_for_attachment($attachment_id)) {
                    $protected_url = $this->get_protected_url($attachment_id, $post_id);
                    return str_replace($img_url, $protected_url, $matches[0]);
                }
                
                return $matches[0];
            },
            $content
        );
        
        // Pattern 5: Direct URL replacements for all attached images
        $attachments = get_attached_media('image', $post_id);
        foreach ($attachments as $attachment) {
            if (get_post_meta($attachment->ID, '_is_blurred_image', true)) {
                continue;
            }
            
            $original_url = wp_get_attachment_url($attachment->ID);
            $protected_url = $this->get_protected_url($attachment->ID, $post_id);
            
            // Replace the URL wherever it appears
            $content = str_replace($original_url, $protected_url, $content);
            
            // Replace different image sizes
            $sizes = array_merge(get_intermediate_image_sizes(), array('full'));
            foreach ($sizes as $size) {
                $image_data = wp_get_attachment_image_src($attachment->ID, $size);
                if ($image_data && $image_data[0] !== $protected_url) {
                    $content = str_replace($image_data[0], $protected_url, $content);
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Replace individual image tag with enhanced protection
     */
    private function replace_image_tag($img_tag, $attachment_id, $post_id) {
        $protected_url = $this->get_protected_url($attachment_id, $post_id);
        
        // Replace all possible image URL attributes
        $attributes = array('src', 'srcset', 'data-src', 'data-srcset', 'data-lazy-src', 'data-original');
        
        foreach ($attributes as $attr) {
            $img_tag = preg_replace(
                '/' . $attr . '=["\'][^"\']*["\']/',
                $attr . '="' . esc_url($protected_url) . '"',
                $img_tag
            );
        }
        
        return $img_tag;
    }
    
    /**
     * Get upload base URL for JavaScript
     */
    private function get_upload_base_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'];
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
    
    public function init() {
        $this->register_post_type();
        $this->register_taxonomies();
        $this->create_logs_directory();
        $this->setup_image_protection();
        
        // Initialize database tables
        $this->analytics->create_tables();
        $this->watermark->create_watermark_table();

        // Initialize blocks
        if ($this->blocks) {
            $this->blocks->init();
        }

        // Load textdomain
        load_plugin_textdomain('prompt-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        $this->register_post_type();
        $this->register_taxonomies();
        flush_rewrite_rules();
        $this->create_logs_directory();
        $this->setup_image_protection();
        
        // Create database tables
        $this->analytics->create_tables();
        $this->watermark->create_watermark_table();
        
        // Set default settings
        $this->settings->set_defaults();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_logs_directory() {
        if (!file_exists(PROMPT_MANAGER_LOGS_DIR)) {
            wp_mkdir_p(PROMPT_MANAGER_LOGS_DIR);
        }
        
        // Create .htaccess to protect logs
        $htaccess_file = PROMPT_MANAGER_LOGS_DIR . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }
    
    /**
     * Setup image protection system
     */
    public function setup_image_protection() {
        // Create protected images directory
        $upload_dir = wp_upload_dir();
        $protected_dir = $upload_dir['basedir'] . '/prompt-manager-protected/';
        
        if (!file_exists($protected_dir)) {
            wp_mkdir_p($protected_dir);
        }
        
        // Create .htaccess to block direct access to protected images
        $htaccess_content = "
# Prompt Manager - Block direct access to protected images
<Files *>
    Order Deny,Allow
    Deny from all
</Files>

# Allow access only through WordPress
<FilesMatch '\.(jpg|jpeg|png|gif|webp)$'>
    Order Deny,Allow
    Deny from all
</FilesMatch>
";
        
        $htaccess_file = $protected_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    /**
     * Intercept image requests and serve protected versions
     */
    public function intercept_image_requests() {
        if (!isset($_GET['prompt_image'])) {
            return;
        }
        
        $image_id = intval($_GET['prompt_image']);
        $post_id = intval($_GET['post_id']);
        
        if (!$image_id || !$post_id) {
            wp_die('Invalid request', 'Error', array('response' => 403));
        }
        
        // Check if post is NSFW
        $is_nsfw = $this->is_prompt_nsfw($post_id);
        $user_logged_in = is_user_logged_in();
        
        // Log access attempt with correct granted status
        $this->analytics->log_image_access($post_id, 'direct_access', array(
            'image_id' => $image_id,
            'user_logged_in' => $user_logged_in,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'is_nsfw' => $is_nsfw
        ));
        
        if (!$is_nsfw) {
            // Not NSFW, serve original image
            $this->serve_original_image($image_id);
            return;
        }
        
        // NSFW content - check user permissions
        if (!$this->can_view_nsfw()) {
            // User cannot view NSFW, serve blurred version
            $this->serve_blurred_image($post_id, $image_id);
        } else {
            // User can view NSFW, serve original with watermark if enabled
            $this->serve_original_image($image_id, $post_id);
        }
    }
    
    /**
     * Serve original image with optional watermarking
     */
    private function serve_original_image($image_id, $post_id = null) {
        $image_path = get_attached_file($image_id);
        
        if (!$image_path || !file_exists($image_path)) {
            wp_die('Image not found', 'Error', array('response' => 404));
        }
        
        // Apply forensic watermark if enabled and user is logged in
        if ($post_id && is_user_logged_in() && get_post_meta($post_id, '_watermark_enabled', true)) {
            $watermarked_path = $this->watermark->apply_forensic_watermark($image_path, get_current_user_id(), $post_id);
            if ($watermarked_path && $watermarked_path !== $image_path) {
                $image_path = $watermarked_path;
            }
        }
        
        $mime_type = wp_get_image_mime($image_path);
        
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($image_path));
        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        readfile($image_path);
        exit;
    }
    
    /**
     * Serve blurred image
     */
    private function serve_blurred_image($post_id, $image_id) {
        // Get blurred version
        $blurred_id = $this->get_blurred_attachment_id($post_id, $image_id);
        
        if ($blurred_id) {
            $blurred_path = get_attached_file($blurred_id);
            if ($blurred_path && file_exists($blurred_path)) {
                $mime_type = wp_get_image_mime($blurred_path);
                
                header('Content-Type: ' . $mime_type);
                header('Content-Length: ' . filesize($blurred_path));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($blurred_path);
                exit;
            }
        }
        
        // Fallback to default blur image
        $fallback_path = PROMPT_MANAGER_PLUGIN_DIR . 'assets/fallback-blur.png';
        if (file_exists($fallback_path)) {
            header('Content-Type: image/png');
            header('Content-Length: ' . filesize($fallback_path));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            readfile($fallback_path);
        }
        
        exit;
    }
    
    /**
     * Get blurred attachment ID for an image
     */
    private function get_blurred_attachment_id($post_id, $original_id) {
        $mapping = get_post_meta($post_id, '_blurred_image_mapping', true);
        
        if (is_array($mapping)) {
            foreach ($mapping as $map) {
                if ($map['original_id'] == $original_id) {
                    return $map['blurred_id'];
                }
            }
        }
        
        return false;
    }
    
    public function register_post_type() {
        $args = array(
            'labels' => array(
                'name' => 'Prompts',
                'singular_name' => 'Prompt',
                'add_new' => 'Add New Prompt',
                'add_new_item' => 'Add New Prompt',
                'edit_item' => 'Edit Prompt',
                'new_item' => 'New Prompt',
                'view_item' => 'View Prompt',
                'search_items' => 'Search Prompts',
                'not_found' => 'No prompts found',
                'not_found_in_trash' => 'No prompts found in trash'
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'prompts'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-format-chat',
            'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'taxonomies' => array()
        );
        
        register_post_type('prompt', $args);
    }
    
    public function register_taxonomies() {
        // Hierarchical taxonomies
        $hierarchical_taxonomies = array(
            'prompt_category' => 'Prompt Categories',
            'platform' => 'Platforms',
            'model' => 'Models'
        );
        
        foreach ($hierarchical_taxonomies as $taxonomy => $label) {
            register_taxonomy($taxonomy, 'prompt', array(
                'labels' => array(
                    'name' => $label,
                    'singular_name' => rtrim($label, 's'),
                    'search_items' => 'Search ' . $label,
                    'all_items' => 'All ' . $label,
                    'parent_item' => 'Parent ' . rtrim($label, 's'),
                    'parent_item_colon' => 'Parent ' . rtrim($label, 's') . ':',
                    'edit_item' => 'Edit ' . rtrim($label, 's'),
                    'update_item' => 'Update ' . rtrim($label, 's'),
                    'add_new_item' => 'Add New ' . rtrim($label, 's'),
                    'new_item_name' => 'New ' . rtrim($label, 's') . ' Name',
                    'menu_name' => $label
                ),
                'hierarchical' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'show_in_rest' => true,
                'query_var' => true,
                'rewrite' => array('slug' => str_replace('_', '-', $taxonomy))
            ));
        }
        
        // Non-hierarchical taxonomy
        register_taxonomy('prompt_tag', 'prompt', array(
            'labels' => array(
                'name' => 'Prompt Tags',
                'singular_name' => 'Prompt Tag',
                'search_items' => 'Search Prompt Tags',
                'popular_items' => 'Popular Prompt Tags',
                'all_items' => 'All Prompt Tags',
                'edit_item' => 'Edit Prompt Tag',
                'update_item' => 'Update Prompt Tag',
                'add_new_item' => 'Add New Prompt Tag',
                'new_item_name' => 'New Prompt Tag Name',
                'separate_items_with_commas' => 'Separate prompt tags with commas',
                'add_or_remove_items' => 'Add or remove prompt tags',
                'choose_from_most_used' => 'Choose from the most used prompt tags',
                'menu_name' => 'Prompt Tags'
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'prompt-tag')
        ));
        
        add_action('registered_post_type', array($this, 'remove_default_taxonomies'), 10, 2);
    }
    
    public function remove_default_taxonomies($post_type, $post_type_object) {
        if ($post_type === 'prompt') {
            unregister_taxonomy_for_object_type('category', 'prompt');
            unregister_taxonomy_for_object_type('post_tag', 'prompt');
        }
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'prompt_meta_fields',
            'Prompt Details',
            array($this, 'render_meta_fields'),
            'prompt',
            'normal',
            'high'
        );
        
        add_meta_box(
            'prompt_nsfw',
            'NSFW Settings',
            array($this, 'render_nsfw_meta_box'),
            'prompt',
            'side',
            'default'
        );
    }
    
    public function render_meta_fields($post) {
        wp_nonce_field('prompt_meta_nonce', 'prompt_meta_nonce');
        
        $meta_fields = array(
            'prompt_text' => 'Prompt Text',
            'negative_prompt' => 'Negative Prompt',
            'sampler' => 'Sampler',
            'seed' => 'Seed',
            'cfg_scale' => 'CFG Scale',
            'steps' => 'Steps',
            'clip_skip' => 'Clip Skip',
            'aspect_ratio' => 'Aspect Ratio',
            'resolution' => 'Resolution',
            'model_hash' => 'Model Hash',
            'style' => 'Style',
            'used_for' => 'Used For'
        );
        
        echo '<table class="form-table">';
        
        foreach ($meta_fields as $key => $label) {
            $value = get_post_meta($post->ID, '_' . $key, true);
            echo '<tr>';
            echo '<th><label for="' . $key . '">' . $label . '</label></th>';
            echo '<td>';
            
            if (in_array($key, array('prompt_text', 'negative_prompt', 'style', 'used_for'))) {
                echo '<textarea id="' . $key . '" name="' . $key . '" rows="3" cols="50">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input type="text" id="' . $key . '" name="' . $key . '" value="' . esc_attr($value) . '" size="50" />';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        // Free prompt checkbox
        $is_free = get_post_meta($post->ID, '_is_free', true);
        echo '<tr>';
        echo '<th><label for="is_free">Free Prompt</label></th>';
        echo '<td><input type="checkbox" id="is_free" name="is_free" value="1" ' . checked($is_free, 1, false) . ' /></td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    public function render_nsfw_meta_box($post) {
        $is_nsfw = get_post_meta($post->ID, '_nsfw', true);
        $blur_status = $this->get_blur_status($post->ID);
        
        echo '<p>';
        echo '<label for="nsfw_checkbox">';
        echo '<input type="checkbox" id="nsfw_checkbox" name="nsfw" value="1" ' . checked($is_nsfw, 1, false) . ' />';
        echo ' Mark as NSFW';
        echo '</label>';
        echo '</p>';
        
        if ($is_nsfw) {
            echo '<div class="blur-status-info">';
            
            if ($blur_status['queued']) {
                echo '<p><strong>Status:</strong> ⏰ Queued for processing</p>';
            } elseif ($blur_status['processing']) {
                echo '<p><strong>Status:</strong> ⏳ Processing images...</p>';
                echo '<p>Progress: ' . $blur_status['processed_images'] . '/' . $blur_status['total_images'] . ' images</p>';
                echo '<div class="progress-bar"><div class="progress-fill" style="width: ' . ($blur_status['total_images'] > 0 ? ($blur_status['processed_images'] / $blur_status['total_images'] * 100) : 0) . '%"></div></div>';
            } elseif ($blur_status['complete']) {
                echo '<p><strong>Status:</strong> ✅ Images protected</p>';
                echo '<p><strong>Total Images:</strong> ' . $blur_status['total_images'] . '</p>';
                echo '<p><strong>Processed:</strong> ' . $blur_status['processed_images'] . '</p>';
            } else {
                echo '<p><strong>Status:</strong> ⚠️ Protection pending</p>';
            }
            
            // Show access analytics summary
            $access_count = $this->analytics->get_post_access_count($post->ID);
            echo '<p><strong>Access Attempts:</strong> ' . $access_count . '</p>';
            
            echo '</div>';
        }
        
        echo '<p><small><strong>Note:</strong> When NSFW is enabled, blur processing is queued in the background and won\'t block post saving. All images are served through protected URLs with forensic watermarking for logged-in users.</small></p>';
        
        // Add progress bar styles
        echo '<style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        .blur-status-info {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        </style>';
    }
    
    /**
     * Get blur processing status
     */
    private function get_blur_status($post_id) {
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
    
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['prompt_meta_nonce']) || !wp_verify_nonce($_POST['prompt_meta_nonce'], 'prompt_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'prompt') {
            return;
        }
        
        // Save meta fields
        $meta_fields = array(
            'prompt_text', 'negative_prompt', 'sampler', 'seed', 'cfg_scale',
            'steps', 'clip_skip', 'aspect_ratio', 'resolution', 'model_hash',
            'style', 'used_for'
        );
        
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_textarea_field($_POST[$field]));
            }
        }
        
        // Save free prompt checkbox
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        update_post_meta($post_id, '_is_free', $is_free);
        
        // Handle NSFW - this now queues processing instead of running immediately
        $old_nsfw = get_post_meta($post_id, '_nsfw', true);
        $new_nsfw = isset($_POST['nsfw']) ? 1 : 0;
        
        update_post_meta($post_id, '_nsfw', $new_nsfw);
        
        if ($new_nsfw && !$old_nsfw) {
            // NSFW enabled - queue blur processing (handled by image-blur.php)
            // This won't block the save process
        } elseif (!$new_nsfw && $old_nsfw) {
            // NSFW disabled - cleanup
            $this->cleanup_blurred_images($post_id);
        }
    }
    
    /**
     * Cleanup blurred images for a post
     */
    private function cleanup_blurred_images($post_id) {
        $blur_processor = new PromptManagerImageBlur();
        return $blur_processor->cleanup_blurred_images($post_id);
    }
    
    public function add_admin_menu() {
        // Main NSFW Monitor page
        add_submenu_page(
            'edit.php?post_type=prompt',
            'NSFW Monitor',
            'NSFW Monitor',
            'manage_options',
            'nsfw-monitor',
            array($this, 'render_nsfw_monitor_page')
        );
        
        // Analytics page
        add_submenu_page(
            'edit.php?post_type=prompt',
            'Image Analytics',
            'Analytics',
            'manage_options',
            'prompt-analytics',
            array($this->analytics, 'render_analytics_page')
        );
        
        // Settings page
        add_submenu_page(
            'edit.php?post_type=prompt',
            'Settings',
            'Settings',
            'manage_options',
            'prompt-settings',
            array($this->settings, 'render_settings_page')
        );
    }
    
    /**
     * Enhanced NSFW Monitor page with stop functionality
     */
    public function render_nsfw_monitor_page() {
        $nsfw_prompts = get_posts(array(
            'post_type' => 'prompt',
            'meta_query' => array(
                array(
                    'key' => '_nsfw',
                    'value' => '1'
                )
            ),
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        ?>
        <div class="wrap">
            <h1>Enhanced NSFW Monitor</h1>
            
            <div class="nsfw-monitor-header">
                <div class="nsfw-stats">
                    <div class="stat-box">
                        <h3><?php echo count($nsfw_prompts); ?></h3>
                        <p>NSFW Prompts</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $this->analytics->get_total_access_attempts(); ?></h3>
                        <p>Total Access Attempts</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $this->analytics->get_blocked_attempts_today(); ?></h3>
                        <p>Blocked Today</p>
                    </div>
                </div>
                
                <div class="bulk-actions">
                    <select id="bulk-nsfw-action">
                        <option value="">Bulk Actions</option>
                        <option value="regenerate_protection">Regenerate Protection</option>
                        <option value="enable_watermark">Enable Watermarking</option>
                        <option value="disable_watermark">Disable Watermarking</option>
                        <option value="stop_all_processing">Stop All Processing</option>
                    </select>
                    <button class="button" id="apply-bulk-action">Apply</button>
                </div>
            </div>
            
            <?php if (empty($nsfw_prompts)): ?>
                <p>No NSFW prompts found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all-prompts"></th>
                            <th>Title</th>
                            <th>Protection Status</th>
                            <th>Total Images</th>
                            <th>Processed Images</th>
                            <th>Access Attempts</th>
                            <th>Watermarking</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nsfw_prompts as $prompt): ?>
                            <?php
                            $blur_status = $this->get_blur_status($prompt->ID);
                            $access_count = $this->analytics->get_post_access_count($prompt->ID);
                            $watermark_enabled = get_post_meta($prompt->ID, '_watermark_enabled', true);
                            ?>
                            <tr data-post-id="<?php echo $prompt->ID; ?>">
                                <td><input type="checkbox" class="prompt-checkbox" value="<?php echo $prompt->ID; ?>"></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo get_edit_post_link($prompt->ID); ?>">
                                            <?php echo esc_html($prompt->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td class="status-cell">
                                    <?php if ($blur_status['queued']): ?>
                                        <span class="queued">⏰ Queued</span>
                                    <?php elseif ($blur_status['processing']): ?>
                                        <span class="processing">⏳ Processing</span>
                                    <?php elseif ($blur_status['complete']): ?>
                                        <span class="complete">🔒 Protected</span>
                                    <?php else: ?>
                                        <span class="pending">⚠️ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="total-images"><?php echo $blur_status['total_images']; ?></td>
                                <td class="processed-images">
                                    <?php echo $blur_status['processed_images']; ?>
                                    <?php if ($blur_status['processing'] && $blur_status['total_images'] > 0): ?>
                                        <div class="mini-progress-bar">
                                            <div class="mini-progress-fill" style="width: <?php echo ($blur_status['processed_images'] / $blur_status['total_images'] * 100); ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('edit.php?post_type=prompt&page=prompt-analytics&post_id=' . $prompt->ID); ?>">
                                        <?php echo $access_count; ?> attempts
                                    </a>
                                </td>
                                <td>
                                    <?php echo $watermark_enabled ? '✅ Enabled' : '❌ Disabled'; ?>
                                </td>
                                <td>
                                    <?php if ($blur_status['processing']): ?>
                                        <button class="button button-secondary stop-processing-btn" data-post-id="<?php echo $prompt->ID; ?>">
                                            🛑 Stop
                                        </button>
                                    <?php else: ?>
                                        <button class="button reblur-btn" data-post-id="<?php echo $prompt->ID; ?>" <?php echo ($blur_status['processing'] || $blur_status['queued']) ? 'disabled' : ''; ?>>
                                            🔁 Regenerate
                                        </button>
                                    <?php endif; ?>
                                    <button class="button watermark-toggle-btn" data-post-id="<?php echo $prompt->ID; ?>" data-enabled="<?php echo $watermark_enabled ? '1' : '0'; ?>">
                                        <?php echo $watermark_enabled ? '🚫 Disable WM' : '🔒 Enable WM'; ?>
                                    </button>
                                    <span class="action-status" id="status-<?php echo $prompt->ID; ?>"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div class="postbox" style="margin-top: 20px;">
                <h3 class="hndle">Enhanced Protection Features v2.2</h3>
                <div class="inside">
                    <ul>
                        <li>✅ <strong>Full-Screen NSFW Modal:</strong> Completely blocks access with login requirement</li>
                        <li>✅ <strong>Heavy Blur Settings:</strong> Extreme blur makes images completely unrecognizable</li>
                        <li>✅ <strong>Non-Blocking Save:</strong> Post saving doesn't wait for blur processing</li>
                        <li>✅ <strong>Queue Management:</strong> Background processing with stop functionality</li>
                        <li>✅ <strong>Real-time Status:</strong> Live updates of processing progress</li>
                        <li>✅ <strong>Complete Image Protection:</strong> All images (featured, content, attachments) are protected</li>
                        <li>✅ <strong>Forensic Watermarking:</strong> Invisible watermarks track image usage</li>
                        <li>✅ <strong>SEO Protection:</strong> NSFW posts are noindexed across all SEO plugins</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .nsfw-monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .nsfw-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-box {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-box h3 {
            margin: 0;
            font-size: 24px;
            color: #0073aa;
        }
        
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .mini-progress-bar {
            width: 100%;
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .mini-progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        
        .status-cell .processing {
            color: #f56500;
        }
        
        .status-cell .complete {
            color: #46b450;
        }
        
        .status-cell .pending {
            color: #dc3232;
        }
        
        .status-cell .queued {
            color: #0073aa;
        }
        
        .stop-processing-btn {
            background: #dc3232 !important;
            color: white !important;
            border-color: #dc3232 !important;
        }
        
        .stop-processing-btn:hover {
            background: #a00 !important;
            border-color: #a00 !important;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Auto-refresh processing status every 3 seconds
            function refreshProcessingStatus() {
                $('.status-cell .processing, .status-cell .queued').each(function() {
                    var $row = $(this).closest('tr');
                    var postId = $row.data('post-id');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_blur_status',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('blur_status_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var status = response.data;
                                var $statusCell = $row.find('.status-cell');
                                var $totalImages = $row.find('.total-images');
                                var $processedImages = $row.find('.processed-images');
                                var $actionsCell = $row.find('td:last-child');
                                
                                $totalImages.text(status.total_images);
                                
                                if (status.queued) {
                                    $statusCell.html('<span class="queued">⏰ Queued</span>');
                                    $processedImages.text('0');
                                } else if (status.processing) {
                                    $statusCell.html('<span class="processing">⏳ Processing</span>');
                                    var progressHtml = status.processed_images;
                                    if (status.total_images > 0) {
                                        var percentage = (status.processed_images / status.total_images * 100);
                                        progressHtml += '<div class="mini-progress-bar"><div class="mini-progress-fill" style="width: ' + percentage + '%"></div></div>';
                                    }
                                    $processedImages.html(progressHtml);
                                    
                                    // Show stop button
                                    $actionsCell.find('.reblur-btn').hide();
                                    if (!$actionsCell.find('.stop-processing-btn').length) {
                                        $actionsCell.prepend('<button class="button button-secondary stop-processing-btn" data-post-id="' + postId + '">🛑 Stop</button> ');
                                    }
                                } else if (status.complete) {
                                    $statusCell.html('<span class="complete">🔒 Protected</span>');
                                    $processedImages.text(status.processed_images);
                                    
                                    // Show regenerate button
                                    $actionsCell.find('.stop-processing-btn').remove();
                                    $actionsCell.find('.reblur-btn').show().prop('disabled', false);
                                }
                            }
                        }
                    });
                });
            }
            
            // Refresh every 3 seconds if there are processing items
            setInterval(function() {
                if ($('.status-cell .processing, .status-cell .queued').length > 0) {
                    refreshProcessingStatus();
                }
            }, 3000);
            
            // Stop processing button handler (delegated)
            $(document).on('click', '.stop-processing-btn', function() {
                var $button = $(this);
                var postId = $button.data('post-id');
                var $status = $('#status-' + postId);
                
                if (confirm('Are you sure you want to stop processing for this post?')) {
                    $button.prop('disabled', true).text('🛑 Stopping...');
                    $status.text('Stopping...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'stop_blur_processing',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('stop_blur_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.text('✅ Stopped!').css('color', 'green');
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                $status.text('❌ Failed').css('color', 'red');
                                $button.prop('disabled', false).text('🛑 Stop');
                            }
                        },
                        error: function() {
                            $status.text('❌ Error').css('color', 'red');
                            $button.prop('disabled', false).text('🛑 Stop');
                        }
                    });
                }
            });
            
            // Select all functionality
            $('#select-all-prompts').change(function() {
                $('.prompt-checkbox').prop('checked', this.checked);
            });
            
            // Individual regenerate buttons
            $('.reblur-btn').click(function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusSpan = $('#status-' + postId);
                var $row = button.closest('tr');
                
                button.prop('disabled', true);
                statusSpan.text('Queuing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'reblur_prompt',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('reblur_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            statusSpan.text('✅ Queued!').css('color', 'green');
                            $row.find('.status-cell').html('<span class="queued">⏰ Queued</span>');
                            $row.find('.processed-images').html('0');
                            
                            // Start monitoring this row
                            setTimeout(refreshProcessingStatus, 1000);
                        } else {
                            statusSpan.text('❌ Failed').css('color', 'red');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        statusSpan.text('❌ Error').css('color', 'red');
                        button.prop('disabled', false);
                    }
                });
            });
            
            // Watermark toggle buttons
            $('.watermark-toggle-btn').click(function() {
                var button = $(this);
                var postId = button.data('post-id');
                var enabled = button.data('enabled') === '1';
                var statusSpan = $('#status-' + postId);
                
                button.prop('disabled', true);
                statusSpan.text('Processing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'toggle_watermark',
                        post_id: postId,
                        enable: enabled ? '0' : '1',
                        nonce: '<?php echo wp_create_nonce('watermark_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            statusSpan.text('✅ Updated!').css('color', 'green');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            statusSpan.text('❌ Failed').css('color', 'red');
                        }
                    },
                    error: function() {
                        statusSpan.text('❌ Error').css('color', 'red');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
            
            // Bulk actions
            $('#apply-bulk-action').click(function() {
                var action = $('#bulk-nsfw-action').val();
                var selectedPosts = $('.prompt-checkbox:checked').map(function() {
                    return this.value;
                }).get();
                
                if (!action || selectedPosts.length === 0) {
                    alert('Please select an action and at least one prompt.');
                    return;
                }
                
                var confirmMessage = 'Are you sure you want to apply this action to ' + selectedPosts.length + ' prompts?';
                if (action === 'stop_all_processing') {
                    confirmMessage = 'Are you sure you want to stop all processing for ' + selectedPosts.length + ' prompts?';
                }
                
                if (confirm(confirmMessage)) {
                    var $button = $(this);
                    $button.prop('disabled', true).text('Processing...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'bulk_nsfw_action',
                            bulk_action: action,
                            post_ids: selectedPosts,
                            nonce: '<?php echo wp_create_nonce('bulk_nsfw_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Bulk action completed successfully!');
                                location.reload();
                            } else {
                                alert('Bulk action failed: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Bulk action failed due to an error.');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('Apply');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    // Helper Functions
    public function is_prompt_nsfw($post_id = null) {
        if (!$post_id) {
            global $post;
            $post_id = $post ? $post->ID : 0;
        }
        
        return (bool) get_post_meta($post_id, '_nsfw', true);
    }
    
    public function can_view_nsfw() {
        return is_user_logged_in();
    }
    
    private function get_protected_url($attachment_id, $post_id, $size = 'full') {
        return add_query_arg(array(
            'prompt_image' => $attachment_id,
            'post_id' => $post_id,
            'size' => is_array($size) ? implode('x', $size) : $size,
            'nonce' => wp_create_nonce('prompt_image_' . $attachment_id . '_' . $post_id)
        ), home_url());
    }
    
    private function get_nsfw_post_for_attachment($attachment_id) {
        // Check direct parent
        $attachment = get_post($attachment_id);
        if ($attachment && $attachment->post_parent) {
            $parent_post = get_post($attachment->post_parent);
            if ($parent_post && $parent_post->post_type === 'prompt' && $this->is_prompt_nsfw($parent_post->ID)) {
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
        
        return false;
    }
    
    public function register_shortcodes() {
        // Meta field shortcodes
        $meta_fields = array(
            'prompt_text', 'negative_prompt', 'sampler', 'seed', 'cfg_scale',
            'steps', 'clip_skip', 'aspect_ratio', 'resolution', 'model_hash',
            'style', 'used_for', 'is_free'
        );
        
        foreach ($meta_fields as $field) {
            add_shortcode($field, array($this, 'shortcode_meta_field'));
        }
        
        // Taxonomy shortcodes
        $taxonomies = array('prompt_category', 'platform', 'model', 'prompt_tag');
        foreach ($taxonomies as $taxonomy) {
            add_shortcode($taxonomy, array($this, 'shortcode_taxonomy'));
        }
        
        // NSFW shortcodes
        add_shortcode('nsfw', array($this, 'shortcode_nsfw'));
        add_shortcode('protected_image', array($this, 'shortcode_protected_image'));
        add_shortcode('featured_image', array($this, 'shortcode_featured_image'));
    }
    
    public function shortcode_meta_field($atts, $content, $tag) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return '';
        }
        
        $value = get_post_meta($post->ID, '_' . $tag, true);
        
        if ($tag === 'is_free') {
            return $value ? '1' : '';
        }
        
        return esc_html($value);
    }
    
    public function shortcode_taxonomy($atts, $content, $tag) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return '';
        }
        
        $terms = get_the_terms($post->ID, $tag);
        if (!$terms || is_wp_error($terms)) {
            return '';
        }
        
        $term_names = array();
        foreach ($terms as $term) {
            $term_names[] = $term->name;
        }
        
        return esc_html(implode(', ', $term_names));
    }
    
    public function shortcode_nsfw($atts) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return '';
        }
        
        $is_nsfw = get_post_meta($post->ID, '_nsfw', true);
        return $is_nsfw ? '1' : '';
    }
    
    public function shortcode_protected_image($atts) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'attachment_id' => null,
            'size' => 'full',
            'class' => '',
            'alt' => ''
        ), $atts);
        
        $image_id = $atts['attachment_id'] ? intval($atts['attachment_id']) : get_post_thumbnail_id($post->ID);
        
        if (!$image_id) {
            return '';
        }
        
        $protected_url = $this->get_protected_url($image_id, $post->ID, $atts['size']);
        $alt_text = $atts['alt'] ? $atts['alt'] : get_post_meta($image_id, '_wp_attachment_image_alt', true);
        
        return '<img src="' . esc_url($protected_url) . '" class="' . esc_attr($atts['class']) . '" alt="' . esc_attr($alt_text) . '" />';
    }
    
    public function shortcode_featured_image($atts) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'size' => 'full',
            'class' => '',
            'alt' => ''
        ), $atts);
        
        $featured_id = get_post_thumbnail_id($post->ID);
        if (!$featured_id) {
            return '';
        }
        
        $protected_url = $this->get_protected_url($featured_id, $post->ID, $atts['size']);
        $alt_text = $atts['alt'] ? $atts['alt'] : get_post_meta($featured_id, '_wp_attachment_image_alt', true);
        
        return '<img src="' . esc_url($protected_url) . '" class="' . esc_attr($atts['class']) . '" alt="' . esc_attr($alt_text) . '" />';
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'prompt') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('prompt-manager-admin', PROMPT_MANAGER_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PROMPT_MANAGER_VERSION, true);
            wp_localize_script('prompt-manager-admin', 'promptManager', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonces' => array(
                    'reblur' => wp_create_nonce('reblur_nonce'),
                    'watermark' => wp_create_nonce('watermark_nonce'),
                    'bulk' => wp_create_nonce('bulk_nsfw_nonce'),
                    'stop_blur' => wp_create_nonce('stop_blur_nonce')
                )
            ));
        }
    }
    
    public function frontend_enqueue_scripts() {
        wp_enqueue_style('prompt-manager-style', PROMPT_MANAGER_PLUGIN_URL . 'assets/css/style.css', array(), PROMPT_MANAGER_VERSION);
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
        
        $blur_processor = new PromptManagerImageBlur();
        $blur_processor->stop_blur_processing($post_id);
        
        wp_send_json_success('Processing stopped successfully');
    }
    
    /**
     * AJAX handler for toggling watermark
     */
    public function ajax_toggle_watermark() {
        if (!wp_verify_nonce($_POST['nonce'], 'watermark_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $enable = intval($_POST['enable']);
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        update_post_meta($post_id, '_watermark_enabled', $enable);
        
        $message = $enable ? 'Watermarking enabled' : 'Watermarking disabled';
        wp_send_json_success($message);
    }

    /**
     * AJAX handler for database cleanup
     */
    public function ajax_cleanup_prompt_database() {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');

        if (!wp_verify_nonce($nonce, 'cleanup_database')) {
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        $analytics_retention = (int) $this->settings->get_option('analytics_retention_days', 365);
        $log_retention       = (int) $this->settings->get_option('log_retention_days', 90);

        $analytics_table = $wpdb->prefix . 'prompt_image_access';
        $summary_table   = $wpdb->prefix . 'prompt_analytics_summary';

        // Calculate date thresholds in UTC
        $threshold_access_time = (new DateTime('now', new DateTimeZone('UTC')))
            ->modify("-{$analytics_retention} days")
            ->format('Y-m-d H:i:s');

        $threshold_summary_date = (new DateTime('now', new DateTimeZone('UTC')))
            ->modify("-{$analytics_retention} days")
            ->format('Y-m-d');

        // Log for debug
        error_log("[CLEANUP] UTC threshold access_time: $threshold_access_time");
        error_log("[CLEANUP] UTC threshold date_recorded: $threshold_summary_date");

        // Cleanup: image access logs
        $deleted_analytics = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$analytics_table} WHERE access_time < %s",
                $threshold_access_time
            )
        );

        // Cleanup: summary analytics
        $deleted_summary = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$summary_table} WHERE date_recorded < %s",
                $threshold_summary_date
            )
        );

        // Cleanup watermark cache
        $deleted_cache = isset($this->watermark)
            ? (int) $this->watermark->cleanup_old_cache()
            : 0;

        wp_send_json_success("Cleaned $deleted_analytics analytics, $deleted_summary summary, $deleted_cache cached files");
    }


    /**
     * AJAX handler for resetting settings
     */
    public function ajax_reset_prompt_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'reset_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Reset to defaults
        $this->settings->set_defaults();
        
        wp_send_json_success('Settings reset to defaults');
    }

    /**
     * AJAX handler for bulk NSFW actions
     */
    public function ajax_bulk_nsfw_action() {
        if (!wp_verify_nonce($_POST['nonce'], 'bulk_nsfw_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $post_ids = array_map('intval', $_POST['post_ids']);
        
        if (empty($post_ids)) {
            wp_send_json_error('No posts selected');
        }
        
        $processed = 0;
        $blur_processor = new PromptManagerImageBlur();
        
        foreach ($post_ids as $post_id) {
            switch ($action) {
                case 'regenerate_protection':
                    $blur_processor->cleanup_blurred_images($post_id);
                    $blur_processor->generate_blurred_images($post_id);
                    $processed++;
                    break;
                    
                case 'enable_watermark':
                    update_post_meta($post_id, '_watermark_enabled', 1);
                    $processed++;
                    break;
                    
                case 'disable_watermark':
                    update_post_meta($post_id, '_watermark_enabled', 0);
                    $processed++;
                    break;
                    
                case 'stop_all_processing':
                    $blur_processor->stop_blur_processing($post_id);
                    $processed++;
                    break;
            }
        }
        
        wp_send_json_success("Processed $processed posts");
    }

    /**
     * AJAX handler for regenerating blur
     */
    public function ajax_reblur_prompt() {
        if (!wp_verify_nonce($_POST['nonce'], 'reblur_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $blur_processor = new PromptManagerImageBlur();
        
        // Clean up old blurred images
        $blur_processor->cleanup_blurred_images($post_id);
        
        // Queue new blur processing
        update_post_meta($post_id, '_blur_queued', 1);
        update_post_meta($post_id, '_blur_queue_time', time());
        
        // Schedule background processing
        wp_schedule_single_event(time() + 5, 'prompt_manager_process_blur', array($post_id));
        
        wp_send_json_success('Protection regeneration queued successfully');
    }

    /**
     * AJAX handler for getting blur status
     */
    public function ajax_get_blur_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'blur_status_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $status = $this->get_blur_status($post_id);
        wp_send_json_success($status);
    }

    /**
     * AJAX handler for saving settings (delegated to settings class)
     */
    public function ajax_save_prompt_settings() {
        $this->settings->ajax_save_settings();
    }

    /**
     * AJAX handler for processing blur batch (delegated to blur processor)
     */
    public function ajax_process_blur_batch() {
        $blur_processor = new PromptManagerImageBlur();
        $blur_processor->ajax_process_blur_batch();
    }

    /**
     * AJAX handler for getting analytics data (delegated to analytics class)
     */
    public function ajax_get_analytics_data() {
        $this->analytics->ajax_get_analytics_data();
    }

    /**
     * Serve protected image (alias for intercept_image_requests)
     */
    public function serve_protected_image() {
        $this->intercept_image_requests();
    }
}

// Initialize the plugin
new PromptManager();

// Global helper functions
function is_prompt_nsfw($post_id = null) {
    global $prompt_manager;
    if (!$prompt_manager) {
        $prompt_manager = new PromptManager();
    }
    return $prompt_manager->is_prompt_nsfw($post_id);
}

function can_view_nsfw() {
    return is_user_logged_in();
}

function get_protected_image_url($post_id, $image_id = null) {
    if (!$image_id) {
        $image_id = get_post_thumbnail_id($post_id);
    }
    
    return add_query_arg(array(
        'prompt_image' => $image_id,
        'post_id' => $post_id
    ), home_url());
}
