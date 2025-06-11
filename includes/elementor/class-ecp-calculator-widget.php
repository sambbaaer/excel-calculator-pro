<?php
// /includes/elementor/widgets/class-ecp-calculator-widget.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

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
        return 'eicon-calculator';
    }

    /**
     * Get widget categories.
     */
    public function get_categories()
    {
        return ['excel-calculator-pro'];
    }

    /**
     * Register widget controls.
     */
    protected function _register_controls()
    {
        $this->start_controls_section(
            'section_calculator',
            [
                'label' => __('Calculator', 'excel-calculator-pro'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $calculators = $this->get_available_calculators();

        if (empty($calculators)) {
            $this->add_control(
                'no_calculators_notice',
                [
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw' => sprintf(
                        '<strong>%1$s</strong><br>%2$s <a href="%3$s" target="_blank">%4$s</a>.',
                        __('No calculators found.', 'excel-calculator-pro'),
                        __('Please', 'excel-calculator-pro'),
                        admin_url('options-general.php?page=excel-calculator-pro'),
                        __('create a calculator', 'excel-calculator-pro')
                    ),
                    'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
                ]
            );
        } else {
            $this->add_control(
                'calculator_id',
                [
                    'label' => __('Select Calculator', 'excel-calculator-pro'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $calculators,
                    'default' => !empty($calculators) ? array_key_first($calculators) : '',
                    'description' => __('Choose from your list of saved calculators.', 'excel-calculator-pro'),
                ]
            );
        }

        $this->end_controls_section();
    }

    /**
     * Helper function to get calculators for the dropdown.
     */
    private function get_available_calculators()
    {
        $options = [];
        $db_handler = ecp_init()->get_database();

        if ($db_handler) {
            $calculators = $db_handler->get_calculators(['orderby' => 'name', 'order' => 'ASC']);
            if (!empty($calculators)) {
                foreach ($calculators as $calc) {
                    $options[$calc->id] = esc_html($calc->name);
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
                echo '<div class="elementor-widget-empty-view__icon"><i class="eicon-calculator"></i></div>';
                echo '<div class="elementor-widget-empty-view__title">' . __('Excel Calculator', 'excel-calculator-pro') . '</div>';
                echo '<div class="elementor-widget-empty-view__message">' . __('Please select a calculator from the panel.', 'excel-calculator-pro') . '</div>';
                echo '</div>';
            }
            return;
        }

        // Use the existing shortcode handler to render the calculator, ensuring full consistency.
        $shortcode_handler = ecp_init()->get_shortcode();
        if ($shortcode_handler && method_exists($shortcode_handler, 'calculator_shortcode')) {
            echo $shortcode_handler->calculator_shortcode(['id' => $calculator_id]);
        } else {
            // Fallback just in case the handler is not available.
            echo do_shortcode('[excel_calculator id="' . esc_attr($calculator_id) . '"]');
        }
    }
}
