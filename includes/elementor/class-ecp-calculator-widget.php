<?php

/**
 * Verbessertes Elementor Widget für Excel Calculator Pro
 *
 * @version 2.0.0
 * @author Samuel Baer
 */

if (!defined('ABSPATH')) {
    exit; // Direkten Zugriff verhindern
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

/**
 * Excel Calculator Pro Elementor Widget
 */
class ECP_Calculator_Widget extends Widget_Base
{
    /**
     * Widget-Name abrufen
     */
    public function get_name()
    {
        return 'ecp_calculator_widget';
    }

    /**
     * Widget-Titel abrufen
     */
    public function get_title()
    {
        return __('Excel Kalkulator', 'excel-calculator-pro');
    }

    /**
     * Widget-Icon abrufen
     */
    public function get_icon()
    {
        return 'eicon-calculator';
    }

    /**
     * Widget-Kategorien abrufen
     */
    public function get_categories()
    {
        return ['excel-calculator-pro'];
    }

    /**
     * Widget-Keywords für bessere Auffindbarkeit
     */
    public function get_keywords()
    {
        return [
            'kalkulator',
            'rechner',
            'excel',
            'berechnung',
            'formular',
            'mathematik',
            'calculator',
            'form',
            'calculation',
            'math',
            'finance',
            'tool'
        ];
    }

    /**
     * Widget-Hilfe-URL
     */
    public function get_help_url()
    {
        return admin_url('options-general.php?page=excel-calculator-pro&tab=formulas');
    }

    /**
     * Widget-Scripts registrieren
     */
    public function get_script_depends()
    {
        // Diese Skripte werden geladen, wenn das Widget verwendet wird.
        return ['ecp-frontend-js'];
    }

    /**
     * Widget-Styles registrieren
     */
    public function get_style_depends()
    {
        // Diese Stylesheets werden geladen, wenn das Widget verwendet wird.
        return ['ecp-frontend-css'];
    }

    /**
     * Widget-Controls registrieren
     */
    protected function register_controls()
    {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Content-Controls registrieren
     */
    protected function register_content_controls()
    {
        // =========================================================================
        // Kalkulator-Auswahl
        // =========================================================================
        $this->start_controls_section(
            'section_calculator_selection',
            [
                'label' => __('Kalkulator', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $calculators = $this->get_available_calculators();

        if (empty($calculators)) {
            $this->add_control(
                'no_calculators_notice',
                [
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => sprintf(
                        '<div class="elementor-panel-alert elementor-panel-alert-warning" style="border-radius: 8px;">
                            <strong>%1$s</strong><br>
                            %2$s <a href="%3$s" target="_blank" style="color: var(--e-color-accent); text-decoration: underline;">%4$s</a>.
                        </div>',
                        __('Keine Kalkulatoren gefunden', 'excel-calculator-pro'),
                        __('Bitte', 'excel-calculator-pro'),
                        admin_url('options-general.php?page=excel-calculator-pro'),
                        __('erstellen Sie zuerst einen Kalkulator', 'excel-calculator-pro')
                    ),
                ]
            );
        } else {
            $this->add_control(
                'calculator_id',
                [
                    'label' => __('Kalkulator wählen', 'excel-calculator-pro'),
                    'type' => Controls_Manager::SELECT,
                    'options' => $calculators,
                    'default' => !empty($calculators) ? array_key_first($calculators) : '',
                    'description' => __('Wählen Sie einen Ihrer gespeicherten Kalkulatoren aus.', 'excel-calculator-pro'),
                    'label_block' => true,
                ]
            );
        }

        $this->end_controls_section();

        // =========================================================================
        // Inhalts-Überschreibungen
        // =========================================================================
        $this->start_controls_section(
            'section_content_overrides',
            [
                'label' => __('Anzeige-Optionen', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'calculator_id!' => '',
                ],
            ]
        );

        $this->add_control(
            'override_title',
            [
                'label' => __('Titel', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Standard (aus Kalkulator)', 'excel-calculator-pro'),
                    'custom' => __('Benutzerdefiniert', 'excel-calculator-pro'),
                    'hide' => __('Ausblenden', 'excel-calculator-pro'),
                ],
            ]
        );

        $this->add_control(
            'custom_title',
            [
                'label' => __('Eigener Titel', 'excel-calculator-pro'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'default' => '',
                'placeholder' => __('Geben Sie Ihren Titel ein', 'excel-calculator-pro'),
                'condition' => [
                    'override_title' => 'custom',
                ],
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'override_description',
            [
                'label' => __('Beschreibung', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Standard (aus Kalkulator)', 'excel-calculator-pro'),
                    'custom' => __('Benutzerdefiniert', 'excel-calculator-pro'),
                    'hide' => __('Ausblenden', 'excel-calculator-pro'),
                ],
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'custom_description',
            [
                'label' => __('Eigene Beschreibung', 'excel-calculator-pro'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '',
                'placeholder' => __('Geben Sie Ihre Beschreibung ein', 'excel-calculator-pro'),
                'rows' => 3,
                'condition' => [
                    'override_description' => 'custom',
                ],
                'dynamic' => ['active' => true],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Style-Controls registrieren
     */
    protected function register_style_controls()
    {
        // =========================================================================
        // Allgemeines Layout
        // =========================================================================
        $this->start_controls_section(
            'section_style_layout',
            [
                'label' => __('Layout & Design', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'theme_style',
            [
                'label' => __('Design-Stil', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => __('Standard', 'excel-calculator-pro'),
                    'compact' => __('Kompakt', 'excel-calculator-pro'),
                    'modern' => __('Modern', 'excel-calculator-pro'), // Überarbeiteter Stil
                    'minimal' => __('Minimalistisch', 'excel-calculator-pro'),
                ],
                'prefix_class' => 'ecp-theme-',
                'render_type' => 'template', // Wichtig für Live-Vorschau von Klassen-Änderungen
                'description' => __('Wählen Sie ein vordefiniertes Design für den Kalkulator.', 'excel-calculator-pro'),
            ]
        );

        $this->add_responsive_control(
            'calculator_width',
            [
                'label' => __('Maximale Breite', 'excel-calculator-pro'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vw'],
                'range' => [
                    'px' => ['min' => 300, 'max' => 1400],
                    '%' => ['min' => 20, 'max' => 100],
                    'vw' => ['min' => 20, 'max' => 100],
                ],
                'default' => ['unit' => '%', 'size' => 100],
                'selectors' => ['{{WRAPPER}} .ecp-calculator' => 'max-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'calculator_alignment',
            [
                'label' => __('Ausrichtung', 'excel-calculator-pro'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => ['title' => __('Links'), 'icon' => 'eicon-h-align-left'],
                    'center' => ['title' => __('Zentriert'), 'icon' => 'eicon-h-align-center'],
                    'right' => ['title' => __('Rechts'), 'icon' => 'eicon-h-align-right'],
                ],
                'default' => 'left',
                'selectors_dictionary' => [
                    'left' => 'margin-left: 0; margin-right: auto;',
                    'center' => 'margin-left: auto; margin-right: auto;',
                    'right' => 'margin-left: auto; margin-right: 0;',
                ],
                'selectors' => ['{{WRAPPER}} .ecp-calculator' => '{{VALUE}}'],
            ]
        );

        $this->end_controls_section();


        // =========================================================================
        // Container-Styling
        // =========================================================================
        $this->start_controls_section(
            'section_style_container',
            [
                'label' => __('Container', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Hintergrund', 'excel-calculator-pro'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .ecp-calculator',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .ecp-calculator',
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Eckenradius', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => ['{{WRAPPER}} .ecp-calculator' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'selector' => '{{WRAPPER}} .ecp-calculator',
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Innenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => ['{{WRAPPER}} .ecp-calculator' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();

        // =========================================================================
        // Eingabefelder-Styling
        // =========================================================================
        $this->start_controls_section(
            'section_style_input_fields',
            [
                'label' => __('Eingabefelder', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control('input_label_color', ['label' => __('Label-Farbe', 'excel-calculator-pro'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .ecp-field-group label' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'input_label_typography', 'selector' => '{{WRAPPER}} .ecp-field-group label']);

        $this->add_control('input_field_color', ['label' => __('Textfarbe', 'excel-calculator-pro'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .ecp-input-field' => 'color: {{VALUE}};'], 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'input_field_typography', 'selector' => '{{WRAPPER}} .ecp-input-field']);

        // **KORREKTUR**: `add_group_control` für den Hintergrund verwenden, um den Bug zu beheben.
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'input_field_background',
                'label' => __('Hintergrund', 'excel-calculator-pro'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .ecp-input-field',
            ]
        );

        $this->add_group_control(Group_Control_Border::get_type(), ['name' => 'input_field_border', 'selector' => '{{WRAPPER}} .ecp-input-field']);
        $this->add_responsive_control('input_field_border_radius', ['label' => __('Eckenradius', 'excel-calculator-pro'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .ecp-input-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);

        $this->end_controls_section();

        // =========================================================================
        // Ergebnisfelder-Styling
        // =========================================================================
        $this->start_controls_section(
            'section_style_output_fields',
            [
                'label' => __('Ergebnisfelder', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control('output_label_color', ['label' => __('Label-Farbe', 'excel-calculator-pro'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .ecp-output-group label' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'output_label_typography', 'selector' => '{{WRAPPER}} .ecp-output-group label']);

        $this->add_control('output_field_color', ['label' => __('Textfarbe', 'excel-calculator-pro'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .ecp-output-field' => 'color: {{VALUE}};'], 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'output_field_typography', 'selector' => '{{WRAPPER}} .ecp-output-field']);

        // **KORREKTUR**: `add_group_control` für den Hintergrund verwenden.
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => 'output_field_background',
                'label' => __('Hintergrund', 'excel-calculator-pro'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .ecp-output-field',
            ]
        );

        $this->add_group_control(Group_Control_Border::get_type(), ['name' => 'output_field_border', 'selector' => '{{WRAPPER}} .ecp-output-field']);
        $this->add_responsive_control('output_field_border_radius', ['label' => __('Eckenradius', 'excel-calculator-pro'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .ecp-output-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);

        $this->end_controls_section();
    }

    /**
     * Verfügbare Kalkulatoren für das Select-Feld abrufen
     */
    private function get_available_calculators()
    {
        $cache_key = 'ecp_elementor_calculators_list';
        $options = wp_cache_get($cache_key, 'ecp');

        if (false === $options) {
            $options = [];
            if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_database')) {
                $db = ecp_init()->get_database();
                $calculators = $db->get_calculators(['orderby' => 'name', 'order' => 'ASC']);
                if (!empty($calculators)) {
                    foreach ($calculators as $calc) {
                        $options[$calc->id] = sprintf('%s (ID: %d)', esc_html($calc->name), $calc->id);
                    }
                }
            }
            wp_cache_set($cache_key, $options, 'ecp', MINUTE_IN_SECONDS * 5);
        }
        return $options;
    }

    /**
     * Prüfen, ob Kalkulatoren vorhanden sind
     */
    private function has_calculators()
    {
        return !empty($this->get_available_calculators());
    }

    /**
     * Frontend-Ausgabe rendern
     */
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $calculator_id = $settings['calculator_id'] ?? '';

        if (empty($calculator_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                $this->render_empty_state();
            }
            return;
        }

        $shortcode_atts = ['id' => $calculator_id];

        if ($settings['override_title'] === 'custom' && !empty($settings['custom_title'])) {
            $shortcode_atts['title'] = $settings['custom_title'];
        } elseif ($settings['override_title'] === 'hide') {
            $shortcode_atts['title'] = 'hide';
        }

        if ($settings['override_description'] === 'custom' && !empty($settings['custom_description'])) {
            $shortcode_atts['description'] = $settings['custom_description'];
        } elseif ($settings['override_description'] === 'hide') {
            $shortcode_atts['description'] = 'hide';
        }

        $atts_string = '';
        foreach ($shortcode_atts as $key => $value) {
            $atts_string .= sprintf(' %s="%s"', $key, esc_attr($value));
        }

        echo do_shortcode('[excel_calculator' . $atts_string . ']');
    }

    /**
     * Leerzustand für den Editor rendern
     */
    private function render_empty_state()
    {
?>
        <div style="text-align: center; padding: 40px 20px; background-color: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px;">
            <i class="eicon-calculator" style="font-size: 48px; margin-bottom: 20px; color: #adb5bd;"></i>
            <h3 style="font-size: 18px; font-weight: 600; margin: 0 0 10px; color: #495057;">
                <?php echo esc_html($this->get_title()); ?>
            </h3>
            <p style="font-size: 14px; line-height: 1.5; color: #6c757d;">
                <?php _e('Bitte wählen Sie einen Kalkulator im Panel links aus.', 'excel-calculator-pro'); ?>
            </p>
        </div>
    <?php
    }

    /**
     * **NEUE VORSCHAU**
     * Editor-Vorlage für die Live-Vorschau (JavaScript-Template)
     */
    protected function content_template()
    {
    ?>
        <#
            // Holt den ausgewählten Kalkulator-Namen aus der Options-Liste
            var calculators=<?php echo json_encode($this->get_available_calculators()); ?>;
            var calculatorId=settings.calculator_id;
            var calculatorName=calculators[calculatorId] || 'Kalkulator auswählen...' ;

            // Titel und Beschreibung für die Vorschau bestimmen
            var previewTitle='' ;
            if (settings.override_title==='custom' ) {
            previewTitle=settings.custom_title || 'Ihr benutzerdefinierter Titel' ;
            } else if (settings.override_title==='auto' ) {
            previewTitle=calculatorName.replace(/ \(ID: \d+\)$/, '' ); // Entfernt '(ID: ...)'
            }

            var previewDescription='' ;
            if (settings.override_description==='custom' ) {
            previewDescription=settings.custom_description || 'Ihre benutzerdefinierte Beschreibung des Rechners.' ;
            } else if (settings.override_description==='auto' ) {
            previewDescription='Dies ist eine Standard-Beschreibung.' ;
            }
            #>
            <div class="ecp-calculator ecp-elementor-live-preview">

                <# if (previewTitle) { #>
                    <div class="ecp-calculator-header">
                        <h3 class="ecp-calculator-title">{{{ previewTitle }}}</h3>
                        <# if (previewDescription) { #>
                            <p class="ecp-calculator-description">{{{ previewDescription }}}</p>
                            <# } #>
                    </div>
                    <# } #>

                        <div class="ecp-section ecp-input-fields">
                            <h4 class="ecp-section-title"><?php _e('Eingaben', 'excel-calculator-pro'); ?></h4>
                            <div class="ecp-field-group">
                                <label>Beispiel-Eingabefeld 1</label>
                                <div class="ecp-input-wrapper">
                                    <input type="number" class="ecp-input-field" placeholder="1.000">
                                    <span class="ecp-input-unit">€</span>
                                </div>
                            </div>
                            <div class="ecp-field-group">
                                <label>Beispiel-Eingabefeld 2</label>
                                <div class="ecp-input-wrapper">
                                    <input type="number" class="ecp-input-field" placeholder="5">
                                    <span class="ecp-input-unit">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="ecp-section ecp-output-fields">
                            <h4 class="ecp-section-title"><?php _e('Ergebnisse', 'excel-calculator-pro'); ?></h4>
                            <div class="ecp-output-group">
                                <label>Ihr Ergebnis</label>
                                <div class="ecp-output-wrapper">
                                    <span class="ecp-output-field">50,00 €</span>
                                </div>
                            </div>
                        </div>
            </div>

            <#
                // Generiere Theme-spezifisches CSS für die Live-Vorschau
                var theme=settings.theme_style || 'default' ;
                var theme_css='' ;
                var selector='.elementor-element.elementor-element-' + view.getID();

                if (theme==='modern' ) {
                theme_css=`
                ${selector} .ecp-calculator.ecp-elementor-live-preview {
                background: #ffffff;
                border: 1px solid #e9ecef;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.05);
                }
                ${selector} .ecp-elementor-live-preview .ecp-section-title {
                border-bottom: none;
                font-size: 13px;
                text-transform: uppercase;
                color: #adb5bd;
                letter-spacing: 0.5px;
                margin-bottom: 15px;
                }
                ${selector} .ecp-elementor-live-preview .ecp-field-group {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                }
                ${selector} .ecp-elementor-live-preview .ecp-output-group {
                background: #e7f5ff;
                border: 1px solid #d0ebff;
                border-radius: 8px;
                }
                ${selector} .ecp-elementor-live-preview .ecp-output-field {
                color: #0056b3;
                font-weight: 600;
                background: transparent;
                }
                `;
                } else if (theme==='compact' ) {
                theme_css=`
                ${selector} .ecp-calculator.ecp-elementor-live-preview { padding: 15px; }
                ${selector} .ecp-elementor-live-preview .ecp-field-group,
                ${selector} .ecp-elementor-live-preview .ecp-output-group { padding: 10px; margin-bottom: 10px; }
                `;
                } else if (theme==='minimal' ) {
                theme_css=`
                ${selector} .ecp-calculator.ecp-elementor-live-preview { border:none; box-shadow:none; background:transparent; padding:0; }
                ${selector} .ecp-elementor-live-preview .ecp-field-group,
                ${selector} .ecp-elementor-live-preview .ecp-output-group { border: none; border-bottom: 1px solid #eee; padding: 15px 0; border-radius: 0; background: transparent; }
                ${selector} .ecp-elementor-live-preview .ecp-input-field { background: transparent; border: none; padding-left:0; padding-right:0; }
                `;
                }
                #>
                <style>{{{ theme_css }}}</style>
        <?php
    }
}
