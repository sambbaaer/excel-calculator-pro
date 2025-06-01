/**
 * Verbesserte Formula Parser Klasse für Excel Calculator Pro
 * Ersetzt die bestehende ECPFormulaParser Klasse in frontend.js
 */

class ECPFormulaParser {
    constructor() {
        this.functions = {
            // Deutsche Funktionen
            'WENN': this.IF.bind(this),
            'RUNDEN': this.ROUND.bind(this),
            'MIN': this.MIN.bind(this),
            'MAX': this.MAX.bind(this),
            'SUMME': this.SUM.bind(this),
            'MITTELWERT': this.AVERAGE.bind(this),
            'ABS': this.ABS.bind(this),
            'WURZEL': this.SQRT.bind(this),
            'POTENZ': this.POW.bind(this),
            'LOG': this.LOG.bind(this),
            'HEUTE': this.TODAY.bind(this),
            'JAHR': this.YEAR.bind(this),
            'MONAT': this.MONTH.bind(this),
            'TAG': this.DAY.bind(this),
            'OBERGRENZE': this.CEILING.bind(this),
            'UNTERGRENZE': this.FLOOR.bind(this),
            'ZUFALLSZAHL': this.RAND.bind(this),
            // Englische Funktionen
            'IF': this.IF.bind(this),
            'ROUND': this.ROUND.bind(this),
            'SUM': this.SUM.bind(this),
            'AVERAGE': this.AVERAGE.bind(this),
            'SQRT': this.SQRT.bind(this),
            'POW': this.POW.bind(this),
            'TODAY': this.TODAY.bind(this),
            'YEAR': this.YEAR.bind(this),
            'MONTH': this.MONTH.bind(this),
            'DAY': this.DAY.bind(this),
            'CEILING': this.CEILING.bind(this),
            'FLOOR': this.FLOOR.bind(this),
            'RAND': this.RAND.bind(this)
        };

        this.constants = {
            'PI': Math.PI,
            'E': Math.E,
            'PHI': (1 + Math.sqrt(5)) / 2
        };

        this.debugMode = false;
    }

    parse(formula, values, debug = false) {
        this.debugMode = debug;

        try {
            if (!formula || typeof formula !== 'string') {
                return 0;
            }

            let processedFormula = formula.trim();

            if (this.debugMode) {
                console.log('=== FORMEL-DEBUG START ===');
                console.log('Original Formel:', formula);
                console.log('Eingabewerte:', values);
            }

            // Schritt 1: Konstanten ersetzen
            processedFormula = this.replaceConstants(processedFormula);

            // Schritt 2: Feldnamen durch Werte ersetzen
            processedFormula = this.replaceFieldValues(processedFormula, values);

            // Schritt 3: Funktionen verarbeiten (iterativ für verschachtelte Funktionen)
            processedFormula = this.processFunctions(processedFormula, values);

            // Schritt 4: Mathematische Ausdrücke auswerten
            const result = this.evaluateExpression(processedFormula);

            if (this.debugMode) {
                console.log('Verarbeitete Formel:', processedFormula);
                console.log('Endergebnis:', result);
                console.log('=== FORMEL-DEBUG ENDE ===');
            }

            return isFinite(result) && !isNaN(result) ? result : 0;

        } catch (error) {
            if (this.debugMode) {
                console.error('Formel-Fehler:', error);
                console.log('Formel zum Zeitpunkt des Fehlers:', processedFormula);
            }
            return 0;
        }
    }

    replaceConstants(formula) {
        let result = formula;
        for (let constant in this.constants) {
            const regex = new RegExp('\\b' + this.escapeRegExp(constant) + '\\b', 'g');
            result = result.replace(regex, this.constants[constant]);
        }
        return result;
    }

    replaceFieldValues(formula, values) {
        let result = formula;

        // Sortiere Feldnamen nach Länge (längste zuerst) um Teilstring-Probleme zu vermeiden
        const sortedFields = Object.keys(values || {}).sort((a, b) => b.length - a.length);

        for (let fieldId of sortedFields) {
            const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
            const value = this.sanitizeNumber(values[fieldId]);
            result = result.replace(regex, value);
        }

        return result;
    }

