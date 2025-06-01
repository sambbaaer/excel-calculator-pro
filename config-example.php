<?php
/**
 * Excel Calculator Pro - Konfigurationsbeispiele
 * 
 * Diese Datei zeigt, wie das Plugin konfiguriert und erweitert werden kann.
 * Kopieren Sie die gewünschten Code-Snippets in die functions.php Ihres Themes
 * oder in ein Custom Plugin.
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==========================================================================
 * GRUNDKONFIGURATION
 * ==========================================================================
 */

/**
 * Plugin-Einstellungen überschreiben
 */
add_filter('ecp_default_settings', function($settings) {
    return array_merge($settings, array(
        'default_currency' => 'CHF',
        'number_format' => 'de_CH',
        'enable_debug' => false,
        'cache_calculations' => true,
        'auto_save_interval' => 30000, // 30 Sekunden
        'max_calculators_per_page' => 3
    ));
});

/**
 * Benutzerdefinierte Währungssymbole
 */
add_filter('ecp_currency_symbols', function($symbols) {
    $symbols['BTC'] = '₿ ';
    $symbols['GBP'] = '£ ';
    $symbols['JPY'] = '¥ ';
    return $symbols;
});

/**
 * Standard-Theme für alle Kalkulatoren setzen
 */
add_filter('ecp_default_theme', function($theme) {
    return 'modern'; // default, compact, modern
});

/**
 * ==========================================================================
 * ERWEITERTE FUNKTIONEN
 * ==========================================================================
 */

/**
 * Benutzerdefinierte Formelfunktionen hinzufügen
 */
add_filter('ecp_custom_functions', function($functions) {
    
    // Benutzerdefinierte Steuerfunktion
    $functions['STEUER'] = function($brutto, $steuersatz) {
        return $brutto * ($steuersatz / 100);
    };
    
    // Schweizer Mehrwertsteuer
    $functions['MWST'] = function($netto, $satz = 7.7) {
        return $netto * ($satz / 100);
    };
    
    // Rabattberechnung
    $functions['RABATT'] = function($preis, $rabatt_prozent) {
        return $preis * (1 - $rabatt_prozent / 100);
    };
    
    // Zinseszins
    $functions['ZINSESZINS'] = function($kapital, $zinssatz, $jahre) {
        return $kapital * pow(1 + $zinssatz / 100, $jahre);
    };
    
    return $functions;
});

/**
 * Benutzerdefinierte Konstanten
 */
add_filter('ecp_custom_constants', function($constants) {
    $constants['MWST_NORMAL'] = 7.7;
    $constants['MWST_REDUZIERT'] = 2.5;
    $constants['MWST_SONDER'] = 3.7;
    $constants['MINDESTLOHN_CH'] = 4000;
    return $constants;
});

/**
 * ==========================================================================
 * VALIDIERUNG UND SICHERHEIT
 * ==========================================================================
 */

/**
 * Benutzerdefinierte Eingabevalidierung
 */
add_filter('ecp_validate_input', function($is_valid, $field_id, $value, $calculator_id) {
    
    // Spezielle Validierung für Kreditrechner
    if ($calculator_id === 1) {
        switch ($field_id) {
            case 'kreditsumme':
                if ($value < 1000 || $value > 1000000) {
                    return new WP_Error('invalid_range', 'Kreditsumme muss zwischen 1\'000 und 1\'000\'000 liegen.');
                }
                break;
                
            case 'zinssatz':
                if ($value < 0.1 || $value > 15) {
                    return new WP_Error('invalid_rate', 'Zinssatz muss zwischen 0.1% und 15% liegen.');
                }
                break;
        }
    }
    
    return $is_valid;
}, 10, 4);

/**
 * Zugriffskontrolle für Kalkulatoren
 */
add_filter('ecp_can_view_calculator', function($can_view, $calculator_id, $user_id) {
    
    // Premium-Kalkulatoren nur für eingeloggte Benutzer
    $premium_calculators = array(5, 6, 7);
    
    if (in_array($calculator_id, $premium_calculators) && !is_user_logged_in()) {
        return false;
    }
    
    // VIP-Kalkulatoren nur für Administratoren
    $vip_calculators = array(10, 11);
    
    if (in_array($calculator_id, $vip_calculators) && !current_user_can('manage_options')) {
        return false;
    }
    
    return $can_view;
}, 10, 3);

