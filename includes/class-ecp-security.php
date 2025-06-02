<?php

/**
 * Sicherheitsverbesserungen für Excel Calculator Pro
 */

class ECP_Security_Manager
{
    private $nonce_actions = array();
    private $rate_limits = array();
    private $security_headers = true;
    private $log_security_events = true;

    public function __construct()
    {
        $this->init_security_measures();
        $this->setup_rate_limiting();
        $this->init_input_sanitization();
    }

    private function init_security_measures()
    {
        // Nonce-Validierung für alle AJAX-Aktionen
        add_action('wp_ajax_ecp_save_calculator', array($this, 'validate_admin_nonce'), 1);
        add_action('wp_ajax_ecp_delete_calculator', array($this, 'validate_admin_nonce'), 1);
        add_action('wp_ajax_ecp_get_calculator', array($this, 'validate_admin_nonce'), 1);
        add_action('wp_ajax_ecp_import_calculator', array($this, 'validate_admin_nonce'), 1);

        // Frontend AJAX-Sicherheit
        add_action('wp_ajax_ecp_calculate', array($this, 'validate_frontend_nonce'), 1);
        add_action('wp_ajax_nopriv_ecp_calculate', array($this, 'validate_frontend_nonce'), 1);

        // Content-Security-Policy Headers
        add_action('wp_head', array($this, 'add_security_headers'), 1);

        // File-Upload Sicherheit
        add_filter('upload_mimes', array($this, 'restrict_upload_mimes'), 10, 2);
        add_filter('wp_check_filetype_and_ext', array($this, 'validate_file_upload'), 10, 4);

        // SQL-Injection Schutz
        add_filter('ecp_database_query', array($this, 'sanitize_database_input'), 10, 2);

        // XSS-Schutz für Ausgaben
        add_filter('ecp_output_value', array($this, 'escape_output'), 10, 2);

        // CSRF-Schutz für Formulare
        add_action('ecp_form_start', array($this, 'add_csrf_token'));
        add_action('ecp_form_validate', array($this, 'validate_csrf_token'));

        // Brute-Force Schutz
        add_action('wp_login_failed', array($this, 'log_failed_login'));
        add_filter('authenticate', array($this, 'check_brute_force'), 30, 3);

        // Plugin-spezifische Sicherheitsprüfungen
        add_action('admin_init', array($this, 'check_plugin_integrity'));
        add_action('wp_loaded', array($this, 'validate_plugin_permissions'));

        // Audit-Logging
        add_action('ecp_calculator_saved', array($this, 'log_calculator_action'), 10, 2);
        add_action('ecp_calculator_deleted', array($this, 'log_calculator_action'), 10, 2);
        add_action('ecp_settings_updated', array($this, 'log_settings_change'));
    }

    /**
     * Nonce-Validierung
     */
    public function validate_admin_nonce()
    {
        $action = $_POST['action'] ?? '';

        if (!check_ajax_referer('ecp_admin_nonce', 'nonce', false)) {
            $this->log_security_event('invalid_admin_nonce', $action);
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.', 'excel-calculator-pro'));
        }

        if (!current_user_can('manage_options')) {
            $this->log_security_event('insufficient_permissions', $action);
            wp_send_json_error(__('Unzureichende Berechtigungen.', 'excel-calculator-pro'));
        }
    }

    public function validate_frontend_nonce()
    {
        $action = $_POST['action'] ?? '';

        if (!check_ajax_referer('ecp_frontend_nonce', 'nonce', false)) {
            $this->log_security_event('invalid_frontend_nonce', $action);
            wp_send_json_error(__('Sicherheitsprüfung fehlgeschlagen.', 'excel-calculator-pro'));
        }

        // Rate-Limiting für Frontend-Aktionen
        if (!$this->check_rate_limit('frontend_ajax', 100, 3600)) { // 100 Requests pro Stunde
            wp_send_json_error(__('Zu viele Anfragen. Bitte warten Sie.', 'excel-calculator-pro'));
        }
    }

    /**
     * Rate-Limiting System
     */
    private function setup_rate_limiting()
    {
        $this->rate_limits = array(
            'admin_actions' => array('limit' => 200, 'window' => 3600), // 200/Stunde
            'frontend_ajax' => array('limit' => 100, 'window' => 3600), // 100/Stunde
            'file_uploads' => array('limit' => 10, 'window' => 3600),   // 10/Stunde
            'login_attempts' => array('limit' => 5, 'window' => 900)    // 5/15Min
        );
    }

