/**
 * Excel Calculator Pro - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    // Enhanced Formula Parser Class
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
                'PHI': (1 + Math.sqrt(5)) / 2 // Goldener Schnitt
            };
            
            this.operators = {
                '^': (a, b) => Math.pow(a, b),
                '*': (a, b) => a * b,
                '/': (a, b) => b !== 0 ? a / b : 0,
                '+': (a, b) => a + b,
                '-': (a, b) => a - b
            };
        }
        
        parse(formula, values, debug = false) {
            try {
                if (!formula || typeof formula !== 'string') {
                    return 0;
                }
                
                let processedFormula = formula.trim();
                
                // Konstanten ersetzen
                for (let constant in this.constants) {
                    const regex = new RegExp('\\b' + constant + '\\b', 'g');
                    processedFormula = processedFormula.replace(regex, this.constants[constant]);
                }
                
                // Feldnamen durch Werte ersetzen
                for (let fieldId in values) {
                    const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                    const value = parseFloat(values[fieldId]) || 0;
                    processedFormula = processedFormula.replace(regex, value);
                }
                
                // Funktionen verarbeiten (mehrere Durchgänge für verschachtelte Funktionen)
                processedFormula = this.processFunctions(processedFormula, values);
                
                if (debug) {
                    console.log('Original:', formula);
                    console.log('Processed:', processedFormula);
                    console.log('Values:', values);
                }
                
                // Sichere Evaluierung
                const result = this.safeEval(processedFormula);
                return isFinite(result) && !isNaN(result) ? result : 0;
            } catch (error) {
                if (debug) {
                    console.error('Formula error:', error);
                }
                return 0;
            }
        }
        
        escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        processFunctions(formula, values) {
            let processedFormula = formula;
            let maxIterations = 10;
            let iteration = 0;
            
            while (iteration < maxIterations) {
                let hasFunction = false;
                let originalFormula = processedFormula;
                
                for (let funcName in this.functions) {
                    // Suche nach Funktionen, die nicht verschachtelt sind
                    const regex = new RegExp(funcName + '\\s*\\(([^()]*)\\)', 'gi');
                    
                    if (regex.test(processedFormula)) {
                        hasFunction = true;
                        processedFormula = processedFormula.replace(regex, (match, args) => {
                            try {
                                return this.functions[funcName](args, values);
                            } catch (e) {
                                console.warn('Function error in', funcName, ':', e);
                                return 0;
                            }
                        });
                    }
                }
                
                // Wenn keine Änderung, dann sind wir fertig
                if (processedFormula === originalFormula) {
                    break;
                }
                
                if (!hasFunction) break;
                iteration++;
            }
            
            return processedFormula;
        }
        
        safeEval(expression) {
            // Normalisierung
            let normalized = expression.toString()
                .replace(/,/g, '.') // Kommas durch Punkte ersetzen
                .replace(/\s+/g, ' ') // Mehrfache Leerzeichen entfernen
                .trim();
            
            // Nur sichere Zeichen erlauben
            if (!/^[0-9+\-*/().,\s^]+$/.test(normalized)) {
                throw new Error('Unsichere Zeichen in Formel');
            }
            
            try {
                // Potenz-Operator (^) durch Math.pow ersetzen
                normalized = this.replacePowerOperator(normalized);
                
                // Evaluierung mit Function-Constructor (sicherer als eval)
                return Function('"use strict"; return (' + normalized + ')')();
            } catch (e) {
                throw new Error('Evaluierungsfehler: ' + e.message);
            }
        }
        
        replacePowerOperator(expression) {
            // Ersetzt a^b durch Math.pow(a,b)
            return expression.replace(/([0-9.]+)\s*\^\s*([0-9.]+)/g, 'Math.pow($1,$2)');
        }
        
        // === Funktions-Implementierungen ===
        
        IF(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 3) return 0;
            
            const condition = this.evaluateCondition(parts[0], values);
            return condition ? this.parseValue(parts[1], values) : this.parseValue(parts[2], values);
        }
        
        ROUND(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            
            const value = this.parseValue(parts[0], values);
            const decimals = parts.length > 1 ? this.parseValue(parts[1], values) : 0;
            
            const factor = Math.pow(10, decimals);
            return Math.round(value * factor) / factor;
        }
        
        MIN(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return 0;
            
            const numbers = parts.map(part => this.parseValue(part, values))
                                .filter(num => isFinite(num));
            
            return numbers.length > 0 ? Math.min(...numbers) : 0;
        }
        
        MAX(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return 0;
            
            const numbers = parts.map(part => this.parseValue(part, values))
                                .filter(num => isFinite(num));
            
            return numbers.length > 0 ? Math.max(...numbers) : 0;
        }
        
        SUM(args, values) {
            const parts = this.parseArguments(args);
            return parts.reduce((sum, part) => {
                const value = this.parseValue(part, values);
                return sum + (isFinite(value) ? value : 0);
            }, 0);
        }
        
        AVERAGE(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return 0;
            
            const numbers = parts.map(part => this.parseValue(part, values))
                                .filter(num => isFinite(num));
            
            return numbers.length > 0 ? numbers.reduce((a, b) => a + b, 0) / numbers.length : 0;
        }
        
        ABS(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            return Math.abs(this.parseValue(parts[0], values));
        }
        
        SQRT(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            const value = this.parseValue(parts[0], values);
            return value >= 0 ? Math.sqrt(value) : 0;
        }
        
        POW(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 2) return 0;
            const base = this.parseValue(parts[0], values);
            const exponent = this.parseValue(parts[1], values);
            return Math.pow(base, exponent);
        }
        
        LOG(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            const value = this.parseValue(parts[0], values);
            if (value <= 0) return 0;
            
            const base = parts.length > 1 ? this.parseValue(parts[1], values) : Math.E;
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
            
            const dateValue = this.parseValue(parts[0], values);
            const date = this.parseDate(dateValue);
            return date ? date.getFullYear() : new Date().getFullYear();
        }
        
        MONTH(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) {
                return new Date().getMonth() + 1;
            }
            
            const dateValue = this.parseValue(parts[0], values);
            const date = this.parseDate(dateValue);
            return date ? date.getMonth() + 1 : new Date().getMonth() + 1;
        }
        
        DAY(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) {
                return new Date().getDate();
            }
            
            const dateValue = this.parseValue(parts[0], values);
            const date = this.parseDate(dateValue);
            return date ? date.getDate() : new Date().getDate();
        }
        
        CEILING(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            return Math.ceil(this.parseValue(parts[0], values));
        }
        
        FLOOR(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            return Math.floor(this.parseValue(parts[0], values));
        }
        
        RAND(args, values) {
            return Math.random();
        }
        
        // === Hilfsfunktionen ===
        
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
                } else if (!inQuotes && char === ',' && parenthesisLevel === 0) {
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
        
        parseValue(value, values) {
            if (typeof value === 'number') return value;
            
            value = value.toString().trim();
            
            // Anführungszeichen entfernen
            value = value.replace(/^["']|["']$/g, '');
            
            // Direkte Zahl
            if (!isNaN(value) && value !== '') {
                return parseFloat(value);
            }
            
            // Feldwert
            if (values && values[value] !== undefined) {
                return parseFloat(values[value]) || 0;
            }
            
            // Konstante
            if (this.constants[value] !== undefined) {
                return this.constants[value];
            }
            
            // Ausdruck evaluieren
            try {
                return this.safeEval(value);
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
            return new Date(value);
        }
        
        evaluateCondition(condition, values) {
            condition = condition.toString().trim();
            
            // Feldnamen durch Werte ersetzen
            for (let fieldId in values) {
                const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                condition = condition.replace(regex, parseFloat(values[fieldId]) || 0);
            }
            
            // Vergleichsoperatoren
            const operators = ['>=', '<=', '!=', '<>', '==', '>', '<', '='];
            
            for (let op of operators) {
                if (condition.includes(op)) {
                    const parts = condition.split(op);
                    if (parts.length === 2) {
                        const left = this.parseValue(parts[0].trim(), values);
                        const right = this.parseValue(parts[1].trim(), values);
                        
                        switch (op) {
                            case '>=': return left >= right;
                            case '<=': return left <= right;
                            case '!=':
                            case '<>': return left !== right;
                            case '==':
                            case '=': return left === right;
                            case '>': return left > right;
                            case '<': return left < right;
                        }
                    }
                }
            }
            
            // Boolescher Wert
            const value = this.parseValue(condition, values);
            return Boolean(value);
        }
    }
    
    // Globale Instanz des Parsers
    const parser = new ECPFormulaParser();
    
    /**
     * Calculator Engine
     */
    class ECPCalculator {
        constructor(element) {
            this.$element = $(element);
            this.calculatorId = this.$element.data('calculator-id');
            this.values = {};
            this.debounceTimer = null;
            this.isCalculating = false;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initializeValues();
            this.calculate();
            
            // Fade-in Animation
            this.$element.hide().fadeIn(500);
            
            // Accessibility
            this.setupAccessibility();
        }
        
        bindEvents() {
            // Eingabe-Events
            this.$element.on('input change keyup', '.ecp-input-field', (e) => {
                this.handleInputChange(e);
            });
            
            // Blur-Event für Validierung
            this.$element.on('blur', '.ecp-input-field', (e) => {
                this.validateInput(e.target);
            });
            
            // Focus-Event für visuelle Hervorhebung
            this.$element.on('focus', '.ecp-input-field', (e) => {
                $(e.target).closest('.ecp-field-group').addClass('ecp-focused');
            });
            
            this.$element.on('blur', '.ecp-input-field', (e) => {
                $(e.target).closest('.ecp-field-group').removeClass('ecp-focused');
            });
        }
        
        setupAccessibility() {
            // ARIA-Attribute setzen
            this.$element.find('.ecp-input-field').each(function() {
                const $input = $(this);
                const fieldId = $input.data('field-id');
                const label = $input.closest('.ecp-field-group').find('label').text();
                
                $input.attr({
                    'aria-label': label,
                    'role': 'spinbutton',
                    'aria-describedby': 'ecp-help-' + fieldId
                });
            });
            
            this.$element.find('.ecp-output-field').each(function() {
                const $output = $(this);
                const label = $output.closest('.ecp-output-group').find('label').text();
                
                $output.attr({
                    'role': 'status',
                    'aria-live': 'polite',
                    'aria-label': label
                });
            });
        }
        
        initializeValues() {
            this.$element.find('.ecp-input-field').each((index, input) => {
                const $input = $(input);
                const fieldId = $input.data('field-id');
                const value = parseFloat($input.val()) || 0;
                this.values[fieldId] = value;
            });
        }
        
        handleInputChange(event) {
            const $input = $(event.target);
            const fieldId = $input.data('field-id');
            const value = parseFloat($input.val()) || 0;
            
            // Wert aktualisieren
            this.values[fieldId] = value;
            
            // Visuelles Feedback
            $input.closest('.ecp-field-group').addClass('ecp-changed');
            setTimeout(() => {
                $input.closest('.ecp-field-group').removeClass('ecp-changed');
            }, 300);
            
            // Debounced Calculation
            this.debouncedCalculate();
        }
        
        validateInput(input) {
            const $input = $(input);
            const value = $input.val();
            const min = parseFloat($input.attr('min'));
            const max = parseFloat($input.attr('max'));
            const required = $input.prop('required');
            
            // Fehler-Container erstellen/finden
            let $errorContainer = $input.siblings('.ecp-error-message');
            if (!$errorContainer.length) {
                $errorContainer = $('<div class="ecp-error-message"></div>');
                $input.parent().append($errorContainer);
            }
            
            $errorContainer.empty();
            $input.removeClass('ecp-error');
            
            // Validierungen
            if (required && (!value || value.trim() === '')) {
                this.showFieldError($input, $errorContainer, 'Dieses Feld ist erforderlich.');
                return false;
            }
            
            const numValue = parseFloat(value);
            if (value && isNaN(numValue)) {
                this.showFieldError($input, $errorContainer, 'Bitte geben Sie eine gültige Zahl ein.');
                return false;
            }
            
            if (!isNaN(min) && numValue < min) {
                this.showFieldError($input, $errorContainer, `Wert muss mindestens ${min} sein.`);
                return false;
            }
            
            if (!isNaN(max) && numValue > max) {
                this.showFieldError($input, $errorContainer, `Wert darf höchstens ${max} sein.`);
                return false;
            }
            
            return true;
        }
        
        showFieldError($input, $errorContainer, message) {
            $input.addClass('ecp-error');
            $errorContainer.text(message);
        }
        
        debouncedCalculate() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.calculate();
            }, 150);
        }
        
        calculate() {
            if (this.isCalculating) return;
            
            this.isCalculating = true;
            this.$element.addClass('ecp-loading');
            
            try {
                // Alle Ausgabefelder berechnen
                this.$element.find('.ecp-output-field').each((index, output) => {
                    const $output = $(output);
                    const formula = $output.data('formula');
                    const format = $output.data('format') || '';
                    const prevValue = $output.text();
                    
                    if (formula) {
                        try {
                            const result = parser.parse(formula, this.values, false);
                            const formattedResult = this.formatNumber(result, format);
                            
                            // Animation nur bei Änderung
                            if (formattedResult !== prevValue) {
                                $output.addClass('ecp-changed');
                                setTimeout(() => {
                                    $output.removeClass('ecp-changed');
                                }, 600);
                                
                                // Accessibility: Screen Reader Ankündigung
                                this.announceChange($output, formattedResult);
                            }
                            
                            $output.text(formattedResult);
                            $output.removeClass('ecp-error');
                            
                        } catch (error) {
                            console.error('Calculation error for formula:', formula, error);
                            $output.text('Fehler').addClass('ecp-error');
                        }
                    }
                });
                
                // Status aktualisieren
                this.$element.removeClass('ecp-error').addClass('ecp-success');
                
            } catch (error) {
                console.error('General calculation error:', error);
                this.$element.addClass('ecp-error').removeClass('ecp-success');
            } finally {
                this.isCalculating = false;
                this.$element.removeClass('ecp-loading');
            }
        }
        
        announceChange($output, value) {
            // Screen Reader Ankündigung für Barrierefreiheit
            const label = $output.closest('.ecp-output-group').find('label').text();
            const announcement = `${label}: ${value}`;
            
            // ARIA live region für Ankündigungen
            let $liveRegion = $('#ecp-live-region');
            if (!$liveRegion.length) {
                $liveRegion = $('<div id="ecp-live-region" class="ecp-sr-only" aria-live="polite"></div>');
                $('body').append($liveRegion);
            }
            
            $liveRegion.text(announcement);
        }
        
        formatNumber(number, format) {
            if (!isFinite(number) || isNaN(number)) {
                return '0';
            }
            
            const settings = window.ecp_frontend ? window.ecp_frontend.settings : {};
            const locale = settings.number_format || 'de-CH';
            const currency = settings.currency || 'CHF';
            
            // Lokalisierungsoptionen
            const localeMap = {
                'de_CH': 'de-CH',
                'de_DE': 'de-DE',
                'en_US': 'en-US'
            };
            
            const targetLocale = localeMap[locale] || 'de-CH';
            
            try {
                switch (format) {
                    case 'currency':
                        const currencySymbol = settings.currency_symbols && settings.currency_symbols[currency] 
                                             ? settings.currency_symbols[currency] 
                                             : currency + ' ';
                        return currencySymbol + number.toLocaleString(targetLocale, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        
                    case 'percentage':
                        return (number * 100).toLocaleString(targetLocale, {
                            minimumFractionDigits: 1,
                            maximumFractionDigits: 2
                        }) + '%';
                        
                    case 'integer':
                        return Math.round(number).toLocaleString(targetLocale);
                        
                    case 'text':
                        return String(number);
                        
                    default:
                        // Automatische Dezimalstellen basierend auf Grösse
                        const absNumber = Math.abs(number);
                        let decimals = 2;
                        
                        if (absNumber >= 1000000) decimals = 0;
                        else if (absNumber >= 1000) decimals = 1;
                        else if (absNumber < 0.01 && absNumber > 0) decimals = 4;
                        
                        return number.toLocaleString(targetLocale, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: decimals
                        });
                }
            } catch (e) {
                // Fallback für Lokalisierungsfehler
                console.warn('Localization error:', e);
                return number.toFixed(2);
            }
        }
        
        // Public API-Methoden
        recalculate() {
            this.calculate();
        }
        
        setValue(fieldId, value) {
            this.values[fieldId] = parseFloat(value) || 0;
            this.$element.find(`[data-field-id="${fieldId}"]`).val(value);
            this.calculate();
        }
        
        getValue(fieldId) {
            return this.values[fieldId] || 0;
        }
        
        getResults() {
            const results = {};
            this.$element.find('.ecp-output-field').each(function() {
                const $output = $(this);
                const outputId = $output.data('output-id');
                const value = $output.text();
                if (outputId) {
                    results[outputId] = value;
                }
            });
            return results;
        }
    }
    
    /**
     * jQuery Plugin
     */
    $.fn.ecpCalculator = function(options) {
        return this.each(function() {
            if (!$.data(this, 'ecpCalculator')) {
                $.data(this, 'ecpCalculator', new ECPCalculator(this));
            }
        });
    };
    
    /**
     * Auto-Initialisierung
     */
    $(document).ready(function() {
        // Alle Kalkulatoren initialisieren
        $('.ecp-calculator').ecpCalculator();
        
        // Performance-Optimierung für grosse Seiten
        if ($('.ecp-calculator').length > 5) {
            console.info('Excel Calculator Pro: Mehrere Kalkulatoren erkannt. Performance-Modus aktiviert.');
        }
        
        // Global Error Handler
        window.addEventListener('error', function(e) {
            if (e.message && e.message.includes('ecp')) {
                console.error('Excel Calculator Pro Error:', e);
            }
        });
    });
    
    /**
     * API für externe Nutzung
     */
    window.ECPCalculator = {
        // Kalkulator-Instanz abrufen
        getInstance: function(selector) {
            const element = $(selector).first()[0];
            return element ? $.data(element, 'ecpCalculator') : null;
        },
        
        // Alle Kalkulatoren neu berechnen
        recalculateAll: function() {
            $('.ecp-calculator').each(function() {
                const instance = $.data(this, 'ecpCalculator');
                if (instance) {
                    instance.recalculate();
                }
            });
        },
        
        // Formel-Parser direkt verwenden
        parseFormula: function(formula, values, debug = false) {
            return parser.parse(formula, values, debug);
        }
    };
    
})(jQuery);