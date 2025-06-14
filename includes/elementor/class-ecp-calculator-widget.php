<?php
// /includes/elementor/class-ecp-calculator-widget.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Elementor Widget for Excel Calculator Pro.
 */
class ECP_Calculator_Widget extends \Elementor\Widget_Base
{

    /**
     * Get widget name.
     */
    public function get_name()
    {
        return 'ecp_calculator_widget';
    }

    /**
     * Get widget title.
     */
    public function get_title()
    {
        return __('Excel Calculator', 'excel-calculator-pro');
    }

    /**
     * Get widget icon.
     */
    public function get_icon()
    {
        return 'eicon-calculator'; // Passenderes Icon
    }

    /**
     * Get widget categories.
     */
    public function get_categories()
    {
        return ['excel-calculator-pro'];
    }

    /**
     * Get widget keywords.
     */
    public function get_keywords()
    {
        return ['calculator', 'excel', 'form', 'berechnung', 'rechner'];
    }

    /**
     * Register widget controls.
     */
    protected function _register_controls()
    {
        // =========================================================================
        // Content Tab
        // =========================================================================

        $this->start_controls_section(
            'section_calculator_selection',
            [
                'label' => __('Calculator Selection', 'excel-calculator-pro'),
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
                        '<strong>%1$s</strong><br>%2$s <a href="%3$s" target="_blank" style="color: #007cba;">%4$s</a>.',
                        __('No calculators found.', 'excel-calculator-pro'),
                        __('Please', 'excel-calculator-pro'),
                        admin_url('options-general.php?page=excel-calculator-pro'),
                        __('create a calculator first', 'excel-calculator-pro')
                    ),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
                ]
            );
        } else {
            $this->add_control(
                'calculator_id',
                [
                    'label' => __('Select Calculator', 'excel-calculator-pro'),
                    'type' => Controls_Manager::SELECT,
                    'options' => $calculators,
                    'default' => !empty($calculators) ? array_key_first($calculators) : '',
                    'description' => __('Choose from your list of saved calculators.', 'excel-calculator-pro'),
                ]
            );
        }

        $this->end_controls_section();

        // -- Override Section --
        $this->start_controls_section(
            'section_overrides',
            [
                'label' => __('Content Overrides', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'calculator_id!' => '',
                ],
            ]
        );

        $this->add_control(
            'override_title',
            [
                'label' => __('Title Display', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Default from Calculator', 'excel-calculator-pro'),
                    'custom' => __('Custom Title', 'excel-calculator-pro'),
                    'hide' => __('Hide Title', 'excel-calculator-pro'),
                ],
            ]
        );

        $this->add_control(
            'custom_title',
            [
                'label' => __('Custom Title', 'excel-calculator-pro'),
                'type' => Controls_Manager::TEXT,
                'label_block' => true,
                'default' => '',
                'placeholder' => __('Enter your custom title', 'excel-calculator-pro'),
                'condition' => [
                    'override_title' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'override_description',
            [
                'label' => __('Description Display', 'excel-calculator-pro'),
                'type' => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Default from Calculator', 'excel-calculator-pro'),
                    'custom' => __('Custom Description', 'excel-calculator-pro'),
                    'hide' => __('Hide Description', 'excel-calculator-pro'),
                ],
            ]
        );

        $this->add_control(
            'custom_description',
            [
                'label' => __('Custom Description', 'excel-calculator-pro'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '',
                'placeholder' => __('Enter your custom description', 'excel-calculator-pro'),
                'condition' => [
                    'override_description' => 'custom',
                ],
            ]
        );

        $this->end_controls_section();


        // =========================================================================
        // Style Tab
        // =========================================================================

        // -- Container Section --
        $this->start_controls_section(
            'section_style_container',
            [
                'label' => __('Container', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_background_color',
            [
                'label' => __('Background Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'excel-calculator-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'excel-calculator-pro'),
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

        // -- Header Section --
        $this->start_controls_section(
            'section_style_header',
            [
                'label' => __('Header (Title & Description)', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'heading_title_style',
            [
                'label' => __('Title', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .ecp-calculator-title',
            ]
        );

        $this->add_responsive_control(
            'title_spacing',
            [
                'label' => __('Bottom Spacing', 'excel-calculator-pro'),
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

        $this->add_control(
            'heading_description_style',
            [
                'label' => __('Description', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => __('Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-calculator-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .ecp-calculator-description',
            ]
        );

        $this->end_controls_section();

        // -- Input Fields Section --
        $this->start_controls_section(
            'section_style_input_fields',
            [
                'label' => __('Input Fields', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'heading_input_label_style',
            [
                'label' => __('Labels', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'input_label_color',
            [
                'label' => __('Color', 'excel-calculator-pro'),
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

        $this->add_control(
            'heading_input_field_style',
            [
                'label' => __('Fields', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'input_field_color',
            [
                'label' => __('Text Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_field_background',
            [
                'label' => __('Background Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-input-field' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_field_border',
                'selector' => '{{WRAPPER}} .ecp-input-field',
            ]
        );

        $this->end_controls_section();

        // -- Result Fields Section --
        $this->start_controls_section(
            'section_style_result_fields',
            [
                'label' => __('Result Fields', 'excel-calculator-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'heading_result_label_style',
            [
                'label' => __('Labels', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
            ]
        );

        $this->add_control(
            'result_label_color',
            [
                'label' => __('Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-group label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'result_label_typography',
                'selector' => '{{WRAPPER}} .ecp-output-group label',
            ]
        );

        $this->add_control(
            'heading_result_field_style',
            [
                'label' => __('Results', 'excel-calculator-pro'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'result_field_color',
            [
                'label' => __('Text Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-field' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'result_field_background',
            [
                'label' => __('Background Color', 'excel-calculator-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ecp-output-field' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'result_field_typography',
                'selector' => '{{WRAPPER}} .ecp-output-field',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'result_field_border',
                'selector' => '{{WRAPPER}} .ecp-output-field',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Helper function to get calculators for the dropdown.
     */
    private function get_available_calculators()
    {
        $options = [];
        // Sicherstellen, dass die Haupt-Plugin-Klasse und ihre Methoden verfügbar sind
        if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_database')) {
            $db_handler = ecp_init()->get_database();
            if ($db_handler) {
                $calculators = $db_handler->get_calculators(['orderby' => 'name', 'order' => 'ASC']);
                if (!empty($calculators)) {
                    foreach ($calculators as $calc) {
                        $options[$calc->id] = esc_html($calc->name);
                    }
                }
            }
        }
        return $options;
    }

    /**
     * Render widget output on the frontend.
     */
    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $calculator_id = $settings['calculator_id'] ?? '';

        if (empty($calculator_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-widget-empty-view" style="text-align: center; padding: 20px; background-color: #fcfcfc; border: 2px dashed #e0e0e0; border-radius: 5px;">';
                echo '<div class="elementor-widget-empty-view__icon" style="font-size: 30px;"><i class="' . esc_attr($this->get_icon()) . '"></i></div>';
                echo '<div class="elementor-widget-empty-view__title" style="font-weight: bold; margin-top: 10px;">' . esc_html($this->get_title()) . '</div>';
                echo '<div class="elementor-widget-empty-view__message" style="margin-top: 5px; font-size: 13px;">' . __('Please select a calculator from the panel.', 'excel-calculator-pro') . '</div>';
                echo '</div>';
            }
            return;
        }

        // Build shortcode attributes based on widget settings
        $shortcode_atts = ['id' => $calculator_id];

        // Handle title override
        if ($settings['override_title'] === 'custom' && !empty($settings['custom_title'])) {
            $shortcode_atts['title'] = $settings['custom_title'];
        } elseif ($settings['override_title'] === 'hide') {
            $shortcode_atts['title'] = 'hide';
        }

        // Handle description override
        if ($settings['override_description'] === 'custom' && !empty($settings['custom_description'])) {
            $shortcode_atts['description'] = $settings['custom_description'];
        } elseif ($settings['override_description'] === 'hide') {
            $shortcode_atts['description'] = 'hide';
        }

        // Use the existing shortcode handler to render the calculator for full consistency.
        if (function_exists('ecp_init') && method_exists(ecp_init(), 'get_shortcode')) {
            $shortcode_handler = ecp_init()->get_shortcode();
            if ($shortcode_handler && method_exists($shortcode_handler, 'calculator_shortcode')) {
                // The handler echos the output, so we just call it.
                echo $shortcode_handler->calculator_shortcode($shortcode_atts);
                return;
            }
        }

        // Fallback using do_shortcode if the handler is not available for some reason.
        $atts_string = '';
        foreach ($shortcode_atts as $key => $value) {
            $atts_string .= sprintf(' %s="%s"', $key, esc_attr($value));
        }
        echo do_shortcode('[excel_calculator' . $atts_string . ']');
    }

    /**
     * Render widget output in the editor as a plain HTML content.
     * This is used for the editor preview.
     */
    protected function _content_template()
    {
        // We don't need a specific template here because the render() method works
        // well for both frontend and editor views. Elementor's JS handles the preview refresh.
    }
}
