<?php

/**
 * Admin Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Admin
{

    /**
     * Database-Handler
     */
    private $database;

    /**
     * Konstruktor
     * Nimmt jetzt die Datenbankinstanz als Parameter entgegen.
     */
    public function __construct($db_instance)
    { // MODIFIZIERT: Parameter hinzugefügt
        $this->database = $db_instance;         // MODIFIZIERT: Zugewiesen
        $this->init_hooks();
    }

    /**
     * Admin-Hooks initialisieren
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_init', array($this, 'admin_init'));

        // AJAX-Hooks
        add_action('wp_ajax_ecp_save_calculator', array($this, 'ajax_save_calculator'));
        add_action('wp_ajax_ecp_delete_calculator', array($this, 'ajax_delete_calculator'));
        add_action('wp_ajax_ecp_get_calculator', array($this, 'ajax_get_calculator'));
        add_action('wp_ajax_ecp_create_from_template', array($this, 'ajax_create_from_template'));
        add_action('wp_ajax_ecp_export_calculator', array($this, 'ajax_export_calculator'));
        add_action('wp_ajax_ecp_import_calculator', array($this, 'ajax_import_calculator'));
    }

    /**
     * Admin-Menü erstellen (unter Einstellungen)
     */
    public function admin_menu()
    {
        add_options_page(
            __('Excel Calculator Pro', 'excel-calculator-pro'),
            __('Calculator Pro', 'excel-calculator-pro'),
            'manage_options',
            'excel-calculator-pro',
            array($this, 'admin_page')
        );
    }

    /**
     * Admin-Scripts einbinden
     */
    public function admin_enqueue_scripts($hook)
    {
        if ($hook !== 'settings_page_excel-calculator-pro') {
            return;
        }

        wp_enqueue_script(
            'ecp-admin-js',
            ECP_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            ECP_VERSION,
            true
        );

        wp_enqueue_style(
            'ecp-admin-css',
            ECP_PLUGIN_URL . 'assets/admin.css',
            array(),
            ECP_VERSION
        );

        // Admin-Lokalisierung
        wp_localize_script('ecp-admin-js', 'ecp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecp_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Sind Sie sicher, dass Sie diesen Kalkulator löschen möchten?', 'excel-calculator-pro'),
                'error_name_required' => __('Bitte geben Sie einen Namen ein.', 'excel-calculator-pro'),
                'success_saved' => __('Kalkulator gespeichert!', 'excel-calculator-pro'),
                'success_deleted' => __('Kalkulator gelöscht!', 'excel-calculator-pro'),
                'error_occurred' => __('Ein Fehler ist aufgetreten:', 'excel-calculator-pro'),
                'loading' => __('Lädt...', 'excel-calculator-pro'),
                'template_created' => __('Kalkulator aus Vorlage erstellt!', 'excel-calculator-pro')
            )
        ));
    }

    /**
     * Admin-Initialisierung - Erweiterte Farbfelder
     */
    public function admin_init()
    {
        // Einstellungen registrieren
        register_setting('ecp_settings', 'ecp_general_settings');
        register_setting('ecp_settings', 'ecp_color_settings');

        // Allgemeine Einstellungssektion
        add_settings_section(
            'ecp_general_section',
            __('Allgemeine Einstellungen', 'excel-calculator-pro'),
            array($this, 'general_section_callback'),
            'ecp_settings'
        );

        // Farb-Einstellungssektion
        add_settings_section(
            'ecp_color_section',
            __('Farb- und Design-Einstellungen', 'excel-calculator-pro'),
            array($this, 'color_section_callback'),
            'ecp_settings'
        );

        // Light Mode Farbsektion
        add_settings_section(
            'ecp_color_light_section',
            __('Light Mode Farben', 'excel-calculator-pro'),
            array($this, 'color_light_section_callback'),
            'ecp_settings'
        );

        // Dark Mode Farbsektion
        add_settings_section(
            'ecp_color_dark_section',
            __('Dark Mode Farben', 'excel-calculator-pro'),
            array($this, 'color_dark_section_callback'),
            'ecp_settings'
        );

        // Allgemeine Einstellungsfelder
        add_settings_field(
            'default_currency',
            __('Standard-Währung', 'excel-calculator-pro'),
            array($this, 'currency_field_callback'),
            'ecp_settings',
            'ecp_general_section'
        );

        add_settings_field(
            'number_format',
            __('Zahlenformat', 'excel-calculator-pro'),
            array($this, 'number_format_field_callback'),
            'ecp_settings',
            'ecp_general_section'
        );

        // Basis-Farbfelder
        add_settings_field(
            'primary_color',
            __('Primärfarbe', 'excel-calculator-pro'),
            array($this, 'primary_color_field_callback'),
            'ecp_settings',
            'ecp_color_section'
        );

        add_settings_field(
            'secondary_color',
            __('Sekundärfarbe', 'excel-calculator-pro'),
            array($this, 'secondary_color_field_callback'),
            'ecp_settings',
            'ecp_color_section'
        );

        add_settings_field(
            'enable_system_dark_mode',
            __('System-Dark-Mode folgen', 'excel-calculator-pro'),
            array($this, 'system_dark_mode_field_callback'),
            'ecp_settings',
            'ecp_color_section'
        );

        add_settings_field(
            'calculator_width',
            __('Standard-Breite', 'excel-calculator-pro'),
            array($this, 'calculator_width_field_callback'),
            'ecp_settings',
            'ecp_color_section'
        );

        // Light Mode Farben
        add_settings_field(
            'background_color_light',
            __('Hintergrundfarbe', 'excel-calculator-pro'),
            array($this, 'background_color_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'text_color_light',
            __('Textfarbe', 'excel-calculator-pro'),
            array($this, 'text_color_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'text_light_light',
            __('Helle Textfarbe (Beschreibungen)', 'excel-calculator-pro'),
            array($this, 'text_light_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'border_color_light',
            __('Rahmenfarbe', 'excel-calculator-pro'),
            array($this, 'border_color_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'input_bg_light',
            __('Eingabefeld Hintergrund', 'excel-calculator-pro'),
            array($this, 'input_bg_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'input_border_light',
            __('Eingabefeld Rahmen', 'excel-calculator-pro'),
            array($this, 'input_border_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'field_group_bg_light',
            __('Feldgruppen Hintergrund', 'excel-calculator-pro'),
            array($this, 'field_group_bg_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'field_group_hover_bg_light',
            __('Feldgruppen Hover Hintergrund', 'excel-calculator-pro'),
            array($this, 'field_group_hover_bg_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'output_group_bg_gradient_start_light',
            __('Ausgabegruppen Gradient Start', 'excel-calculator-pro'),
            array($this, 'output_group_bg_gradient_start_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'output_group_bg_gradient_end_light',
            __('Ausgabegruppen Gradient Ende', 'excel-calculator-pro'),
            array($this, 'output_group_bg_gradient_end_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'output_group_border_light',
            __('Ausgabegruppen Rahmen', 'excel-calculator-pro'),
            array($this, 'output_group_border_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'output_field_bg_light',
            __('Ausgabefeld Hintergrund', 'excel-calculator-pro'),
            array($this, 'output_field_bg_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        add_settings_field(
            'copy_icon_feedback_color_light',
            __('Kopieren-Feedback Farbe', 'excel-calculator-pro'),
            array($this, 'copy_icon_feedback_color_light_field_callback'),
            'ecp_settings',
            'ecp_color_light_section'
        );

        // Dark Mode Farben
        add_settings_field(
            'background_color_dark',
            __('Hintergrundfarbe', 'excel-calculator-pro'),
            array($this, 'background_color_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'text_color_dark',
            __('Textfarbe', 'excel-calculator-pro'),
            array($this, 'text_color_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'text_light_dark',
            __('Helle Textfarbe (Beschreibungen)', 'excel-calculator-pro'),
            array($this, 'text_light_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'border_color_dark',
            __('Rahmenfarbe', 'excel-calculator-pro'),
            array($this, 'border_color_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'input_bg_dark',
            __('Eingabefeld Hintergrund', 'excel-calculator-pro'),
            array($this, 'input_bg_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'input_border_dark',
            __('Eingabefeld Rahmen', 'excel-calculator-pro'),
            array($this, 'input_border_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'field_group_bg_dark',
            __('Feldgruppen Hintergrund', 'excel-calculator-pro'),
            array($this, 'field_group_bg_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'field_group_hover_bg_dark',
            __('Feldgruppen Hover Hintergrund', 'excel-calculator-pro'),
            array($this, 'field_group_hover_bg_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'output_group_bg_gradient_start_dark',
            __('Ausgabegruppen Gradient Start', 'excel-calculator-pro'),
            array($this, 'output_group_bg_gradient_start_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'output_group_bg_gradient_end_dark',
            __('Ausgabegruppen Gradient Ende', 'excel-calculator-pro'),
            array($this, 'output_group_bg_gradient_end_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'output_group_border_dark',
            __('Ausgabegruppen Rahmen', 'excel-calculator-pro'),
            array($this, 'output_group_border_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'output_field_bg_dark',
            __('Ausgabefeld Hintergrund', 'excel-calculator-pro'),
            array($this, 'output_field_bg_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );

        add_settings_field(
            'copy_icon_feedback_color_dark',
            __('Kopieren-Feedback Farbe', 'excel-calculator-pro'),
            array($this, 'copy_icon_feedback_color_dark_field_callback'),
            'ecp_settings',
            'ecp_color_dark_section'
        );
    }


    /**
     * Admin-Seite anzeigen
     */
    public function admin_page()
    {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calculators';
?>
        <div class="wrap">
            <h1><?php _e('Excel Calculator Pro', 'excel-calculator-pro'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=excel-calculator-pro&tab=calculators"
                    class="nav-tab <?php echo $current_tab === 'calculators' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Kalkulatoren', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=templates"
                    class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Vorlagen', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=import-export"
                    class="nav-tab <?php echo $current_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import/Export', 'excel-calculator-pro'); ?>
                </a>
                <a href="?page=excel-calculator-pro&tab=settings"
                    class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Einstellungen', 'excel-calculator-pro'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'templates':
                        $this->templates_tab();
                        break;
                    case 'import-export':
                        $this->import_export_tab();
                        break;
                    case 'settings':
                        $this->settings_tab();
                        break;
                    default:
                        $this->calculators_tab();
                        break;
                }
                ?>
            </div>
        </div>
    <?php
    }

    /**
     * Kalkulatoren-Tab
     */
    private function calculators_tab()
    {
    ?>
        <div id="ecp-calculators-tab">
            <div class="ecp-admin-header">
                <button id="ecp-new-calculator" class="button button-primary">
                    <?php _e('Neuer Kalkulator', 'excel-calculator-pro'); ?>
                </button>
            </div>

            <div id="ecp-calculators-list">
                <?php $this->display_calculators_list(); ?>
            </div>

            <div id="ecp-calculator-editor" style="display: none;">
                <div class="ecp-editor-header">
                    <h2 id="ecp-editor-title"><?php _e('Kalkulator bearbeiten', 'excel-calculator-pro'); ?></h2>
                </div>

                <div class="ecp-editor-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="calculator-name"><?php _e('Name:', 'excel-calculator-pro'); ?></label></th>
                            <td><input type="text" id="calculator-name" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th><label for="calculator-description"><?php _e('Beschreibung:', 'excel-calculator-pro'); ?></label></th>
                            <td><textarea id="calculator-description" class="large-text" rows="3"></textarea></td>
                        </tr>
                    </table>

                    <div class="ecp-editor-sections">
                        <div class="ecp-section">
                            <h3><?php _e('Eingabefelder', 'excel-calculator-pro'); ?></h3>
                            <div id="ecp-fields-container" class="ecp-sortable">
                            </div>
                            <button id="ecp-add-field" class="button">
                                <?php _e('Feld hinzufügen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>

                        <div class="ecp-section">
                            <h3><?php _e('Ausgabefelder & Formeln', 'excel-calculator-pro'); ?></h3>
                            <div id="ecp-outputs-container" class="ecp-sortable">
                            </div>
                            <button id="ecp-add-output" class="button">
                                <?php _e('Ausgabefeld hinzufügen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="ecp-editor-actions">
                    <button id="ecp-save-calculator" class="button button-primary">
                        <?php _e('Speichern', 'excel-calculator-pro'); ?>
                    </button>
                    <button id="ecp-cancel-edit" class="button">
                        <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                    </button>
                    <button id="ecp-duplicate-calculator" class="button" style="margin-left: 20px;">
                        <?php _e('Duplizieren', 'excel-calculator-pro'); ?>
                    </button>
                    <button id="ecp-delete-calculator" class="button button-secondary" style="float: right;">
                        <?php _e('Löschen', 'excel-calculator-pro'); ?>
                    </button>
                </div>

                <input type="hidden" id="calculator-id" value="" />
            </div>

            <div id="ecp-preview-modal" class="ecp-modal" style="display: none;">
                <div class="ecp-modal-content">
                    <div class="ecp-modal-header">
                        <h3><?php _e('Kalkulator-Vorschau', 'excel-calculator-pro'); ?></h3>
                        <span class="ecp-modal-close">&times;</span>
                    </div>
                    <div class="ecp-modal-body">
                        <div id="ecp-preview-content"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Vorlagen-Tab
     */
    private function templates_tab()
    {
        $templates = $this->database->get_templates();
    ?>
        <div id="ecp-templates-tab">
            <p><?php _e('Wählen Sie eine Vorlage aus, um schnell einen neuen Kalkulator zu erstellen:', 'excel-calculator-pro'); ?></p>

            <div class="ecp-templates-grid">
                <?php foreach ($templates as $template): ?>
                    <div class="ecp-template-card" data-template-id="<?php echo $template->id; ?>">
                        <h3><?php echo esc_html($template->name); ?></h3>
                        <p><?php echo esc_html($template->description); ?></p>
                        <div class="ecp-template-actions">
                            <button class="button button-primary ecp-use-template">
                                <?php _e('Verwenden', 'excel-calculator-pro'); ?>
                            </button>
                            <span class="ecp-template-category"><?php echo esc_html($template->category); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="ecp-template-modal" class="ecp-modal" style="display: none;">
                <div class="ecp-modal-content">
                    <div class="ecp-modal-header">
                        <h3><?php _e('Kalkulator aus Vorlage erstellen', 'excel-calculator-pro'); ?></h3>
                        <span class="ecp-modal-close">&times;</span>
                    </div>
                    <div class="ecp-modal-body">
                        <table class="form-table">
                            <tr>
                                <th><label for="template-calculator-name"><?php _e('Name für neuen Kalkulator:', 'excel-calculator-pro'); ?></label></th>
                                <td><input type="text" id="template-calculator-name" class="regular-text" required /></td>
                            </tr>
                        </table>
                        <div class="ecp-modal-actions">
                            <button id="ecp-create-from-template" class="button button-primary">
                                <?php _e('Erstellen', 'excel-calculator-pro'); ?>
                            </button>
                            <button class="button ecp-modal-close">
                                <?php _e('Abbrechen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Import/Export-Tab
     */
    private function import_export_tab()
    {
    ?>
        <div id="ecp-import-export-tab">
            <div class="ecp-ie-section">
                <h3><?php _e('Kalkulator exportieren', 'excel-calculator-pro'); ?></h3>
                <p><?php _e('Wählen Sie einen Kalkulator zum Exportieren aus:', 'excel-calculator-pro'); ?></p>

                <select id="ecp-export-calculator">
                    <option value=""><?php _e('Kalkulator wählen...', 'excel-calculator-pro'); ?></option>
                    <?php
                    $calculators = $this->database->get_calculators();
                    foreach ($calculators as $calc):
                    ?>
                        <option value="<?php echo $calc->id; ?>"><?php echo esc_html($calc->name); ?></option>
                    <?php endforeach; ?>
                </select>

                <button id="ecp-export-btn" class="button button-primary">
                    <?php _e('Exportieren', 'excel-calculator-pro'); ?>
                </button>
            </div>

            <div class="ecp-ie-section">
                <h3><?php _e('Kalkulator importieren', 'excel-calculator-pro'); ?></h3>
                <p><?php _e('Laden Sie eine zuvor exportierte Kalkulator-Datei hoch:', 'excel-calculator-pro'); ?></p>

                <input type="file" id="ecp-import-file" accept=".json" />
                <button id="ecp-import-btn" class="button button-primary">
                    <?php _e('Importieren', 'excel-calculator-pro'); ?>
                </button>
            </div>
        </div>
    <?php
    }

    /**
     * Einstellungen-Tab
     */
    private function settings_tab()
    {
    ?>
        <div id="ecp-settings-tab">
            <form method="post" action="options.php">
                <?php
                settings_fields('ecp_settings');
                do_settings_sections('ecp_settings');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    /**
     * Kalkulatoren-Liste anzeigen
     */
    private function display_calculators_list()
    {
        $calculators = $this->database->get_calculators();

        if (empty($calculators)) {
            echo '<div class="ecp-empty-state">';
            echo '<p>' . __('Noch keine Kalkulatoren erstellt.', 'excel-calculator-pro') . '</p>';
            echo '<p>' . __('Erstellen Sie Ihren ersten Kalkulator oder verwenden Sie eine Vorlage.', 'excel-calculator-pro') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="ecp-calculators-grid">';
        foreach ($calculators as $calc) {
            echo '<div class="ecp-calculator-card">';
            echo '<div class="ecp-card-header">';
            echo '<h3>' . esc_html($calc->name) . '</h3>';
            echo '<div class="ecp-card-actions">';
            echo '<button class="button-link ecp-edit-calc" data-id="' . $calc->id . '" title="' . __('Bearbeiten', 'excel-calculator-pro') . '">✏️</button>';
            echo '<button class="button-link ecp-duplicate-calc" data-id="' . $calc->id . '" title="' . __('Duplizieren', 'excel-calculator-pro') . '">📋</button>';
            echo '<button class="button-link ecp-delete-calc" data-id="' . $calc->id . '" title="' . __('Löschen', 'excel-calculator-pro') . '">🗑️</button>';
            echo '</div>';
            echo '</div>';

            if (!empty($calc->description)) {
                echo '<p class="ecp-card-description">' . esc_html($calc->description) . '</p>';
            }

            echo '<div class="ecp-shortcode-display">';
            echo '<code>[excel_calculator id="' . $calc->id . '"]</code>';
            echo '<button class="ecp-copy-shortcode" data-shortcode="[excel_calculator id=&quot;' . $calc->id . '&quot;]" title="' . __('Shortcode kopieren', 'excel-calculator-pro') . '">📋</button>';
            echo '</div>';

            echo '<div class="ecp-card-meta">';
            echo '<span>' . sprintf(__('Erstellt: %s', 'excel-calculator-pro'), date_i18n(get_option('date_format'), strtotime($calc->created_at))) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * AJAX: Kalkulator speichern
     */
    public function ajax_save_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_data = array(
            'id' => intval($_POST['calculator_id'] ?? 0),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'fields' => $_POST['fields'] ?? array(),
            'formulas' => $_POST['formulas'] ?? array(),
            'settings' => $_POST['settings'] ?? array()
        );

        // Versuche, die Daten zu sanitizen und zu validieren (Beispielhaft)
        try {
            // Dies würde eine Methode in ECP_Security_Manager aufrufen, falls vorhanden
            // $sanitized_data = ECP_Security_Manager::sanitize_calculator_data($calculator_data);
            // Für jetzt verwenden wir die übergebenen Daten, aber Logging bei Fehlern
            $result = $this->database->save_calculator($calculator_data);

            if ($result) {
                wp_send_json_success(array('id' => $result));
            } else {
                wp_send_json_error(__('Fehler beim Speichern des Kalkulators.', 'excel-calculator-pro'));
            }
        } catch (Exception $e) {
            error_log("ECP Save Calculator Error: " . $e->getMessage());
            wp_send_json_error(__('Fehler beim Speichern: ', 'excel-calculator-pro') . $e->getMessage());
        }
    }

    /**
     * AJAX: Kalkulator löschen
     */
    public function ajax_delete_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id']);
        $result = $this->database->delete_calculator($calculator_id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Fehler beim Löschen', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator abrufen
     */
    public function ajax_get_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id']);
        $calculator = $this->database->get_calculator($calculator_id);

        if ($calculator) {
            wp_send_json_success($calculator);
        } else {
            wp_send_json_error(__('Kalkulator nicht gefunden', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Aus Vorlage erstellen
     */
    public function ajax_create_from_template()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $template_id = intval($_POST['template_id']);
        $name = sanitize_text_field($_POST['name']);

        $result = $this->database->create_from_template($template_id, $name);

        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error(__('Fehler beim Erstellen aus Vorlage', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator exportieren
     */
    public function ajax_export_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            // Anstatt wp_send_json_error, was für AJAX-Antworten gedacht ist,
            // hier eine Fehlermeldung ausgeben und beenden, da dies ein direkter Download sein soll.
            wp_die(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        $calculator_id = intval($_POST['calculator_id']);
        $calculator = $this->database->export_calculator($calculator_id); // export_calculator gibt bereits ein Array oder false zurück

        if ($calculator && is_array($calculator)) { // Sicherstellen, dass es ein Array ist
            $filename = sanitize_file_name($calculator['name'] ?? 'calculator_export') . '_calculator.json';

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Verhindert Caching

            echo json_encode($calculator, JSON_PRETTY_PRINT);
            exit;
        } else {
            wp_die(__('Kalkulator nicht gefunden oder Exportfehler', 'excel-calculator-pro'));
        }
    }

    /**
     * AJAX: Kalkulator importieren
     */
    public function ajax_import_calculator()
    {
        check_ajax_referer('ecp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Keine Datei hochgeladen oder Fehler beim Upload.', 'excel-calculator-pro'));
        }

        $file = $_FILES['import_file'];

        // Dateityp prüfen (sollte application/json sein)
        if ($file['type'] !== 'application/json') {
            wp_send_json_error(__('Ungültiger Dateityp. Bitte laden Sie eine JSON-Datei hoch.', 'excel-calculator-pro'));
            return;
        }

        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true); // true für assoziatives Array

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Ungültige JSON-Datei: ', 'excel-calculator-pro') . json_last_error_msg());
            return;
        }

        // Grundlegende Validierung der erwarteten Struktur
        if (!isset($data['name']) || !isset($data['fields']) || !isset($data['formulas'])) {
            wp_send_json_error(__('Die JSON-Datei hat nicht die erwartete Struktur.', 'excel-calculator-pro'));
            return;
        }

        $result = $this->database->import_calculator($data);

        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error(__('Fehler beim Importieren des Kalkulators.', 'excel-calculator-pro'));
        }
    }

    /**
     * Einstellungen-Callbacks
     */
    public function general_section_callback()
    {
        echo '<p>' . __('Konfigurieren Sie die allgemeinen Einstellungen für Excel Calculator Pro.', 'excel-calculator-pro') . '</p>';
    }

    public function currency_field_callback()
    {
        $options = get_option('ecp_general_settings', array());
        $currency = isset($options['default_currency']) ? $options['default_currency'] : 'CHF';

        echo '<select name="ecp_general_settings[default_currency]">';
        $currencies = array('CHF' => 'CHF (Schweizer Franken)', 'EUR' => 'EUR (Euro)', 'USD' => 'USD (US-Dollar)');
        // Währungssymbole aus Filter anwenden, falls vorhanden
        $custom_symbols = apply_filters('ecp_currency_symbols', array());
        foreach ($custom_symbols as $code => $symbol_unused) {
            if (!isset($currencies[$code])) { // Füge nur hinzu, wenn nicht schon Standard
                $currencies[$code] = $code; // Einfach den Code als Namen, wenn kein spezifischer Name da ist
            }
        }

        foreach ($currencies as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($currency, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    public function number_format_field_callback()
    {
        $options = get_option('ecp_general_settings', array());
        $format = isset($options['number_format']) ? $options['number_format'] : 'de_CH';

        echo '<select name="ecp_general_settings[number_format]">';
        $formats = array(
            'de_CH' => 'Schweiz (1\'234.56)',
            'de_DE' => 'Deutschland (1.234,56)',
            'en_US' => 'USA (1,234.56)'
        );
        foreach ($formats as $code => $name) {
            echo '<option value="' . esc_attr($code) . '"' . selected($format, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Farb-Sektion Callback
     */
    public function color_section_callback()
    {
        echo '<p>' . __('Passen Sie das Aussehen Ihrer Kalkulatoren an.', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Primärfarbe Callback
     */
    public function primary_color_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['primary_color']) ? $options['primary_color'] : '#007cba';

        echo '<input type="color" name="ecp_color_settings[primary_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hauptfarbe für Buttons, Rahmen und Akzente', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Sekundärfarbe Callback
     */
    public function secondary_color_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['secondary_color']) ? $options['secondary_color'] : '#00a0d2';

        echo '<input type="color" name="ecp_color_settings[secondary_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Sekundäre Akzentfarbe für Verläufe und Hover-Effekte', 'excel-calculator-pro') . '</p>';
    }

    /**
     * System Dark Mode Callback
     */
    public function system_dark_mode_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $checked = isset($options['enable_system_dark_mode']) ? checked($options['enable_system_dark_mode'], 1, false) : '';

        echo '<label for="ecp_enable_system_dark_mode">';
        echo '<input type="checkbox" id="ecp_enable_system_dark_mode" name="ecp_color_settings[enable_system_dark_mode]" value="1" ' . $checked . ' />';
        echo ' ' . __('Frontend-Kalkulatoren an das helle/dunkle Design des Betriebssystems anpassen', 'excel-calculator-pro');
        echo '</label>';
        echo '<p class="description">' . __('Wenn aktiviert, versucht das Plugin, das Farbschema des Nutzer-Betriebssystems (Hell/Dunkel) zu übernehmen. Deaktivieren Sie diese Option, wenn der automatische Dark-Mode nicht zu Ihrem Webseiten-Layout passt.', 'excel-calculator-pro') . '</p>';
    }


    /**
     * Kalkulator-Breite Callback
     */
    public function calculator_width_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $width = isset($options['calculator_width']) ? $options['calculator_width'] : 'full';

        echo '<select name="ecp_color_settings[calculator_width]">';
        $widths = array(
            'full' => __('Volle Breite (100%)', 'excel-calculator-pro'),
            'contained' => __('Begrenzt (700px)', 'excel-calculator-pro'),
            'large' => __('Gross (900px)', 'excel-calculator-pro'),
            'medium' => __('Mittel (600px)', 'excel-calculator-pro')
        );

        foreach ($widths as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($width, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Standard-Breite für neue Kalkulatoren', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Callback für Light Mode Sektion
     */
    public function color_light_section_callback()
    {
        echo '<p>' . __('Farben für den hellen Modus. Diese werden verwendet, wenn das System-Dark-Mode deaktiviert ist oder wenn der Nutzer ein helles Design verwendet.', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Callback für Dark Mode Sektion
     */
    public function color_dark_section_callback()
    {
        echo '<p>' . __('Farben für den dunklen Modus. Diese werden nur verwendet, wenn das System-Dark-Mode aktiviert ist und der Nutzer ein dunkles Design verwendet.', 'excel-calculator-pro') . '</p>';
    }

    // Light Mode Farb-Callbacks
    public function background_color_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['background_color_light']) ? $options['background_color_light'] : '#ffffff';
        echo '<input type="color" name="ecp_color_settings[background_color_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Haupthintergrundfarbe des Kalkulators', 'excel-calculator-pro') . '</p>';
    }

    public function text_color_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['text_color_light']) ? $options['text_color_light'] : '#2c3e50';
        echo '<input type="color" name="ecp_color_settings[text_color_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Haupttextfarbe für Titel und Labels', 'excel-calculator-pro') . '</p>';
    }

    public function text_light_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['text_light_light']) ? $options['text_light_light'] : '#6c757d';
        echo '<input type="color" name="ecp_color_settings[text_light_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hellere Textfarbe für Beschreibungen und Hilfstexte', 'excel-calculator-pro') . '</p>';
    }

    public function border_color_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['border_color_light']) ? $options['border_color_light'] : '#e1e5e9';
        echo '<input type="color" name="ecp_color_settings[border_color_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Allgemeine Rahmenfarbe', 'excel-calculator-pro') . '</p>';
    }

    public function input_bg_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['input_bg_light']) ? $options['input_bg_light'] : '#ffffff';
        echo '<input type="color" name="ecp_color_settings[input_bg_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Eingabefelder', 'excel-calculator-pro') . '</p>';
    }

    public function input_border_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['input_border_light']) ? $options['input_border_light'] : '#dee2e6';
        echo '<input type="color" name="ecp_color_settings[input_border_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Rahmenfarbe der Eingabefelder', 'excel-calculator-pro') . '</p>';
    }

    public function field_group_bg_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['field_group_bg_light']) ? $options['field_group_bg_light'] : '#f8f9fa';
        echo '<input type="color" name="ecp_color_settings[field_group_bg_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Feldgruppen', 'excel-calculator-pro') . '</p>';
    }

    public function field_group_hover_bg_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['field_group_hover_bg_light']) ? $options['field_group_hover_bg_light'] : '#e9ecef';
        echo '<input type="color" name="ecp_color_settings[field_group_hover_bg_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Feldgruppen bei Hover', 'excel-calculator-pro') . '</p>';
    }

    public function output_group_bg_gradient_start_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_group_bg_gradient_start_light']) ? $options['output_group_bg_gradient_start_light'] : '#e8f4f8';
        echo '<input type="color" name="ecp_color_settings[output_group_bg_gradient_start_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Startfarbe für den Hintergrund-Verlauf der Ausgabegruppen', 'excel-calculator-pro') . '</p>';
    }

    public function output_group_bg_gradient_end_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_group_bg_gradient_end_light']) ? $options['output_group_bg_gradient_end_light'] : '#f0f9ff';
        echo '<input type="color" name="ecp_color_settings[output_group_bg_gradient_end_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Endfarbe für den Hintergrund-Verlauf der Ausgabegruppen', 'excel-calculator-pro') . '</p>';
    }

    public function output_group_border_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_group_border_light']) ? $options['output_group_border_light'] : '#b3d9e6';
        echo '<input type="color" name="ecp_color_settings[output_group_border_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Rahmenfarbe der Ausgabegruppen', 'excel-calculator-pro') . '</p>';
    }

    public function output_field_bg_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_field_bg_light']) ? $options['output_field_bg_light'] : '#ffffff';
        echo '<input type="color" name="ecp_color_settings[output_field_bg_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Ausgabefelder', 'excel-calculator-pro') . '</p>';
    }

    public function copy_icon_feedback_color_light_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['copy_icon_feedback_color_light']) ? $options['copy_icon_feedback_color_light'] : '#28a745';
        echo '<input type="color" name="ecp_color_settings[copy_icon_feedback_color_light]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Farbe für das Kopieren-Feedback (Erfolgsmeldung)', 'excel-calculator-pro') . '</p>';
    }

    // Dark Mode Farb-Callbacks
    public function background_color_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['background_color_dark']) ? $options['background_color_dark'] : '#1e1e1e';
        echo '<input type="color" name="ecp_color_settings[background_color_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Haupthintergrundfarbe des Kalkulators im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function text_color_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['text_color_dark']) ? $options['text_color_dark'] : '#e0e0e0';
        echo '<input type="color" name="ecp_color_settings[text_color_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Haupttextfarbe für Titel und Labels im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function text_light_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['text_light_dark']) ? $options['text_light_dark'] : '#adb5bd';
        echo '<input type="color" name="ecp_color_settings[text_light_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hellere Textfarbe für Beschreibungen im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function border_color_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['border_color_dark']) ? $options['border_color_dark'] : '#404040';
        echo '<input type="color" name="ecp_color_settings[border_color_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Allgemeine Rahmenfarbe im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function input_bg_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['input_bg_dark']) ? $options['input_bg_dark'] : '#2d2d2d';
        echo '<input type="color" name="ecp_color_settings[input_bg_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Eingabefelder im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function input_border_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['input_border_dark']) ? $options['input_border_dark'] : '#505050';
        echo '<input type="color" name="ecp_color_settings[input_border_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Rahmenfarbe der Eingabefelder im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function field_group_bg_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['field_group_bg_dark']) ? $options['field_group_bg_dark'] : '#2d2d2d';
        echo '<input type="color" name="ecp_color_settings[field_group_bg_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Feldgruppen im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function field_group_hover_bg_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['field_group_hover_bg_dark']) ? $options['field_group_hover_bg_dark'] : '#353535';
        echo '<input type="color" name="ecp_color_settings[field_group_hover_bg_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Feldgruppen bei Hover im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function output_group_bg_gradient_start_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_group_bg_gradient_start_dark']) ? $options['output_group_bg_gradient_start_dark'] : '#1a3a4a';
        echo '<input type="color" name="ecp_color_settings[output_group_bg_gradient_start_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Startfarbe für den Hintergrund-Verlauf der Ausgabegruppen im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function output_group_bg_gradient_end_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_group_bg_gradient_end_dark']) ? $options['output_group_bg_gradient_end_dark'] : '#0f2f3f';
        echo '<input type="color" name="ecp_color_settings[output_group_bg_gradient_end_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Endfarbe für den Hintergrund-Verlauf der Ausgabegruppen im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function output_group_border_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_group_border_dark']) ? $options['output_group_border_dark'] : '#4a6c7a';
        echo '<input type="color" name="ecp_color_settings[output_group_border_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Rahmenfarbe der Ausgabegruppen im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function output_field_bg_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['output_field_bg_dark']) ? $options['output_field_bg_dark'] : '#2d2d2d';
        echo '<input type="color" name="ecp_color_settings[output_field_bg_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Ausgabefelder im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }

    public function copy_icon_feedback_color_dark_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['copy_icon_feedback_color_dark']) ? $options['copy_icon_feedback_color_dark'] : '#34d399';
        echo '<input type="color" name="ecp_color_settings[copy_icon_feedback_color_dark]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Farbe für das Kopieren-Feedback im dunklen Modus', 'excel-calculator-pro') . '</p>';
    }
}
