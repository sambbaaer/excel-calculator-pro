<?php
/**
 * Excel Calculator Pro - Uninstall Handler
 * 
 * Diese Datei wird ausgeführt, wenn das Plugin deinstalliert wird.
 * Sie entfernt alle Plugin-Daten aus der Datenbank und dem Dateisystem.
 */

// Verhindert direkten Zugriff
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Uninstall-Klasse für saubere Deinstallation
 */
class ECP_Uninstaller {
    
    /**
     * Hauptmethode für Deinstallation
     */
    public static function uninstall() {
        // Sicherheitsprüfung
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Plugin-Informationen laden
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("uninstall-plugin_{$plugin}");
        
        // Benutzerbestätigung prüfen (falls gesetzt)
        $delete_data = get_option('ecp_delete_data_on_uninstall', false);
        
        if ($delete_data) {
            // Vollständige Deinstallation
            self::delete_all_data();
        } else {
            // Nur Plugin-Dateien entfernen, Daten behalten
            self::cleanup_temporary_data();
        }
        
        // Logs schreiben
        self::log_uninstall();
    }
    
    /**
     * Alle Plugin-Daten löschen
     */
    private static function delete_all_data() {
        global $wpdb;
        
        // Datenbank-Tabellen löschen
        self::drop_tables();
        
        // Optionen löschen
        self::delete_options();
        
        // Benutzerdefinierte Meta-Daten löschen
        self::delete_user_meta();
        
        // Uploads löschen
        self::delete_uploads();
        
        // Transients löschen
        self::delete_transients();
        
        // Cron-Jobs entfernen
        self::clear_cron_jobs();
        
        // Rewrite-Rules löschen
        flush_rewrite_rules();
    }
    
    /**
     * Nur temporäre Daten löschen
     */
    private static function cleanup_temporary_data() {
        // Nur Caches und temporäre Daten löschen
        self::delete_transients();
        self::clear_cron_jobs();
        wp_cache_flush();
    }
    
    /**
     * Datenbank-Tabellen löschen
     */
    private static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'excel_calculators',
            $wpdb->prefix . 'excel_calculator_templates'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
    
