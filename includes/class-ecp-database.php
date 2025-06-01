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

    <?php
/**
 * Verbesserte Database-Methoden für ECP_Database
 * Diese Methoden ersetzen/erweitern die bestehenden in includes/class-ecp-database.php
 */

/**
 * Erweiterte Datenbank-Integrität prüfen
 */
public function check_integrity()
{
    global $wpdb;
    $issues = array();

    try {
        // 1. Tabellen-Existenz prüfen
        $required_tables = array(
            $this->table_calculators => 'Kalkulatoren-Tabelle',
            $this->table_templates => 'Vorlagen-Tabelle'
        );

        foreach ($required_tables as $table => $description) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $issues[] = "{$description} ({$table}) fehlt";
            }
        }

        // 2. Tabellen-Struktur prüfen
        $this->check_table_structure($this->table_calculators, $issues);
        $this->check_table_structure($this->table_templates, $issues);

        // 3. JSON-Felder validieren
        $this->validate_json_fields($issues);

        // 4. Orphaned Records prüfen
        $this->check_orphaned_records($issues);

        // 5. Datenbank-Konsistenz prüfen
        $this->check_database_consistency($issues);

        // 6. Performance-Issues identifizieren
        $this->check_performance_issues($issues);

    } catch (Exception $e) {
        $issues[] = "Fehler bei Integritätsprüfung: " . $e->getMessage();
    }

    return empty($issues) ? true : $issues;
}

/**
 * Tabellen-Struktur prüfen
 */
private function check_table_structure($table_name, &$issues)
{
    global $wpdb;

    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    
    if (empty($columns)) {
        $issues[] = "Tabelle {$table_name} ist leer oder beschädigt";
        return;
    }

    $expected_columns = array();
    
    if ($table_name === $this->table_calculators) {
        $expected_columns = array(
            'id', 'name', 'description', 'fields', 'formulas', 
            'settings', 'status', 'created_by', 'created_at', 'updated_at'
        );
    } elseif ($table_name === $this->table_templates) {
        $expected_columns = array(
            'id', 'name', 'description', 'category', 'fields', 
            'formulas', 'settings', 'is_public', 'sort_order', 
            'created_by', 'created_at', 'updated_at'
        );
    }

    $actual_columns = wp_list_pluck($columns, 'Field');
    $missing_columns = array_diff($expected_columns, $actual_columns);
    
    if (!empty($missing_columns)) {
        $issues[] = "Fehlende Spalten in {$table_name}: " . implode(', ', $missing_columns);
    }
}

/**
 * JSON-Felder validieren
 */
private function validate_json_fields(&$issues)
{
    global $wpdb;

    // Kalkulatoren prüfen
    $calculators = $wpdb->get_results(
        "SELECT id, name, fields, formulas, settings FROM {$this->table_calculators} WHERE status = 'active'"
    );

    foreach ($calculators as $calc) {
        // Fields validieren
        $fields = json_decode($calc->fields, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = "Ungültige JSON-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) - Felder";
        } elseif (!is_array($fields)) {
            $issues[] = "Felder-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) sind kein Array";
        } else {
            // Feld-Struktur validieren
            foreach ($fields as $index => $field) {
                if (!isset($field['id']) || !isset($field['label'])) {
                    $issues[] = "Unvollständiges Feld #{$index} in Kalkulator '{$calc->name}' (ID: {$calc->id})";
                }
            }
        }

        // Formulas validieren
        $formulas = json_decode($calc->formulas, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = "Ungültige JSON-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) - Formeln";
        } elseif (!is_array($formulas)) {
            $issues[] = "Formel-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) sind kein Array";
        } else {
            // Formel-Struktur validieren
            foreach ($formulas as $index => $formula) {
                if (!isset($formula['label']) || !isset($formula['formula'])) {
                    $issues[] = "Unvollständige Formel #{$index} in Kalkulator '{$calc->name}' (ID: {$calc->id})";
                }
                
                // Gefährliche Funktionen in Formeln prüfen
                if (isset($formula['formula'])) {
                    $dangerous_patterns = array(
                        '/eval\s*\(/i',
                        '/exec\s*\(/i', 
                        '/system\s*\(/i',
                        '/shell_exec\s*\(/i',
                        '/file_get_contents\s*\(/i',
                        '/<\?php/i',
                        '/javascript:/i'
                    );
                    
                    foreach ($dangerous_patterns as $pattern) {
                        if (preg_match($pattern, $formula['formula'])) {
                            $issues[] = "Potentiell gefährliche Formel in Kalkulator '{$calc->name}' (ID: {$calc->id}), Formel #{$index}";
                            break;
                        }
                    }
                }
            }
        }

        // Settings validieren
        if (!empty($calc->settings)) {
            $settings = json_decode($calc->settings, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = "Ungültige JSON-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) - Einstellungen";
            }
        }
    }

    // Vorlagen prüfen
    $templates = $wpdb->get_results(
        "SELECT id, name, fields, formulas, settings FROM {$this->table_templates}"
    );

    foreach ($templates as $template) {
        if (json_decode($template->fields) === null) {
            $issues[] = "Ungültige JSON-Daten in Vorlage '{$template->name}' (ID: {$template->id}) - Felder";
        }
        if (json_decode($template->formulas) === null) {
            $issues[] = "Ungültige JSON-Daten in Vorlage '{$template->name}' (ID: {$template->id}) - Formeln";
        }
        if (!empty($template->settings) && json_decode($template->settings) === null) {
            $issues[] = "Ungültige JSON-Daten in Vorlage '{$template->name}' (ID: {$template->id}) - Einstellungen";
        }
    }
}

