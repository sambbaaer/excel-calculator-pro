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
        add_action('wp_head', array($this, 'add_inline_styles'));
        add_action('wp_footer', array($this, 'add_inline_scripts')); // Dieser Hook benötigt die Methode add_inline_scripts

        // AJAX-Hooks für Frontend (falls benötigt)
        add_action('wp_ajax_ecp_calculate', array($this, 'ajax_calculate'));
        add_action('wp_ajax_nopriv_ecp_calculate', array($this, 'ajax_calculate'));
    }

    /**
     * Frontend-Scripts einbinden
     */
    public function enqueue_scripts()
    {
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

        // Frontend-Lokalisierung
        wp_localize_script('ecp-frontend-js', 'ecp_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_frontend_nonce'),
            'settings' => $this->get_frontend_settings()
        ));
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
                'USD' => '$ '
            )
        );
    }

    /**
 * Inline-Styles mit benutzerdefinierten Farben hinzufügen
 */
public function add_inline_styles() {
    // Farbeinstellungen abrufen
    $color_settings = get_option('ecp_color_settings', array());
    $primary_color = isset($color_settings['primary_color']) ? $color_settings['primary_color'] : '#007cba';
    $secondary_color = isset($color_settings['secondary_color']) ? $color_settings['secondary_color'] : '#00a0d2';
    $background_color = isset($color_settings['background_color']) ? $color_settings['background_color'] : '#ffffff';
    $calculator_width = isset($color_settings['calculator_width']) ? $color_settings['calculator_width'] : 'full';

    // NEU: Einstellung für System Dark Mode abrufen
    // Standardmäßig ist die Option deaktiviert (false), wenn sie nicht explizit gesetzt ist.
    $enable_system_dark_mode = isset($color_settings['enable_system_dark_mode']) ? (bool) $color_settings['enable_system_dark_mode'] : false;
    
    // Breiten-CSS basierend auf Einstellung
    $width_css = '';
    switch ($calculator_width) {
        case 'contained':
            $width_css = 'max-width: 700px; margin: 30px auto;';
            break;
        case 'large':
            $width_css = 'max-width: 900px; margin: 30px auto;';
            break;
        case 'medium':
            $width_css = 'max-width: 600px; margin: 30px auto;';
            break;
        default: // full
            $width_css = 'max-width: 100%; width: 100%; margin: 30px 0;';
            break;
    }
    
    ?>
    <style id="ecp-inline-styles">
    /* Excel Calculator Pro Frontend Styles - Dynamische Farben */
    :root {
        --ecp-primary-color: <?php echo esc_attr($primary_color); ?>;
        --ecp-secondary-color: <?php echo esc_attr($secondary_color); ?>;
        --ecp-background-color: <?php echo esc_attr($background_color); ?>;
    }
    
    /* ... (alle bestehenden CSS-Regeln bis zum Dark Mode Block) ... */
    .ecp-calculator {
        <?php echo $width_css; ?> /* */
        padding: 30px; /* */
        border: 1px solid #e1e5e9; /* */
        border-radius: 12px; /* */
        background: var(--ecp-background-color); /* */
        box-shadow: 0 4px 15px rgba(0,0,0,0.08); /* */
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; /* */
        line-height: 1.6; /* */
        color: #2c3e50; /* */
        position: relative; /* */
        overflow: hidden; /* */
    }
    
    .ecp-calculator::before {
        content: ''; /* */
        position: absolute; /* */
        top: 0; /* */
        left: 0; /* */
        right: 0; /* */
        height: 4px; /* */
        background: linear-gradient(90deg, var(--ecp-primary-color) 0%, var(--ecp-secondary-color) 50%, var(--ecp-primary-color) 100%); /* */
        background-size: 200% 100%; /* */
        animation: ecpGradientShift 3s ease-in-out infinite; /* */
    }
    
    @keyframes ecpGradientShift {
        0%, 100% { background-position: 0% 50%; } /* */
        50% { background-position: 100% 50%; } /* */
    }
    
    .ecp-calculator-header {
        margin-bottom: 25px; /* */
        text-align: center; /* */
        border-bottom: 2px solid #f8f9fa; /* */
        padding-bottom: 15px; /* */
    }
    
    .ecp-calculator-title {
        font-size: 24px; /* */
        font-weight: 600; /* */
        color: #2c3e50; /* */
        margin: 0 0 8px 0; /* */
    }
    
    .ecp-calculator-description {
        color: #6c757d; /* */
        font-size: 14px; /* */
        margin: 0; /* */
    }
    
    .ecp-section {
        margin-bottom: 30px; /* */
    }
    
    .ecp-section-title {
        font-size: 18px; /* */
        font-weight: 600; /* */
        color: #2c3e50; /* */
        margin: 0 0 15px 0; /* */
        padding-bottom: 8px; /* */
        border-bottom: 1px solid #e9ecef; /* */
        position: relative; /* */
        display: flex; /* */
        align-items: center; /* */
        gap: 10px; /* */
    }
    
    .ecp-section-title::before {
        content: ''; /* */
        width: 4px; /* */
        height: 20px; /* */
        background: linear-gradient(135deg, var(--ecp-primary-color), var(--ecp-secondary-color)); /* */
        border-radius: 2px; /* */
    }
    
    .ecp-field-group, .ecp-output-group {
        margin-bottom: 20px; /* */
        display: flex; /* */
        align-items: center; /* */
        justify-content: space-between; /* */
        padding: 15px; /* */
        background: #f8f9fa; /* */
        border-radius: 6px; /* */
        transition: all 0.2s ease; /* */
        position: relative; /* */
        overflow: hidden; /* */
    }
    
    .ecp-field-group::before {
        content: ''; /* */
        position: absolute; /* */
        top: 0; /* */
        left: 0; /* */
        width: 4px; /* */
        height: 100%; /* */
        background: linear-gradient(135deg, var(--ecp-primary-color), var(--ecp-secondary-color)); /* */
        transform: scaleY(0); /* */
        transition: transform 0.3s ease; /* */
    }
    
    .ecp-field-group:hover {
        background: #e9ecef; /* */
        border-color: var(--ecp-primary-color); /* */
        transform: translateY(-2px); /* */
        box-shadow: 0 6px 20px rgba(0, 124, 186, 0.15); /* */
    }
    
    .ecp-field-group:hover::before {
        transform: scaleY(1); /* */
    }
    
    .ecp-field-group label, .ecp-output-group label {
        font-weight: 600; /* */
        color: #495057; /* */
        margin-right: 15px; /* */
        min-width: 180px; /* */
        text-align: left; /* */
        font-size: 14px; /* */
    }
    
    .ecp-input-field {
        padding: 12px 16px; /* */
        border: 2px solid #dee2e6; /* */
        border-radius: 6px; /* */
        width: 220px; /* */
        font-size: 16px; /* */
        font-weight: 500; /* */
        transition: all 0.2s ease; /* */
        background: white; /* */
        outline: none; /* */
    }
    
    .ecp-input-field:focus {
        border-color: var(--ecp-primary-color); /* */
        box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1); /* */
        transform: scale(1.02); /* */
    }
    
    .ecp-input-field:invalid {
        border-color: #dc3545; /* */
    }
    
    .ecp-output-group {
        background: linear-gradient(135deg, #e8f4f8 0%, #f0f9ff 100%); /* */
        border: 1px solid #b3d9e6; /* */
        border-left: 5px solid var(--ecp-primary-color); /* */
    }
    
    .ecp-output-field {
        font-weight: 700; /* */
        font-size: 18px; /* */
        color: var(--ecp-primary-color); /* */
        background: white; /* */
        padding: 12px 16px; /* */
        border-radius: 6px; /* */
        min-width: 120px; /* */
        text-align: right; /* */
        border: 2px solid var(--ecp-primary-color); /* */
        transition: all 0.3s ease; /* */
        box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1); /* */
    }
    
    .ecp-output-field.ecp-animated {
        transform: scale(1.05); /* */
        box-shadow: 0 6px 20px rgba(0, 124, 186, 0.25); /* */
        background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%); /* */
    }
    
    .ecp-output-field.ecp-changed {
        animation: ecpPulseGlow 0.6s ease-in-out; /* */
    }
    
    @keyframes ecpPulseGlow {
        0% { 
            transform: scale(1); /* */
            box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1); /* */
        }
        50% { 
            transform: scale(1.08); /* */
            box-shadow: 0 8px 25px rgba(0, 124, 186, 0.3); /* */
            background: linear-gradient(135deg, #e8f4f8 0%, #ffffff 100%); /* */
        }
        100% { 
            transform: scale(1); /* */
            box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1); /* */
        }
    }
    
    .ecp-field-help, .ecp-input-unit, .ecp-output-unit {
        font-size: 12px; /* */
        color: #6c757d; /* */
        margin-top: 4px; /* */
        font-style: italic; /* */
    }
    
    .ecp-error-message {
        color: #dc3545; /* */
        font-size: 12px; /* */
        margin-top: 4px; /* */
    }
    
    .ecp-loading {
        opacity: 0.6; /* */
        pointer-events: none; /* */
    }
    
    /* Responsive Design */
    @media (max-width: 1200px) {
        .ecp-calculator {
            margin: 20px 15px !important; /* */
        }
    }
    
    @media (max-width: 768px) {
        .ecp-calculator {
            margin: 15px !important; /* */
            padding: 25px 20px; /* */
        }
        
        .ecp-field-group, .ecp-output-group {
            flex-direction: column; /* */
            align-items: flex-start; /* */
            text-align: left; /* */
        }
        
        .ecp-field-group label, .ecp-output-group label {
            margin-bottom: 8px; /* */
            min-width: auto; /* */
            width: 100%; /* */
        }
        
        .ecp-input-field {
            width: 100%; /* */
            max-width: none; /* */
        }
        
        .ecp-output-field {
            width: 100%; /* */
            text-align: center; /* */
        }
    }
    
    @media (max-width: 480px) {
        .ecp-calculator {
            margin: 10px !important; /* */
            padding: 20px 15px; /* */
        }
        
        .ecp-calculator-title {
            font-size: 20px; /* */
        }
        
        .ecp-section-title {
            font-size: 16px; /* */
        }
    }

    /* Dark Mode Support - NUR WENN AKTIVIERT */
    <?php if ($enable_system_dark_mode): ?>
    @media (prefers-color-scheme: dark) {
        .ecp-calculator {
            background: #1e1e1e !important; /* Wichtig, um evtl. andere :root Variablen zu überschreiben */ /* */
            border-color: #404040 !important; /* */
            color: #e0e0e0 !important; /* */
        }
        
        .ecp-calculator-title, .ecp-section-title {
            color: #ffffff; /* */
        }
        
        .ecp-section-title {
            border-bottom-color: #404040; /* */
        }
        
        .ecp-field-group { /* Angepasst von der Vorlage, da der :hover Effekt und ::before Gradienten evtl. nicht gut aussehen */
            background: #2d2d2d !important; /* */
        }
        
        .ecp-field-group:hover {
            background: #353535 !important; /* */
        }
        
        .ecp-input-field {
            background: #1e1e1e !important; /* */
            border-color: #404040 !important; /* */
            color: #e0e0e0 !important; /* */
        }
        
        .ecp-input-field:focus {
            background: #2d2d2d !important; /* */
            /* border-color: var(--ecp-primary-color); -> wird von oben übernommen, ggf. anpassen */
        }
        
        .ecp-output-group { /* Angepasst von der Vorlage */
            background: #1a3a4a !important; /* */
            /* border-left-color: var(--ecp-primary-color); -> wird von oben übernommen, ggf. anpassen */
        }
        
        .ecp-output-field {
            background: #1e1e1e !important; /* */
            color: var(--ecp-secondary-color) !important; /* Beibehaltung der Akzentfarbe für Ergebnisse */ /* */
            /* border-color: var(--ecp-primary-color); -> wird von oben übernommen, ggf. anpassen */
        }

        .ecp-output-field.ecp-changed {
            animation: ecpPulseGlowDark 0.6s ease-in-out;
        }
        /* Eigene Keyframe-Animation für Dark Mode, um Konflikte mit hellen Farben zu vermeiden */
        @keyframes ecpPulseGlowDark {
            0% { 
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(var(--ecp-secondary-color-rgb, 80, 180, 220), 0.15); /* Annahme: Sekundärfarbe ist hell genug */
            }
            50% { 
                transform: scale(1.08);
                box-shadow: 0 8px 25px rgba(var(--ecp-secondary-color-rgb, 80, 180, 220), 0.35);
                background: linear-gradient(135deg, #2a4a5a 0%, #1a2a3a 100%) !important;
            }
            100% { 
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(var(--ecp-secondary-color-rgb, 80, 180, 220), 0.15);
            }
        }
    }
    <?php endif; ?>
    
    /* Print Styles */
    @media print {
        .ecp-calculator {
            box-shadow: none !important; /* */
            border: 1px solid #000 !important; /* */
            background: white !important; /* */
            color: black !important; /* */
            max-width: none !important; /* */
            margin: 0 !important; /* */
            padding: 20px !important; /* */
        }
        
        .ecp-calculator::before {
            display: none !important; /* */
        }
        
        .ecp-field-group, .ecp-output-group {
            background: white !important; /* */
            border: 1px solid #000 !important; /* */
        }
        
        .ecp-input-field, .ecp-output-field {
            border: 1px solid #000 !important; /* */
            background: white !important; /* */
            color: black !important; /* */
            box-shadow: none !important; /* */
        }
    }
    </style>
    <?php
}