/**
 * ==========================================================================
 * DESIGN UND LAYOUT
 * ==========================================================================
 */

/**
 * Benutzerdefinierte CSS-Klassen für Kalkulatoren
 */
add_filter('ecp_calculator_classes', function($classes, $calculator_id, $attributes) {
    
    // Verschiedene Styles je nach Kalkulator
    $calculator_styles = array(
        1 => 'loan-calculator',
        2 => 'roi-calculator', 
        3 => 'bmi-calculator',
        4 => 'tax-calculator'
    );
    
    if (isset($calculator_styles[$calculator_id])) {
        $classes[] = $calculator_styles[$calculator_id];
    }
    
    // Responsive Klassen
    if (wp_is_mobile()) {
        $classes[] = 'ecp-mobile';
    }
    
    return $classes;
}, 10, 3);

/**
 * Benutzerdefinierte Icons für Ausgabefelder
 */
add_filter('ecp_output_icons', function($icons) {
    return array(
        'currency' => '💰',
        'percentage' => '📊',
        'time' => '⏰',
        'weight' => '⚖️',
        'distance' => '📏',
        'temperature' => '🌡️'
    );
});

/**
 * ==========================================================================
 * INTEGRATION UND HOOKS
 * ==========================================================================
 */

/**
 * Nach Berechnung ausgeführte Aktion
 */
add_action('ecp_after_calculation', function($calculator_id, $inputs, $outputs) {
    
    // Statistiken sammeln
    $stats = get_option('ecp_usage_stats', array());
    $stats[$calculator_id] = isset($stats[$calculator_id]) ? $stats[$calculator_id] + 1 : 1;
    update_option('ecp_usage_stats', $stats);
    
    // Bei hohen Beträgen E-Mail an Admin senden
    if ($calculator_id === 1 && isset($outputs['kreditsumme']) && $outputs['kreditsumme'] > 500000) {
        wp_mail(
            get_option('admin_email'),
            'Hohe Kreditsumme berechnet',
            "Ein Benutzer hat eine Kreditsumme von {$outputs['kreditsumme']} berechnet."
        );
    }
    
}, 10, 3);

/**
 * Google Analytics Integration
 */
add_action('ecp_calculation_completed', function($calculator_id, $results) {
    
    if (function_exists('gtag')) {
        // Google Analytics Event senden
        echo "<script>
        gtag('event', 'calculator_used', {
            'event_category': 'Calculator',
            'event_label': 'Calculator ID: {$calculator_id}',
            'value': 1
        });
        </script>";
    }
    
});

/**
 * ==========================================================================
 * ERWEITERTE TEMPLATES
 * ==========================================================================
 */

/**
 * Benutzerdefinierte Kalkulator-Templates
 */
