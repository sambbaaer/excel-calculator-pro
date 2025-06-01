<?php
/**
 * Shortcode Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Shortcode {
    
    /**
     * Database-Handler
     */
    private $database;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->database = ecp_init()->get_database();
        $this->init_shortcodes();
    }
    
    /**
     * Shortcodes registrieren
     */
    private function init_shortcodes() {
        add_shortcode('excel_calculator', array($this, 'calculator_shortcode'));
        add_shortcode('ecp_calculator', array($this, 'calculator_shortcode')); // Alias
    }
    
    /**
     * Hauptshortcode für Kalkulatoren
     */
    public function calculator_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'id' => '',
            'title' => 'auto', // auto, hide, custom
            'description' => 'auto', // auto, hide, custom
            'theme' => 'default', // default, compact, modern
            'width' => 'auto',
            'class' => ''
        ), $atts, 'excel_calculator');
        
        // Validierung
        if (empty($atts['id'])) {
            return $this->error_message(__('Fehler: Kalkulator-ID fehlt', 'excel-calculator-pro'));
        }
        
        // Kalkulator laden
        $calculator = $this->database->get_calculator(intval($atts['id']));
        
        if (!$calculator) {
            return $this->error_message(__('Fehler: Kalkulator nicht gefunden', 'excel-calculator-pro'));
        }
        
        // Ausgabe generieren
        return $this->render_calculator($calculator, $atts);
    }
    
    /**
     * Kalkulator rendern
     */
    private function render_calculator($calculator, $atts) {
        // CSS-Klassen zusammenstellen
        $css_classes = array('ecp-calculator');
        
        if (!empty($atts['theme']) && $atts['theme'] !== 'default') {
            $css_classes[] = 'ecp-theme-' . sanitize_html_class($atts['theme']);
        }
        
        if (!empty($atts['class'])) {
            $css_classes[] = sanitize_html_class($atts['class']);
        }
        
        // Inline-Styles
        $inline_styles = array();
        if (!empty($atts['width']) && $atts['width'] !== 'auto') {
            $inline_styles[] = 'max-width: ' . esc_attr($atts['width']);
        }
        
        $style_attr = !empty($inline_styles) ? ' style="' . implode('; ', $inline_styles) . '"' : '';
        
        // Ausgabe starten
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $css_classes)); ?>" 
             data-calculator-id="<?php echo esc_attr($calculator->id); ?>"<?php echo $style_attr; ?>>
            
            <?php
            // Header anzeigen
            if ($this->should_show_header($atts, $calculator)) {
                $this->render_header($calculator, $atts);
            }
            
            // Eingabefelder anzeigen
            if (!empty($calculator->fields)) {
                $this->render_input_fields($calculator->fields);
            }
            
            // Ausgabefelder anzeigen
            if (!empty($calculator->formulas)) {
                $this->render_output_fields($calculator->formulas);
            }
            ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Header anzeigen prüfen
     */
    private function should_show_header($atts, $calculator) {
        if ($atts['title'] === 'hide' && $atts['description'] === 'hide') {
            return false;
        }
        
        if ($atts['title'] === 'auto' && empty($calculator->name)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Header rendern
     */
    private function render_header($calculator, $atts) {
        ?>
        <div class="ecp-calculator-header">
            <?php
            // Titel
            if ($atts['title'] !== 'hide') {
                $title = '';
                
                if ($atts['title'] === 'auto') {
                    $title = $calculator->name;
                } elseif (!empty($atts['title'])) {
                    $title = $atts['title'];
                }
                
                if (!empty($title)) {
                    echo '<h3 class="ecp-calculator-title">' . esc_html($title) . '</h3>';
                }
            }
            
            // Beschreibung
            if ($atts['description'] !== 'hide') {
                $description = '';
                
                if ($atts['description'] === 'auto') {
                    $description = $calculator->description;
                } elseif (!empty($atts['description'])) {
                    $description = $atts['description'];
                }
                
                if (!empty($description)) {
                    echo '<p class="ecp-calculator-description">' . esc_html($description) . '</p>';
                }
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Eingabefelder rendern
     */
    private function render_input_fields($fields) {
        ?>
        <div class="ecp-section ecp-input-fields">
            <h4 class="ecp-section-title"><?php _e('Eingaben', 'excel-calculator-pro'); ?></h4>
            
            <?php foreach ($fields as $field): ?>
                <div class="ecp-field-group" data-field-id="<?php echo esc_attr($field['id']); ?>">
                    <label for="ecp-field-<?php echo esc_attr($field['id']); ?>">
                        <?php echo esc_html($field['label']); ?>
                        <?php if (isset($field['required']) && $field['required']): ?>
                            <span class="ecp-required">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <div class="ecp-input-wrapper">
                        <?php
                        $input_type = isset($field['type']) ? $field['type'] : 'number';
                        $default_value = isset($field['default']) ? $field['default'] : '';
                        $min = isset($field['min']) ? $field['min'] : '';
                        $max = isset($field['max']) ? $field['max'] : '';
                        $step = isset($field['step']) ? $field['step'] : 'any';
                        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
                        ?>
                        
                        <input type="<?php echo esc_attr($input_type); ?>" 
                               id="ecp-field-<?php echo esc_attr($field['id']); ?>" 
                               class="ecp-input-field" 
                               data-field-id="<?php echo esc_attr($field['id']); ?>" 
                               value="<?php echo esc_attr($default_value); ?>"
                               <?php if (!empty($min)): ?>min="<?php echo esc_attr($min); ?>"<?php endif; ?>
                               <?php if (!empty($max)): ?>max="<?php echo esc_attr($max); ?>"<?php endif; ?>
                               <?php if (!empty($step)): ?>step="<?php echo esc_attr($step); ?>"<?php endif; ?>
                               <?php if (!empty($placeholder)): ?>placeholder="<?php echo esc_attr($placeholder); ?>"<?php endif; ?>
                               <?php if (isset($field['required']) && $field['required']): ?>required<?php endif; ?>
                               />
                        
                        <?php if (isset($field['unit']) && !empty($field['unit'])): ?>
                            <span class="ecp-input-unit"><?php echo esc_html($field['unit']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($field['help']) && !empty($field['help'])): ?>
                        <div class="ecp-field-help"><?php echo esc_html($field['help']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Ausgabefelder rendern
     */
    private function render_output_fields($formulas) {
        ?>
        <div class="ecp-section ecp-output-fields">
            <h4 class="ecp-section-title"><?php _e('Ergebnisse', 'excel-calculator-pro'); ?></h4>
            
            <?php foreach ($formulas as $index => $formula): ?>
                <div class="ecp-output-group" data-output-id="<?php echo esc_attr($index); ?>">
                    <label>
                        <?php echo esc_html($formula['label']); ?>
                        <?php if (isset($formula['help']) && !empty($formula['help'])): ?>
                            <span class="ecp-output-help" title="<?php echo esc_attr($formula['help']); ?>">ℹ️</span>
                        <?php endif; ?>
                    </label>
                    
                    <div class="ecp-output-wrapper">
                        <span class="ecp-output-field" 
                              data-formula="<?php echo esc_attr($formula['formula']); ?>" 
                              data-format="<?php echo esc_attr($formula['format'] ?? ''); ?>"
                              data-output-id="<?php echo esc_attr($index); ?>">
                            0
                        </span>
                        
                        <?php if (isset($formula['unit']) && !empty($formula['unit'])): ?>
                            <span class="ecp-output-unit"><?php echo esc_html($formula['unit']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')): ?>
                        <div class="ecp-formula-debug">
                            <?php echo esc_html($formula['formula']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Fehlermeldung ausgeben
     */
    private function error_message($message) {
        if (current_user_can('manage_options')) {
            return '<div class="ecp-error" style="color: #dc3545; font-weight: bold; padding: 10px; border: 1px solid #dc3545; border-radius: 4px; background: #f8d7da;">' . esc_html($message) . '</div>';
        }
        return '<!-- Excel Calculator Pro: ' . esc_html($message) . ' -->';
    }
    
    /**
     * Shortcode in TinyMCE-Editor hinzufügen
     */
    public function add_tinymce_button() {
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            return;
        }
        
        if (get_user_option('rich_editing') !== 'true') {
            return;
        }
        
        add_filter('mce_external_plugins', array($this, 'add_tinymce_plugin'));
        add_filter('mce_buttons', array($this, 'register_tinymce_button'));
    }
    
    /**
     * TinyMCE-Plugin hinzufügen
     */
    public function add_tinymce_plugin($plugin_array) {
        $plugin_array['ecp_shortcode'] = ECP_PLUGIN_URL . 'assets/tinymce-plugin.js';
        return $plugin_array;
    }
    
    /**
     * TinyMCE-Button registrieren
     */
    public function register_tinymce_button($buttons) {
        array_push($buttons, 'ecp_shortcode');
        return $buttons;
    }
    
    /**
     * Lokalisierung für TinyMCE
     */
    public function tinymce_localization() {
        $calculators = $this->database->get_calculators();
        $calculator_options = array();
        
        foreach ($calculators as $calc) {
            $calculator_options[] = array(
                'value' => $calc->id,
                'text' => $calc->name
            );
        }
        
        wp_localize_script('editor', 'ecp_tinymce', array(
            'title' => __('Excel Calculator Pro einfügen', 'excel-calculator-pro'),
            'calculators' => $calculator_options,
            'no_calculators' => __('Keine Kalkulatoren verfügbar', 'excel-calculator-pro')
        ));
    }
}