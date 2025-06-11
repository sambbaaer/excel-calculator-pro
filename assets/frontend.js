/**
 * Excel Calculator Pro - Verbessertes Frontend JavaScript
 */

(function ($) {
    'use strict';

    // Enhanced Formula Parser Class mit erweiterten Funktionen
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

                for (let constant in this.constants) {
                    const regex = new RegExp('\\b' + constant + '\\b', 'g');
                    processedFormula = processedFormula.replace(regex, this.constants[constant]);
                }

                for (let fieldId in values) {
                    const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                    const value = parseFloat(values[fieldId]) || 0;
                    processedFormula = processedFormula.replace(regex, value);
                }

                processedFormula = this.processFunctions(processedFormula, values);

                if (debug) {
                    console.log('Original:', formula);
                    console.log('Processed:', processedFormula);
                    console.log('Values:', values);
                }

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
            let lastFormula;

            while (iteration < maxIterations) {
                lastFormula = processedFormula;
                let hasFunction = false;

                for (let funcName in this.functions) {
                    const regex = new RegExp(funcName + '\\s*\\(([^()]*?)\\)', 'gi');
                    if (regex.test(processedFormula)) {
                        hasFunction = true;
                        processedFormula = processedFormula.replace(regex, (match, args) => {
                            try {
                                return this.functions[funcName](args, values);
                            } catch (e) {
                                console.warn('Function error in', funcName, ':', e, 'Args:', args);
                                return 0;
                            }
                        });
                    }
                }
                if (processedFormula === lastFormula || !hasFunction) {
                    break;
                }
                iteration++;
            }
            if (iteration === maxIterations) {
                console.warn("ECP Formula Parser: Max iterations reached for formula processing. Check for circular dependencies or overly complex nesting:", formula);
            }
            return processedFormula;
        }

        safeEval(expression) {
            let normalized = expression.toString()
                .replace(/,/g, '.')
                .replace(/\s+/g, '')
                .trim();

            try {
                // Potenz-Operator (^) durch Math.pow ersetzen
                normalized = normalized.replace(/(\d+(?:\.\d+)?)\^(\d+(?:\.\d+)?)/g, 'Math.pow($1,$2)');

                return Function('"use strict"; return (' + normalized + ')')();
            } catch (e) {
                throw new Error('Evaluierungsfehler: ' + e.message + " Ausdruck: " + expression);
            }
        }

        IF(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 2) return 0;

            const conditionResult = this.evaluateCondition(parts[0], values);

            if (conditionResult) {
                return this.parseValue(parts[1], values);
            } else if (parts.length > 2) {
                return this.parseValue(parts[2], values);
            }
            return 0;
        }

        ROUND(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            const value = this.parseValue(parts[0], values);
            const decimals = parts.length > 1 ? Math.max(0, Math.floor(this.parseValue(parts[1], values))) : 0;
            const factor = Math.pow(10, decimals);
            return Math.round(value * factor) / factor;
        }

        /**
         * Verbesserte OBERGRENZE (CEILING) Funktion
         * Rundet eine Zahl auf das nächste Vielfache einer Signifikanz auf
         */
        CEILING(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return NaN;

            const number = this.parseValue(parts[0], values);
            const significance = parts.length > 1 ? this.parseValue(parts[1], values) : 1;

            if (significance === 0) return 0;

            // Excel-kompatible Logik
            if (number > 0 && significance < 0) return NaN;
            if (number < 0 && significance > 0) return NaN;

            if (significance < 0) {
                return -Math.floor(-number / -significance) * -significance;
            } else {
                return Math.ceil(number / significance) * significance;
            }
        }

        /**
         * Verbesserte UNTERGRENZE (FLOOR) Funktion
         * Rundet eine Zahl auf das nächste Vielfache einer Signifikanz ab
         */
        FLOOR(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return NaN;

            const number = this.parseValue(parts[0], values);
            const significance = parts.length > 1 ? this.parseValue(parts[1], values) : 1;

            if (significance === 0) return 0;

            // Excel-kompatible Logik
            if (number > 0 && significance < 0) return NaN;
            if (number < 0 && significance > 0) return NaN;

            if (significance < 0) {
                return -Math.ceil(-number / -significance) * -significance;
            } else {
                return Math.floor(number / significance) * significance;
            }
        }

        MIN(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return NaN;
            const numbers = parts.map(part => this.parseValue(part, values)).filter(num => isFinite(num));
            return numbers.length > 0 ? Math.min(...numbers) : NaN;
        }

        MAX(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return NaN;
            const numbers = parts.map(part => this.parseValue(part, values)).filter(num => isFinite(num));
            return numbers.length > 0 ? Math.max(...numbers) : NaN;
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
            if (parts.length === 0) return NaN;
            const numbers = parts.map(part => this.parseValue(part, values)).filter(num => isFinite(num));
            return numbers.length > 0 ? numbers.reduce((a, b) => a + b, 0) / numbers.length : NaN;
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
            return value >= 0 ? Math.sqrt(value) : NaN;
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
            if (parts.length < 1) return NaN;
            const value = this.parseValue(parts[0], values);
            if (value <= 0) return NaN;

            const base = parts.length > 1 ? this.parseValue(parts[1], values) : Math.E;
            if (base <= 0 || base === 1) return NaN;

            return Math.log(value) / Math.log(base);
        }

        TODAY(args, values) {
            const today = new Date();
            const excelEpoch = new Date(1899, 11, 30);
            return Math.floor((today - excelEpoch) / (24 * 60 * 60 * 1000));
        }

        YEAR(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return new Date().getFullYear();
            const dateValue = this.parseValue(parts[0], values);
            const date = this.excelSerialToDate(dateValue);
            return date ? date.getFullYear() : NaN;
        }

        MONTH(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return new Date().getMonth() + 1;
            const dateValue = this.parseValue(parts[0], values);
            const date = this.excelSerialToDate(dateValue);
            return date ? date.getMonth() + 1 : NaN;
        }

        DAY(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return new Date().getDate();
            const dateValue = this.parseValue(parts[0], values);
            const date = this.excelSerialToDate(dateValue);
            return date ? date.getDate() : NaN;
        }

        excelSerialToDate(serial) {
            if (!isFinite(serial) || serial <= 0) return null;
            const utc_days = Math.floor(serial - 25569);
            const utc_value = utc_days * 86400;
            const date_info = new Date(utc_value * 1000);
            return new Date(date_info.getFullYear(), date_info.getMonth(), date_info.getDate());
        }

        RAND(args, values) {
            return Math.random();
        }

        parseArguments(argsString) {
            const args = [];
            let currentArg = '';
            let depth = 0;
            let inQuotes = false;
            let quoteChar = '';

            for (let i = 0; i < argsString.length; i++) {
                const char = argsString[i];

                if ((char === '"' || char === "'") && (i === 0 || argsString[i - 1] !== '\\')) {
                    if (inQuotes && char === quoteChar) {
                        inQuotes = false;
                    } else if (!inQuotes) {
                        inQuotes = true;
                        quoteChar = char;
                    }
                }

                if (!inQuotes && char === '(') {
                    depth++;
                } else if (!inQuotes && char === ')') {
                    depth--;
                }

                if (char === ';' && depth === 0 && !inQuotes) {
                    args.push(currentArg.trim());
                    currentArg = '';
                } else {
                    currentArg += char;
                }
            }
            args.push(currentArg.trim());
            return args.filter(arg => arg.length > 0);
        }

        parseValue(value, values) {
            if (typeof value === 'number') return value;
            value = String(value).trim();

            if (value.startsWith('"') && value.endsWith('"')) {
                return value.substring(1, value.length - 1);
            }
            if (value.startsWith("'") && value.endsWith("'")) {
                return value.substring(1, value.length - 1);
            }

            if (value.toLowerCase() === 'true') return true;
            if (value.toLowerCase() === 'false') return false;

            if (!isNaN(value) && value !== '') {
                return parseFloat(value);
            }

            if (values && values.hasOwnProperty(value)) {
                return parseFloat(values[value]) || 0;
            }

            if (this.constants.hasOwnProperty(value.toUpperCase())) {
                return this.constants[value.toUpperCase()];
            }

            try {
                if (/[A-Z]+\s*\(.*\)/i.test(value)) {
                    return this.parse(value, values);
                }
                return this.safeEval(value);
            } catch (e) {
                return value;
            }
        }

        evaluateCondition(conditionStr, values) {
            try {
                let populatedCondition = conditionStr;
                for (const fieldId in values) {
                    if (values.hasOwnProperty(fieldId)) {
                        const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                        populatedCondition = populatedCondition.replace(regex, values[fieldId] || 0);
                    }
                }

                const operators = {
                    '>=': (a, b) => a >= b,
                    '<=': (a, b) => a <= b,
                    '<>': (a, b) => a != b,
                    '!=': (a, b) => a != b,
                    '=': (a, b) => a == b,
                    '>': (a, b) => a > b,
                    '<': (a, b) => a < b
                };

                for (const op in operators) {
                    const parts = populatedCondition.split(op);
                    if (parts.length === 2) {
                        const left = this.parseValue(parts[0], values);
                        const right = this.parseValue(parts[1], values);
                        if (isFinite(left) && isFinite(right)) {
                            return operators[op](Number(left), Number(right));
                        }
                    }
                }

                return !!this.parseValue(populatedCondition, values);

            } catch (e) {
                return false;
            }
        }
    }

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

            this.$element.hide().fadeIn(500);
            this.setupAccessibility();
        }

        bindEvents() {
            this.$element.on('input change keyup', '.ecp-input-field', (e) => {
                this.handleInputChange(e);
            });

            this.$element.on('blur', '.ecp-input-field', (e) => {
                this.validateInput(e.target);
                this.handleInputChange(e);
            });

            this.$element.on('focus', '.ecp-input-field', (e) => {
                $(e.target).closest('.ecp-field-group').addClass('ecp-focused');
            });

            this.$element.on('blur', '.ecp-input-field', (e) => {
                $(e.target).closest('.ecp-field-group').removeClass('ecp-focused');
            });

            // Verbesserter Event-Handler für Kopier-Icon
            this.$element.on('click keypress', '.ecp-copy-icon', (e) => {
                if (e.type === 'click' || (e.type === 'keypress' && (e.key === 'Enter' || e.key === ' '))) {
                    e.preventDefault();
                    this.handleCopyResult($(e.currentTarget));
                }
            });
        }

        // Verbesserte Kopierfunktion mit Einheiten-Unterstützung
        handleCopyResult($icon) {
            if ($icon.hasClass('ecp-copied-feedback')) return;

            const $wrapper = $icon.closest('.ecp-output-wrapper');
            const $outputField = $wrapper.find('.ecp-output-field');
            const $outputUnitSpan = $wrapper.find('.ecp-output-unit');

            let valueToCopy = $outputField.text().trim();
            const unitFromSpan = $outputUnitSpan.length ? $outputUnitSpan.text().trim() : '';

            if (unitFromSpan) {
                const valueLower = valueToCopy.toLowerCase();
                const unitLower = unitFromSpan.toLowerCase();

                // Füge die Einheit hinzu, wenn sie nicht bereits Teil des formatierten Wertes ist
                if (!valueLower.includes(unitLower) && !(valueToCopy.endsWith('%') && unitFromSpan === '%')) {
                    valueToCopy += ' ' + unitFromSpan;
                }
            }

            navigator.clipboard.writeText(valueToCopy).then(() => {
                const originalIconContent = $icon.html();
                $icon.html('✅').addClass('ecp-copied-feedback');
                setTimeout(() => {
                    $icon.html(originalIconContent).removeClass('ecp-copied-feedback');
                }, 1500);
            }).catch(err => {
                console.error('ECP: Fehler beim Kopieren: ', err);
                const originalIconContent = $icon.html();
                $icon.html('❌').addClass('ecp-copied-feedback ecp-copy-error');
                setTimeout(() => {
                    $icon.html(originalIconContent).removeClass('ecp-copied-feedback ecp-copy-error');
                }, 2000);
            });
        }

        setupAccessibility() {
            this.$element.find('.ecp-input-field').each(function () {
                const $input = $(this);
                const fieldId = $input.data('field-id');
                const $label = $input.closest('.ecp-field-group').find('label[for="ecp-field-' + fieldId + '"]');
                let labelText = $label.length ? $label.text().trim() : 'Eingabefeld ' + fieldId;

                const $helpText = $input.closest('.ecp-input-wrapper').find('.ecp-field-help-prefix');
                let helpId = '';
                if ($helpText.length) {
                    helpId = 'ecp-help-desc-' + fieldId;
                    $helpText.attr('id', helpId);
                }

                $input.attr({
                    'aria-label': labelText,
                    'role': $input.attr('type') === 'number' ? 'spinbutton' : null,
                    'aria-describedby': helpId ? helpId : null
                });
            });

            this.$element.find('.ecp-output-field').each(function () {
                const $output = $(this);
                const outputId = $output.data('output-id');
                const $label = $output.closest('.ecp-output-group').find('label');
                let labelText = $label.length ? $label.first().contents().filter(function () { return this.nodeType === 3; }).text().trim() : 'Ergebnis ' + outputId;

                $output.attr({
                    'role': 'status',
                    'aria-live': 'polite',
                    'aria-label': labelText
                });
            });

            this.$element.find('.ecp-copy-icon').each(function (index) {
                const $icon = $(this);
                const $outputGroup = $icon.closest('.ecp-output-group');
                const outputLabel = $outputGroup.find('label').first().contents().filter(function () { return this.nodeType === 3; }).text().trim();
                const iconId = 'ecp-copy-icon-' + ($outputGroup.data('output-id') || index);
                $icon.attr({
                    'id': iconId,
                    'aria-label': (ecp_frontend && ecp_frontend.strings && ecp_frontend.strings.copy_label_prefix || 'Kopiere ') + outputLabel,
                });
            });
        }

        initializeValues() {
            this.$element.find('.ecp-input-field').each((index, input) => {
                const $input = $(input);
                const fieldId = $input.data('field-id');
                let value = $input.val();
                if ($input.attr('type') === 'number') {
                    value = parseFloat(value);
                    if (isNaN(value)) value = 0;
                }
                this.values[fieldId] = value;
                $input.val(value);
            });
        }

        handleInputChange(event) {
            const $input = $(event.target);
            const fieldId = $input.data('field-id');
            let value = $input.val();

            if ($input.attr('type') === 'number') {
                if (value.trim() === '') {
                    // Leer ist ok
                } else if (isNaN(parseFloat(value))) {
                    // Ungültige Eingabe
                }
            }

            this.values[fieldId] = parseFloat(value) || 0;

            $input.closest('.ecp-field-group').addClass('ecp-field-changed-animation');
            setTimeout(() => {
                $input.closest('.ecp-field-group').removeClass('ecp-field-changed-animation');
            }, 300);

            this.debouncedCalculate();
        }

        validateInput(inputElement) {
            const $input = $(inputElement);
            let value = $input.val();
            const type = $input.attr('type');
            const min = parseFloat($input.attr('min'));
            const max = parseFloat($input.attr('max'));

            let $errorContainer = $input.siblings('.ecp-error-message');
            if (!$errorContainer.length) {
                $errorContainer = $('<div class="ecp-error-message" aria-live="assertive"></div>');
                $input.parent().append($errorContainer);
            }
            $errorContainer.empty().hide();
            $input.removeClass('ecp-input-error');

            if (type === 'number') {
                if (value.trim() === '') {
                    return true;
                }
                const numValue = parseFloat(value);
                if (isNaN(numValue)) {
                    this.showFieldError($input, $errorContainer, ecp_frontend.strings.error_invalid_number || 'Ungültige Zahl.');
                    return false;
                }
                if (!isNaN(min) && numValue < min) {
                    this.showFieldError($input, $errorContainer, (ecp_frontend.strings.error_min_value || 'Wert muss mind. %min% sein.').replace('%min%', min));
                    return false;
                }
                if (!isNaN(max) && numValue > max) {
                    this.showFieldError($input, $errorContainer, (ecp_frontend.strings.error_max_value || 'Wert darf max. %max% sein.').replace('%max%', max));
                    return false;
                }
            }
            return true;
        }

        showFieldError($input, $errorContainer, message) {
            $input.addClass('ecp-input-error');
            $errorContainer.text(message).show();
        }

        debouncedCalculate() {
            clearTimeout(this.debounceTimer);
            this.$element.addClass('ecp-calculating');
            this.debounceTimer = setTimeout(() => {
                this.calculate();
                this.$element.removeClass('ecp-calculating');
            }, 250);
        }

        calculate() {
            if (this.isCalculating) return;
            this.isCalculating = true;

            this.initializeValues();

            try {
                this.$element.find('.ecp-output-field').each((index, output) => {
                    const $output = $(output);
                    const formula = $output.data('formula');
                    const format = $output.data('format') || '';
                    const prevValue = $output.text();

                    if (formula) {
                        try {
                            const result = parser.parse(formula, this.values, false);
                            const formattedResult = this.formatNumber(result, format);

                            if (formattedResult !== prevValue) {
                                $output.addClass('ecp-value-changed-animation');
                                setTimeout(() => {
                                    $output.removeClass('ecp-value-changed-animation');
                                }, 600);
                                this.announceChange($output, formattedResult);
                            }
                            $output.text(formattedResult);
                            $output.removeClass('ecp-output-error');
                        } catch (error) {
                            $output.text('Fehler').addClass('ecp-output-error');
                        }
                    }
                });
                this.$element.removeClass('ecp-calculator-error-state').addClass('ecp-calculator-success-state');
            } catch (error) {
                this.$element.addClass('ecp-calculator-error-state').removeClass('ecp-calculator-success-state');
            } finally {
                this.isCalculating = false;
            }
        }

        announceChange($output, value) {
            const $label = $output.closest('.ecp-output-group').find('label');
            let labelText = $label.length ? $label.first().contents().filter(function () { return this.nodeType === 3; }).text().trim() : '';
            const announcement = `${labelText}: ${value}`;

            let $liveRegion = $('#ecp-live-region');
            if (!$liveRegion.length) {
                $liveRegion = $('<div id="ecp-live-region" class="ecp-sr-only" aria-live="polite" aria-atomic="true"></div>');
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
            const currencySymbols = settings.currency_symbols || { CHF: 'CHF', EUR: '€', USD: '$' };

            const targetLocale = locale.replace('_', '-');

            try {
                switch (format) {
                    case 'currency':
                        return number.toLocaleString(targetLocale, {
                            style: 'currency',
                            currency: currency,
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    case 'percentage':
                        return number.toLocaleString(targetLocale, {
                            style: 'percent',
                            minimumFractionDigits: 1,
                            maximumFractionDigits: 2
                        });
                    case 'integer':
                        return Math.round(number).toLocaleString(targetLocale, { maximumFractionDigits: 0 });
                    case 'text':
                        return String(number);
                    default:
                        let defaultDecimals = 2;
                        if (Math.abs(number) >= 1000) defaultDecimals = 0;
                        else if (Math.abs(number) < 1 && Math.abs(number) > 0) defaultDecimals = Math.min(4, (number.toString().split('.')[1] || '').length);

                        return number.toLocaleString(targetLocale, {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: defaultDecimals
                        });
                }
            } catch (e) {
                let fixedNum = number.toFixed(2);
                if (format === 'integer') fixedNum = Math.round(number).toString();
                if (format === 'percentage') fixedNum = (number * 100).toFixed(1) + '%';
                return fixedNum;
            }
        }

        recalculate() {
            this.calculate();
        }

        setValue(fieldId, value) {
            const $input = this.$element.find(`.ecp-input-field[data-field-id="${fieldId}"]`);
            if ($input.length) {
                $input.val(value);
                this.handleInputChange({ target: $input[0] });
            }
        }

        getValue(fieldId) {
            return this.values[fieldId] || 0;
        }

        getResults() {
            const results = {};
            this.$element.find('.ecp-output-field').each(function () {
                const $output = $(this);
                const outputId = $output.data('output-id');
                const label = $output.closest('.ecp-output-group').find('label').text().trim();
                const key = outputId !== undefined ? outputId : label;
                if (key) {
                    results[key] = $output.text().trim();
                }
            });
            return results;
        }
    }

    $.fn.ecpCalculator = function (options) {
        return this.each(function () {
            if (!$.data(this, 'ecpCalculatorInstance')) {
                $.data(this, 'ecpCalculatorInstance', new ECPCalculator(this));
            }
        });
    };

    $(document).ready(function () {
        $('.ecp-calculator').ecpCalculator();

        if (typeof ecp_frontend === 'undefined') {
            window.ecp_frontend = {
                settings: {
                    number_format: 'de_CH',
                    currency: 'CHF',
                    currency_symbols: { CHF: 'CHF ', EUR: '€ ', USD: '$ ' }
                },
                strings: {
                    error_invalid_number: 'Ungültige Zahl.',
                    error_min_value: 'Wert muss mind. %min% sein.',
                    error_max_value: 'Wert darf max. %max% sein.',
                    copy_label_prefix: 'Kopiere '
                }
            };
        }
    });

    // API für externe Nutzung
    window.ECPCalculatorAPI = {
        getInstance: function (selectorOrElement) {
            const $element = $(selectorOrElement).first();
            return $element.length ? $element.data('ecpCalculatorInstance') : null;
        },
        recalculateAll: function () {
            $('.ecp-calculator').each(function () {
                const instance = $(this).data('ecpCalculatorInstance');
                if (instance && typeof instance.recalculate === 'function') {
                    instance.recalculate();
                }
            });
        },
        parseFormula: function (formula, values, debug = false) {
            return parser.parse(formula, values, debug);
        }
    };

})(jQuery);