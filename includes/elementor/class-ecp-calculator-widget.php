<?php

/**
 * Verbessertes Elementor Widget für Excel Calculator Pro
 */

if (!defined('ABSPATH')) {
    exit; // Direkten Zugriff verhindern
}

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
class ECP_Calculator_Widget extends \Elementor\Widget_Base
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
     * Widget unterstützt Inline-Bearbeitung
     */
    public function get_upsale_data()
    {
        return array(
            'condition' => !$this->has_calculators(),
            'image' => ECP_PLUGIN_URL . 'assets/images/elementor-upsale.png',
            'image_alt' => __('Keine Kalkulatoren verfügbar', 'excel-calculator-pro'),
            'title' => __('Erstellen Sie Ihren ersten Kalkulator', 'excel-calculator-pro'),
            'description' => __('Sie benötigen mindestens einen Kalkulator, um dieses Widget zu verwenden.', 'excel-calculator-pro'),
            'upgrade_url' => admin_url('options-general.php?page=excel-calculator-pro'),
            'upgrade_text' => __('Kalkulator erstellen', 'excel-calculator-pro'),
        );
    }

    /**
     * Widget-Scripts registrieren
     */
    public function get_script_depends()
    {
        return ['ecp-frontend-js', 'ecp-elementor-frontend'];
    }

    /**
     * Widget-Styles registrieren
     */
    public function get_style_depends()
    {
        return ['ecp-frontend-css'];
    }

    /**
     * Widget-Controls registrieren
     */
    protected function register_controls()
    {
        $this->register_content_controls();
        $this->register_style_controls();
        $this->register_advanced_controls();
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
                'label' => __('Kalkulator auswählen', 'excel-calculator-pro'),
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
                        '<div class="elementor-panel-alert elementor-panel-alert-warning">
                            <strong>%1$s</strong><br>
                            %2$s <a href="%3$s" target="_blank" style="color: #007cba; text-decoration: underline;">%4$s</a>.
                        </div>',
                        __('Keine Kalkulatoren gefunden.', 'excel-calculator-pro'),
                        __('Bitte', 'excel-calculator-pro'),
                        admin_url('options-general.php?page=excel-calculator-pro'),
                        __('erstellen Sie zuerst einen Kalkulator', 'excel-calculator-pro')
                    ),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
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
                    'description' => __('Wählen Sie aus Ihrer Liste der gespeicherten Kalkulatoren.', 'excel-calculator-pro'),
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
                'label' => __('Inhalts-Anpassungen', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'calculator_id!' => '',
                ],
            ]
        );

        $this->add_control(
            'override_title',
            [
                'label' => __('Titel-Anzeige', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Standard (aus Kalkulator)', 'excel-calculator-pro'),
                    'custom' => __('Benutzerdefinierten Titel', 'excel-calculator-pro'),
                    'hide' => __('Titel ausblenden', 'excel-calculator-pro'),
                ],
                'label_block' => true,
            ]
        );

        $this->add_control(
            'custom_title',
            [
                'label' => __('Benutzerdefinierter Titel', 'excel-calculator-pro'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'default' => '',
                'placeholder' => __('Geben Sie Ihren eigenen Titel ein', 'excel-calculator-pro'),
                'condition' => [
                    'override_title' => 'custom',
                ],
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );

        $this->add_control(
            'override_description',
            [
                'label' => __('Beschreibung-Anzeige', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Standard (aus Kalkulator)', 'excel-calculator-pro'),
                    'custom' => __('Benutzerdefinierte Beschreibung', 'excel-calculator-pro'),
                    'hide' => __('Beschreibung ausblenden', 'excel-calculator-pro'),
                ],
                'label_block' => true,
            ]
        );

        $this->add_control(
            'custom_description',
            [
                'label' => __('Benutzerdefinierte Beschreibung', 'excel-calculator-pro'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '',
                'placeholder' => __('Geben Sie Ihre eigene Beschreibung ein', 'excel-calculator-pro'),
                'rows' => 3,
                'condition' => [
                    'override_description' => 'custom',
                ],
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );

        $this->end_controls_section();

        // =========================================================================
        // Layout-Einstellungen
        // =========================================================================
        $this->start_controls_section(
            'section_layout_settings',
            [
                'label' => __('Layout-Einstellungen', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'calculator_id!' => '',
                ],
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
                    'modern' => __('Modern', 'excel-calculator-pro'),
                    'minimal' => __('Minimal', 'excel-calculator-pro'),
                ],
                'prefix_class' => 'ecp-theme-',
                'description' => __('Verschiedene vordefinierte Design-Stile für das Layout.', 'excel-calculator-pro'),
            ]
        );

        $this->add_responsive_control(
            'calculator_width',
            [
                'label' => __('Breite', 'excel-calculator-pro'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'vw'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 1200,
                    ],
                    '%' => [
                        'min' => 50,
                        'max' => 100,
                    ],
                    'vw' => [
                        'min' => 30,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 100,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'calculator_alignment',
            [
                'label' => __('Ausrichtung', 'excel-calculator-pro'),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Links', 'excel-calculator-pro'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Zentriert', 'excel-calculator-pro'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Rechts', 'excel-calculator-pro'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}}' => 'text-align: {{VALUE}};',
                ],
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
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .ecp-calculator',
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Innenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_margin',
            [
                'label' => __('Außenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
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
                'label' => __('Ecken-Rundung', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'selector' => '{{WRAPPER}} .ecp-calculator',
            ]
        );

        $this->end_controls_section();

        // =========================================================================
        // Header-Styling
        // =========================================================================
        $this->start_controls_section(
            'section_style_header',
            [
                'label' => __('Kopfbereich (Titel & Beschreibung)', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        // Titel-Styling
        $this->add_control(
            'heading_title_style',
            [
                'label' => __('Titel', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Farbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'global' => [
                    'default' => Global_Colors::COLOR_PRIMARY,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
                ],
                'selector' => '{{WRAPPER}} .ecp-calculator-title',
            ]
        );

        $this->add_responsive_control(
            'title_spacing',
            [
                'label' => __('Abstand unten', 'excel-calculator-pro'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Beschreibung-Styling
        $this->add_control(
            'heading_description_style',
            [
                'label' => __('Beschreibung', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Farbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'global' => [
                    'default' => Global_Colors::COLOR_TEXT,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_TEXT,
                ],
                'selector' => '{{WRAPPER}} .ecp-calculator-description',
            ]
        );

        $this->add_responsive_control(
            'description_spacing',
            [
                'label' => __('Abstand unten', 'excel-calculator-pro'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator-description' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
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

        // Eingabe-Gruppen Container
        $this->add_control(
            'heading_input_group_style',
            [
                'label' => __('Eingabe-Container', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'input_group_background',
            [
                'label' => __('Hintergrundfarbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8f9fa',
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group' => 'background: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-field-group' => 'background-color: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-field-group' => 'background-image: none !important;',
                ],
            ]
        );

        $this->add_control(
            'input_group_hover_background',
            [
                'label' => __('Hintergrundfarbe (Hover)', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'default' => '#e9ecef',
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group:hover' => 'background: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-field-group:hover' => 'background-color: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-field-group:hover' => 'background-image: none !important;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_group_border',
                'selector' => '{{WRAPPER}} .ecp-field-group',
            ]
        );

        $this->add_responsive_control(
            'input_group_border_radius',
            [
                'label' => __('Ecken-Rundung', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_group_padding',
            [
                'label' => __('Innenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_group_margin',
            [
                'label' => __('Außenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Labels
        $this->add_control(
            'heading_input_label_style',
            [
                'label' => __('Feldbezeichnungen', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'input_label_color',
            [
                'label' => __('Farbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_label_typography',
                'selector' => '{{WRAPPER}} .ecp-field-group label',
            ]
        );

        $this->add_responsive_control(
            'input_label_spacing',
            [
                'label' => __('Abstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-field-group label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Eingabefelder
        $this->add_control(
            'heading_input_field_style',
            [
                'label' => __('Eingabefelder', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'input_field_color',
            [
                'label' => __('Textfarbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_field_background',
            [
                'label' => __('Hintergrundfarbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_field_typography',
                'selector' => '{{WRAPPER}} .ecp-input-field',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_field_border',
                'selector' => '{{WRAPPER}} .ecp-input-field',
            ]
        );

        $this->add_responsive_control(
            'input_field_border_radius',
            [
                'label' => __('Ecken-Rundung', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_field_padding',
            [
                'label' => __('Innenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Focus-Zustand
        $this->add_control(
            'heading_input_focus_style',
            [
                'label' => __('Fokus-Zustand', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'input_field_focus_border_color',
            [
                'label' => __('Rahmenfarbe (Fokus)', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field:focus' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'input_field_focus_box_shadow',
                'selector' => '{{WRAPPER}} .ecp-input-field:focus',
            ]
        );

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

        // Ausgabe-Gruppen Container
        $this->add_control(
            'heading_output_group_style',
            [
                'label' => __('Ergebnis-Container', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'output_group_background',
            [
                'label' => __('Hintergrundfarbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ecf6fc',
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group' => 'background: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-output-group' => 'background-color: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-output-group' => 'background-image: none !important;',
                ],
            ]
        );

        $this->add_control(
            'output_group_hover_background',
            [
                'label' => __('Hintergrundfarbe (Hover)', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group:hover' => 'background: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-output-group:hover' => 'background-color: {{VALUE}} !important;',
                    '{{WRAPPER}} .ecp-output-group:hover' => 'background-image: none !important;',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'output_group_border',
                'selector' => '{{WRAPPER}} .ecp-output-group',
            ]
        );

        $this->add_responsive_control(
            'output_group_border_radius',
            [
                'label' => __('Ecken-Rundung', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'output_group_padding',
            [
                'label' => __('Innenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'output_group_margin',
            [
                'label' => __('Außenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Labels
        $this->add_control(
            'heading_output_label_style',
            [
                'label' => __('Bezeichnungen', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'output_label_color',
            [
                'label' => __('Farbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'output_label_typography',
                'selector' => '{{WRAPPER}} .ecp-output-group label',
            ]
        );

        // Ergebnisfelder
        $this->add_control(
            'heading_output_field_style',
            [
                'label' => __('Ergebnisse', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'output_field_color',
            [
                'label' => __('Textfarbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'global' => [
                    'default' => Global_Colors::COLOR_ACCENT,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-field' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'output_field_background',
            [
                'label' => __('Hintergrundfarbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-field' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'output_field_typography',
                'selector' => '{{WRAPPER}} .ecp-output-field',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'output_field_border',
                'selector' => '{{WRAPPER}} .ecp-output-field',
            ]
        );

        $this->add_responsive_control(
            'output_field_border_radius',
            [
                'label' => __('Ecken-Rundung', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'output_field_padding',
            [
                'label' => __('Innenabstand', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-field' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'output_field_box_shadow',
                'selector' => '{{WRAPPER}} .ecp-output-field',
            ]
        );

        $this->end_controls_section();

        // =========================================================================
        // Sektions-Styling
        // =========================================================================
        $this->start_controls_section(
            'section_style_sections',
            [
                'label' => __('Sektionen', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'section_title_color',
            [
                'label' => __('Titel-Farbe', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-section-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'section_title_typography',
                'selector' => '{{WRAPPER}} .ecp-section-title',
            ]
        );

        $this->add_responsive_control(
            'section_spacing',
            [
                'label' => __('Abstand zwischen Sektionen', 'excel-calculator-pro'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .ecp-section' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Erweiterte Controls registrieren
     */
    protected function register_advanced_controls()
    {
        // =========================================================================
        // Animation & Effekte
        // =========================================================================
        $this->start_controls_section(
            'section_advanced_animation',
            [
                'label' => __('Animation & Effekte', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_ADVANCED,
            ]
        );

        $this->add_control(
            'enable_animations',
            [
                'label' => __('Animationen aktivieren', 'excel-calculator-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Ein', 'excel-calculator-pro'),
                'label_off' => __('Aus', 'excel-calculator-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
                'prefix_class' => 'ecp-animations-',
            ]
        );

        $this->add_control(
            'hover_effects',
            [
                'label' => __('Hover-Effekte', 'excel-calculator-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Ein', 'excel-calculator-pro'),
                'label_off' => __('Aus', 'excel-calculator-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
                'prefix_class' => 'ecp-hover-',
            ]
        );

        $this->add_control(
            'loading_animation',
            [
                'label' => __('Lade-Animation', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'fade',
                'options' => [
                    'none' => __('Keine', 'excel-calculator-pro'),
                    'fade' => __('Einblenden', 'excel-calculator-pro'),
                    'slide' => __('Gleiten', 'excel-calculator-pro'),
                    'bounce' => __('Hüpfen', 'excel-calculator-pro'),
                ],
                'prefix_class' => 'ecp-loading-',
            ]
        );

        $this->end_controls_section();

        // =========================================================================
        // Barrierefreiheit
        // =========================================================================
        $this->start_controls_section(
            'section_advanced_accessibility',
            [
                'label' => __('Barrierefreiheit', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_ADVANCED,
            ]
        );

        $this->add_control(
            'aria_labels',
            [
                'label' => __('ARIA-Labels aktivieren', 'excel-calculator-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Ein', 'excel-calculator-pro'),
                'label_off' => __('Aus', 'excel-calculator-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Verbessert die Barrierefreiheit für Screenreader.', 'excel-calculator-pro'),
            ]
        );

        $this->add_control(
            'keyboard_navigation',
            [
                'label' => __('Tastatur-Navigation', 'excel-calculator-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Ein', 'excel-calculator-pro'),
                'label_off' => __('Aus', 'excel-calculator-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Ermöglicht Navigation mit Tab-Taste.', 'excel-calculator-pro'),
            ]
        );

        $this->add_control(
            'high_contrast',
            [
                'label' => __('Hoher Kontrast', 'excel-calculator-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Ein', 'excel-calculator-pro'),
                'label_off' => __('Aus', 'excel-calculator-pro'),
                'return_value' => 'yes',
                'default' => '',
                'prefix_class' => 'ecp-high-contrast-',
                'description' => __('Verbessert die Lesbarkeit für sehbeeinträchtigte Benutzer.', 'excel-calculator-pro'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Verfügbare Kalkulatoren abrufen
     */
    private function get_available_calculators()
    {
        $options = [];

        // Cache für bessere Performance
        $cache_key = 'ecp_elementor_calculators';
        $cached_options = wp_cache_get($cache_key);

        if ($cached_options !== false) {
            return $cached_options;
        }

        try {
            if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_database')) {
                $db_handler = ecp_init()->get_database();
                if ($db_handler) {
                    $calculators = $db_handler->get_calculators(['orderby' => 'name', 'order' => 'ASC']);
                    if (!empty($calculators)) {
                        foreach ($calculators as $calc) {
                            $options[$calc->id] = sprintf(
                                '%s (ID: %d)',
                                esc_html($calc->name),
                                $calc->id
                            );
                        }
                    }
                }
            }

            // Cache für 5 Minuten
            wp_cache_set($cache_key, $options, '', 300);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ECP Elementor Widget: Fehler beim Laden der Kalkulatoren - ' . $e->getMessage());
            }
        }

        return $options;
    }

    /**
     * Prüfen ob Kalkulatoren vorhanden sind
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

        // Debug-Information für Entwickler
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            echo '<!-- ECP Elementor Widget Debug: Calculator ID = ' . esc_html($calculator_id) . ' -->';
        }

        if (empty($calculator_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                $this->render_empty_state();
            }
            return;
        }

        // Shortcode-Attribute aufbauen
        $shortcode_atts = $this->build_shortcode_attributes($settings);

        // Widget-Container mit erweiterten Klassen
        $widget_classes = $this->get_widget_classes($settings);
        echo '<div class="' . esc_attr(implode(' ', $widget_classes)) . '">';

        // Kalkulator rendern
        $this->render_calculator($shortcode_atts, $settings);

        echo '</div>';

        // Theme-spezifische CSS-Stile hinzufügen
        $this->add_theme_styles($settings);
    }

    /**
     * Theme-spezifische CSS-Stile hinzufügen
     */
    private function add_theme_styles($settings)
    {
        $theme = $settings['theme_style'] ?? 'default';

        if ($theme === 'default') {
            return; // Keine zusätzlichen Styles für Standard
        }

        $widget_id = $this->get_id();
        $css = $this->generate_theme_css($theme, $widget_id);

        if (!empty($css)) {
            echo '<style>' . $css . '</style>';
        }
    }

    /**
     * Theme-CSS generieren
     */
    private function generate_theme_css($theme, $widget_id)
    {
        $selector = '.elementor-element-' . $widget_id;
        $css = '';

        switch ($theme) {
            case 'compact':
                $css = "
                    {$selector} .ecp-calculator {
                        padding: 20px;
                    }
                    {$selector} .ecp-field-group,
                    {$selector} .ecp-output-group {
                        padding: 12px;
                        margin-bottom: 12px;
                    }
                    {$selector} .ecp-calculator-title {
                        font-size: 20px;
                        margin-bottom: 15px;
                    }
                    {$selector} .ecp-section-title {
                        font-size: 16px;
                        margin-bottom: 12px;
                    }
                    {$selector} .ecp-input-field,
                    {$selector} .ecp-output-field {
                        padding: 8px 12px;
                        font-size: 14px;
                    }
                ";
                break;

            case 'modern':
                $css = "
                    {$selector} .ecp-calculator {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        border-radius: 16px;
                        padding: 35px;
                    }
                    {$selector} .ecp-calculator-title,
                    {$selector} .ecp-section-title {
                        color: white;
                    }
                    {$selector} .ecp-field-group,
                    {$selector} .ecp-output-group {
                        background: rgba(255, 255, 255, 0.1);
                        border: 1px solid rgba(255, 255, 255, 0.2);
                        border-radius: 12px;
                        backdrop-filter: blur(10px);
                    }
                    {$selector} .ecp-field-group label,
                    {$selector} .ecp-output-group label {
                        color: rgba(255, 255, 255, 0.9);
                    }
                    {$selector} .ecp-input-field {
                        background: rgba(255, 255, 255, 0.9);
                        border: none;
                        border-radius: 8px;
                    }
                    {$selector} .ecp-output-field {
                        background: rgba(255, 255, 255, 0.15);
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        color: white;
                        font-weight: 600;
                    }
                ";
                break;

            case 'minimal':
                $css = "
                    {$selector} .ecp-calculator {
                        border: none;
                        box-shadow: none;
                        background: transparent;
                        padding: 0;
                    }
                    {$selector} .ecp-calculator-header {
                        border-bottom: 2px solid #e0e0e0;
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                    }
                    {$selector} .ecp-field-group,
                    {$selector} .ecp-output-group {
                        background: transparent;
                        border: none;
                        border-bottom: 1px solid #e0e0e0;
                        border-radius: 0;
                        padding: 15px 0;
                        margin-bottom: 0;
                    }
                    {$selector} .ecp-input-field {
                        border: none;
                        border-bottom: 2px solid #e0e0e0;
                        border-radius: 0;
                        background: transparent;
                        padding: 10px 0;
                    }
                    {$selector} .ecp-input-field:focus {
                        border-bottom-color: #007cba;
                        box-shadow: none;
                    }
                    {$selector} .ecp-output-field {
                        border: none;
                        background: transparent;
                        font-size: 18px;
                        font-weight: 700;
                        color: #007cba;
                    }
                    {$selector} .ecp-section-title {
                        font-size: 14px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                        color: #666;
                        border-bottom: none;
                        margin-bottom: 20px;
                    }
                ";
                break;
        }

        return $css;
    }

    /**
     * Shortcode-Attribute aufbauen
     */
    private function build_shortcode_attributes($settings)
    {
        $shortcode_atts = ['id' => $settings['calculator_id']];

        // Titel-Überschreibung
        if ($settings['override_title'] === 'custom' && !empty($settings['custom_title'])) {
            $shortcode_atts['title'] = $settings['custom_title'];
        } elseif ($settings['override_title'] === 'hide') {
            $shortcode_atts['title'] = 'hide';
        }

        // Beschreibung-Überschreibung
        if ($settings['override_description'] === 'custom' && !empty($settings['custom_description'])) {
            $shortcode_atts['description'] = $settings['custom_description'];
        } elseif ($settings['override_description'] === 'hide') {
            $shortcode_atts['description'] = 'hide';
        }

        // Theme-Stil
        if (!empty($settings['theme_style']) && $settings['theme_style'] !== 'default') {
            $shortcode_atts['theme'] = $settings['theme_style'];
        }

        return $shortcode_atts;
    }

    /**
     * Widget-CSS-Klassen generieren
     */
    private function get_widget_classes($settings)
    {
        $classes = ['ecp-elementor-widget'];

        // Theme-Klassen
        if (!empty($settings['theme_style'])) {
            $classes[] = 'ecp-theme-' . $settings['theme_style'];
        }

        // Animation-Klassen
        if ($settings['enable_animations'] === 'yes') {
            $classes[] = 'ecp-animations-enabled';
        }

        if ($settings['hover_effects'] === 'yes') {
            $classes[] = 'ecp-hover-enabled';
        }

        // Barrierefreiheit-Klassen
        if ($settings['high_contrast'] === 'yes') {
            $classes[] = 'ecp-high-contrast';
        }

        return $classes;
    }

    /**
     * Kalkulator rendern
     */
    private function render_calculator($shortcode_atts, $settings)
    {
        try {
            // Primärer Renderer über Shortcode-Handler
            if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_shortcode')) {
                $shortcode_handler = ecp_init()->get_shortcode();
                if ($shortcode_handler && method_exists($shortcode_handler, 'calculator_shortcode')) {
                    echo $shortcode_handler->calculator_shortcode($shortcode_atts);
                    return;
                }
            }

            // Fallback über do_shortcode
            $this->render_fallback_shortcode($shortcode_atts);
        } catch (Exception $e) {
            $this->render_error_state($e->getMessage());
        }
    }

    /**
     * Fallback-Shortcode rendern
     */
    private function render_fallback_shortcode($shortcode_atts)
    {
        $atts_string = '';
        foreach ($shortcode_atts as $key => $value) {
            $atts_string .= sprintf(' %s="%s"', $key, esc_attr($value));
        }

        echo do_shortcode('[excel_calculator' . $atts_string . ']');
    }

    /**
     * Leerzustand rendern
     */
    private function render_empty_state()
    {
?>
        <div class="ecp-elementor-empty-state">
            <div class="ecp-empty-icon">
                <i class="<?php echo esc_attr($this->get_icon()); ?>"></i>
            </div>
            <div class="ecp-empty-title">
                <?php echo esc_html($this->get_title()); ?>
            </div>
            <div class="ecp-empty-message">
                <?php if ($this->has_calculators()): ?>
                    <?php _e('Bitte wählen Sie einen Kalkulator aus dem Panel aus.', 'excel-calculator-pro'); ?>
                <?php else: ?>
                    <?php printf(
                        __('Keine Kalkulatoren verfügbar. <a href="%s" target="_blank">Erstellen Sie zuerst einen Kalkulator</a>.', 'excel-calculator-pro'),
                        admin_url('options-general.php?page=excel-calculator-pro')
                    ); ?>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .ecp-elementor-empty-state {
                text-align: center;
                padding: 40px 20px;
                background-color: #f8f9fa;
                border: 2px dashed #dee2e6;
                border-radius: 8px;
                color: #6c757d;
            }

            .ecp-empty-icon {
                font-size: 48px;
                margin-bottom: 20px;
                opacity: 0.7;
            }

            .ecp-empty-title {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 10px;
                color: #495057;
            }

            .ecp-empty-message {
                font-size: 14px;
                line-height: 1.5;
            }

            .ecp-empty-message a {
                color: #007cba;
                text-decoration: underline;
            }
        </style>
        <?php
    }

    /**
     * Fehlerzustand rendern
     */
    private function render_error_state($error_message)
    {
        if (\Elementor\Plugin::$instance->editor->is_edit_mode() || (defined('WP_DEBUG') && WP_DEBUG)) {
        ?>
            <div class="ecp-elementor-error-state">
                <div class="ecp-error-icon">⚠️</div>
                <div class="ecp-error-title"><?php _e('Fehler beim Laden des Kalkulators', 'excel-calculator-pro'); ?></div>
                <div class="ecp-error-message"><?php echo esc_html($error_message); ?></div>
            </div>

            <style>
                .ecp-elementor-error-state {
                    text-align: center;
                    padding: 30px 20px;
                    background-color: #fff3cd;
                    border: 2px solid #ffeaa7;
                    border-radius: 8px;
                    color: #856404;
                }

                .ecp-error-icon {
                    font-size: 32px;
                    margin-bottom: 15px;
                }

                .ecp-error-title {
                    font-size: 16px;
                    font-weight: 600;
                    margin-bottom: 8px;
                }

                .ecp-error-message {
                    font-size: 14px;
                    font-family: monospace;
                }
            </style>
        <?php
        }
    }

    /**
     * Editor-Vorlage rendern (für Live-Bearbeitung)
     */
    protected function content_template()
    {
        ?>
        <#
            var calculatorId=settings.calculator_id;
            var calculatorName='' ;
            var hasCalculators=<?php echo json_encode($this->has_calculators()); ?>;

            // Kalkulator-Name aus Options holen
            var calculators=<?php echo json_encode($this->get_available_calculators()); ?>;
            if (calculators[calculatorId]) {
            calculatorName=calculators[calculatorId];
            }
            #>

            <# if (!calculatorId && hasCalculators) { #>
                <div class="ecp-elementor-empty-state">
                    <div class="ecp-empty-icon"><i class="<?php echo esc_attr($this->get_icon()); ?>"></i></div>
                    <div class="ecp-empty-title"><?php echo esc_js($this->get_title()); ?></div>
                    <div class="ecp-empty-message"><?php esc_html_e('Bitte wählen Sie einen Kalkulator aus dem Panel aus.', 'excel-calculator-pro'); ?></div>
                </div>
                <# } else if (!hasCalculators) { #>
                    <div class="ecp-elementor-empty-state">
                        <div class="ecp-empty-icon"><i class="<?php echo esc_attr($this->get_icon()); ?>"></i></div>
                        <div class="ecp-empty-title"><?php echo esc_js($this->get_title()); ?></div>
                        <div class="ecp-empty-message">
                            <?php printf(
                                esc_js(__('Keine Kalkulatoren verfügbar. <a href="%s" target="_blank">Erstellen Sie zuerst einen Kalkulator</a>.', 'excel-calculator-pro')),
                                admin_url('options-general.php?page=excel-calculator-pro')
                            ); ?>
                        </div>
                    </div>
                    <# } else { #>
                        <div class="ecp-elementor-preview">
                            <div class="ecp-preview-header">
                                <div class="ecp-preview-icon">
                                    <i class="<?php echo esc_attr($this->get_icon()); ?>"></i>
                                </div>
                                <div class="ecp-preview-info">
                                    <h3 class="ecp-preview-title">{{{ calculatorName }}}</h3>
                                    <p class="ecp-preview-subtitle">
                                        <?php esc_html_e('Kalkulator-ID:', 'excel-calculator-pro'); ?> {{ calculatorId }}
                                        <# if (settings.theme_style && settings.theme_style !=='default' ) { #>
                                            | <?php esc_html_e('Design:', 'excel-calculator-pro'); ?> {{{ settings.theme_style }}}
                                            <# } #>
                                    </p>
                                </div>
                            </div>

                            <div class="ecp-preview-content">
                                <div class="ecp-preview-section">
                                    <h4><?php esc_html_e('Konfiguration:', 'excel-calculator-pro'); ?></h4>
                                    <ul class="ecp-preview-config">
                                        <# if (settings.override_title==='custom' && settings.custom_title) { #>
                                            <li><?php esc_html_e('Titel:', 'excel-calculator-pro'); ?> {{{ settings.custom_title }}}</li>
                                            <# } else if (settings.override_title==='hide' ) { #>
                                                <li><?php esc_html_e('Titel: Ausgeblendet', 'excel-calculator-pro'); ?></li>
                                                <# } #>

                                                    <# if (settings.override_description==='custom' && settings.custom_description) { #>
                                                        <li><?php esc_html_e('Beschreibung:', 'excel-calculator-pro'); ?> {{{ settings.custom_description }}}</li>
                                                        <# } else if (settings.override_description==='hide' ) { #>
                                                            <li><?php esc_html_e('Beschreibung: Ausgeblendet', 'excel-calculator-pro'); ?></li>
                                                            <# } #>
                                    </ul>
                                </div>

                                <div class="ecp-preview-note">
                                    <i class="eicon-info-circle"></i>
                                    <?php esc_html_e('Die vollständige Kalkulator-Vorschau wird im Frontend angezeigt.', 'excel-calculator-pro'); ?>
                                </div>
                            </div>
                        </div>
                        <# } #>

                            <style>
                                .ecp-elementor-empty-state {
                                    text-align: center;
                                    padding: 40px 20px;
                                    background-color: #f8f9fa;
                                    border: 2px dashed #dee2e6;
                                    border-radius: 8px;
                                    color: #6c757d;
                                }

                                .ecp-empty-icon {
                                    font-size: 48px;
                                    margin-bottom: 20px;
                                    opacity: 0.7;
                                }

                                .ecp-empty-title {
                                    font-size: 18px;
                                    font-weight: 600;
                                    margin-bottom: 10px;
                                    color: #495057;
                                }

                                .ecp-empty-message {
                                    font-size: 14px;
                                    line-height: 1.5;
                                }

                                .ecp-elementor-preview {
                                    background: #fff;
                                    border: 1px solid #e0e0e0;
                                    border-radius: 8px;
                                    overflow: hidden;
                                }

                                .ecp-preview-header {
                                    background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
                                    color: white;
                                    padding: 20px;
                                    display: flex;
                                    align-items: center;
                                    gap: 15px;
                                }

                                .ecp-preview-icon {
                                    font-size: 24px;
                                    background: rgba(255, 255, 255, 0.2);
                                    width: 50px;
                                    height: 50px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    border-radius: 8px;
                                }

                                .ecp-preview-info {
                                    flex: 1;
                                }

                                .ecp-preview-title {
                                    margin: 0 0 5px 0;
                                    font-size: 18px;
                                    font-weight: 600;
                                }

                                .ecp-preview-subtitle {
                                    margin: 0;
                                    font-size: 12px;
                                    opacity: 0.9;
                                }

                                .ecp-preview-content {
                                    padding: 20px;
                                }

                                .ecp-preview-section {
                                    margin-bottom: 20px;
                                }

                                .ecp-preview-section h4 {
                                    margin: 0 0 10px 0;
                                    font-size: 14px;
                                    font-weight: 600;
                                    color: #333;
                                }

                                .ecp-preview-config {
                                    list-style: none;
                                    padding: 0;
                                    margin: 0;
                                    background: #f8f9fa;
                                    border-radius: 4px;
                                    padding: 12px;
                                }

                                .ecp-preview-config li {
                                    font-size: 13px;
                                    color: #666;
                                    margin-bottom: 5px;
                                }

                                .ecp-preview-config li:last-child {
                                    margin-bottom: 0;
                                }

                                .ecp-preview-note {
                                    background: #e7f3ff;
                                    border: 1px solid #b3d7ff;
                                    border-radius: 4px;
                                    padding: 12px;
                                    font-size: 13px;
                                    color: #0066cc;
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                }

                                .ecp-preview-note i {
                                    font-size: 16px;
                                }
                            </style>
                    <?php
                }
            }