add_action('init', function() {
    
    // Nur einmal ausführen
    if (get_option('ecp_custom_templates_loaded')) {
        return;
    }
    
    $database = ecp_init()->get_database();
    
    // Schweizer Hypothekenrechner
    $database->insert_template(array(
        'name' => 'Schweizer Hypothekenrechner',
        'description' => 'Berechnet Hypothekenzinsen nach Schweizer Standards',
        'category' => 'finance_ch',
        'fields' => json_encode(array(
            array('id' => 'kaufpreis', 'label' => 'Kaufpreis', 'default' => '800000'),
            array('id' => 'eigenkapital', 'label' => 'Eigenkapital', 'default' => '160000'),
            array('id' => 'zinssatz_1', 'label' => 'Zinssatz 1. Hypothek (%)', 'default' => '1.5'),
            array('id' => 'zinssatz_2', 'label' => 'Zinssatz 2. Hypothek (%)', 'default' => '2.5')
        )),
        'formulas' => json_encode(array(
            array(
                'label' => '1. Hypothek (2/3)',
                'formula' => 'MIN((kaufpreis - eigenkapital) * 2/3, kaufpreis * 2/3)',
                'format' => 'currency'
            ),
            array(
                'label' => '2. Hypothek (1/3)',
                'formula' => 'MAX(0, (kaufpreis - eigenkapital) - MIN((kaufpreis - eigenkapital) * 2/3, kaufpreis * 2/3))',
                'format' => 'currency'
            ),
            array(
                'label' => 'Jahreszins 1. Hypothek',
                'formula' => 'MIN((kaufpreis - eigenkapital) * 2/3, kaufpreis * 2/3) * zinssatz_1 / 100',
                'format' => 'currency'
            ),
            array(
                'label' => 'Jahreszins 2. Hypothek',
                'formula' => 'MAX(0, (kaufpreis - eigenkapital) - MIN((kaufpreis - eigenkapital) * 2/3, kaufpreis * 2/3)) * zinssatz_2 / 100',
                'format' => 'currency'
            )
        )),
        'is_public' => 1
    ));
    
    // Schweizer Säule 3a Rechner
    $database->insert_template(array(
        'name' => 'Säule 3a Rechner',
        'description' => 'Berechnet Steuerersparnis und Rendite der Säule 3a',
        'category' => 'tax_ch',
        'fields' => json_encode(array(
            array('id' => 'jahreseinkommen', 'label' => 'Jahreseinkommen', 'default' => '80000'),
            array('id' => 'einzahlung_3a', 'label' => 'Jährliche 3a-Einzahlung', 'default' => '7056'),
            array('id' => 'grenzsteuersatz', 'label' => 'Grenzsteuersatz (%)', 'default' => '25'),
            array('id' => 'laufzeit', 'label' => 'Laufzeit (Jahre)', 'default' => '20'),
            array('id' => 'rendite', 'label' => 'Erwartete Rendite (%)', 'default' => '3')
        )),
        'formulas' => json_encode(array(
            array(
                'label' => 'Max. Einzahlung (Angestellte)',
                'formula' => '7056',
                'format' => 'currency'
            ),
            array(
                'label' => 'Jährliche Steuerersparnis',
                'formula' => 'einzahlung_3a * grenzsteuersatz / 100',
                'format' => 'currency'
            ),
            array(
                'label' => 'Guthaben nach Laufzeit',
                'formula' => 'einzahlung_3a * ((POW(1 + rendite/100, laufzeit) - 1) / (rendite/100))',
                'format' => 'currency'
            ),
            array(
                'label' => 'Gesamte Steuerersparnis',
                'formula' => '(einzahlung_3a * grenzsteuersatz / 100) * laufzeit',
                'format' => 'currency'
            )
        )),
        'is_public' => 1
    ));
    
    update_option('ecp_custom_templates_loaded', true);
});

/**
 * ==========================================================================
 * PERFORMANCE-OPTIMIERUNGEN
 * ==========================================================================
 */

/**
 * Berechnungen cachen
 */
add_filter('ecp_cache_calculation', function($should_cache, $calculator_id, $inputs) {
    
    // Nur komplexe Kalkulatoren cachen
    $complex_calculators = array(1, 5, 10);
    
    return in_array($calculator_id, $complex_calculators);
}, 10, 3);

/**
 * Cache-Schlüssel anpassen
 */
add_filter('ecp_cache_key', function($cache_key, $calculator_id, $inputs) {
    
    // Benutzer-spezifischen Cache für eingeloggte Benutzer
    if (is_user_logged_in()) {
        $cache_key .= '_user_' . get_current_user_id();
    }
    
    return $cache_key;
}, 10, 3);

/**
 * ==========================================================================
 * DEBUGGING UND ENTWICKLUNG
 * ==========================================================================
 */

/**
 * Debug-Informationen zu Berechnungen
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    
    add_action('ecp_before_calculation', function($calculator_id, $inputs) {
        error_log("ECP Debug: Starting calculation for calculator {$calculator_id} with inputs: " . print_r($inputs, true));
    });
    
    add_action('ecp_after_calculation', function($calculator_id, $inputs, $outputs) {
        error_log("ECP Debug: Completed calculation for calculator {$calculator_id}. Outputs: " . print_r($outputs, true));
    });
    
    add_filter('ecp_formula_debug', '__return_true');
}

/**
 * Performance-Monitoring
 */
add_action('ecp_calculation_performance', function($calculator_id, $execution_time, $memory_usage) {
    
    // Langsame Berechnungen protokollieren
    if ($execution_time > 0.5) { // 500ms
        error_log("ECP Performance Warning: Calculator {$calculator_id} took {$execution_time}s to calculate");
    }
    
    // Hoher Speicherverbrauch protokollieren
    if ($memory_usage > 10 * 1024 * 1024) { // 10MB
        error_log("ECP Memory Warning: Calculator {$calculator_id} used " . round($memory_usage / 1024 / 1024, 2) . "MB memory");
    }
    
});