    public function check_rate_limit($action, $limit = null, $window = null)
    {
        $user_ip = $this->get_client_ip();
        $user_id = get_current_user_id();

        // Identifikations-String: IP + User-ID (falls eingeloggt)
        $identifier = $user_ip . ($user_id ? '_user_' . $user_id : '');
        $cache_key = 'rate_limit_' . $action . '_' . md5($identifier);

        // Standard-Limits verwenden falls nicht spezifiziert
        if ($limit === null) {
            $limit = $this->rate_limits[$action]['limit'] ?? 50;
        }
        if ($window === null) {
            $window = $this->rate_limits[$action]['window'] ?? 3600;
        }

        $current_count = get_transient($cache_key) ?: 0;

        if ($current_count >= $limit) {
            $this->log_security_event('rate_limit_exceeded', $action, array(
                'identifier' => $identifier,
                'current_count' => $current_count,
                'limit' => $limit
            ));
            return false;
        }

        set_transient($cache_key, $current_count + 1, $window);
        return true;
    }

    /**
     * Input-Sanitization
     */
    private function init_input_sanitization()
    {
        // Automatische Sanitization für alle $_POST-Daten
        add_action('init', array($this, 'sanitize_global_input'));

        // Spezielle Sanitization für Plugin-Daten
        add_filter('ecp_sanitize_calculator_data', array($this, 'sanitize_calculator_data'));
        add_filter('ecp_sanitize_formula', array($this, 'sanitize_formula'));
        add_filter('ecp_sanitize_field_data', array($this, 'sanitize_field_data'));
    }

    public function sanitize_global_input()
    {
        // Nur für Plugin-spezifische Aktionen
        $action = $_POST['action'] ?? '';
        if (strpos($action, 'ecp_') !== 0) {
            return;
        }

        // Rekursive Sanitization
        $_POST = $this->deep_sanitize($_POST);
        $_GET = $this->deep_sanitize($_GET);
        $_REQUEST = $this->deep_sanitize($_REQUEST);
    }

