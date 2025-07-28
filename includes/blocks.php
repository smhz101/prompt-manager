<?php
/**
 * WordPress Blocks for Prompt Manager
 * Adds Gutenberg blocks support for modern WordPress themes
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerBlocks {
    
    public function __construct() {
        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
        add_action('admin_post_prompt_manager_submit_prompt', array($this, 'handle_prompt_submission'));
        add_action('admin_post_nopriv_prompt_manager_submit_prompt', array($this, 'handle_prompt_submission'));
    }
    
    /**
     * Initialize blocks
     */
    public function init() {
        // Register block categories
        add_filter('block_categories_all', array($this, 'add_block_category'), 10, 2);
    }
    
    /**
     * Add Prompt Manager block category
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug'  => 'prompt-manager',
                    'title' => __('Prompt Manager', 'prompt-manager'),
                    'icon'  => 'lightbulb',
                ),
            )
        );
    }
    
    /**
     * Register all blocks
     */
    public function register_blocks() {
        // Check if block editor is available
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register all blocks using metadata
        $blocks = array(
            'prompt-display'      => 'render_prompt_display_block',
            'prompt-gallery'      => 'render_prompt_gallery_block',
            'nsfw-warning'        => 'render_nsfw_warning_block',
            'protected-image'     => 'render_protected_image_block',
            'prompt-search'       => 'render_prompt_search_block',
            'analytics-summary'   => 'render_analytics_summary_block',
            'random-prompt'       => 'render_random_prompt_block',
            'prompt-submission'   => 'render_prompt_submission_block',
            'protected-download'  => 'render_protected_download_block',
            'prompt-slider'       => 'render_prompt_slider_block',
            'advance-query'       => 'render_advance_query_block',
        );

        foreach ($blocks as $slug => $callback) {
            register_block_type(
                PROMPT_MANAGER_PLUGIN_DIR . 'src/blocks/' . $slug . '/block.json',
                array('render_callback' => array($this, $callback))
            );
        }
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        // Load compiled block script and its dependencies.
        $asset_file = include PROMPT_MANAGER_PLUGIN_DIR . 'build/blocks.asset.php';
        wp_enqueue_script(
            'prompt-manager-blocks',
            PROMPT_MANAGER_PLUGIN_URL . 'build/blocks.js',
            $asset_file['dependencies'],
            $asset_file['version']
        );
        
        wp_enqueue_style(
            'prompt-manager-blocks-editor',
            PROMPT_MANAGER_PLUGIN_URL . 'assets/css/blocks-editor.css',
            array('wp-edit-blocks'),
            PROMPT_MANAGER_VERSION
        );
        
        // Localize script with data
        wp_localize_script('prompt-manager-blocks', 'promptManagerBlocks', array(
            'apiUrl' => rest_url('wp/v2/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'prompts' => $this->get_prompts_for_blocks(),
            'imageSizes' => $this->get_image_sizes(),
        ));
    }
    
    /**
     * Enqueue block assets for frontend
     */
    public function enqueue_block_assets() {
        wp_enqueue_style(
            'prompt-manager-blocks',
            PROMPT_MANAGER_PLUGIN_URL . 'assets/css/blocks.css',
            array(),
            PROMPT_MANAGER_VERSION
        );
    }
    
    /**
     * Get prompts for block selector
     */
    private function get_prompts_for_blocks() {
        $prompts = get_posts(array(
            'post_type' => 'prompt',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $prompt_options = array();
        foreach ($prompts as $prompt) {
            $prompt_options[] = array(
                'value' => $prompt->ID,
                'label' => $prompt->post_title
            );
        }
        
        return $prompt_options;
    }
    
    /**
     * Get available image sizes
     */
    private function get_image_sizes() {
        $sizes = get_intermediate_image_sizes();
        $sizes[] = 'full';
        
        $size_options = array();
        foreach ($sizes as $size) {
            $size_options[] = array(
                'value' => $size,
                'label' => ucfirst(str_replace('_', ' ', $size))
            );
        }
        
        return $size_options;
    }
    
    /**
     * Render Prompt Display Block
     */
    public function render_prompt_display_block($attributes) {
        $prompt_id = intval($attributes['promptId']);
        
        if (!$prompt_id) {
            return '<p>' . __('Please select a prompt to display.', 'prompt-manager') . '</p>';
        }
        
        $prompt = get_post($prompt_id);
        if (!$prompt || $prompt->post_type !== 'prompt') {
            return '<p>' . __('Prompt not found.', 'prompt-manager') . '</p>';
        }
        
        // Check NSFW protection
        $is_nsfw = get_post_meta($prompt_id, '_nsfw', true);
        if ($is_nsfw && !is_user_logged_in()) {
            return $this->render_nsfw_protection($prompt_id);
        }
        
        $output = '<div class="wp-block-prompt-manager-prompt-display">';
        
        // Add alignment class
        if ($attributes['alignment'] !== 'none') {
            $output = '<div class="wp-block-prompt-manager-prompt-display align' . esc_attr($attributes['alignment']) . '">';
        }
        
        // Show image
        if ($attributes['showImage'] && has_post_thumbnail($prompt_id)) {
            $image_size = $attributes['imageSize'];
            $image_html = get_the_post_thumbnail($prompt_id, $image_size, array('class' => 'prompt-display-image'));
            
            // Apply protection if NSFW
            if ($is_nsfw) {
                $image_html = $this->apply_image_protection($image_html, $prompt_id);
            }
            
            $output .= '<div class="prompt-display-image-container">' . $image_html . '</div>';
        }
        
        // Show title
        if ($attributes['showTitle']) {
            $output .= '<h3 class="prompt-display-title">' . esc_html($prompt->post_title) . '</h3>';
        }
        
        // Show excerpt
        if ($attributes['showExcerpt']) {
            $excerpt = $prompt->post_excerpt ? $prompt->post_excerpt : wp_trim_words($prompt->post_content, 30);
            $output .= '<div class="prompt-display-excerpt">' . esc_html($excerpt) . '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render Prompt Gallery Block
     */
    public function render_prompt_gallery_block($attributes) {
        $args = array(
            'post_type' => 'prompt',
            'posts_per_page' => intval($attributes['numberOfPosts']),
            'post_status' => 'publish',
            'orderby' => $attributes['orderBy'],
            'order' => $attributes['order']
        );
        
        // Filter NSFW content if not showing it
        if (!$attributes['showNSFW'] && !is_user_logged_in()) {
            $args['meta_query'] = array(
                array(
                    'key' => '_nsfw',
                    'compare' => 'NOT EXISTS'
                )
            );
        }
        
        // Add category filter if specified
        if (!empty($attributes['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'prompt_category',
                    'field' => 'slug',
                    'terms' => $attributes['category']
                )
            );
        }
        
        $prompts = get_posts($args);
        
        if (empty($prompts)) {
            return '<p>' . __('No prompts found.', 'prompt-manager') . '</p>';
        }
        
        $columns = intval($attributes['columns']);
        $output = '<div class="wp-block-prompt-manager-prompt-gallery columns-' . $columns . '">';
        
        foreach ($prompts as $prompt) {
            $is_nsfw = get_post_meta($prompt->ID, '_nsfw', true);
            
            $output .= '<div class="prompt-gallery-item">';
            
            if (has_post_thumbnail($prompt->ID)) {
                $image_html = get_the_post_thumbnail($prompt->ID, 'medium', array('class' => 'prompt-gallery-image'));
                
                // Apply protection if NSFW
                if ($is_nsfw && !is_user_logged_in()) {
                    $image_html = $this->apply_image_protection($image_html, $prompt->ID);
                }
                
                $output .= '<div class="prompt-gallery-image-container">' . $image_html . '</div>';
            }
            
            $output .= '<h4 class="prompt-gallery-title"><a href="' . get_permalink($prompt->ID) . '">' . esc_html($prompt->post_title) . '</a></h4>';
            
            $excerpt = $prompt->post_excerpt ? $prompt->post_excerpt : wp_trim_words($prompt->post_content, 20);
            $output .= '<div class="prompt-gallery-excerpt">' . esc_html($excerpt) . '</div>';
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render NSFW Warning Block
     */
    public function render_nsfw_warning_block($attributes) {
        $warning_text = esc_html($attributes['warningText']);
        $button_text = esc_html($attributes['buttonText']);
        $bg_color = esc_attr($attributes['backgroundColor']);
        $text_color = esc_attr($attributes['textColor']);
        $login_url = wp_login_url(get_permalink());
        
        $style = 'background-color: ' . $bg_color . '; color: ' . $text_color . ';';
        
        return '<div class="wp-block-prompt-manager-nsfw-warning" style="' . $style . '">
            <div class="nsfw-warning-content">
                <p class="nsfw-warning-text">' . $warning_text . '</p>
                <a href="' . esc_url($login_url) . '" class="nsfw-warning-button">' . $button_text . '</a>
            </div>
        </div>';
    }
    
    /**
     * Render Protected Image Block
     */
    public function render_protected_image_block($attributes) {
        $image_id = intval($attributes['imageId']);
        
        if (!$image_id) {
            return '<p>' . __('Please select an image.', 'prompt-manager') . '</p>';
        }
        
        $image = get_post($image_id);
        if (!$image || $image->post_type !== 'attachment') {
            return '<p>' . __('Image not found.', 'prompt-manager') . '</p>';
        }
        
        $require_login = $attributes['requireLogin'];
        $blur_intensity = intval($attributes['blurIntensity']);
        $size = $attributes['size'];
        $alt = $attributes['alt'] ?: get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $caption = $attributes['caption'] ?: $image->post_excerpt;
        
        // Check if protection is needed
        if ($require_login && !is_user_logged_in()) {
            global $post;
            $post_id = $post ? $post->ID : 0;
            
            // Generate protected URL
            $protected_url = home_url() . '?prompt_image=' . $image_id . '&post_id=' . $post_id . '&nonce=' . wp_create_nonce('prompt_image_' . $image_id);
            
            $image_html = '<img src="' . esc_url($protected_url) . '" alt="' . esc_attr($alt) . '" class="wp-image-' . $image_id . ' size-' . esc_attr($size) . ' protected-image blur-' . $blur_intensity . '">';
        } else {
            $image_html = wp_get_attachment_image($image_id, $size, false, array(
                'alt' => $alt,
                'class' => 'wp-image-' . $image_id
            ));
        }
        
        $output = '<figure class="wp-block-prompt-manager-protected-image">';
        $output .= $image_html;
        
        if ($caption) {
            $output .= '<figcaption>' . esc_html($caption) . '</figcaption>';
        }
        
        $output .= '</figure>';
        
        return $output;
    }
    
    /**
     * Apply image protection for NSFW content
     */
    private function apply_image_protection($image_html, $post_id) {
        // Add blur class and modify src to use protected URL
        $blur_intensity = get_post_meta($post_id, '_blur_intensity', true) ?: 15;
        
        // Extract image ID from HTML
        preg_match('/wp-image-(\d+)/', $image_html, $matches);
        if (isset($matches[1])) {
            $image_id = $matches[1];
            $protected_url = home_url() . '?prompt_image=' . $image_id . '&post_id=' . $post_id . '&nonce=' . wp_create_nonce('prompt_image_' . $image_id);
            
            // Replace src with protected URL and add blur class
            $image_html = preg_replace('/src="[^"]*"/', 'src="' . esc_url($protected_url) . '"', $image_html);
            $image_html = preg_replace('/class="([^"]*)"/', 'class="$1 protected-image blur-' . $blur_intensity . '"', $image_html);
        }
        
        return $image_html;
    }
    
    /**
     * Render NSFW protection message
     */
    private function render_nsfw_protection($post_id) {
        $login_url = wp_login_url(get_permalink($post_id));

        return '<div class="wp-block-prompt-manager-nsfw-protection">
            <div class="nsfw-protection-message">
                <h3>' . __('NSFW Content - Login Required', 'prompt-manager') . '</h3>
                <p>' . __('This content contains NSFW material and requires login to view.', 'prompt-manager') . '</p>
                <a href="' . esc_url($login_url) . '" class="nsfw-login-button">' . __('Login to View Content', 'prompt-manager') . '</a>
            </div>
        </div>';
    }

    /**
     * Render Prompt Search Block
     */
    public function render_prompt_search_block() {
        return '<div class="wp-block-prompt-manager-prompt-search"><input type="text" class="prompt-search-input" placeholder="' . esc_attr__('Search prompts...', 'prompt-manager') . '"/><div class="prompt-search-results"></div></div>';
    }

    /**
     * Render Analytics Summary Block
     */
    public function render_analytics_summary_block($attributes) {
        $days = intval($attributes['days']);
        $analytics = new PromptManagerAnalytics();
        $total = $analytics->get_total_access_attempts($days);
        $blocked = $analytics->get_blocked_attempts_today();

        return '<div class="wp-block-prompt-manager-analytics-summary"><p>' . sprintf(__('Total Attempts (last %d days): %d', 'prompt-manager'), $days, $total) . '</p><p>' . sprintf(__('Blocked Attempts Today: %d', 'prompt-manager'), $blocked) . '</p></div>';
    }

    /**
     * Render Random Prompt Block
     */
    public function render_random_prompt_block($attributes) {
        $prompt = get_posts(array(
            'post_type' => 'prompt',
            'posts_per_page' => 1,
            'orderby' => 'rand',
        ));

        if (empty($prompt)) {
            return '<p>' . __('No prompts found.', 'prompt-manager') . '</p>';
        }

        $attributes['promptId'] = $prompt[0]->ID;
        return $this->render_prompt_display_block($attributes);
    }

    /**
     * Render Prompt Submission Block
     */
    public function render_prompt_submission_block() {
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to submit a prompt.', 'prompt-manager') . '</p>';
        }

        $nonce = wp_create_nonce('prompt_submit');
        $action = esc_url(admin_url('admin-post.php'));

        return '<form class="prompt-submission-form" method="post" action="' . $action . '">
            <input type="hidden" name="action" value="prompt_manager_submit_prompt" />
            <input type="hidden" name="_wpnonce" value="' . $nonce . '" />
            <p><input type="text" name="prompt_title" placeholder="' . esc_attr__('Title', 'prompt-manager') . '" required></p>
            <p><textarea name="prompt_content" placeholder="' . esc_attr__('Prompt text', 'prompt-manager') . '" required></textarea></p>
            <p><button type="submit">' . __('Submit', 'prompt-manager') . '</button></p>
        </form>';
    }

    /**
     * Handle prompt submission
     */
    public function handle_prompt_submission() {
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to submit prompts.', 'prompt-manager'));
        }

        check_admin_referer('prompt_submit');

        $title = sanitize_text_field($_POST['prompt_title'] ?? '');
        $content = sanitize_textarea_field($_POST['prompt_content'] ?? '');

        if ($title && $content) {
            wp_insert_post(array(
                'post_type'   => 'prompt',
                'post_title'  => $title,
                'post_content'=> $content,
                'post_status' => 'pending',
            ));
        }

        wp_safe_redirect(wp_get_referer());
        exit;
    }

    /**
     * Render Protected Download Block
     */
    public function render_protected_download_block($attributes) {
        $attachment_id = intval($attributes['attachmentId']);
        if (!$attachment_id) {
            return '<p>' . __('No file selected.', 'prompt-manager') . '</p>';
        }

        $url = wp_get_attachment_url($attachment_id);
        $title = get_the_title($attachment_id);

        if (!is_user_logged_in()) {
            $login = wp_login_url($url);
            return '<a href="' . esc_url($login) . '">' . __('Login to Download', 'prompt-manager') . '</a>';
        }

        return '<a href="' . esc_url($url) . '" download>' . esc_html($title) . '</a>';
    }

    /**
     * Render Prompt Slider Block
     */
    public function render_prompt_slider_block($attributes) {
        $args = array(
            'post_type'      => 'prompt',
            'posts_per_page' => intval($attributes['numberOfPosts']),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if (!$attributes['showNSFW'] && !is_user_logged_in()) {
            $args['meta_query'] = array(
                array(
                    'key' => '_nsfw',
                    'compare' => 'NOT EXISTS'
                )
            );
        }

        $prompts = get_posts($args);

        if (empty($prompts)) {
            return '<p>' . __('No prompts found.', 'prompt-manager') . '</p>';
        }

        $output = '<div class="wp-block-prompt-manager-prompt-slider">';
        foreach ($prompts as $prompt) {
            $output .= '<div class="prompt-slide"><a href="' . get_permalink($prompt->ID) . '">' . esc_html($prompt->post_title) . '</a></div>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render Advance Query Block
     */
    public function render_advance_query_block($attributes) {
        $args = array(
            'post_type'      => 'prompt',
            'posts_per_page' => intval($attributes['postsPerPage']),
            'orderby'        => $attributes['orderBy'],
            'order'          => $attributes['order'],
        );

        $prompts = get_posts($args);
        if (empty($prompts)) {
            return '<p>' . __('No prompts found.', 'prompt-manager') . '</p>';
        }

        $output = '<ul class="wp-block-prompt-manager-advance-query">';
        foreach ($prompts as $prompt) {
            $output .= '<li><a href="' . get_permalink($prompt->ID) . '">' . esc_html($prompt->post_title) . '</a></li>';
        }
        $output .= '</ul>';

        return $output;
    }
}