?>
        <style id="ecp-inline-styles">
            /* Excel Calculator Pro Frontend Styles - Dynamische Farben */
            :root {
                --ecp-primary-color: <?php echo esc_attr($primary_color); ?>;
                --ecp-secondary-color: <?php echo esc_attr($secondary_color); ?>;
                --ecp-background-color: <?php echo esc_attr($background_color); ?>;
            }

            .ecp-calculator {
                <?php echo $width_css; ?>padding: 30px;
                border: 1px solid #e1e5e9;
                border-radius: 12px;
                background: var(--ecp-background-color);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #2c3e50;
                position: relative;
                overflow: hidden;
            }

            .ecp-calculator::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--ecp-primary-color) 0%, var(--ecp-secondary-color) 50%, var(--ecp-primary-color) 100%);
                background-size: 200% 100%;
                animation: ecpGradientShift 3s ease-in-out infinite;
            }

            @keyframes ecpGradientShift {

                0%,
                100% {
                    background-position: 0% 50%;
                }

                50% {
                    background-position: 100% 50%;
                }
            }

            .ecp-calculator-header {
                margin-bottom: 25px;
                text-align: center;
                border-bottom: 2px solid #f8f9fa;
                padding-bottom: 15px;
            }

            .ecp-calculator-title {
                font-size: 24px;
                font-weight: 600;
                color: #2c3e50;
                margin: 0 0 8px 0;
            }

            .ecp-calculator-description {
                color: #6c757d;
                font-size: 14px;
                margin: 0;
            }

            .ecp-section {
                margin-bottom: 30px;
            }

            .ecp-section-title {
                font-size: 18px;
                font-weight: 600;
                color: #2c3e50;
                margin: 0 0 15px 0;
                padding-bottom: 8px;
                border-bottom: 1px solid #e9ecef;
                position: relative;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .ecp-section-title::before {
                content: '';
                width: 4px;
                height: 20px;
                background: linear-gradient(135deg, var(--ecp-primary-color), var(--ecp-secondary-color));
                border-radius: 2px;
            }

            .ecp-field-group,
            .ecp-output-group {
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 6px;
                transition: all 0.2s ease;
                position: relative;
                overflow: hidden;
            }

            .ecp-field-group::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 4px;
                height: 100%;
                background: linear-gradient(135deg, var(--ecp-primary-color), var(--ecp-secondary-color));
                transform: scaleY(0);
                transition: transform 0.3s ease;
            }

            .ecp-field-group:hover {
                background: #e9ecef;
                border-color: var(--ecp-primary-color);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 124, 186, 0.15);
            }

            .ecp-field-group:hover::before {
                transform: scaleY(1);
            }

            .ecp-field-group label,
            .ecp-output-group label {
                font-weight: 600;
                color: #495057;
                margin-right: 15px;
                min-width: 180px;
                text-align: left;
                font-size: 14px;
            }

            .ecp-input-field {
                padding: 12px 16px;
                border: 2px solid #dee2e6;
                border-radius: 6px;
                width: 220px;
                font-size: 16px;
                font-weight: 500;
                transition: all 0.2s ease;
                background: white;
                outline: none;
            }

            .ecp-input-field:focus {
                border-color: var(--ecp-primary-color);
                box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
                transform: scale(1.02);
            }

            .ecp-input-field:invalid {
                border-color: #dc3545;
            }

            .ecp-output-group {
                background: linear-gradient(135deg, #e8f4f8 0%, #f0f9ff 100%);
                border: 1px solid #b3d9e6;
                border-left: 5px solid var(--ecp-primary-color);
            }

            .ecp-output-field {
                font-weight: 700;
                font-size: 18px;
                color: var(--ecp-primary-color);
                background: white;
                padding: 12px 16px;
                border-radius: 6px;
                min-width: 120px;
                text-align: right;
                border: 2px solid var(--ecp-primary-color);
                transition: all 0.3s ease;
                box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1);
            }

            .ecp-output-field.ecp-animated {
                transform: scale(1.05);
                box-shadow: 0 6px 20px rgba(0, 124, 186, 0.25);
                background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            }

            .ecp-output-field.ecp-changed {
                animation: ecpPulseGlow 0.6s ease-in-out;
            }

            @keyframes ecpPulseGlow {
                0% {
                    transform: scale(1);
                    box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1);
                }

                50% {
                    transform: scale(1.08);
                    box-shadow: 0 8px 25px rgba(0, 124, 186, 0.3);
                    background: linear-gradient(135deg, #e8f4f8 0%, #ffffff 100%);
                }

                100% {
                    transform: scale(1);
                    box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1);
                }
            }

            .ecp-field-help,
            .ecp-input-unit,
            .ecp-output-unit {
                font-size: 12px;
                color: #6c757d;
                margin-top: 4px;
                font-style: italic;
            }

            .ecp-error-message {
                color: #dc3545;
                font-size: 12px;
                margin-top: 4px;
            }

            .ecp-loading {
                opacity: 0.6;
                pointer-events: none;
            }

            /* Responsive Design */
            @media (max-width: 1200px) {
                .ecp-calculator {
                    margin: 20px 15px !important;
                }
            }

            @media (max-width: 768px) {
                .ecp-calculator {
                    margin: 15px !important;
                    padding: 25px 20px;
                }

                .ecp-field-group,
                .ecp-output-group {
                    flex-direction: column;
                    align-items: flex-start;
                    text-align: left;
                }

                .ecp-field-group label,
                .ecp-output-group label {
                    margin-bottom: 8px;
                    min-width: auto;
                    width: 100%;
                }

                .ecp-input-field {
                    width: 100%;
                    max-width: none;
                }

                .ecp-output-field {
                    width: 100%;
                    text-align: center;
                }
            }

            @media (max-width: 480px) {
                .ecp-calculator {
                    margin: 10px !important;
                    padding: 20px 15px;
                }

                .ecp-calculator-title {
                    font-size: 20px;
                }

                .ecp-section-title {
                    font-size: 16px;
                }
            }

            /* Dark Mode Support */
            @media (prefers-color-scheme: dark) {
                .ecp-calculator {
                    background: #1e1e1e !important;
                    border-color: #404040;
                    color: #e0e0e0;
                }

                .ecp-calculator-title,
                .ecp-section-title {
                    color: #ffffff;
                }

                .ecp-section-title {
                    border-bottom-color: #404040;
                }

                .ecp-field-group,
                .ecp-output-group {
                    background: #2d2d2d;
                }

                .ecp-field-group:hover {
                    background: #353535;
                }

                .ecp-input-field {
                    background: #1e1e1e;
                    border-color: #404040;
                    color: #e0e0e0;
                }

                .ecp-input-field:focus {
                    background: #2d2d2d;
                }

                .ecp-output-group {
                    background: #1a3a4a;
                }

                .ecp-output-field {
                    background: #1e1e1e;
                    color: var(--ecp-secondary-color);
                }
            }

            /* Print Styles */
            @media print {
                .ecp-calculator {
                    box-shadow: none !important;
                    border: 1px solid #000 !important;
                    background: white !important;
                    color: black !important;
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 20px !important;
                }

                .ecp-calculator::before {
                    display: none !important;
                }

                .ecp-field-group,
                .ecp-output-group {
                    background: white !important;
                    border: 1px solid #000 !important;
                }

                .ecp-input-field,
                .ecp-output-field {
                    border: 1px solid #000 !important;
                    background: white !important;
                    color: black !important;
                    box-shadow: none !important;
                }
            }
        </style>
    <?php // KORREKTUR: PHP-Block für add_inline_styles hier beenden
    } // KORREKTUR: Ende der Methode add_inline_styles

    /**
     * Inline-Scripts hinzufügen
     */
    public function add_inline_scripts()
    { // KORREKTUR: Dies ist jetzt eine eigenständige Methode
    ?>
        <script id="ecp-inline-scripts">
            (function($) {
                'use strict';

                // Enhanced Formula Parser Class
                class ECPFormulaParser {
                    constructor() {
                        this.functions = {
                            // Deutsch
                            'WENN': this.IF.bind(this),
                            'RUNDEN': this.ROUND.bind(this),
                            'MIN': this.MIN.bind(this),
                            'MAX': this.MAX.bind(this),
                            'SUMME': this.SUM.bind(this),
                            'MITTELWERT': this.AVERAGE.bind(this),
                            'ABS': this.ABS.bind(this),
                            'WURZEL': this.SQRT.bind(this),
                            'POTENZ': this.POW.bind(this),
                            'LOG': this.LOG.bind(this),
                            'HEUTE': this.TODAY.bind(this),
                            'JAHR': this.YEAR.bind(this),
                            'MONAT': this.MONTH.bind(this),
                            'TAG': this.DAY.bind(this),
                            // English
                            'IF': this.IF.bind(this),
                            'ROUND': this.ROUND.bind(this),
                            'SUM': this.SUM.bind(this),
                            'AVERAGE': this.AVERAGE.bind(this),
                            'SQRT': this.SQRT.bind(this),
                            'POW': this.POW.bind(this),
                            'TODAY': this.TODAY.bind(this),
                            'YEAR': this.YEAR.bind(this),
                            'MONTH': this.MONTH.bind(this),
                            'DAY': this.DAY.bind(this)
                        };

                        this.constants = {
                            'PI': Math.PI,
                            'E': Math.E
                        };
                    }

                    parse(formula, values, debug = false) {
                        try {
                            let processedFormula = formula;

                            // Konstanten ersetzen
                            for (let constant in this.constants) {
                                const regex = new RegExp('\\b' + constant + '\\b', 'g');
                                processedFormula = processedFormula.replace(regex, this.constants[constant]);
                            }

                            // Feldnamen durch Werte ersetzen
                            for (let fieldId in values) {
                                const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                                processedFormula = processedFormula.replace(regex, values[fieldId] || 0);
                            }

                            // Funktionen verarbeiten
                            processedFormula = this.processFunctions(processedFormula, values);

                            if (debug) {
                                console.log('Original:', formula);
                                console.log('Processed:', processedFormula);
                                console.log('Values:', values);
                            }

                            // Sichere Evaluierung
                            const result = this.safeEval(processedFormula);
                            return isNaN(result) ? 0 : result;
                        } catch (error) {
                            if (debug) {
                                console.error('Formula error:', error);
                            }
                            return 0;
                        }
                    }

                    escapeRegExp(string) {
                        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    }

                    processFunctions(formula, values) {
                        // Verschachtelte Funktionen von innen nach aussen verarbeiten
                        let processedFormula = formula;
                        let maxIterations = 10;
                        let iteration = 0;

                        while (iteration < maxIterations) {
                            let hasFunction = false;

                            for (let funcName in this.functions) {
                                const regex = new RegExp(funcName + '\\s*\\(([^()]*)\\)', 'gi');
                                if (regex.test(processedFormula)) {
                                    hasFunction = true;
                                    processedFormula = processedFormula.replace(regex, (match, args) => {
                                        return this.functions[funcName](args, values);
                                    });
                                }
                            }

                            if (!hasFunction) break;
                            iteration++;
                        }

                        return processedFormula;
                    }

                    safeEval(expression) {
                        // Nur sichere mathematische Operationen erlauben
                        const sanitized = expression.replace(/[^0-9+\-*/().,\s]/g, '');

                        // Kommas durch Punkte ersetzen für Dezimalzahlen
                        const normalized = sanitized.replace(/,/g, '.');

                        try {
                            return Function('"use strict"; return (' + normalized + ')')();
                        } catch (e) {
                            return 0;
                        }
                    }

                    // Implementierung der Funktionen
                    IF(args, values) {
                        const parts = this.parseArguments(args);
                        if (parts.length < 3) return 0;

                        const condition = this.evaluateCondition(parts[0], values);
                        return condition ? this.parseValue(parts[1], values) : this.parseValue(parts[2], values);
                    }

                    ROUND(args, values) {
                        const parts = this.parseArguments(args);
                        if (parts.length < 1) return 0;

                        const value = this.parseValue(parts[0], values);
                        const decimals = parts.length > 1 ? this.parseValue(parts[1], values) : 0;

                        return Math.round(value * Math.pow(10, decimals)) / Math.pow(10, decimals);
                    }

                    MIN(args, values) {
                        const parts = this.parseArguments(args);
                        const numbers = parts.map(part => this.parseValue(part, values));
                        return Math.min(...numbers);
                    }

                    MAX(args, values) {
                        const parts = this.parseArguments(args);
                        const numbers = parts.map(part => this.parseValue(part, values));
                        return Math.max(...numbers);
                    }

                    SUM(args, values) {
                        const parts = this.parseArguments(args);
                        return parts.reduce((sum, part) => sum + this.parseValue(part, values), 0);
                    }

                    AVERAGE(args, values) {
                        const parts = this.parseArguments(args);
                        const sum = this.SUM(args, values);
                        return sum / parts.length;
                    }

                    ABS(args, values) {
                        const parts = this.parseArguments(args);
                        if (parts.length < 1) return 0;
                        return Math.abs(this.parseValue(parts[0], values));
                    }

                    SQRT(args, values) {
                        const parts = this.parseArguments(args);
                        if (parts.length < 1) return 0;
                        return Math.sqrt(this.parseValue(parts[0], values));
                    }

                    POW(args, values) {
                        const parts = this.parseArguments(args);
                        if (parts.length < 2) return 0;
                        return Math.pow(this.parseValue(parts[0], values), this.parseValue(parts[1], values));
                    }

                    LOG(args, values) {
                        const parts = this.parseArguments(args);
                        if (parts.length < 1) return 0;
                        const base = parts.length > 1 ? this.parseValue(parts[1], values) : Math.E;
                        return Math.log(this.parseValue(parts[0], values)) / Math.log(base);
                    }

                    TODAY(args, values) {
                        return new Date().getTime();
                    }

                    YEAR(args, values) {
                        const parts = this.parseArguments(args);
                        const date = parts.length > 0 ? new Date(this.parseValue(parts[0], values)) : new Date();
                        return date.getFullYear();
                    }

                    MONTH(args, values) {
                        const parts = this.parseArguments(args);
                        const date = parts.length > 0 ? new Date(this.parseValue(parts[0], values)) : new Date();
                        return date.getMonth() + 1;
                    }

                    DAY(args, values) {
                        const parts = this.parseArguments(args);
                        const date = parts.length > 0 ? new Date(this.parseValue(parts[0], values)) : new Date();
                        return date.getDate();
                    }

                    parseArguments(args) {
                        // Verbesserte Argumentenaufteilung
                        const result = [];
                        let current = '';
                        let parenthesisLevel = 0;
                        let inQuotes = false;

                        for (let i = 0; i < args.length; i++) {
                            const char = args[i];

                            if (char === '"' || char === "'") {
                                inQuotes = !inQuotes;
                                current += char;
                            } else if (!inQuotes && char === '(') {
                                parenthesisLevel++;
                                current += char;
                            } else if (!inQuotes && char === ')') {
                                parenthesisLevel--;
                                current += char;
                            } else if (!inQuotes && char === ',' && parenthesisLevel === 0) {
                                result.push(current.trim());
                                current = '';
                            } else {
                                current += char;
                            }
                        }

                        if (current.trim()) {
                            result.push(current.trim());
                        }

                        return result;
                    }

                    parseValue(value, values) {
                        value = value.replace(/^["']|["']$/g, '');

                        if (!isNaN(value)) {
                            return parseFloat(value);
                        }

                        if (values[value] !== undefined) {
                            return parseFloat(values[value]) || 0;
                        }

                        try {
                            return this.safeEval(value);
                        } catch (e) {
                            return 0;
                        }
                    }

                    evaluateCondition(condition, values) {
                        condition = condition.trim();

                        // Feldnamen durch Werte ersetzen
                        for (let fieldId in values) {
                            const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                            condition = condition.replace(regex, values[fieldId] || 0);
                        }

                        const operators = ['>=', '<=', '!=', '==', '>', '<', '='];

                        for (let op of operators) {
                            if (condition.includes(op)) {
                                const parts = condition.split(op);
                                if (parts.length === 2) {
                                    const left = this.parseValue(parts[0].trim(), values);
                                    const right = this.parseValue(parts[1].trim(), values);

                                    switch (op) {
                                        case '>=':
                                            return left >= right;
                                        case '<=':
                                            return left <= right;
                                        case '!=':
                                            return left != right;
                                        case '==':
                                        case '=':
                                            return left == right;
                                        case '>':
                                            return left > right;
                                        case '<':
                                            return left < right;
                                    }
                                }
                            }
                        }

                        return Boolean(this.parseValue(condition, values));
                    }
                }

                const parser = new ECPFormulaParser();

                // Berechnung durchführen
                function calculateResults(calculator) {
                    const values = {};

                    // Eingabewerte sammeln
                    calculator.find('.ecp-input-field').each(function() {
                        const fieldId = $(this).data('field-id');
                        const value = parseFloat($(this).val()) || 0;
                        values[fieldId] = value;
                    });

                    // Ausgabewerte berechnen
                    calculator.find('.ecp-output-field').each(function() {
                        const $output = $(this);
                        const formula = $output.data('formula');
                        const format = $output.data('format') || '';
                        const prevValue = $output.text();

                        if (formula) {
                            try {
                                const result = parser.parse(formula, values);
                                const formattedResult = formatNumber(result, format);

                                if (formattedResult !== prevValue) {
                                    $output.addClass('ecp-changed');
                                    setTimeout(() => $output.removeClass('ecp-changed'), 300);
                                }

                                $output.text(formattedResult);
                            } catch (error) {
                                console.error('Calculation error:', error);
                                $output.text('Fehler');
                            }
                        }
                    });
                }

                // Erweiterte Zahlenformatierung
                function formatNumber(number, format) {
                    if (isNaN(number) || !isFinite(number)) return '0';

                    const settings = ecp_frontend.settings;
                    const locale = settings.number_format || 'de_CH';
                    const currency = settings.currency || 'CHF';

                    // Lokalisierungsoptionen
                    const localeMap = {
                        'de_CH': 'de-CH',
                        'de_DE': 'de-DE',
                        'en_US': 'en-US'
                    };

                    const targetLocale = localeMap[locale] || 'de-CH';

                    switch (format) {
                        case 'currency':
                            const currencySymbol = settings.currency_symbols[currency] || currency + ' ';
                            return currencySymbol + number.toLocaleString(targetLocale, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });

                        case 'percentage':
                            return (number * 100).toLocaleString(targetLocale, {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 2
                            }) + '%';

                        case 'integer':
                            return Math.round(number).toLocaleString(targetLocale);

                        case 'text':
                            return String(number);

                        default:
                            return number.toLocaleString(targetLocale, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 2
                            });
                    }
                }

                // Event-Handler für Eingabefelder
                $(document).on('input change keyup', '.ecp-input-field', function() {
                    const calculator = $(this).closest('.ecp-calculator');

                    // Debounce für bessere Performance
                    clearTimeout(calculator.data('calcTimeout'));
                    calculator.data('calcTimeout', setTimeout(function() {
                        calculateResults(calculator);
                    }, 100));
                });

                // Eingabevalidierung
                $(document).on('blur', '.ecp-input-field', function() {
                    const $input = $(this);
                    const value = parseFloat($input.val());

                    if (isNaN(value)) {
                        $input.val(0);
                        calculateResults($input.closest('.ecp-calculator'));
                    }
                });

                // Initialisierung aller Kalkulatoren beim Laden
                $(document).ready(function() {
                    $('.ecp-calculator').each(function() {
                        const $calc = $(this);

                        // Initiale Berechnung
                        calculateResults($calc);

                        // Animation für bessere UX
                        $calc.hide().fadeIn(500);
                    });

                    // Accessibility improvements
                    $('.ecp-input-field').attr('role', 'spinbutton');
                    $('.ecp-output-field').attr('role', 'status').attr('aria-live', 'polite');
                });

            })(jQuery);
        </script>
<?php
    } // KORREKTUR: Ende der Methode add_inline_scripts

    /**
     * AJAX: Kalkulation durchführen (falls Server-seitige Berechnungen nötig)
     */
    public function ajax_calculate()
    {
        check_ajax_referer('ecp_frontend_nonce', 'nonce');

        $calculator_id = intval($_POST['calculator_id']);
        $values = $_POST['values'] ?? array();

        $calculator = $this->get_database()->get_calculator($calculator_id); // Verwende get_database() für Konsistenz

        if (!$calculator) {
            wp_send_json_error(__('Kalkulator nicht gefunden', 'excel-calculator-pro'));
        }

        // Server-seitige Berechnungen (falls komplexe Operationen nötig)
        $results = array();

        foreach ($calculator->formulas as $formula) {
            // Hier könnten komplexe Server-seitige Berechnungen durchgeführt werden
            // Für jetzt delegieren wir an das Frontend
            $results[] = array(
                'label' => $formula['label'],
                'value' => 0 // Placeholder
            );
        }

        wp_send_json_success($results);
    }
}