    /**
     * Plugin-Optionen löschen
     */
    private static function delete_options() {
        $options = array(
            'ecp_version',
            'ecp_general_settings',
            'ecp_advanced_settings',
            'ecp_delete_data_on_uninstall',
            'ecp_activation_date',
            'ecp_last_cleanup',
            'ecp_stats_cache',
            'ecp_license_key',
            'ecp_license_status'
        );
        
        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option); // Für Multisite
        }
    }
    
    /**
     * Benutzer-Meta-Daten löschen
     */
    private static function delete_user_meta() {
        global $wpdb;
        
        $meta_keys = array(
            'ecp_user_preferences',
            'ecp_last_calculator_used',
            'ecp_tutorial_completed'
        );
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->usermeta,
                array('meta_key' => $meta_key),
                array('%s')
            );
        }
    }
    
    /**
     * Upload-Dateien löschen
     */
    private static function delete_uploads() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/excel-calculator-pro';
        
        if (is_dir($plugin_upload_dir)) {
            self::recursive_rmdir($plugin_upload_dir);
        }
    }
    
    /**
     * Verzeichnis rekursiv löschen
     */
    private static function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::recursive_rmdir($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Transients löschen
     */
    private static function delete_transients() {
        global $wpdb;
        
        // Plugin-spezifische Transients löschen
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_ecp_%' 
             OR option_name LIKE '_transient_timeout_ecp_%'"
        );
        
        // Site Transients für Multisite
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_site_transient_ecp_%' 
             OR option_name LIKE '_site_transient_timeout_ecp_%'"
        );
    }
    
    /**
     * Cron-Jobs löschen
     */
    private static function clear_cron_jobs() {
        $cron_jobs = array(
            'ecp_daily_cleanup',
            'ecp_weekly_stats',
            'ecp_license_check'
        );
        
        foreach ($cron_jobs as $job) {
            wp_clear_scheduled_hook($job);
        }
    }
    
    /**
     * Deinstallation protokollieren
     */
    private static function log_uninstall() {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'version' => get_option('ecp_version', 'unknown'),
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'user_id' => get_current_user_id()
        );
        
        // Log an externe Statistik-API senden (optional)
        if (defined('ECP_STATS_ENDPOINT')) {
            wp_remote_post(ECP_STATS_ENDPOINT . '/uninstall', array(
                'body' => $log_data,
                'timeout' => 10,
                'blocking' => false // Non-blocking request
            ));
        }
        
        // Lokales Backup der wichtigsten Daten erstellen (optional)
        if (get_option('ecp_create_backup_on_uninstall', false)) {
            self::create_backup();
        }
    }
    
    /**
     * Backup der wichtigsten Daten erstellen
     */
    private static function create_backup() {
        global $wpdb;
        
        try {
            $backup_data = array(
                'version' => get_option('ecp_version'),
                'settings' => get_option('ecp_general_settings'),
                'export_date' => current_time('mysql'),
                'calculators' => array()
            );
            
            // Kalkulatoren exportieren
            $calculators = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}excel_calculators WHERE status = 'active'"
            );
            
            foreach ($calculators as $calc) {
                $backup_data['calculators'][] = array(
                    'name' => $calc->name,
                    'description' => $calc->description,
                    'fields' => $calc->fields,
                    'formulas' => $calc->formulas,
                    'settings' => $calc->settings,
                    'created_at' => $calc->created_at
                );
            }
            
            // Backup-Datei erstellen
            $upload_dir = wp_upload_dir();
            $backup_file = $upload_dir['basedir'] . '/ecp_backup_' . date('Y-m-d_H-i-s') . '.json';
            
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
            
            // Benutzer über Backup informieren
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                wp_mail(
                    $admin_email,
                    'Excel Calculator Pro - Backup erstellt',
                    "Ein Backup Ihrer Kalkulatoren wurde erstellt: {$backup_file}\n\n" .
                    "Sie können diese Datei verwenden, um Ihre Kalkulatoren später wiederherzustellen.",
                    array('Content-Type: text/plain; charset=UTF-8')
                );
            }
            
        } catch (Exception $e) {
            error_log('ECP Backup Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Multisite-Deinstallation
     */
    public static function uninstall_multisite() {
        if (!is_multisite()) {
            return;
        }
        
        $blog_ids = get_sites(array('fields' => 'ids'));
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            self::uninstall();
            restore_current_blog();
        }
    }
    
    /**
     * Deinstallation rückgängig machen (falls Plugin erneut installiert wird)
     */
    public static function maybe_restore_data() {
        // Suche nach Backup-Dateien
        $upload_dir = wp_upload_dir();
        $backup_files = glob($upload_dir['basedir'] . '/ecp_backup_*.json');
        
        if (!empty($backup_files)) {
            // Neueste Backup-Datei finden
            usort($backup_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $latest_backup = $backup_files[0];
            $backup_age = time() - filemtime($latest_backup);
            
            // Nur wiederherstellen, wenn Backup weniger als 7 Tage alt ist
            if ($backup_age < (7 * 24 * 60 * 60)) {
                set_transient('ecp_restore_available', $latest_backup, 30 * 24 * 60 * 60);
            }
        }
    }
    
    /**
     * Diagnose-Informationen sammeln
     */
    public static function get_uninstall_info() {
        global $wpdb;
        
        $info = array(
            'plugin_version' => get_option('ecp_version', 'unknown'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'active_theme' => get_stylesheet(),
            'active_plugins' => get_option('active_plugins', array()),
            'multisite' => is_multisite(),
            'tables_exist' => array(),
            'options_exist' => array(),
            'upload_dir_exists' => false
        );
        
        // Tabellen prüfen
        $tables = array(
            $wpdb->prefix . 'excel_calculators',
            $wpdb->prefix . 'excel_calculator_templates'
        );
        
        foreach ($tables as $table) {
            $info['tables_exist'][$table] = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        }
        
        // Optionen prüfen
        $options = array('ecp_version', 'ecp_general_settings');
        foreach ($options as $option) {
            $info['options_exist'][$option] = get_option($option) !== false;
        }
        
        // Upload-Verzeichnis prüfen
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/excel-calculator-pro';
        $info['upload_dir_exists'] = is_dir($plugin_upload_dir);
        
        return $info;
    }
}

// Hauptdeinstallation ausführen
if (is_multisite()) {
    ECP_Uninstaller::uninstall_multisite();
} else {
    ECP_Uninstaller::uninstall();
}

// Diagnose-Hook für Debugging (nur bei WP_DEBUG)
if (defined('WP_DEBUG') && WP_DEBUG) {
    $uninstall_info = ECP_Uninstaller::get_uninstall_info();
    error_log('ECP Uninstall Info: ' . print_r($uninstall_info, true));
}