    sanitizeNumber(value) {
        if (value === null || value === undefined || value === '') {
            return '0';
        }

        const num = parseFloat(value);
        return isFinite(num) ? num.toString() : '0';
    }

    processFunctions(formula, values) {
        let result = formula;
        let maxIterations = 20; // Erhöht für komplexe verschachtelte Funktionen
        let iteration = 0;

        while (iteration < maxIterations) {
            let hasChanges = false;
            let originalFormula = result;

            // Funktionen von innen nach aussen verarbeiten
            const functionPattern = /(\w+)\s*\(([^()]*)\)/g;
            let match;

            while ((match = functionPattern.exec(result)) !== null) {
                const funcName = match[1].toUpperCase();
                const args = match[2];

                if (this.functions[funcName]) {
                    try {
                        const funcResult = this.functions[funcName](args, values);
                        const replacement = isFinite(funcResult) ? funcResult.toString() : '0';
                        result = result.replace(match[0], replacement);
                        hasChanges = true;

                        if (this.debugMode) {
                            console.log(`Funktion ${funcName}(${args}) = ${replacement}`);
                        }

                        // Reset regex für nächste Iteration
                        functionPattern.lastIndex = 0;
                        break;
                    } catch (error) {
                        if (this.debugMode) {
                            console.warn(`Fehler in Funktion ${funcName}:`, error);
                        }
                        result = result.replace(match[0], '0');
                        hasChanges = true;
                        functionPattern.lastIndex = 0;
                        break;
                    }
                }
            }

            if (!hasChanges) {
                break;
            }

            iteration++;
        }

        if (iteration >= maxIterations) {
            console.warn('Maximum Funktions-Iterationen erreicht');
        }

        return result;
    }

    evaluateExpression(expression) {
        // Bereinigung und Validierung
        let cleaned = expression.toString()
            .replace(/,/g, '.') // Kommas durch Punkte
            .replace(/\s+/g, '') // Leerzeichen entfernen
            .trim();

        // Sicherheitsprüfung: Nur erlaubte Zeichen
        if (!/^[0-9+\-*/().,^]+$/.test(cleaned)) {
            throw new Error('Unerlaubte Zeichen in mathematischem Ausdruck');
        }

        // Potenz-Operator behandeln
        cleaned = this.handlePowerOperator(cleaned);

        // Division durch Null vermeiden
        cleaned = this.preventDivisionByZero(cleaned);

        try {
            // Sichere Evaluierung mit Function Constructor
            const result = new Function('return ' + cleaned)();
            return result;
        } catch (error) {
            throw new Error('Mathematischer Evaluierungsfehler: ' + error.message);
        }
    }

