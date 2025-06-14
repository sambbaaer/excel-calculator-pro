<?php

/**
 * Verbesserte Elementor-Integration für Excel Calculator Pro
 */

if (!defined('ABSPATH')) {
    exit; // Direkten Zugriff verhindern
}

/**
 * Hauptklasse für die Elementor-Integration
 */
final class ECP_Elementor_Integration
{
    /**
     * @var ECP_Elementor_Integration
     */
    private static $_instance = null;

    /**
     * @var string Plugin-Version für Asset-Caching
     */
    private $version;

    /**
     * Singleton-Instanz sicherstellen
     *
     * @return ECP_Elementor_Integration Eine Instanz der Klasse
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->version = defined('ECP_VERSION') ? ECP_VERSION : '1.0.0';
        $this->init_hooks();
    }

    /**
     * Hooks initialisieren
     */
    private function init_hooks()
    {
        // Widget-Registrierung mit verbesserter Kompatibilitätsprüfung
        add_action('elementor/widgets/register', array($this, 'register_widgets'));

        // Kategorien hinzufügen
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_category'));

        // Assets für Elementor-Editor
        add_action('elementor/editor/after_enqueue_scripts', array($this, 'editor_scripts'));
        add_action('elementor/editor/after_enqueue_styles', array($this, 'editor_styles'));

        // Frontend-Assets nur wenn nötig
        add_action('elementor/frontend/after_enqueue_scripts', array($this, 'frontend_scripts'));

        // Admin-Hinweise
        add_action('admin_notices', array($this, 'check_elementor_compatibility'));
    }

    /**
     * Benutzerdefinierte Widget-Kategorie hinzufügen
     *
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function add_elementor_widget_category($elements_manager)
    {
        $elements_manager->add_category(
            'excel-calculator-pro',
            array(
                'title' => __('Excel Calculator Pro', 'excel-calculator-pro'),
                'icon' => 'eicon-calculator',
                'active' => true,
            )
        );
    }

    /**
     * Widgets registrieren
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager)
    {
        // Prüfung, ob die Widget-Datei existiert
        $widget_file = ECP_PLUGIN_PATH . 'includes/elementor/class-ecp-calculator-widget.php';

        if (!file_exists($widget_file)) {
            $this->log_error('Widget-Datei nicht gefunden: ' . $widget_file);
            return;
        }

        // Widget-Klasse laden
        require_once $widget_file;

        // Prüfung, ob die Klasse existiert
        if (!class_exists('ECP_Calculator_Widget')) {
            $this->log_error('Widget-Klasse ECP_Calculator_Widget nicht gefunden');
            return;
        }

        try {
            // Widget registrieren
            $widgets_manager->register(new ECP_Calculator_Widget());

            // Debug-Info im Development-Modus
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ECP Elementor Widget erfolgreich registriert');
            }
        } catch (Exception $e) {
            $this->log_error('Fehler beim Registrieren des Widgets: ' . $e->getMessage());
        }
    }

    /**
     * Editor-Scripts einbinden
     */
    public function editor_scripts()
    {
        wp_enqueue_script(
            'ecp-elementor-editor',
            ECP_PLUGIN_URL . 'assets/elementor-editor.js',
            array('elementor-editor', 'jquery'),
            $this->version,
            true
        );

        // Lokalisierung für Editor
        wp_localize_script('ecp-elementor-editor', 'ecpElementor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_elementor_nonce'),
            'strings' => array(
                'loadingEditor' => __('Editor wird geladen...', 'excel-calculator-pro'),
                'editorReady' => __('Editor bereit', 'excel-calculator-pro'),
                'configurationSaved' => __('Konfiguration gespeichert', 'excel-calculator-pro'),
            )
        ));
    }

    /**
     * Editor-Styles einbinden
     */
    public function editor_styles()
    {
        wp_enqueue_style(
            'ecp-elementor-editor',
            ECP_PLUGIN_URL . 'assets/elementor-editor.css',
            array(),
            $this->version
        );
    }

    /**
     * Frontend-Scripts für Elementor
     */
    public function frontend_scripts()
    {
        // Nur laden wenn ein ECP-Widget auf der Seite ist
        if ($this->page_has_ecp_widget()) {
            wp_enqueue_script(
                'ecp-elementor-frontend',
                ECP_PLUGIN_URL . 'assets/elementor-frontend.js',
                array('ecp-frontend-js', 'elementor-frontend'),
                $this->version,
                true
            );
        }
    }

    /**
     * Prüft, ob die Seite ein ECP-Widget enthält
     */
    private function page_has_ecp_widget()
    {
        global $wp_query;

        if (!is_singular() || !isset($wp_query->post->ID)) {
            return false;
        }

        $post_id = $wp_query->post->ID;

        // Elementor-Meta prüfen
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);

        if (empty($elementor_data)) {
            return false;
        }

        // Nach ECP-Widget suchen
        return strpos($elementor_data, 'ecp_calculator_widget') !== false;
    }

    /**
     * Elementor-Kompatibilität prüfen
     */
    public function check_elementor_compatibility()
    {
        // Nur im Admin-Bereich anzeigen
        if (!is_admin()) {
            return;
        }

        // Nur für Administratoren
        if (!current_user_can('manage_options')) {
            return;
        }

        // Elementor installiert?
        if (!did_action('elementor/loaded')) {
            $this->show_admin_notice(
                __('Excel Calculator Pro: Elementor ist nicht installiert oder aktiviert.', 'excel-calculator-pro'),
                'warning'
            );
            return;
        }

        // Elementor-Version prüfen
        if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, '3.0.0', '<')) {
            $this->show_admin_notice(
                sprintf(
                    __('Excel Calculator Pro: Elementor Version %s oder höher wird empfohlen. Aktuelle Version: %s', 'excel-calculator-pro'),
                    '3.0.0',
                    ELEMENTOR_VERSION
                ),
                'warning'
            );
        }
    }

    /**
     * Admin-Hinweis anzeigen
     */
    private function show_admin_notice($message, $type = 'info')
    {
        $class = 'notice notice-' . $type;
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Fehler protokollieren
     */
    private function log_error($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ECP Elementor Integration Error: ' . $message);
        }
    }

    /**
     * Plugin-Informationen für Elementor bereitstellen
     */
    public function get_plugin_info()
    {
        return array(
            'name' => 'Excel Calculator Pro',
            'version' => $this->version,
            'elementor_tested' => '3.15.0',
            'widgets' => array('ecp_calculator_widget'),
            'categories' => array('excel-calculator-pro')
        );
    }

    /**
     * Debug-Informationen sammeln
     */
    public function get_debug_info()
    {
        return array(
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'nicht installiert',
            'elementor_pro_version' => defined('ELEMENTOR_PRO_VERSION') ? ELEMENTOR_PRO_VERSION : 'nicht installiert',
            'plugin_version' => $this->version,
            'widgets_registered' => class_exists('ECP_Calculator_Widget'),
            'category_registered' => true,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
    }
}

// Integration starten, aber nur wenn Elementor verfügbar ist
if (did_action('elementor/loaded')) {
    ECP_Elementor_Integration::instance();
} else {
    // Hook für späteren Start falls Elementor noch nicht geladen wurde
    add_action('elementor/loaded', function () {
        ECP_Elementor_Integration::instance();
    });
}
