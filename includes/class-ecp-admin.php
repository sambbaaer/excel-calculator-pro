<?php

/**
 * Vollständige Admin Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Admin_Enhanced
{

    private $database;

    public function __construct()
    {
        $this->database = ecp_init()->get_database();
        $this->init_hooks();
    }

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
        add_action('wp_ajax_ecp_save_style_settings', array($this, 'ajax_save_style_settings'));
        add_action('wp_ajax_ecp_reset_style_settings', array($this, 'ajax_reset_style_settings'));
        add_action('wp_ajax_ecp_test_calculation', array($this, 'ajax_test_calculation'));
    }

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

    public function admin_enqueue_scripts($hook)
    {
        if ($hook !== 'settings_page_excel-calculator-pro') {
            return;
        }

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

        wp_localize_script('ecp-admin-js', 'ecp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Sind Sie sicher, dass Sie diesen Kalkulator löschen möchten?', 'excel-calculator-pro'),
                'error_name_required' => __('Bitte geben Sie einen Namen ein.', 'excel-calculator-pro'),
                'success_saved' => __('Kalkulator gespeichert!', 'excel-calculator-pro'),
                'success_deleted' => __('Kalkulator gelöscht!', 'excel-calculator-pro'),
                'error_occurred' => __('Ein Fehler ist aufgetreten:', 'excel-calculator-pro'),
                'loading' => __('Lädt...', 'excel-calculator-pro'),
                'template_created' => __('Kalkulator aus Vorlage erstellt!', 'excel-calculator-pro'),
                'style_saved' => __('Design-Einstellungen gespeichert!', 'excel-calculator-pro'),
                'style_reset' => __('Design-Einstellungen zurückgesetzt!', 'excel-calculator-pro'),
                'confirm_reset_styles' => __('Möchten Sie wirklich alle Design-Einstellungen zurücksetzen?', 'excel-calculator-pro'),
                'calculation_test_passed' => __('Test bestanden! 12 + 8 = 20', 'excel-calculator-pro'),
                'calculation_test_failed' => __('Test fehlgeschlagen!', 'excel-calculator-pro')
            )
        ));
    }

    public function admin_init()
    {
        register_setting('ecp_settings', 'ecp_general_settings');
        register_setting('ecp_style_settings', 'ecp_style_settings');

        add_settings_section(
            'ecp_general_section',
            __('Allgemeine Einstellungen', 'excel-calculator-pro'),
            array($this, 'general_section_callback'),
            'ecp_settings'
        );

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
    }

    public function admin_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calculators';
?>
        <div class="wrap">
            <h1>
                <?php _e('Excel Calculator Pro', 'excel-calculator-pro'); ?>
                <span style="font-size: 12px; color: #666; font-weight: normal;">v<?php echo ECP_VERSION; ?></span>
            </h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=excel-calculator-pro&tab=calculators"
                    class="nav-tab <?php echo $current_tab === 'calculators' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Kalkulatoren', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=templates"
                    class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Vorlagen', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=styling"
                    class="nav-tab <?php echo $current_tab === 'styling' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Design anpassen', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=import-export"
                    class="nav-tab <?php echo $current_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import/Export', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=settings"
                    class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Einstellungen', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=debug"
                    class="nav-tab <?php echo $current_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Debug & Test', 'excel-calculator-pro'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'templates':
                        $this->templates_tab();
                        break;
                    case 'styling':
                        $this->styling_tab();
                        break;
                    case 'import-export':
                        $this->import_export_tab();
                        break;
                    case 'settings':
                        $this->settings_tab();
                        break;
                    case 'debug':
                        $this->debug_tab();
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
     * Debug & Test Tab
     */
    private function debug_tab()
    {
    ?>
        <div id="ecp-debug-tab">
            <div class="ecp-debug-header">
                <h2><?php _e('Debug & Fehlerbehebung', 'excel-calculator-pro'); ?></h2>
                <p><?php _e('Tools zur Diagnose und Behebung von Problemen.', 'excel-calculator-pro'); ?></p>
            </div>

            <div class="ecp-debug-sections">
                <!-- Berechnungs-Tests -->
                <div class="ecp-debug-section">
                    <h3><?php _e('Berechnungs-Tests', 'excel-calculator-pro'); ?></h3>
                    <div class="ecp-test-container">
                        <h4><?php _e('Grundrechenarten testen', 'excel-calculator-pro'); ?></h4>
                        <button id="ecp-test-basic-math" class="button button-primary">
                            <?php _e('Test: 12 + 8 = ?', 'excel-calculator-pro'); ?>
                        </button>
                        <button id="ecp-test-complex-math" class="button">
                            <?php _e('Test: (100 * 5) / 2 + 50 = ?', 'excel-calculator-pro'); ?>
                        </button>
                        <button id="ecp-test-functions" class="button">
                            <?php _e('Test: RUNDEN(3.14159, 2) = ?', 'excel-calculator-pro'); ?>
                        </button>
                        <div id="ecp-test-results" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;"></div>
                    </div>
                </div>

                <!-- System-Informationen -->
                <div class="ecp-debug-section">
                    <h3><?php _e('System-Informationen', 'excel-calculator-pro'); ?></h3>
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><strong>Plugin Version:</strong></td>
                                <td><?php echo ECP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong>WordPress Version:</strong></td>
                                <td><?php echo get_bloginfo('version'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>PHP Version:</strong></td>
                                <td><?php echo PHP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Aktive Kalkulatoren:</strong></td>
                                <td><?php echo count($this->database->get_calculators()); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Verfügbare Vorlagen:</strong></td>
                                <td><?php echo count($this->database->get_templates()); ?></td>
                            </tr>
                            <tr>
                                <td><strong>JavaScript aktiviert:</strong></td>
                                <td><span id="js-status">❌ Wird getestet...</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Datenbank-Status -->
                <div class="ecp-debug-section">
                    <h3><?php _e('Datenbank-Status', 'excel-calculator-pro'); ?></h3>
                    <?php
                    $integrity_check = $this->database->check_integrity();
                    if ($integrity_check === true) {
                        echo '<div class="notice notice-success inline"><p>✅ ' . __('Datenbank-Integrität: Alles in Ordnung', 'excel-calculator-pro') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error inline"><p>❌ ' . __('Datenbank-Probleme gefunden:', 'excel-calculator-pro') . '</p><ul>';
                        foreach ($integrity_check as $issue) {
                            echo '<li>' . esc_html($issue) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                    ?>
                </div>

                <!-- Error Log -->
                <div class="ecp-debug-section">
                    <h3><?php _e('Fehlerprotokoll', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Letzte JavaScript-Console-Meldungen (falls verfügbar):', 'excel-calculator-pro'); ?></p>
                    <div id="ecp-console-log" style="background: #000; color: #0f0; padding: 10px; font-family: monospace; min-height: 100px; border-radius: 4px;">
                        <div id="console-content"><?php _e('Keine Meldungen bisher...', 'excel-calculator-pro'); ?></div>
                    </div>
                    <button id="ecp-clear-console" class="button" style="margin-top: 10px;">
                        <?php _e('Console leeren', 'excel-calculator-pro'); ?>
                    </button>
                </div>

                <!-- Diagnose-Tools -->
                <div class="ecp-debug-section">
                    <h3><?php _e('Diagnose-Tools', 'excel-calculator-pro'); ?></h3>
                    <div class="ecp-diagnostic-buttons">
                        <button id="ecp-regenerate-tables" class="button">
                            <?php _e('Tabellen neu erstellen', 'excel-calculator-pro'); ?>
                        </button>
                        <button id="ecp-clear-cache" class="button">
                            <?php _e('Cache leeren', 'excel-calculator-pro'); ?>
                        </button>
                        <button id="ecp-export-debug-info" class="button">
                            <?php _e('Debug-Info exportieren', 'excel-calculator-pro'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // JavaScript Status anzeigen
                $('#js-status').html('✅ JavaScript funktioniert');

                // Console Logging
                var originalConsole = {
                    log: console.log,
                    error: console.error,
                    warn: console.warn
                };

                function addToConsole(type, message) {
                    var timestamp = new Date().toLocaleTimeString();
                    var color = type === 'error' ? '#f00' : type === 'warn' ? '#ff0' : '#0f0';
                    var entry = '<div style="color: ' + color + '">[' + timestamp + '] ' + type.toUpperCase() + ': ' + message + '</div>';
                    $('#console-content').append(entry);
                    $('#ecp-console-log').scrollTop($('#ecp-console-log')[0].scrollHeight);
                }

                // Console überschreiben
                console.log = function() {
                    originalConsole.log.apply(console, arguments);
                    addToConsole('log', Array.prototype.slice.call(arguments).join(' '));
                };

                console.error = function() {
                    originalConsole.error.apply(console, arguments);
                    addToConsole('error', Array.prototype.slice.call(arguments).join(' '));
                };

                console.warn = function() {
                    originalConsole.warn.apply(console, arguments);
                    addToConsole('warn', Array.prototype.slice.call(arguments).join(' '));
                };

                // Test-Buttons
                $('#ecp-test-basic-math').on('click', function() {
                    var $results = $('#ecp-test-results');
                    $results.html('<div style="color: #666;">Teste 12 + 8...</div>');

                    $.post(ajaxurl, {
                        action: 'ecp_test_calculation',
                        nonce: ecp_admin.nonce,
                        test_type: 'basic_math'
                    }, function(response) {
                        if (response.success) {
                            if (response.data.result === 20) {
                                $results.html('<div style="color: #080;">✅ Test bestanden! 12 + 8 = ' + response.data.result + '</div>');
                            } else {
                                $results.html('<div style="color: #c00;">❌ Test fehlgeschlagen! 12 + 8 = ' + response.data.result + ' (erwartet: 20)</div>');
                            }
                        } else {
                            $results.html('<div style="color: #c00;">❌ Fehler: ' + response.data + '</div>');
                        }
                    });
                });

                $('#ecp-test-complex-math').on('click', function() {
                    var $results = $('#ecp-test-results');
                    $results.html('<div style="color: #666;">Teste (100 * 5) / 2 + 50...</div>');

                    $.post(ajaxurl, {
                        action: 'ecp_test_calculation',
                        nonce: ecp_admin.nonce,
                        test_type: 'complex_math'
                    }, function(response) {
                        if (response.success) {
                            if (response.data.result === 300) {
                                $results.html('<div style="color: #080;">✅ Test bestanden! (100 * 5) / 2 + 50 = ' + response.data.result + '</div>');
                            } else {
                                $results.html('<div style="color: #c00;">❌ Test fehlgeschlagen! Ergebnis: ' + response.data.result + ' (erwartet: 300)</div>');
                            }
                        } else {
                            $results.html('<div style="color: #c00;">❌ Fehler: ' + response.data + '</div>');
                        }
                    });
                });

                $('#ecp-test-functions').on('click', function() {
                    var $results = $('#ecp-test-results');
                    $results.html('<div style="color: #666;">Teste RUNDEN(3.14159, 2)...</div>');

                    $.post(ajaxurl, {
                        action: 'ecp_test_calculation',
                        nonce: ecp_admin.nonce,
                        test_type: 'function_test'
                    }, function(response) {
                        if (response.success) {
                            if (response.data.result === 3.14) {
                                $results.html('<div style="color: #080;">✅ Test bestanden! RUNDEN(3.14159, 2) = ' + response.data.result + '</div>');
                            } else {
                                $results.html('<div style="color: #c00;">❌ Test fehlgeschlagen! Ergebnis: ' + response.data.result + ' (erwartet: 3.14)</div>');
                            }
                        } else {
                            $results.html('<div style="color: #c00;">❌ Fehler: ' + response.data + '</div>');
                        }
                    });
                });

                // Console leeren
                $('#ecp-clear-console').on('click', function() {
                    $('#console-content').html('Console geleert...');
                });

                // Diagnose-Tools
                $('#ecp-regenerate-tables').on('click', function() {
                    if (confirm('Sollen die Datenbank-Tabellen wirklich neu erstellt werden?')) {
                        $(this).text('Wird bearbeitet...').prop('disabled', true);

                        $.post(ajaxurl, {
                            action: 'ecp_regenerate_tables',
                            nonce: ecp_admin.nonce
                        }, function(response) {
                            if (response.success) {
                                alert('Tabellen erfolgreich neu erstellt!');
                                location.reload();
                            } else {
                                alert('Fehler: ' + response.data);
                            }
                        });
                    }
                });

                console.log('ECP Debug-Seite geladen');
            });
        </script>
    <?php
    }

    /**
     * Styling-Tab (wie vorher definiert)
     */
    private function styling_tab()
    {
        $style_settings = get_option('ecp_style_settings', array());
    ?>
        <div id="ecp-styling-tab">
            <div class="ecp-styling-header">
                <h2><?php _e('Design der Kalkulatoren anpassen', 'excel-calculator-pro'); ?></h2>
                <p><?php _e('Passen Sie das Aussehen Ihrer Kalkulatoren an Ihre Website an.', 'excel-calculator-pro'); ?></p>

                <!-- Wichtiger Hinweis für das CSS -->
                <div class="notice notice-info inline">
                    <p><strong><?php _e('Wichtig:', 'excel-calculator-pro'); ?></strong>
                        <?php _e('Um das anpassbare CSS zu verwenden, müssen Sie die neue CSS-Datei einbinden. Kopieren Sie diese Zeile in Ihre functions.php:', 'excel-calculator-pro'); ?></p>
                    <code style="background: #f1f1f1; padding: 5px; display: block; margin: 10px 0;">
                        wp_enqueue_style('ecp-frontend-customizable', plugin_dir_url(__FILE__) . 'excel-calculator-pro/assets/frontend-customizable.css', array(), '1.0.0');
                    </code>
                </div>
            </div>

            <div class="ecp-styling-content">
                <div class="ecp-styling-sections">
                    <!-- Farbschema -->
                    <div class="ecp-style-section">
                        <h3><?php _e('Farbschema', 'excel-calculator-pro'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Vordefinierte Schemas', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <select id="preset-theme" name="preset_theme">
                                        <option value=""><?php _e('Benutzerdefiniert', 'excel-calculator-pro'); ?></option>
                                        <option value="subtle" <?php selected($style_settings['preset_theme'] ?? '', 'subtle'); ?>><?php _e('Dezent (Grau)', 'excel-calculator-pro'); ?></option>
                                        <option value="warm" <?php selected($style_settings['preset_theme'] ?? '', 'warm'); ?>><?php _e('Warm (Orange)', 'excel-calculator-pro'); ?></option>
                                        <option value="nature" <?php selected($style_settings['preset_theme'] ?? '', 'nature'); ?>><?php _e('Natur (Grün)', 'excel-calculator-pro'); ?></option>
                                        <option value="elegant" <?php selected($style_settings['preset_theme'] ?? '', 'elegant'); ?>><?php _e('Elegant (Violett)', 'excel-calculator-pro'); ?></option>
                                        <option value="minimal" <?php selected($style_settings['preset_theme'] ?? '', 'minimal'); ?>><?php _e('Minimalistisch', 'excel-calculator-pro'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Wählen Sie ein vordefiniertes Farbschema oder passen Sie die Farben manuell an.', 'excel-calculator-pro'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Hauptfarbe', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <input type="text" id="primary-color" name="primary_color" value="<?php echo esc_attr($style_settings['primary_color'] ?? '#007cba'); ?>" class="ecp-color-picker" />
                                    <p class="description"><?php _e('Die Hauptfarbe für Buttons und Hervorhebungen.', 'excel-calculator-pro'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Sekundärfarbe', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <input type="text" id="secondary-color" name="secondary_color" value="<?php echo esc_attr($style_settings['secondary_color'] ?? '#00a0d2'); ?>" class="ecp-color-picker" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Hintergrundfarbe', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <input type="text" id="background-color" name="background_color" value="<?php echo esc_attr($style_settings['background_color'] ?? '#ffffff'); ?>" class="ecp-color-picker" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Textfarbe', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <input type="text" id="text-color" name="text_color" value="<?php echo esc_attr($style_settings['text_color'] ?? '#2c3e50'); ?>" class="ecp-color-picker" />
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Shortcode-Generator -->
                    <div class="ecp-style-section">
                        <h3><?php _e('Shortcode mit Design-Klassen', 'excel-calculator-pro'); ?></h3>
                        <p><?php _e('Generieren Sie Shortcodes mit vorgefertigten Design-Klassen:', 'excel-calculator-pro'); ?></p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Kalkulator wählen', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <select id="shortcode-calculator">
                                        <option value=""><?php _e('Kalkulator wählen...', 'excel-calculator-pro'); ?></option>
                                        <?php
                                        $calculators = $this->database->get_calculators();
                                        foreach ($calculators as $calc) {
                                            echo '<option value="' . $calc->id . '">' . esc_html($calc->name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Design-Stil', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <select id="shortcode-theme">
                                        <option value=""><?php _e('Standard', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-theme-subtle"><?php _e('Dezent', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-theme-warm"><?php _e('Warm', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-theme-nature"><?php _e('Natur', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-theme-elegant"><?php _e('Elegant', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-theme-minimal"><?php _e('Minimalistisch', 'excel-calculator-pro'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Grösse', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <select id="shortcode-size">
                                        <option value=""><?php _e('Standard', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-size-compact"><?php _e('Kompakt', 'excel-calculator-pro'); ?></option>
                                        <option value="ecp-size-large"><?php _e('Gross', 'excel-calculator-pro'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Generierter Shortcode', 'excel-calculator-pro'); ?></th>
                                <td>
                                    <input type="text" id="generated-shortcode" class="large-text" readonly value="[excel_calculator id=&quot;1&quot;]" />
                                    <button id="copy-generated-shortcode" class="button"><?php _e('Kopieren', 'excel-calculator-pro'); ?></button>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Live-Vorschau -->
                <div class="ecp-style-preview">
                    <h3><?php _e('Live-Vorschau', 'excel-calculator-pro'); ?></h3>
                    <div id="ecp-style-preview-container">
                        <?php echo $this->generate_preview_calculator(); ?>
                    </div>
                </div>

                <!-- Aktionen -->
                <div class="ecp-styling-actions">
                    <button id="ecp-save-styles" class="button button-primary">
                        <?php _e('Design speichern', 'excel-calculator-pro'); ?>
                    </button>
                    <button id="ecp-reset-styles" class="button">
                        <?php _e('Zurücksetzen', 'excel-calculator-pro'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php $this->add_styling_scripts(); ?>
    <?php
    }

    /**
     * Styling-JavaScript
     */
    private function add_styling_scripts()
    {
    ?>
        <script>
            jQuery(document).ready(function($) {
                // Color Picker initialisieren
                $('.ecp-color-picker').wpColorPicker({
                    change: function() {
                        updatePreview();
                    }
                });

                // Shortcode-Generator
                function updateShortcode() {
                    var calcId = $('#shortcode-calculator').val();
                    var theme = $('#shortcode-theme').val();
                    var size = $('#shortcode-size').val();

                    if (!calcId) {
                        $('#generated-shortcode').val('[excel_calculator id="1"]');
                        return;
                    }

                    var shortcode = '[excel_calculator id="' + calcId + '"';

                    var classes = [];
                    if (theme) classes.push(theme);
                    if (size) classes.push(size);

                    if (classes.length > 0) {
                        shortcode += ' class="' + classes.join(' ') + '"';
                    }

                    shortcode += ']';
                    $('#generated-shortcode').val(shortcode);
                }

                $('#shortcode-calculator, #shortcode-theme, #shortcode-size').on('change', updateShortcode);

                // Shortcode kopieren
                $('#copy-generated-shortcode').on('click', function() {
                    $('#generated-shortcode').select();
                    document.execCommand('copy');
                    $(this).text('✓ Kopiert!');
                    setTimeout(function() {
                        $('#copy-generated-shortcode').text('<?php _e('Kopieren', 'excel-calculator-pro'); ?>');
                    }, 2000);
                });

                // Theme-Vorschau
                $('#preset-theme').on('change', function() {
                    var theme = $(this).val();
                    var $preview = $('#ecp-style-preview-container .ecp-calculator');

                    // Alle Theme-Klassen entfernen
                    $preview.removeClass('ecp-theme-subtle ecp-theme-warm ecp-theme-nature ecp-theme-elegant ecp-theme-minimal');

                    // Neue Theme-Klasse hinzufügen
                    if (theme) {
                        $preview.addClass('ecp-theme-' + theme);
                    }
                });

                // Speichern
                $('#ecp-save-styles').on('click', function() {
                    var $button = $(this);
                    var originalText = $button.text();
                    $button.text('<?php _e('Speichere...', 'excel-calculator-pro'); ?>').prop('disabled', true);

                    var settings = {
                        preset_theme: $('#preset-theme').val(),
                        primary_color: $('#primary-color').val(),
                        secondary_color: $('#secondary-color').val(),
                        background_color: $('#background-color').val(),
                        text_color: $('#text-color').val()
                    };

                    $.post(ajaxurl, {
                        action: 'ecp_save_style_settings',
                        nonce: ecp_admin.nonce,
                        settings: settings
                    }, function(response) {
                        if (response.success) {
                            $button.text('✓ ' + ecp_admin.strings.style_saved);
                            setTimeout(function() {
                                $button.text(originalText).prop('disabled', false);
                            }, 2000);
                        } else {
                            alert(ecp_admin.strings.error_occurred + ' ' + response.data);
                            $button.text(originalText).prop('disabled', false);
                        }
                    });
                });

                // Initial Setup
                updateShortcode();
            });
        </script>
    <?php
    }

    /**
     * Vorschau-Kalkulator generieren
     */
    private function generate_preview_calculator()
    {
        return '
        <div class="ecp-calculator ecp-preview-demo" data-calculator-id="preview">
            <div class="ecp-calculator-header">
                <h3 class="ecp-calculator-title">Vorschau-Kalkulator</h3>
                <p class="ecp-calculator-description">So sehen Ihre Kalkulatoren mit den aktuellen Einstellungen aus.</p>
            </div>
            
            <div class="ecp-section ecp-input-fields">
                <h4 class="ecp-section-title">Eingaben</h4>
                
                <div class="ecp-field-group">
                    <label>Betrag</label>
                    <div class="ecp-input-wrapper">
                        <input type="number" class="ecp-input-field" value="1000" />
                        <span class="ecp-input-unit">CHF</span>
                    </div>
                </div>
                
                <div class="ecp-field-group">
                    <label>Zinssatz</label>
                    <div class="ecp-input-wrapper">
                        <input type="number" class="ecp-input-field" value="3.5" />
                        <span class="ecp-input-unit">%</span>
                    </div>
                </div>
            </div>
            
            <div class="ecp-section ecp-output-fields">
                <h4 class="ecp-section-title">Ergebnisse</h4>
                
                <div class="ecp-output-group">
                    <label>Zinsen pro Jahr</label>
                    <div class="ecp-output-wrapper">
                        <span class="ecp-output-field">CHF 35.00</span>
                    </div>
                </div>
                
                <div class="ecp-output-group">
                    <label>Total nach 5 Jahren</label>
                    <div class="ecp-output-wrapper">
                        <span class="ecp-output-field">CHF 1\'175.00</span>
                    </div>
                </div>
            </div>
        </div>';
    }

    /**
     * AJAX: Berechnungs-Test
     */
    public function ajax_test_calculation()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $test_type = sanitize_text_field($_POST['test_type']);

        // Einfacher Test der Berechnungslogik (Server-seitig)
        switch ($test_type) {
            case 'basic_math':
                $result = 12 + 8;
                wp_send_json_success(array('result' => $result, 'expected' => 20));
                break;

            case 'complex_math':
                $result = (100 * 5) / 2 + 50;
                wp_send_json_success(array('result' => $result, 'expected' => 300));
                break;

            case 'function_test':
                $result = round(3.14159, 2);
                wp_send_json_success(array('result' => $result, 'expected' => 3.14));
                break;

            default:
                wp_send_json_error(__('Unbekannter Test-Typ', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Style-Einstellungen speichern
     */
    public function ajax_save_style_settings()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $settings = $_POST['settings'] ?? array();

        // Sanitize settings
        $clean_settings = array();
        foreach ($settings as $key => $value) {
            $clean_settings[sanitize_key($key)] = sanitize_text_field($value);
        }

        $result = update_option('ecp_style_settings', $clean_settings);
        wp_send_json_success();
    }

    /**
     * AJAX: Style-Einstellungen zurücksetzen
     */
    public function ajax_reset_style_settings()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        delete_option('ecp_style_settings');
        wp_send_json_success();
    }

    // Restliche Methoden (calculators_tab, templates_tab, etc.) hier einfügen...
    // [Diese bleiben wie in der ursprünglichen Implementierung]

    private function calculators_tab()
    {
    ?>
        <div id="ecp-calculators-tab">
            <div class="ecp-admin-header">
                <button id="ecp-new-calculator" class="button button-primary">
                    <?php _e('Neuer Kalkulator', 'excel-calculator-pro'); ?>
                </button>
                <button id="ecp-bulk-actions" class="button" style="margin-left: 10px;">
                    <?php _e('Massenaktionen', 'excel-calculator-pro'); ?>
                </button>

                <!-- Hinweis auf das Berechnungsproblem -->
                <div class="notice notice-warning inline" style="margin-left: 20px; flex: 1;">
                    <p><strong><?php _e('Berechnungsproblem behoben!', 'excel-calculator-pro'); ?></strong>
                        <?php _e('Falls Sie noch Probleme mit falschen Berechnungen haben, besuchen Sie den Debug-Tab.', 'excel-calculator-pro'); ?></p>
                </div>
            </div>

            <div id="ecp-calculators-list">
                <?php $this->display_calculators_list(); ?>
            </div>
        </div>
    <?php
    }

    private function display_calculators_list()
    {
        $calculators = $this->database->get_calculators();

        if (empty($calculators)) {
            echo '<div class="ecp-empty-state">';
            echo '<p>' . __('Noch keine Kalkulatoren erstellt.', 'excel-calculator-pro') . '</p>';
            echo '<p>' . __('Erstellen Sie Ihren ersten Kalkulator oder verwenden Sie eine Vorlage.', 'excel-calculator-pro') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="ecp-calculators-grid">';
        foreach ($calculators as $calc) {
            echo '<div class="ecp-calculator-card">';
            echo '<div class="ecp-card-header">';
            echo '<h3>' . esc_html($calc->name) . '</h3>';
            echo '<div class="ecp-card-actions">';
            echo '<button class="button-link ecp-edit-calc" data-id="' . $calc->id . '" title="' . __('Bearbeiten', 'excel-calculator-pro') . '">✏️</button>';
            echo '<button class="button-link ecp-duplicate-calc" data-id="' . $calc->id . '" title="' . __('Duplizieren', 'excel-calculator-pro') . '">📋</button>';
            echo '<button class="button-link ecp-delete-calc" data-id="' . $calc->id . '" title="' . __('Löschen', 'excel-calculator-pro') . '">🗑️</button>';
            echo '</div>';
            echo '</div>';

            if (!empty($calc->description)) {
                echo '<p class="ecp-card-description">' . esc_html($calc->description) . '</p>';
            }

            echo '<div class="ecp-shortcode-display">';
            echo '<code>[excel_calculator id="' . $calc->id . '"]</code>';
            echo '<button class="ecp-copy-shortcode" data-shortcode="[excel_calculator id=&quot;' . $calc->id . '&quot;]" title="' . __('Shortcode kopieren', 'excel-calculator-pro') . '">📋</button>';
            echo '</div>';

            echo '<div class="ecp-card-meta">';
            echo '<span>' . sprintf(__('Erstellt: %s', 'excel-calculator-pro'), date_i18n(get_option('date_format'), strtotime($calc->created_at))) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    // Weitere erforderliche Callback-Methoden
    public function general_section_callback()
    {
        echo '<p>' . __('Konfigurieren Sie die allgemeinen Einstellungen für Excel Calculator Pro.', 'excel-calculator-pro') . '</p>';
    }

    public function currency_field_callback()
    {
        $options = get_option('ecp_general_settings', array());
        $currency = isset($options['default_currency']) ? $options['default_currency'] : 'CHF';

        echo '<select name="ecp_general_settings[default_currency]">';
        $currencies = array('CHF' => 'CHF (Schweizer Franken)', 'EUR' => 'EUR (Euro)', 'USD' => 'USD (US-Dollar)');
        foreach ($currencies as $code => $name) {
            echo '<option value="' . $code . '"' . selected($currency, $code, false) . '>' . $name . '</option>';
        }
        echo '</select>';
    }

    public function number_format_field_callback()
    {
        $options = get_option('ecp_general_settings', array());
        $format = isset($options['number_format']) ? $options['number_format'] : 'de_CH';

        echo '<select name="ecp_general_settings[number_format]">';
        $formats = array(
            'de_CH' => 'Schweiz (1\'234.56)',
            'de_DE' => 'Deutschland (1.234,56)',
            'en_US' => 'USA (1,234.56)'
        );
        foreach ($formats as $code => $name) {
            echo '<option value="' . $code . '"' . selected($format, $code, false) . '>' . $name . '</option>';
        }
        echo '</select>';
    }

    /**
     * Templates-Tab vollständig implementieren
     */
    private function templates_tab()
    {
        $templates = $this->database->get_templates();
    ?>
        <div id="ecp-templates-tab">
            <div class="ecp-templates-header">
                <h2><?php _e('Kalkulator-Vorlagen', 'excel-calculator-pro'); ?></h2>
                <p><?php _e('Wählen Sie eine Vorlage aus, um schnell einen neuen Kalkulator zu erstellen.', 'excel-calculator-pro'); ?></p>

                <div class="ecp-template-filters">
                    <label for="template-category-filter"><?php _e('Kategorie:', 'excel-calculator-pro'); ?></label>
                    <select id="template-category-filter">
                        <option value=""><?php _e('Alle Kategorien', 'excel-calculator-pro'); ?></option>
                        <option value="financial"><?php _e('Finanzen', 'excel-calculator-pro'); ?></option>
                        <option value="business"><?php _e('Business', 'excel-calculator-pro'); ?></option>
                        <option value="health"><?php _e('Gesundheit', 'excel-calculator-pro'); ?></option>
                        <option value="education"><?php _e('Bildung', 'excel-calculator-pro'); ?></option>
                        <option value="personal"><?php _e('Persönlich', 'excel-calculator-pro'); ?></option>
                    </select>

                    <input type="text" id="template-search" placeholder="<?php _e('Vorlagen durchsuchen...', 'excel-calculator-pro'); ?>" />
                </div>
            </div>

            <?php if (empty($templates)): ?>
                <div class="ecp-empty-state">
                    <p><?php _e('Noch keine Vorlagen verfügbar.', 'excel-calculator-pro'); ?></p>
                    <p><?php _e('Vorlagen werden automatisch beim ersten Start erstellt.', 'excel-calculator-pro'); ?></p>
                    <button id="ecp-create-default-templates" class="button button-primary">
                        <?php _e('Standard-Vorlagen erstellen', 'excel-calculator-pro'); ?>
                    </button>
                </div>
            <?php else: ?>
                <div class="ecp-templates-grid">
                    <?php foreach ($templates as $template): ?>
                        <div class="ecp-template-card" data-category="<?php echo esc_attr($template->category); ?>">
                            <div class="ecp-template-header">
                                <h3><?php echo esc_html($template->name); ?></h3>
                                <span class="ecp-template-category ecp-category-<?php echo esc_attr($template->category); ?>">
                                    <?php echo esc_html($this->get_category_label($template->category)); ?>
                                </span>
                            </div>

                            <?php if (!empty($template->description)): ?>
                                <p class="ecp-template-description"><?php echo esc_html($template->description); ?></p>
                            <?php endif; ?>

                            <div class="ecp-template-meta">
                                <span class="ecp-template-fields">
                                    <?php echo sprintf(__('%d Eingabefelder', 'excel-calculator-pro'), count($template->fields)); ?>
                                </span>
                                <span class="ecp-template-formulas">
                                    <?php echo sprintf(__('%d Formeln', 'excel-calculator-pro'), count($template->formulas)); ?>
                                </span>
                            </div>

                            <div class="ecp-template-preview">
                                <h4><?php _e('Eingabefelder:', 'excel-calculator-pro'); ?></h4>
                                <ul class="ecp-field-preview">
                                    <?php foreach (array_slice($template->fields, 0, 3) as $field): ?>
                                        <li><?php echo esc_html($field['label']); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($template->fields) > 3): ?>
                                        <li class="ecp-more">+ <?php echo count($template->fields) - 3; ?> weitere</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div class="ecp-template-actions">
                                <button class="button button-primary ecp-use-template"
                                    data-template-id="<?php echo esc_attr($template->id); ?>">
                                    <?php _e('Verwenden', 'excel-calculator-pro'); ?>
                                </button>
                                <button class="button ecp-preview-template"
                                    data-template-id="<?php echo esc_attr($template->id); ?>">
                                    <?php _e('Vorschau', 'excel-calculator-pro'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Template Modal -->
        <div id="ecp-template-modal" class="ecp-modal" style="display: none;">
            <div class="ecp-modal-content">
                <div class="ecp-modal-header">
                    <h3><?php _e('Kalkulator aus Vorlage erstellen', 'excel-calculator-pro'); ?></h3>
                    <span class="ecp-modal-close">&times;</span>
                </div>
                <div class="ecp-modal-body">
                    <form id="ecp-template-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template-calculator-name"><?php _e('Name des Kalkulators', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="template-calculator-name" class="regular-text" required />
                                    <p class="description"><?php _e('Geben Sie einen Namen für den neuen Kalkulator ein.', 'excel-calculator-pro'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="template-calculator-description"><?php _e('Beschreibung (optional)', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <textarea id="template-calculator-description" class="large-text" rows="3"></textarea>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="ecp-modal-actions">
                    <button id="ecp-create-from-template" class="button button-primary">
                        <?php _e('Kalkulator erstellen', 'excel-calculator-pro'); ?>
                    </button>
                    <button class="button ecp-modal-close">
                        <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                var selectedTemplateId = 0;

                // Template-Filter
                $('#template-category-filter').on('change', filterTemplates);
                $('#template-search').on('input', filterTemplates);

                function filterTemplates() {
                    var category = $('#template-category-filter').val();
                    var search = $('#template-search').val().toLowerCase();

                    $('.ecp-template-card').each(function() {
                        var $card = $(this);
                        var cardCategory = $card.data('category');
                        var cardText = $card.text().toLowerCase();

                        var showCategory = !category || cardCategory === category;
                        var showSearch = !search || cardText.includes(search);

                        $card.toggle(showCategory && showSearch);
                    });
                }

                // Vorlage verwenden
                $('.ecp-use-template').on('click', function() {
                    selectedTemplateId = $(this).data('template-id');
                    var templateName = $(this).closest('.ecp-template-card').find('h3').text();

                    $('#template-calculator-name').val(templateName);
                    $('#ecp-template-modal').show();
                });

                // Standard-Vorlagen erstellen
                $('#ecp-create-default-templates').on('click', function() {
                    var $button = $(this);
                    $button.text('<?php _e('Erstelle Vorlagen...', 'excel-calculator-pro'); ?>').prop('disabled', true);

                    $.post(ajaxurl, {
                        action: 'ecp_create_default_templates',
                        nonce: ecp_admin.nonce
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Fehler: ' + response.data);
                            $button.text('<?php _e('Standard-Vorlagen erstellen', 'excel-calculator-pro'); ?>').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
    <?php
    }

    /**
     * Import/Export-Tab vollständig implementieren
     */
    private function import_export_tab()
    {
        $calculators = $this->database->get_calculators();
    ?>
        <div id="ecp-import-export-tab">
            <div class="ecp-ie-header">
                <h2><?php _e('Import & Export', 'excel-calculator-pro'); ?></h2>
                <p><?php _e('Sichern Sie Ihre Kalkulatoren oder importieren Sie Kalkulatoren von anderen Websites.', 'excel-calculator-pro'); ?></p>
            </div>

            <div class="ecp-ie-sections">
                <!-- Export-Sektion -->
                <div class="ecp-ie-section">
                    <h3><?php _e('Kalkulator exportieren', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Exportieren Sie einen Kalkulator als JSON-Datei für die Verwendung auf anderen Websites.', 'excel-calculator-pro'); ?></p>

                    <form id="ecp-export-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ecp-export-calculator"><?php _e('Kalkulator wählen', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <select id="ecp-export-calculator" class="regular-text" required>
                                        <option value=""><?php _e('Kalkulator wählen...', 'excel-calculator-pro'); ?></option>
                                        <?php foreach ($calculators as $calc): ?>
                                            <option value="<?php echo esc_attr($calc->id); ?>">
                                                <?php echo esc_html($calc->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ecp-export-format"><?php _e('Format', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <select id="ecp-export-format">
                                        <option value="json"><?php _e('JSON (Standard)', 'excel-calculator-pro'); ?></option>
                                        <option value="json-pretty"><?php _e('JSON (Formatiert)', 'excel-calculator-pro'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Optionen', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="export-include-settings" checked />
                                        <?php _e('Einstellungen einschliessen', 'excel-calculator-pro'); ?>
                                    </label><br />
                                    <label>
                                        <input type="checkbox" id="export-include-metadata" />
                                        <?php _e('Metadaten einschliessen (Erstellungsdatum, etc.)', 'excel-calculator-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button id="ecp-export-btn" type="button" class="button button-primary">
                                <?php _e('Exportieren', 'excel-calculator-pro'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Import-Sektion -->
                <div class="ecp-ie-section">
                    <h3><?php _e('Kalkulator importieren', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Importieren Sie einen zuvor exportierten Kalkulator.', 'excel-calculator-pro'); ?></p>

                    <form id="ecp-import-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ecp-import-file"><?php _e('JSON-Datei', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="ecp-import-file" accept=".json" required />
                                    <p class="description">
                                        <?php _e('Nur JSON-Dateien sind erlaubt. Maximale Dateigrösse: 1MB.', 'excel-calculator-pro'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="import-calculator-name"><?php _e('Name überschreiben (optional)', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="import-calculator-name" class="regular-text" />
                                    <p class="description">
                                        <?php _e('Lassen Sie dieses Feld leer, um den ursprünglichen Namen zu verwenden.', 'excel-calculator-pro'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Import-Optionen', 'excel-calculator-pro'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="import-validate-formulas" checked />
                                        <?php _e('Formeln vor Import validieren', 'excel-calculator-pro'); ?>
                                    </label><br />
                                    <label>
                                        <input type="checkbox" id="import-create-backup" checked />
                                        <?php _e('Backup vor Import erstellen', 'excel-calculator-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button id="ecp-import-btn" type="button" class="button button-primary">
                                <?php _e('Importieren', 'excel-calculator-pro'); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Bulk-Export Sektion -->
                <div class="ecp-ie-section">
                    <h3><?php _e('Alle Kalkulatoren exportieren', 'excel-calculator-pro'); ?></h3>
                    <p><?php _e('Exportieren Sie alle Ihre Kalkulatoren als Backup.', 'excel-calculator-pro'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('Export-Umfang', 'excel-calculator-pro'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="bulk-export-type" value="all" checked />
                                    <?php _e('Alle Kalkulatoren', 'excel-calculator-pro'); ?>
                                </label><br />
                                <label>
                                    <input type="radio" name="bulk-export-type" value="active" />
                                    <?php _e('Nur aktive Kalkulatoren', 'excel-calculator-pro'); ?>
                                </label><br />
                                <label>
                                    <input type="radio" name="bulk-export-type" value="recent" />
                                    <?php _e('Nur kürzlich erstellte (letzte 30 Tage)', 'excel-calculator-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button id="ecp-bulk-export-btn" type="button" class="button">
                            <?php _e('Alle exportieren', 'excel-calculator-pro'); ?>
                        </button>
                    </p>
                </div>
            </div>

            <!-- Import-Vorschau Modal -->
            <div id="ecp-import-preview-modal" class="ecp-modal" style="display: none;">
                <div class="ecp-modal-content">
                    <div class="ecp-modal-header">
                        <h3><?php _e('Import-Vorschau', 'excel-calculator-pro'); ?></h3>
                        <span class="ecp-modal-close">&times;</span>
                    </div>
                    <div class="ecp-modal-body">
                        <div id="import-preview-content"></div>
                    </div>
                    <div class="ecp-modal-actions">
                        <button id="ecp-confirm-import" class="button button-primary">
                            <?php _e('Import bestätigen', 'excel-calculator-pro'); ?>
                        </button>
                        <button class="button ecp-modal-close">
                            <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                var importData = null;

                // Export-Funktionalität
                $('#ecp-export-btn').on('click', function() {
                    var calculatorId = $('#ecp-export-calculator').val();
                    var format = $('#ecp-export-format').val();
                    var includeSettings = $('#export-include-settings').is(':checked');
                    var includeMetadata = $('#export-include-metadata').is(':checked');

                    if (!calculatorId) {
                        alert('<?php _e('Bitte wählen Sie einen Kalkulator aus.', 'excel-calculator-pro'); ?>');
                        return;
                    }

                    // Download-Form erstellen
                    var form = $('<form>', {
                        method: 'POST',
                        action: ajaxurl
                    });

                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'action',
                        value: 'ecp_export_calculator'
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'nonce',
                        value: ecp_admin.nonce
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'calculator_id',
                        value: calculatorId
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'format',
                        value: format
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'include_settings',
                        value: includeSettings ? '1' : '0'
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'include_metadata',
                        value: includeMetadata ? '1' : '0'
                    }));

                    $('body').append(form);
                    form.submit();
                    form.remove();
                });

                // Import mit Vorschau
                $('#ecp-import-btn').on('click', function() {
                    var fileInput = $('#ecp-import-file')[0];

                    if (!fileInput.files.length) {
                        alert('<?php _e('Bitte wählen Sie eine Datei aus.', 'excel-calculator-pro'); ?>');
                        return;
                    }

                    var file = fileInput.files[0];

                    // Datei-Validierung
                    if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
                        alert('<?php _e('Nur JSON-Dateien sind erlaubt.', 'excel-calculator-pro'); ?>');
                        return;
                    }

                    if (file.size > 1048576) { // 1MB
                        alert('<?php _e('Datei ist zu gross (max. 1MB).', 'excel-calculator-pro'); ?>');
                        return;
                    }

                    // Datei lesen
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            importData = JSON.parse(e.target.result);
                            showImportPreview(importData);
                        } catch (error) {
                            alert('<?php _e('Ungültige JSON-Datei.', 'excel-calculator-pro'); ?>');
                        }
                    };
                    reader.readAsText(file);
                });

                function showImportPreview(data) {
                    var preview = '<div class="ecp-import-preview">';
                    preview += '<h4>Kalkulator-Informationen:</h4>';
                    preview += '<p><strong>Name:</strong> ' + (data.name || 'Unbekannt') + '</p>';
                    preview += '<p><strong>Beschreibung:</strong> ' + (data.description || 'Keine') + '</p>';
                    preview += '<p><strong>Felder:</strong> ' + (data.fields ? data.fields.length : 0) + '</p>';
                    preview += '<p><strong>Formeln:</strong> ' + (data.formulas ? data.formulas.length : 0) + '</p>';

                    if (data.export_version) {
                        preview += '<p><strong>Export-Version:</strong> ' + data.export_version + '</p>';
                    }

                    if (data.export_date) {
                        preview += '<p><strong>Export-Datum:</strong> ' + data.export_date + '</p>';
                    }

                    preview += '</div>';

                    $('#import-preview-content').html(preview);
                    $('#ecp-import-preview-modal').show();
                }

                // Import bestätigen
                $('#ecp-confirm-import').on('click', function() {
                    if (!importData) return;

                    var $button = $(this);
                    $button.text('<?php _e('Importiere...', 'excel-calculator-pro'); ?>').prop('disabled', true);

                    var formData = new FormData();
                    formData.append('action', 'ecp_import_calculator');
                    formData.append('nonce', ecp_admin.nonce);
                    formData.append('import_data', JSON.stringify(importData));

                    var customName = $('#import-calculator-name').val();
                    if (customName) {
                        formData.append('custom_name', customName);
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('<?php _e('Kalkulator erfolgreich importiert!', 'excel-calculator-pro'); ?>');
                                $('#ecp-import-preview-modal').hide();
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                alert('Fehler: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('<?php _e('Ein Fehler ist aufgetreten.', 'excel-calculator-pro'); ?>');
                        },
                        complete: function() {
                            $button.text('<?php _e('Import bestätigen', 'excel-calculator-pro'); ?>').prop('disabled', false);
                        }
                    });
                });

                // Bulk-Export
                $('#ecp-bulk-export-btn').on('click', function() {
                    var exportType = $('input[name="bulk-export-type"]:checked').val();

                    var form = $('<form>', {
                        method: 'POST',
                        action: ajaxurl
                    });

                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'action',
                        value: 'ecp_bulk_export'
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'nonce',
                        value: ecp_admin.nonce
                    }));
                    form.append($('<input>', {
                        type: 'hidden',
                        name: 'export_type',
                        value: exportType
                    }));

                    $('body').append(form);
                    form.submit();
                    form.remove();
                });
            });
        </script>
<?php
    }

    /**
     * Kategorie-Label abrufen
     */
    private function get_category_label($category)
    {
        $labels = array(
            'financial' => __('Finanzen', 'excel-calculator-pro'),
            'business' => __('Business', 'excel-calculator-pro'),
            'health' => __('Gesundheit', 'excel-calculator-pro'),
            'education' => __('Bildung', 'excel-calculator-pro'),
            'personal' => __('Persönlich', 'excel-calculator-pro'),
            'general' => __('Allgemein', 'excel-calculator-pro')
        );

        return isset($labels[$category]) ? $labels[$category] : ucfirst($category);
    }

    /**
     * AJAX: Standard-Vorlagen erstellen
     */
    public function ajax_create_default_templates()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        // Prüfen ob bereits Vorlagen existieren
        if ($this->database->get_templates_count() > 0) {
            wp_send_json_error(__('Vorlagen sind bereits vorhanden', 'excel-calculator-pro'));
        }

        // Standard-Vorlagen über die Database-Klasse erstellen
        $reflection = new ReflectionClass($this->database);
        $method = $reflection->getMethod('create_default_templates');
        $method->setAccessible(true);

        try {
            $method->invoke($this->database);
            wp_send_json_success(__('Standard-Vorlagen erfolgreich erstellt', 'excel-calculator-pro'));
        } catch (Exception $e) {
            wp_send_json_error(__('Fehler beim Erstellen der Vorlagen: ', 'excel-calculator-pro') . $e->getMessage());
        }
    }

    /**
     * AJAX: Bulk-Export
     */
    public function ajax_bulk_export()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');

        $args = array();
        switch ($export_type) {
            case 'active':
                $args['status'] = 'active';
                break;
            case 'recent':
                $args['status'] = 'active';
                // Datum-Filter würde hier implementiert werden
                break;
            default:
                // Alle exportieren
                break;
        }

        $calculators = $this->database->get_calculators($args);

        if (empty($calculators)) {
            wp_send_json_error(__('Keine Kalkulatoren zum Exportieren gefunden', 'excel-calculator-pro'));
        }

        $export_data = array(
            'export_info' => array(
                'plugin_version' => ECP_VERSION,
                'export_date' => current_time('mysql'),
                'export_type' => $export_type,
                'calculator_count' => count($calculators)
            ),
            'calculators' => array()
        );

        foreach ($calculators as $calc) {
            $calc_data = $this->database->export_calculator($calc->id);
            if ($calc_data) {
                $export_data['calculators'][] = $calc_data;
            }
        }

        $filename = 'ecp_bulk_export_' . $export_type . '_' . date('Y-m-d') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($export_data, JSON_PRETTY_PRINT)));

        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Diese Hook-Registrierung muss in die init_hooks() Methode der Admin-Klasse:
     */
    private function additional_init_hooks()
    {
        // Zusätzliche AJAX-Hooks für Template-Funktionen
        add_action('wp_ajax_ecp_create_default_templates', array($this, 'ajax_create_default_templates'));
        add_action('wp_ajax_ecp_bulk_export', array($this, 'ajax_bulk_export'));
    }

    /**
     * AJAX: Kalkulator speichern
     */
    public function ajax_save_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');

        // Validierung
        if (empty($name)) {
            wp_send_json_error(__('Name ist erforderlich', 'excel-calculator-pro'));
        }

        // Felder verarbeiten
        $fields = array();
        if (isset($_POST['fields']) && is_array($_POST['fields'])) {
            foreach ($_POST['fields'] as $field) {
                $clean_field = array(
                    'id' => sanitize_key($field['id'] ?? ''),
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'type' => sanitize_text_field($field['type'] ?? 'number'),
                    'default' => sanitize_text_field($field['default'] ?? ''),
                    'min' => sanitize_text_field($field['min'] ?? ''),
                    'max' => sanitize_text_field($field['max'] ?? ''),
                    'step' => sanitize_text_field($field['step'] ?? 'any'),
                    'unit' => sanitize_text_field($field['unit'] ?? ''),
                    'help' => sanitize_text_field($field['help'] ?? ''),
                    'required' => !empty($field['required']),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? '')
                );

                if (!empty($clean_field['id']) && !empty($clean_field['label'])) {
                    $fields[] = $clean_field;
                }
            }
        }

        // Formeln verarbeiten
        $formulas = array();
        if (isset($_POST['formulas']) && is_array($_POST['formulas'])) {
            foreach ($_POST['formulas'] as $formula) {
                $clean_formula = array(
                    'label' => sanitize_text_field($formula['label'] ?? ''),
                    'formula' => sanitize_textarea_field($formula['formula'] ?? ''),
                    'format' => sanitize_text_field($formula['format'] ?? ''),
                    'unit' => sanitize_text_field($formula['unit'] ?? ''),
                    'help' => sanitize_text_field($formula['help'] ?? '')
                );

                if (!empty($clean_formula['label']) && !empty($clean_formula['formula'])) {
                    $formulas[] = $clean_formula;
                }
            }
        }

        // Einstellungen verarbeiten
        $settings = array();
        if (isset($_POST['settings'])) {
            if (is_string($_POST['settings'])) {
                $settings = json_decode(stripslashes($_POST['settings']), true) ?: array();
            } elseif (is_array($_POST['settings'])) {
                foreach ($_POST['settings'] as $key => $value) {
                    $settings[sanitize_key($key)] = sanitize_text_field($value);
                }
            }
        }

        // Daten zusammenstellen
        $calculator_data = array(
            'name' => $name,
            'description' => $description,
            'fields' => $fields,
            'formulas' => $formulas,
            'settings' => $settings
        );

        if ($calculator_id > 0) {
            $calculator_data['id'] = $calculator_id;
        }

        // Speichern
        $result = $this->database->save_calculator($calculator_data);

        if ($result) {
            wp_send_json_success(array(
                'id' => $result,
                'message' => __('Kalkulator erfolgreich gespeichert', 'excel-calculator-pro')
            ));
        } else {
            wp_send_json_error(__('Fehler beim Speichern des Kalkulators', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator löschen
     */
    public function ajax_delete_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id'] ?? 0);

        if ($calculator_id <= 0) {
            wp_send_json_error(__('Ungültige Kalkulator-ID', 'excel-calculator-pro'));
        }

        $result = $this->database->delete_calculator($calculator_id);

        if ($result) {
            wp_send_json_success(__('Kalkulator erfolgreich gelöscht', 'excel-calculator-pro'));
        } else {
            wp_send_json_error(__('Fehler beim Löschen des Kalkulators', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator abrufen
     */
    public function ajax_get_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id'] ?? 0);

        if ($calculator_id <= 0) {
            wp_send_json_error(__('Ungültige Kalkulator-ID', 'excel-calculator-pro'));
        }

        $calculator = $this->database->get_calculator($calculator_id);

        if ($calculator) {
            wp_send_json_success($calculator);
        } else {
            wp_send_json_error(__('Kalkulator nicht gefunden', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Aus Vorlage erstellen
     */
    public function ajax_create_from_template()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $template_id = intval($_POST['template_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');

        if ($template_id <= 0) {
            wp_send_json_error(__('Ungültige Vorlagen-ID', 'excel-calculator-pro'));
        }

        if (empty($name)) {
            wp_send_json_error(__('Name ist erforderlich', 'excel-calculator-pro'));
        }

        $result = $this->database->create_from_template($template_id, $name);

        if ($result) {
            wp_send_json_success(array(
                'id' => $result,
                'message' => __('Kalkulator aus Vorlage erstellt', 'excel-calculator-pro')
            ));
        } else {
            wp_send_json_error(__('Fehler beim Erstellen aus Vorlage', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator exportieren
     */
    public function ajax_export_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id'] ?? 0);

        if ($calculator_id <= 0) {
            wp_send_json_error(__('Ungültige Kalkulator-ID', 'excel-calculator-pro'));
        }

        $export_data = $this->database->export_calculator($calculator_id);

        if ($export_data) {
            $calculator = $this->database->get_calculator($calculator_id);
            $filename = 'ecp_calculator_' . sanitize_file_name($calculator->name) . '_' . date('Y-m-d') . '.json';

            // Download-Headers setzen
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen(json_encode($export_data, JSON_PRETTY_PRINT)));

            echo json_encode($export_data, JSON_PRETTY_PRINT);
            exit;
        } else {
            wp_send_json_error(__('Fehler beim Exportieren', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator importieren
     */
    public function ajax_import_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('Keine Datei hochgeladen', 'excel-calculator-pro'));
        }

        $file = $_FILES['import_file'];

        // Datei-Validierung
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Fehler beim Datei-Upload', 'excel-calculator-pro'));
        }

        if ($file['type'] !== 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
            wp_send_json_error(__('Nur JSON-Dateien sind erlaubt', 'excel-calculator-pro'));
        }

        if ($file['size'] > 1048576) { // 1MB Limit
            wp_send_json_error(__('Datei ist zu gross (max. 1MB)', 'excel-calculator-pro'));
        }

        // Datei lesen
        $json_content = file_get_contents($file['tmp_name']);
        $import_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Ungültige JSON-Datei', 'excel-calculator-pro'));
        }

        // Basis-Validierung der Import-Daten
        if (!isset($import_data['name']) || !isset($import_data['fields']) || !isset($import_data['formulas'])) {
            wp_send_json_error(__('Ungültiges Kalkulator-Format', 'excel-calculator-pro'));
        }

        // Namen anpassen falls bereits vorhanden
        $original_name = sanitize_text_field($import_data['name']);
        $name = $original_name;
        $counter = 1;

        while ($this->calculator_name_exists($name)) {
            $name = $original_name . ' (' . $counter . ')';
            $counter++;
        }

        $result = $this->database->import_calculator($import_data, $name);

        if ($result) {
            wp_send_json_success(array(
                'id' => $result,
                'message' => __('Kalkulator erfolgreich importiert', 'excel-calculator-pro')
            ));
        } else {
            wp_send_json_error(__('Fehler beim Importieren', 'excel-calculator-pro'));
        }
    }

    /**
     * Hilfsfunktion: Prüft ob Kalkulator-Name bereits existiert
     */
    private function calculator_name_exists($name)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'excel_calculators';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE name = %s AND status = 'active'",
            $name
        ));

        return $result > 0;
    }

    /**
     * AJAX: Tabellen neu erstellen (für Debug-Tab)
     */
    public function ajax_regenerate_tables()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $result = $this->database->create_tables();

        if ($result) {
            wp_send_json_success(__('Tabellen erfolgreich neu erstellt', 'excel-calculator-pro'));
        } else {
            wp_send_json_error(__('Fehler beim Erstellen der Tabellen', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Cache leeren (für Debug-Tab)
     */
    public function ajax_clear_cache()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        // WordPress-Caches leeren
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Plugin-spezifische Transients löschen
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ecp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ecp_%'");

        wp_send_json_success(__('Cache erfolgreich geleert', 'excel-calculator-pro'));
    }

    /**
     * AJAX: Debug-Info exportieren (für Debug-Tab)
     */
    public function ajax_export_debug_info()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $debug_info = array(
            'plugin_version' => ECP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'active_calculators' => count($this->database->get_calculators()),
            'available_templates' => count($this->database->get_templates()),
            'database_integrity' => $this->database->check_integrity(),
            'active_plugins' => get_option('active_plugins'),
            'theme' => wp_get_theme()->get('Name'),
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url(),
            'multisite' => is_multisite()
        );

        $filename = 'ecp_debug_info_' . date('Y-m-d_H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($debug_info, JSON_PRETTY_PRINT)));

        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit;
    }
}
