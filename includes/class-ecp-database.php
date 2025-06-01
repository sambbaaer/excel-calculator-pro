<?php
/**
 * Database Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Database {
    
    /**
     * Tabellennamen
     */
    private $table_calculators;
    private $table_templates;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        global $wpdb;
        $this->table_calculators = $wpdb->prefix . 'excel_calculators';
        $this->table_templates = $wpdb->prefix . 'excel_calculator_templates';
    }
    
    /**
     * Tabellen erstellen
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Kalkulatoren-Tabelle
        $sql_calculators = "CREATE TABLE {$this->table_calculators} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            fields text NOT NULL,
            formulas text NOT NULL,
            settings text,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Vorlagen-Tabelle
        $sql_templates = "CREATE TABLE {$this->table_templates} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100) DEFAULT 'general',
            fields text NOT NULL,
            formulas text NOT NULL,
            settings text,
            is_public tinyint(1) DEFAULT 0,
            created_by bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_public (is_public),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_calculators);
        dbDelta($sql_templates);
        
        // Standard-Vorlagen erstellen
        $this->create_default_templates();
    }
    
    /**
     * Standard-Vorlagen erstellen
     */
    private function create_default_templates() {
        if ($this->get_templates_count() > 0) {
            return; // Bereits Vorlagen vorhanden
        }
        
        // Einfacher Kreditrechner
        $this->insert_template(array(
            'name' => 'Kreditrechner',
            'description' => 'Berechnet monatliche Raten für Kredite',
            'category' => 'financial',
            'fields' => json_encode(array(
                array('id' => 'kreditsumme', 'label' => 'Kreditsumme', 'default' => '10000'),
                array('id' => 'zinssatz', 'label' => 'Zinssatz (%)', 'default' => '3.5'),
                array('id' => 'laufzeit', 'label' => 'Laufzeit (Jahre)', 'default' => '5')
            )),
            'formulas' => json_encode(array(
                array(
                    'label' => 'Monatliche Rate',
                    'formula' => 'RUNDEN((kreditsumme * (zinssatz/100/12) * POW(1 + zinssatz/100/12, laufzeit*12)) / (POW(1 + zinssatz/100/12, laufzeit*12) - 1), 2)',
                    'format' => 'currency'
                ),
                array(
                    'label' => 'Gesamtkosten',
                    'formula' => 'RUNDEN((kreditsumme * (zinssatz/100/12) * POW(1 + zinssatz/100/12, laufzeit*12)) / (POW(1 + zinssatz/100/12, laufzeit*12) - 1) * laufzeit * 12, 2)',
                    'format' => 'currency'
                )
            )),
            'is_public' => 1
        ));
        
        // ROI-Rechner
        $this->insert_template(array(
            'name' => 'ROI-Rechner',
            'description' => 'Return on Investment berechnen',
            'category' => 'business',
            'fields' => json_encode(array(
                array('id' => 'investition', 'label' => 'Investition', 'default' => '5000'),
                array('id' => 'gewinn', 'label' => 'Gewinn', 'default' => '1500')
            )),
            'formulas' => json_encode(array(
                array(
                    'label' => 'ROI (%)',
                    'formula' => 'RUNDEN(((gewinn - investition) / investition) * 100, 2)',
                    'format' => 'percentage'
                ),
                array(
                    'label' => 'Nettogewinn',
                    'formula' => 'gewinn - investition',
                    'format' => 'currency'
                )
            )),
            'is_public' => 1
        ));
        
        // BMI-Rechner
        $this->insert_template(array(
            'name' => 'BMI-Rechner',
            'description' => 'Body Mass Index berechnen',
            'category' => 'health',
            'fields' => json_encode(array(
                array('id' => 'gewicht', 'label' => 'Gewicht (kg)', 'default' => '70'),
                array('id' => 'groesse', 'label' => 'Grösse (cm)', 'default' => '175')
            )),
            'formulas' => json_encode(array(
                array(
                    'label' => 'BMI',
                    'formula' => 'RUNDEN(gewicht / POW(groesse/100, 2), 1)',
                    'format' => ''
                ),
                array(
                    'label' => 'Kategorie',
                    'formula' => 'WENN(gewicht / POW(groesse/100, 2) < 18.5, "Untergewicht", WENN(gewicht / POW(groesse/100, 2) < 25, "Normalgewicht", WENN(gewicht / POW(groesse/100, 2) < 30, "Übergewicht", "Adipositas")))',
                    'format' => 'text'
                )
            )),
            'is_public' => 1
        ));
    }
    
    /**
     * Kalkulator speichern
     */
    public function save_calculator($data) {
        global $wpdb;
        
        $calculator_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'fields' => is_string($data['fields']) ? $data['fields'] : json_encode($data['fields']),
            'formulas' => is_string($data['formulas']) ? $data['formulas'] : json_encode($data['formulas']),
            'settings' => is_string($data['settings'] ?? '') ? $data['settings'] : json_encode($data['settings'] ?? array()),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'created_by' => get_current_user_id()
        );
        
        if (isset($data['id']) && $data['id'] > 0) {
            // Update
            $result = $wpdb->update(
                $this->table_calculators,
                $calculator_data,
                array('id' => intval($data['id'])),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d'),
                array('%d')
            );
            
            return $result !== false ? intval($data['id']) : false;
        } else {
            // Insert
            $result = $wpdb->insert(
                $this->table_calculators,
                $calculator_data,
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Kalkulator abrufen
     */
    public function get_calculator($id) {
        global $wpdb;
        
        $calculator = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_calculators} WHERE id = %d",
            $id
        ));
        
        if ($calculator) {
            $calculator->fields = json_decode($calculator->fields, true);
            $calculator->formulas = json_decode($calculator->formulas, true);
            $calculator->settings = json_decode($calculator->settings, true);
        }
        
        return $calculator;
    }
    
    /**
     * Alle Kalkulatoren abrufen
     */
    public function get_calculators($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "WHERE status = %s";
        $query_args = array($args['status']);
        
        if (isset($args['search']) && !empty($args['search'])) {
            $where .= " AND (name LIKE %s OR description LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_args[] = $search;
            $query_args[] = $search;
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT * FROM {$this->table_calculators} {$where} ORDER BY {$orderby}{$limit}";
        
        return $wpdb->get_results($wpdb->prepare($sql, $query_args));
    }
    
    /**
     * Kalkulator löschen
     */
    public function delete_calculator($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_calculators,
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    /**
     * Vorlage speichern
     */
    public function insert_template($data) {
        global $wpdb;
        
        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? 'general'),
            'fields' => is_string($data['fields']) ? $data['fields'] : json_encode($data['fields']),
            'formulas' => is_string($data['formulas']) ? $data['formulas'] : json_encode($data['formulas']),
            'settings' => is_string($data['settings'] ?? '') ? $data['settings'] : json_encode($data['settings'] ?? array()),
            'is_public' => intval($data['is_public'] ?? 0),
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert(
            $this->table_templates,
            $template_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Vorlagen abrufen
     */
    public function get_templates($category = null) {
        global $wpdb;
        
        $where = "WHERE is_public = 1";
        $query_args = array();
        
        if ($category) {
            $where .= " AND category = %s";
            $query_args[] = $category;
        }
        
        $sql = "SELECT * FROM {$this->table_templates} {$where} ORDER BY name ASC";
        
        $templates = $wpdb->get_results($wpdb->prepare($sql, $query_args));
        
        foreach ($templates as $template) {
            $template->fields = json_decode($template->fields, true);
            $template->formulas = json_decode($template->formulas, true);
            $template->settings = json_decode($template->settings, true);
        }
        
        return $templates;
    }
    
    /**
     * Anzahl Vorlagen
     */
    public function get_templates_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_templates}");
    }
    
    /**
     * Kalkulator aus Vorlage erstellen
     */
    public function create_from_template($template_id, $name) {
        global $wpdb;
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_templates} WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            return false;
        }
        
        return $this->save_calculator(array(
            'name' => sanitize_text_field($name),
            'description' => $template->description,
            'fields' => $template->fields,
            'formulas' => $template->formulas,
            'settings' => $template->settings
        ));
    }
    
    /**
     * Statistiken abrufen
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Gesamtanzahl Kalkulatoren
        $stats['total_calculators'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_calculators} WHERE status = 'active'"
        );
        
        // Kalkulatoren nach Monat (letzte 12 Monate)
        $stats['monthly_created'] = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
             FROM {$this->table_calculators} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        );
        
        return $stats;
    }
    
    /**
     * Daten exportieren
     */
    public function export_calculator($id) {
        $calculator = $this->get_calculator($id);
        
        if (!$calculator) {
            return false;
        }
        
        // Sensitive Daten entfernen
        unset($calculator->id);
        unset($calculator->created_by);
        unset($calculator->created_at);
        unset($calculator->updated_at);
        
        return $calculator;
    }
    
    /**
     * Daten importieren
     */
    public function import_calculator($data, $name_override = null) {
        if (isset($data->name) || isset($data['name'])) {
            $import_data = (array) $data;
            
            if ($name_override) {
                $import_data['name'] = $name_override;
            }
            
            return $this->save_calculator($import_data);
        }
        
        return false;
    }
}