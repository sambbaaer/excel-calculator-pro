<?php
/**
 * Admin Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Admin {
    
    /**
     * Database-Handler
     */
    private $database;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->database = ecp_init()->get_database();
        $this->init_hooks();
    }
    
    /**
     * Admin-Hooks initialisieren
     */
    private function init_hooks() {
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
    public function admin_menu() {
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
    public function admin_enqueue_scripts($hook) {
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
     * Admin-Initialisierung
     */
    public function admin_init()
    {
        // Einstellungen registrieren
        register_setting('ecp_settings', 'ecp_general_settings');
        register_setting('ecp_settings', 'ecp_color_settings'); // NEU

        // Allgemeine Einstellungssektion
        add_settings_section(
            'ecp_general_section',
            __('Allgemeine Einstellungen', 'excel-calculator-pro'),
            array($this, 'general_section_callback'),
            'ecp_settings'
        );

        // Farb-Einstellungssektion - NEU
        add_settings_section(
            'ecp_color_section',
            __('Farb- und Design-Einstellungen', 'excel-calculator-pro'),
            array($this, 'color_section_callback'),
            'ecp_settings'
        );

        // Einstellungsfelder
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

        // NEU: Farbfelder
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
            'background_color',
            __('Hintergrundfarbe', 'excel-calculator-pro'),
            array($this, 'background_color_field_callback'),
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
    }
    
    /**
     * Admin-Seite anzeigen
     */
    public function admin_page() {
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
    private function calculators_tab() {
        ?>
        <div id="ecp-calculators-tab">
            <div class="ecp-admin-header">
                <button id="ecp-new-calculator" class="button button-primary">
                    <?php _e('Neuer Kalkulator', 'excel-calculator-pro'); ?>
                </button>
                <button id="ecp-bulk-actions" class="button" style="margin-left: 10px;">
                    <?php _e('Massenaktionen', 'excel-calculator-pro'); ?>
                </button>
            </div>
            
            <div id="ecp-calculators-list">
                <?php $this->display_calculators_list(); ?>
            </div>
            
            <div id="ecp-calculator-editor" style="display: none;">
                <div class="ecp-editor-header">
                    <h2 id="ecp-editor-title"><?php _e('Kalkulator bearbeiten', 'excel-calculator-pro'); ?></h2>
                    <button id="ecp-preview-calculator" class="button">
                        <?php _e('Vorschau', 'excel-calculator-pro'); ?>
                    </button>
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
                                <!-- Felder werden hier dynamisch hinzugefügt -->
                            </div>
                            <button id="ecp-add-field" class="button">
                                <?php _e('Feld hinzufügen', 'excel-calculator-pro'); ?>
                            </button>
                        </div>
                        
                        <div class="ecp-section">
                            <h3><?php _e('Ausgabefelder & Formeln', 'excel-calculator-pro'); ?></h3>
                            <div id="ecp-outputs-container" class="ecp-sortable">
                                <!-- Ausgabefelder werden hier dynamisch hinzugefügt -->
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
            
            <!-- Vorschau-Modal -->
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
    private function templates_tab() {
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
            
            <!-- Template verwenden Modal -->
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
    private function import_export_tab() {
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
    private function settings_tab() {
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
    private function display_calculators_list() {
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
    public function ajax_save_calculator() {
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
        
        $result = $this->database->save_calculator($calculator_data);
        
        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error(__('Fehler beim Speichern', 'excel-calculator-pro'));
        }
    }
    
    /**
     * AJAX: Kalkulator löschen
     */
    public function ajax_delete_calculator() {
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
    public function ajax_get_calculator() {
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
    public function ajax_create_from_template() {
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
    public function ajax_export_calculator() {
        check_ajax_referer('ecp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }
        
        $calculator_id = intval($_POST['calculator_id']);
        $calculator = $this->database->export_calculator($calculator_id);
        
        if ($calculator) {
            $filename = sanitize_file_name($calculator->name) . '_calculator.json';
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo json_encode($calculator, JSON_PRETTY_PRINT);
            exit;
        } else {
            wp_send_json_error(__('Kalkulator nicht gefunden', 'excel-calculator-pro'));
        }
    }
    
    /**
     * AJAX: Kalkulator importieren
     */
    public function ajax_import_calculator() {
        check_ajax_referer('ecp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unzureichende Berechtigungen', 'excel-calculator-pro'));
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('Keine Datei hochgeladen', 'excel-calculator-pro'));
        }
        
        $file = $_FILES['import_file'];
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);
        
        if (!$data) {
            wp_send_json_error(__('Ungültige JSON-Datei', 'excel-calculator-pro'));
        }
        
        $result = $this->database->import_calculator($data);
        
        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error(__('Fehler beim Importieren', 'excel-calculator-pro'));
        }
    }
    
    /**
     * Einstellungen-Callbacks
     */
    public function general_section_callback() {
        echo '<p>' . __('Konfigurieren Sie die allgemeinen Einstellungen für Excel Calculator Pro.', 'excel-calculator-pro') . '</p>';
    }
    
    public function currency_field_callback() {
        $options = get_option('ecp_general_settings', array());
        $currency = isset($options['default_currency']) ? $options['default_currency'] : 'CHF';
        
        echo '<select name="ecp_general_settings[default_currency]">';
        $currencies = array('CHF' => 'CHF (Schweizer Franken)', 'EUR' => 'EUR (Euro)', 'USD' => 'USD (US-Dollar)');
        foreach ($currencies as $code => $name) {
            echo '<option value="' . $code . '"' . selected($currency, $code, false) . '>' . $name . '</option>';
        }
        echo '</select>';
    }
    
    public function number_format_field_callback() {
        $options = get_option('ecp_general_settings', array());
        $format = isset($options['number_format']) ? $options['number_format'] : 'de_CH';
        
        echo '<select name="ecp_general_settings[number_format]">';
        $formats = array(
            'de_CH' => 'Schweiz (1\'234.56)',
            'de_DE' => 'Deutschland (1.234,56)',
            'en_US' => 'USA (1,234.56)'
        );
        foreach ($formats as $code => $name) {
            echo '<option value="' . $code . '"' . selected($format, $code, false) . '>' . $name . '</option>';
        }
        echo '</select>';
    }  

    /**
     * Farb-Sektion Callback - NEU
     */
    public function color_section_callback()
    {
        echo '<p>' . __('Passen Sie das Aussehen Ihrer Kalkulatoren an.', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Primärfarbe Callback - NEU
     */
    public function primary_color_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['primary_color']) ? $options['primary_color'] : '#007cba';

        echo '<input type="color" name="ecp_color_settings[primary_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hauptfarbe für Buttons, Rahmen und Akzente', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Sekundärfarbe Callback - NEU
     */
    public function secondary_color_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['secondary_color']) ? $options['secondary_color'] : '#00a0d2';

        echo '<input type="color" name="ecp_color_settings[secondary_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Sekundäre Akzentfarbe für Verläufe und Hover-Effekte', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Hintergrundfarbe Callback - NEU
     */
    public function background_color_field_callback()
    {
        $options = get_option('ecp_color_settings', array());
        $color = isset($options['background_color']) ? $options['background_color'] : '#ffffff';

        echo '<input type="color" name="ecp_color_settings[background_color]" value="' . esc_attr($color) . '" />';
        echo '<p class="description">' . __('Hintergrundfarbe der Kalkulatoren', 'excel-calculator-pro') . '</p>';
    }

    /**
     * Kalkulator-Breite Callback - NEU
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
}