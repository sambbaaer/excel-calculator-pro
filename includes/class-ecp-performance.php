<?php

/**
 * Performance-Optimierungen und Caching-System für Excel Calculator Pro
 */

class ECP_Performance_Manager
{
    private $cache_prefix = 'ecp_';
    private $cache_group = 'excel_calculator_pro';
    private $default_cache_time = 3600; // 1 Stunde
    private $performance_data = array();

    public function __construct()
    {
        $this->init_hooks();
        $this->setup_object_cache();
    }

    private function init_hooks()
    {
        // Cache-Management
        add_action('ecp_calculator_saved', array($this, 'clear_calculator_cache'));
        add_action('ecp_calculator_deleted', array($this, 'clear_calculator_cache'));
        add_action('ecp_settings_updated', array($this, 'clear_all_cache'));

        // Performance-Monitoring
        add_action('wp_footer', array($this, 'output_performance_data'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_performance'), 100);

        // Resource-Optimierung
        add_action('wp_enqueue_scripts', array($this, 'optimize_script_loading'), 5);
        add_action('wp_print_styles', array($this, 'optimize_style_loading'), 5);

        // Database-Optimierung
        add_action('ecp_daily_cleanup', array($this, 'cleanup_cache'));
        add_action('wp_loaded', array($this, 'schedule_cleanup'));

        // Lazy Loading für Kalkulatoren
        add_filter('ecp_render_calculator', array($this, 'maybe_lazy_load'), 10, 3);

        // Preloading für kritische Ressourcen
        add_action('wp_head', array($this, 'add_resource_hints'), 1);
    }

    /**
     * Object Cache Setup
     */
    private function setup_object_cache()
    {
        // Redis/Memcached Detection
        if (class_exists('Redis') || class_exists('Memcached')) {
            add_filter('ecp_use_object_cache', '__return_true');
        }
    }

    /**
     * Calculator-spezifisches Caching
     */
    public function get_calculator_cache($calculator_id, $cache_key = '')
    {
        $start_time = microtime(true);

        if (empty($cache_key)) {
            $cache_key = 'calculator_' . $calculator_id;
        }

        $full_key = $this->cache_prefix . $cache_key;

        if (wp_using_ext_object_cache()) {
            $data = wp_cache_get($full_key, $this->cache_group);
        } else {
            $data = get_transient($full_key);
        }

        $this->log_performance('cache_read', microtime(true) - $start_time, $cache_key);

        return $data;
    }

    public function set_calculator_cache($calculator_id, $data, $cache_key = '', $expiration = null)
    {
        $start_time = microtime(true);

        if (empty($cache_key)) {
            $cache_key = 'calculator_' . $calculator_id;
        }

        if ($expiration === null) {
            $expiration = $this->default_cache_time;
        }

        $full_key = $this->cache_prefix . $cache_key;

        // Cache-Daten mit Metadaten versehen
        $cache_data = array(
            'data' => $data,
            'timestamp' => time(),
            'version' => ECP_VERSION,
            'calculator_id' => $calculator_id
        );

        if (wp_using_ext_object_cache()) {
            $result = wp_cache_set($full_key, $cache_data, $this->cache_group, $expiration);
        } else {
            $result = set_transient($full_key, $cache_data, $expiration);
        }

        $this->log_performance('cache_write', microtime(true) - $start_time, $cache_key);

        return $result;
    }

    /**
     * Intelligente Cache-Invalidierung
     */
    public function clear_calculator_cache($calculator_id)
    {
        $patterns = array(
            'calculator_' . $calculator_id,
            'shortcode_' . $calculator_id . '_*',
            'results_' . $calculator_id . '_*',
            'template_' . $calculator_id
        );

        foreach ($patterns as $pattern) {
            $this->clear_cache_pattern($pattern);
        }

        // Event für externe Cache-Systeme
        do_action('ecp_calculator_cache_cleared', $calculator_id);
    }

    private function clear_cache_pattern($pattern)
    {
        global $wpdb;

        if (wp_using_ext_object_cache()) {
            // Object Cache - Pattern-basierte Löschung
            wp_cache_flush_group($this->cache_group);
        } else {
            // Transient-basierte Löschung
            $like_pattern = $wpdb->esc_like('_transient_' . $this->cache_prefix . str_replace('*', '', $pattern)) . '%';

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like_pattern
            ));

            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $like_pattern
            ));
        }
    }

    public function clear_all_cache()
    {
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group($this->cache_group);
        } else {
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%'
            ));
        }

        do_action('ecp_all_cache_cleared');
    }

    /**
     * Script- und Style-Optimierung
     */
    public function optimize_script_loading()
    {
        // Conditional Loading - nur laden wenn Kalkulatoren auf der Seite sind
        if (!$this->page_has_calculators()) {
            return;
        }

        // Script-Bundling für bessere Performance
        $this->bundle_calculator_scripts();

        // Preload kritische Ressourcen
        $this->preload_critical_resources();
    }

    public function optimize_style_loading()
    {
        if (!$this->page_has_calculators()) {
            return;
        }

        // Critical CSS inline einbetten
        $this->embed_critical_css();

        // Non-critical CSS asynchron laden
        $this->async_load_non_critical_css();
    }

    private function page_has_calculators()
    {
        global $post;

        if (!$post) {
            return false;
        }

        // Cache-Key für Seiten-Analyse
        $cache_key = 'page_calculators_' . $post->ID;
        $cached_result = $this->get_calculator_cache($post->ID, $cache_key);

        if ($cached_result !== false) {
            return $cached_result['data'];
        }

        // Shortcodes prüfen
        $has_shortcodes = has_shortcode($post->post_content, 'excel_calculator') ||
            has_shortcode($post->post_content, 'ecp_calculator') ||
            has_shortcode($post->post_content, 'ecp_list');

        // Widget-Bereiche prüfen
        $has_widgets = $this->check_widgets_for_calculators();

        $result = $has_shortcodes || $has_widgets;

        // Ergebnis cachen
        $this->set_calculator_cache($post->ID, $result, $cache_key, 1800); // 30 Minuten

        return $result;
    }

    private function check_widgets_for_calculators()
    {
        // Vereinfachte Widget-Prüfung
        $sidebars = wp_get_sidebars_widgets();

        foreach ($sidebars as $sidebar_id => $widgets) {
            if (is_array($widgets)) {
                foreach ($widgets as $widget) {
                    if (strpos($widget, 'ecp_') === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function bundle_calculator_scripts()
    {
        // Nur in Produktion aktivieren
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return;
        }

        $bundle_cache_key = 'script_bundle_' . ECP_VERSION;
        $bundle_url = $this->get_calculator_cache(0, $bundle_cache_key);

        if ($bundle_url === false) {
            $bundle_url = $this->create_script_bundle();
            $this->set_calculator_cache(0, $bundle_url, $bundle_cache_key, 86400); // 24 Stunden
        }

        if ($bundle_url) {
            wp_dequeue_script('ecp-frontend-js');
            wp_enqueue_script('ecp-bundled-js', $bundle_url['data'], array('jquery'), ECP_VERSION, true);
        }
    }

    private function create_script_bundle()
    {
        $upload_dir = wp_upload_dir();
        $bundle_dir = $upload_dir['basedir'] . '/ecp-bundles/';
        $bundle_url_base = $upload_dir['baseurl'] . '/ecp-bundles/';

        if (!file_exists($bundle_dir)) {
            wp_mkdir_p($bundle_dir);
        }

        $bundle_file = 'ecp-bundle-' . md5(ECP_VERSION . filemtime(ECP_PLUGIN_PATH . 'assets/frontend.js')) . '.js';
        $bundle_path = $bundle_dir . $bundle_file;
        $bundle_url = $bundle_url_base . $bundle_file;

        if (!file_exists($bundle_path)) {
            $scripts = array(
                ECP_PLUGIN_PATH . 'assets/frontend.js'
            );

            $bundled_content = '';
            foreach ($scripts as $script) {
                if (file_exists($script)) {
                    $bundled_content .= file_get_contents($script) . "\n";
                }
            }

            // Minifizierung (einfach)
            $bundled_content = $this->minify_js($bundled_content);

            file_put_contents($bundle_path, $bundled_content);
        }

        return $bundle_url;
    }

    private function minify_js($js)
    {
        // Einfache JS-Minifizierung
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js); // Block-Kommentare
        $js = preg_replace('/\/\/.*$/m', '', $js); // Zeilen-Kommentare
        $js = preg_replace('/\s+/', ' ', $js); // Mehrfache Leerzeichen
        $js = trim($js);

        return $js;
    }

    private function embed_critical_css()
    {
        $critical_css = $this->get_critical_css();

        if ($critical_css) {
            echo '<style id="ecp-critical-css">' . $critical_css . '</style>';
        }
    }

    private function get_critical_css()
    {
        $cache_key = 'critical_css_' . ECP_VERSION;
        $cached_css = $this->get_calculator_cache(0, $cache_key);

        if ($cached_css !== false) {
            return $cached_css['data'];
        }

        // Critical CSS definieren (Above-the-fold Styles)
        $critical_css = '
        .ecp-calculator{max-width:700px;margin:30px auto;padding:30px;border:1px solid #e1e5e9;border-radius:12px;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
        .ecp-calculator-title{font-size:28px;font-weight:700;color:#1a202c;margin:0 0 12px 0}
        .ecp-field-group{margin-bottom:24px;display:flex;align-items:center;padding:20px;background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px}
        .ecp-input-field{padding:14px 18px;border:2px solid #dee2e6;border-radius:8px;width:240px;font-size:16px}
        .ecp-output-field{font-weight:700;font-size:20px;color:#007cba;background:#fff;padding:14px 20px;border-radius:8px;border:2px solid #007cba}
        ';

        $this->set_calculator_cache(0, $critical_css, $cache_key, 86400);

        return $critical_css;
    }

    /**
     * Performance-Monitoring
     */
    public function start_performance_timer($operation)
    {
        $this->performance_data[$operation] = array(
            'start' => microtime(true),
            'memory_start' => memory_get_usage()
        );
    }

    public function end_performance_timer($operation)
    {
        if (!isset($this->performance_data[$operation])) {
            return;
        }

        $this->performance_data[$operation]['end'] = microtime(true);
        $this->performance_data[$operation]['memory_end'] = memory_get_usage();
        $this->performance_data[$operation]['duration'] =
            $this->performance_data[$operation]['end'] - $this->performance_data[$operation]['start'];
        $this->performance_data[$operation]['memory_used'] =
            $this->performance_data[$operation]['memory_end'] - $this->performance_data[$operation]['memory_start'];
    }

    private function log_performance($operation, $duration, $details = '')
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $this->performance_data['operations'][] = array(
            'operation' => $operation,
            'duration' => $duration,
            'details' => $details,
            'timestamp' => microtime(true)
        );
    }

    public function output_performance_data()
    {
        if (!current_user_can('manage_options') || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (empty($this->performance_data)) {
            return;
        }

        echo "\n<!-- ECP Performance Data\n";
        foreach ($this->performance_data as $operation => $data) {
            if (is_array($data) && isset($data['duration'])) {
                printf(
                    "%s: %.4fms (Memory: %s)\n",
                    $operation,
                    $data['duration'] * 1000,
                    size_format($data['memory_used'])
                );
            }
        }
        echo "-->\n";
    }

    public function add_admin_bar_performance($wp_admin_bar)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $total_time = 0;
        $operation_count = 0;

        foreach ($this->performance_data as $data) {
            if (is_array($data) && isset($data['duration'])) {
                $total_time += $data['duration'];
                $operation_count++;
            }
        }

        if ($operation_count > 0) {
            $wp_admin_bar->add_node(array(
                'id' => 'ecp-performance',
                'title' => sprintf('ECP: %.2fms (%d ops)', $total_time * 1000, $operation_count),
                'href' => admin_url('admin.php?page=excel-calculator-pro&tab=debug')
            ));
        }
    }

    /**
     * Database-Optimierung
     */
    public function schedule_cleanup()
    {
        if (!wp_next_scheduled('ecp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ecp_daily_cleanup');
        }
    }

    public function cleanup_cache()
    {
        // Abgelaufene Cache-Einträge löschen
        $this->cleanup_expired_cache();

        // Statistiken aktualisieren
        $this->update_cache_statistics();

        // Alte Bundle-Dateien löschen
        $this->cleanup_old_bundles();
    }

    private function cleanup_expired_cache()
    {
        global $wpdb;

        // Abgelaufene Transients löschen
        $wpdb->query("
            DELETE t1, t2 FROM {$wpdb->options} t1
            LEFT JOIN {$wpdb->options} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_', '_transient_timeout_')
            WHERE t1.option_name LIKE '_transient_ecp_%'
            AND t2.option_value < UNIX_TIMESTAMP()
        ");
    }

    private function cleanup_old_bundles()
    {
        $upload_dir = wp_upload_dir();
        $bundle_dir = $upload_dir['basedir'] . '/ecp-bundles/';

        if (!is_dir($bundle_dir)) {
            return;
        }

        $files = glob($bundle_dir . 'ecp-bundle-*.js');
        $current_bundle = 'ecp-bundle-' . md5(ECP_VERSION . filemtime(ECP_PLUGIN_PATH . 'assets/frontend.js')) . '.js';

        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename !== $current_bundle && filemtime($file) < (time() - 7 * 24 * 3600)) {
                unlink($file);
            }
        }
    }

    /**
     * Resource Hints für bessere Performance
     */
    public function add_resource_hints()
    {
        if (!$this->page_has_calculators()) {
            return;
        }

        // DNS-Prefetch für externe Ressourcen
        echo '<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">' . "\n";

        // Preload kritische Assets
        echo '<link rel="preload" href="' . ECP_PLUGIN_URL . 'assets/frontend.css" as="style">' . "\n";
        echo '<link rel="preload" href="' . ECP_PLUGIN_URL . 'assets/frontend.js" as="script">' . "\n";

        // Prefetch für wahrscheinlich benötigte Ressourcen
        echo '<link rel="prefetch" href="' . admin_url('admin-ajax.php') . '">' . "\n";
    }

    /**
     * Cache-Statistiken
     */
    public function get_cache_statistics()
    {
        global $wpdb;

        $stats = array();

        // Transient-Statistiken
        $transient_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_ecp_%'
        ");

        $stats['transient_count'] = $transient_count;

        // Object Cache Statistiken (falls verfügbar)
        if (wp_using_ext_object_cache()) {
            $stats['object_cache'] = true;
            $stats['cache_type'] = $this->detect_cache_type();
        } else {
            $stats['object_cache'] = false;
            $stats['cache_type'] = 'transients';
        }

        // Cache-Grösse schätzen
        $cache_size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_ecp_%'
        ");

        $stats['cache_size'] = $cache_size;

        return $stats;
    }

    private function detect_cache_type()
    {
        if (class_exists('Redis')) {
            return 'Redis';
        } elseif (class_exists('Memcached')) {
            return 'Memcached';
        } elseif (function_exists('apcu_store')) {
            return 'APCu';
        } else {
            return 'Unknown Object Cache';
        }
    }

    private function update_cache_statistics()
    {
        $stats = $this->get_cache_statistics();
        update_option('ecp_cache_stats', $stats);
    }

    /**
     * Lazy Loading für Kalkulatoren
     */
    public function maybe_lazy_load($output, $calculator, $atts)
    {
        // Lazy Loading nur bei grossen Kalkulatoren oder expliziter Anfrage
        $should_lazy_load = (
            isset($atts['lazy']) && $atts['lazy'] === 'true'
        ) || (
            count($calculator->fields) > 10 ||
            count($calculator->formulas) > 5
        );

        if ($should_lazy_load) {
            return $this->render_lazy_placeholder($calculator, $atts);
        }

        return $output;
    }

    private function render_lazy_placeholder($calculator, $atts)
    {
        $placeholder_id = 'ecp-lazy-' . $calculator->id . '-' . mt_rand();

        ob_start();
?>
        <div id="<?php echo esc_attr($placeholder_id); ?>" class="ecp-lazy-placeholder"
            data-calculator-id="<?php echo esc_attr($calculator->id); ?>"
            style="min-height: 300px; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
            <div class="ecp-lazy-content" style="text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">⚡</div>
                <h3 style="margin: 0 0 8px 0;"><?php echo esc_html($calculator->name); ?></h3>
                <p style="margin: 0 0 16px 0; color: #666;">Klicken Sie hier, um den Kalkulator zu laden</p>
                <button class="ecp-load-lazy" style="padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Jetzt laden
                </button>
            </div>
        </div>

        <script>
            document.getElementById('<?php echo esc_js($placeholder_id); ?>').addEventListener('click', function() {
                this.innerHTML = '<div style="text-align: center; padding: 40px;">Lädt...</div>';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=ecp_load_calculator&calculator_id=<?php echo $calculator->id; ?>&nonce=<?php echo wp_create_nonce('ecp_lazy_load'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.outerHTML = data.data;
                            // Kalkulator initialisieren falls jQuery verfügbar
                            if (typeof jQuery !== 'undefined' && jQuery('.ecp-calculator').last().length) {
                                jQuery('.ecp-calculator').last().ecpCalculator();
                            }
                        } else {
                            this.innerHTML = '<div style="color: red; text-align: center; padding: 20px;">Fehler beim Laden</div>';
                        }
                    });
            });
        </script>
<?php

        return ob_get_clean();
    }
}

/**
 * Performance-Manager initialisieren
 */
function ecp_init_performance_manager()
{
    global $ecp_performance;
    $ecp_performance = new ECP_Performance_Manager();
    return $ecp_performance;
}

// Performance-Manager starten
add_action('plugins_loaded', 'ecp_init_performance_manager', 5);
