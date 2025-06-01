<?php

/**
 * Erweiterte Frontend Handler für Excel Calculator Pro mit Custom Styling
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Frontend_Enhanced
{

    private $database;

    public function __construct($database = null)
    {
        $this->database = $database;
        $this->init_hooks();
    }

    private function get_database()
    {
        if (!$this->database) {
            $this->database = new ECP_Database();
        }
        return $this->database;
    }

    /**
     * Frontend-Hooks initialisieren
     */
    private function init_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_custom_styles'));
        add_action('wp_footer', array($this, 'add_inline_scripts'));

        // AJAX-Hooks für Frontend (falls benötigt)
        add_action('wp_ajax_ecp_calculate', array($this, 'ajax_calculate'));
        add_action('wp_ajax_nopriv_ecp_calculate', array($this, 'ajax_calculate'));
    }

    /**
     * Frontend-Scripts einbinden
     */
    public function enqueue_scripts()
    {
        // Hauptscript (mit Bugfixes)
        wp_enqueue_script(
            'ecp-frontend-js',
            ECP_PLUGIN_URL . 'assets/frontend.js',
            array('jquery'),
            ECP_VERSION,
            true
        );

        // Anpassbares CSS einbinden
        wp_enqueue_style(
            'ecp-frontend-customizable-css',
            ECP_PLUGIN_URL . 'assets/frontend-customizable.css',
            array(),
            ECP_VERSION
        );

        // Standard CSS als Fallback
        wp_enqueue_style(
            'ecp-frontend-css',
            ECP_PLUGIN_URL . 'assets/frontend.css',
            array(),
            ECP_VERSION
        );

        // Frontend-Lokalisierung
        wp_localize_script('ecp-frontend-js', 'ecp_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_frontend_nonce'),
            'settings' => $this->get_frontend_settings(),
            'debug_mode' => $this->is_debug_mode()
        ));
    }

    /**
     * Benutzerdefinierte Styles hinzufügen
     */
    public function add_custom_styles()
    {
        $style_settings = get_option('ecp_style_settings', array());

        if (empty($style_settings)) {
            return;
        }

        $css = $this->generate_custom_css($style_settings);

        if (!empty($css)) {
            echo "\n<!-- Excel Calculator Pro Custom Styles -->\n";
            echo "<style id=\"ecp-custom-styles\">\n";
            echo $css;
            echo "\n</style>\n";
        }
    }

    /**
     * Benutzerdefiniertes CSS generieren
     */
    private function generate_custom_css($settings)
    {
        if (empty($settings)) {
            return '';
        }

        $css = "/* Excel Calculator Pro - Benutzerdefinierte Styles */\n";

        // CSS-Variablen überschreiben
        $css .= ".ecp-calculator {\n";

        if (!empty($settings['primary_color'])) {
            $css .= "    --ecp-primary-color: {$settings['primary_color']};\n";
            $css .= "    --ecp-input-focus: {$settings['primary_color']};\n";
            $css .= "    --ecp-output-border: {$settings['primary_color']};\n";
            $css .= "    --ecp-output-color: {$settings['primary_color']};\n";
        }

        if (!empty($settings['secondary_color'])) {
            $css .= "    --ecp-secondary-color: {$settings['secondary_color']};\n";
        }

        if (!empty($settings['background_color'])) {
            $css .= "    --ecp-background-color: {$settings['background_color']};\n";
        }

        if (!empty($settings['text_color'])) {
            $css .= "    --ecp-text-color: {$settings['text_color']};\n";
        }

        if (!empty($settings['max_width'])) {
            $css .= "    --ecp-max-width: {$settings['max_width']};\n";
        }

        if (!empty($settings['border_radius'])) {
            $css .= "    --ecp-border-radius: {$settings['border_radius']}px;\n";
        }

        if (!empty($settings['font_size'])) {
            $css .= "    --ecp-font-size-base: {$settings['font_size']}px;\n";
        }

        // Schatten-Optionen
        if (!empty($settings['shadow_style'])) {
            $shadows = array(
                'none' => 'none',
                'subtle' => '0 1px 3px rgba(0,0,0,0.05)',
                'medium' => '0 2px 10px rgba(0,0,0,0.08)',
                'strong' => '0 4px 20px rgba(0,0,0,0.15)'
            );
            if (isset($shadows[$settings['shadow_style']])) {
                $css .= "    --ecp-shadow: {$shadows[$settings['shadow_style']]};\n";
            }
        }

        // Font Family
        if (!empty($settings['font_family'])) {
            $fonts = array(
                'serif' => 'Georgia, "Times New Roman", Times, serif',
                'monospace' => '"Courier New", Courier, monospace',
                'custom' => $settings['custom_font'] ?? ''
            );

            if ($settings['font_family'] === 'custom' && !empty($settings['custom_font'])) {
                $css .= "    --ecp-font-family: {$settings['custom_font']};\n";
            } elseif (isset($fonts[$settings['font_family']])) {
                $css .= "    --ecp-font-family: {$fonts[$settings['font_family']]};\n";
            }
        }

        $css .= "}\n\n";

        // Grössen-Variante
        if (!empty($settings['size_variant'])) {
            $css .= ".ecp-calculator {\n";

            if ($settings['size_variant'] === 'compact') {
                $css .= "    --ecp-spacing-small: 6px;\n";
                $css .= "    --ecp-spacing-medium: 12px;\n";
                $css .= "    --ecp-spacing-large: 18px;\n";
                $css .= "    --ecp-input-padding: 8px 12px;\n";
                $css .= "    --ecp-input-font-size: 14px;\n";
                $css .= "    --ecp-output-font-size: 16px;\n";
            } elseif ($settings['size_variant'] === 'large') {
                $css .= "    --ecp-spacing-small: 15px;\n";
                $css .= "    --ecp-spacing-medium: 30px;\n";
                $css .= "    --ecp-spacing-large: 45px;\n";
                $css .= "    --ecp-input-padding: 16px 20px;\n";
                $css .= "    --ecp-input-font-size: 18px;\n";
                $css .= "    --ecp-output-font-size: 22px;\n";
            }

            $css .= "}\n\n";
        }

        return $css;
    }

    /**
     * Frontend-Einstellungen abrufen
     */
    private function get_frontend_settings()
    {
        $settings = get_option('ecp_general_settings', array());

        return array(
            'currency' => isset($settings['default_currency']) ? $settings['default_currency'] : 'CHF',
            'number_format' => isset($settings['number_format']) ? $settings['number_format'] : 'de_CH',
            'currency_symbols' => array(
                'CHF' => 'CHF ',
                'EUR' => '€ ',
                'USD' => '$ ',
                'GBP' => '£ ',
                'JPY' => '¥ '
            ),
            'debug_enabled' => $this->is_debug_mode()
        );
    }

    /**
     * Debug-Modus prüfen
     */
    private function is_debug_mode()
    {
        return (defined('WP_DEBUG') && WP_DEBUG) ||
            isset($_GET['ecp_debug']) ||
            (current_user_can('manage_options') && isset($_GET['debug']));
    }

    /**
     * Verbesserte Inline-Scripts
     */
    public function add_inline_scripts()
    {
        $debug_mode = $this->is_debug_mode();
?>
        <script id="ecp-enhanced-scripts">
            (function($) {
                'use strict';

                // Erweiterte Initialisierung
                $(document).ready(function() {
                    // Debug-Modus Info
                    <?php if ($debug_mode): ?>
                        console.log('Excel Calculator Pro: Debug-Modus aktiv');
                        console.log('Frontend-Settings:', ecp_frontend.settings);

                        // Debug-Button hinzufügen
                        if (window.location.hash === '#debug' || ecp_frontend.settings.debug_enabled) {
                            $('body').append('<div id="ecp-debug-panel" style="position: fixed; top: 10px; right: 10px; background: #000; color: #0f0; padding: 10px; border-radius: 5px; z-index: 10000; font-family: monospace; font-size: 12px;">' +
                                '<div><strong>ECP Debug Panel</strong></div>' +
                                '<button onclick="window.ECPCalculator.test.simpleCalculation()" style="margin: 5px; padding: 5px;">Test 12+8</button>' +
                                '<button onclick="window.ECPCalculator.test.complexCalculation()" style="margin: 5px; padding: 5px;">Test Komplex</button>' +
                                '<button onclick="window.ECPCalculator.enableDebugMode()" style="margin: 5px; padding: 5px;">Debug Alle</button>' +
                                '<button onclick="$(\'#ecp-debug-panel\').remove()" style="margin: 5px; padding: 5px;">Schliessen</button>' +
                                '</div>');
                        }
                    <?php endif; ?>

                    // Kalkulatoren mit verbesserter Fehlerbehandlung initialisieren
                    $('.ecp-calculator').each(function() {
                        const $calc = $(this);

                        try {
                            $calc.ecpCalculator();

                            <?php if ($debug_mode): ?>
                                console.log('Kalkulator initialisiert:', $calc.data('calculator-id'));
                            <?php endif; ?>

                        } catch (error) {
                            console.error('Fehler beim Initialisieren des Kalkulators:', error);

                            // Fehlermeldung für Benutzer anzeigen
                            $calc.prepend('<div class="ecp-error-notice" style="background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #f5c6cb;">' +
                                '<strong>Fehler:</strong> Dieser Kalkulator konnte nicht geladen werden. ' +
                                <?php if ($debug_mode): ?> 'Fehlerdetails in der Browser-Konsole (F12).' +
                                <?php else: ?> 'Bitte kontaktieren Sie den Administrator.' +
                                <?php endif; ?> '</div>');
                        }
                    });

                    // Performance-Monitoring
                    if (ecp_frontend.settings.debug_enabled) {
                        const calculatorCount = $('.ecp-calculator').length;
                        if (calculatorCount > 3) {
                            console.warn('Excel Calculator Pro: Viele Kalkulatoren auf einer Seite (' + calculatorCount + '). Performance könnte beeinträchtigt sein.');
                        }
                    }

                    // Accessibility-Verbesserungen
                    $('.ecp-calculator').each(function() {
                        const $calc = $(this);

                        // Hauptcontainer als Anwendung markieren
                        $calc.attr('role', 'application').attr('aria-label', 'Kalkulator');

                        // Tastatur-Navigation verbessern
                        $calc.find('.ecp-input-field').each(function(index) {
                            $(this).attr('tabindex', index + 1);
                        });
                    });

                    // Auto-Update für alle Kalkulatoren bei Änderungen
                    $(document).on('ecp_value_changed', function(e, calculatorId, fieldId, newValue) {
                        <?php if ($debug_mode): ?>
                            console.log('Wert geändert:', calculatorId, fieldId, newValue);
                        <?php endif; ?>
                    });
                });

                // Globale Fehlerbehandlung für ECP-bezogene Fehler
                window.addEventListener('error', function(e) {
                    if (e.message && e.message.toLowerCase().includes('ecp')) {
                        console.error('Excel Calculator Pro Fehler:', e);

                        <?php if ($debug_mode): ?>
                            // Debug-Info sammeln
                            const debugInfo = {
                                message: e.message,
                                filename: e.filename,
                                lineno: e.lineno,
                                timestamp: new Date().toISOString(),
                                userAgent: navigator.userAgent,
                                calculators: $('.ecp-calculator').length
                            };
                            console.log('Debug-Info:', debugInfo);
                        <?php endif; ?>
                    }
                });

                // Erweiterte API-Funktionen
                if (window.ECPCalculator) {
                    // Massen-Updates
                    window.ECPCalculator.updateAllCalculators = function(updates) {
                        $('.ecp-calculator').each(function() {
                            const instance = $.data(this, 'ecpCalculator');
                            if (instance && updates) {
                                for (let fieldId in updates) {
                                    instance.setValue(fieldId, updates[fieldId]);
                                }
                            }
                        });
                    };

                    // Alle Ergebnisse sammeln
                    window.ECPCalculator.getAllResults = function() {
                        const results = {};
                        $('.ecp-calculator').each(function() {
                            const instance = $.data(this, 'ecpCalculator');
                            const calcId = $(this).data('calculator-id');
                            if (instance && calcId) {
                                results[calcId] = instance.getResults();
                            }
                        });
                        return results;
                    };

                    // Performance-Monitor
                    window.ECPCalculator.getPerformanceStats = function() {
                        return {
                            calculators: $('.ecp-calculator').length,
                            activeInstances: $('.ecp-calculator').filter(function() {
                                return $.data(this, 'ecpCalculator');
                            }).length,
                            memoryUsage: performance.memory ? {
                                used: Math.round(performance.memory.usedJSHeapSize / 1048576) + ' MB',
                                total: Math.round(performance.memory.totalJSHeapSize / 1048576) + ' MB'
                            } : 'Nicht verfügbar'
                        };
                    };

                    <?php if ($debug_mode): ?>
                        // Debug-Helper
                        window.ECPCalculator.debugAll = function() {
                            $('.ecp-calculator').each(function() {
                                const instance = $.data(this, 'ecpCalculator');
                                if (instance && instance.enableDebug) {
                                    instance.enableDebug();
                                }
                            });
                            console.log('Debug-Modus für alle Kalkulatoren aktiviert');
                        };

                        window.ECPCalculator.validateAllCalculations = function() {
                            console.log('Validiere alle Berechnungen...');
                            const tests = [{
                                    formula: '12 + 8',
                                    expected: 20,
                                    values: {}
                                },
                                {
                                    formula: '(100 * 5) / 2 + 50',
                                    expected: 300,
                                    values: {}
                                },
                                {
                                    formula: 'a + b',
                                    expected: 15,
                                    values: {
                                        a: 7,
                                        b: 8
                                    }
                                }
                            ];

                            tests.forEach((test, index) => {
                                try {
                                    const result = window.ECPCalculator.parseFormula(test.formula, test.values, true);
                                    const passed = Math.abs(result - test.expected) < 0.001;
                                    console.log(`Test ${index + 1}: ${test.formula} = ${result} (erwartet: ${test.expected}) ${passed ? '✅' : '❌'}`);
                                } catch (error) {
                                    console.error(`Test ${index + 1} Fehler:`, error);
                                }
                            });
                        };
                    <?php endif; ?>
                }

            })(jQuery);
        </script>
<?php
    }

    /**
     * AJAX: Kalkulation durchführen (Server-seitig falls nötig)
     */
    public function ajax_calculate()
    {
        check_ajax_referer('ecp_frontend_nonce', 'nonce');

        $calculator_id = intval($_POST['calculator_id']);
        $values = $_POST['values'] ?? array();

        $calculator = $this->get_database()->get_calculator($calculator_id);

        if (!$calculator) {
            wp_send_json_error(__('Kalkulator nicht gefunden', 'excel-calculator-pro'));
        }

        // Server-seitige Berechnungen (für komplexe Operationen)
        $results = array();

        foreach ($calculator->formulas as $formula) {
            // Hier könnten Server-seitige Berechnungen durchgeführt werden
            $results[] = array(
                'label' => $formula['label'],
                'value' => 0 // Placeholder - wird normalerweise im Frontend berechnet
            );
        }

        wp_send_json_success($results);
    }

    /**
     * Styles für verschiedene Themes hinzufügen
     */
    public function add_theme_styles()
    {
        $style_settings = get_option('ecp_style_settings', array());
        $preset_theme = $style_settings['preset_theme'] ?? '';

        if (empty($preset_theme)) {
            return;
        }

        echo "\n<!-- ECP Theme: {$preset_theme} -->\n";
        echo "<style id=\"ecp-theme-{$preset_theme}\">\n";

        switch ($preset_theme) {
            case 'subtle':
                echo $this->get_subtle_theme_css();
                break;
            case 'warm':
                echo $this->get_warm_theme_css();
                break;
            case 'nature':
                echo $this->get_nature_theme_css();
                break;
            case 'elegant':
                echo $this->get_elegant_theme_css();
                break;
            case 'minimal':
                echo $this->get_minimal_theme_css();
                break;
        }

        echo "\n</style>\n";
    }

    /**
     * Theme CSS-Methoden
     */
    private function get_subtle_theme_css()
    {
        return "
        .ecp-calculator {
            --ecp-primary-color: #495057;
            --ecp-secondary-color: #6c757d;
            --ecp-background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }";
    }

    private function get_warm_theme_css()
    {
        return "
        .ecp-calculator {
            --ecp-primary-color: #fd7e14;
            --ecp-secondary-color: #ff8c00;
            --ecp-background-color: #fff8f5;
            border: 1px solid #fed7aa;
        }";
    }

    private function get_nature_theme_css()
    {
        return "
        .ecp-calculator {
            --ecp-primary-color: #198754;
            --ecp-secondary-color: #20c997;
            --ecp-background-color: #f8fff8;
            border: 1px solid #d1eddb;
        }";
    }

    private function get_elegant_theme_css()
    {
        return "
        .ecp-calculator {
            --ecp-primary-color: #6f42c1;
            --ecp-secondary-color: #8e69d8;
            --ecp-background-color: #faf9ff;
            border: 1px solid #e2d9f3;
        }";
    }

    private function get_minimal_theme_css()
    {
        return "
        .ecp-calculator {
            --ecp-primary-color: #000000;
            --ecp-secondary-color: #333333;
            --ecp-background-color: #ffffff;
            --ecp-border-radius: 4px;
            --ecp-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #eeeeee;
        }";
    }
}
