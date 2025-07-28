<?php
/**
 * NSFW Frontend Protection for Prompt Manager
 * Handles frontend display, modals, and content protection
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerNSFWFrontend {
    
    public function __construct() {
        // Content filtering
        // add_filter('the_content', array($this, 'filter_nsfw_content'), 1);
        // add_filter('the_excerpt', array($this, 'filter_nsfw_excerpt'), 1);
        // add_filter('get_the_excerpt', array($this, 'filter_nsfw_excerpt'), 1);
        
        // // SEO and meta modifications
        // add_action('wp_head', array($this, 'add_nsfw_meta_tags'), 1);
        // add_filter('wp_robots', array($this, 'modify_robots_meta'));
        
        // // Plugin-specific SEO overrides
        // add_filter('wpseo_robots', array($this, 'override_yoast_robots'));
        // add_filter('rank_math_robots', array($this, 'override_rankmath_robots'));
        // add_filter('aioseo_robots_meta', array($this, 'override_aioseo_robots'));
        
        // // Modal and scripts
        // add_action('wp_footer', array($this, 'add_nsfw_modal'));
        // add_action('wp_enqueue_scripts', array($this, 'enqueue_nsfw_scripts'));
        
        // // Template modifications
        // add_action('template_redirect', array($this, 'handle_nsfw_page_access'));
        
        // // Body class for NSFW pages
        // add_filter('body_class', array($this, 'add_nsfw_body_class'));
    }
    
    /**
     * Add NSFW body class
     */
    public function add_nsfw_body_class($classes) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $classes;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $classes;
        }
        
        if (!$this->can_view_nsfw()) {
            $classes[] = 'nsfw-blocked-page';
        }
        
        return $classes;
    }
    
    /**
     * Filter NSFW content
     */
    public function filter_nsfw_content($content) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $content;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $content;
        }
        
        if ($this->can_view_nsfw()) {
            return $content;
        }
        
        // Replace content with login prompt
        return $this->get_nsfw_content_replacement();
    }
    
    /**
     * Filter NSFW excerpts
     */
    public function filter_nsfw_excerpt($excerpt) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $excerpt;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $excerpt;
        }
        
        if ($this->can_view_nsfw()) {
            return $excerpt;
        }
        
        return 'This content is marked as NSFW and requires login to view.';
    }
    
    /**
     * Get NSFW content replacement
     */
    private function get_nsfw_content_replacement() {
        $login_url = wp_login_url(get_permalink());
        
        return '<div class="nsfw-content-blocked">
            <div class="nsfw-warning-box">
                <h3>🔒 NSFW Content - Login Required</h3>
                <p>This content contains NSFW material and requires login to view.</p>
                <a href="' . esc_url($login_url) . '" class="nsfw-login-button">Login to View Content</a>
            </div>
        </div>';
    }
    
    /**
     * Add NSFW meta tags for SEO
     */
    public function add_nsfw_meta_tags() {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return;
        }
        
        // Add noindex, nofollow for NSFW content
        echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">' . "\n";
        echo '<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">' . "\n";
        echo '<meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">' . "\n";
        
        // Additional meta tags
        echo '<meta name="rating" content="adult">' . "\n";
        echo '<meta name="content-rating" content="mature">' . "\n";
    }
    
    /**
     * Modify WordPress robots meta
     */
    public function modify_robots_meta($robots) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $robots;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $robots;
        }
        
        return array(
            'noindex' => true,
            'nofollow' => true,
            'noarchive' => true,
            'nosnippet' => true,
            'noimageindex' => true
        );
    }
    
    /**
     * Override Yoast SEO robots
     */
    public function override_yoast_robots($robots) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $robots;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $robots;
        }
        
        return 'noindex, nofollow, noarchive, nosnippet, noimageindex';
    }
    
    /**
     * Override Rank Math robots
     */
    public function override_rankmath_robots($robots) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $robots;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $robots;
        }
        
        return array(
            'index' => 'noindex',
            'follow' => 'nofollow',
            'archive' => 'noarchive',
            'snippet' => 'nosnippet',
            'imageindex' => 'noimageindex'
        );
    }
    
    /**
     * Override AIOSEO robots
     */
    public function override_aioseo_robots($robots) {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return $robots;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return $robots;
        }
        
        return array(
            'noindex' => true,
            'nofollow' => true,
            'noarchive' => true,
            'nosnippet' => true,
            'noimageindex' => true
        );
    }
    
    /**
     * Handle NSFW page access
     */
    public function handle_nsfw_page_access() {
        global $post;
        
        if (!is_single() || !$post || $post->post_type !== 'prompt') {
            return;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return;
        }
        
        if ($this->can_view_nsfw()) {
            return;
        }
        
        // Force modal display by adding inline script
        add_action('wp_footer', function() {
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    if (typeof showNSFWModal === "function") {
                        showNSFWModal();
                    }
                });
            </script>';
        }, 999);
    }
    
    /**
     * Enqueue NSFW scripts and styles
     */
    public function enqueue_nsfw_scripts() {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return;
        }
        
        wp_enqueue_script('prompt-manager-nsfw', PROMPT_MANAGER_PLUGIN_URL . 'assets/js/nsfw-frontend.js', array('jquery'), PROMPT_MANAGER_VERSION, true);
        wp_localize_script('prompt-manager-nsfw', 'promptManagerNSFW', array(
            'loginUrl' => wp_login_url(get_permalink()),
            'postId' => $post->ID,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isBlocked' => !$this->can_view_nsfw()
        ));
        
        // Add inline styles for NSFW modal
        wp_add_inline_style('prompt-manager-style', $this->get_nsfw_modal_styles());
    }
    
    /**
     * Add NSFW modal to footer
     */
    public function add_nsfw_modal() {
        global $post;
        
        if (!$post || $post->post_type !== 'prompt') {
            return;
        }
        
        if (!$this->is_prompt_nsfw($post->ID)) {
            return;
        }
        
        if ($this->can_view_nsfw()) {
            return;
        }
        
        $login_url = wp_login_url(get_permalink());
        
        ?>
        <div id="nsfw-modal" class="nsfw-modal">
            <div class="nsfw-modal-overlay"></div>
            <div class="nsfw-modal-content">
                <div class="nsfw-modal-header">
                    <h2>🔒 NSFW Content Warning</h2>
                </div>
                <div class="nsfw-modal-body">
                    <p><strong>This page contains NSFW (Not Safe For Work) content.</strong></p>
                    <p>You must be logged in to view this content.</p>
                    <p>All images and text content are protected and require authentication.</p>
                </div>
                <div class="nsfw-modal-footer">
                    <a href="<?php echo esc_url($login_url); ?>" class="nsfw-modal-login-btn">Login to Continue</a>
                    <button type="button" class="nsfw-modal-close-btn" onclick="window.history.back()">Go Back</button>
                </div>
            </div>
        </div>
        
        <script>
        // Ensure modal shows immediately and is properly centered
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('nsfw-modal');
            if (modal && document.body.classList.contains('nsfw-blocked-page')) {
                // Force modal to center of viewport
                modal.style.display = 'flex';
                modal.style.position = 'fixed';
                modal.style.top = '0';
                modal.style.left = '0';
                modal.style.width = '100vw';
                modal.style.height = '100vh';
                modal.style.zIndex = '999999';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
                modal.classList.add('active');
                
                // Prevent body scrolling
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
                
                // Blur all content
                var contentSelectors = [
                    '.site-content', '.content', 'main', 'article', 
                    '.entry-content', '.post-content', '#content', 
                    '.page-content', '.single-content', '.site-main',
                    '.main-content', '.primary-content', '#main',
                    '.container', '.wrapper', '#wrapper'
                ];
                
                contentSelectors.forEach(function(selector) {
                    var elements = document.querySelectorAll(selector);
                    elements.forEach(function(el) {
                        if (el !== modal && !modal.contains(el)) {
                            el.style.filter = 'blur(25px) brightness(0.7)';
                            el.style.pointerEvents = 'none';
                            el.style.userSelect = 'none';
                            el.style.webkitUserSelect = 'none';
                            el.style.mozUserSelect = 'none';
                            el.style.msUserSelect = 'none';
                        }
                    });
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get NSFW modal styles
     */
    private function get_nsfw_modal_styles() {
        return '
        /* NSFW Modal Styles - Fixed Positioning */
        .nsfw-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999999;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .nsfw-modal.active {
            display: flex !important;
        }
        
        .nsfw-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .nsfw-modal-content {
            position: relative;
            background: white;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            z-index: 1000000;
            animation: modalSlideIn 0.3s ease-out;
            margin: auto;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(-50px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .nsfw-modal-header {
            padding: 30px 30px 20px;
            text-align: center;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .nsfw-modal-header h2 {
            margin: 0;
            color: #dc3232;
            font-size: 28px;
            font-weight: bold;
        }
        
        .nsfw-modal-body {
            padding: 30px;
            text-align: center;
            color: #333;
            line-height: 1.8;
            font-size: 16px;
        }
        
        .nsfw-modal-body p {
            margin-bottom: 15px;
        }
        
        .nsfw-modal-body strong {
            color: #dc3232;
        }
        
        .nsfw-modal-footer {
            padding: 20px 30px 30px;
            text-align: center;
            display: flex;
            gap: 15px;
            justify-content: center;
            border-top: 2px solid #f0f0f0;
        }
        
        .nsfw-modal-login-btn {
            background: linear-gradient(135deg, #0073aa, #005a87);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 115, 170, 0.3);
        }
        
        .nsfw-modal-login-btn:hover {
            background: linear-gradient(135deg, #005a87, #004a73);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 115, 170, 0.4);
        }
        
        .nsfw-modal-close-btn {
            background: #666;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 102, 102, 0.3);
        }
        
        .nsfw-modal-close-btn:hover {
            background: #555;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 102, 102, 0.4);
        }
        
        /* Force blur on NSFW blocked pages */
        body.nsfw-blocked-page .site-content,
        body.nsfw-blocked-page .content,
        body.nsfw-blocked-page main,
        body.nsfw-blocked-page article,
        body.nsfw-blocked-page .entry-content,
        body.nsfw-blocked-page .post-content,
        body.nsfw-blocked-page #content,
        body.nsfw-blocked-page .page-content,
        body.nsfw-blocked-page .single-content {
            filter: blur(25px) !important;
            pointer-events: none !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }
        
        /* Prevent any interaction on blocked pages */
        body.nsfw-blocked-page {
            overflow: hidden !important;
        }
        
        body.nsfw-blocked-page * {
            -webkit-touch-callout: none !important;
            -webkit-user-select: none !important;
            -khtml-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
        }
        
        /* NSFW content replacement styles */
        .nsfw-content-blocked {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f9f9f9, #f0f0f0);
            border-radius: 15px;
            margin: 40px 0;
            border: 3px solid #dc3232;
        }
        
        .nsfw-warning-box {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .nsfw-warning-box h3 {
            color: #dc3232;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .nsfw-login-button {
            display: inline-block;
            background: linear-gradient(135deg, #0073aa, #005a87);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 20px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 115, 170, 0.3);
        }
        
        .nsfw-login-button:hover {
            background: linear-gradient(135deg, #005a87, #004a73);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 115, 170, 0.4);
        }
        
        @media (max-width: 768px) {
            .nsfw-modal-content {
                margin: 20px;
                width: calc(100% - 40px);
                max-width: none;
            }
            
            .nsfw-modal-header {
                padding: 20px 20px 15px;
            }
            
            .nsfw-modal-header h2 {
                font-size: 24px;
            }
            
            .nsfw-modal-body {
                padding: 20px;
                font-size: 14px;
            }
            
            .nsfw-modal-footer {
                flex-direction: column;
                padding: 15px 20px 20px;
            }
            
            .nsfw-modal-login-btn,
            .nsfw-modal-close-btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
        ';
    }
    
    /**
     * Check if prompt is NSFW
     */
    private function is_prompt_nsfw($post_id) {
        return (bool) get_post_meta($post_id, '_nsfw', true);
    }
    
    /**
     * Check if user can view NSFW content
     */
    private function can_view_nsfw() {
        return is_user_logged_in();
    }
}

// Initialize NSFW frontend protection
new PromptManagerNSFWFrontend();
