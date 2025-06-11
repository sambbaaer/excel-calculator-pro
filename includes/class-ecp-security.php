<?php

/**
 * Verbessertes Security-System für Excel Calculator Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class ECP_Security
{
    private $rate_limits = array();
    private $security_events = array();
    private $allowed_functions = array();

    public function __construct()
    {
        $this->init_security();
        $this->setup_rate_limits();
        $this->define_allowed_functions();
    }

    private function init_security()
    {
        // Nonce validation for all AJAX requests
        add_action('wp_ajax_ecp_save_calculator', array($this, 'validate_admin_request'), 1);
        add_action('wp_ajax_ecp_delete_calculator', array($this, 'validate_admin_request'), 1);
        add_action('wp_ajax_ecp_get_calculator', array($this, 'validate_admin_request'), 1);
        add_action('wp_ajax_ecp_import_calculator', array($this, 'validate_admin_request'), 1);
        add_action('wp_ajax_ecp_export_calculator', array($this, 'validate_admin_request'), 1);

        // Input sanitization hooks
        add_filter('ecp_sanitize_calculator_data', array($this, 'sanitize_calculator_data'));
        add_filter('ecp_validate_formula', array($this, 'validate_formula'));

        // File upload security
        add_filter('wp_handle_upload_prefilter', array($this, 'validate_file_upload'));

        // Audit logging
        add_action('ecp_calculator_saved', array($this, 'log_calculator_action'), 10, 2);
        add_action('ecp_calculator_deleted', array($this, 'log_calculator_action'), 10, 2);
    }

    private function setup_rate_limits()
    {
        $this->rate_limits = array(
            'admin_actions' => array('limit' => 100, 'window' => 3600),
            'file_uploads' => array('limit' => 5, 'window' => 3600),
            'failed_validations' => array('limit' => 10, 'window' => 900)
        );
    }

    private function define_allowed_functions()
    {
        $this->allowed_functions = array(
            // German functions
            'WENN',
            'RUNDEN',
            'MIN',
            'MAX',
            'SUMME',
            'MITTELWERT',
            'ABS',
            'WURZEL',
            'POTENZ',
            'LOG',
            'HEUTE',
            'JAHR',
            'MONAT',
            'TAG',

            // English functions
            'IF',
            'ROUND',
            'SUM',
            'AVERAGE',
            'SQRT',
            'POW',
            'TODAY',
            'YEAR',
            'MONTH',
            'DAY',

            // Math functions
            'CEILING',
            'FLOOR',
            'RAND',
            'PI',
            'E'
        );
    }

    /**
     * Validate admin AJAX requests
     */
    public function validate_admin_request()
    {
        // Check nonce
        if (!check_ajax_referer('ecp_admin_nonce', 'nonce', false)) {
            $this->log_security_event('invalid_nonce', $_POST['action'] ?? 'unknown');
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen.', 'excel-calculator-pro')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            $this->log_security_event('insufficient_permissions', $_POST['action'] ?? 'unknown');
            wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'excel-calculator-pro')));
        }

        // Rate limiting
        if (!$this->check_rate_limit('admin_actions')) {
            $this->log_security_event('rate_limit_exceeded', 'admin_actions');
            wp_send_json_error(array('message' => __('Zu viele Anfragen. Bitte warten Sie.', 'excel-calculator-pro')));
        }
    }

    /**
     * Sanitize calculator data
     */
    public function sanitize_calculator_data($data)
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Calculator data must be an array');
        }

        $sanitized = array();

        // Name validation
        if (empty($data['name'])) {
            throw new InvalidArgumentException(__('Name ist erforderlich.', 'excel-calculator-pro'));
        }
        $sanitized['name'] = sanitize_text_field($data['name']);

        if (strlen($sanitized['name']) > 255) {
            throw new InvalidArgumentException(__('Name ist zu lang (max. 255 Zeichen).', 'excel-calculator-pro'));
        }

        // Description
        $sanitized['description'] = sanitize_textarea_field($data['description'] ?? '');

        // Fields validation
        if (isset($data['fields'])) {
            $sanitized['fields'] = $this->sanitize_fields($data['fields']);
        }

        // Formulas validation
        if (isset($data['formulas'])) {
            $sanitized['formulas'] = $this->sanitize_formulas($data['formulas']);
        }

        // Settings validation
        if (isset($data['settings'])) {
            $sanitized['settings'] = $this->sanitize_settings($data['settings']);
        }

        return $sanitized;
    }

    /**
     * Sanitize fields array
     */
    private function sanitize_fields($fields)
    {
        if (!is_array($fields)) {
            return array();
        }

        $sanitized = array();
        $field_ids = array();

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $sanitized_field = $this->sanitize_single_field($field);

            if ($sanitized_field) {
                // Check for duplicate field IDs
                if (in_array($sanitized_field['id'], $field_ids)) {
                    throw new InvalidArgumentException(
                        sprintf(__('Doppelte Feld-ID: %s', 'excel-calculator-pro'), $sanitized_field['id'])
                    );
                }

                $field_ids[] = $sanitized_field['id'];
                $sanitized[] = $sanitized_field;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize single field
     */
    private function sanitize_single_field($field)
    {
        if (empty($field['id']) || empty($field['label'])) {
            return null;
        }

        $sanitized = array();

        // Validate field ID
        $field_id = sanitize_key($field['id']);
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field_id)) {
            throw new InvalidArgumentException(
                sprintf(__('Ungültige Feld-ID: %s', 'excel-calculator-pro'), $field['id'])
            );
        }
        $sanitized['id'] = $field_id;

        // Validate label
        $sanitized['label'] = sanitize_text_field($field['label']);
        if (strlen($sanitized['label']) > 255) {
            throw new InvalidArgumentException(__('Feld-Label ist zu lang.', 'excel-calculator-pro'));
        }

        // Type validation
        $allowed_types = array('number', 'text', 'email', 'tel', 'url');
        $sanitized['type'] = in_array($field['type'] ?? 'number', $allowed_types)
            ? $field['type'] : 'number';

        // Numeric fields
        foreach (array('min', 'max', 'step', 'default') as $numeric_field) {
            if (isset($field[$numeric_field]) && is_numeric($field[$numeric_field])) {
                $sanitized[$numeric_field] = floatval($field[$numeric_field]);
            }
        }

        // Text fields
        foreach (array('unit', 'placeholder', 'help') as $text_field) {
            if (isset($field[$text_field])) {
                $sanitized[$text_field] = sanitize_text_field($field[$text_field]);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize formulas array
     */
    private function sanitize_formulas($formulas)
    {
        if (!is_array($formulas)) {
            return array();
        }

        $sanitized = array();

        foreach ($formulas as $formula) {
            if (!is_array($formula)) {
                continue;
            }

            $sanitized_formula = $this->sanitize_single_formula($formula);

            if ($sanitized_formula) {
                $sanitized[] = $sanitized_formula;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize single formula
     */
    private function sanitize_single_formula($formula)
    {
        if (empty($formula['label']) || empty($formula['formula'])) {
            return null;
        }

        $sanitized = array();

        // Label validation
        $sanitized['label'] = sanitize_text_field($formula['label']);
        if (strlen($sanitized['label']) > 255) {
            throw new InvalidArgumentException(__('Formel-Label ist zu lang.', 'excel-calculator-pro'));
        }

        // Formula validation
        $sanitized['formula'] = $this->validate_formula($formula['formula']);

        // Format validation
        $allowed_formats = array('', 'currency', 'percentage', 'integer', 'text');
        $sanitized['format'] = in_array($formula['format'] ?? '', $allowed_formats)
            ? $formula['format'] : '';

        // Other fields
        foreach (array('unit', 'help') as $field) {
            if (isset($formula[$field])) {
                $sanitized[$field] = sanitize_text_field($formula[$field]);
            }
        }

        return $sanitized;
    }

    /**
     * Validate formula syntax and security
     */
    public function validate_formula($formula)
    {
        if (!is_string($formula)) {
            throw new InvalidArgumentException(__('Formel muss ein String sein.', 'excel-calculator-pro'));
        }

        $formula = trim($formula);

        if (empty($formula)) {
            throw new InvalidArgumentException(__('Formel darf nicht leer sein.', 'excel-calculator-pro'));
        }

        // Check formula length
        if (strlen($formula) > 5000) {
            throw new InvalidArgumentException(__('Formel ist zu lang (max. 5000 Zeichen).', 'excel-calculator-pro'));
        }

        // Security checks - dangerous patterns
        $dangerous_patterns = array(
            '/\beval\s*\(/i',
            '/\bexec\s*\(/i',
            '/\bsystem\s*\(/i',
            '/\bshell_exec\s*\(/i',
            '/\bpassthru\s*\(/i',
            '/\bfile_get_contents\s*\(/i',
            '/\bfile_put_contents\s*\(/i',
            '/\bfopen\s*\(/i',
            '/\bfwrite\s*\(/i',
            '/\bunlink\s*\(/i',
            '/\$_[A-Z]+\[/i',
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/document\./i',
            '/window\./i',
            '/alert\s*\(/i'
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $formula)) {
                $this->log_security_event('dangerous_formula', $formula);
                throw new InvalidArgumentException(__('Formel enthält nicht erlaubte Funktionen.', 'excel-calculator-pro'));
            }
        }

        // Validate allowed characters
        if (!preg_match('/^[a-zA-Z0-9_+\-*\/().,\s<>=!&|äöüÄÖÜß;]+$/', $formula)) {
            throw new InvalidArgumentException(__('Formel enthält unerlaubte Zeichen.', 'excel-calculator-pro'));
        }

        // Validate function names
        $this->validate_formula_functions($formula);

        // Validate parentheses balance
        if (substr_count($formula, '(') !== substr_count($formula, ')')) {
            throw new InvalidArgumentException(__('Unausgeglichene Klammern in der Formel.', 'excel-calculator-pro'));
        }

        return $formula;
    }

    /**
     * Validate formula functions
     */
    private function validate_formula_functions($formula)
    {
        // Extract function names
        preg_match_all('/([A-Z_]+)\s*\(/i', $formula, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $function_name) {
                $function_name = strtoupper($function_name);

                if (!in_array($function_name, $this->allowed_functions)) {
                    throw new InvalidArgumentException(
                        sprintf(__('Nicht erlaubte Funktion: %s', 'excel-calculator-pro'), $function_name)
                    );
                }
            }
        }
    }

    /**
     * Sanitize settings array
     */
    private function sanitize_settings($settings)
    {
        if (!is_array($settings)) {
            return array();
        }

        $sanitized = array();

        foreach ($settings as $key => $value) {
            $clean_key = sanitize_key($key);

            if (is_string($value)) {
                $sanitized[$clean_key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$clean_key] = floatval($value);
            } elseif (is_bool($value)) {
                $sanitized[$clean_key] = (bool) $value;
            } elseif (is_array($value)) {
                $sanitized[$clean_key] = $this->sanitize_settings($value);
            }
        }

        return $sanitized;
    }

    /**
     * Validate file upload
     */
    public function validate_file_upload($file)
    {
        // Only process ECP uploads
        if (!isset($_POST['action']) || $_POST['action'] !== 'ecp_import_calculator') {
            return $file;
        }

        // Rate limiting for uploads
        if (!$this->check_rate_limit('file_uploads')) {
            $file['error'] = __('Zu viele Datei-Uploads. Bitte warten Sie.', 'excel-calculator-pro');
            return $file;
        }

        // File size check (1MB max)
        if ($file['size'] > 1048576) {
            $file['error'] = __('Datei ist zu groß (max. 1MB).', 'excel-calculator-pro');
            return $file;
        }

        // MIME type check
        $allowed_types = array('application/json', 'text/plain');
        if (!in_array($file['type'], $allowed_types)) {
            $file['error'] = __('Nur JSON-Dateien sind erlaubt.', 'excel-calculator-pro');
            return $file;
        }

        // Content validation
        if (!$this->validate_json_content($file['tmp_name'])) {
            $file['error'] = __('Ungültiger JSON-Inhalt.', 'excel-calculator-pro');
            return $file;
        }

        return $file;
    }

    /**
     * Validate JSON file content
     */
    private function validate_json_content($file_path)
    {
        $content = file_get_contents($file_path);

        if (!$content) {
            return false;
        }

        // JSON validation
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Structure validation
        $required_fields = array('name', 'fields', 'formulas');
        foreach ($required_fields as $field) {
            if (!isset($decoded[$field])) {
                return false;
            }
        }

        // Security check for content
        $content_lower = strtolower($content);
        $dangerous_strings = array(
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            '<script',
            'javascript:',
            'document.',
            'window.',
            '$_get',
            '$_post',
            '$_cookie'
        );

        foreach ($dangerous_strings as $dangerous) {
            if (strpos($content_lower, $dangerous) !== false) {
                $this->log_security_event('dangerous_import_content', $dangerous);
                return false;
            }
        }

        return true;
    }

    /**
     * Rate limiting check
     */
    private function check_rate_limit($action)
    {
        $user_id = get_current_user_id();
        $user_ip = $this->get_client_ip();
        $identifier = $user_id ? "user_{$user_id}" : "ip_{$user_ip}";

        $cache_key = "rate_limit_{$action}_{$identifier}";
        $current_count = get_transient($cache_key) ?: 0;

        $limit = $this->rate_limits[$action]['limit'] ?? 50;
        $window = $this->rate_limits[$action]['window'] ?? 3600;

        if ($current_count >= $limit) {
            return false;
        }

        set_transient($cache_key, $current_count + 1, $window);
        return true;
    }

    /**
     * Log security events
     */
    private function log_security_event($event_type, $details = '')
    {
        $event = array(
            'type' => $event_type,
            'details' => $details,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        );

        // Store in database or option
        $security_log = get_option('ecp_security_log', array());
        array_unshift($security_log, $event);

        // Keep only last 100 events
        $security_log = array_slice($security_log, 0, 100);
        update_option('ecp_security_log', $security_log);

        // Log critical events to error log
        $critical_events = array('dangerous_formula', 'dangerous_import_content', 'rate_limit_exceeded');
        if (in_array($event_type, $critical_events)) {
            error_log("ECP Security Alert: {$event_type} - " . json_encode($event));
        }

        // Hook for external security systems
        do_action('ecp_security_event', $event);
    }

    /**
     * Log calculator actions for audit trail
     */
    public function log_calculator_action($calculator_id, $action)
    {
        $this->log_security_event('calculator_action', array(
            'calculator_id' => $calculator_id,
            'action' => $action
        ));
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip_fields = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];

                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }

                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get security log
     */
    public function get_security_log($limit = 50)
    {
        $log = get_option('ecp_security_log', array());
        return array_slice($log, 0, $limit);
    }

    /**
     * Get security statistics
     */
    public function get_security_stats()
    {
        $log = $this->get_security_log(100);

        $stats = array(
            'total_events' => count($log),
            'events_24h' => 0,
            'critical_events_24h' => 0,
            'top_event_types' => array()
        );

        $event_counts = array();
        $critical_events = array('dangerous_formula', 'dangerous_import_content', 'rate_limit_exceeded');
        $cutoff_time = time() - 86400; // 24 hours ago

        foreach ($log as $event) {
            $event_time = strtotime($event['timestamp']);

            if ($event_time > $cutoff_time) {
                $stats['events_24h']++;

                if (in_array($event['type'], $critical_events)) {
                    $stats['critical_events_24h']++;
                }
            }

            $event_counts[$event['type']] = ($event_counts[$event['type']] ?? 0) + 1;
        }

        arsort($event_counts);
        $stats['top_event_types'] = array_slice($event_counts, 0, 5, true);

        return $stats;
    }

    /**
     * Security health check
     */
    public function security_health_check()
    {
        $issues = array();

        // Check for recent critical events
        $stats = $this->get_security_stats();
        if ($stats['critical_events_24h'] > 0) {
            $issues[] = sprintf(
                __('%d kritische Sicherheitsereignisse in den letzten 24 Stunden.', 'excel-calculator-pro'),
                $stats['critical_events_24h']
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            $issues[] = __('WordPress-Version ist nicht aktuell.', 'excel-calculator-pro');
        }

        // Check SSL
        if (!is_ssl()) {
            $issues[] = __('SSL ist nicht aktiviert.', 'excel-calculator-pro');
        }

        // Check file permissions
        if (is_writable(ECP_PLUGIN_PATH)) {
            $issues[] = __('Plugin-Verzeichnis ist beschreibbar.', 'excel-calculator-pro');
        }

        return $issues;
    }
}

/**
 * Initialize security system
 */
function ecp_init_security()
{
    global $ecp_security;
    $ecp_security = new ECP_Security();
    return $ecp_security;
}

// Start security system early
add_action('plugins_loaded', 'ecp_init_security', 1);
