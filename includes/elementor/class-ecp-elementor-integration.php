<?php
// /includes/elementor/class-ecp-elementor-integration.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class ECP_Elementor_Integration
 *
 * Handles the integration with the Elementor page builder.
 */
final class ECP_Elementor_Integration
{

    /**
     * @var ECP_Elementor_Integration
     */
    private static $_instance = null;

    /**
     * Ensures only one instance of the class is loaded.
     *
     * @return ECP_Elementor_Integration An instance of the class.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * ECP_Elementor_Integration constructor.
     */
    public function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'add_elementor_widget_category']);
    }

    /**
     * Adds a custom category to Elementor.
     *
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function add_elementor_widget_category($elements_manager)
    {
        $elements_manager->add_category(
            'excel-calculator-pro',
            [
                'title' => __('Excel Calculator Pro', 'excel-calculator-pro'),
                'icon' => 'eicon-calculator',
            ]
        );
    }

    /**
     * Registers the custom widget.
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager)
    {
        // KORRIGIERTER PFAD HIER:
        require_once ECP_PLUGIN_PATH . 'includes/elementor/class-ecp-calculator-widget.php';
        $widgets_manager->register(new ECP_Calculator_Widget());
    }
}

// Instantiate the integration class.
ECP_Elementor_Integration::instance();
