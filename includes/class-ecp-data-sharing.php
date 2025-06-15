<?php

/**
 * Data Sharing Handler für Excel Calculator Pro
 * Verwaltet Local Storage basierte Datenübertragung zwischen Kalkulatoren
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECP_Data_Sharing
{
    private $database;
    private $settings_key = 'ecp_data_sharing_settings';
    private $cache_time = 3600;

    public function __construct($database = null)
    {
        $this->database = $database;
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX handlers für Admin
        add_action('wp_ajax_ecp_save_data_sharing_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_ecp_get_data_sharing_settings', array($this, 'ajax_get_settings'));
        add_action('wp_ajax_ecp_clear_sharing_data', array($this, 'ajax_clear_data'));
        add_action('wp_ajax_ecp_get_sharing_overview', array($this, 'ajax_get_overview'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_localize_script', array($this, 'localize_frontend_data'));
    }

    /**
     * Settings registrieren
     */
    public function register_settings()
    {
        register_setting(
            'ecp_data_sharing_group',
            $this->settings_key,
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Frontend-Assets einbinden
     */
    public function enqueue_frontend_assets()
    {
        // Nur laden wenn mindestens ein Kalkulator Data Sharing aktiviert hat
        if (!$this->has_data_sharing_enabled()) {
            return;
        }

        wp_enqueue_script(
            'ecp-data-sharing',
            ECP_PLUGIN_URL . 'assets/data-sharing.js',
            array('ecp-frontend-js'),
            ECP_VERSION,
            true
        );

        wp_enqueue_style(
            'ecp-data-sharing-css',
            ECP_PLUGIN_URL . 'assets/data-sharing.css',
            array('ecp-frontend-css'),
            ECP_VERSION
        );

        // Konfiguration für Frontend bereitstellen
        wp_localize_script('ecp-data-sharing', 'ecpDataSharing', array(
            'settings' => $this->get_frontend_settings(),
            'strings' => $this->get_frontend_strings(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }

    /**
     * Frontend-Einstellungen abrufen
     */
    private function get_frontend_settings()
    {
        $settings = get_option($this->settings_key, array());
        $frontend_settings = array();

        foreach ($settings as $calculator_id => $calc_settings) {
            if (!empty($calc_settings['enable_export']) || !empty($calc_settings['enable_import'])) {
                $frontend_settings[$calculator_id] = array(
                    'export_enabled' => !empty($calc_settings['enable_export']),
                    'import_enabled' => !empty($calc_settings['enable_import']),
                    'field_mappings' => $calc_settings['field_mappings'] ?? array(),
                    'data_retention_days' => $calc_settings['data_retention_days'] ?? 30,
                    'show_ui_hints' => $calc_settings['show_ui_hints'] ?? true
                );
            }
        }

        return $frontend_settings;
    }

    /**
     * Frontend-Strings
     */
    private function get_frontend_strings()
    {
        return array(
            'data_loaded_from' => __('Wert aus %s übernommen', 'excel-calculator-pro'),
            'data_shared_tooltip' => __('Dieses Feld wurde automatisch aus einem anderen Kalkulator befüllt. Klicken Sie hier für Details.', 'excel-calculator-pro'),
            'data_source_calculator' => __('Quelle: %s', 'excel-calculator-pro'),
            'data_timestamp' => __('Zeitpunkt: %s', 'excel-calculator-pro'),
            'clear_shared_data' => __('Geteilte Daten löschen', 'excel-calculator-pro'),
            'data_cleared' => __('Geteilte Daten wurden gelöscht.', 'excel-calculator-pro')
        );
    }

    /**
     * Prüft ob mindestens ein Kalkulator Data Sharing aktiviert hat
     */
    private function has_data_sharing_enabled()
    {
        $settings = get_option($this->settings_key, array());

        foreach ($settings as $calc_settings) {
            if (!empty($calc_settings['enable_export']) || !empty($calc_settings['enable_import'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Admin-Tab Inhalt rendern
     */
    public function render_admin_tab()
    {
        $calculators = $this->get_calculators();
        $settings = get_option($this->settings_key, array());
?>
        <div class="ecp-data-sharing-admin">
            <div class="ecp-settings-container">
                <h2><?php _e('Datenübertragung zwischen Kalkulatoren', 'excel-calculator-pro'); ?></h2>
                <p class="description">
                    <?php _e('Konfigurieren Sie, wie Daten zwischen verschiedenen Kalkulatoren automatisch geteilt werden sollen. Die Daten werden lokal im Browser des Benutzers gespeichert.', 'excel-calculator-pro'); ?>
                </p>

                <?php if (empty($calculators)): ?>
                    <div class="ecp-empty-state">
                        <p><?php _e('Keine Kalkulatoren gefunden. Erstellen Sie zuerst einen Kalkulator.', 'excel-calculator-pro'); ?></p>
                    </div>
                <?php else: ?>

                    <!-- Globale Einstellungen -->
                    <div class="ecp-section">
                        <h3><?php _e('Globale Einstellungen', 'excel-calculator-pro'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Standard Aufbewahrungsdauer', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <input type="number" id="ecp-default-retention" value="30" min="1" max="365" class="small-text">
                                    <span class="description"><?php _e('Tage (Standard: 30)', 'excel-calculator-pro'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('UI-Hinweise anzeigen', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="ecp-global-ui-hints" checked>
                                        <?php _e('Kleine Badges bei automatisch befüllten Feldern anzeigen', 'excel-calculator-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kalkulator-spezifische Einstellungen -->
                    <div class="ecp-section">
                        <h3><?php _e('Kalkulator-Einstellungen', 'excel-calculator-pro'); ?></h3>
                        <div class="ecp-calculators-sharing-grid">
                            <?php foreach ($calculators as $calculator): ?>
                                <?php $calc_settings = $settings[$calculator->id] ?? array(); ?>
                                <div class="ecp-calculator-sharing-card" data-calculator-id="<?php echo esc_attr($calculator->id); ?>">
                                    <div class="ecp-card-header">
                                        <h4><?php echo esc_html($calculator->name); ?></h4>
                                        <small class="ecp-calculator-id">ID: <?php echo $calculator->id; ?></small>
                                    </div>

                                    <div class="ecp-card-content">
                                        <div class="ecp-setting-group">
                                            <h5><?php _e('Daten-Export', 'excel-calculator-pro'); ?></h5>
                                            <label>
                                                <input type="checkbox"
                                                    class="ecp-enable-export"
                                                    <?php checked(!empty($calc_settings['enable_export'])); ?>>
                                                <?php _e('Ergebnisse und Eingaben für andere Kalkulatoren verfügbar machen', 'excel-calculator-pro'); ?>
                                            </label>
                                        </div>

                                        <div class="ecp-setting-group">
                                            <h5><?php _e('Daten-Import', 'excel-calculator-pro'); ?></h5>
                                            <label>
                                                <input type="checkbox"
                                                    class="ecp-enable-import"
                                                    <?php checked(!empty($calc_settings['enable_import'])); ?>>
                                                <?php _e('Daten von anderen Kalkulatoren automatisch verwenden', 'excel-calculator-pro'); ?>
                                            </label>
                                        </div>

                                        <!-- Feld-Mappings -->
                                        <div class="ecp-setting-group ecp-field-mappings" style="display: none;">
                                            <h5><?php _e('Feld-Zuordnungen', 'excel-calculator-pro'); ?></h5>
                                            <p class="description">
                                                <?php _e('Standardmäßig werden Felder mit identischen IDs automatisch zugeordnet. Hier können Sie benutzerdefinierte Zuordnungen erstellen.', 'excel-calculator-pro'); ?>
                                            </p>
                                            <div class="ecp-mappings-container">
                                                <!-- Wird per JavaScript befüllt -->
                                            </div>
                                            <button type="button" class="button ecp-add-mapping">
                                                <?php _e('Zuordnung hinzufügen', 'excel-calculator-pro'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="ecp-card-footer">
                                        <button type="button" class="button ecp-save-calculator-settings">
                                            <?php _e('Speichern', 'excel-calculator-pro'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Datenübersicht -->
                    <div class="ecp-section">
                        <h3><?php _e('Datenübersicht', 'excel-calculator-pro'); ?></h3>
                        <p class="description">
                            <?php _e('Diese Ansicht zeigt, welche Daten aktuell im Browser der Benutzer gespeichert werden könnten. Die tatsächlichen Daten sind nur für jeden Benutzer individuell sichtbar.', 'excel-calculator-pro'); ?>
                        </p>

                        <div class="ecp-data-overview">
                            <div class="ecp-overview-stats">
                                <div class="ecp-stat-card">
                                    <div class="ecp-stat-number" id="ecp-enabled-calculators">-</div>
                                    <div class="ecp-stat-label"><?php _e('Aktivierte Kalkulatoren', 'excel-calculator-pro'); ?></div>
                                </div>
                                <div class="ecp-stat-card">
                                    <div class="ecp-stat-number" id="ecp-shared-fields">-</div>
                                    <div class="ecp-stat-label"><?php _e('Geteilte Felder', 'excel-calculator-pro'); ?></div>
                                </div>
                                <div class="ecp-stat-card">
                                    <div class="ecp-stat-number" id="ecp-custom-mappings">-</div>
                                    <div class="ecp-stat-label"><?php _e('Benutzerdefinierte Zuordnungen', 'excel-calculator-pro'); ?></div>
                                </div>
                            </div>

                            <div class="ecp-overview-actions">
                                <button type="button" class="button" id="ecp-refresh-overview">
                                    <?php _e('Aktualisieren', 'excel-calculator-pro'); ?>
                                </button>
                                <button type="button" class="button button-link-delete" id="ecp-clear-all-sharing-data">
                                    <?php _e('Alle geteilten Daten löschen', 'excel-calculator-pro'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <style>
            .ecp-calculators-sharing-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .ecp-calculator-sharing-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .ecp-card-header {
                border-bottom: 1px solid #eee;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }

            .ecp-card-header h4 {
                margin: 0 0 5px 0;
                color: #333;
            }

            .ecp-calculator-id {
                color: #666;
                font-size: 12px;
            }

            .ecp-setting-group {
                margin-bottom: 20px;
            }

            .ecp-setting-group h5 {
                margin: 0 0 10px 0;
                color: #007cba;
                font-size: 14px;
            }

            .ecp-setting-group label {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                font-size: 13px;
            }

            .ecp-card-footer {
                border-top: 1px solid #eee;
                padding-top: 15px;
                text-align: right;
            }

            .ecp-overview-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }

            .ecp-stat-card {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                border: 1px solid #e9ecef;
            }

            .ecp-stat-number {
                font-size: 28px;
                font-weight: bold;
                color: #007cba;
                margin-bottom: 5px;
            }

            .ecp-stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .ecp-overview-actions {
                text-align: center;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }

            .ecp-overview-actions .button {
                margin: 0 5px;
            }
        </style>
<?php
    }

    /**
     * Kalkulatoren für Admin abrufen
     */
    private function get_calculators()
    {
        if (!$this->database) {
            $this->database = ecp_init()->get_database();
        }

        return $this->database->get_calculators(array(
            'orderby' => 'name',
            'order' => 'ASC'
        ));
    }

    /**
     * AJAX: Einstellungen speichern
     */
    public function ajax_save_settings()
    {
        $this->verify_admin_access();

        try {
            $settings = $this->sanitize_settings($_POST['settings'] ?? array());
            update_option($this->settings_key, $settings);

            wp_send_json_success(array(
                'message' => __('Einstellungen erfolgreich gespeichert.', 'excel-calculator-pro')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Einstellungen abrufen
     */
    public function ajax_get_settings()
    {
        $this->verify_admin_access();

        $calculator_id = intval($_POST['calculator_id'] ?? 0);
        $settings = get_option($this->settings_key, array());

        wp_send_json_success($settings[$calculator_id] ?? array());
    }

    /**
     * AJAX: Geteilte Daten löschen
     */
    public function ajax_clear_data()
    {
        $this->verify_admin_access();

        // Da localStorage clientseitig ist, können wir nur die Einstellungen zurücksetzen
        delete_option($this->settings_key);

        wp_send_json_success(array(
            'message' => __('Sharing-Konfiguration wurde zurückgesetzt.', 'excel-calculator-pro')
        ));
    }

    /**
     * AJAX: Übersicht abrufen
     */
    public function ajax_get_overview()
    {
        $this->verify_admin_access();

        $settings = get_option($this->settings_key, array());
        $stats = array(
            'enabled_calculators' => 0,
            'shared_fields' => 0,
            'custom_mappings' => 0
        );

        foreach ($settings as $calc_settings) {
            if (!empty($calc_settings['enable_export']) || !empty($calc_settings['enable_import'])) {
                $stats['enabled_calculators']++;
            }

            if (!empty($calc_settings['field_mappings'])) {
                $stats['custom_mappings'] += count($calc_settings['field_mappings']);
            }
        }

        wp_send_json_success($stats);
    }

    /**
     * Einstellungen sanitizen
     */
    private function sanitize_settings($settings)
    {
        if (!is_array($settings)) {
            throw new InvalidArgumentException(__('Ungültige Einstellungen.', 'excel-calculator-pro'));
        }

        $sanitized = array();

        foreach ($settings as $calculator_id => $calc_settings) {
            $calc_id = intval($calculator_id);
            if ($calc_id <= 0) {
                continue;
            }

            $sanitized[$calc_id] = array(
                'enable_export' => !empty($calc_settings['enable_export']),
                'enable_import' => !empty($calc_settings['enable_import']),
                'data_retention_days' => max(1, min(365, intval($calc_settings['data_retention_days'] ?? 30))),
                'show_ui_hints' => !empty($calc_settings['show_ui_hints']),
                'field_mappings' => $this->sanitize_field_mappings($calc_settings['field_mappings'] ?? array())
            );
        }

        return $sanitized;
    }

    /**
     * Feld-Mappings sanitizen
     */
    private function sanitize_field_mappings($mappings)
    {
        if (!is_array($mappings)) {
            return array();
        }

        $sanitized = array();

        foreach ($mappings as $mapping) {
            if (!is_array($mapping) || empty($mapping['source_field']) || empty($mapping['target_field'])) {
                continue;
            }

            $sanitized[] = array(
                'source_field' => sanitize_key($mapping['source_field']),
                'target_field' => sanitize_key($mapping['target_field']),
                'source_calculator' => intval($mapping['source_calculator'] ?? 0)
            );
        }

        return $sanitized;
    }

    /**
     * Admin-Zugriff verifizieren
     */
    private function verify_admin_access()
    {
        if (!check_ajax_referer('ecp_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen.', 'excel-calculator-pro')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'excel-calculator-pro')));
        }
    }
}
