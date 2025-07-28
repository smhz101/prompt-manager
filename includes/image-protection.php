<?php
/**
 * Comprehensive Image Protection System for Prompt Manager
 * Protects ALL images in NSFW posts regardless of display method
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerImageProtection {
    
    public function __construct() {
        // Core WordPress image hooks
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
        add_filter('wp_calculate_image_srcset', array($this, 'filter_image_srcset'), 10, 5);
        add_filter('wp_get_attachment_image', array($this, 'filter_attachment_image_html'), 10, 5);
        add_filter('wp_get_attachment_link', array($this, 'filter_attachment_link'), 10, 6);
        
        // Content and block filters
        add_filter('the_content', array($this, 'filter_post_content'), 20);
        add_filter('post_thumbnail_html', array($this, 'filter_thumbnail_html'), 10, 5);
        add_filter('render_block', array($this, 'filter_block_content'), 10, 2);
        
        // Gallery and media filters
        add_filter('get_post_gallery', array($this, 'filter_post_gallery'), 10, 3);
        add_filter('wp_get_attachment_metadata', array($this, 'filter_attachment_metadata'), 10, 2);
        
        // Shortcode filters
        add_filter('img_caption_shortcode', array($this, 'filter_caption_shortcode'), 10, 3);
        add_filter('wp_video_shortcode', array($this, 'filter_video_shortcode'), 10, 5);
        
        // REST API and AJAX protection
        add_filter('rest_prepare_attachment', array($this, 'filter_rest_attachment'), 10, 3);
        add_action('wp_ajax_query-attachments', array($this, 'filter_ajax_attachments'), 1);
        add_action('wp_ajax_nopriv_query-attachments', array($this, 'filter_ajax_attachments'), 1);
        
        // Admin and media library protection
        add_action('pre_get_posts', array($this, 'filter_media_queries'));
        
        // Setup file system protection
        add_action('init', array($this, 'setup_comprehensive_protection'));
    }
    
    /**
     * Setup comprehensive file system protection
     */
    public function setup_comprehensive_protection() {
        $this->setup_htaccess_protection();
        $this->setup_nginx_protection();
    }
    
    /**
     * Filter all attachment URLs
     */
    public function filter_attachment_url($url, $attachment_id) {
        $post_id = $this->get_nsfw_post_for_attachment($attachment_id);
        
        if ($post_id && !$this->can_view_nsfw()) {
            return $this->get_protected_url($attachment_id, $post_id);
        }
        
        return $url;
    }
    
    /**
     * Filter attachment image src arrays
     */
    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        if (!$image) return $image;
        
        $post_id = $this->get_nsfw_post_for_attachment($attachment_id);
        
        if ($post_id && !$this->can_view_nsfw()) {
            $image[0] = $this->get_protected_url($attachment_id, $post_id, $size);
        }
        
        return $image;
    }
    
    /**
     * Filter image srcsets comprehensively
     */
    public function filter_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        $post_id = $this->get_nsfw_post_for_attachment($attachment_id);
        
        if ($post_id && !$this->can_view_nsfw()) {
            foreach ($sources as $width => $source) {
                $sources[$width]['url'] = $this->get_protected_url($attachment_id, $post_id);
            }
        }
        
        return $sources;
    }
    
    /**
     * Filter attachment image HTML completely
     */
    public function filter_attachment_image_html($html, $attachment_id, $size, $icon, $attr) {
        $post_id = $this->get_nsfw_post_for_attachment($attachment_id);
        
        if ($post_id && !$this->can_view_nsfw()) {
            $protected_url = $this->get_protected_url($attachment_id, $post_id, $size);
            
            // Replace src
            $html = preg_replace('/src=["\'][^"\']*["\']/', 'src="' . esc_url($protected_url) . '"', $html);
            
            // Replace srcset
            $html = preg_replace('/srcset=["\'][^"\']*["\']/', 'srcset="' . esc_url($protected_url) . '"', $html);
            
            // Replace data-src (lazy loading)
            $html = preg_replace('/data-src=["\'][^"\']*["\']/', 'data-src="' . esc_url($protected_url) . '"', $html);
        }
        
        return $html;
    }
    
    /**
     * Filter attachment links
     */
    public function filter_attachment_link($link, $attachment_id, $size, $permalink, $icon, $text) {
        $post_id = $this->get_nsfw_post_for_attachment($attachment_id);
        
        if ($post_id && !$this->can_view_nsfw()) {
            $protected_url = $this->get_protected_url($attachment_id, $post_id, $size);
            
            // Replace href in links
            $link = preg_replace('/href=["\'][^"\']*["\']/', 'href="' . esc_url($protected_url) . '"', $link);
            
            // Replace any image URLs within the link
            $original_url = wp_get_attachment_url($attachment_id);
            $link = str_replace($original_url, $protected_url, $link);
        }
        
        return $link;
    }
    
    /**
     * Filter ALL block content (Gutenberg)
     */
    public function filter_block_content($block_content, $block) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return $block_content;
        }
        
        // List of all blocks that can contain images
        $image_blocks = array(
            'core/image', 'core/gallery', 'core/media-text', 'core/cover',
            'core/columns', 'core/column', 'core/group', 'core/freeform',
            'core/html', 'core/embed', 'core/file', 'core/audio',
            'core/video', 'core/table', 'core/verse', 'core/preformatted'
        );
        
        // Always filter content regardless of block type to catch nested images
        return $this->replace_all_images_in_content($block_content, $post->ID);
    }
    
    /**
     * Filter post content comprehensively
     */
    public function filter_post_content($content) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return $content;
        }
        
        return $this->replace_all_images_in_content($content, $post->ID);
    }
    
    /**
     * Filter post thumbnail HTML
     */
    public function filter_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (get_post_type($post_id) !== 'prompt' || !$this->is_prompt_nsfw($post_id) || $this->can_view_nsfw()) {
            return $html;
        }
        
        $protected_url = $this->get_protected_url($post_thumbnail_id, $post_id, $size);
        
        // Replace all possible image URL attributes
        $html = preg_replace('/src=["\'][^"\']*["\']/', 'src="' . esc_url($protected_url) . '"', $html);
        $html = preg_replace('/srcset=["\'][^"\']*["\']/', 'srcset="' . esc_url($protected_url) . '"', $html);
        $html = preg_replace('/data-src=["\'][^"\']*["\']/', 'data-src="' . esc_url($protected_url) . '"', $html);
        
        return $html;
    }
    
    /**
     * Filter post galleries
     */
    public function filter_post_gallery($output, $attr, $instance) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return $output;
        }
        
        if (!empty($output)) {
            return $this->replace_all_images_in_content($output, $post->ID);
        }
        
        return $output;
    }
    
    /**
     * Filter attachment metadata
     */
    public function filter_attachment_metadata($data, $attachment_id) {
        $post_id = $this->get_nsfw_post_for_attachment($attachment_id);
        
        if ($post_id && !$this->can_view_nsfw() && is_array($data) && isset($data['sizes'])) {
            // Replace URLs in metadata
            foreach ($data['sizes'] as $size => $size_data) {
                if (isset($size_data['file'])) {
                    $data['sizes'][$size]['protected_url'] = $this->get_protected_url($attachment_id, $post_id, $size);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Filter caption shortcodes
     */
    public function filter_caption_shortcode($output, $attr, $content) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt' || !$this->is_prompt_nsfw($post->ID) || $this->can_view_nsfw()) {
            return $output;
        }
        
        if (!empty($output)) {
            return $this->replace_all_images_in_content($output, $post->ID);
        }
        
        return $output;
    }
    
    /**
     * Filter video shortcodes (poster images)
     */
    public function filter_video_shortcode($output, $atts, $video, $post_id, $library) {
        if ($post_id && get_post_type($post_id) === 'prompt' && $this->is_prompt_nsfw($post_id) && !$this->can_view_nsfw()) {
            return $this->replace_all_images_in_content($output, $post_id);
        }
        
        return $output;
    }
    
    /**
     * Filter REST API attachment responses
     */
    public function filter_rest_attachment($response, $attachment, $request) {
        $post_id = $this->get_nsfw_post_for_attachment($attachment->ID);
        
        if ($post_id && !$this->can_view_nsfw()) {
            $data = $response->get_data();
            
            // Replace main source URL
            if (isset($data['source_url'])) {
                $data['source_url'] = $this->get_protected_url($attachment->ID, $post_id);
            }
            
            // Replace media details
            if (isset($data['media_details']['sizes'])) {
                foreach ($data['media_details']['sizes'] as $size => $details) {
                    if (isset($details['source_url'])) {
                        $data['media_details']['sizes'][$size]['source_url'] = $this->get_protected_url($attachment->ID, $post_id, $size);
                    }
                }
            }
            
            // Replace guid
            if (isset($data['guid']['rendered'])) {
                $data['guid']['rendered'] = $this->get_protected_url($attachment->ID, $post_id);
            }
            
            $response->set_data($data);
        }
        
        return $response;
    }
    
    /**
     * Filter AJAX attachment queries
     */
    public function filter_ajax_attachments() {
        if (!$this->can_view_nsfw()) {
            // Modify the query to exclude NSFW attachments
            add_filter('ajax_query_attachments_args', array($this, 'exclude_nsfw_attachments_from_ajax'));
        }
    }
    
    /**
     * Exclude NSFW attachments from AJAX queries
     */
    public function exclude_nsfw_attachments_from_ajax($query) {
        $nsfw_prompts = get_posts(array(
            'post_type' => 'prompt',
            'meta_key' => '_nsfw',
            'meta_value' => '1',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        if (!empty($nsfw_prompts)) {
            $query['post_parent__not_in'] = $nsfw_prompts;
        }
        
        return $query;
    }
    
    /**
     * Filter media library queries
     */
    public function filter_media_queries($query) {
        if (!$this->can_view_nsfw() && 
            !is_admin() && 
            $query->is_main_query() && 
            isset($query->query_vars['post_type']) && 
            $query->query_vars['post_type'] === 'attachment') {
            
            $nsfw_prompts = get_posts(array(
                'post_type' => 'prompt',
                'meta_key' => '_nsfw',
                'meta_value' => '1',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ));
            
            if (!empty($nsfw_prompts)) {
                $query->set('post_parent__not_in', $nsfw_prompts);
            }
        }
    }
    
    /**
     * Replace ALL images in content with protected URLs
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
        
        // Pattern 2: Any img tag with src pointing to uploads directory
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        
        $content = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']*' . preg_quote($upload_url, '/') . '[^"\']*)["\'][^>]*>/i',
            function($matches) use ($post_id) {
                $img_tag = $matches[0];
                $img_url = $matches[1];
                
                // Try to get attachment ID from URL
                $attachment_id = attachment_url_to_postid($img_url);
                if ($attachment_id && $this->get_nsfw_post_for_attachment($attachment_id)) {
                    return $this->replace_image_tag($img_tag, $attachment_id, $post_id);
                }
                
                return $img_tag;
            },
            $content
        );
        
        // Pattern 3: Background images in style attributes
        $content = preg_replace_callback(
            '/style=["\'][^"\']*background-image:\s*url$$["\']?([^"\']*' . preg_quote($upload_url, '/') . '[^"\']*)["\']?$$[^"\']*["\']/',
            function($matches) use ($post_id) {
                $style_attr = $matches[0];
                $img_url = $matches[1];
                
                $attachment_id = attachment_url_to_postid($img_url);
                if ($attachment_id && $this->get_nsfw_post_for_attachment($attachment_id)) {
                    $protected_url = $this->get_protected_url($attachment_id, $post_id);
                    return str_replace($img_url, $protected_url, $style_attr);
                }
                
                return $style_attr;
            },
            $content
        );
        
        // Pattern 4: Direct URL replacements for known attachments
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
     * Replace individual image tag
     */
    private function replace_image_tag($img_tag, $attachment_id, $post_id) {
        $protected_url = $this->get_protected_url($attachment_id, $post_id);
        
        // Replace src
        $img_tag = preg_replace('/src=["\'][^"\']*["\']/', 'src="' . esc_url($protected_url) . '"', $img_tag);
        
        // Replace srcset
        $img_tag = preg_replace('/srcset=["\'][^"\']*["\']/', 'srcset="' . esc_url($protected_url) . '"', $img_tag);
        
        // Replace data-src (lazy loading)
        $img_tag = preg_replace('/data-src=["\'][^"\']*["\']/', 'data-src="' . esc_url($protected_url) . '"', $img_tag);
        
        // Replace data-srcset (lazy loading)
        $img_tag = preg_replace('/data-srcset=["\'][^"\']*["\']/', 'data-srcset="' . esc_url($protected_url) . '"', $img_tag);
        
        return $img_tag;
    }
    
    /**
     * Get NSFW post ID for attachment (comprehensive check)
     */
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
        
        // Check content mentions
        $prompts_with_image = get_posts(array(
            'post_type' => 'prompt',
            'meta_query' => array(
                array(
                    'key' => '_nsfw',
                    'value' => '1'
                )
            ),
            's' => 'wp-image-' . $attachment_id,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        if (!empty($prompts_with_image)) {
            return $prompts_with_image[0];
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
     * Generate protected URL
     */
    private function get_protected_url($attachment_id, $post_id, $size = 'full') {
        return add_query_arg(array(
            'prompt_image' => $attachment_id,
            'post_id' => $post_id,
            'size' => is_array($size) ? implode('x', $size) : $size,
            'nonce' => wp_create_nonce('prompt_image_' . $attachment_id . '_' . $post_id)
        ), home_url());
    }
    
    /**
     * Check if prompt is NSFW
     */
    private function is_prompt_nsfw($post_id) {
        return (bool) get_post_meta($post_id, '_nsfw', true);
    }
    
    /**
     * Check if user can view NSFW
     */
    private function can_view_nsfw() {
        return is_user_logged_in();
    }
    
    /**
     * Setup comprehensive .htaccess protection
     */
    private function setup_htaccess_protection() {
        $upload_dir = wp_upload_dir();
        $htaccess_file = $upload_dir['basedir'] . '/.htaccess';
        
        $existing_rules = '';
        if (file_exists($htaccess_file)) {
            $existing_rules = file_get_contents($htaccess_file);
        }
        
        if (strpos($existing_rules, '# Prompt Manager Comprehensive Protection') !== false) {
            return;
        }
        
        $protection_rules = "
# Prompt Manager Comprehensive Protection - START
RewriteEngine On

# Block direct access to all images unless coming through WordPress
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif|webp|svg|bmp|tiff)$ [NC]
RewriteCond %{HTTP_REFERER} !^https?://" . $_SERVER['HTTP_HOST'] . "/wp-admin [NC]
RewriteCond %{QUERY_STRING} !prompt_image= [NC]
RewriteCond %{REQUEST_URI} !prompt-manager-protected [NC]
RewriteRule ^(.*)$ /wp-content/plugins/prompt-manager/includes/image-blocker.php?file=$1 [L]

# Additional security headers
<FilesMatch \"\.(jpg|jpeg|png|gif|webp|svg|bmp|tiff)$\">
    Header set X-Content-Type-Options nosniff
    Header set X-Frame-Options DENY
    Header set Referrer-Policy strict-origin-when-cross-origin
</FilesMatch>
# Prompt Manager Comprehensive Protection - END

";
        
        $new_content = $protection_rules . $existing_rules;
        file_put_contents($htaccess_file, $new_content);
    }
    
    /**
     * Setup Nginx protection (creates config snippet)
     */
    private function setup_nginx_protection() {
        $upload_dir = wp_upload_dir();
        $nginx_config = $upload_dir['basedir'] . '/nginx-protection.conf';
        
        if (file_exists($nginx_config)) {
            return;
        }
        
        $nginx_rules = "# Prompt Manager Nginx Protection
# Add this to your server block

location ~* \.(jpg|jpeg|png|gif|webp|svg|bmp|tiff)$ {
    if (\$args !~ \"prompt_image=\") {
        try_files \$uri @prompt_protection;
    }
}

location @prompt_protection {
    rewrite ^/wp-content/uploads/(.*)$ /wp-content/plugins/prompt-manager/includes/image-blocker.php?file=\$1 last;
}

# Security headers for images
location ~* \.(jpg|jpeg|png|gif|webp|svg|bmp|tiff)$ {
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header Referrer-Policy strict-origin-when-cross-origin;
}
";
        
        file_put_contents($nginx_config, $nginx_rules);
    }
}

// Initialize comprehensive image protection
new PromptManagerImageProtection();
