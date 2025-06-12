<?php

/**
 * Plugin Name: Excel Calculator Pro
 * Plugin URI: https://samuelbaer.ch/
 * Description: Excel-ähnliche Kalkulatoren mit Echtzeit-Berechnung und Formelunterstützung
 * Version: 3.9.3
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
define('ECP_VERSION', '3.9.0');
define('ECP_MIN_PHP_VERSION', '7.4');
define('ECP_MIN_WP_VERSION', '5.0');

/**
 * Haupt-Plugin-Klasse
 */
class ExcelCalculatorPro
{

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
     * Shortcode-Handler
     */
    private $shortcode;

    /**
     * Plugin initialisiert
     */
    private $initialized = false;

    /**
     * Singleton-Pattern
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct()
    {
        // Systemanforderungen prüfen
        if (!$this->check_requirements()) {
            return;
        }

        // Basis-Hooks früh registrieren
        add_action('plugins_loaded', array($this, 'init'), 10);
        add_action('init', array($this, 'load_textdomain'));

        // Aktivierungs/Deaktivierungs-Hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('ExcelCalculatorPro', 'uninstall'));
    }

    /**
     * Plugin initialisieren
     */
    public function init()
    {
        if ($this->initialized) {
            return;
        }

        // Dependencies laden
        $this->load_dependencies();

        // Handler initialisieren
        $this->init_handlers();

        // Elementor-Integration initialisieren, falls Elementor aktiv ist
        if (did_action('elementor/loaded')) {
            require_once ECP_PLUGIN_PATH . 'includes/elementor/class-ecp-elementor-integration.php';
        }

        // Plugin als initialisiert markieren
        $this->initialized = true;

        // Plugin initialisiert Hook
        do_action('ecp_init');
    }

    /**
     * Systemanforderungen prüfen
     */
    private function check_requirements()
    {
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
    private function load_dependencies()
    {
        // Basis-Klassen laden
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-database.php';
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-admin.php';
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-frontend.php';
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-shortcode.php';

        // Prüfen ob alle Dateien existieren
        $required_files = array(
            ECP_PLUGIN_PATH . 'includes/class-ecp-database.php',
            ECP_PLUGIN_PATH . 'includes/class-ecp-admin.php',
            ECP_PLUGIN_PATH . 'includes/class-ecp-frontend.php',
            ECP_PLUGIN_PATH . 'includes/class-ecp-shortcode.php'
        );

        foreach ($required_files as $file) {
            if (!file_exists($file)) {
                add_action('admin_notices', function () use ($file) {
                    echo '<div class="notice notice-error"><p>';
                    printf(__('Excel Calculator Pro: Erforderliche Datei fehlt: %s', 'excel-calculator-pro'), $file);
                    echo '</p></div>';
                });
                return false;
            }
        }

        return true;
    }

    /**
     * Handler initialisieren
     */
    private function init_handlers()
    {
        // Database-Handler zuerst initialisieren
        $this->database = new ECP_Database();

        // Andere Handler initialisieren (nur wenn Database erfolgreich)
        if ($this->database) {
            $this->admin = new ECP_Admin($this->database);
            $this->frontend = new ECP_Frontend($this->database);
            $this->shortcode = new ECP_Shortcode($this->database);
        }
    }

    /**
     * Textdomain laden
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'excel-calculator-pro',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Plugin aktivieren
     */
    public function activate()
    {
        // Systemanforderungen nochmals prüfen
        if (!$this->check_requirements()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Excel Calculator Pro kann nicht aktiviert werden. Systemanforderungen nicht erfüllt.', 'excel-calculator-pro'));
        }

        // Dependencies laden für Aktivierung
        $this->load_dependencies();

        // Database-Handler für Aktivierung
        if (!$this->database) {
            $this->database = new ECP_Database();
        }

        // Tabellen erstellen
        if ($this->database) {
            $this->database->create_tables();
        }

        // Plugin-Ordner für Assets erstellen
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/excel-calculator-pro';
        if (!file_exists($plugin_dir)) {
            wp_mkdir_p($plugin_dir);
        }

        // Version speichern
        update_option('ecp_version', ECP_VERSION);
        update_option('ecp_activation_date', current_time('mysql'));

        // Rewrite-Rules aktualisieren
        flush_rewrite_rules();

        do_action('ecp_activated');
    }

    /**
     * Plugin deaktivieren
     */
    public function deactivate()
    {
        // Cleanup bei Bedarf
        flush_rewrite_rules();

        // Cleanup-Hook
        do_action('ecp_deactivated');
    }

    /**
     * Plugin deinstallieren
     */
    public static function uninstall()
    {
        // Alle Plugin-Daten entfernen (nur wenn explizit gewünscht)
        if (!get_option('ecp_delete_data_on_uninstall', false)) {
            return; // Daten behalten
        }

        global $wpdb;

        // Tabellen löschen
        $table_name = $wpdb->prefix . 'excel_calculators';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Optionen löschen
        $options = array(
            'ecp_version',
            'ecp_general_settings',
            'ecp_color_settings',
            'ecp_activation_date',
            'ecp_delete_data_on_uninstall',
            'ecp_db_version'
        );

        foreach ($options as $option) {
            delete_option($option);
        }

        // Upload-Ordner löschen
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/excel-calculator-pro';
        if (file_exists($plugin_dir)) {
            // Verzeichnis rekursiv löschen
            $files = array_diff(scandir($plugin_dir), array('.', '..'));
            foreach ($files as $file) {
                $file_path = $plugin_dir . '/' . $file;
                if (is_file($file_path)) {
                    unlink($file_path);
                }
            }
            rmdir($plugin_dir);
        }

        do_action('ecp_uninstalled');
    }

    /**
     * PHP-Version-Warnung
     */
    public function php_version_notice()
    {
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
    public function wp_version_notice()
    {
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
    public function get_database()
    {
        if (!$this->database) {
            $this->database = new ECP_Database();
        }
        return $this->database;
    }

    /**
     * Getter für Admin-Handler
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * Getter für Frontend-Handler
     */
    public function get_frontend()
    {
        return $this->frontend;
    }

    /**
     * Getter für Shortcode-Handler
     */
    public function get_shortcode()
    {
        return $this->shortcode;
    }

    /**
     * Plugin bereit prüfen
     */
    public function is_ready()
    {
        return $this->initialized && $this->database;
    }
}

/**
 * Plugin initialisieren
 */
function ecp_init()
{
    return ExcelCalculatorPro::get_instance();
}

/**
 * Sicherheitsfunktion: Plugin nur initialisieren wenn WordPress bereit ist
 */
if (defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
    // Plugin starten
    ecp_init();
}
