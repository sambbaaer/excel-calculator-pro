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
            if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_database')) {
                $this->database = ecp_init()->get_database();
            } else {
                // Fallback, sollte idealerweise nicht oft benötigt werden.
                // Stellt sicher, dass $this->database immer eine Instanz ist.
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
        add_action('wp_head', array($this, 'add_inline_styles'));

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

        wp_localize_script('ecp-frontend-js', 'ecp_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_frontend_nonce'),
            'settings' => $this->get_frontend_settings(),
            'strings' => array(
                'error_invalid_number' => __('Ungültige Zahl.', 'excel-calculator-pro'),
                'error_min_value' => __('Wert muss mind. %min% sein.', 'excel-calculator-pro'),
                'error_max_value' => __('Wert darf max. %max% sein.', 'excel-calculator-pro'),
                'copy_label_prefix' => __('Kopiere ', 'excel-calculator-pro'),
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
                'CHF' => 'CHF ',
                'EUR' => '€ ',
                'USD' => '$ '
            )),
            'enable_system_dark_mode' => isset($color_settings['enable_system_dark_mode']) && $color_settings['enable_system_dark_mode'] == '1',
        );
    }

    /**
     * Inline-Styles mit benutzerdefinierten Farben hinzufügen
     */
    public function add_inline_styles()
    {
        $color_settings = get_option('ecp_color_settings', array());
        $primary_color = !empty($color_settings['primary_color']) ? $color_settings['primary_color'] : '#007cba';
        $secondary_color = !empty($color_settings['secondary_color']) ? $color_settings['secondary_color'] : '#00a0d2';

        $calculator_width_option = isset($color_settings['calculator_width']) ? $color_settings['calculator_width'] : 'full';
        $enable_system_dark_mode = isset($color_settings['enable_system_dark_mode']) && $color_settings['enable_system_dark_mode'] == '1';

        // Light Mode Farben mit Fallbacks
        $background_color_light = !empty($color_settings['background_color_light']) ? $color_settings['background_color_light'] : '#ffffff';
        $text_color_light = !empty($color_settings['text_color_light']) ? $color_settings['text_color_light'] : '#2c3e50';
        $text_light_light = !empty($color_settings['text_light_light']) ? $color_settings['text_light_light'] : '#6c757d';
        $border_color_light = !empty($color_settings['border_color_light']) ? $color_settings['border_color_light'] : '#e1e5e9';
        $input_bg_light = !empty($color_settings['input_bg_light']) ? $color_settings['input_bg_light'] : '#ffffff';
        $input_border_light = !empty($color_settings['input_border_light']) ? $color_settings['input_border_light'] : '#dee2e6';
        $field_group_bg_light = !empty($color_settings['field_group_bg_light']) ? $color_settings['field_group_bg_light'] : '#f8f9fa';
        $field_group_hover_bg_light = !empty($color_settings['field_group_hover_bg_light']) ? $color_settings['field_group_hover_bg_light'] : '#e9ecef';

        // Gradient-Farben für Light Mode
        $output_group_bg_gradient_start_light = !empty($color_settings['output_group_bg_gradient_start_light']) ? $color_settings['output_group_bg_gradient_start_light'] : '#e8f4f8';
        $output_group_bg_gradient_end_light = !empty($color_settings['output_group_bg_gradient_end_light']) ? $color_settings['output_group_bg_gradient_end_light'] : '#f0f9ff';
        $output_group_bg_light = 'linear-gradient(135deg, ' . $output_group_bg_gradient_start_light . ' 0%, ' . $output_group_bg_gradient_end_light . ' 100%)';

        $output_group_border_light = !empty($color_settings['output_group_border_light']) ? $color_settings['output_group_border_light'] : '#b3d9e6';
        $output_field_bg_light = !empty($color_settings['output_field_bg_light']) ? $color_settings['output_field_bg_light'] : '#ffffff';
        $copy_icon_feedback_color_light = !empty($color_settings['copy_icon_feedback_color_light']) ? $color_settings['copy_icon_feedback_color_light'] : '#28a745';

        // Dark Mode Farben mit Fallbacks
        $background_color_dark = !empty($color_settings['background_color_dark']) ? $color_settings['background_color_dark'] : '#1e1e1e';
        $text_color_dark = !empty($color_settings['text_color_dark']) ? $color_settings['text_color_dark'] : '#e0e0e0';
        $text_light_dark = !empty($color_settings['text_light_dark']) ? $color_settings['text_light_dark'] : '#adb5bd';
        $border_color_dark = !empty($color_settings['border_color_dark']) ? $color_settings['border_color_dark'] : '#404040';
        $input_bg_dark = !empty($color_settings['input_bg_dark']) ? $color_settings['input_bg_dark'] : '#2d2d2d';
        $input_border_dark = !empty($color_settings['input_border_dark']) ? $color_settings['input_border_dark'] : '#505050';
        $field_group_bg_dark = !empty($color_settings['field_group_bg_dark']) ? $color_settings['field_group_bg_dark'] : '#2d2d2d';
        $field_group_hover_bg_dark = !empty($color_settings['field_group_hover_bg_dark']) ? $color_settings['field_group_hover_bg_dark'] : '#353535';

        // Gradient-Farben für Dark Mode
        $output_group_bg_gradient_start_dark = !empty($color_settings['output_group_bg_gradient_start_dark']) ? $color_settings['output_group_bg_gradient_start_dark'] : '#1a3a4a';
        $output_group_bg_gradient_end_dark = !empty($color_settings['output_group_bg_gradient_end_dark']) ? $color_settings['output_group_bg_gradient_end_dark'] : '#0f2f3f';
        $output_group_bg_dark = 'linear-gradient(135deg, ' . $output_group_bg_gradient_start_dark . ' 0%, ' . $output_group_bg_gradient_end_dark . ' 100%)';

        $output_group_border_dark = !empty($color_settings['output_group_border_dark']) ? $color_settings['output_group_border_dark'] : $primary_color;
        $output_field_bg_dark = !empty($color_settings['output_field_bg_dark']) ? $color_settings['output_field_bg_dark'] : '#2d2d2d';
        $copy_icon_feedback_color_dark = !empty($color_settings['copy_icon_feedback_color_dark']) ? $color_settings['copy_icon_feedback_color_dark'] : '#34d399';

        $width_css = '';
        switch ($calculator_width_option) {
            case 'contained':
                $width_css = 'max-width: 700px; margin-left: auto; margin-right: auto;';
                break;
            case 'large':
                $width_css = 'max-width: 900px; margin-left: auto; margin-right: auto;';
                break;
            case 'medium':
                $width_css = 'max-width: 600px; margin-left: auto; margin-right: auto;';
                break;
            default:
                $width_css = 'width: 100%;';
                break;
        }

?>
        <style id="ecp-inline-styles">
            :root {
                --ecp-primary-color: <?php echo esc_attr($primary_color); ?>;
                --ecp-secondary-color: <?php echo esc_attr($secondary_color); ?>;

                /* Light Mode Variablen */
                --ecp-background-color: <?php echo esc_attr($background_color_light); ?>;
                --ecp-text-color: <?php echo esc_attr($text_color_light); ?>;
                --ecp-text-light: <?php echo esc_attr($text_light_light); ?>;
                --ecp-border-color: <?php echo esc_attr($border_color_light); ?>;
                --ecp-input-bg: <?php echo esc_attr($input_bg_light); ?>;
                --ecp-input-border: <?php echo esc_attr($input_border_light); ?>;
                --ecp-input-focus-border: var(--ecp-primary-color);
                --ecp-output-group-bg: <?php echo esc_attr($output_group_bg_light); ?>;
                --ecp-output-group-border: <?php echo esc_attr($output_group_border_light); ?>;
                --ecp-output-field-bg: <?php echo esc_attr($output_field_bg_light); ?>;
                --ecp-output-field-color: var(--ecp-primary-color);
                --ecp-output-field-border: var(--ecp-primary-color);
                --ecp-field-group-bg: <?php echo esc_attr($field_group_bg_light); ?>;
                --ecp-field-group-hover-bg: <?php echo esc_attr($field_group_hover_bg_light); ?>;
                --ecp-copy-icon-color: var(--ecp-primary-color);
                --ecp-copy-icon-feedback-color: <?php echo esc_attr($copy_icon_feedback_color_light); ?>;
                
                /* Globale Breite für Kalkulatoren */
                --ecp-calculator-global-max-width: <?php echo ($calculator_width_option === 'full') ? '100%' : esc_attr(str_replace('px', '', $calculator_width_option)) . 'px'; ?>;
                --ecp-calculator-global-margin-top: 30px;
                --ecp-calculator-global-margin-bottom: 30px;
            }

            .ecp-calculator {
                <?php echo $width_css; ?>
                margin-top: var(--ecp-calculator-global-margin-top);
                margin-bottom: var(--ecp-calculator-global-margin-bottom);
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
                0%, 100% {
                    background-position: 0% 50%;
                }
                50% {
                    background-position: 100% 50%;
                }
            }

            <?php if ($enable_system_dark_mode) : ?>
            @media (prefers-color-scheme: dark) {
                :root {
                    --ecp-background-color: <?php echo esc_attr($background_color_dark); ?>;
                    --ecp-text-color: <?php echo esc_attr($text_color_dark); ?>;
                    --ecp-text-light: <?php echo esc_attr($text_light_dark); ?>;
                    --ecp-border-color: <?php echo esc_attr($border_color_dark); ?>;
                    --ecp-input-bg: <?php echo esc_attr($input_bg_dark); ?>;
                    --ecp-input-border: <?php echo esc_attr($input_border_dark); ?>;
                    --ecp-output-group-bg: <?php echo esc_attr($output_group_bg_dark); ?>;
                    --ecp-output-group-border: <?php echo esc_attr($output_group_border_dark); ?>;
                    --ecp-output-field-bg: <?php echo esc_attr($output_field_bg_dark); ?>;
                    --ecp-output-field-color: var(--ecp-secondary-color);
                    --ecp-output-field-border: var(--ecp-secondary-color);
                    --ecp-field-group-bg: <?php echo esc_attr($field_group_bg_dark); ?>;
                    --ecp-field-group-hover-bg: <?php echo esc_attr($field_group_hover_bg_dark); ?>;
                    --ecp-copy-icon-color: var(--ecp-secondary-color);
                    --ecp-copy-icon-feedback-color: <?php echo esc_attr($copy_icon_feedback_color_dark); ?>;
                }
            }
            <?php endif; ?>
        </style>
<?php
    }