/**
 * Verwaiste Datensätze prüfen
 */
private function check_orphaned_records(&$issues)
{
    global $wpdb;

    // Kalkulatoren mit ungültigen created_by Werten
    $orphaned_calculators = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$this->table_calculators} c 
         LEFT JOIN {$wpdb->users} u ON c.created_by = u.ID 
         WHERE c.created_by > 0 AND u.ID IS NULL AND c.status = 'active'"
    );

    if ($orphaned_calculators > 0) {
        $issues[] = "{$orphaned_calculators} Kalkulatoren haben ungültige Ersteller-IDs";
    }

    // Vorlagen mit ungültigen created_by Werten
    $orphaned_templates = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$this->table_templates} t 
         LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID 
         WHERE t.created_by > 0 AND u.ID IS NULL"
    );

    if ($orphaned_templates > 0) {
        $issues[] = "{$orphaned_templates} Vorlagen haben ungültige Ersteller-IDs";
    }
}

/**
 * Datenbank-Konsistenz prüfen
 */
private function check_database_consistency(&$issues)
{
    global $wpdb;

    // Duplikate Kalkulator-Namen prüfen
    $duplicate_names = $wpdb->get_results(
        "SELECT name, COUNT(*) as count 
         FROM {$this->table_calculators} 
         WHERE status = 'active' 
         GROUP BY name 
         HAVING count > 1"
    );

    foreach ($duplicate_names as $duplicate) {
        $issues[] = "Doppelter Kalkulator-Name: '{$duplicate->name}' ({$duplicate->count}x)";
    }

    // Sehr alte 'deleted' Einträge
    $old_deleted = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$this->table_calculators} 
         WHERE status = 'deleted' AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
    );

    if ($old_deleted > 0) {
        $issues[] = "{$old_deleted} gelöschte Kalkulatoren älter als 6 Monate (können bereinigt werden)";
    }

    // Sehr grosse JSON-Felder (potentielle Performance-Probleme)
    $large_data = $wpdb->get_results(
        "SELECT id, name, CHAR_LENGTH(fields) as fields_size, CHAR_LENGTH(formulas) as formulas_size 
         FROM {$this->table_calculators} 
         WHERE CHAR_LENGTH(fields) > 10000 OR CHAR_LENGTH(formulas) > 10000"
    );

    foreach ($large_data as $large) {
        $issues[] = "Kalkulator '{$large->name}' (ID: {$large->id}) hat sehr grosse Datenmengen (Felder: {$large->fields_size}, Formeln: {$large->formulas_size})";
    }
}

/**
 * Performance-Issues identifizieren
 */
private function check_performance_issues(&$issues)
{
    global $wpdb;

    // Tabellengrösse prüfen
    $table_info = $wpdb->get_results(
        "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH 
         FROM information_schema.TABLES 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME IN ('{$this->table_calculators}', '{$this->table_templates}')"
    );

    foreach ($table_info as $table) {
        $size_mb = round(($table->DATA_LENGTH + $table->INDEX_LENGTH) / 1024 / 1024, 2);
        
        if ($size_mb > 50) { // Warnung bei über 50MB
            $issues[] = "Tabelle {$table->TABLE_NAME} ist sehr gross ({$size_mb}MB, {$table->TABLE_ROWS} Zeilen)";
        }
    }

    // Index-Nutzung prüfen (vereinfacht)
    $missing_indexes = array();
    
    // Prüfen ob wichtige Indizes existieren
    $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_calculators}");
    $index_names = wp_list_pluck($indexes, 'Key_name');
    
    if (!in_array('status', $index_names)) {
        $missing_indexes[] = "Index 'status' fehlt in {$this->table_calculators}";
    }
    
    if (!empty($missing_indexes)) {
        $issues = array_merge($issues, $missing_indexes);
    }
}

/**
 * Verbesserte Eingabevalidierung für save_calculator
 */
