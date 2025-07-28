<?php
/**
 * Analytics System for Prompt Manager
 * Tracks image access attempts and provides detailed reporting
 */

if (!defined('ABSPATH')) {
    exit;
}

class PromptManagerAnalytics {
    
    public function __construct() {
        add_action('wp_ajax_get_analytics_data', array($this, 'ajax_get_analytics_data'));
    }
    
    /**
     * Create analytics database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Image access log table
        $access_table = $wpdb->prefix . 'prompt_image_access';
        $sql1 = "CREATE TABLE $access_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            image_id bigint(20),
            access_type varchar(50) NOT NULL,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            referer text,
            access_granted tinyint(1) DEFAULT 0,
            access_time datetime DEFAULT CURRENT_TIMESTAMP,
            additional_data longtext,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY image_id (image_id),
            KEY user_id (user_id),
            KEY access_time (access_time),
            KEY access_type (access_type)
        ) $charset_collate;";
        
        // Analytics summary table
        $summary_table = $wpdb->prefix . 'prompt_analytics_summary';
        $sql2 = "CREATE TABLE $summary_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            date_recorded date NOT NULL,
            total_attempts int(11) DEFAULT 0,
            blocked_attempts int(11) DEFAULT 0,
            unique_visitors int(11) DEFAULT 0,
            unique_ips int(11) DEFAULT 0,
            top_referers text,
            top_user_agents text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_date (post_id, date_recorded),
            KEY date_recorded (date_recorded)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    /**
     * Log image access attempt
     */
    public function log_image_access($post_id, $access_type, $additional_data = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_image_access';
        
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        
        // Determine if access was actually granted based on context
        $access_granted = 0; // Default to blocked
        
        // Check if this is an NSFW post
        $is_nsfw = (bool) get_post_meta($post_id, '_nsfw', true);
        
        if (!$is_nsfw) {
            // Not NSFW, access is always granted
            $access_granted = 1;
        } elseif (is_user_logged_in()) {
            // NSFW but user is logged in, access granted
            $access_granted = 1;
        }
        // If NSFW and not logged in, access_granted remains 0 (blocked)
        
        $data = array(
            'post_id' => $post_id,
            'access_type' => $access_type,
            'user_id' => $user_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'access_granted' => $access_granted,
            'additional_data' => json_encode($additional_data)
        );
        
        if (isset($additional_data['image_id'])) {
            $data['image_id'] = intval($additional_data['image_id']);
        }
        
        $wpdb->insert($table_name, $data);
        
        // Update daily summary
        $this->update_daily_summary($post_id);
    }
    
