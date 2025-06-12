<?php

/**
 * Verbesserte Admin Handler für Excel Calculator Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECP_Admin
{
    private $database;
    private $settings_manager;
    private $page_hook;

    public function __construct($db_instance)
    {
        $this->database = $db_instance;
        $this->settings_manager = new ECP_Settings_Manager();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX-Handlers
        $ajax_actions = array(
            'save_calculator',
            'delete_calculator',
            'get_calculator',
            'export_calculator',
            'import_calculator',
            'get_calculators_for_tinymce'
        );

        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_ecp_{$action}", array($this, "ajax_{$action}"));
        }
    }

    public function admin_menu()
    {
        $this->page_hook = add_options_page(
            __('Excel Calculator Pro', 'excel-calculator-pro'),
            __('Calculator Pro', 'excel-calculator-pro'),
            'manage_options',
            'excel-calculator-pro',
            array($this, 'admin_page')
        );
    }

    public function admin_enqueue_scripts($hook)
    {
        if ($hook !== $this->page_hook) {
            return;
        }

        // Modern admin assets
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');

        wp_enqueue_script(
            'ecp-admin-js',
            ECP_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'jquery-ui-sortable', 'wp-color-picker'),
            ECP_VERSION,
            true
        );

        wp_enqueue_style(
            'ecp-admin-css',
            ECP_PLUGIN_URL . 'assets/admin.css',
            array('wp-color-picker'),
            ECP_VERSION
        );

        // Localization
        wp_localize_script('ecp-admin-js', 'ecp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_admin_nonce'),
            'strings' => $this->get_admin_strings()
        ));
    }

    private function get_admin_strings()
    {
        return array(
            // General
            'loading' => __('Lädt...', 'excel-calculator-pro'),
            'success_saved' => __('Erfolgreich gespeichert!', 'excel-calculator-pro'),
            'error_occurred' => __('Ein Fehler ist aufgetreten.', 'excel-calculator-pro'),

            // Confirmations
            'confirm_delete' => __('Sind Sie sicher, dass Sie diesen Kalkulator löschen möchten?', 'excel-calculator-pro'),
            'unsaved_changes' => __('Sie haben ungespeicherte Änderungen.', 'excel-calculator-pro'),

            // Form validation
            'error_name_required' => __('Name ist erforderlich.', 'excel-calculator-pro'),
            'error_invalid_formula' => __('Ungültige Formel.', 'excel-calculator-pro'),

            // Actions
            'new_calculator' => __('Neuer Kalkulator', 'excel-calculator-pro'),
            'edit_calculator' => __('Kalkulator bearbeiten', 'excel-calculator-pro'),
            'duplicate_calculator' => __('Kalkulator duplizieren', 'excel-calculator-pro'),

            // Import/Export
            'export_success' => __('Kalkulator erfolgreich exportiert.', 'excel-calculator-pro'),
            'import_success' => __('Kalkulator erfolgreich importiert.', 'excel-calculator-pro'),
            'import_error' => __('Fehler beim Importieren der Datei.', 'excel-calculator-pro'),
        );
    }

    public function admin_page()
    {
        $current_tab = sanitize_text_field($_GET['tab'] ?? 'calculators');
?>
        <div class="wrap ecp-admin-wrap">
            <?php $this->render_header($current_tab); ?>
            <?php $this->render_navigation($current_tab); ?>
            <?php $this->render_tab_content($current_tab); ?>
        </div>
    <?php
    }

    private function render_header($current_tab)
    {
    ?>
        <div class="ecp-admin-header">
            <div class="ecp-header-content">
                <h1>
                    <span class="dashicons dashicons-calculator"></span>
                    Excel Calculator Pro
                    <span class="ecp-version">v<?php echo ECP_VERSION; ?></span>
                </h1>
                <?php if ($current_tab === 'calculators'): ?>
                    <button id="ecp-new-calculator" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Neuer Kalkulator', 'excel-calculator-pro'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    private function render_navigation($current_tab)
    {
        $tabs = array(
            'calculators' => array(
                'title' => __('Kalkulatoren', 'excel-calculator-pro'),
                'icon' => 'dashicons-list-view'
            ),
            'formulas' => array(
                'title' => __('Formel-Referenz', 'excel-calculator-pro'),
                'icon' => 'dashicons-editor-code'
            ),
            'import-export' => array(
                'title' => __('Import/Export', 'excel-calculator-pro'),
                'icon' => 'dashicons-upload'
            ),
            'settings' => array(
                'title' => __('Einstellungen', 'excel-calculator-pro'),
                'icon' => 'dashicons-admin-settings'
            )
        );

    ?>
        <nav class="nav-tab-wrapper ecp-nav-wrapper">
            <?php foreach ($tabs as $tab_key => $tab_data): ?>
                <a href="?page=excel-calculator-pro&tab=<?php echo $tab_key; ?>"
                    class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo $tab_data['icon']; ?>"></span>
                    <?php echo $tab_data['title']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php
    }

    private function render_tab_content($current_tab)
    {
        echo '<div class="ecp-tab-content">';

        switch ($current_tab) {
            case 'formulas':
                $this->formulas_reference_tab();
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

        echo '</div>';
    }

    private function calculators_tab()
    {
    ?>
        <div id="ecp-calculators-tab">
            <div id="ecp-calculators-list">
                <?php $this->render_calculators_grid(); ?>
            </div>

            <div id="ecp-calculator-editor" style="display: none;">
                <?php $this->render_calculator_editor(); ?>
            </div>
        </div>
    <?php
    }

    private function formulas_reference_tab()
    {
    ?>
        <div class="ecp-settings-container">
            <h2><?php _e('Verfügbare Formeln und Funktionen', 'excel-calculator-pro'); ?></h2>
            <p><?php _e('Verwenden Sie ein Semikolon (;) um Argumente in Funktionen zu trennen.', 'excel-calculator-pro'); ?></p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; margin-top: 20px;">

                <!-- Grundrechenarten -->
                <div class="ecp-formula-category">
                    <h3><span class="dashicons dashicons-plus-alt"></span> <?php _e('Grundrechenarten', 'excel-calculator-pro'); ?></h3>
                    <table class="ecp-formula-table">
                        <tr>
                            <td><code>field_1 + field_2</code></td>
                            <td><?php _e('Addition', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>field_1 - field_2</code></td>
                            <td><?php _e('Subtraktion', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>field_1 * field_2</code></td>
                            <td><?php _e('Multiplikation', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>field_1 / field_2</code></td>
                            <td><?php _e('Division', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>field_1 ^ field_2</code></td>
                            <td><?php _e('Potenz', 'excel-calculator-pro'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Mathematische Funktionen -->
                <div class="ecp-formula-category">
                    <h3><span class="dashicons dashicons-calculator"></span> <?php _e('Mathematische Funktionen', 'excel-calculator-pro'); ?></h3>
                    <table class="ecp-formula-table">
                        <tr>
                            <td><code>ABS(field_1)</code></td>
                            <td><?php _e('Absolutwert', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>SQRT(field_1)</code></td>
                            <td><?php _e('Quadratwurzel', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>POW(field_1; 2)</code></td>
                            <td><?php _e('Potenz', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>LOG(field_1)</code></td>
                            <td><?php _e('Logarithmus', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>PI</code></td>
                            <td><?php _e('Pi (3.14159...)', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>E</code></td>
                            <td><?php _e('Eulersche Zahl (2.71828...)', 'excel-calculator-pro'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Rundungsfunktionen -->
                <div class="ecp-formula-category">
                    <h3><span class="dashicons dashicons-format-aside"></span> <?php _e('Rundungsfunktionen', 'excel-calculator-pro'); ?></h3>
                    <table class="ecp-formula-table">
                        <tr>
                            <td><code>RUNDEN(field_1; 2)</code></td>
                            <td><?php _e('Auf 2 Dezimalstellen runden', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>OBERGRENZE(field_1)</code></td>
                            <td><?php _e('Aufrunden zur nächsten Ganzzahl', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>UNTERGRENZE(field_1)</code></td>
                            <td><?php _e('Abrunden zur nächsten Ganzzahl', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>OBERGRENZE(field_1; 0.5)</code></td>
                            <td><?php _e('Aufrunden auf 0.5er-Schritte', 'excel-calculator-pro'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Aggregationsfunktionen -->
                <div class="ecp-formula-category">
                    <h3><span class="dashicons dashicons-chart-bar"></span> <?php _e('Aggregationsfunktionen', 'excel-calculator-pro'); ?></h3>
                    <table class="ecp-formula-table">
                        <tr>
                            <td><code>SUMME(field_1; field_2; field_3)</code></td>
                            <td><?php _e('Summe aller Werte', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>MITTELWERT(field_1; field_2)</code></td>
                            <td><?php _e('Durchschnitt der Werte', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>MIN(field_1; field_2)</code></td>
                            <td><?php _e('Kleinster Wert', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>MAX(field_1; field_2)</code></td>
                            <td><?php _e('Größter Wert', 'excel-calculator-pro'); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Bedingte Logik -->
                <div class="ecp-formula-category">
                    <h3><span class="dashicons dashicons-randomize"></span> <?php _e('Bedingte Logik', 'excel-calculator-pro'); ?></h3>
                    <table class="ecp-formula-table">
                        <tr>
                            <td><code>WENN(field_1 > 100; field_1 * 0.1; 0)</code></td>
                            <td><?php _e('Einfache Bedingung', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>WENN(field_1 > 1000; 100; WENN(field_1 > 500; 50; 0))</code></td>
                            <td><?php _e('Verschachtelte Bedingung', 'excel-calculator-pro'); ?></td>
                        </tr>
                    </table>
                    <p><strong><?php _e('Vergleichsoperatoren:', 'excel-calculator-pro'); ?></strong> <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code>, <code>=</code>, <code>!=</code></p>
                </div>

                <!-- Datumsfunktionen -->
                <div class="ecp-formula-category">
                    <h3><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Datumsfunktionen', 'excel-calculator-pro'); ?></h3>
                    <table class="ecp-formula-table">
                        <tr>
                            <td><code>HEUTE()</code></td>
                            <td><?php _e('Heutiges Datum', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>JAHR(HEUTE())</code></td>
                            <td><?php _e('Aktuelles Jahr', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>MONAT(HEUTE())</code></td>
                            <td><?php _e('Aktueller Monat', 'excel-calculator-pro'); ?></td>
                        </tr>
                        <tr>
                            <td><code>TAG(HEUTE())</code></td>
                            <td><?php _e('Aktueller Tag', 'excel-calculator-pro'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="ecp-formula-examples" style="margin-top: 40px;">
                <h3><?php _e('Praktische Beispiele', 'excel-calculator-pro'); ?></h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                    <div class="ecp-example-box">
                        <h4><?php _e('Kreditrechner', 'excel-calculator-pro'); ?></h4>
                        <p><strong><?php _e('Monatliche Rate:', 'excel-calculator-pro'); ?></strong></p>
                        <code>RUNDEN((kreditsumme * (zinssatz/100/12) * POW(1 + zinssatz/100/12; laufzeit*12)) / (POW(1 + zinssatz/100/12; laufzeit*12) - 1); 2)</code>
                    </div>

                    <div class="ecp-example-box">
                        <h4><?php _e('Rabattrechnung', 'excel-calculator-pro'); ?></h4>
                        <p><strong><?php _e('Endpreis mit Staffelrabatt:', 'excel-calculator-pro'); ?></strong></p>
                        <code>WENN(menge > 100; preis * 0.9; WENN(menge > 50; preis * 0.95; preis))</code>
                    </div>

                    <div class="ecp-example-box">
                        <h4><?php _e('BMI-Rechner', 'excel-calculator-pro'); ?></h4>
                        <p><strong><?php _e('Body Mass Index:', 'excel-calculator-pro'); ?></strong></p>
                        <code>RUNDEN(gewicht / POW(groesse/100; 2); 1)</code>
                    </div>

                    <div class="ecp-example-box">
                        <h4><?php _e('Zinseszinsrechnung', 'excel-calculator-pro'); ?></h4>
                        <p><strong><?php _e('Endkapital:', 'excel-calculator-pro'); ?></strong></p>
                        <code>startkapital * POW(1 + zinssatz/100; jahre)</code>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .ecp-formula-category {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #ddd;
            }

            .ecp-formula-category h3 {
                margin-top: 0;
                color: #007cba;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .ecp-formula-table {
                width: 100%;
                border-collapse: collapse;
            }

            .ecp-formula-table td {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .ecp-formula-table td:first-child {
                font-family: 'Courier New', monospace;
                font-weight: bold;
                color: #d63384;
                padding-right: 15px;
                vertical-align: top;
            }

            .ecp-example-box {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #ddd;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .ecp-example-box h4 {
                margin-top: 0;
                color: #007cba;
            }

            .ecp-example-box code {
                display: block;
                background: #f1f1f1;
                padding: 10px;
                border-radius: 4px;
                font-size: 12px;
                word-break: break-all;
            }
        </style>
    <?php
    }

    // ... (Rest der Methoden bleibt unverändert bis auf die sanitize_formulas Methode)

    private function sanitize_formulas($formulas)
    {
        if (!is_array($formulas)) {
            return array();
        }

        $sanitized = array();

        foreach ($formulas as $formula) {
            if (!is_array($formula)) {
                continue;
            }

            $sanitized_formula = $this->sanitize_single_formula($formula);

            if ($sanitized_formula) {
                $sanitized[] = $sanitized_formula;
            }
        }

        return $sanitized;
    }

    private function sanitize_single_formula($formula)
    {
        if (empty($formula['label'])) {
            return null;
        }

        $sanitized = array();

        // Label validation
        $sanitized['label'] = sanitize_text_field($formula['label']);
        if (strlen($sanitized['label']) > 255) {
            throw new InvalidArgumentException(__('Formel-Label ist zu lang.', 'excel-calculator-pro'));
        }

        // Formula validation - vereinfacht, um den Speicher-Bug zu beheben
        $sanitized['formula'] = $this->simple_formula_validation($formula['formula'] ?? '');

        // Format validation
        $allowed_formats = array('', 'currency', 'percentage', 'integer', 'text');
        $sanitized['format'] = in_array($formula['format'] ?? '', $allowed_formats)
            ? $formula['format'] : '';

        // Other fields
        foreach (array('unit', 'help') as $field) {
            if (isset($formula[$field])) {
                $sanitized[$field] = sanitize_text_field($formula[$field]);
            }
        }

        return $sanitized;
    }

    /**
     * Vereinfachte Formel-Validierung um Speicher-Bug zu beheben
     */
    private function simple_formula_validation($formula)
    {
        if (!is_string($formula)) {
            throw new InvalidArgumentException(__('Formel muss ein String sein.', 'excel-calculator-pro'));
        }

        $formula = trim($formula);

        // Check formula length
        if (strlen($formula) > 5000) {
            throw new InvalidArgumentException(__('Formel ist zu lang (max. 5000 Zeichen).', 'excel-calculator-pro'));
        }

        // Basic security checks - gefährliche Muster
        $dangerous_patterns = array(
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            'file_get_contents',
            '<script',
            'javascript:',
            'document.',
            'window.'
        );

        $formula_lower = strtolower($formula);
        foreach ($dangerous_patterns as $pattern) {
            if (strpos($formula_lower, $pattern) !== false) {
                throw new InvalidArgumentException(__('Formel enthält nicht erlaubte Funktionen.', 'excel-calculator-pro'));
            }
        }

        return $formula;
    }

    // ... (Rest der Klasse bleibt unverändert)

    private function import_export_tab()
    {
    ?>
        <div class="ecp-settings-container">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Export Bereich -->
                <div>
                    <h3><?php _e('Kalkulator exportieren', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Exportieren Sie einen Kalkulator als JSON-Datei für Backup oder Transfer.', 'excel-calculator-pro'); ?></p>

                    <div class="ecp-form-group">
                        <label for="export-calculator-select"><?php _e('Kalkulator auswählen:', 'excel-calculator-pro'); ?></label>
                        <select id="export-calculator-select" class="regular-text">
                            <option value=""><?php _e('-- Bitte wählen --', 'excel-calculator-pro'); ?></option>
                            <?php
                            $calculators = $this->database->get_calculators();
                            foreach ($calculators as $calc) {
                                echo '<option value="' . esc_attr($calc->id) . '">' . esc_html($calc->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <button id="ecp-export-calculator" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Exportieren', 'excel-calculator-pro'); ?>
                    </button>
                </div>

                <!-- Import Bereich -->
                <div>
                    <h3><?php _e('Kalkulator importieren', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Importieren Sie einen Kalkulator aus einer JSON-Datei.', 'excel-calculator-pro'); ?></p>

                    <div class="ecp-form-group">
                        <label for="import-file"><?php _e('JSON-Datei auswählen:', 'excel-calculator-pro'); ?></label>
                        <input type="file" id="import-file" accept=".json" />
                        <p class="description"><?php _e('Nur JSON-Dateien sind erlaubt (max. 1MB)', 'excel-calculator-pro'); ?></p>
                    </div>

                    <button id="ecp-import-calculator" class="button button-primary" disabled>
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Importieren', 'excel-calculator-pro'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php
    }

    private function render_calculators_grid()
    {
        $calculators = $this->database->get_calculators(array('orderby' => 'name', 'order' => 'ASC'));

        if (empty($calculators)) {
            $this->render_empty_state();
            return;
        }

        echo '<div class="ecp-calculators-grid">';
        foreach ($calculators as $calc) {
            $this->render_calculator_card($calc);
        }
        echo '</div>';
    }

    private function render_calculator_card($calc)
    {
        $field_count = count(json_decode($calc->fields, true) ?: array());
        $formula_count = count(json_decode($calc->formulas, true) ?: array());

    ?>
        <div class="ecp-calculator-card" data-id="<?php echo esc_attr($calc->id); ?>">
            <div class="ecp-card-header">
                <h3 class="ecp-card-title"><?php echo esc_html($calc->name); ?></h3>
                <div class="ecp-card-actions">
                    <button class="ecp-action-btn ecp-edit-calc" data-id="<?php echo esc_attr($calc->id); ?>"
                        title="<?php esc_attr_e('Bearbeiten', 'excel-calculator-pro'); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button class="ecp-action-btn ecp-duplicate-calc" data-id="<?php echo esc_attr($calc->id); ?>"
                        title="<?php esc_attr_e('Duplizieren', 'excel-calculator-pro'); ?>">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                    <button class="ecp-action-btn ecp-delete-calc" data-id="<?php echo esc_attr($calc->id); ?>"
                        title="<?php esc_attr_e('Löschen', 'excel-calculator-pro'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>

            <?php if (!empty($calc->description)): ?>
                <p class="ecp-card-description"><?php echo esc_html($calc->description); ?></p>
            <?php endif; ?>

            <div class="ecp-card-stats">
                <span class="ecp-stat">
                    <span class="dashicons dashicons-edit-large"></span>
                    <?php printf(_n('%d Feld', '%d Felder', $field_count, 'excel-calculator-pro'), $field_count); ?>
                </span>
                <span class="ecp-stat">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php printf(_n('%d Formel', '%d Formeln', $formula_count, 'excel-calculator-pro'), $formula_count); ?>
                </span>
            </div>

            <div class="ecp-shortcode-section">
                <div class="ecp-shortcode-display">
                    <code>[excel_calculator id="<?php echo esc_attr($calc->id); ?>"]</code>
                    <button class="ecp-copy-shortcode" data-shortcode="[excel_calculator id=&quot;<?php echo esc_attr($calc->id); ?>&quot;]">
                        <span class="dashicons dashicons-admin-page"></span>
                    </button>
                </div>
            </div>

            <div class="ecp-card-footer">
                <small>
                    <?php printf(
                        __('Erstellt: %s', 'excel-calculator-pro'),
                        wp_date(get_option('date_format'), strtotime($calc->created_at))
                    ); ?>
                </small>
            </div>
        </div>
    <?php
    }

    private function render_empty_state()
    {
    ?>
        <div class="ecp-empty-state">
            <div class="ecp-empty-icon">
                <span class="dashicons dashicons-calculator"></span>
            </div>
            <h3><?php _e('Noch keine Kalkulatoren', 'excel-calculator-pro'); ?></h3>
            <p><?php _e('Erstellen Sie Ihren ersten Kalkulator.', 'excel-calculator-pro'); ?></p>
            <button id="ecp-new-calculator-empty" class="button button-primary">
                <?php _e('Ersten Kalkulator erstellen', 'excel-calculator-pro'); ?>
            </button>
        </div>
    <?php
    }

    private function render_calculator_editor()
    {
    ?>
        <div class="ecp-editor-container">
            <div class="ecp-editor-header">
                <h2 id="ecp-editor-title"><?php _e('Kalkulator Editor', 'excel-calculator-pro'); ?></h2>
                <div class="ecp-editor-actions">
                    <button id="ecp-save-calculator" class="button button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Speichern', 'excel-calculator-pro'); ?>
                    </button>
                    <button id="ecp-cancel-edit" class="button">
                        <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                    </button>
                </div>
            </div>

            <div class="ecp-editor-content">
                <div class="ecp-editor-form">
                    <div class="ecp-form-group">
                        <label for="calculator-name"><?php _e('Name', 'excel-calculator-pro'); ?> *</label>
                        <input type="text" id="calculator-name" class="regular-text" required>
                    </div>

                    <div class="ecp-form-group">
                        <label for="calculator-description"><?php _e('Beschreibung', 'excel-calculator-pro'); ?></label>
                        <textarea id="calculator-description" class="large-text" rows="3"></textarea>
                    </div>
                </div>

                <div class="ecp-editor-sections">
                    <div class="ecp-section">
                        <h3>
                            <span class="dashicons dashicons-edit-large"></span>
                            <?php _e('Eingabefelder', 'excel-calculator-pro'); ?>
                        </h3>
                        <div id="ecp-fields-container" class="ecp-sortable"></div>
                        <button id="ecp-add-field" class="button ecp-add-btn">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Feld hinzufügen', 'excel-calculator-pro'); ?>
                        </button>
                    </div>

                    <div class="ecp-section">
                        <h3>
                            <span class="dashicons dashicons-chart-line"></span>
                            <?php _e('Ausgabefelder', 'excel-calculator-pro'); ?>
                        </h3>
                        <div id="ecp-outputs-container" class="ecp-sortable"></div>
                        <button id="ecp-add-output" class="button ecp-add-btn">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Ausgabe hinzufügen', 'excel-calculator-pro'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="ecp-editor-footer">
                <div class="ecp-footer-actions">
                    <button id="ecp-duplicate-calculator" class="button" style="display:none;">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php _e('Duplizieren', 'excel-calculator-pro'); ?>
                    </button>
                    <button id="ecp-delete-calculator" class="button button-link-delete" style="display:none;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Löschen', 'excel-calculator-pro'); ?>
                    </button>
                </div>
            </div>

            <input type="hidden" id="calculator-id" value="0">
        </div>
    <?php
    }

    private function settings_tab()
    {
    ?>
        <div class="ecp-settings-container">
            <form method="post" action="options.php">
                <?php
                settings_fields('ecp_settings_group');
                do_settings_sections('ecp_settings_page');
                submit_button(__('Einstellungen speichern', 'excel-calculator-pro'));
                ?>
            </form>
        </div>
<?php
    }

    public function register_settings()
    {
        $this->settings_manager->register_settings();
    }

    // AJAX Handlers (bleiben unverändert bis auf bessere Fehlerbehandlung)
    public function ajax_save_calculator()
    {
        $this->verify_admin_access();

        try {
            $data = $this->sanitize_calculator_data($_POST);
            $result_id = $this->database->save_calculator($data);

            if ($result_id) {
                wp_send_json_success(array(
                    'id' => $result_id,
                    'message' => __('Kalkulator erfolgreich gespeichert.', 'excel-calculator-pro')
                ));
            } else {
                throw new Exception(__('Fehler beim Speichern.', 'excel-calculator-pro'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function ajax_delete_calculator()
    {
        $this->verify_admin_access();

        $calculator_id = intval($_POST['calculator_id'] ?? 0);

        if ($calculator_id <= 0) {
            wp_send_json_error(array('message' => __('Ungültige ID.', 'excel-calculator-pro')));
        }

        if ($this->database->delete_calculator($calculator_id)) {
            wp_send_json_success(array('message' => __('Erfolgreich gelöscht.', 'excel-calculator-pro')));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Löschen.', 'excel-calculator-pro')));
        }
    }

    public function ajax_get_calculator()
    {
        $this->verify_admin_access();

        $calculator_id = intval($_POST['calculator_id'] ?? 0);
        $calculator = $this->database->get_calculator($calculator_id);

        if ($calculator) {
            wp_send_json_success($calculator);
        } else {
            wp_send_json_error(array('message' => __('Kalkulator nicht gefunden.', 'excel-calculator-pro')));
        }
    }

    public function ajax_export_calculator()
    {
        $this->verify_admin_access();

        $calculator_id = intval($_POST['calculator_id'] ?? 0);

        if ($calculator_id <= 0) {
            wp_send_json_error(array('message' => __('Ungültige Kalkulator-ID.', 'excel-calculator-pro')));
        }

        $export_data = $this->database->export_calculator($calculator_id);

        if ($export_data) {
            wp_send_json_success(array(
                'data' => $export_data,
                'filename' => sanitize_file_name($export_data['name']) . '_export.json',
                'message' => __('Export erfolgreich erstellt.', 'excel-calculator-pro')
            ));
        } else {
            wp_send_json_error(array('message' => __('Fehler beim Exportieren.', 'excel-calculator-pro')));
        }
    }

    public function ajax_import_calculator()
    {
        $this->verify_admin_access();

        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(array('message' => __('Keine Datei hochgeladen.', 'excel-calculator-pro')));
        }

        $file = $_FILES['import_file'];

        // Datei-Validierung
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Fehler beim Datei-Upload.', 'excel-calculator-pro')));
        }

        if ($file['size'] > 1048576) { // 1MB
            wp_send_json_error(array('message' => __('Datei ist zu groß (max. 1MB).', 'excel-calculator-pro')));
        }

        $allowed_types = array('application/json', 'text/plain');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => __('Nur JSON-Dateien sind erlaubt.', 'excel-calculator-pro')));
        }

        // Datei-Inhalt lesen und validieren
        $content = file_get_contents($file['tmp_name']);
        if (!$content) {
            wp_send_json_error(array('message' => __('Datei konnte nicht gelesen werden.', 'excel-calculator-pro')));
        }

        $import_data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Ungültiges JSON-Format.', 'excel-calculator-pro')));
        }

        // Struktur validieren
        $required_fields = array('name', 'fields', 'formulas');
        foreach ($required_fields as $field) {
            if (!isset($import_data[$field])) {
                wp_send_json_error(array('message' => sprintf(__('Erforderliches Feld "%s" fehlt.', 'excel-calculator-pro'), $field)));
            }
        }

        // Import durchführen
        try {
            $result_id = $this->database->import_calculator($import_data);

            if ($result_id) {
                wp_send_json_success(array(
                    'id' => $result_id,
                    'message' => __('Kalkulator erfolgreich importiert.', 'excel-calculator-pro')
                ));
            } else {
                wp_send_json_error(array('message' => __('Fehler beim Importieren.', 'excel-calculator-pro')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function ajax_get_calculators_for_tinymce()
    {
        if (!check_ajax_referer('ecp_tinymce_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen.', 'excel-calculator-pro')));
        }

        $calculators = $this->database->get_calculators();
        $result = array();

        foreach ($calculators as $calc) {
            $result[] = array(
                'id' => $calc->id,
                'name' => $calc->name
            );
        }

        wp_send_json_success($result);
    }

    // Utility methods
    private function verify_admin_access()
    {
        if (!check_ajax_referer('ecp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen.', 'excel-calculator-pro')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'excel-calculator-pro')));
        }
    }

    private function sanitize_calculator_data($data)
    {
        return array(
            'id' => intval($data['id'] ?? 0),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'fields' => $this->sanitize_fields($data['fields'] ?? array()),
            'formulas' => $this->sanitize_formulas($data['formulas'] ?? array()),
            'settings' => $this->sanitize_settings($data['settings'] ?? array())
        );
    }

    private function sanitize_fields($fields)
    {
        if (!is_array($fields)) {
            return array();
        }

        $sanitized = array();
        foreach ($fields as $field) {
            if (is_array($field) && !empty($field['id']) && !empty($field['label'])) {
                $sanitized[] = array(
                    'id' => sanitize_key($field['id']),
                    'label' => sanitize_text_field($field['label']),
                    'type' => sanitize_text_field($field['type'] ?? 'number'),
                    'default' => sanitize_text_field($field['default'] ?? ''),
                    'min' => is_numeric($field['min'] ?? '') ? floatval($field['min']) : '',
                    'max' => is_numeric($field['max'] ?? '') ? floatval($field['max']) : '',
                    'step' => sanitize_text_field($field['step'] ?? ''),
                    'unit' => sanitize_text_field($field['unit'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'help' => sanitize_text_field($field['help'] ?? '')
                );
            }
        }

        return $sanitized;
    }

    private function sanitize_settings($settings)
    {
        if (!is_array($settings)) {
            return array();
        }

        $sanitized = array();
        foreach ($settings as $key => $value) {
            $clean_key = sanitize_key($key);
            if (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = floatval($value);
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = (bool) $value;
            }
        }

        return $sanitized;
    }
}

/**
 * Settings Manager für bessere Organisation
 */
class ECP_Settings_Manager
{
    public function register_settings()
    {
        register_setting('ecp_settings_group', 'ecp_general_settings', array($this, 'sanitize_general'));
        register_setting('ecp_settings_group', 'ecp_color_settings', array($this, 'sanitize_colors'));

        // General settings section
        add_settings_section(
            'ecp_general_section',
            __('Allgemeine Einstellungen', 'excel-calculator-pro'),
            array($this, 'general_section_callback'),
            'ecp_settings_page'
        );

        // Color settings section
        add_settings_section(
            'ecp_color_section',
            __('Design-Einstellungen', 'excel-calculator-pro'),
            array($this, 'color_section_callback'),
            'ecp_settings_page'
        );

        $this->add_settings_fields();
    }

    private function add_settings_fields()
    {
        // Currency field
        add_settings_field(
            'default_currency',
            __('Standard-Währung', 'excel-calculator-pro'),
            array($this, 'currency_field'),
            'ecp_settings_page',
            'ecp_general_section'
        );

        // Number format field
        add_settings_field(
            'number_format',
            __('Zahlenformat', 'excel-calculator-pro'),
            array($this, 'number_format_field'),
            'ecp_settings_page',
            'ecp_general_section'
        );

        // Primary color field
        add_settings_field(
            'primary_color',
            __('Primärfarbe', 'excel-calculator-pro'),
            array($this, 'color_field'),
            'ecp_settings_page',
            'ecp_color_section',
            array('key' => 'primary_color', 'default' => '#007cba')
        );
    }

    public function general_section_callback()
    {
        echo '<p>' . __('Konfigurieren Sie die grundlegenden Einstellungen.', 'excel-calculator-pro') . '</p>';
    }

    public function color_section_callback()
    {
        echo '<p>' . __('Passen Sie das Aussehen Ihrer Kalkulatoren an.', 'excel-calculator-pro') . '</p>';
    }

    public function currency_field()
    {
        $options = get_option('ecp_general_settings', array());
        $currency = $options['default_currency'] ?? 'CHF';

        $currencies = array(
            'CHF' => 'CHF (Schweizer Franken)',
            'EUR' => 'EUR (Euro)',
            'USD' => 'USD (US-Dollar)'
        );

        echo '<select name="ecp_general_settings[default_currency]">';
        foreach ($currencies as $code => $name) {
            printf('<option value="%s" %s>%s</option>', $code, selected($currency, $code, false), $name);
        }
        echo '</select>';
    }

    public function number_format_field()
    {
        $options = get_option('ecp_general_settings', array());
        $format = $options['number_format'] ?? 'de_CH';

        $formats = array(
            'de_CH' => __('Schweiz (1\'234.56)', 'excel-calculator-pro'),
            'de_DE' => __('Deutschland (1.234,56)', 'excel-calculator-pro'),
            'en_US' => __('USA (1,234.56)', 'excel-calculator-pro')
        );

        echo '<select name="ecp_general_settings[number_format]">';
        foreach ($formats as $code => $name) {
            printf('<option value="%s" %s>%s</option>', $code, selected($format, $code, false), $name);
        }
        echo '</select>';
    }

    public function color_field($args)
    {
        $options = get_option('ecp_color_settings', array());
        $value = $options[$args['key']] ?? $args['default'];

        printf(
            '<input type="text" name="ecp_color_settings[%s]" value="%s" class="ecp-color-picker" data-default-color="%s">',
            esc_attr($args['key']),
            esc_attr($value),
            esc_attr($args['default'])
        );
    }

    public function sanitize_general($input)
    {
        $sanitized = array();

        if (isset($input['default_currency'])) {
            $sanitized['default_currency'] = sanitize_text_field($input['default_currency']);
        }

        if (isset($input['number_format'])) {
            $sanitized['number_format'] = sanitize_text_field($input['number_format']);
        }

        return $sanitized;
    }

    public function sanitize_colors($input)
    {
        $sanitized = array();

        foreach ($input as $key => $value) {
            if (strpos($key, 'color') !== false) {
                $sanitized[$key] = sanitize_hex_color($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}