public function save_calculator($data)
{
    global $wpdb;

    // Erweiterte Validierung
    $validation_errors = $this->validate_calculator_data($data);
    if (!empty($validation_errors)) {
        error_log('ECP Validation Errors: ' . implode(', ', $validation_errors));
        return false;
    }

    // Daten vorbereiten und sanitisieren
    $calculator_data = array(
        'name' => sanitize_text_field($data['name']),
        'description' => sanitize_textarea_field($data['description'] ?? ''),
        'fields' => $this->sanitize_json_field($data['fields']),
        'formulas' => $this->sanitize_json_field($data['formulas']),
        'settings' => $this->sanitize_json_field($data['settings'] ?? array()),
        'status' => sanitize_text_field($data['status'] ?? 'active'),
        'created_by' => get_current_user_id()
    );

    // Datentypen für wpdb
    $data_types = array('%s', '%s', '%s', '%s', '%s', '%s', '%d');

    if (isset($data['id']) && $data['id'] > 0) {
        // Update mit zusätzlicher Berechtigung-Prüfung
        $existing = $this->get_calculator(intval($data['id']));
        if (!$existing) {
            return false;
        }

        // Prüfen ob Benutzer berechtigt ist zu bearbeiten
        if (!current_user_can('manage_options') && $existing->created_by != get_current_user_id()) {
            return false;
        }

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
 * Kalkulator-Daten validieren
 */
private function validate_calculator_data($data)
{
    $errors = array();

    // Name validieren
    if (empty($data['name'])) {
        $errors[] = 'Name ist erforderlich';
    } elseif (strlen($data['name']) > 255) {
        $errors[] = 'Name ist zu lang (max. 255 Zeichen)';
    }

    // Felder validieren
    if (!isset($data['fields']) || !is_array($data['fields'])) {
        $errors[] = 'Felder müssen als Array angegeben werden';
    } else {
        foreach ($data['fields'] as $index => $field) {
            if (!isset($field['id']) || empty($field['id'])) {
                $errors[] = "Feld #{$index}: ID fehlt";
            } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field['id'])) {
                $errors[] = "Feld #{$index}: Ungültige ID (nur Buchstaben, Zahlen und Unterstriche)";
            }
            
            if (!isset($field['label']) || empty($field['label'])) {
                $errors[] = "Feld #{$index}: Label fehlt";
            }
        }
    }

    // Formeln validieren
    if (!isset($data['formulas']) || !is_array($data['formulas'])) {
        $errors[] = 'Formeln müssen als Array angegeben werden';
    } else {
        foreach ($data['formulas'] as $index => $formula) {
            if (!isset($formula['label']) || empty($formula['label'])) {
                $errors[] = "Formel #{$index}: Label fehlt";
            }
            
            if (!isset($formula['formula']) || empty($formula['formula'])) {
                $errors[] = "Formel #{$index}: Formel fehlt";
            } else {
                // Basis-Sicherheitsprüfung für Formeln
                $dangerous_patterns = array(
                    '/\beval\b/i',
                    '/\bexec\b/i',
                    '/\bsystem\b/i',
                    '/<\?php/i',
                    '/javascript:/i'
                );
                
                foreach ($dangerous_patterns as $pattern) {
                    if (preg_match($pattern, $formula['formula'])) {
                        $errors[] = "Formel #{$index}: Potentiell gefährlicher Inhalt erkannt";
                        break;
                    }
                }
            }
        }
    }

    return $errors;
}

/**
 * JSON-Feld sicher sanitisieren
 */
private function sanitize_json_field($data)
{
    if (is_string($data)) {
        // Bereits JSON-String - validieren
        $decoded = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            return wp_json_encode(array());
        }
    } elseif (is_array($data)) {
        // Array - in JSON konvertieren
        return wp_json_encode($data);
    } else {
        // Anderer Typ - leeres Array
        return wp_json_encode(array());
    }
}

/**
 * Datenbank bereinigen (für Wartung)
 */
public function cleanup_database()
{
    global $wpdb;

    $cleanup_results = array();

    try {
        // Sehr alte gelöschte Einträge endgültig löschen
        $deleted_count = $wpdb->query(
            "DELETE FROM {$this->table_calculators} 
             WHERE status = 'deleted' AND created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
        );
        $cleanup_results['deleted_calculators'] = $deleted_count;

        // Verwaiste Metadaten löschen (falls vorhanden)
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ecp_%' AND option_value = ''");

        $cleanup_results['success'] = true;
        $cleanup_results['message'] = "Bereinigung abgeschlossen: {$deleted_count} veraltete Einträge entfernt";

    } catch (Exception $e) {
        $cleanup_results['success'] = false;
        $cleanup_results['message'] = "Fehler bei Bereinigung: " . $e->getMessage();
    }

    return $cleanup_results;
}
}