<?php

/**
 * Optimierte Database Handler für Excel Calculator Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECP_Database
{
    private $table_calculators;
    private $cache_group = 'ecp_calculators';
    private $cache_time = 3600; // 1 hour

    public function __construct()
    {
        global $wpdb;
        $this->table_calculators = $wpdb->prefix . 'excel_calculators';

        add_action('plugins_loaded', array($this, 'maybe_upgrade_database'), 20);
    }

    /**
     * Database upgrade check
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
     * Create database tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Calculators table
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
            KEY created_by (created_by)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql_calculators);

        return !$wpdb->last_error;
    }

    /**
     * Save calculator
     */
    public function save_calculator($data)
    {
        global $wpdb;

        if (empty($data['name'])) {
            return false;
        }

        $calculator_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'fields' => is_string($data['fields']) ? $data['fields'] : wp_json_encode($data['fields']),
            'formulas' => is_string($data['formulas']) ? $data['formulas'] : wp_json_encode($data['formulas']),
            'settings' => is_string($data['settings'] ?? '') ? $data['settings'] : wp_json_encode($data['settings'] ?? array()),
            'status' => 'active',
            'created_by' => get_current_user_id()
        );

        $data_types = array('%s', '%s', '%s', '%s', '%s', '%s', '%d');

        try {
            if (isset($data['id']) && $data['id'] > 0) {
                // Update existing calculator
                unset($calculator_data['created_by']); // Don't update creator
                $result = $wpdb->update(
                    $this->table_calculators,
                    $calculator_data,
                    array('id' => intval($data['id'])),
                    array_slice($data_types, 0, -1), // Remove created_by type
                    array('%d')
                );

                if ($result !== false) {
                    $this->clear_cache($data['id']);
                    return intval($data['id']);
                }
            } else {
                // Insert new calculator
                $result = $wpdb->insert(
                    $this->table_calculators,
                    $calculator_data,
                    $data_types
                );

                if ($result !== false) {
                    $new_id = $wpdb->insert_id;
                    $this->clear_cache();
                    return $new_id;
                }
            }
        } catch (Exception $e) {
            error_log('ECP Database Error: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Get calculator by ID
     */
    public function get_calculator($id)
    {
        global $wpdb;

        $cache_key = "calculator_{$id}";
        $calculator = wp_cache_get($cache_key, $this->cache_group);

        if ($calculator === false) {
            $calculator = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_calculators} WHERE id = %d AND status = 'active'",
                intval($id)
            ));

            if ($calculator) {
                // Decode JSON fields
                $calculator->fields = json_decode($calculator->fields, true) ?: array();
                $calculator->formulas = json_decode($calculator->formulas, true) ?: array();
                $calculator->settings = json_decode($calculator->settings, true) ?: array();

                wp_cache_set($cache_key, $calculator, $this->cache_group, $this->cache_time);
            }
        }

        return $calculator;
    }

    /**
     * Get all calculators
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

        $cache_key = 'calculators_' . md5(serialize($args));
        $calculators = wp_cache_get($cache_key, $this->cache_group);

        if ($calculators === false) {
            $where = "WHERE status = %s";
            $query_args = array($args['status']);

            if (isset($args['search']) && !empty($args['search'])) {
                $where .= " AND (name LIKE %s OR description LIKE %s)";
                $search = '%' . $wpdb->esc_like($args['search']) . '%';
                $query_args[] = $search;
                $query_args[] = $search;
            }

            $orderby = in_array($args['orderby'], array('id', 'name', 'created_at', 'updated_at'))
                ? $args['orderby'] : 'created_at';
            $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

            $limit = '';
            if ($args['limit'] > 0) {
                $limit = $wpdb->prepare(" LIMIT %d OFFSET %d", intval($args['limit']), intval($args['offset']));
            }

            $sql = "SELECT * FROM {$this->table_calculators} {$where} ORDER BY {$orderby} {$order}{$limit}";
            $calculators = $wpdb->get_results($wpdb->prepare($sql, $query_args));

            // Add field and formula counts for display
            foreach ($calculators as $calculator) {
                $calculator->field_count = count(json_decode($calculator->fields, true) ?: array());
                $calculator->formula_count = count(json_decode($calculator->formulas, true) ?: array());
            }

            wp_cache_set($cache_key, $calculators, $this->cache_group, $this->cache_time);
        }

        return $calculators;
    }

    /**
     * Delete calculator (soft delete)
     */
    public function delete_calculator($id)
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_calculators,
            array('status' => 'deleted'),
            array('id' => intval($id)),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            $this->clear_cache($id);
            return true;
        }

        return false;
    }

    /**
     * Export calculator
     */
    public function export_calculator($id)
    {
        $calculator = $this->get_calculator($id);

        if (!$calculator) {
            return false;
        }

        return array(
            'name' => $calculator->name,
            'description' => $calculator->description,
            'fields' => $calculator->fields,
            'formulas' => $calculator->formulas,
            'settings' => $calculator->settings,
            'export_version' => ECP_VERSION,
            'export_date' => current_time('mysql')
        );
    }

    /**
     * Import calculator
     */
    public function import_calculator($data)
    {
        if (!isset($data['name']) || !isset($data['fields']) || !isset($data['formulas'])) {
            return false;
        }

        // Add import suffix to name
        $data['name'] = $data['name'] . ' (' . __('Importiert', 'excel-calculator-pro') . ')';

        return $this->save_calculator($data);
    }

    /**
     * Clear cache
     */
    private function clear_cache($calculator_id = null)
    {
        if ($calculator_id) {
            wp_cache_delete("calculator_{$calculator_id}", $this->cache_group);
        }

        // Clear all calculator list caches
        wp_cache_flush_group($this->cache_group);
    }

    /**
     * Get database statistics
     */
    public function get_stats()
    {
        global $wpdb;

        $stats = array();

        $stats['total_calculators'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_calculators} WHERE status = 'active'"
        );

        $stats['recent_calculators'] = $wpdb->get_results(
            "SELECT name, created_at FROM {$this->table_calculators} 
             WHERE status = 'active' 
             ORDER BY created_at DESC 
             LIMIT 5"
        );

        return $stats;
    }

    /**
     * Database cleanup
     */
    public function cleanup()
    {
        global $wpdb;

        // Delete calculators marked as deleted older than 30 days
        $wpdb->query(
            "DELETE FROM {$this->table_calculators} 
             WHERE status = 'deleted' 
             AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Clear cache
        $this->clear_cache();
    }
}
