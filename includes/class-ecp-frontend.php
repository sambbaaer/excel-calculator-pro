<?php

/**
 * Frontend Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Frontend
{
    private $database;

    public function __construct($database = null)
    {
        $this->database = $database;
        $this->init_hooks();
    }

    private function get_database_instance() // Umbenannt, um Verwechslung mit $this->database zu vermeiden
    {
        if (!$this->database) {
            if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_database')) {
                $this->database = ecp_init()->get_database();
            } else {
                require_once ECP_PLUGIN_PATH . 'includes/class-ecp-database.php';
                $this->database = new ECP_Database();
            }
        }
        return $this->database;
    }


    /**
     * Frontend-Hooks initialisieren
     */
    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_inline_styles'), 5); // Priorität angepasst, um nach globalen Styles zu laden

        // AJAX-Berechnung bleibt bestehen
        add_action('wp_ajax_ecp_calculate', array($this, 'ajax_calculate'));
        add_action('wp_ajax_nopriv_ecp_calculate', array($this, 'ajax_calculate'));
    }

    /**
     * Frontend-Scripts einbinden
     */
    public function enqueue_scripts()
    {
        // Nur laden, wenn ein Kalkulator auf der Seite ist (optional, kann Performance verbessern)
        // if (!$this->has_calculator_on_page()) {
        // return;
        // }

        wp_enqueue_script(
            'ecp-frontend-js',
            ECP_PLUGIN_URL . 'assets/frontend.js',
            array('jquery'),
            ECP_VERSION,
            true
        );

        wp_enqueue_style(
            'ecp-frontend-css',
            ECP_PLUGIN_URL . 'assets/frontend.css',
            array(),
            ECP_VERSION
        );

        wp_localize_script('ecp-frontend-js', 'ecp_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_frontend_nonce'), // Frontend Nonce
            'settings' => $this->get_frontend_settings(),
            'strings' => array(
                'error_invalid_number' => __('Ungültige Zahl.', 'excel-calculator-pro'),
                'error_min_value' => __('Wert muss mind. %min% sein.', 'excel-calculator-pro'),
                'error_max_value' => __('Wert darf max. %max% sein.', 'excel-calculator-pro'),
                'copy_label_prefix' => __('Kopiere ', 'excel-calculator-pro'),
                'copied_success' => __('Kopiert!', 'excel-calculator-pro'),
                'copied_error' => __('Fehler!', 'excel-calculator-pro'),
            )
        ));
    }

    /**
     * Frontend-Einstellungen abrufen
     */
    private function get_frontend_settings()
    {
        $general_settings = get_option('ecp_general_settings', array());
        $color_settings = get_option('ecp_color_settings', array());

        return array(
            'currency' => isset($general_settings['default_currency']) ? $general_settings['default_currency'] : 'CHF',
            'number_format' => isset($general_settings['number_format']) ? $general_settings['number_format'] : 'de_CH',
            'currency_symbols' => apply_filters('ecp_currency_symbols', array(
                'CHF' => 'CHF', // Leerzeichen entfernt, da toLocaleString das oft besser handhabt
                'EUR' => '€',
                'USD' => '$'
            )),
            'enable_system_dark_mode' => !empty($color_settings['enable_system_dark_mode']), // Prüft auf Existenz und Wert
        );
    }

    /**
     * Inline-Styles mit benutzerdefinierten Farben hinzufügen
     */
    public function add_inline_styles()
    {
        $color_settings = get_option('ecp_color_settings', array());

        // Standardwerte holen, falls Einstellungen nicht gesetzt sind
        $admin_instance = null;
        if (class_exists('ECP_Admin')) {
            // Temporäre Instanz, um an die Farbdefinitionen zu kommen
            // Dies ist nicht ideal, besser wäre eine zentrale Konfigurationsklasse oder Helper-Funktion.
            // Für jetzt wird angenommen, dass die ECP_Admin Klasse geladen ist.
            // $admin_instance = new ECP_Admin(null); // Benötigt DB-Instanz, was hier problematisch sein kann.
            // Sicherer ist, die Defaults direkt hier zu definieren oder aus einer Helper-Klasse zu holen.
        }

        // Funktion, um Standardwerte für Farben zu holen
        // Diese Funktion sollte idealerweise in einer Helper-Klasse sein oder statisch in ECP_Admin.
        // Hier als lokale Funktion zur Vereinfachung:
        $get_color_default = function ($key, $mode = 'light') use ($color_settings) {
            // Diese Logik muss die Standardwerte aus ECP_Admin::get_color_definitions widerspiegeln
            $light_defaults = array(
                'background_color_light' => '#ffffff',
                'text_color_light' => '#2c3e50',
                'text_light_light' => '#6c757d',
                'border_color_light' => '#e1e5e9',
                'input_bg_light' => '#ffffff',
                'input_border_light' => '#dee2e6',
                'input_focus_border_light' => $color_settings['primary_color'] ?? '#007cba',
                'field_group_bg_light' => '#f8f9fa',
                'field_group_hover_bg_light' => '#e9ecef',
                'output_group_bg_gradient_start_light' => '#e8f4f8',
                'output_group_bg_gradient_end_light' => '#f0f9ff',
                'output_group_border_light' => '#b3d9e6',
                'output_field_bg_light' => '#ffffff',
                'output_field_color_light' => $color_settings['primary_color'] ?? '#007cba',
                'output_field_border_light' => $color_settings['primary_color'] ?? '#007cba',
                'copy_icon_color_light' => $color_settings['primary_color'] ?? '#007cba',
                'copy_icon_feedback_color_light' => '#28a745',
            );
            $dark_defaults = array(
                'background_color_dark' => '#1e1e1e',
                'text_color_dark' => '#e0e0e0',
                'text_light_dark' => '#adb5bd',
                'border_color_dark' => '#404040',
                'input_bg_dark' => '#2d2d2d',
                'input_border_dark' => '#505050',
                'input_focus_border_dark' => $color_settings['secondary_color'] ?? '#00a0d2',
                'field_group_bg_dark' => '#2a2a2a',
                'field_group_hover_bg_dark' => '#313131',
                'output_group_bg_gradient_start_dark' => '#1a3a4a',
                'output_group_bg_gradient_end_dark' => '#0f2f3f',
                'output_group_border_dark' => '#4a6c7a',
                'output_field_bg_dark' => '#2d2d2d',
                'output_field_color_dark' => $color_settings['secondary_color'] ?? '#00a0d2',
                'output_field_border_dark' => $color_settings['secondary_color'] ?? '#00a0d2',
                'copy_icon_color_dark' => $color_settings['secondary_color'] ?? '#00a0d2',
                'copy_icon_feedback_color_dark' => '#34d399',
            );
            $defaults = ($mode === 'dark') ? $dark_defaults : $light_defaults;
            return isset($color_settings[$key]) && !empty($color_settings[$key]) ? $color_settings[$key] : ($defaults[$key] ?? '#000000');
        };


        // Globale Farben
        $primary_color = $get_color_default('primary_color'); // Nimmt den globalen Wert
        $secondary_color = $get_color_default('secondary_color');

        $enable_system_dark_mode = !empty($color_settings['enable_system_dark_mode']);
        $calculator_width_option = isset($color_settings['calculator_width']) ? $color_settings['calculator_width'] : 'full';

        // Light Mode Farben
        $css_vars_light = array(
            '--ecp-background-color' => $get_color_default('background_color_light'),
            '--ecp-text-color' => $get_color_default('text_color_light'),
            '--ecp-text-light' => $get_color_default('text_light_light'),
            '--ecp-border-color' => $get_color_default('border_color_light'),
            '--ecp-input-bg' => $get_color_default('input_bg_light'),
            '--ecp-input-border' => $get_color_default('input_border_light'),
            '--ecp-input-focus-border' => $get_color_default('input_focus_border_light'),
            '--ecp-field-group-bg' => $get_color_default('field_group_bg_light'),
            '--ecp-field-group-hover-bg' => $get_color_default('field_group_hover_bg_light'),
            '--ecp-output-group-bg' => sprintf('linear-gradient(135deg, %s 0%%, %s 100%%)', $get_color_default('output_group_bg_gradient_start_light'), $get_color_default('output_group_bg_gradient_end_light')),
            '--ecp-output-group-border' => $get_color_default('output_group_border_light'),
            '--ecp-output-field-bg' => $get_color_default('output_field_bg_light'),
            '--ecp-output-field-color' => $get_color_default('output_field_color_light'),
            '--ecp-output-field-border' => $get_color_default('output_field_border_light'),
            '--ecp-copy-icon-color' => $get_color_default('copy_icon_color_light'),
            '--ecp-copy-icon-feedback-color' => $get_color_default('copy_icon_feedback_color_light'),
        );

        // Dark Mode Farben
        $css_vars_dark = array();
        if ($enable_system_dark_mode) {
            $css_vars_dark = array(
                '--ecp-background-color' => $get_color_default('background_color_dark', 'dark'),
                '--ecp-text-color' => $get_color_default('text_color_dark', 'dark'),
                '--ecp-text-light' => $get_color_default('text_light_dark', 'dark'),
                '--ecp-border-color' => $get_color_default('border_color_dark', 'dark'),
                '--ecp-input-bg' => $get_color_default('input_bg_dark', 'dark'),
                '--ecp-input-border' => $get_color_default('input_border_dark', 'dark'),
                '--ecp-input-focus-border' => $get_color_default('input_focus_border_dark', 'dark'),
                '--ecp-field-group-bg' => $get_color_default('field_group_bg_dark', 'dark'),
                '--ecp-field-group-hover-bg' => $get_color_default('field_group_hover_bg_dark', 'dark'),
                '--ecp-output-group-bg' => sprintf('linear-gradient(135deg, %s 0%%, %s 100%%)', $get_color_default('output_group_bg_gradient_start_dark', 'dark'), $get_color_default('output_group_bg_gradient_end_dark', 'dark')),
                '--ecp-output-group-border' => $get_color_default('output_group_border_dark', 'dark'),
                '--ecp-output-field-bg' => $get_color_default('output_field_bg_dark', 'dark'),
                '--ecp-output-field-color' => $get_color_default('output_field_color_dark', 'dark'),
                '--ecp-output-field-border' => $get_color_default('output_field_border_dark', 'dark'),
                '--ecp-copy-icon-color' => $get_color_default('copy_icon_color_dark', 'dark'),
                '--ecp-copy-icon-feedback-color' => $get_color_default('copy_icon_feedback_color_dark', 'dark'),
            );
        }

        // Breite des Kalkulators
        $width_value = '100%'; // Default für 'full'
        $margin_css = '';
        switch ($calculator_width_option) {
            case 'large':
                $width_value = '900px';
                $margin_css = 'margin-left: auto; margin-right: auto;';
                break;
            case 'contained':
                $width_value = '700px';
                $margin_css = 'margin-left: auto; margin-right: auto;';
                break;
            case 'medium':
                $width_value = '600px';
                $margin_css = 'margin-left: auto; margin-right: auto;';
                break;
            case 'small':
                $width_value = '500px';
                $margin_css = 'margin-left: auto; margin-right: auto;';
                break;
        }


        // CSS-Output generieren
        $css_output = ":root {\n";
        $css_output .= "    --ecp-primary-color: " . esc_attr($primary_color) . ";\n";
        $css_output .= "    --ecp-secondary-color: " . esc_attr($secondary_color) . ";\n";

        foreach ($css_vars_light as $var => $value) {
            $css_output .= "    " . esc_attr($var) . ": " . esc_attr($value) . ";\n";
        }
        $css_output .= "}\n";

        // Dark Mode Styles, falls aktiviert
        if ($enable_system_dark_mode && !empty($css_vars_dark)) {
            $css_output .= "@media (prefers-color-scheme: dark) {\n";
            $css_output .= "    :root {\n";
            foreach ($css_vars_dark as $var => $value) {
                $css_output .= "        " . esc_attr($var) . ": " . esc_attr($value) . ";\n";
            }
            $css_output .= "    }\n";
            // Spezifische Darkmode Animation für PulseGlow, falls benötigt
            $css_output .= "    .ecp-output-field.ecp-changed {\n";
            $css_output .= "        animation-name: ecpPulseGlowDark;\n";
            $css_output .= "    }\n";
            $css_output .= "}\n";
        }

        // Globale Breite und Margins für den Kalkulator-Container
        // Diese werden als CSS-Variablen im :root definiert und in frontend.css verwendet.
        // Hier setzen wir die Werte dieser Variablen.
        $css_output .= ":root {\n";
        $css_output .= "    --ecp-calculator-global-max-width: " . esc_attr($width_value) . ";\n";
        $css_output .= "    --ecp-calculator-global-margin-top: 30px;\n"; // Standard-Margin
        $css_output .= "    --ecp-calculator-global-margin-bottom: 30px;\n"; // Standard-Margin
        $css_output .= "}\n";

        // Direkte Styles für .ecp-calculator für die Breite, falls die Variablen nicht greifen oder für spezifische Fälle
        $css_output .= ".ecp-calculator {\n";
        $css_output .= "    max-width: var(--ecp-calculator-global-max-width, 100%);\n";
        if (!empty($margin_css)) {
            $css_output .= "    " . $margin_css . "\n";
        }
        $css_output .= "    margin-top: var(--ecp-calculator-global-margin-top, 30px);\n";
        $css_output .= "    margin-bottom: var(--ecp-calculator-global-margin-bottom, 30px);\n";
        $css_output .= "}\n";


        // Gradient für den oberen Rand
        $css_output .= ".ecp-calculator::before {\n";
        $css_output .= "    background: linear-gradient(90deg, var(--ecp-primary-color) 0%, var(--ecp-secondary-color) 50%, var(--ecp-primary-color) 100%);\n";
        $css_output .= "}\n";


        // Inline-Style ausgeben
        if (!empty($css_output)) {
            echo '<style id="ecp-inline-styles">' . "\n" . $css_output . "\n" . '</style>' . "\n";
        }
    }


    /**
     * AJAX-Handler für Berechnungen (bleibt unverändert)
     * Diese Funktion wird nicht mehr direkt für die Echtzeit-Berechnung im Frontend verwendet,
     * da dies nun clientseitig geschieht. Sie könnte aber für serverseitige Validierung
     * oder komplexe Berechnungen, die nicht im Frontend erfolgen sollen, beibehalten werden.
     * Für dieses Update wird sie als Platzhalter belassen.
     */
    public function ajax_calculate()
    {
        check_ajax_referer('ecp_frontend_nonce', 'nonce');

        $calculator_id = isset($_POST['calculator_id']) ? intval($_POST['calculator_id']) : 0;
        $input_values = isset($_POST['inputs']) && is_array($_POST['inputs']) ? $_POST['inputs'] : array();

        if (empty($calculator_id) || empty($input_values)) {
            wp_send_json_error(__('Ungültige Anfrage.', 'excel-calculator-pro'));
        }

        $db = $this->get_database_instance(); // Sicherstellen, dass DB-Instanz vorhanden ist
        $calculator = $db->get_calculator($calculator_id);

        if (!$calculator) {
            wp_send_json_error(__('Kalkulator nicht gefunden.', 'excel-calculator-pro'));
        }

        // Sanitization der Eingabewerte
        $sanitized_inputs = array();
        foreach ($input_values as $field_id => $value) {
            // Feld-ID muss mit Buchstaben beginnen, gefolgt von alphanumerischen Zeichen oder Unterstrichen
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field_id)) {
                $sanitized_inputs[sanitize_key($field_id)] = is_numeric($value) ? floatval($value) : sanitize_text_field($value);
            }
        }


        // Berechnung durchführen (vereinfacht - die Logik ist jetzt im Frontend JS)
        // Hier könnte eine serverseitige Neuberechnung oder Validierung stattfinden.
        // Für dieses Beispiel geben wir einfach die Eingaben zurück.
        // In einer echten Implementierung würde hier der ECPFormulaParser serverseitig verwendet.

        $results = array();
        // Beispiel:
        // $parser = new ECPFormulaParser(); // Annahme: Parser-Klasse ist auch serverseitig verfügbar
        // foreach($calculator->formulas as $formula_obj) {
        // if(isset($formula_obj['formula']) && isset($formula_obj['label'])) {
        // $result_value = $parser->parse($formula_obj['formula'], $sanitized_inputs);
        // $results[$formula_obj['label']] = $result_value; // oder formatieren
        // }
        // }

        // Da die Berechnung clientseitig erfolgt, ist diese AJAX-Funktion für die
        // reine Berechnung nicht mehr primär zuständig.
        // Sie könnte für Logging, serverseitige Validierung oder komplexe Operationen dienen.
        // Für den Moment senden wir eine Erfolgsmeldung, da die Hauptlogik im JS liegt.
        wp_send_json_success(array('message' => 'Anfrage erhalten', 'inputs_received' => $sanitized_inputs, 'calculated_results' => $results));
    }

    /**
     * Prüft, ob ein Kalkulator-Shortcode auf der aktuellen Seite/im aktuellen Beitrag vorhanden ist.
     * (Optional, für bedingtes Laden von Skripten)
     */
    private function has_calculator_on_page()
    {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'excel_calculator')) {
            return true;
        }
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'ecp_calculator')) { // Alias prüfen
            return true;
        }
        // Hier könnte man auch Widgets prüfen, falls Kalkulatoren in Widgets verwendet werden können.
        return false;
    }
}
