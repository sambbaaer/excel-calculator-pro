<?php

/**
 * Database Handler für Excel Calculator Pro
 */

// Verhindert direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

class ECP_Database
{

    /**
     * Tabellennamen
     */
    private $table_calculators;
    private $table_templates;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        global $wpdb;

        // Tabellennamen setzen
        $this->table_calculators = $wpdb->prefix . 'excel_calculators';
        $this->table_templates = $wpdb->prefix . 'excel_calculator_templates';

        // WordPress-Hook für Datenbank-Updates
        add_action('plugins_loaded', array($this, 'maybe_upgrade_database'), 20);
    }

    /**
     * Datenbank-Upgrade prüfen
     */
    public function maybe_upgrade_database()
    {
        $current_version = get_option('ecp_db_version', '0');

        if (version_compare($current_version, ECP_VERSION, '<')) {
            $this->create_tables();
            update_option('ecp_db_version', ECP_VERSION);
        }
    }

    /**
     * Tabellen erstellen
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Kalkulatoren-Tabelle
        $sql_calculators = "CREATE TABLE {$this->table_calculators} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            fields longtext NOT NULL,
            formulas longtext NOT NULL,
            settings longtext,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Vorlagen-Tabelle
        $sql_templates = "CREATE TABLE {$this->table_templates} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100) DEFAULT 'general',
            fields longtext NOT NULL,
            formulas longtext NOT NULL,
            settings longtext,
            is_public tinyint(1) DEFAULT 0,
            sort_order int(11) DEFAULT 0,
            created_by bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_public (is_public),
            KEY sort_order (sort_order),
            KEY created_by (created_by)
        ) $charset_collate;";

        // WordPress-Funktion für Tabellenerstellung
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $result1 = dbDelta($sql_calculators);
        $result2 = dbDelta($sql_templates);

        // Fehlerprotokollierung
        if ($wpdb->last_error) {
            error_log('ECP Database Error: ' . $wpdb->last_error);
        }

        // Standard-Vorlagen erstellen (nur bei erstmaliger Installation)
        if ($this->get_templates_count() === 0) {
            $this->create_default_templates();
        }

        return !$wpdb->last_error;
    }

    /**
     * Standard-Vorlagen erstellen
     */
    private function create_default_templates()
    {
        // Prüfen ob bereits Vorlagen existieren
        if ($this->get_templates_count() > 0) {
            return;
        }

        try {
            // Einfacher Kreditrechner
            $this->insert_template(array(
                'name' => 'Kreditrechner',
                'description' => 'Berechnet monatliche Raten für Kredite',
                'category' => 'financial',
                'fields' => json_encode(array(
                    array(
                        'id' => 'kreditsumme',
                        'label' => 'Kreditsumme',
                        'type' => 'number',
                        'default' => '10000',
                        'min' => '1000',
                        'max' => '1000000',
                        'unit' => 'CHF'
                    ),
                    array(
                        'id' => 'zinssatz',
                        'label' => 'Zinssatz (%)',
                        'type' => 'number',
                        'default' => '3.5',
                        'min' => '0.1',
                        'max' => '15',
                        'step' => '0.1',
                        'unit' => '%'
                    ),
                    array(
                        'id' => 'laufzeit',
                        'label' => 'Laufzeit (Jahre)',
                        'type' => 'number',
                        'default' => '5',
                        'min' => '1',
                        'max' => '30',
                        'unit' => 'Jahre'
                    )
                )),
                'formulas' => json_encode(array(
                    array(
                        'label' => 'Monatliche Rate',
                        'formula' => 'RUNDEN((kreditsumme * (zinssatz/100/12) * POW(1 + zinssatz/100/12, laufzeit*12)) / (POW(1 + zinssatz/100/12, laufzeit*12) - 1), 2)',
                        'format' => 'currency',
                        'help' => 'Die monatlich zu zahlende Rate'
                    ),
                    array(
                        'label' => 'Gesamtkosten',
                        'formula' => 'RUNDEN(((kreditsumme * (zinssatz/100/12) * POW(1 + zinssatz/100/12, laufzeit*12)) / (POW(1 + zinssatz/100/12, laufzeit*12) - 1)) * laufzeit * 12, 2)',
                        'format' => 'currency',
                        'help' => 'Gesamtbetrag über die gesamte Laufzeit'
                    ),
                    array(
                        'label' => 'Zinszahlungen gesamt',
                        'formula' => 'RUNDEN((((kreditsumme * (zinssatz/100/12) * POW(1 + zinssatz/100/12, laufzeit*12)) / (POW(1 + zinssatz/100/12, laufzeit*12) - 1)) * laufzeit * 12) - kreditsumme, 2)',
                        'format' => 'currency',
                        'help' => 'Gesamte Zinszahlungen über die Laufzeit'
                    )
                )),
                'is_public' => 1,
                'sort_order' => 1
            ));

            // ROI-Rechner
            $this->insert_template(array(
                'name' => 'ROI-Rechner',
                'description' => 'Return on Investment berechnen',
                'category' => 'business',
                'fields' => json_encode(array(
                    array(
                        'id' => 'investition',
                        'label' => 'Investition',
                        'type' => 'number',
                        'default' => '5000',
                        'min' => '1',
                        'unit' => 'CHF'
                    ),
                    array(
                        'id' => 'gewinn',
                        'label' => 'Gewinn',
                        'type' => 'number',
                        'default' => '1500',
                        'min' => '0',
                        'unit' => 'CHF'
                    )
                )),
                'formulas' => json_encode(array(
                    array(
                        'label' => 'ROI (%)',
                        'formula' => 'RUNDEN(((gewinn - investition) / investition) * 100, 2)',
                        'format' => 'percentage',
                        'help' => 'Return on Investment in Prozent'
                    ),
                    array(
                        'label' => 'Nettogewinn',
                        'formula' => 'gewinn - investition',
                        'format' => 'currency',
                        'help' => 'Gewinn abzüglich der ursprünglichen Investition'
                    ),
                    array(
                        'label' => 'Gewinnfaktor',
                        'formula' => 'RUNDEN(gewinn / investition, 2)',
                        'format' => '',
                        'help' => 'Faktor um den sich die Investition vermehrt hat'
                    )
                )),
                'is_public' => 1,
                'sort_order' => 2
            ));

            // BMI-Rechner
            $this->insert_template(array(
                'name' => 'BMI-Rechner',
                'description' => 'Body Mass Index berechnen',
                'category' => 'health',
                'fields' => json_encode(array(
                    array(
                        'id' => 'gewicht',
                        'label' => 'Gewicht',
                        'type' => 'number',
                        'default' => '70',
                        'min' => '20',
                        'max' => '300',
                        'step' => '0.1',
                        'unit' => 'kg'
                    ),
                    array(
                        'id' => 'groesse',
                        'label' => 'Grösse',
                        'type' => 'number',
                        'default' => '175',
                        'min' => '100',
                        'max' => '250',
                        'unit' => 'cm'
                    )
                )),
                'formulas' => json_encode(array(
                    array(
                        'label' => 'BMI',
                        'formula' => 'RUNDEN(gewicht / POW(groesse/100, 2), 1)',
                        'format' => '',
                        'help' => 'Body Mass Index'
                    ),
                    array(
                        'label' => 'Kategorie',
                        'formula' => 'WENN(gewicht / POW(groesse/100, 2) < 18.5, "Untergewicht", WENN(gewicht / POW(groesse/100, 2) < 25, "Normalgewicht", WENN(gewicht / POW(groesse/100, 2) < 30, "Übergewicht", "Adipositas")))',
                        'format' => 'text',
                        'help' => 'BMI-Kategorie nach WHO-Standard'
                    )
                )),
                'is_public' => 1,
                'sort_order' => 3
            ));
        } catch (Exception $e) {
            error_log('ECP Template Creation Error: ' . $e->getMessage());
        }
    }

    /**
     * Kalkulator speichern
     */
    public function save_calculator($data)
    {
        global $wpdb;

        // Datenvalidierung
        if (empty($data['name'])) {
            return false;
        }

        // Daten vorbereiten
        $calculator_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'fields' => is_string($data['fields']) ? $data['fields'] : wp_json_encode($data['fields']),
            'formulas' => is_string($data['formulas']) ? $data['formulas'] : wp_json_encode($data['formulas']),
            'settings' => is_string($data['settings'] ?? '') ? $data['settings'] : wp_json_encode($data['settings'] ?? array()),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
            'created_by' => get_current_user_id()
        );

        // Datentypen für wpdb
        $data_types = array('%s', '%s', '%s', '%s', '%s', '%s', '%d');

        if (isset($data['id']) && $data['id'] > 0) {
            // Update
            $result = $wpdb->update(
                $this->table_calculators,
                $calculator_data,
                array('id' => intval($data['id'])),
                $data_types,
                array('%d')
            );

            if ($result === false) {
                error_log('ECP Database Update Error: ' . $wpdb->last_error);
                return false;
            }

            return intval($data['id']);
        } else {
            // Insert
            $result = $wpdb->insert(
                $this->table_calculators,
                $calculator_data,
                $data_types
            );

            if ($result === false) {
                error_log('ECP Database Insert Error: ' . $wpdb->last_error);
                return false;
            }

            return $wpdb->insert_id;
        }
    }

    /**
     * Kalkulator abrufen
     */
    public function get_calculator($id)
    {
        global $wpdb;

        $calculator = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_calculators} WHERE id = %d AND status = 'active'",
            intval($id)
        ));

        if ($calculator) {
            // JSON-Felder dekodieren
            $calculator->fields = json_decode($calculator->fields, true) ?: array();
            $calculator->formulas = json_decode($calculator->formulas, true) ?: array();
            $calculator->settings = json_decode($calculator->settings, true) ?: array();
        }

        return $calculator;
    }

    /**
     * Alle Kalkulatoren abrufen
     */
    public function get_calculators($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        // Query zusammenbauen
        $where = "WHERE status = %s";
        $query_args = array($args['status']);

        if (isset($args['search']) && !empty($args['search'])) {
            $where .= " AND (name LIKE %s OR description LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_args[] = $search;
            $query_args[] = $search;
        }

        // Sortierung
        $allowed_orderby = array('id', 'name', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Limit
        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $wpdb->prepare(" LIMIT %d OFFSET %d", intval($args['limit']), intval($args['offset']));
        }

        $sql = "SELECT * FROM {$this->table_calculators} {$where} ORDER BY {$orderby} {$order}{$limit}";

        $calculators = $wpdb->get_results($wpdb->prepare($sql, $query_args));

        // JSON-Felder für Anzeige dekodieren (optional)
        foreach ($calculators as $calculator) {
            $calculator->field_count = count(json_decode($calculator->fields, true) ?: array());
            $calculator->formula_count = count(json_decode($calculator->formulas, true) ?: array());
        }

        return $calculators;
    }

    /**
     * Kalkulator löschen
     */
    public function delete_calculator($id)
    {
        global $wpdb;

        // Soft delete - Status auf 'deleted' setzen
        $result = $wpdb->update(
            $this->table_calculators,
            array('status' => 'deleted'),
            array('id' => intval($id)),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            error_log('ECP Database Delete Error: ' . $wpdb->last_error);
        }

        return $result !== false;
    }

    /**
     * Vorlage speichern
     */
    public function insert_template($data)
    {
        global $wpdb;

        if (empty($data['name'])) {
            return false;
        }

        $template_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? 'general'),
            'fields' => is_string($data['fields']) ? $data['fields'] : wp_json_encode($data['fields']),
            'formulas' => is_string($data['formulas']) ? $data['formulas'] : wp_json_encode($data['formulas']),
            'settings' => is_string($data['settings'] ?? '') ? $data['settings'] : wp_json_encode($data['settings'] ?? array()),
            'is_public' => intval($data['is_public'] ?? 0),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'created_by' => get_current_user_id()
        );

        $result = $wpdb->insert(
            $this->table_templates,
            $template_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
        );

        if ($result === false) {
            error_log('ECP Template Insert Error: ' . $wpdb->last_error);
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Vorlagen abrufen
     */
    public function get_templates($category = null)
    {
        global $wpdb;

        $where = "WHERE is_public = 1";
        $query_args = array();

        if ($category) {
            $where .= " AND category = %s";
            $query_args[] = $category;
        }

        $sql = "SELECT * FROM {$this->table_templates} {$where} ORDER BY sort_order ASC, name ASC";

        $templates = $wpdb->get_results($wpdb->prepare($sql, $query_args));

        // JSON-Felder dekodieren
        foreach ($templates as $template) {
            $template->fields = json_decode($template->fields, true) ?: array();
            $template->formulas = json_decode($template->formulas, true) ?: array();
            $template->settings = json_decode($template->settings, true) ?: array();
        }

        return $templates;
    }

    /**
     * Anzahl Vorlagen
     */
    public function get_templates_count()
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_templates}");
    }

    /**
     * Kalkulator aus Vorlage erstellen
     */
    public function create_from_template($template_id, $name)
    {
        global $wpdb;

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_templates} WHERE id = %d",
            intval($template_id)
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
    public function get_stats()
    {
        global $wpdb;

        $stats = array();

        // Gesamtanzahl Kalkulatoren
        $stats['total_calculators'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_calculators} WHERE status = 'active'"
        );

        // Kalkulatoren nach Monat (letzte 12 Monate)
        $stats['monthly_created'] = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
             FROM {$this->table_calculators} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND status = 'active'
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        );

        // Beliebte Vorlagen
        $stats['popular_templates'] = $wpdb->get_results(
            "SELECT name, COUNT(*) as usage_count 
             FROM {$this->table_templates} 
             WHERE is_public = 1 
             GROUP BY name 
             ORDER BY usage_count DESC 
             LIMIT 5"
        );

        return $stats;
    }

    /**
     * Daten exportieren
     */
    public function export_calculator($id)
    {
        $calculator = $this->get_calculator($id);

        if (!$calculator) {
            return false;
        }

        // Sensitive Daten entfernen
        $export_data = array(
            'name' => $calculator->name,
            'description' => $calculator->description,
            'fields' => $calculator->fields,
            'formulas' => $calculator->formulas,
            'settings' => $calculator->settings,
            'export_version' => ECP_VERSION,
            'export_date' => current_time('mysql')
        );

        return $export_data;
    }

    /**
     * Daten importieren
     */
    public function import_calculator($data, $name_override = null)
    {
        if (!isset($data['name']) && !isset($data->name)) {
            return false;
        }

        $import_data = is_object($data) ? (array) $data : $data;

        // Namen überschreiben falls gewünscht
        if ($name_override) {
            $import_data['name'] = $name_override;
        }

        // Validierung der importierten Daten
        if (!isset($import_data['fields']) || !isset($import_data['formulas'])) {
            return false;
        }

        return $this->save_calculator($import_data);
    }

    /**
     * Datenbank-Integrität prüfen
     */
    public function check_integrity()
    {
        global $wpdb;

        $issues = array();

        // Tabellen existieren?
        $tables = array($this->table_calculators, $this->table_templates);
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $issues[] = "Tabelle {$table} fehlt";
            }
        }

        // JSON-Felder validieren
        $calculators = $wpdb->get_results("SELECT id, fields, formulas FROM {$this->table_calculators} WHERE status = 'active'");
        foreach ($calculators as $calc) {
            if (json_decode($calc->fields) === null) {
                $issues[] = "Ungültige JSON-Daten in Kalkulator {$calc->id} (fields)";
            }
            if (json_decode($calc->formulas) === null) {
                $issues[] = "Ungültige JSON-Daten in Kalkulator {$calc->id} (formulas)";
            }
        }

        return empty($issues) ? true : $issues;
    }
}
