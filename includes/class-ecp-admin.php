<?php

/**
 * Admin Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Admin
{

    /**
     * Database-Handler
     */
    private $database;

    /**
     * Konstruktor
     * Nimmt jetzt die Datenbankinstanz als Parameter entgegen.
     */
    public function __construct($db_instance)
    {
        $this->database = $db_instance;
        $this->init_hooks();
    }

    /**
     * Admin-Hooks initialisieren
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_init', array($this, 'admin_init'));

        // AJAX-Hooks
        add_action('wp_ajax_ecp_save_calculator', array($this, 'ajax_save_calculator'));
        add_action('wp_ajax_ecp_delete_calculator', array($this, 'ajax_delete_calculator'));
        add_action('wp_ajax_ecp_get_calculator', array($this, 'ajax_get_calculator'));
        add_action('wp_ajax_ecp_create_from_template', array($this, 'ajax_create_from_template'));
        add_action('wp_ajax_ecp_export_calculator', array($this, 'ajax_export_calculator'));
        add_action('wp_ajax_ecp_import_calculator', array($this, 'ajax_import_calculator'));

        // AJAX Hook für TinyMCE
        add_action('wp_ajax_ecp_get_calculators_for_tinymce', array($this, 'ajax_get_calculators_for_tinymce'));
    }

    /**
     * Admin-Menü erstellen (unter Einstellungen)
     */
    public function admin_menu()
    {
        add_options_page(
            __('Excel Calculator Pro', 'excel-calculator-pro'),
            __('Calculator Pro', 'excel-calculator-pro'),
            'manage_options',
            'excel-calculator-pro',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin-Scripts einbinden
     */
    public function admin_enqueue_scripts($hook)
    {
        if ($hook !== 'settings_page_excel-calculator-pro') {
            return;
        }

        // WordPress Color Picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');


        wp_enqueue_script(
            'ecp-admin-js',
            ECP_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'jquery-ui-sortable', 'wp-color-picker'), // wp-color-picker als Abhängigkeit hinzugefügt
            ECP_VERSION,
            true
        );

        wp_enqueue_style(
            'ecp-admin-css',
            ECP_PLUGIN_URL . 'assets/admin.css',
            array(),
            ECP_VERSION
        );

        // Admin-Lokalisierung
        wp_localize_script('ecp-admin-js', 'ecp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_admin_nonce'),
            'wp_spinner_url' => admin_url('images/spinner.gif'),
            'strings' => array(
                'confirm_delete' => __('Sind Sie sicher, dass Sie diesen Kalkulator löschen möchten?', 'excel-calculator-pro'),
                'error_name_required' => __('Bitte geben Sie einen Namen ein.', 'excel-calculator-pro'),
                'success_saved' => __('Kalkulator gespeichert!', 'excel-calculator-pro'),
                'success_deleted' => __('Kalkulator gelöscht!', 'excel-calculator-pro'),
                'error_occurred' => __('Ein Fehler ist aufgetreten.', 'excel-calculator-pro'), // Generische Fehlermeldung
                'loading' => __('Lädt...', 'excel-calculator-pro'),
                'template_created' => __('Kalkulator aus Vorlage erstellt!', 'excel-calculator-pro'),
                'new_calculator' => __('Neuer Kalkulator', 'excel-calculator-pro'),
                'edit_calculator' => __('Kalkulator bearbeiten', 'excel-calculator-pro'),
                'calculator' => __('Kalkulator', 'excel-calculator-pro'),
                'copy' => __('Kopie', 'excel-calculator-pro'),
                'success_duplicated' => __('Kalkulator erfolgreich dupliziert!', 'excel-calculator-pro'),
                'unsaved_changes_confirm' => __('Sie haben ungespeicherte Änderungen. Sind Sie sicher, dass Sie die Seite verlassen möchten?', 'excel-calculator-pro'),
                'unsaved_changes_confirm_new' => __('Ungespeicherte Änderungen im aktuellen Entwurf gehen verloren. Fortfahren?', 'excel-calculator-pro'),
                'unsaved_changes_confirm_load' => __('Ungespeicherte Änderungen im aktuellen Entwurf gehen verloren. Neuen Kalkulator laden?', 'excel-calculator-pro'),
                'unsaved_changes_confirm_cancel' => __('Ungespeicherte Änderungen verwerfen?', 'excel-calculator-pro'),
                'unsaved_changes_confirm_template' => __('Ungespeicherte Änderungen im aktuellen Editor gehen verloren. Vorlage verwenden?', 'excel-calculator-pro'),
                'select_calculator_to_export' => __('Bitte wählen Sie einen Kalkulator zum Exportieren aus.', 'excel-calculator-pro'),
                'select_import_file' => __('Bitte wählen Sie eine Datei zum Importieren aus.', 'excel-calculator-pro'),
                'invalid_json_file' => __('Ungültige Datei. Bitte laden Sie eine JSON-Datei hoch.', 'excel-calculator-pro'),
                'import_successful' => __('Kalkulator erfolgreich importiert!', 'excel-calculator-pro'),
                'copied' => __('Kopiert!', 'excel-calculator-pro'),
                'copy_failed' => __('Kopieren fehlgeschlagen!', 'excel-calculator-pro'),
                'dismiss_notice' => __('Diese Meldung ausblenden.', 'excel-calculator-pro'),
                'no_calculators_yet_title' => __('Noch keine Kalkulatoren', 'excel-calculator-pro'),
                'no_calculators_yet' => __('Sie haben noch keine Kalkulatoren erstellt.', 'excel-calculator-pro'),
                'create_first_calculator' => __('Klicken Sie auf "Neuer Kalkulator" oder verwenden Sie eine Vorlage, um zu beginnen.', 'excel-calculator-pro'),
                'sort_field' => __('Feld sortieren', 'excel-calculator-pro'),
                'remove_field' => __('Feld entfernen', 'excel-calculator-pro'),
                'field_id' => __('ID', 'excel-calculator-pro'),
                'field_id_placeholder' => __('z.B. kreditsumme (klein, keine Leerzeichen)', 'excel-calculator-pro'),
                'field_id_desc' => __('Eindeutige ID für dieses Feld. Wird in Formeln verwendet.', 'excel-calculator-pro'),
                'field_label' => __('Label', 'excel-calculator-pro'),
                'field_label_placeholder' => __('z.B. Kreditsumme', 'excel-calculator-pro'),
                'field_type' => __('Typ', 'excel-calculator-pro'),
                'field_type_number' => __('Zahl', 'excel-calculator-pro'),
                'field_type_text' => __('Text', 'excel-calculator-pro'),
                'field_default' => __('Standardwert', 'excel-calculator-pro'),
                'field_default_placeholder' => __('z.B. 0 oder leer', 'excel-calculator-pro'),
                'field_min_max' => __('Min/Max', 'excel-calculator-pro'),
                'min' => __('Min', 'excel-calculator-pro'),
                'max' => __('Max', 'excel-calculator-pro'),
                'field_step' => __('Schrittweite', 'excel-calculator-pro'),
                'step_placeholder' => __('beliebig / Zahl (z.B. 0.1)', 'excel-calculator-pro'),
                'field_unit' => __('Einheit', 'excel-calculator-pro'),
                'field_unit_placeholder' => __('z.B. €, %, kg', 'excel-calculator-pro'),
                'field_placeholder_input' => __('Platzhalter (im Feld)', 'excel-calculator-pro'),
                'field_placeholder_input_desc' => __('Optionaler Text im Eingabefeld', 'excel-calculator-pro'),
                'field_help' => __('Hilfetext (Tooltip/Info)', 'excel-calculator-pro'),
                'field_help_desc' => __('Optionale Erklärung für Benutzer', 'excel-calculator-pro'),
                'sort_output' => __('Ausgabefeld sortieren', 'excel-calculator-pro'),
                'remove_output' => __('Ausgabefeld entfernen', 'excel-calculator-pro'),
                'output_label' => __('Label', 'excel-calculator-pro'),
                'output_label_placeholder' => __('z.B. Monatliche Rate', 'excel-calculator-pro'),
                'output_formula' => __('Formel', 'excel-calculator-pro'),
                'output_formula_placeholder' => __('z.B. feld_1 * feld_2', 'excel-calculator-pro'),
                'formula_help' => __('Verwenden Sie Feld-IDs (z.B. <code>kreditsumme</code>). Funktionen: <code>WENN(bed;dann;sonst)</code>, <code>RUNDEN(zahl;dez)</code>, etc.', 'excel-calculator-pro'),
                'output_format' => __('Format', 'excel-calculator-pro'),
                'format_standard' => __('Standard (Automatisch)', 'excel-calculator-pro'),
                'format_currency' => __('Währung', 'excel-calculator-pro'),
                'format_percentage' => __('Prozent (%)', 'excel-calculator-pro'),
                'format_integer' => __('Ganzzahl', 'excel-calculator-pro'),
                'format_text' => __('Text (Keine Formatierung)', 'excel-calculator-pro'),
                'output_unit' => __('Einheit (nach Zahl)', 'excel-calculator-pro'),
                'output_unit_placeholder' => __('z.B. €, Jahre', 'excel-calculator-pro'),
                'output_help' => __('Hilfetext (Tooltip/Info)', 'excel-calculator-pro'),
                'output_help_desc' => __('Optionale Erklärung der Berechnung', 'excel-calculator-pro'),

            )
        ));
    }

    /**
     * Admin-Initialisierung - Erweiterte Farbfelder
     */
    public function admin_init()
    {
        // Einstellungen registrieren
        register_setting('ecp_settings', 'ecp_general_settings', array($this, 'sanitize_general_settings'));
        register_setting('ecp_settings', 'ecp_color_settings', array($this, 'sanitize_color_settings'));

        // Allgemeine Einstellungssektion
        add_settings_section(
            'ecp_general_section',
            __('Allgemeine Einstellungen', 'excel-calculator-pro'),
            array($this, 'general_section_callback'),
            'ecp_settings'
        );

        // Farb-Einstellungssektion (Hauptüberschrift)
        add_settings_section(
            'ecp_color_main_section', // Eindeutige ID für die Haupt-Farbsektion
            __('Farb- und Design-Einstellungen', 'excel-calculator-pro'),
            array($this, 'color_main_section_callback'),
            'ecp_settings'
        );


        // Globale Farben Untersektion
        add_settings_section(
            'ecp_color_global_section',
            __('Globale Farben & Design', 'excel-calculator-pro'),
            '__return_false', // Kein eigener Callback, nur als Gruppierung
            'ecp_settings' // Selbe Seite wie Hauptsektion
        );


        // Light Mode Farbsektion
        add_settings_section(
            'ecp_color_light_section',
            __('Light Mode Farben', 'excel-calculator-pro'),
            array($this, 'color_light_section_callback'),
            'ecp_settings'
        );

        // Dark Mode Farbsektion
        add_settings_section(
            'ecp_color_dark_section',
            __('Dark Mode Farben', 'excel-calculator-pro'),
            array($this, 'color_dark_section_callback'),
            'ecp_settings'
        );

        // Allgemeine Einstellungsfelder
        add_settings_field(
            'default_currency',
            __('Standard-Währung', 'excel-calculator-pro'),
            array($this, 'currency_field_callback'),
            'ecp_settings',
            'ecp_general_section'
        );

        add_settings_field(
            'number_format',
            __('Zahlenformat', 'excel-calculator-pro'),
            array($this, 'number_format_field_callback'),
            'ecp_settings',
            'ecp_general_section'
        );

        // Globale Farbfelder
        add_settings_field(
            'primary_color',
            __('Primärfarbe', 'excel-calculator-pro'),
            array($this, 'color_field_callback'), // Generische Callback-Funktion
            'ecp_settings',
            'ecp_color_global_section', // Zugehörigkeit zur globalen Farbsektion
            array(
                'id' => 'primary_color',
                'default' => '#007cba',
                'description' => __('Hauptfarbe für Buttons, Akzente und primäre Elemente.', 'excel-calculator-pro')
            )
        );

        add_settings_field(
            'secondary_color',
            __('Sekundärfarbe', 'excel-calculator-pro'),
            array($this, 'color_field_callback'),
            'ecp_settings',
            'ecp_color_global_section',
            array(
                'id' => 'secondary_color',
                'default' => '#00a0d2',
                'description' => __('Sekundäre Akzentfarbe, oft für Verläufe oder Hover-Effekte.', 'excel-calculator-pro')
            )
        );
        add_settings_field(
            'enable_system_dark_mode',
            __('System-Dark-Mode folgen', 'excel-calculator-pro'),
            array($this, 'checkbox_field_callback'),
            'ecp_settings',
            'ecp_color_global_section',
            array(
                'id' => 'enable_system_dark_mode',
                'description' => __('Frontend-Kalkulatoren an das helle/dunkle Design des Betriebssystems anpassen. Wenn aktiviert, werden die untenstehenden Dark-Mode-Farben verwendet, falls das System dies meldet.', 'excel-calculator-pro')
            )
        );

        add_settings_field(
            'calculator_width',
            __('Standard-Breite der Kalkulatoren', 'excel-calculator-pro'),
            array($this, 'calculator_width_field_callback'),
            'ecp_settings',
            'ecp_color_global_section'
        );


        // Light Mode Farben
        $light_colors = $this->get_color_definitions('light');
        foreach ($light_colors as $id => $details) {
            add_settings_field(
                $id . '_light',
                $details['label'],
                array($this, 'color_field_callback'),
                'ecp_settings',
                'ecp_color_light_section',
                array(
                    'id' => $id . '_light',
                    'default' => $details['default'],
                    'description' => $details['description']
                )
            );
        }

        // Dark Mode Farben
        $dark_colors = $this->get_color_definitions('dark');
        foreach ($dark_colors as $id => $details) {
            add_settings_field(
                $id . '_dark',
                $details['label'],
                array($this, 'color_field_callback'),
                'ecp_settings',
                'ecp_color_dark_section',
                array(
                    'id' => $id . '_dark',
                    'default' => $details['default'],
                    'description' => $details['description']
                )
            );
        }
    }

    /**
     * Definiert die Farboptionen für Light und Dark Mode.
     */
    private function get_color_definitions($mode_suffix = '')
    {
        // Standardwerte für Light Mode
        $defaults_light = array(
            'background_color' => '#ffffff',
            'text_color' => '#2c3e50',
            'text_light' => '#6c757d',
            'border_color' => '#e1e5e9',
            'input_bg' => '#ffffff',
            'input_border' => '#dee2e6',
            'input_focus_border' => '#007cba', // Wird von Primärfarbe abgeleitet, aber kann hier überschrieben werden
            'field_group_bg' => '#f8f9fa',
            'field_group_hover_bg' => '#e9ecef',
            'output_group_bg_gradient_start' => '#e8f4f8',
            'output_group_bg_gradient_end' => '#f0f9ff',
            'output_group_border' => '#b3d9e6',
            'output_field_bg' => '#ffffff',
            'output_field_color' => '#007cba', // Wird von Primärfarbe abgeleitet
            'output_field_border' => '#007cba', // Wird von Primärfarbe abgeleitet
            'copy_icon_color' => '#007cba',      // Wird von Primärfarbe abgeleitet
            'copy_icon_feedback_color' => '#28a745',
        );

        // Standardwerte für Dark Mode
        $defaults_dark = array(
            'background_color' => '#1e1e1e',
            'text_color' => '#e0e0e0',
            'text_light' => '#adb5bd',
            'border_color' => '#404040',
            'input_bg' => '#2d2d2d',
            'input_border' => '#505050',
            'input_focus_border' => '#00a0d2', // Wird von Sekundärfarbe abgeleitet
            'field_group_bg' => '#2a2a2a',
            'field_group_hover_bg' => '#313131',
            'output_group_bg_gradient_start' => '#1a3a4a',
            'output_group_bg_gradient_end' => '#0f2f3f',
            'output_group_border' => '#4a6c7a',
            'output_field_bg' => '#2d2d2d',
            'output_field_color' => '#00a0d2', // Wird von Sekundärfarbe abgeleitet
            'output_field_border' => '#00a0d2', // Wird von Sekundärfarbe abgeleitet
            'copy_icon_color' => '#00a0d2',      // Wird von Sekundärfarbe abgeleitet
            'copy_icon_feedback_color' => '#34d399',
        );

        $current_defaults = ($mode_suffix === 'dark') ? $defaults_dark : $defaults_light;

        return array(
            'background_color' => array('label' => __('Hintergrund Kalkulator', 'excel-calculator-pro'), 'default' => $current_defaults['background_color'], 'description' => __('Haupthintergrundfarbe des gesamten Kalkulator-Blocks.', 'excel-calculator-pro')),
            'text_color' => array('label' => __('Textfarbe (Standard)', 'excel-calculator-pro'), 'default' => $current_defaults['text_color'], 'description' => __('Standardtextfarbe für Labels, Titel etc.', 'excel-calculator-pro')),
            'text_light' => array('label' => __('Textfarbe (Hell)', 'excel-calculator-pro'), 'default' => $current_defaults['text_light'], 'description' => __('Für Beschreibungen, Hilfetexte, Einheiten.', 'excel-calculator-pro')),
            'border_color' => array('label' => __('Rahmenfarbe (Allgemein)', 'excel-calculator-pro'), 'default' => $current_defaults['border_color'], 'description' => __('Allgemeine Rahmenfarbe für Trennlinien und den Kalkulator-Rand.', 'excel-calculator-pro')),
            'input_bg' => array('label' => __('Eingabefeld Hintergrund', 'excel-calculator-pro'), 'default' => $current_defaults['input_bg'], 'description' => __('Hintergrund der Eingabefelder.', 'excel-calculator-pro')),
            'input_border' => array('label' => __('Eingabefeld Rahmen', 'excel-calculator-pro'), 'default' => $current_defaults['input_border'], 'description' => __('Rahmenfarbe der Eingabefelder.', 'excel-calculator-pro')),
            'input_focus_border' => array('label' => __('Eingabefeld Rahmen (Fokus)', 'excel-calculator-pro'), 'default' => $current_defaults['input_focus_border'], 'description' => __('Rahmenfarbe bei Fokussierung eines Eingabefeldes. Standard: Primärfarbe (Light) / Sekundärfarbe (Dark).', 'excel-calculator-pro')),
            'field_group_bg' => array('label' => __('Feldgruppe Hintergrund', 'excel-calculator-pro'), 'default' => $current_defaults['field_group_bg'], 'description' => __('Hintergrund für den Bereich, der ein Label und ein Eingabefeld umschliesst.', 'excel-calculator-pro')),
            'field_group_hover_bg' => array('label' => __('Feldgruppe Hintergrund (Hover)', 'excel-calculator-pro'), 'default' => $current_defaults['field_group_hover_bg'], 'description' => __('Hintergrund der Feldgruppe, wenn man mit der Maus darüberfährt.', 'excel-calculator-pro')),
            'output_group_bg_gradient_start' => array('label' => __('Ausgabegruppe Gradient Start', 'excel-calculator-pro'), 'default' => $current_defaults['output_group_bg_gradient_start'], 'description' => __('Startfarbe des Hintergrundverlaufs für Ausgabegruppen.', 'excel-calculator-pro')),
            'output_group_bg_gradient_end' => array('label' => __('Ausgabegruppe Gradient Ende', 'excel-calculator-pro'), 'default' => $current_defaults['output_group_bg_gradient_end'], 'description' => __('Endfarbe des Hintergrundverlaufs für Ausgabegruppen.', 'excel-calculator-pro')),
            'output_group_border' => array('label' => __('Ausgabegruppe Rahmen', 'excel-calculator-pro'), 'default' => $current_defaults['output_group_border'], 'description' => __('Rahmenfarbe für Ausgabegruppen.', 'excel-calculator-pro')),
            'output_field_bg' => array('label' => __('Ausgabefeld Hintergrund', 'excel-calculator-pro'), 'default' => $current_defaults['output_field_bg'], 'description' => __('Hintergrund der eigentlichen Ergebnis-Anzeige.', 'excel-calculator-pro')),
            'output_field_color' => array('label' => __('Ausgabefeld Textfarbe', 'excel-calculator-pro'), 'default' => $current_defaults['output_field_color'], 'description' => __('Textfarbe der Ergebnisse. Standard: Primärfarbe (Light) / Sekundärfarbe (Dark).', 'excel-calculator-pro')),
            'output_field_border' => array('label' => __('Ausgabefeld Rahmen', 'excel-calculator-pro'), 'default' => $current_defaults['output_field_border'], 'description' => __('Rahmenfarbe der Ergebnisse. Standard: Primärfarbe (Light) / Sekundärfarbe (Dark).', 'excel-calculator-pro')),
            'copy_icon_color' => array('label' => __('Kopieren-Icon Farbe', 'excel-calculator-pro'), 'default' => $current_defaults['copy_icon_color'], 'description' => __('Farbe des Kopieren-Icons neben den Ergebnissen. Standard: Primärfarbe (Light) / Sekundärfarbe (Dark).', 'excel-calculator-pro')),
            'copy_icon_feedback_color' => array('label' => __('Kopieren-Icon Feedback Farbe', 'excel-calculator-pro'), 'default' => $current_defaults['copy_icon_feedback_color'], 'description' => __('Farbe des Kopieren-Icons nach erfolgreichem Kopiervorgang.', 'excel-calculator-pro')),
        );
    }


    /**
     * Admin-Seite anzeigen
     */
    public function admin_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calculators';
    ?>
        <div class="wrap ecp-admin-wrap">
            <div class="ecp-admin-header">
                <h1><span class="dashicons dashicons-calculator"></span> Excel Calculator Pro</h1>
                <?php if ($current_tab === 'calculators') : ?>
                    <button id="ecp-new-calculator" class="button button-primary ecp-btn-icon">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Neuer Kalkulator', 'excel-calculator-pro'); ?>
                    </button>
                <?php endif; ?>
            </div>


            <nav class="nav-tab-wrapper">
                <a href="?page=excel-calculator-pro&tab=calculators" class="nav-tab <?php echo $current_tab === 'calculators' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> <?php _e('Kalkulatoren', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=templates" class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-layout"></span> <?php _e('Vorlagen', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=import-export" class="nav-tab <?php echo $current_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-upload"></span> <?php _e('Import/Export', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php _e('Einstellungen', 'excel-calculator-pro'); ?>
                </a>
            </nav>

            <div class="tab-content ecp-tab-content-<?php echo esc_attr($current_tab); ?>">
                <?php
                switch ($current_tab) {
                    case 'templates':
                        $this->templates_tab();
                        break;
                    case 'import-export':
                        $this->import_export_tab();
                        break;
                    case 'settings':
                        $this->settings_tab();
                        break;
                    default:
                        $this->calculators_tab();
                        break;
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Kalkulatoren-Tab
     */
    private function calculators_tab()
    {
    ?>
        <div id="ecp-calculators-tab">
            <!-- Der Button "Neuer Kalkulator" ist jetzt im Header -->
            <div id="ecp-calculators-list">
                <?php $this->display_calculators_list(); ?>
            </div>

            <div id="ecp-calculator-editor" style="display: none;">
                <div class="ecp-editor-header">
                    <h2 id="ecp-editor-title"><?php _e('Kalkulator bearbeiten', 'excel-calculator-pro'); ?></h2>
                </div>

                <div class="ecp-editor-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="calculator-name"><?php _e('Name:', 'excel-calculator-pro'); ?> <span class="ecp-required-asterisk">*</span></label></th>
                            <td><input type="text" id="calculator-name" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="calculator-description"><?php _e('Beschreibung (optional):', 'excel-calculator-pro'); ?></label></th>
                            <td><textarea id="calculator-description" class="large-text" rows="3"></textarea></td>
                        </tr>
                    </table>

                    <div class="ecp-editor-sections">
                        <div class="ecp-section">
                            <h3><span class="dashicons dashicons-edit-large"></span> <?php _e('Eingabefelder', 'excel-calculator-pro'); ?></h3>
                            <div id="ecp-fields-container" class="ecp-sortable">
                                <!-- Felder werden hier per JS eingefügt -->
                            </div>
                            <button id="ecp-add-field" class="button ecp-btn-icon">
                                <span class="dashicons dashicons-plus"></span> <?php _e('Eingabefeld hinzufügen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>

                        <div class="ecp-section">
                            <h3><span class="dashicons dashicons-chart-line"></span> <?php _e('Ausgabefelder & Formeln', 'excel-calculator-pro'); ?></h3>
                            <div id="ecp-outputs-container" class="ecp-sortable">
                                <!-- Ausgaben werden hier per JS eingefügt -->
                            </div>
                            <button id="ecp-add-output" class="button ecp-btn-icon">
                                <span class="dashicons dashicons-plus"></span> <?php _e('Ausgabefeld hinzufügen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ecp-editor-actions">
                    <div>
                        <button id="ecp-save-calculator" class="button button-primary ecp-btn-icon">
                            <span class="dashicons dashicons-yes-alt"></span> <?php _e('Speichern', 'excel-calculator-pro'); ?>
                        </button>
                        <button id="ecp-cancel-edit" class="button ecp-btn-icon">
                            <span class="dashicons dashicons-no"></span> <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                        </button>
                    </div>
                    <div>
                        <button id="ecp-duplicate-calculator" class="button ecp-btn-icon" style="display:none;">
                            <span class="dashicons dashicons-admin-page"></span> <?php _e('Duplizieren', 'excel-calculator-pro'); ?>
                        </button>
                        <button id="ecp-delete-calculator" class="button button-link-delete ecp-btn-icon" style="display:none;">
                            <span class="dashicons dashicons-trash"></span> <?php _e('Löschen', 'excel-calculator-pro'); ?>
                        </button>
                    </div>
                </div>
                <input type="hidden" id="calculator-id" value="0" />
            </div>
        </div>
    <?php
    }

    /**
     * Vorlagen-Tab
     */
    private function templates_tab()
    {
        $templates = $this->database->get_templates();
    ?>
        <div id="ecp-templates-tab">
            <p><?php _e('Wählen Sie eine Vorlage aus, um schnell einen neuen Kalkulator zu erstellen. Die Vorlage dient als Ausgangspunkt und kann im Editor angepasst werden.', 'excel-calculator-pro'); ?></p>

            <?php if (empty($templates)) : ?>
                <div class="ecp-empty-state">
                    <span class="dashicons dashicons-info-outline ecp-empty-icon"></span>
                    <h3><?php _e('Keine Vorlagen verfügbar', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Es wurden noch keine Standardvorlagen oder benutzerdefinierte Vorlagen gefunden.', 'excel-calculator-pro'); ?></p>
                </div>
            <?php else : ?>
                <div class="ecp-templates-grid">
                    <?php foreach ($templates as $template) : ?>
                        <div class="ecp-template-card" data-template-id="<?php echo esc_attr($template->id); ?>">
                            <div class="ecp-template-card-header">
                                <span class="dashicons dashicons-layout"></span>
                                <h3><?php echo esc_html($template->name); ?></h3>
                            </div>
                            <p class="ecp-template-description"><?php echo esc_html($template->description); ?></p>
                            <div class="ecp-template-actions">
                                <button class="button button-primary ecp-use-template ecp-btn-icon">
                                    <span class="dashicons dashicons-admin-page"></span> <?php _e('Vorlage verwenden', 'excel-calculator-pro'); ?>
                                </button>
                                <?php if (!empty($template->category)) : ?>
                                    <span class="ecp-template-category"><?php echo esc_html(ucfirst($template->category)); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="ecp-template-modal" class="ecp-modal" style="display: none;">
                <div class="ecp-modal-content">
                    <div class="ecp-modal-header">
                        <h3><?php _e('Kalkulator aus Vorlage erstellen', 'excel-calculator-pro'); ?></h3>
                        <span class="ecp-modal-close" role="button" tabindex="0" aria-label="<?php esc_attr_e('Schliessen', 'excel-calculator-pro'); ?>">&times;</span>
                    </div>
                    <div class="ecp-modal-body">
                        <table class="form-table">
                            <tr>
                                <th><label for="template-calculator-name"><?php _e('Name für neuen Kalkulator:', 'excel-calculator-pro'); ?> <span class="ecp-required-asterisk">*</span></label></th>
                                <td><input type="text" id="template-calculator-name" class="regular-text" required /></td>
                            </tr>
                        </table>
                        <div class="ecp-modal-actions">
                            <button id="ecp-create-from-template" class="button button-primary ecp-btn-icon">
                                <span class="dashicons dashicons-yes-alt"></span> <?php _e('Erstellen & Bearbeiten', 'excel-calculator-pro'); ?>
                            </button>
                            <button class="button ecp-modal-close ecp-btn-icon">
                                <span class="dashicons dashicons-no"></span> <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Import/Export-Tab
     */
    private function import_export_tab()
    {
    ?>
        <div id="ecp-import-export-tab" class="ecp-settings-sections">
            <div class="ecp-settings-section">
                <h3><span class="dashicons dashicons-download"></span> <?php _e('Kalkulator exportieren', 'excel-calculator-pro'); ?></h3>
                <p><?php _e('Wählen Sie einen Kalkulator aus, um ihn als JSON-Datei zu exportieren. Diese Datei kann später wieder importiert werden.', 'excel-calculator-pro'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ecp-export-calculator"><?php _e('Kalkulator auswählen:', 'excel-calculator-pro'); ?></label></th>
                        <td>
                            <select id="ecp-export-calculator">
                                <option value=""><?php _e('-- Kalkulator wählen --', 'excel-calculator-pro'); ?></option>
                                <?php
                                $calculators = $this->database->get_calculators();
                                if (!empty($calculators)) {
                                    foreach ($calculators as $calc) :
                                ?>
                                        <option value="<?php echo esc_attr($calc->id); ?>"><?php echo esc_html($calc->name); ?> (ID: <?php echo esc_attr($calc->id); ?>)</option>
                                <?php
                                    endforeach;
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button id="ecp-export-btn" class="button button-primary ecp-btn-icon">
                                <span class="dashicons dashicons-download"></span> <?php _e('Exportieren', 'excel-calculator-pro'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ecp-settings-section">
                <h3><span class="dashicons dashicons-upload"></span> <?php _e('Kalkulator importieren', 'excel-calculator-pro'); ?></h3>
                <p><?php _e('Laden Sie eine zuvor exportierte <code>.json</code> Kalkulator-Datei hoch. Der importierte Kalkulator wird als neuer Kalkulator erstellt.', 'excel-calculator-pro'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ecp-import-file"><?php _e('JSON-Datei auswählen:', 'excel-calculator-pro'); ?></label></th>
                        <td><input type="file" id="ecp-import-file" accept=".json" /></td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button id="ecp-import-btn" class="button button-primary ecp-btn-icon">
                                <span class="dashicons dashicons-upload"></span> <?php _e('Importieren & Bearbeiten', 'excel-calculator-pro'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    <?php
    }


    /**
     * Einstellungen-Tab
     */
    private function settings_tab()
    {
    ?>
        <div id="ecp-settings-tab">
            <form method="post" action="options.php">
                <?php
                settings_fields('ecp_settings'); // Gruppe für alle Einstellungen
                ?>
                <div class="ecp-settings-sections">
                    <?php
                    // Allgemeine Einstellungen
                    echo '<div class="ecp-settings-section">';
                    do_settings_sections('ecp_settings_general_section_page'); // Seite für allgemeine Einstellungen
                    echo '</div>';

                    // Farb- und Designeinstellungen
                    echo '<div class="ecp-settings-section">';
                    do_settings_sections('ecp_settings_color_main_section_page'); // Seite für Haupt-Farbsektion
                    echo '</div>';

                    // Globale Farben
                    echo '<div class="ecp-settings-section ecp-settings-subsection">';
                    echo '<h4>' . __('Globale Farben & Design', 'excel-calculator-pro') . '</h4>';
                    do_settings_sections('ecp_settings_color_global_section_page');
                    echo '</div>';


                    // Light Mode Farben
                    echo '<div class="ecp-settings-section ecp-settings-subsection">';
                    echo '<h4>' . __('Light Mode Farben', 'excel-calculator-pro') . '</h4>';
                    do_settings_sections('ecp_settings_color_light_section_page');
                    echo '</div>';

                    // Dark Mode Farben
                    echo '<div class="ecp-settings-section ecp-settings-subsection">';
                    echo '<h4>' . __('Dark Mode Farben', 'excel-calculator-pro') . '</h4>';
                    do_settings_sections('ecp_settings_color_dark_section_page');
                    echo '</div>';
                    ?>
                </div>
                <?php
                submit_button(__('Einstellungen speichern', 'excel-calculator-pro'));
                ?>
            </form>
        </div>
    <?php
    }


    /**
     * Kalkulatoren-Liste anzeigen
     */
    private function display_calculators_list()
    {
        $calculators = $this->database->get_calculators(array('orderby' => 'name', 'order' => 'ASC'));

        if (empty($calculators)) {
            echo '<div class="ecp-empty-state">';
            echo '<span class="dashicons dashicons-calculator ecp-empty-icon"></span>';
            echo '<h3>' . esc_html(ecp_admin_get_string('no_calculators_yet_title', __('Noch keine Kalkulatoren', 'excel-calculator-pro'))) . '</h3>';
            echo '<p>' . esc_html(ecp_admin_get_string('no_calculators_yet', __('Sie haben noch keine Kalkulatoren erstellt.', 'excel-calculator-pro'))) . '</p>';
            echo '<p>' . esc_html(ecp_admin_get_string('create_first_calculator', __('Klicken Sie auf "Neuer Kalkulator" oder verwenden Sie eine Vorlage, um zu beginnen.', 'excel-calculator-pro'))) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="ecp-calculators-grid">';
        foreach ($calculators as $calc) {
            $status_class = 'ecp-status-' . sanitize_html_class($calc->status);
            $status_title = ucfirst($calc->status); // TODO: Übersetzen
            // Fallback für Beschreibung
            $description = !empty($calc->description) ? esc_html($calc->description) : '<em>' . __('Keine Beschreibung vorhanden.', 'excel-calculator-pro') . '</em>';


            echo '<div class="ecp-calculator-card ' . $status_class . '">';
            echo '<div class="ecp-card-header">';
            echo '<h3><span class="ecp-status-indicator" title="' . esc_attr($status_title) . '"></span> ' . esc_html($calc->name) . '</h3>';
            echo '<div class="ecp-card-actions">';
            echo '<button class="button-link ecp-edit-calc" data-id="' . esc_attr($calc->id) . '" title="' . esc_attr__('Bearbeiten', 'excel-calculator-pro') . '"><span class="dashicons dashicons-edit"></span></button>';
            echo '<button class="button-link ecp-duplicate-calc" data-id="' . esc_attr($calc->id) . '" title="' . esc_attr__('Duplizieren', 'excel-calculator-pro') . '"><span class="dashicons dashicons-admin-page"></span></button>';
            echo '<button class="button-link ecp-delete-calc" data-id="' . esc_attr($calc->id) . '" title="' . esc_attr__('Löschen', 'excel-calculator-pro') . '"><span class="dashicons dashicons-trash"></span></button>';
            echo '</div>';
            echo '</div>';

            echo '<p class="ecp-card-description">' . $description . '</p>'; // wp_kses_post für erlaubtes HTML, falls benötigt

            echo '<div class="ecp-shortcode-display">';
            echo '<code>[excel_calculator id="' . esc_attr($calc->id) . '"]</code>';
            echo '<button class="ecp-copy-shortcode" data-shortcode="[excel_calculator id=&quot;' . esc_attr($calc->id) . '&quot;]" title="' . esc_attr__('Shortcode kopieren', 'excel-calculator-pro') . '"><span class="dashicons dashicons-admin-page"></span></button>';
            echo '</div>';

            echo '<div class="ecp-card-meta">';
            echo '<span>ID: ' . esc_html($calc->id) . '</span> | ';
            echo '<span>' . sprintf(__('Erstellt: %s', 'excel-calculator-pro'), date_i18n(get_option('date_format'), strtotime($calc->created_at))) . '</span>';
            if ($calc->updated_at !== $calc->created_at) {
                echo ' | <span>' . sprintf(__('Aktualisiert: %s', 'excel-calculator-pro'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($calc->updated_at))) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }


    /**
     * AJAX: Kalkulator speichern
     */
    public function ajax_save_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen', 'excel-calculator-pro')), 403);
        }

        $calculator_id = isset($_POST['calculator_id']) ? intval($_POST['calculator_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $fields_json = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : '[]'; // Erwarte JSON String oder Array
        $formulas_json = isset($_POST['formulas']) ? wp_unslash($_POST['formulas']) : '[]';
        $settings_json = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '{}';

        if (empty($name)) {
            wp_send_json_error(array('message' => __('Name ist erforderlich.', 'excel-calculator-pro')), 400);
        }

        // JSON-Daten dekodieren, wenn sie als Strings kommen
        $fields = is_string($fields_json) ? json_decode($fields_json, true) : $fields_json;
        $formulas = is_string($formulas_json) ? json_decode($formulas_json, true) : $formulas_json;
        $settings = is_string($settings_json) ? json_decode($settings_json, true) : $settings_json;


        if (json_last_error() !== JSON_ERROR_NONE && (is_string($fields_json) || is_string($formulas_json) || is_string($settings_json))) {
            wp_send_json_error(array('message' => __('Fehler beim Verarbeiten der Kalkulator-Daten (JSON).', 'excel-calculator-pro') . ' ' . json_last_error_msg()), 400);
        }


        $calculator_data = array(
            'id' => $calculator_id,
            'name' => $name,
            'description' => $description,
            'fields' => $fields, // Jetzt als Array
            'formulas' => $formulas, // Jetzt als Array
            'settings' => $settings   // Jetzt als Array
        );

        try {
            // Hier könnte eine tiefere Sanitization/Validation der $fields und $formulas Arrays stattfinden
            // z.B. mit ECP_Security_Manager::sanitize_calculator_data($calculator_data);
            $result_id = $this->database->save_calculator($calculator_data);

            if ($result_id) {
                do_action('ecp_calculator_saved', $result_id, $calculator_id === 0 ? 'created' : 'updated');
                wp_send_json_success(array('id' => $result_id, 'message' => __('Kalkulator erfolgreich gespeichert.', 'excel-calculator-pro')));
            } else {
                wp_send_json_error(array('message' => __('Fehler beim Speichern des Kalkulators in der Datenbank.', 'excel-calculator-pro')), 500);
            }
        } catch (Exception $e) {
            error_log("ECP Save Calculator Error: " . $e->getMessage());
            wp_send_json_error(array('message' => __('Fehler beim Speichern: ', 'excel-calculator-pro') . $e->getMessage()), 500);
        }
    }


    /**
     * AJAX: Kalkulator löschen
     */
    public function ajax_delete_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen', 'excel-calculator-pro')), 403);
        }

        $calculator_id = isset($_POST['calculator_id']) ? intval($_POST['calculator_id']) : 0;

        if ($calculator_id <= 0) {
            wp_send_json_error(array('message' => __('Ungültige Kalkulator-ID.', 'excel-calculator-pro')), 400);
        }

        $result = $this->database->delete_calculator($calculator_id);

        if ($result) {
            do_action('ecp_calculator_deleted', $calculator_id, 'deleted');
            wp_send_json_success(array('message' => __('Kalkulator erfolgreich gelöscht.', 'excel-calculator-pro')));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Löschen des Kalkulators.', 'excel-calculator-pro')), 500);
        }
    }

    /**
     * AJAX: Kalkulator abrufen
     */
    public function ajax_get_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen', 'excel-calculator-pro')), 403);
        }

        $calculator_id = isset($_POST['calculator_id']) ? intval($_POST['calculator_id']) : 0;
        if ($calculator_id <= 0) {
            wp_send_json_error(array('message' => __('Ungültige Kalkulator-ID.', 'excel-calculator-pro')), 400);
        }

        $calculator = $this->database->get_calculator($calculator_id);

        if ($calculator) {
            wp_send_json_success($calculator); // $calculator enthält bereits dekodierte JSON-Felder
        } else {
            wp_send_json_error(array('message' => __('Kalkulator nicht gefunden.', 'excel-calculator-pro')), 404);
        }
    }

    /**
     * AJAX: Aus Vorlage erstellen
     */
    public function ajax_create_from_template()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen', 'excel-calculator-pro')), 403);
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Ungültige Vorlagen-ID.', 'excel-calculator-pro')), 400);
        }
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Name für neuen Kalkulator ist erforderlich.', 'excel-calculator-pro')), 400);
        }

        $result_id = $this->database->create_from_template($template_id, $name);

        if ($result_id) {
            do_action('ecp_calculator_saved', $result_id, 'created_from_template');
            wp_send_json_success(array('id' => $result_id, 'message' => __('Kalkulator erfolgreich aus Vorlage erstellt.', 'excel-calculator-pro')));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Erstellen des Kalkulators aus der Vorlage.', 'excel-calculator-pro')), 500);
        }
    }

    /**
     * AJAX: Kalkulator exportieren
     */
    public function ajax_export_calculator()
    {
        // Nonce-Prüfung direkt aus dem POST-Parameter, da check_ajax_referer hier nicht passt für Dateidownload
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ecp_admin_nonce')) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen.', 'excel-calculator-pro'), __('Fehler', 'excel-calculator-pro'), array('response' => 403));
        }


        if (!current_user_can('manage_options')) {
            wp_die(__('Unzureichende Berechtigungen', 'excel-calculator-pro'), __('Fehler', 'excel-calculator-pro'), array('response' => 403));
        }

        $calculator_id = isset($_POST['calculator_id']) ? intval($_POST['calculator_id']) : 0;
        if ($calculator_id <= 0) {
            wp_die(__('Ungültige Kalkulator-ID.', 'excel-calculator-pro'), __('Fehler', 'excel-calculator-pro'), array('response' => 400));
        }

        $calculator_data = $this->database->export_calculator($calculator_id);

        if ($calculator_data && is_array($calculator_data)) {
            $filename_name_part = isset($calculator_data['name']) ? $calculator_data['name'] : 'calculator';
            $filename = sanitize_file_name($filename_name_part) . '_export_' . date('Y-m-d') . '.json';


            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache'); // HTTP 1.0.
            header('Expires: 0'); // Proxies.

            echo wp_json_encode($calculator_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            wp_die(__('Kalkulator nicht gefunden oder Exportfehler.', 'excel-calculator-pro'), __('Fehler', 'excel-calculator-pro'), array('response' => 404));
        }
    }

    /**
     * AJAX: Kalkulator importieren
     */
    public function ajax_import_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen', 'excel-calculator-pro')), 403);
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Keine Datei hochgeladen oder Fehler beim Upload.', 'excel-calculator-pro')), 400);
        }

        $file = $_FILES['import_file'];

        if ($file['type'] !== 'application/json') {
            wp_send_json_error(array('message' => __('Ungültiger Dateityp. Bitte laden Sie eine JSON-Datei hoch.', 'excel-calculator-pro')), 400);
        }

        // Dateigrößenlimit (z.B. 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Datei ist zu groß (max. 2MB).', 'excel-calculator-pro')), 400);
        }

        $content = file_get_contents($file['tmp_name']);
        // Entferne BOM, falls vorhanden (kann json_decode stören)
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Ungültige JSON-Datei: ', 'excel-calculator-pro') . json_last_error_msg()), 400);
        }

        if (!isset($data['name']) || !isset($data['fields']) || !isset($data['formulas'])) {
            wp_send_json_error(array('message' => __('Die JSON-Datei hat nicht die erwartete Struktur.', 'excel-calculator-pro')), 400);
        }

        // Optional: Überschreibe den Namen, wenn er schon existiert, oder füge "(Importiert)" hinzu
        // $data['name'] = $data['name'] . ' (' . __('Importiert', 'excel-calculator-pro') . ' ' . date('Y-m-d') . ')';

        $result_id = $this->database->import_calculator($data);

        if ($result_id) {
            do_action('ecp_calculator_saved', $result_id, 'imported');
            wp_send_json_success(array('id' => $result_id, 'message' => __('Kalkulator erfolgreich importiert.', 'excel-calculator-pro')));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Importieren des Kalkulators in die Datenbank.', 'excel-calculator-pro')), 500);
        }
    }

    /**
     * AJAX: Kalkulatoren für TinyMCE abrufen
     */
    public function ajax_get_calculators_for_tinymce()
    {
        check_ajax_referer('ecp_tinymce_nonce', 'nonce'); // Verwende die Nonce aus tinymce_plugin.js

        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculators = $this->database->get_calculators(array('limit' => 200, 'orderby' => 'name', 'order' => 'ASC'));
        $options = array();
        if (!empty($calculators)) {
            foreach ($calculators as $calc) {
                $options[] = array('id' => $calc->id, 'name' => $calc->name);
            }
        }

        wp_send_json_success($options);
    }


    /**
     * Einstellungen-Callbacks
     */
    public function general_section_callback()
    {
        echo '<p>' . __('Konfigurieren Sie die allgemeinen Einstellungen für Excel Calculator Pro.', 'excel-calculator-pro') . '</p>';
    }

    public function currency_field_callback()
    {
        $options = get_option('ecp_general_settings', array());
        $currency = isset($options['default_currency']) ? $options['default_currency'] : 'CHF';

        echo '<select id="ecp_default_currency" name="ecp_general_settings[default_currency]">';
        $currencies = array('CHF' => 'CHF (Schweizer Franken)', 'EUR' => 'EUR (Euro)', 'USD' => 'USD (US-Dollar)');
        $custom_symbols = apply_filters('ecp_currency_symbols', array());
        foreach ($custom_symbols as $code => $symbol_unused) {
            if (!isset($currencies[$code])) {
                $currencies[$code] = $code;
            }
        }

        foreach ($currencies as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($currency, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Wählen Sie die Standardwährung für neue Kalkulatoren.', 'excel-calculator-pro') . '</p>';
    }

    public function number_format_field_callback()
    {
        $options = get_option('ecp_general_settings', array());
        $format = isset($options['number_format']) ? $options['number_format'] : 'de_CH';

        echo '<select id="ecp_number_format" name="ecp_general_settings[number_format]">';
        $formats = array(
            'de_CH' => __('Schweiz (1\'234.56)', 'excel-calculator-pro'),
            'de_DE' => __('Deutschland (1.234,56)', 'excel-calculator-pro'),
            'en_US' => __('USA (1,234.56)', 'excel-calculator-pro'),
            'fr_CH' => __('Schweiz Französisch (1 234.56)', 'excel-calculator-pro'),
            // Weitere Formate können hier hinzugefügt werden
        );
        foreach ($formats as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($format, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Wählen Sie das Standard-Zahlenformat für die Anzeige.', 'excel-calculator-pro') . '</p>';
    }

    public function color_main_section_callback()
    {
        echo '<p>' . __('Passen Sie hier das Aussehen Ihrer Kalkulatoren an. Die Primär- und Sekundärfarben werden für Akzente und Verläufe verwendet. Definieren Sie anschliessend spezifische Farben für den Light und Dark Mode.', 'excel-calculator-pro') . '</p>';
    }

    public function color_light_section_callback()
    {
        echo '<p>' . __('Diese Farben werden verwendet, wenn der Nutzer ein helles Betriebssystem-Theme hat oder wenn "System-Dark-Mode folgen" deaktiviert ist.', 'excel-calculator-pro') . '</p>';
    }

    public function color_dark_section_callback()
    {
        echo '<p>' . __('Diese Farben werden verwendet, wenn "System-Dark-Mode folgen" aktiviert ist und der Nutzer ein dunkles Betriebssystem-Theme verwendet.', 'excel-calculator-pro') . '</p>';
    }


    /**
     * Generischer Callback für Farbfelder
     */
    public function color_field_callback($args)
    {
        $options = get_option('ecp_color_settings', array());
        $value = isset($options[$args['id']]) ? $options[$args['id']] : $args['default'];

        echo '<input type="text" name="ecp_color_settings[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="ecp-color-picker" data-default-color="' . esc_attr($args['default']) . '" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Callback für Checkbox-Felder
     */
    public function checkbox_field_callback($args)
    {
        $options = get_option('ecp_color_settings', array()); // Oder ecp_general_settings, je nach Feld
        $checked = isset($options[$args['id']]) ? checked($options[$args['id']], 1, false) : '';

        echo '<label for="ecp_' . esc_attr($args['id']) . '">';
        echo '<input type="checkbox" id="ecp_' . esc_attr($args['id']) . '" name="ecp_color_settings[' . esc_attr($args['id']) . ']" value="1" ' . $checked . ' />';
        if (isset($args['description'])) {
            echo ' ' . esc_html($args['description']);
        }
        echo '</label>';
        if (isset($args['detailed_description'])) {
            echo '<p class="description">' . esc_html($args['detailed_description']) . '</p>';
        }
    }


    public function calculator_width_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $width = isset($options['calculator_width']) ? $options['calculator_width'] : 'full';

        echo '<select name="ecp_color_settings[calculator_width]">';
        $widths = array(
            'full' => __('Volle Breite des Containers (100%)', 'excel-calculator-pro'),
            'large' => __('Gross (max. 900px, zentriert)', 'excel-calculator-pro'),
            'contained' => __('Standard (max. 700px, zentriert)', 'excel-calculator-pro'),
            'medium' => __('Mittel (max. 600px, zentriert)', 'excel-calculator-pro'),
            'small' => __('Klein (max. 500px, zentriert)', 'excel-calculator-pro')
        );

        foreach ($widths as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($width, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Definiert die Standardbreite für Kalkulatoren. Kann pro Shortcode überschrieben werden.', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Sanitize Callbacks für Einstellungen
     */
    public function sanitize_general_settings($input)
    {
        $sanitized_input = array();
        if (isset($input['default_currency'])) {
            $sanitized_input['default_currency'] = sanitize_text_field($input['default_currency']);
        }
        if (isset($input['number_format'])) {
            $sanitized_input['number_format'] = sanitize_text_field($input['number_format']);
        }
        // Fügen Sie hier weitere allgemeine Einstellungen hinzu
        return $sanitized_input;
    }

    public function sanitize_color_settings($input)
    {
        $sanitized_input = array();
        $color_keys = array_merge(
            array('primary_color', 'secondary_color', 'calculator_width'),
            array_keys($this->get_color_definitions('light')),
            array_keys($this->get_color_definitions('dark'))
        );
        // Suffixe für Light/Dark Mode hinzufügen
        $full_color_keys = array('primary_color', 'secondary_color', 'calculator_width');
        foreach (array_keys($this->get_color_definitions('light')) as $key) {
            $full_color_keys[] = $key . '_light';
        }
        foreach (array_keys($this->get_color_definitions('dark')) as $key) {
            $full_color_keys[] = $key . '_dark';
        }


        foreach ($full_color_keys as $key) {
            if (isset($input[$key])) {
                if (strpos($key, 'color') !== false || strpos($key, 'bg') !== false) { // Prüft ob es ein Farbfeld ist
                    $sanitized_input[$key] = sanitize_hex_color($input[$key]);
                } elseif ($key === 'calculator_width') {
                    $allowed_widths = array('full', 'large', 'contained', 'medium', 'small');
                    $sanitized_input[$key] = in_array($input[$key], $allowed_widths) ? $input[$key] : 'full';
                } else {
                    $sanitized_input[$key] = sanitize_text_field($input[$key]);
                }
            }
        }
        if (isset($input['enable_system_dark_mode'])) {
            $sanitized_input['enable_system_dark_mode'] = ($input['enable_system_dark_mode'] == 1 ? 1 : 0);
        }

        return $sanitized_input;
    }
}

/**
 * Hilfsfunktion für einfachen Zugriff auf Admin-Strings im JS, falls ecp_admin nicht global genug ist.
 * Wird hier nicht direkt verwendet, aber könnte nützlich sein.
 */
function ecp_admin_get_string($key, $default = '')
{
    // Diese Funktion ist ein Platzhalter. Die Strings werden jetzt direkt in wp_localize_script definiert.
    // Wenn Sie eine zentrale String-Verwaltung in PHP benötigen, könnte diese ausgebaut werden.
    $strings = array(
        'no_calculators_yet_title' => __('Noch keine Kalkulatoren', 'excel-calculator-pro'),
        'no_calculators_yet' => __('Sie haben noch keine Kalkulatoren erstellt.', 'excel-calculator-pro'),
        'create_first_calculator' => __('Klicken Sie auf "Neuer Kalkulator" oder verwenden Sie eine Vorlage, um zu beginnen.', 'excel-calculator-pro'),
        // ... weitere Strings
    );
    return isset($strings[$key]) ? $strings[$key] : $default;
}
