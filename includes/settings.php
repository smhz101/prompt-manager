<?php
/**
 * Enhanced Settings System for Prompt Manager
 * Handles plugin configuration with blur intensity options
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerSettings {
    
    private $options_group = 'prompt_manager_settings';
    private $option_name = 'prompt_manager_options';
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_save_prompt_settings', array($this, 'ajax_save_settings'));
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting($this->options_group, $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * Set default settings on activation
     */
    public function set_defaults() {
        $defaults = array(
            'enable_watermarking' => true,
            'watermark_strength' => 'medium',
            'blur_iterations' => 25,
            'blur_factor' => 0.02,
            'blur_intensity' => 'extreme',
            'enable_analytics' => true,
            'analytics_retention_days' => 365,
            'enable_ip_blocking' => false,
            'blocked_ips' => array(),
            'enable_jetpack_protection' => true,
            'enable_dynamic_protection' => true,
            'fallback_blur_image' => '',
            'protection_message' => 'This content contains NSFW material and requires login to view.',
            'enable_forensic_tracking' => true,
            'watermark_methods' => array('steganography', 'metadata', 'pixel_modification'),
            'enable_bulk_operations' => true,
            'cache_watermarks' => true,
            'watermark_cache_duration' => 7, // days
            'enable_access_logging' => true,
            'log_retention_days' => 90,
            'enable_real_time_protection' => true,
            'protection_level' => 'maximum',
            'modal_blur_intensity' => 25,
            'content_blur_intensity' => 20
        );
        
        $existing_options = get_option($this->option_name, array());
        $options = array_merge($defaults, $existing_options);
        
        update_option($this->option_name, $options);
    }
    
    /**
     * Get option value
     */
    public function get_option($key, $default = null) {
        $options = get_option($this->option_name, array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Update option value
     */
    public function update_option($key, $value) {
        $options = get_option($this->option_name, array());
        $options[$key] = $value;
        return update_option($this->option_name, $options);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $options = get_option($this->option_name, array());
        
        ?>
        <div class="wrap">
            <h1>Prompt Manager Settings</h1>
            
            <div class="settings-container">
                <form id="prompt-manager-settings-form">
                    <?php wp_nonce_field('prompt_manager_settings', 'settings_nonce'); ?>
                    
                    <div class="settings-tabs">
                        <nav class="nav-tab-wrapper">
                            <a href="#protection" class="nav-tab nav-tab-active">Protection</a>
                            <a href="#blur-settings" class="nav-tab">Blur Settings</a>
                            <a href="#watermarking" class="nav-tab">Watermarking</a>
                            <a href="#analytics" class="nav-tab">Analytics</a>
                            <a href="#advanced" class="nav-tab">Advanced</a>
                        </nav>
                        
                        <!-- Protection Settings -->
                        <div id="protection" class="tab-content active">
                            <h2>Image Protection Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Protection Level</th>
                                    <td>
                                        <select name="protection_level">
                                            <option value="basic" <?php selected($options['protection_level'] ?? 'maximum', 'basic'); ?>>Basic</option>
                                            <option value="standard" <?php selected($options['protection_level'] ?? 'maximum', 'standard'); ?>>Standard</option>
                                            <option value="maximum" <?php selected($options['protection_level'] ?? 'maximum', 'maximum'); ?>>Maximum</option>
                                        </select>
                                        <p class="description">Maximum protection provides the strongest security but may impact performance.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Jetpack Protection</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_jetpack_protection" value="1" <?php checked($options['enable_jetpack_protection'] ?? true); ?> />
                                            Enable enhanced protection for Jetpack blocks (Tiled Gallery, etc.)
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Dynamic Protection</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_dynamic_protection" value="1" <?php checked($options['enable_dynamic_protection'] ?? true); ?> />
                                            Enable JavaScript-based protection for dynamically loaded images
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Protection Message</th>
                                    <td>
                                        <textarea name="protection_message" rows="3" cols="50"><?php echo esc_textarea($options['protection_message'] ?? 'This content contains NSFW material and requires login to view.'); ?></textarea>
                                        <p class="description">Message shown to non-logged users when accessing NSFW content.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Fallback Blur Image</th>
                                    <td>
                                        <input type="text" name="fallback_blur_image" value="<?php echo esc_attr($options['fallback_blur_image'] ?? ''); ?>" class="regular-text" />
                                        <button type="button" class="button" id="upload-fallback-image">Upload Image</button>
                                        <p class="description">Custom image to show when blur generation fails.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Blur Settings -->
                        <div id="blur-settings" class="tab-content">
                            <h2>Blur Intensity Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Blur Intensity</th>
                                    <td>
                                        <select name="blur_intensity" id="blur-intensity-select">
                                            <option value="light" <?php selected($options['blur_intensity'] ?? 'extreme', 'light'); ?>>Light (Recognizable)</option>
                                            <option value="medium" <?php selected($options['blur_intensity'] ?? 'extreme', 'medium'); ?>>Medium (Partially Obscured)</option>
                                            <option value="heavy" <?php selected($options['blur_intensity'] ?? 'extreme', 'heavy'); ?>>Heavy (Heavily Obscured)</option>
                                            <option value="extreme" <?php selected($options['blur_intensity'] ?? 'extreme', 'extreme'); ?>>Extreme (Completely Unrecognizable)</option>
                                        </select>
                                        <p class="description">Controls how heavily images are blurred. Extreme makes content completely unrecognizable.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Blur Iterations</th>
                                    <td>
                                        <input type="number" name="blur_iterations" id="blur-iterations" value="<?php echo esc_attr($options['blur_iterations'] ?? 25); ?>" min="5" max="50" />
                                        <p class="description">Number of blur passes applied to images (higher = more blur, but slower processing).</p>
                                        <div id="blur-iterations-info" class="blur-info"></div>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Blur Factor</th>
                                    <td>
                                        <input type="number" name="blur_factor" id="blur-factor" value="<?php echo esc_attr($options['blur_factor'] ?? 0.02); ?>" min="0.01" max="0.1" step="0.005" />
                                        <p class="description">Blur intensity factor (lower = more blur, 0.015 recommended for extreme).</p>
                                        <div id="blur-factor-info" class="blur-info"></div>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Modal Blur Intensity</th>
                                    <td>
                                        <input type="range" name="modal_blur_intensity" id="modal-blur-range" value="<?php echo esc_attr($options['modal_blur_intensity'] ?? 25); ?>" min="5" max="50" />
                                        <span id="modal-blur-value"><?php echo esc_attr($options['modal_blur_intensity'] ?? 25); ?>px</span>
                                        <p class="description">Blur intensity for page content when NSFW modal is shown.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Content Blur Intensity</th>
                                    <td>
                                        <input type="range" name="content_blur_intensity" id="content-blur-range" value="<?php echo esc_attr($options['content_blur_intensity'] ?? 20); ?>" min="5" max="40" />
                                        <span id="content-blur-value"><?php echo esc_attr($options['content_blur_intensity'] ?? 20); ?>px</span>
                                        <p class="description">Blur intensity for protected content areas.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Blur Preview</th>
                                    <td>
                                        <div id="blur-preview-container">
                                            <div id="blur-preview-sample" style="
                                                width: 200px; 
                                                height: 150px; 
                                                background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4); 
                                                border-radius: 10px;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                color: white;
                                                font-weight: bold;
                                                font-size: 18px;
                                                text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
                                            ">
                                                SAMPLE IMAGE
                                            </div>
                                            <p class="description">Preview of how images will appear with current blur settings.</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Watermarking Settings -->
                        <div id="watermarking" class="tab-content">
                            <h2>Forensic Watermarking Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Enable Watermarking</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_watermarking" value="1" <?php checked($options['enable_watermarking'] ?? true); ?> />
                                            Apply forensic watermarks to images for logged-in users
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Watermark Strength</th>
                                    <td>
                                        <select name="watermark_strength">
                                            <option value="light" <?php selected($options['watermark_strength'] ?? 'medium', 'light'); ?>>Light (Less detectable)</option>
                                            <option value="medium" <?php selected($options['watermark_strength'] ?? 'medium', 'medium'); ?>>Medium (Balanced)</option>
                                            <option value="strong" <?php selected($options['watermark_strength'] ?? 'medium', 'strong'); ?>>Strong (More robust)</option>
                                        </select>
                                        <p class="description">Strength of the watermark embedding.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Watermark Methods</th>
                                    <td>
                                        <?php
                                        $methods = array(
                                            'steganography' => 'Steganographic (LSB)',
                                            'metadata' => 'EXIF Metadata',
                                            'pixel_modification' => 'Pixel Modification',
                                            'frequency_domain' => 'Frequency Domain (DCT)'
                                        );
                                        
                                        $selected_methods = $options['watermark_methods'] ?? array('steganography', 'metadata', 'pixel_modification');
                                        
                                        foreach ($methods as $key => $label) {
                                            $checked = in_array($key, $selected_methods) ? 'checked' : '';
                                            echo '<label><input type="checkbox" name="watermark_methods[]" value="' . $key . '" ' . $checked . ' /> ' . $label . '</label><br>';
                                        }
                                        ?>
                                        <p class="description">Select watermarking methods to use (multiple methods increase security).</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Cache Watermarks</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="cache_watermarks" value="1" <?php checked($options['cache_watermarks'] ?? true); ?> />
                                            Cache watermarked images to improve performance
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Cache Duration</th>
                                    <td>
                                        <input type="number" name="watermark_cache_duration" value="<?php echo esc_attr($options['watermark_cache_duration'] ?? 7); ?>" min="1" max="365" />
                                        <span>days</span>
                                        <p class="description">How long to keep watermarked images in cache.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Forensic Tracking</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_forensic_tracking" value="1" <?php checked($options['enable_forensic_tracking'] ?? true); ?> />
                                            Enable detailed forensic tracking of watermarked images
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Analytics Settings -->
                        <div id="analytics" class="tab-content">
                            <h2>Analytics & Logging Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Enable Analytics</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_analytics" value="1" <?php checked($options['enable_analytics'] ?? true); ?> />
                                            Track image access attempts and generate analytics
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Analytics Retention</th>
                                    <td>
                                        <input type="number" name="analytics_retention_days" value="<?php echo esc_attr($options['analytics_retention_days'] ?? 365); ?>" min="30" max="3650" />
                                        <span>days</span>
                                        <p class="description">How long to keep analytics data.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Access Logging</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_access_logging" value="1" <?php checked($options['enable_access_logging'] ?? true); ?> />
                                            Log all image access attempts
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Log Retention</th>
                                    <td>
                                        <input type="number" name="log_retention_days" value="<?php echo esc_attr($options['log_retention_days'] ?? 90); ?>" min="7" max="365" />
                                        <span>days</span>
                                        <p class="description">How long to keep access logs.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">IP Blocking</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_ip_blocking" value="1" <?php checked($options['enable_ip_blocking'] ?? false); ?> />
                                            Enable automatic IP blocking for suspicious activity
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Blocked IPs</th>
                                    <td>
                                        <textarea name="blocked_ips" rows="5" cols="50" placeholder="Enter IP addresses, one per line"><?php echo esc_textarea(implode("\n", $options['blocked_ips'] ?? array())); ?></textarea>
                                        <p class="description">IP addresses to block from accessing NSFW content.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Advanced Settings -->
                        <div id="advanced" class="tab-content">
                            <h2>Advanced Settings</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Bulk Operations</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_bulk_operations" value="1" <?php checked($options['enable_bulk_operations'] ?? true); ?> />
                                            Enable bulk NSFW management operations
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Real-time Protection</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enable_real_time_protection" value="1" <?php checked($options['enable_real_time_protection'] ?? true); ?> />
                                            Enable real-time image protection updates
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Debug Mode</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="debug_mode" value="1" <?php checked($options['debug_mode'] ?? false); ?> />
                                            Enable debug logging (for troubleshooting)
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Performance Mode</th>
                                    <td>
                                        <select name="performance_mode">
                                            <option value="balanced" <?php selected($options['performance_mode'] ?? 'balanced', 'balanced'); ?>>Balanced</option>
                                            <option value="performance" <?php selected($options['performance_mode'] ?? 'balanced', 'performance'); ?>>Performance Priority</option>
                                            <option value="security" <?php selected($options['performance_mode'] ?? 'balanced', 'security'); ?>>Security Priority</option>
                                        </select>
                                        <p class="description">Optimize for performance or security.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Database Cleanup</th>
                                    <td>
                                        <button type="button" class="button" id="cleanup-database">Clean Old Data</button>
                                        <p class="description">Remove old analytics data and logs based on retention settings.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Reset Settings</th>
                                    <td>
                                        <button type="button" class="button button-secondary" id="reset-settings">Reset to Defaults</button>
                                        <p class="description">Reset all settings to default values.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                        <span class="spinner"></span>
                        <span id="settings-message"></span>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .settings-container {
            max-width: 1000px;
        }
        
        .settings-tabs {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .nav-tab-wrapper {
            border-bottom: 1px solid #ccd0d4;
            margin: 0;
        }
        
        .tab-content {
            display: none;
            padding: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-table th {
            width: 200px;
        }
        
        .form-table td label {
            display: block;
            margin-bottom: 5px;
        }
        
        .form-table td label input[type="checkbox"] {
            margin-right: 8px;
        }
        
        #settings-message {
            margin-left: 10px;
            font-weight: bold;
        }
        
        #settings-message.success {
            color: #46b450;
        }
        
        #settings-message.error {
            color: #dc3232;
        }
        
        /* Blur Settings Styles */
        .blur-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        #blur-preview-container {
            margin-top: 10px;
        }
        
        #blur-preview-sample {
            transition: filter 0.3s ease;
        }
        
        input[type="range"] {
            width: 200px;
            margin-right: 10px;
        }
        
        #modal-blur-value,
        #content-blur-value {
            font-weight: bold;
            color: #0073aa;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                
                $(this).addClass('nav-tab-active');
                $($(this).attr('href')).addClass('active');
            });
            
            // Blur intensity preset handling
            $('#blur-intensity-select').change(function() {
                var intensity = $(this).val();
                var iterations, factor;
                
                switch(intensity) {
                    case 'light':
                        iterations = 10;
                        factor = 0.05;
                        break;
                    case 'medium':
                        iterations = 15;
                        factor = 0.03;
                        break;
                    case 'heavy':
                        iterations = 25;
                        factor = 0.02;
                        break;
                    case 'extreme':
                        iterations = 35;
                        factor = 0.015;
                        break;
                }
                
                $('#blur-iterations').val(iterations);
                $('#blur-factor').val(factor);
                updateBlurPreview();
                updateBlurInfo();
            });
            
            // Range slider updates
            $('#modal-blur-range').on('input', function() {
                $('#modal-blur-value').text($(this).val() + 'px');
            });
            
            $('#content-blur-range').on('input', function() {
                $('#content-blur-value').text($(this).val() + 'px');
            });
            
            // Blur preview update
            function updateBlurPreview() {
                var iterations = parseInt($('#blur-iterations').val());
                var factor = parseFloat($('#blur-factor').val());
                
                // Simulate blur effect (approximation for preview)
                var blurAmount = Math.min(iterations * factor * 100, 20);
                $('#blur-preview-sample').css('filter', 'blur(' + blurAmount + 'px)');
            }
            
            // Update blur info
            function updateBlurInfo() {
                var iterations = parseInt($('#blur-iterations').val());
                var factor = parseFloat($('#blur-factor').val());
                
                var iterationsInfo = '';
                if (iterations < 15) {
                    iterationsInfo = 'Light blur - content may be recognizable';
                } else if (iterations < 25) {
                    iterationsInfo = 'Medium blur - content partially obscured';
                } else if (iterations < 35) {
                    iterationsInfo = 'Heavy blur - content heavily obscured';
                } else {
                    iterationsInfo = 'Extreme blur - content completely unrecognizable';
                }
                
                var factorInfo = '';
                if (factor > 0.04) {
                    factorInfo = 'Light blur factor - faster processing';
                } else if (factor > 0.025) {
                    factorInfo = 'Medium blur factor - balanced';
                } else if (factor > 0.018) {
                    factorInfo = 'Heavy blur factor - stronger blur';
                } else {
                    factorInfo = 'Extreme blur factor - maximum blur';
                }
                
                $('#blur-iterations-info').text(iterationsInfo);
                $('#blur-factor-info').text(factorInfo);
            }
            
            // Initialize blur info and preview
            updateBlurInfo();
            updateBlurPreview();
            
            // Update on input change
            $('#blur-iterations, #blur-factor').on('input', function() {
                updateBlurPreview();
                updateBlurInfo();
            });
            
            // Form submission
            $('#prompt-manager-settings-form').submit(function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $spinner = $form.find('.spinner');
                var $message = $('#settings-message');
                
                $spinner.addClass('is-active');
                $message.removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=save_prompt_settings',
                    success: function(response) {
                        if (response.success) {
                            $message.addClass('success').text('Settings saved successfully!');
                        } else {
                            $message.addClass('error').text('Failed to save settings: ' + response.data);
                        }
                    },
                    error: function() {
                        $message.addClass('error').text('An error occurred while saving settings.');
                    },
                    complete: function() {
                        $spinner.removeClass('is-active');
                        setTimeout(function() {
                            $message.text('');
                        }, 3000);
                    }
                });
            });
            
            // Upload fallback image
            $('#upload-fallback-image').click(function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: 'Select Fallback Blur Image',
                    button: {
                        text: 'Use This Image'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('input[name="fallback_blur_image"]').val(attachment.url);
                });
                
                mediaUploader.open();
            });
            
            // Database cleanup
            $('#cleanup-database').click(function() {
                if (confirm('Are you sure you want to clean old data? This action cannot be undone.')) {
                    var $button = $(this);
                    $button.prop('disabled', true).text('Cleaning...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cleanup_prompt_database',
                            nonce: '<?php echo wp_create_nonce('cleanup_database'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Database cleanup completed successfully!');
                            } else {
                                alert('Database cleanup failed: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('An error occurred during database cleanup.');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('Clean Old Data');
                        }
                    });
                }
            });
            
            // Reset settings
            $('#reset-settings').click(function() {
                if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
                    var $button = $(this);
                    $button.prop('disabled', true).text('Resetting...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'reset_prompt_settings',
                            nonce: '<?php echo wp_create_nonce('reset_settings'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Settings reset successfully!');
                                location.reload();
                            } else {
                                alert('Settings reset failed: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('An error occurred during settings reset.');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('Reset to Defaults');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['settings_nonce'], 'prompt_manager_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        $settings = $this->sanitize_settings($_POST);
        
        if (update_option($this->option_name, $settings)) {
            wp_send_json_success('Settings saved successfully');
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }
    
    /**
     * Sanitize settings input
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Protection settings
        $sanitized['protection_level'] = sanitize_text_field($input['protection_level'] ?? 'maximum');
        $sanitized['enable_jetpack_protection'] = isset($input['enable_jetpack_protection']);
        $sanitized['enable_dynamic_protection'] = isset($input['enable_dynamic_protection']);
        $sanitized['protection_message'] = sanitize_textarea_field($input['protection_message'] ?? '');
        $sanitized['fallback_blur_image'] = esc_url_raw($input['fallback_blur_image'] ?? '');
        
        // Blur settings
        $sanitized['blur_intensity'] = sanitize_text_field($input['blur_intensity'] ?? 'extreme');
        $sanitized['blur_iterations'] = intval($input['blur_iterations'] ?? 25);
        $sanitized['blur_factor'] = floatval($input['blur_factor'] ?? 0.02);
        $sanitized['modal_blur_intensity'] = intval($input['modal_blur_intensity'] ?? 25);
        $sanitized['content_blur_intensity'] = intval($input['content_blur_intensity'] ?? 20);
        
        // Watermarking settings
        $sanitized['enable_watermarking'] = isset($input['enable_watermarking']);
        $sanitized['watermark_strength'] = sanitize_text_field($input['watermark_strength'] ?? 'medium');
        $sanitized['watermark_methods'] = array_map('sanitize_text_field', $input['watermark_methods'] ?? array());
        $sanitized['cache_watermarks'] = isset($input['cache_watermarks']);
        $sanitized['watermark_cache_duration'] = intval($input['watermark_cache_duration'] ?? 7);
        $sanitized['enable_forensic_tracking'] = isset($input['enable_forensic_tracking']);
        
        // Analytics settings
        $sanitized['enable_analytics'] = isset($input['enable_analytics']);
        $sanitized['analytics_retention_days'] = intval($input['analytics_retention_days'] ?? 365);
        $sanitized['enable_access_logging'] = isset($input['enable_access_logging']);
        $sanitized['log_retention_days'] = intval($input['log_retention_days'] ?? 90);
        $sanitized['enable_ip_blocking'] = isset($input['enable_ip_blocking']);
        
        // Process blocked IPs
        $blocked_ips_text = sanitize_textarea_field($input['blocked_ips'] ?? '');
        $blocked_ips = array_filter(array_map('trim', explode("\n", $blocked_ips_text)));
        $sanitized['blocked_ips'] = array_filter($blocked_ips, function($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
        
        // Advanced settings
        $sanitized['enable_bulk_operations'] = isset($input['enable_bulk_operations']);
        $sanitized['enable_real_time_protection'] = isset($input['enable_real_time_protection']);
        $sanitized['debug_mode'] = isset($input['debug_mode']);
        $sanitized['performance_mode'] = sanitize_text_field($input['performance_mode'] ?? 'balanced');
        
        return $sanitized;
    }
}