    /**
     * Update daily analytics summary
     */
    private function update_daily_summary($post_id) {
        global $wpdb;
        
        $access_table = $wpdb->prefix . 'prompt_image_access';
        $summary_table = $wpdb->prefix . 'prompt_analytics_summary';
        $today = current_time('Y-m-d');
        
        // Get today's stats
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN access_granted = 0 THEN 1 ELSE 0 END) as blocked_attempts,
                COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN user_id END) as unique_visitors,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM $access_table 
            WHERE post_id = %d AND DATE(access_time) = %s
        ", $post_id, $today));
        
        // Get top referers and user agents
        $top_referers = $wpdb->get_results($wpdb->prepare("
            SELECT referer, COUNT(*) as count 
            FROM $access_table 
            WHERE post_id = %d AND DATE(access_time) = %s AND referer != ''
            GROUP BY referer 
            ORDER BY count DESC 
            LIMIT 10
        ", $post_id, $today));
        
        $top_user_agents = $wpdb->get_results($wpdb->prepare("
            SELECT user_agent, COUNT(*) as count 
            FROM $access_table 
            WHERE post_id = %d AND DATE(access_time) = %s AND user_agent != ''
            GROUP BY user_agent 
            ORDER BY count DESC 
            LIMIT 10
        ", $post_id, $today));
        
        // Insert or update summary
        $wpdb->replace($summary_table, array(
            'post_id' => $post_id,
            'date_recorded' => $today,
            'total_attempts' => $stats->total_attempts,
            'blocked_attempts' => $stats->blocked_attempts,
            'unique_visitors' => $stats->unique_visitors,
            'unique_ips' => $stats->unique_ips,
            'top_referers' => json_encode($top_referers),
            'top_user_agents' => json_encode($top_user_agents)
        ));
    }
    
    /**
     * Get post access count
     */
    public function get_post_access_count($post_id, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_image_access';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $table_name 
            WHERE post_id = %d AND access_time >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $post_id, $days));
    }
    
    /**
     * Get total access attempts across all posts
     */
    public function get_total_access_attempts($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_image_access';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $table_name 
            WHERE access_time >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
    }
    
    /**
     * Get blocked attempts today
     */
    public function get_blocked_attempts_today() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'prompt_image_access';
        
        return $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $table_name 
            WHERE access_granted = 0 AND DATE(access_time) = CURDATE()
        ");
    }
    
    /**
     * Get analytics dashboard data
     */
    public function get_dashboard_data($post_id = null, $days = 30) {
        global $wpdb;
        
        $access_table = $wpdb->prefix . 'prompt_image_access';
        $where_clause = $post_id ? $wpdb->prepare("WHERE post_id = %d AND", $post_id) : "WHERE";
        
        // Basic stats
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN access_granted = 1 THEN 1 ELSE 0 END) as granted_attempts,
                SUM(CASE WHEN access_granted = 0 THEN 1 ELSE 0 END) as blocked_attempts,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(DISTINCT user_id) as unique_users
            FROM $access_table 
            $where_clause access_time >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
        
        // Daily breakdown
        $daily_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(access_time) as date,
                COUNT(*) as total,
                SUM(CASE WHEN access_granted = 0 THEN 1 ELSE 0 END) as blocked
            FROM $access_table 
            $where_clause access_time >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(access_time)
            ORDER BY date DESC
        ", $days));
        
        // Top blocked IPs
        $top_blocked_ips = $wpdb->get_results($wpdb->prepare("
            SELECT 
                ip_address,
                COUNT(*) as attempts
            FROM $access_table 
            $where_clause access_granted = 0 AND access_time >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY ip_address
            ORDER BY attempts DESC
            LIMIT 10
        ", $days));
        
        // Access types breakdown
        $access_types = $wpdb->get_results($wpdb->prepare("
            SELECT 
                access_type,
                COUNT(*) as count
            FROM $access_table 
            $where_clause access_time >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY access_type
            ORDER BY count DESC
        ", $days));
        
        return array(
            'stats' => $stats,
            'daily_stats' => $daily_stats,
            'top_blocked_ips' => $top_blocked_ips,
            'access_types' => $access_types
        );
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        
        $data = $this->get_dashboard_data($post_id, $days);
        
        ?>
        <div class="wrap">
            <h1>Image Access Analytics</h1>
            
            <div class="analytics-filters">
                <form method="get">
                    <input type="hidden" name="post_type" value="prompt">
                    <input type="hidden" name="page" value="prompt-analytics">
                    
                    <label for="post_id">Post:</label>
                    <select name="post_id" id="post_id">
                        <option value="">All Posts</option>
                        <?php
                        $nsfw_posts = get_posts(array(
                            'post_type' => 'prompt',
                            'meta_key' => '_nsfw',
                            'meta_value' => '1',
                            'posts_per_page' => -1
                        ));
                        
                        foreach ($nsfw_posts as $post) {
                            $selected = ($post_id == $post->ID) ? 'selected' : '';
                            echo '<option value="' . $post->ID . '" ' . $selected . '>' . esc_html($post->post_title) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <label for="days">Time Period:</label>
                    <select name="days" id="days">
                        <option value="7" <?php selected($days, 7); ?>>Last 7 days</option>
                        <option value="30" <?php selected($days, 30); ?>>Last 30 days</option>
                        <option value="90" <?php selected($days, 90); ?>>Last 90 days</option>
                        <option value="365" <?php selected($days, 365); ?>>Last year</option>
                    </select>
                    
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <div class="analytics-dashboard">
                <div class="analytics-stats">
                    <div class="stat-card">
                        <h3><?php echo number_format($data['stats']->total_attempts); ?></h3>
                        <p>Total Attempts</p>
                    </div>
                    <div class="stat-card blocked">
                        <h3><?php echo number_format($data['stats']->blocked_attempts); ?></h3>
                        <p>Blocked Attempts</p>
                    </div>
                    <div class="stat-card granted">
                        <h3><?php echo number_format($data['stats']->granted_attempts); ?></h3>
                        <p>Granted Access</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($data['stats']->unique_ips); ?></h3>
                        <p>Unique IPs</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($data['stats']->unique_users); ?></h3>
                        <p>Unique Users</p>
                    </div>
                </div>
                
                <div class="analytics-charts">
                    <div class="chart-container">
                        <h3>Daily Access Attempts</h3>
                        <canvas id="dailyChart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3>Access Types</h3>
                        <canvas id="accessTypesChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <div class="analytics-tables">
                    <div class="table-container">
                        <h3>Top Blocked IP Addresses</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Blocked Attempts</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['top_blocked_ips'] as $ip_data): ?>
                                <tr>
                                    <td><?php echo esc_html($ip_data->ip_address); ?></td>
                                    <td><?php echo number_format($ip_data->attempts); ?></td>
                                    <td>
                                        <button class="button button-small block-ip-btn" data-ip="<?php echo esc_attr($ip_data->ip_address); ?>">
                                            Block IP
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .analytics-filters {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .analytics-filters form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .analytics-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0;
            font-size: 32px;
            color: #0073aa;
        }
        
        .stat-card.blocked h3 {
            color: #dc3232;
        }
        
        .stat-card.granted h3 {
            color: #46b450;
        }
        
        .stat-card p {
            margin: 10px 0 0 0;
            color: #666;
            font-weight: 500;
        }
        
        .analytics-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-container h3 {
            margin-top: 0;
            color: #333;
        }
        
        .analytics-tables {
            display: grid;
            gap: 30px;
        }
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-container h3 {
            margin-top: 0;
            color: #333;
        }
        </style>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Daily chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode(array_reverse($data['daily_stats'])); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [{
                    label: 'Total Attempts',
                    data: dailyData.map(d => d.total),
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Blocked Attempts',
                    data: dailyData.map(d => d.blocked),
                    borderColor: '#dc3232',
                    backgroundColor: 'rgba(220, 50, 50, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Access types chart
        const accessTypesCtx = document.getElementById('accessTypesChart').getContext('2d');
        const accessTypesData = <?php echo json_encode($data['access_types']); ?>;
        
        new Chart(accessTypesCtx, {
            type: 'doughnut',
            data: {
                labels: accessTypesData.map(d => d.access_type),
                datasets: [{
                    data: accessTypesData.map(d => d.count),
                    backgroundColor: [
                        '#0073aa',
                        '#dc3232',
                        '#46b450',
                        '#ffb900',
                        '#826eb4'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Block IP functionality
        document.querySelectorAll('.block-ip-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const ip = this.dataset.ip;
                if (confirm('Are you sure you want to block IP: ' + ip + '?')) {
                    // Implement IP blocking functionality
                    alert('IP blocking functionality would be implemented here');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        
        $data = $this->get_dashboard_data($post_id, $days);
        
        wp_send_json_success($data);
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
}