/**
 * ==========================================================================
 * MULTISITE-KONFIGURATION
 * ==========================================================================
 */

/**
 * Netzwerk-weite Einstellungen für Multisite
 */
if (is_multisite()) {
    
    add_filter('ecp_network_settings', function($settings) {
        return array(
            'max_calculators_per_site' => 50,
            'allowed_themes' => array('default', 'compact'),
            'disable_export' => false,
            'require_login' => false
        );
    });
    
    // Site-spezifische Einstellungen überschreiben
    add_filter('ecp_site_settings', function($settings, $blog_id) {
        
        // Spezielle Einstellungen für bestimmte Sites
        $special_sites = array(
            2 => array('max_calculators' => 100),
            5 => array('disable_export' => true)
        );
        
        if (isset($special_sites[$blog_id])) {
            $settings = array_merge($settings, $special_sites[$blog_id]);
        }
        
        return $settings;
    }, 10, 2);
}

/**
 * ==========================================================================
 * BEISPIEL-VERWENDUNG
 * ==========================================================================
 */

/**
 * Programmatische Erstellung eines Kalkulators
 */
add_action('wp_loaded', function() {
    
    // Nur einmal ausführen
    if (get_option('ecp_example_calculator_created')) {
        return;
    }
    
    $database = ecp_init()->get_database();
    
    $calculator_id = $database->save_calculator(array(
        'name' => 'Automatisch erstellter Kalkulator',
        'description' => 'Dieser Kalkulator wurde programmatisch erstellt',
        'fields' => array(
            array(
                'id' => 'wert_a',
                'label' => 'Wert A',
                'type' => 'number',
                'default' => '100',
                'min' => '0',
                'max' => '1000'
            ),
            array(
                'id' => 'wert_b', 
                'label' => 'Wert B',
                'type' => 'number',
                'default' => '50'
            )
        ),
        'formulas' => array(
            array(
                'label' => 'Summe',
                'formula' => 'wert_a + wert_b',
                'format' => ''
            ),
            array(
                'label' => 'Produkt',
                'formula' => 'wert_a * wert_b',
                'format' => ''
            )
        )
    ));
    
    if ($calculator_id) {
        update_option('ecp_example_calculator_created', true);
        update_option('ecp_example_calculator_id', $calculator_id);
    }
});

/**
 * ==========================================================================
 * SHORTCODE-ERWEITERUNGEN
 * ==========================================================================
 */

/**
 * Eigenen Shortcode für spezielle Kalkulatoren erstellen
 */
add_shortcode('hypothekenrechner', function($atts) {
    
    $atts = shortcode_atts(array(
        'theme' => 'modern',
        'width' => '100%'
    ), $atts);
    
    // Spezifischen Hypothekenrechner laden (ID 15)
    return do_shortcode('[excel_calculator id="15" theme="' . $atts['theme'] . '" width="' . $atts['width'] . '"]');
});

/**
 * Shortcode mit bedingter Anzeige
 */
add_shortcode('premium_calculator', function($atts) {
    
    if (!is_user_logged_in()) {
        return '<div class="ecp-login-required">
                    <p>Bitte loggen Sie sich ein, um diesen Kalkulator zu verwenden.</p>
                    <a href="' . wp_login_url() . '" class="button">Anmelden</a>
                </div>';
    }
    
    $atts = shortcode_atts(array(
        'id' => '',
        'theme' => 'default'
    ), $atts);
    
    return do_shortcode('[excel_calculator id="' . $atts['id'] . '" theme="' . $atts['theme'] . '"]');
});

/**
 * ==========================================================================
 * HINWEISE
 * ==========================================================================
 * 
 * 1. Kopieren Sie nur die Funktionen, die Sie benötigen
 * 2. Passen Sie die Werte an Ihre Anforderungen an
 * 3. Testen Sie alle Änderungen in einer Staging-Umgebung
 * 4. Dokumentieren Sie Ihre Anpassungen
 * 5. Erstellen Sie Backups vor grösseren Änderungen
 * 
 * Weitere Dokumentation und Beispiele finden Sie unter:
 * https://ihre-website.com/docs/excel-calculator-pro
 */