    private function deep_sanitize($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->deep_sanitize($value);
            }
        } else {
            // Basis-Sanitization
            $data = trim($data);
            $data = stripslashes($data);

            // XSS-Schutz
            $data = wp_kses($data, $this->get_allowed_html());
        }

        return $data;
    }

    private function get_allowed_html()
    {
        return array(
            'b' => array(),
            'strong' => array(),
            'i' => array(),
            'em' => array(),
            'u' => array(),
            'br' => array(),
            'p' => array(),
            'span' => array('class' => array(), 'style' => array())
        );
    }

    public function sanitize_calculator_data($data)
    {
        $sanitized = array();

        // Name - erforderlich, alphanumerisch mit Leerzeichen
        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
            if (empty($sanitized['name'])) {
                throw new Exception(__('Kalkulator-Name ist erforderlich', 'excel-calculator-pro'));
            }
        }

        // Beschreibung - optional, Textarea-Content
        if (isset($data['description'])) {
            $sanitized['description'] = sanitize_textarea_field($data['description']);
        }

        // Felder - Array-Validierung
        if (isset($data['fields']) && is_array($data['fields'])) {
            $sanitized['fields'] = array();
            foreach ($data['fields'] as $field) {
                $sanitized_field = $this->sanitize_field_data($field);
                if ($sanitized_field) {
                    $sanitized['fields'][] = $sanitized_field;
                }
            }
        }

        // Formeln - Array mit spezieller Validierung
        if (isset($data['formulas']) && is_array($data['formulas'])) {
            $sanitized['formulas'] = array();
            foreach ($data['formulas'] as $formula) {
                $sanitized_formula = $this->sanitize_formula_data($formula);
                if ($sanitized_formula) {
                    $sanitized['formulas'][] = $sanitized_formula;
                }
            }
        }

        // Einstellungen - Key-Value Sanitization
        if (isset($data['settings'])) {
            $sanitized['settings'] = $this->sanitize_settings($data['settings']);
        }

        return $sanitized;
    }

    public function sanitize_field_data($field)
    {
        if (!is_array($field)) {
            return false;
        }

        $sanitized = array();

        // Feld-ID - alphanumerisch, Unterstriche erlaubt
        if (isset($field['id'])) {
            $sanitized['id'] = sanitize_key($field['id']);
            if (empty($sanitized['id']) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $sanitized['id'])) {
                throw new Exception(__('Ungültige Feld-ID: ' . $field['id'], 'excel-calculator-pro'));
            }
        }

        // Label - erforderlich
        if (isset($field['label'])) {
            $sanitized['label'] = sanitize_text_field($field['label']);
            if (empty($sanitized['label'])) {
                return false; // Feld ohne Label ignorieren
            }
        }

        // Typ - Whitelist
        $allowed_types = array('number', 'text', 'email', 'tel', 'url', 'date');
        if (isset($field['type'])) {
            $sanitized['type'] = in_array($field['type'], $allowed_types) ? $field['type'] : 'number';
        }

        // Numerische Felder
        foreach (array('min', 'max', 'step', 'default') as $numeric_field) {
            if (isset($field[$numeric_field])) {
                $value = $field[$numeric_field];
                if (is_numeric($value)) {
                    $sanitized[$numeric_field] = floatval($value);
                }
            }
        }

        // Text-Felder
        foreach (array('placeholder', 'unit', 'help') as $text_field) {
            if (isset($field[$text_field])) {
                $sanitized[$text_field] = sanitize_text_field($field[$text_field]);
            }
        }

        // Boolean-Felder - FIX für den Required-Bug
        $sanitized['required'] = isset($field['required']) ? filter_var($field['required'], FILTER_VALIDATE_BOOLEAN) : false;

        return $sanitized;
    }

    public function sanitize_formula_data($formula)
    {
        if (!is_array($formula)) {
            return false;
        }

        $sanitized = array();

        // Label - erforderlich
        if (isset($formula['label'])) {
            $sanitized['label'] = sanitize_text_field($formula['label']);
            if (empty($sanitized['label'])) {
                return false;
            }
        }

        // Formel - spezielle Validierung
        if (isset($formula['formula'])) {
            $sanitized['formula'] = $this->sanitize_formula($formula['formula']);
            if (empty($sanitized['formula'])) {
                throw new Exception(__('Ungültige Formel: ' . $formula['formula'], 'excel-calculator-pro'));
            }
        }

        // Format - Whitelist
        $allowed_formats = array('', 'currency', 'percentage', 'integer', 'text');
        if (isset($formula['format'])) {
            $sanitized['format'] = in_array($formula['format'], $allowed_formats) ? $formula['format'] : '';
        }

        // Text-Felder
        foreach (array('unit', 'help') as $text_field) {
            if (isset($formula[$text_field])) {
                $sanitized[$text_field] = sanitize_text_field($formula[$text_field]);
            }
        }

        return $sanitized;
    }

    public function sanitize_formula($formula)
    {
        // Gefährliche Funktionen und Keywords blockieren
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
            '/\bmysql_\w+\s*\(/i',
            '/\$_[A-Z]+\[/i',
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i'
        );

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $formula)) {
                $this->log_security_event('dangerous_formula_detected', 'formula_validation', array(
                    'formula' => $formula,
                    'pattern' => $pattern
                ));
                throw new Exception(__('Formel enthält nicht erlaubte Funktionen', 'excel-calculator-pro'));
            }
        }

        // Erlaubte Funktionen und Operatoren definieren
        $allowed_functions = array(
            'IF',
            'WENN',
            'ROUND',
            'RUNDEN',
            'MIN',
            'MAX',
            'SUM',
            'SUMME',
            'AVERAGE',
            'MITTELWERT',
            'ABS',
            'SQRT',
            'WURZEL',
            'POW',
            'POTENZ',
            'LOG',
            'TODAY',
            'HEUTE',
            'YEAR',
            'JAHR',
            'MONTH',
            'MONAT',
            'DAY',
            'TAG',
            'CEILING',
            'OBERGRENZE',
            'AUFRUNDEN',
            'FLOOR',
            'UNTERGRENZE',
            'ABRUNDEN',
            'RAND',
            'ZUFALLSZAHL'
        );

        // Basis-Bereinigung
        $formula = trim($formula);
        $formula = preg_replace('/\s+/', ' ', $formula); // Mehrfache Leerzeichen

        // Validierung der Zeichen (erweitert)
        if (!preg_match('/^[a-zA-Z0-9_+\-*\/().,\s<>=!&|ÄÖÜäöüß]+$/', $formula)) {
            throw new Exception(__('Formel enthält unerlaubte Zeichen', 'excel-calculator-pro'));
        }

        return $formula;
    }

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
     * File-Upload Sicherheit
     */
    public function restrict_upload_mimes($mimes, $user)
    {
        // Für ECP nur JSON erlauben
        if (isset($_POST['action']) && $_POST['action'] === 'ecp_import_calculator') {
            return array('json' => 'application/json');
        }

        return $mimes;
    }

    public function validate_file_upload($data, $file, $filename, $mimes)
    {
        if (isset($_POST['action']) && $_POST['action'] === 'ecp_import_calculator') {
            // Rate-Limiting für File-Uploads
            if (!$this->check_rate_limit('file_uploads', 10, 3600)) {
                $data['error'] = __('Zu viele Datei-Uploads. Bitte warten Sie.', 'excel-calculator-pro');
                return $data;
            }

            // Dateigrösse prüfen (1MB Maximum)
            if ($file['size'] > 1048576) {
                $data['error'] = __('Datei ist zu gross (Maximum: 1MB)', 'excel-calculator-pro');
                return $data;
            }

            // MIME-Type validieren
            if ($data['type'] !== 'application/json') {
                $data['error'] = __('Nur JSON-Dateien sind erlaubt', 'excel-calculator-pro');
                return $data;
            }

            // Dateiinhalt validieren
            $content = file_get_contents($file['tmp_name']);
            if (!$this->validate_json_content($content)) {
                $data['error'] = __('Ungültiger JSON-Inhalt oder Sicherheitsproblem erkannt', 'excel-calculator-pro');
                return $data;
            }
        }

        return $data;
    }

    private function validate_json_content($content)
    {
        // JSON-Validierung
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Sicherheitsprüfungen für JSON-Inhalt
        $content_lower = strtolower($content);
        $dangerous_strings = array(
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            '<script',
            'javascript:',
            'onload=',
            'onerror=',
            'document.',
            'window.',
            'parent.',
            '$_get',
            '$_post',
            '$_cookie',
            '$_session'
        );

        foreach ($dangerous_strings as $dangerous) {
            if (strpos($content_lower, $dangerous) !== false) {
                $this->log_security_event('dangerous_json_content', 'file_upload', array(
                    'content_snippet' => substr($content, 0, 200),
                    'dangerous_string' => $dangerous
                ));
                return false;
            }
        }

        return true;
    }

    /**
     * Security Headers
     */
    public function add_security_headers()
    {
        if (!$this->security_headers) {
            return;
        }

        // Nur für Plugin-Seiten
        if (!$this->is_plugin_page()) {
            return;
        }

        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self' " . admin_url('admin-ajax.php') . ";";

        echo '<meta http-equiv="Content-Security-Policy" content="' . esc_attr($csp) . '">' . "\n";

        // Weitere Security Headers
        echo '<meta http-equiv="X-Content-Type-Options" content="nosniff">' . "\n";
        echo '<meta http-equiv="X-Frame-Options" content="SAMEORIGIN">' . "\n";
        echo '<meta http-equiv="X-XSS-Protection" content="1; mode=block">' . "\n";
    }

    private function is_plugin_page()
    {
        global $post;

        // Admin-Seiten
        if (is_admin()) {
            $screen = get_current_screen();
            return $screen && strpos($screen->id, 'excel-calculator-pro') !== false;
        }

        // Frontend-Seiten mit Kalkulatoren
        if ($post) {
            return has_shortcode($post->post_content, 'excel_calculator') ||
                has_shortcode($post->post_content, 'ecp_calculator');
        }

        return false;
    }

    /**
     * Security Event Logging
     */
    private function log_security_event($event_type, $context = '', $details = array())
    {
        if (!$this->log_security_events) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'user_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'details' => $details
        );

        // Log in WordPress Option (begrenzt auf die letzten 100 Einträge)
        $security_log = get_option('ecp_security_log', array());
        array_unshift($security_log, $log_entry);
        $security_log = array_slice($security_log, 0, 100);

        update_option('ecp_security_log', $security_log);

        // Kritische Events auch in WordPress Error Log
        $critical_events = array('sql_injection_attempt', 'dangerous_formula_detected', 'brute_force_detected');
        if (in_array($event_type, $critical_events)) {
            error_log('ECP Security Alert: ' . $event_type . ' - ' . json_encode($log_entry));
        }

        // Hook für externe Security-Systeme
        do_action('ecp_security_event', $event_type, $log_entry);
    }

    public function get_security_log($limit = 50)
    {
        $log = get_option('ecp_security_log', array());
        return array_slice($log, 0, $limit);
    }

    /**
     * Brute-Force Schutz
     */
    public function log_failed_login($username)
    {
        $ip = $this->get_client_ip();
        $cache_key = 'login_attempts_' . md5($ip);

        $attempts = get_transient($cache_key) ?: 0;
        set_transient($cache_key, $attempts + 1, 900); // 15 Minuten

        if ($attempts >= 5) {
            $this->log_security_event('brute_force_detected', 'login', array(
                'username' => $username,
                'attempts' => $attempts + 1
            ));
        }
    }

    public function check_brute_force($user, $username, $password)
    {
        $ip = $this->get_client_ip();
        $cache_key = 'login_attempts_' . md5($ip);

        $attempts = get_transient($cache_key) ?: 0;

        if ($attempts >= 5) {
            return new WP_Error(
                'too_many_attempts',
                __('Zu viele Login-Versuche. Bitte warten Sie 15 Minuten.', 'excel-calculator-pro')
            );
        }

        return $user;
    }

    /**
     * Hilfsfunktionen
     */
    private function get_client_ip()
    {
        $ip_fields = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];

                // Erste IP bei komma-getrennten IPs
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }

                $ip = trim($ip);

                // IP-Validierung
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * CSRF-Schutz
     */
    public function add_csrf_token()
    {
        $token = wp_create_nonce('ecp_csrf_token');
        echo '<input type="hidden" name="ecp_csrf_token" value="' . esc_attr($token) . '">';
    }

    public function validate_csrf_token()
    {
        if (!isset($_POST['ecp_csrf_token']) || !wp_verify_nonce($_POST['ecp_csrf_token'], 'ecp_csrf_token')) {
            $this->log_security_event('csrf_token_validation_failed', 'form_submission');
            wp_die(__('Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.', 'excel-calculator-pro'));
        }
    }

    /**
     * Plugin-Integrität prüfen
     */
    public function check_plugin_integrity()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Prüfe kritische Plugin-Dateien
        $critical_files = array(
            ECP_PLUGIN_PATH . 'excel-calculator-pro.php',
            ECP_PLUGIN_PATH . 'includes/class-ecp-database.php',
            ECP_PLUGIN_PATH . 'includes/class-ecp-admin.php',
            ECP_PLUGIN_PATH . 'includes/class-ecp-frontend.php'
        );

        foreach ($critical_files as $file) {
            if (!file_exists($file)) {
                $this->log_security_event('plugin_file_missing', 'integrity_check', array('file' => $file));
                add_action('admin_notices', function () use ($file) {
                    echo '<div class="notice notice-error"><p><strong>Excel Calculator Pro:</strong> Kritische Datei fehlt: ' . basename($file) . '</p></div>';
                });
            }
        }
    }

    public function validate_plugin_permissions()
    {
        // Prüfe Datei-Berechtigungen
        $plugin_dir = ECP_PLUGIN_PATH;

        if (is_writable($plugin_dir)) {
            $this->log_security_event('plugin_directory_writable', 'permission_check', array(
                'directory' => $plugin_dir
            ));
        }
    }

    /**
     * Audit-Logging für wichtige Aktionen
     */
    public function log_calculator_action($calculator_id, $action = 'unknown')
    {
        $this->log_security_event('calculator_action', $action, array(
            'calculator_id' => $calculator_id,
            'action' => $action
        ));
    }

    public function log_settings_change()
    {
        $this->log_security_event('settings_changed', 'admin_action');
    }

    /**
     * Security-Dashboard für Admin
     */
    public function get_security_overview()
    {
        $overview = array();

        // Security-Log-Statistiken
        $log = $this->get_security_log(100);
        $overview['total_events'] = count($log);

        // Event-Typen zählen
        $event_counts = array();
        foreach ($log as $entry) {
            $type = $entry['event_type'];
            $event_counts[$type] = ($event_counts[$type] ?? 0) + 1;
        }
        $overview['event_counts'] = $event_counts;

        // Kritische Events der letzten 24h
        $critical_events = array_filter($log, function ($entry) {
            return strtotime($entry['timestamp']) > (time() - 86400) &&
                in_array($entry['event_type'], array('brute_force_detected', 'dangerous_formula_detected', 'sql_injection_attempt'));
        });
        $overview['critical_events_24h'] = count($critical_events);

        // Rate-Limiting Status
        $overview['rate_limits'] = $this->rate_limits;

        // Plugin-Integrität
        $overview['plugin_integrity'] = file_exists(ECP_PLUGIN_PATH . 'excel-calculator-pro.php');

        return $overview;
    }
}

/**
 * Security-Manager initialisieren
 */
function ecp_init_security_manager()
{
    global $ecp_security;
    $ecp_security = new ECP_Security_Manager();
    return $ecp_security;
}

// Security-Manager früh starten
add_action('plugins_loaded', 'ecp_init_security_manager', 1);