    handlePowerOperator(expression) {
        // Ersetzt a^b durch Math.pow(a,b), dabei Klammern beachten
        return expression.replace(/([0-9.]+|\([^)]+\))\s*\^\s*([0-9.]+|\([^)]+\))/g, 'Math.pow($1,$2)');
    }

    preventDivisionByZero(expression) {
        // Ersetzt /0 durch /0.000001 um Division durch Null zu vermeiden
        return expression.replace(/\/\s*0(?!\d)/g, '/0.000001');
    }

    escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // === Funktions-Implementierungen (verbessert) ===

    IF(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 3) return 0;

        const condition = this.evaluateCondition(parts[0], values);
        const trueValue = this.parseNumericValue(parts[1], values);
        const falseValue = this.parseNumericValue(parts[2], values);

        return condition ? trueValue : falseValue;
    }

    ROUND(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 1) return 0;

        const value = this.parseNumericValue(parts[0], values);
        const decimals = parts.length > 1 ? Math.floor(this.parseNumericValue(parts[1], values)) : 0;

        const factor = Math.pow(10, Math.max(0, Math.min(15, decimals))); // Begrenzt auf 0-15 Dezimalstellen
        return Math.round(value * factor) / factor;
    }

    MIN(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length === 0) return 0;

        const numbers = parts.map(part => this.parseNumericValue(part, values))
            .filter(num => isFinite(num));

        return numbers.length > 0 ? Math.min(...numbers) : 0;
    }

    MAX(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length === 0) return 0;

        const numbers = parts.map(part => this.parseNumericValue(part, values))
            .filter(num => isFinite(num));

        return numbers.length > 0 ? Math.max(...numbers) : 0;
    }

    SUM(args, values) {
        const parts = this.parseArguments(args);
        return parts.reduce((sum, part) => {
            const value = this.parseNumericValue(part, values);
            return sum + (isFinite(value) ? value : 0);
        }, 0);
    }

    AVERAGE(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length === 0) return 0;

        const numbers = parts.map(part => this.parseNumericValue(part, values))
            .filter(num => isFinite(num));

        return numbers.length > 0 ? numbers.reduce((a, b) => a + b, 0) / numbers.length : 0;
    }

    ABS(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 1) return 0;
        return Math.abs(this.parseNumericValue(parts[0], values));
    }

    SQRT(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 1) return 0;
        const value = this.parseNumericValue(parts[0], values);
        return value >= 0 ? Math.sqrt(value) : 0;
    }

    POW(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 2) return 0;
        const base = this.parseNumericValue(parts[0], values);
        const exponent = this.parseNumericValue(parts[1], values);

        // Sicherheitsbegrenzungen für Potenzierung
        if (Math.abs(base) > 1000 && Math.abs(exponent) > 10) {
            return 0; // Verhindert extreme Berechnungen
        }

        return Math.pow(base, exponent);
    }

    LOG(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 1) return 0;

        const value = this.parseNumericValue(parts[0], values);
        if (value <= 0) return 0;

        const base = parts.length > 1 ? this.parseNumericValue(parts[1], values) : Math.E;
        if (base <= 0 || base === 1) return 0;

        return Math.log(value) / Math.log(base);
    }

    TODAY(args, values) {
        const today = new Date();
        return today.getFullYear() * 10000 + (today.getMonth() + 1) * 100 + today.getDate();
    }

    YEAR(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length === 0) {
            return new Date().getFullYear();
        }

        const dateValue = this.parseNumericValue(parts[0], values);
        const date = this.parseDate(dateValue);
        return date ? date.getFullYear() : new Date().getFullYear();
    }

    MONTH(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length === 0) {
            return new Date().getMonth() + 1;
        }

        const dateValue = this.parseNumericValue(parts[0], values);
        const date = this.parseDate(dateValue);
        return date ? date.getMonth() + 1 : new Date().getMonth() + 1;
    }

    DAY(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length === 0) {
            return new Date().getDate();
        }

        const dateValue = this.parseNumericValue(parts[0], values);
        const date = this.parseDate(dateValue);
        return date ? date.getDate() : new Date().getDate();
    }

    CEILING(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 1) return 0;
        return Math.ceil(this.parseNumericValue(parts[0], values));
    }

    FLOOR(args, values) {
        const parts = this.parseArguments(args);
        if (parts.length < 1) return 0;
        return Math.floor(this.parseNumericValue(parts[0], values));
    }

    RAND(args, values) {
        return Math.random();
    }

    // === Hilfsfunktionen (verbessert) ===

    parseArguments(args) {
        if (!args || args.trim() === '') return [];

        const result = [];
        let current = '';
        let parenthesisLevel = 0;
        let inQuotes = false;
        let quoteChar = '';

        for (let i = 0; i < args.length; i++) {
            const char = args[i];

            if ((char === '"' || char === "'") && !inQuotes) {
                inQuotes = true;
                quoteChar = char;
                current += char;
            } else if (char === quoteChar && inQuotes) {
                inQuotes = false;
                quoteChar = '';
                current += char;
            } else if (!inQuotes && char === '(') {
                parenthesisLevel++;
                current += char;
            } else if (!inQuotes && char === ')') {
                parenthesisLevel--;
                current += char;
            } else if (!inQuotes && (char === ',' || char === ';') && parenthesisLevel === 0) {
                if (current.trim()) {
                    result.push(current.trim());
                }
                current = '';
            } else {
                current += char;
            }
        }

        if (current.trim()) {
            result.push(current.trim());
        }

        return result;
    }

    parseNumericValue(value, values) {
        if (typeof value === 'number') return value;

        let stringValue = value.toString().trim();

        // Anführungszeichen entfernen
        stringValue = stringValue.replace(/^["']|["']$/g, '');

        // Direkte Zahl
        const directNumber = parseFloat(stringValue);
        if (!isNaN(directNumber) && stringValue !== '') {
            return directNumber;
        }

        // Feldwert
        if (values && values[stringValue] !== undefined) {
            const fieldValue = parseFloat(values[stringValue]);
            return isFinite(fieldValue) ? fieldValue : 0;
        }

        // Konstante
        if (this.constants[stringValue] !== undefined) {
            return this.constants[stringValue];
        }

        // Einfachen mathematischen Ausdruck versuchen zu evaluieren
        try {
            return this.evaluateExpression(stringValue);
        } catch (e) {
            return 0;
        }
    }

    parseDate(value) {
        if (typeof value === 'number') {
            // Format: YYYYMMDD
            if (value > 19000101 && value < 30001231) {
                const year = Math.floor(value / 10000);
                const month = Math.floor((value % 10000) / 100) - 1;
                const day = value % 100;
                return new Date(year, month, day);
            }
            // Timestamp
            return new Date(value);
        }

        // String-Datum parsen
        const date = new Date(value);
        return isNaN(date.getTime()) ? null : date;
    }

    evaluateCondition(condition, values) {
        let conditionStr = condition.toString().trim();

        // Feldnamen durch Werte ersetzen
        for (let fieldId in (values || {})) {
            const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
            const fieldValue = this.parseNumericValue(fieldId, values);
            conditionStr = conditionStr.replace(regex, fieldValue);
        }

        // Vergleichsoperatoren (längere zuerst!)
        const operators = ['>=', '<=', '!=', '<>', '==', '>', '<', '='];

        for (let op of operators) {
            if (conditionStr.includes(op)) {
                const parts = conditionStr.split(op);
                if (parts.length === 2) {
                    const left = this.parseNumericValue(parts[0].trim(), values);
                    const right = this.parseNumericValue(parts[1].trim(), values);

                    switch (op) {
                        case '>=': return left >= right;
                        case '<=': return left <= right;
                        case '!=':
                        case '<>': return Math.abs(left - right) > 0.000001; // Floating-point Vergleich
                        case '==':
                        case '=': return Math.abs(left - right) <= 0.000001; // Floating-point Vergleich
                        case '>': return left > right;
                        case '<': return left < right;
                    }
                }
            }
        }

        // Boolescher Wert
        const value = this.parseNumericValue(conditionStr, values);
        return Boolean(value);
    }
    <? php
/**
 * Verbesserte Database-Methoden für ECP_Database
 * Diese Methoden ersetzen/erweitern die bestehenden in includes/class-ecp-database.php
 */

/**
 * Erweiterte Datenbank-Integrität prüfen
 */
public function check_integrity() {
    global $wpdb;
    $issues = array();

    try {
        // 1. Tabellen-Existenz prüfen
        $required_tables = array(
            $this -> table_calculators => 'Kalkulatoren-Tabelle',
            $this -> table_templates => 'Vorlagen-Tabelle'
        );

        foreach($required_tables as $table => $description) {
            if ($wpdb -> get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                $issues[] = "{$description} ({$table}) fehlt";
            }
        }

        // 2. Tabellen-Struktur prüfen
        $this -> check_table_structure($this -> table_calculators, $issues);
        $this -> check_table_structure($this -> table_templates, $issues);

        // 3. JSON-Felder validieren
        $this -> validate_json_fields($issues);

        // 4. Orphaned Records prüfen
        $this -> check_orphaned_records($issues);

        // 5. Datenbank-Konsistenz prüfen
        $this -> check_database_consistency($issues);

        // 6. Performance-Issues identifizieren
        $this -> check_performance_issues($issues);

    } catch (Exception $e) {
        $issues[] = "Fehler bei Integritätsprüfung: ".$e -> getMessage();
    }

    return empty($issues) ? true : $issues;
}

/**
 * Tabellen-Struktur prüfen
 */
private function check_table_structure($table_name, &$issues) {
    global $wpdb;

    $columns = $wpdb -> get_results("DESCRIBE {$table_name}");

    if (empty($columns)) {
        $issues[] = "Tabelle {$table_name} ist leer oder beschädigt";
        return;
    }

    $expected_columns = array();

    if ($table_name === $this -> table_calculators) {
        $expected_columns = array(
            'id', 'name', 'description', 'fields', 'formulas',
            'settings', 'status', 'created_by', 'created_at', 'updated_at'
        );
    } elseif($table_name === $this -> table_templates) {
        $expected_columns = array(
            'id', 'name', 'description', 'category', 'fields',
            'formulas', 'settings', 'is_public', 'sort_order',
            'created_by', 'created_at', 'updated_at'
        );
    }

    $actual_columns = wp_list_pluck($columns, 'Field');
    $missing_columns = array_diff($expected_columns, $actual_columns);

    if (!empty($missing_columns)) {
        $issues[] = "Fehlende Spalten in {$table_name}: ".implode(', ', $missing_columns);
    }
}

/**
 * JSON-Felder validieren
 */
private function validate_json_fields(&$issues) {
    global $wpdb;

    // Kalkulatoren prüfen
    $calculators = $wpdb -> get_results(
        "SELECT id, name, fields, formulas, settings FROM {$this->table_calculators} WHERE status = 'active'"
    );

    foreach($calculators as $calc) {
        // Fields validieren
        $fields = json_decode($calc -> fields, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = "Ungültige JSON-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) - Felder";
        } elseif(!is_array($fields)) {
            $issues[] = "Felder-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) sind kein Array";
        } else {
            // Feld-Struktur validieren
            foreach($fields as $index => $field) {
                if (!isset($field['id']) || !isset($field['label'])) {
                    $issues[] = "Unvollständiges Feld #{$index} in Kalkulator '{$calc->name}' (ID: {$calc->id})";
                }
            }
        }

        // Formulas validieren
        $formulas = json_decode($calc -> formulas, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $issues[] = "Ungültige JSON-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) - Formeln";
        } elseif(!is_array($formulas)) {
            $issues[] = "Formel-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) sind kein Array";
        } else {
            // Formel-Struktur validieren
            foreach($formulas as $index => $formula) {
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

                    foreach($dangerous_patterns as $pattern) {
                        if (preg_match($pattern, $formula['formula'])) {
                            $issues[] = "Potentiell gefährliche Formel in Kalkulator '{$calc->name}' (ID: {$calc->id}), Formel #{$index}";
                            break;
                        }
                    }
                }
            }
        }

        // Settings validieren
        if (!empty($calc -> settings)) {
            $settings = json_decode($calc -> settings, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = "Ungültige JSON-Daten in Kalkulator '{$calc->name}' (ID: {$calc->id}) - Einstellungen";
            }
        }
    }

    // Vorlagen prüfen
    $templates = $wpdb -> get_results(
        "SELECT id, name, fields, formulas, settings FROM {$this->table_templates}"
    );

    foreach($templates as $template) {
        if (json_decode($template -> fields) === null) {
            $issues[] = "Ungültige JSON-Daten in Vorlage '{$template->name}' (ID: {$template->id}) - Felder";
        }
        if (json_decode($template -> formulas) === null) {
            $issues[] = "Ungültige JSON-Daten in Vorlage '{$template->name}' (ID: {$template->id}) - Formeln";
        }
        if (!empty($template -> settings) && json_decode($template -> settings) === null) {
            $issues[] = "Ungültige JSON-Daten in Vorlage '{$template->name}' (ID: {$template->id}) - Einstellungen";
        }
    }
}

/**
 * Verwaiste Datensätze prüfen
 */
private function check_orphaned_records(&$issues) {
    global $wpdb;

    // Kalkulatoren mit ungültigen created_by Werten
    $orphaned_calculators = $wpdb -> get_var(
        "SELECT COUNT(*) FROM {$this->table_calculators} c 
         LEFT JOIN { $wpdb-> users} u ON c.created_by = u.ID 
         WHERE c.created_by > 0 AND u.ID IS NULL AND c.status = 'active'"
    );

if ($orphaned_calculators > 0) {
    $issues[] = "{$orphaned_calculators} Kalkulatoren haben ungültige Ersteller-IDs";
}

// Vorlagen mit ungültigen created_by Werten
$orphaned_templates = $wpdb -> get_var(
    "SELECT COUNT(*) FROM {$this->table_templates} t 
         LEFT JOIN { $wpdb-> users} u ON t.created_by = u.ID 
         WHERE t.created_by > 0 AND u.ID IS NULL"
);

if ($orphaned_templates > 0) {
    $issues[] = "{$orphaned_templates} Vorlagen haben ungültige Ersteller-IDs";
}
}

/**
 * Datenbank-Konsistenz prüfen
 */
private function check_database_consistency(&$issues) {
    global $wpdb;

    // Duplikate Kalkulator-Namen prüfen
    $duplicate_names = $wpdb -> get_results(
        "SELECT name, COUNT(*) as count 
         FROM { $this-> table_calculators} 
         WHERE status = 'active' 
         GROUP BY name 
         HAVING count > 1"
    );

foreach($duplicate_names as $duplicate) {
    $issues[] = "Doppelter Kalkulator-Name: '{$duplicate->name}' ({$duplicate->count}x)";
}

// Sehr alte 'deleted' Einträge
$old_deleted = $wpdb -> get_var(
    "SELECT COUNT(*) FROM {$this->table_calculators} 
         WHERE status = 'deleted' AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
);

if ($old_deleted > 0) {
    $issues[] = "{$old_deleted} gelöschte Kalkulatoren älter als 6 Monate (können bereinigt werden)";
}

// Sehr grosse JSON-Felder (potentielle Performance-Probleme)
$large_data = $wpdb -> get_results(
    "SELECT id, name, CHAR_LENGTH(fields) as fields_size, CHAR_LENGTH(formulas) as formulas_size 
         FROM { $this-> table_calculators}
    WHERE CHAR_LENGTH(fields) > 10000 OR CHAR_LENGTH(formulas) > 10000"
);

foreach($large_data as $large) {
    $issues[] = "Kalkulator '{$large->name}' (ID: {$large->id}) hat sehr grosse Datenmengen (Felder: {$large->fields_size}, Formeln: {$large->formulas_size})";
}
}

/**
 * Performance-Issues identifizieren
 */
private function check_performance_issues(&$issues) {
    global $wpdb;

    // Tabellengrösse prüfen
    $table_info = $wpdb -> get_results(
        "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH 
         FROM information_schema.TABLES 
         WHERE TABLE_SCHEMA = DATABASE() 
         AND TABLE_NAME IN('{$this->table_calculators}', '{$this->table_templates}')"
    );

    foreach($table_info as $table) {
        $size_mb = round(($table -> DATA_LENGTH + $table -> INDEX_LENGTH) / 1024 / 1024, 2);

        if ($size_mb > 50) { // Warnung bei über 50MB
            $issues[] = "Tabelle {$table->TABLE_NAME} ist sehr gross ({$size_mb}MB, {$table->TABLE_ROWS} Zeilen)";
        }
    }

    // Index-Nutzung prüfen (vereinfacht)
    $missing_indexes = array();

    // Prüfen ob wichtige Indizes existieren
    $indexes = $wpdb -> get_results("SHOW INDEX FROM {$this->table_calculators}");
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
public function save_calculator($data) {
    global $wpdb;

    // Erweiterte Validierung
    $validation_errors = $this -> validate_calculator_data($data);
    if (!empty($validation_errors)) {
        error_log('ECP Validation Errors: '.implode(', ', $validation_errors));
        return false;
    }

    // Daten vorbereiten und sanitisieren
    $calculator_data = array(
        'name' => sanitize_text_field($data['name']),
        'description' => sanitize_textarea_field($data['description'] ?? ''),
        'fields' => $this -> sanitize_json_field($data['fields']),
        'formulas' => $this -> sanitize_json_field($data['formulas']),
        'settings' => $this -> sanitize_json_field($data['settings'] ?? array()),
        'status' => sanitize_text_field($data['status'] ?? 'active'),
        'created_by' => get_current_user_id()
    );

    // Datentypen für wpdb
    $data_types = array('%s', '%s', '%s', '%s', '%s', '%s', '%d');

    if (isset($data['id']) && $data['id'] > 0) {
        // Update mit zusätzlicher Berechtigung-Prüfung
        $existing = $this -> get_calculator(intval($data['id']));
        if (!$existing) {
            return false;
        }

        // Prüfen ob Benutzer berechtigt ist zu bearbeiten
        if (!current_user_can('manage_options') && $existing -> created_by != get_current_user_id()) {
            return false;
        }

        $result = $wpdb -> update(
            $this -> table_calculators,
            $calculator_data,
            array('id' => intval($data['id'])),
            $data_types,
            array('%d')
        );

        if ($result === false) {
            error_log('ECP Database Update Error: '.$wpdb -> last_error);
            return false;
        }

        return intval($data['id']);
    } else {
        // Insert
        $result = $wpdb -> insert(
            $this -> table_calculators,
            $calculator_data,
            $data_types
        );

        if ($result === false) {
            error_log('ECP Database Insert Error: '.$wpdb -> last_error);
            return false;
        }

        return $wpdb -> insert_id;
    }
}

/**
 * Kalkulator-Daten validieren
 */
private function validate_calculator_data($data) {
    $errors = array();

    // Name validieren
    if (empty($data['name'])) {
        $errors[] = 'Name ist erforderlich';
    } elseif(strlen($data['name']) > 255) {
        $errors[] = 'Name ist zu lang (max. 255 Zeichen)';
    }

    // Felder validieren
    if (!isset($data['fields']) || !is_array($data['fields'])) {
        $errors[] = 'Felder müssen als Array angegeben werden';
    } else {
        foreach($data['fields'] as $index => $field) {
            if (!isset($field['id']) || empty($field['id'])) {
                $errors[] = "Feld #{$index}: ID fehlt";
            } elseif(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $field['id'])) {
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
        foreach($data['formulas'] as $index => $formula) {
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

                foreach($dangerous_patterns as $pattern) {
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
private function sanitize_json_field($data) {
    if (is_string($data)) {
        // Bereits JSON-String - validieren
        $decoded = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            return wp_json_encode(array());
        }
    } elseif(is_array($data)) {
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
public function cleanup_database() {
    global $wpdb;

    $cleanup_results = array();

    try {
        // Sehr alte gelöschte Einträge endgültig löschen
        $deleted_count = $wpdb -> query(
            "DELETE FROM {$this->table_calculators} 
             WHERE status = 'deleted' AND created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
        );
        $cleanup_results['deleted_calculators'] = $deleted_count;

        // Verwaiste Metadaten löschen (falls vorhanden)
        $wpdb -> query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ecp_%' AND option_value = ''");

        $cleanup_results['success'] = true;
        $cleanup_results['message'] = "Bereinigung abgeschlossen: {$deleted_count} veraltete Einträge entfernt";

    } catch (Exception $e) {
        $cleanup_results['success'] = false;
        $cleanup_results['message'] = "Fehler bei Bereinigung: ".$e -> getMessage();
    }

    return $cleanup_results;
    }
}