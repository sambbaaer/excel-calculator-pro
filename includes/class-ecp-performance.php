<?php

/**
 * Verbessertes Performance-Management für Excel Calculator Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECP_Performance
{
    private $cache_group = 'ecp_performance';
    private $cache_time = 3600; // 1 hour
    private $performance_data = array();
    private $timers = array();
    private $memory_tracking = array();

    public function __construct()
    {
        $this->init_performance_monitoring();
        $this->setup_caching();
        $this->setup_optimization_hooks();
    }

    private function init_performance_monitoring()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array($this, 'output_performance_data'));
            add_action('admin_footer', array($this, 'output_performance_data'));
            add_action('admin_bar_menu', array($this, 'add_admin_bar_performance'), 100);
        }

        add_action('wp_loaded', array($this, 'schedule_cleanup'));
        add_action('ecp_performance_cleanup', array($this, 'cleanup_performance_data'));
    }

    private function setup_caching()
    {
        // Cache invalidation hooks
        add_action('ecp_calculator_saved', array($this, 'clear_calculator_cache'));
        add_action('ecp_calculator_deleted', array($this, 'clear_calculator_cache'));
        add_action('ecp_settings_updated', array($this, 'clear_all_cache'));

        // Preload critical data
        add_action('init', array($this, 'preload_critical_data'));
    }

    private function setup_optimization_hooks()
    {
        // Asset optimization
        add_action('wp_enqueue_scripts', array($this, 'optimize_frontend_assets'), 5);
        add_action('admin_enqueue_scripts', array($this, 'optimize_admin_assets'), 5);

        // Database optimization
        add_filter('ecp_database_query', array($this, 'optimize_database_query'), 10, 2);

        // Memory optimization
        add_action('wp_loaded', array($this, 'optimize_memory_usage'));
    }

    /**
     * Performance Timer System
     */
    public function start_timer($operation)
    {
        $this->timers[$operation] = array(
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
    }

    public function end_timer($operation)
    {
        if (!isset($this->timers[$operation])) {
            return false;
        }

        $timer = $this->timers[$operation];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        $result = array(
            'operation' => $operation,
            'duration' => $end_time - $timer['start_time'],
            'memory_used' => $end_memory - $timer['start_memory'],
            'memory_peak' => memory_get_peak_usage(true) - $timer['peak_memory'],
            'timestamp' => current_time('mysql')
        );

        $this->performance_data[] = $result;
        unset($this->timers[$operation]);

        // Log slow operations
        if ($result['duration'] > 1.0) { // More than 1 second
            $this->log_slow_operation($result);
        }

        return $result;
    }

    /**
     * Advanced Caching System
     */
    public function get_cache($key, $group = null)
    {
        $group = $group ?: $this->cache_group;

        if (wp_using_ext_object_cache()) {
            return wp_cache_get($key, $group);
        }

        return get_transient($this->get_cache_key($key, $group));
    }

    public function set_cache($key, $data, $expiration = null, $group = null)
    {
        $group = $group ?: $this->cache_group;
        $expiration = $expiration ?: $this->cache_time;

        // Add cache metadata
        $cache_data = array(
            'data' => $data,
            'timestamp' => time(),
            'version' => ECP_VERSION,
            'hash' => md5(serialize($data))
        );

        if (wp_using_ext_object_cache()) {
            return wp_cache_set($key, $cache_data, $group, $expiration);
        }

        return set_transient($this->get_cache_key($key, $group), $cache_data, $expiration);
    }

    public function delete_cache($key, $group = null)
    {
        $group = $group ?: $this->cache_group;

        if (wp_using_ext_object_cache()) {
            return wp_cache_delete($key, $group);
        }

        return delete_transient($this->get_cache_key($key, $group));
    }

    private function get_cache_key($key, $group)
    {
        return "ecp_{$group}_{$key}";
    }

    /**
     * Calculator-specific caching
     */
    public function get_calculator_cache($calculator_id, $type = 'data')
    {
        $cache_key = "calculator_{$calculator_id}_{$type}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false && isset($cached['data'])) {
            return $cached['data'];
        }

        return false;
    }

    public function set_calculator_cache($calculator_id, $data, $type = 'data', $expiration = null)
    {
        $cache_key = "calculator_{$calculator_id}_{$type}";
        return $this->set_cache($cache_key, $data, $expiration);
    }

    public function clear_calculator_cache($calculator_id = null)
    {
        if ($calculator_id) {
            $patterns = array(
                "calculator_{$calculator_id}_data",
                "calculator_{$calculator_id}_rendered",
                "calculator_{$calculator_id}_settings"
            );

            foreach ($patterns as $pattern) {
                $this->delete_cache($pattern);
            }
        } else {
            $this->clear_cache_group($this->cache_group);
        }
    }

    public function clear_all_cache()
    {
        $this->clear_cache_group($this->cache_group);

        // Clear WordPress object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    private function clear_cache_group($group)
    {
        global $wpdb;

        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group($group);
        } else {
            // Clear transients
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like("_transient_ecp_{$group}") . '%'
            ));
        }
    }

    /**
     * Asset Optimization
     */
    public function optimize_frontend_assets()
    {
        if (!$this->page_has_calculators()) {
            return;
        }

        // Preload critical resources
        $this->preload_critical_assets();

        // Defer non-critical scripts
        add_filter('script_loader_tag', array($this, 'defer_non_critical_scripts'), 10, 2);

        // Optimize CSS delivery
        add_filter('style_loader_tag', array($this, 'optimize_css_delivery'), 10, 2);
    }

    public function optimize_admin_assets($hook)
    {
        if (!$this->is_ecp_admin_page($hook)) {
            return;
        }

        // Bundle assets if not in debug mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            $this->bundle_admin_assets();
        }

        // Minify inline styles
        add_filter('ecp_inline_styles', array($this, 'minify_css'));
    }

    private function page_has_calculators()
    {
        global $post;

        if (!$post) {
            return false;
        }

        $cache_key = "page_has_calculators_{$post->ID}";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached['data'];
        }

        $has_calculators = has_shortcode($post->post_content, 'excel_calculator') ||
            has_shortcode($post->post_content, 'ecp_calculator');

        $this->set_cache($cache_key, $has_calculators, 1800); // 30 minutes

        return $has_calculators;
    }

    private function is_ecp_admin_page($hook)
    {
        return strpos($hook, 'excel-calculator-pro') !== false;
    }

    private function preload_critical_assets()
    {
        echo '<link rel="preload" href="' . ECP_PLUGIN_URL . 'assets/frontend.css" as="style">' . "\n";
        echo '<link rel="preload" href="' . ECP_PLUGIN_URL . 'assets/frontend.js" as="script">' . "\n";
    }

    public function defer_non_critical_scripts($tag, $handle)
    {
        $non_critical_scripts = array('ecp-frontend-js');

        if (in_array($handle, $non_critical_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }

        return $tag;
    }

    public function optimize_css_delivery($tag, $handle)
    {
        if ($handle === 'ecp-frontend-css') {
            // Add preload for CSS
            $preload_tag = str_replace('rel=\'stylesheet\'', 'rel=\'preload\' as=\'style\' onload="this.onload=null;this.rel=\'stylesheet\'"', $tag);
            return $preload_tag . '<noscript>' . $tag . '</noscript>';
        }

        return $tag;
    }

    /**
     * Database Optimization
     */
    public function optimize_database_query($query, $type)
    {
        // Add query caching for expensive operations
        if (strpos($query, 'SELECT') === 0) {
            return $this->cache_database_query($query);
        }

        return $query;
    }

    private function cache_database_query($query)
    {
        $cache_key = 'db_query_' . md5($query);
        $cached_result = $this->get_cache($cache_key);

        if ($cached_result !== false) {
            return $cached_result['data'];
        }

        // Execute query and cache result
        global $wpdb;
        $result = $wpdb->get_results($query);

        if (!$wpdb->last_error) {
            $this->set_cache($cache_key, $result, 1800); // 30 minutes
        }

        return $result;
    }

    /**
     * Memory Optimization
     */
    public function optimize_memory_usage()
    {
        // Track memory usage
        $this->memory_tracking['initial'] = memory_get_usage(true);

        // Set memory limit if not sufficient
        $current_limit = ini_get('memory_limit');
        $current_bytes = $this->convert_to_bytes($current_limit);
        $required_bytes = 128 * 1024 * 1024; // 128MB

        if ($current_bytes < $required_bytes) {
            ini_set('memory_limit', '128M');
        }

        // Register shutdown function to track peak memory
        register_shutdown_function(array($this, 'track_peak_memory'));
    }

    public function track_peak_memory()
    {
        $this->memory_tracking['peak'] = memory_get_peak_usage(true);
        $this->memory_tracking['final'] = memory_get_usage(true);

        // Log high memory usage
        if ($this->memory_tracking['peak'] > 100 * 1024 * 1024) { // 100MB
            error_log('ECP High Memory Usage: ' . size_format($this->memory_tracking['peak']));
        }
    }

    private function convert_to_bytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Critical Data Preloading
     */
    public function preload_critical_data()
    {
        // Preload frequently used settings
        $this->preload_settings();

        // Preload active calculators list
        $this->preload_active_calculators();

        // Preload templates
        $this->preload_templates();
    }

    private function preload_settings()
    {
        $cache_key = 'preloaded_settings';

        if ($this->get_cache($cache_key) === false) {
            $settings = array(
                'general' => get_option('ecp_general_settings', array()),
                'colors' => get_option('ecp_color_settings', array())
            );

            $this->set_cache($cache_key, $settings, 3600);
        }
    }

    private function preload_active_calculators()
    {
        $cache_key = 'active_calculators_list';

        if ($this->get_cache($cache_key) === false) {
            global $wpdb;

            $calculators = $wpdb->get_results(
                "SELECT id, name FROM {$wpdb->prefix}excel_calculators 
                 WHERE status = 'active' 
                 ORDER BY name ASC"
            );

            $this->set_cache($cache_key, $calculators, 1800);
        }
    }

    private function preload_templates()
    {
        $cache_key = 'available_templates';

        if ($this->get_cache($cache_key) === false) {
            global $wpdb;

            $templates = $wpdb->get_results(
                "SELECT id, name, category FROM {$wpdb->prefix}excel_calculator_templates 
                 WHERE is_public = 1 
                 ORDER BY sort_order ASC, name ASC"
            );

            $this->set_cache($cache_key, $templates, 3600);
        }
    }

    /**
     * Performance Monitoring
     */
    public function output_performance_data()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (empty($this->performance_data)) {
            return;
        }

        echo "\n<!-- ECP Performance Data\n";

        $total_time = 0;
        $total_memory = 0;

        foreach ($this->performance_data as $data) {
            printf(
                "%s: %.4fms (Memory: %s)\n",
                $data['operation'],
                $data['duration'] * 1000,
                size_format($data['memory_used'])
            );

            $total_time += $data['duration'];
            $total_memory += $data['memory_used'];
        }

        printf(
            "Total: %.4fms (Memory: %s, Peak: %s)\n",
            $total_time * 1000,
            size_format($total_memory),
            size_format(memory_get_peak_usage(true))
        );

        echo "-->\n";
    }

    public function add_admin_bar_performance($wp_admin_bar)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $performance_summary = $this->get_performance_summary();

        if ($performance_summary) {
            $wp_admin_bar->add_node(array(
                'id' => 'ecp-performance',
                'title' => sprintf(
                    'ECP: %s | %s',
                    $performance_summary['time'],
                    $performance_summary['memory']
                ),
                'href' => admin_url('admin.php?page=excel-calculator-pro&tab=performance')
            ));
        }
    }

    private function get_performance_summary()
    {
        if (empty($this->performance_data)) {
            return null;
        }

        $total_time = array_sum(array_column($this->performance_data, 'duration'));
        $peak_memory = memory_get_peak_usage(true);

        return array(
            'time' => sprintf('%.2fms', $total_time * 1000),
            'memory' => size_format($peak_memory),
            'operations' => count($this->performance_data)
        );
    }

    /**
     * Performance Analytics
     */
    public function get_performance_report($days = 7)
    {
        $cache_key = "performance_report_{$days}d";
        $cached = $this->get_cache($cache_key);

        if ($cached !== false) {
            return $cached['data'];
        }

        $report = array(
            'cache_stats' => $this->get_cache_statistics(),
            'slow_operations' => $this->get_slow_operations($days),
            'memory_usage' => $this->get_memory_statistics(),
            'database_performance' => $this->get_database_performance(),
            'recommendations' => $this->get_performance_recommendations()
        );

        $this->set_cache($cache_key, $report, 3600);

        return $report;
    }

    private function get_cache_statistics()
    {
        $stats = array(
            'hit_rate' => 0,
            'total_requests' => 0,
            'cache_size' => 0
        );

        // Implement cache statistics collection
        $cache_log = get_option('ecp_cache_log', array());

        if (!empty($cache_log)) {
            $hits = array_filter($cache_log, function ($entry) {
                return $entry['type'] === 'hit';
            });

            $stats['hit_rate'] = count($hits) / count($cache_log) * 100;
            $stats['total_requests'] = count($cache_log);
        }

        return $stats;
    }

    private function get_slow_operations($days)
    {
        $slow_operations = get_option('ecp_slow_operations', array());
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return array_filter($slow_operations, function ($operation) use ($cutoff_date) {
            return $operation['timestamp'] >= $cutoff_date;
        });
    }

    private function get_memory_statistics()
    {
        return array(
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'tracking_data' => $this->memory_tracking
        );
    }

    private function get_database_performance()
    {
        global $wpdb;

        return array(
            'total_queries' => get_num_queries(),
            'query_time' => timer_stop(),
            'slow_queries' => $this->get_slow_database_queries()
        );
    }

    private function get_slow_database_queries()
    {
        // This would require database query logging
        return get_option('ecp_slow_queries', array());
    }

    private function get_performance_recommendations()
    {
        $recommendations = array();

        // Check cache hit rate
        $cache_stats = $this->get_cache_statistics();
        if ($cache_stats['hit_rate'] < 80) {
            $recommendations[] = array(
                'type' => 'cache',
                'message' => __('Cache-Hit-Rate ist niedrig. Erwägen Sie eine Verlängerung der Cache-Zeit.', 'excel-calculator-pro'),
                'priority' => 'medium'
            );
        }

        // Check memory usage
        $memory_stats = $this->get_memory_statistics();
        $memory_usage_percent = ($memory_stats['current_usage'] / $this->convert_to_bytes($memory_stats['limit'])) * 100;

        if ($memory_usage_percent > 80) {
            $recommendations[] = array(
                'type' => 'memory',
                'message' => __('Hohe Speichernutzung erkannt. Optimierung empfohlen.', 'excel-calculator-pro'),
                'priority' => 'high'
            );
        }

        // Check for object cache
        if (!wp_using_ext_object_cache()) {
            $recommendations[] = array(
                'type' => 'cache',
                'message' => __('Object Cache nicht aktiviert. Redis oder Memcached empfohlen.', 'excel-calculator-pro'),
                'priority' => 'low'
            );
        }

        return $recommendations;
    }

    /**
     * Logging and Cleanup
     */
    private function log_slow_operation($operation)
    {
        $slow_operations = get_option('ecp_slow_operations', array());
        array_unshift($slow_operations, $operation);

        // Keep only last 50 entries
        $slow_operations = array_slice($slow_operations, 0, 50);
        update_option('ecp_slow_operations', $slow_operations);
    }

    public function schedule_cleanup()
    {
        if (!wp_next_scheduled('ecp_performance_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ecp_performance_cleanup');
        }
    }

    public function cleanup_performance_data()
    {
        // Clean old performance logs
        delete_option('ecp_slow_operations');
        delete_option('ecp_cache_log');

        // Clear expired cache entries
        $this->cleanup_expired_cache();

        // Optimize database tables
        $this->optimize_database_tables();
    }

    private function cleanup_expired_cache()
    {
        global $wpdb;

        // Clean expired transients
        $wpdb->query("
            DELETE t1, t2 FROM {$wpdb->options} t1
            LEFT JOIN {$wpdb->options} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_', '_transient_timeout_')
            WHERE t1.option_name LIKE '_transient_ecp_%'
            AND t2.option_value < UNIX_TIMESTAMP()
        ");
    }

    private function optimize_database_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'excel_calculators',
            $wpdb->prefix . 'excel_calculator_templates'
        );

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }
    }

    public function minify_css($css)
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);

        return $css;
    }
}

/**
 * Initialize performance system
 */
function ecp_init_performance()
{
    global $ecp_performance;
    $ecp_performance = new ECP_Performance();
    return $ecp_performance;
}

// Start performance monitoring early
add_action('plugins_loaded', 'ecp_init_performance', 5);
