<?php
/**
 * Template functions for Prompt Manager
 * These functions can be used in themes to display protected content
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display protected featured image
 */
function prompt_manager_featured_image($post_id = null, $size = 'full', $attr = array()) {
    if (!$post_id) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    
    if (get_post_type($post_id) !== 'prompt') {
        return get_the_post_thumbnail($post_id, $size, $attr);
    }
    
    $featured_id = get_post_thumbnail_id($post_id);
    if (!$featured_id) {
        return '';
    }
    
    // Generate protected URL
    $protected_url = get_protected_image_url($post_id, $featured_id);
    
    $class = isset($attr['class']) ? $attr['class'] : '';
    $alt = isset($attr['alt']) ? $attr['alt'] : get_post_meta($featured_id, '_wp_attachment_image_alt', true);
    
    $attr['class'] = trim($class);
    $attr['src'] = $protected_url;
    $attr['alt'] = $alt;
    
    $html = '<img';
    foreach ($attr as $key => $value) {
        $html .= ' ' . $key . '="' . esc_attr($value) . '"';
    }
    $html .= ' />';
    
    return $html;
}

/**
 * Display NSFW warning if needed
 */
function prompt_manager_nsfw_warning($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    
    if (!is_prompt_nsfw($post_id)) {
        return '';
    }
    
    if (can_view_nsfw()) {
        return '<div class="nsfw-content-warning">⚠️ This content is marked as NSFW</div>';
    }
    
    $login_url = wp_login_url(get_permalink($post_id));
    
    return '<div class="nsfw-login-prompt">
        🔒 This content contains NSFW material. 
        <a href="' . esc_url($login_url) . '" class="nsfw-login-button">Login to View</a>
    </div>';
}

/**
 * Check if current user can view full content
 */
function prompt_manager_can_view_full_content($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    
    return !is_prompt_nsfw($post_id) || can_view_nsfw();
}

/**
 * Get gallery images with protection
 */
function prompt_manager_get_gallery_images($post_id = null) {
    if (!$post_id) {
        global $post;
        $post_id = $post ? $post->ID : 0;
    }
    
    $images = get_attached_media('image', $post_id);
    $gallery_images = array();
    
    foreach ($images as $image) {
        // Skip blurred images
        if (get_post_meta($image->ID, '_is_blurred_image', true)) {
            continue;
        }
        
        $gallery_images[] = array(
            'id' => $image->ID,
            'url' => get_protected_image_url($post_id, $image->ID),
            'title' => $image->post_title,
            'alt' => get_post_meta($image->ID, '_wp_attachment_image_alt', true),
            'is_nsfw' => is_prompt_nsfw($post_id)
        );
    }
    
    return $gallery_images;
}

/**
 * Display image gallery with protection
 */
function prompt_manager_display_gallery($post_id = null, $columns = 3) {
    $images = prompt_manager_get_gallery_images($post_id);
    
    if (empty($images)) {
        return '';
    }
    
    $html = '<div class="prompt-gallery columns-' . intval($columns) . '">';
    
    foreach ($images as $image) {
        $html .= '<div class="gallery-item">';
        $html .= '<img src="' . esc_url($image['url']) . '" ';
        $html .= 'alt="' . esc_attr($image['alt']) . '" ';
        $html .= 'title="' . esc_attr($image['title']) . '" ';
        $html .= '/>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
