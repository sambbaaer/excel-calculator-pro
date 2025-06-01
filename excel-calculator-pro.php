<?php
/**
 * Plugin Name: Excel Calculator Pro
 * Plugin URI: https://samuelbaer.ch/
 * Description: Excel-ähnliche Kalkulatoren mit Echtzeit-Berechnung und Formelunterstützung
 * Version: 2.0.0
 * Author: Samuel Baer
 * License: GPL v2 or later
 * Text Domain: excel-calculator-pro
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('ECP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ECP_VERSION', '2.0.0');
define('ECP_MIN_PHP_VERSION', '7.4');
define('ECP_MIN_WP_VERSION', '5.0');

/**
 * Haupt-Plugin-Klasse
 */
class ExcelCalculatorPro {
    
    /**
     * Plugin-Instanz
     */
    private static $instance = null;
    
    /**
     * Admin-Handler
     */
    private $admin;
    
    /**
     * Frontend-Handler
     */
    private $frontend;
    
    /**
     * Database-Handler
     */
    private $database;
    
    /**
     * Singleton-Pattern
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        $this->check_requirements();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Systemanforderungen prüfen
     */
    private function check_requirements() {
        if (version_compare(PHP_VERSION, ECP_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        if (version_compare(get_bloginfo('version'), ECP_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Abhängigkeiten laden
     */
    private function load_dependencies() {
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-database.php';
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-admin.php';
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-frontend.php';
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-shortcode.php';
        
        $this->database = new ECP_Database();
        $this->admin = new ECP_Admin();
        $this->frontend = new ECP_Frontend();
        
        new ECP_Shortcode();
    }
    
    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Aktivierungs/Deaktivierungs-Hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('ExcelCalculatorPro', 'uninstall'));
    }
    
    /**
     * Plugin initialisieren
     */
    public function init() {
        // Plugin initialisiert
        do_action('ecp_init');
    }
    
    /**
     * Textdomain laden
     */
    public function load_textdomain() {
        load_plugin_textdomain('excel-calculator-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Plugin aktivieren
     */
    public function activate() {
        // Tabellen erstellen
        $this->database->create_tables();
        
        // Plugin-Ordner für Assets erstellen
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/excel-calculator-pro';
        if (!file_exists($plugin_dir)) {
            wp_mkdir_p($plugin_dir);
        }
        
        // Version speichern
        update_option('ecp_version', ECP_VERSION);
        
        // Rewrite-Rules aktualisieren
        flush_rewrite_rules();
        
        do_action('ecp_activated');
    }
    
    /**
     * Plugin deaktivieren
     */
    public function deactivate() {
        // Cleanup bei Bedarf
        flush_rewrite_rules();
        do_action('ecp_deactivated');
    }
    
    /**
     * Plugin deinstallieren
     */
    public static function uninstall() {
        // Alle Plugin-Daten entfernen
        global $wpdb;
        
        // Tabellen löschen
        $table_name = $wpdb->prefix . 'excel_calculators';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Optionen löschen
        delete_option('ecp_version');
        delete_option('ecp_settings');
        
        // Upload-Ordner löschen
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/excel-calculator-pro';
        if (file_exists($plugin_dir)) {
            wp_delete_file_from_directory($plugin_dir, $plugin_dir);
        }
        
        do_action('ecp_uninstalled');
    }
    
    /**
     * PHP-Version-Warnung
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('Excel Calculator Pro erfordert PHP %s oder höher. Sie verwenden PHP %s.', 'excel-calculator-pro'),
            ECP_MIN_PHP_VERSION,
            PHP_VERSION
        );
        echo '</p></div>';
    }
    
    /**
     * WordPress-Version-Warnung
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('Excel Calculator Pro erfordert WordPress %s oder höher. Sie verwenden WordPress %s.', 'excel-calculator-pro'),
            ECP_MIN_WP_VERSION,
            get_bloginfo('version')
        );
        echo '</p></div>';
    }
    
    /**
     * Getter für Database-Handler
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Getter für Admin-Handler
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Getter für Frontend-Handler
     */
    public function get_frontend() {
        return $this->frontend;
    }
}

/**
 * Plugin initialisieren
 */
function ecp_init() {
    return ExcelCalculatorPro::get_instance();
}

// Plugin starten
ecp_init();