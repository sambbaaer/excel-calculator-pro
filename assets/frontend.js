/**
 * Excel Calculator Pro - Frontend JavaScript
 */

(function ($) {
    'use strict';

    // Enhanced Formula Parser Class (bleibt unverändert, hier gekürzt)
    class ECPFormulaParser {
        // ... (kompletter Parser-Code wie zuvor) ...
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
                    const regex = new RegExp(funcName + '\\s*\\(([^()]*?)\\)', 'gi'); // Non-greedy match for arguments
                    if (regex.test(processedFormula)) {
                        hasFunction = true;
                        processedFormula = processedFormula.replace(regex, (match, args) => {
                            // Bevor die Funktion ausgewertet wird, parse die Argumente
                            // Dies ist wichtig für verschachtelte Funktionen oder Ausdrücke als Argumente
                            const parsedArgs = this.parseArguments(args).map(arg => this.parseValue(arg, values));
                            // Rekonstruiere die Argumente-Zeichenkette für die Funktion,
                            // oder pass die geparsten Werte direkt, wenn die Funktion das erwartet.
                            // Für dieses Beispiel nehmen wir an, dass die Funktion die rohe Argument-Zeichenkette erwartet
                            // und intern parst oder evaluiert.
                            try {
                                return this.functions[funcName](args, values); // Alte Methode beibehalten, wenn sie funktioniert
                            } catch (e) {
                                console.warn('Function error in', funcName, ':', e, 'Args:', args);
                                return 0; // Fehlerbehandlung
                            }
                        });
                    }
                }
                if (processedFormula === lastFormula || !hasFunction) { // Wenn keine Änderungen oder keine Funktionen mehr gefunden wurden
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
                .replace(/\s+/g, '') // Alle Leerzeichen entfernen für shunting-yard
                .trim();

            // Check for allowed characters more strictly if using a custom parser
            // For Function constructor, it's generally safer but still limited.
            // This regex is a basic check, a proper parser would be more robust.
            if (/[^0-9.+\-*/%()^piPIeEphiPHI]/.test(normalized.replace(/[a-zA-Z_][a-zA-Z0-9_]*/g, ''))) { // Erlaube Funktionsnamen
                // throw new Error('Unsichere Zeichen in Formel: ' + normalized);
                // Sanft behandeln, da Feld-IDs schon ersetzt sein sollten
            }


            try {
                // Potenz-Operator (^) durch Math.pow ersetzen, bevor evaluiert wird
                // Muss sorgfältig gemacht werden, um die Operatorrangfolge nicht zu verletzen
                // Für einfache Fälle:
                normalized = normalized.replace(/(\d+(?:\.\d+)?)\^(\d+(?:\.\d+)?)/g, 'Math.pow($1,$2)');

                return Function('"use strict"; return (' + normalized + ')')();
            } catch (e) {
                // console.error('SafeEval error:', e.message, "Expression:", normalized);
                throw new Error('Evaluierungsfehler: ' + e.message + " Ausdruck: " + expression);
            }
        }


        replacePowerOperator(expression) {
            // Diese Funktion muss rekursiv oder mit korrekter Rangfolge arbeiten.
            // Eine einfache Regex-Ersetzung ist oft nicht ausreichend für komplexe Ausdrücke.
            // Für dieses Beispiel wird es in safeEval direkt behandelt.
            return expression;
        }

        IF(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 2) return 0; // Benötigt mindestens Bedingung und Wahr-Wert

            const conditionResult = this.evaluateCondition(parts[0], values);

            if (conditionResult) {
                return this.parseValue(parts[1], values);
            } else if (parts.length > 2) {
                return this.parseValue(parts[2], values);
            }
            return 0; // Oder FALSE, je nach Excel-Verhalten
        }

        ROUND(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return 0;
            const value = this.parseValue(parts[0], values);
            const decimals = parts.length > 1 ? Math.max(0, Math.floor(this.parseValue(parts[1], values))) : 0;
            const factor = Math.pow(10, decimals);
            return Math.round(value * factor) / factor;
        }

        MIN(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return NaN; // Oder 0, je nach Excel-Verhalten
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
            if (parts.length === 0) return NaN; // Division durch Null vermeiden
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
            return value >= 0 ? Math.sqrt(value) : NaN; // Excel gibt #ZAHL! zurück
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
            // Gibt eine Zahl zurück, die das Datum repräsentiert (Excel-Stil)
            // Dies ist eine Vereinfachung. Echte Excel-Datumswerte sind komplexer.
            const today = new Date();
            const excelEpoch = new Date(1899, 11, 30); // Excel's Epoch (Tag 0)
            return Math.floor((today - excelEpoch) / (24 * 60 * 60 * 1000));
        }

        YEAR(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length === 0) return new Date().getFullYear();
            const dateValue = this.parseValue(parts[0], values);
            // Annahme: dateValue ist eine Excel-Datumszahl
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
            // Excel zählt Tage seit dem 30. Dezember 1899 (Tag 0)
            // JavaScript zählt Millisekunden seit dem 1. Januar 1970
            // Der 29. Februar 1900 ist ein Fehler in Excel, der hier ignoriert wird.
            const utc_days = Math.floor(serial - 25569); // 25569 ist die Differenz zwischen Excel-Epoche und Unix-Epoche
            const utc_value = utc_days * 86400;
            const date_info = new Date(utc_value * 1000);
            return new Date(date_info.getFullYear(), date_info.getMonth(), date_info.getDate());
        }


        CEILING(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return NaN;
            const number = this.parseValue(parts[0], values);
            const significance = parts.length > 1 ? this.parseValue(parts[1], values) : 1;
            if (significance == 0) return 0;
            if ((number > 0 && significance < 0)) return NaN; // Excel #ZAHL!
            return Math.ceil(number / significance) * significance;
        }

        FLOOR(args, values) {
            const parts = this.parseArguments(args);
            if (parts.length < 1) return NaN;
            const number = this.parseValue(parts[0], values);
            const significance = parts.length > 1 ? this.parseValue(parts[1], values) : 1;
            if (significance == 0) return 0;
            if ((number > 0 && significance < 0) || (number < 0 && significance > 0)) return NaN; // Excel #ZAHL!
            return Math.floor(number / significance) * significance;
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

                if ((char === '"' || char === "'") && (i === 0 || argsString[i - 1] !== '\\')) { // Handle escaped quotes later if needed
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

                if (char === ';' && depth === 0 && !inQuotes) { // Excel uses ';' as separator in some locales
                    args.push(currentArg.trim());
                    currentArg = '';
                } else {
                    currentArg += char;
                }
            }
            args.push(currentArg.trim());
            return args.filter(arg => arg.length > 0); // Filter empty args
        }


        parseValue(value, values) {
            if (typeof value === 'number') return value;
            value = String(value).trim();

            if (value.startsWith('"') && value.endsWith('"')) {
                return value.substring(1, value.length - 1); // String literal
            }
            if (value.startsWith("'") && value.endsWith("'")) {
                return value.substring(1, value.length - 1); // String literal
            }

            if (value.toLowerCase() === 'true') return true;
            if (value.toLowerCase() === 'false') return false;

            if (!isNaN(value) && value !== '') { // Check if it's a number
                return parseFloat(value);
            }

            if (values && values.hasOwnProperty(value)) { // Check if it's a field ID
                return parseFloat(values[value]) || 0; // Default to 0 if field is not a number
            }

            if (this.constants.hasOwnProperty(value.toUpperCase())) { // Check constants
                return this.constants[value.toUpperCase()];
            }

            // If it's not a number, field ID, or known constant, try to evaluate as an expression
            // This part is tricky and needs robust parsing or safe evaluation.
            // For now, we assume it might be a nested expression processed earlier or a direct number.
            try {
                // Re-process if it looks like a function call that was missed (simple check)
                if (/[A-Z]+\s*\(.*\)/i.test(value)) {
                    return this.parse(value, values); // Recursive call for sub-expressions/functions
                }
                return this.safeEval(value);
            } catch (e) {
                // console.warn("Could not parse value:", value, e.message);
                return value; // Return as string if it can't be parsed as number/expression
            }
        }

        evaluateCondition(conditionStr, values) {
            // This needs a proper expression evaluator for conditions like "A1 > 10", "B2 = C3", etc.
            // For simplicity, we'll try to use safeEval, but this is not robust for complex comparisons.
            // A real implementation would parse the comparison operator and operands.
            try {
                // Replace field IDs in the condition string
                let populatedCondition = conditionStr;
                for (const fieldId in values) {
                    if (values.hasOwnProperty(fieldId)) {
                        const regex = new RegExp('\\b' + this.escapeRegExp(fieldId) + '\\b', 'g');
                        populatedCondition = populatedCondition.replace(regex, values[fieldId] || 0);
                    }
                }
                // Basic comparison operators
                const operators = {
                    '>=': (a, b) => a >= b,
                    '<=': (a, b) => a <= b,
                    '<>': (a, b) => a != b,
                    '!=': (a, b) => a != b,
                    '=': (a, b) => a == b, // Use == for Excel-like comparison (e.g. "5" == 5 is true)
                    '>': (a, b) => a > b,
                    '<': (a, b) => a < b
                };

                for (const op in operators) {
                    const parts = populatedCondition.split(op);
                    if (parts.length === 2) {
                        const left = this.parseValue(parts[0], values); // Parse operands
                        const right = this.parseValue(parts[1], values);
                        if (isFinite(left) && isFinite(right)) {
                            return operators[op](Number(left), Number(right));
                        }
                    }
                }
                // Fallback for single values (e.g. WENN(feld_x; ...))
                return !!this.parseValue(populatedCondition, values); // Coerce to boolean

            } catch (e) {
                // console.warn("Condition evaluation error:", e.message, "Condition:", conditionStr);
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
            this.calculate(); // Initial calculation

            this.$element.hide().fadeIn(500);
            this.setupAccessibility();
        }

        bindEvents() {
            this.$element.on('input change keyup', '.ecp-input-field', (e) => {
                this.handleInputChange(e);
            });

            this.$element.on('blur', '.ecp-input-field', (e) => {
                this.validateInput(e.target);
                // Recalculate on blur as well, as value might have been corrected
                this.handleInputChange(e);
            });

            this.$element.on('focus', '.ecp-input-field', (e) => {
                $(e.target).closest('.ecp-field-group').addClass('ecp-focused');
            });

            this.$element.on('blur', '.ecp-input-field', (e) => {
                $(e.target).closest('.ecp-field-group').removeClass('ecp-focused');
            });

            // MODIFIZIERT: Event-Handler für Kopier-Icon
            this.$element.on('click keypress', '.ecp-copy-icon', (e) => {
                if (e.type === 'click' || (e.type === 'keypress' && (e.key === 'Enter' || e.key === ' '))) {
                    e.preventDefault();
                    this.handleCopyResult($(e.currentTarget));
                }
            });
        }

        // MODIFIZIERT: Kopierfunktion
        handleCopyResult($icon) {
            if ($icon.hasClass('ecp-copied-feedback')) return; // Verhindert schnelles erneutes Klicken

            const $wrapper = $icon.closest('.ecp-output-wrapper');
            const $outputField = $wrapper.find('.ecp-output-field');
            const $outputUnitSpan = $wrapper.find('.ecp-output-unit');

            let valueToCopy = $outputField.text().trim();
            const unitFromSpan = $outputUnitSpan.length ? $outputUnitSpan.text().trim() : '';

            if (unitFromSpan) {
                const valueLower = valueToCopy.toLowerCase();
                const unitLower = unitFromSpan.toLowerCase();
                // Füge die Einheit hinzu, wenn sie nicht bereits Teil des formatierten Wertes ist
                // (z.B. % oder Währungssymbol wird bereits von formatNumber hinzugefügt)
                if (!valueLower.includes(unitLower) && !(valueToCopy.endsWith('%') && unitFromSpan === '%')) {
                    valueToCopy += ' ' + unitFromSpan;
                }
            }

            navigator.clipboard.writeText(valueToCopy).then(() => {
                const originalIconContent = $icon.html(); // Speichere den originalen Icon-Inhalt (könnte auch ein SVG sein)
                $icon.html('✅').addClass('ecp-copied-feedback');
                setTimeout(() => {
                    $icon.html(originalIconContent).removeClass('ecp-copied-feedback');
                }, 1500);
            }).catch(err => {
                console.error('ECP: Fehler beim Kopieren: ', err);
                const originalIconContent = $icon.html();
                $icon.html('❌').addClass('ecp-copied-feedback ecp-copy-error'); // Eigene Klasse für Fehler
                setTimeout(() => {
                    $icon.html(originalIconContent).removeClass('ecp-copied-feedback ecp-copy-error');
                }, 2000);
            });
        }


        setupAccessibility() {
            this.$element.find('.ecp-input-field').each(function () {
                const $input = $(this);
                const fieldId = $input.data('field-id'); // Use data-field-id for consistency
                const $label = $input.closest('.ecp-field-group').find('label[for="ecp-field-' + fieldId + '"]');
                let labelText = $label.length ? $label.text().trim() : 'Eingabefeld ' + fieldId;

                // Hilfetext als aria-describedby hinzufügen, falls vorhanden
                const $helpText = $input.closest('.ecp-input-wrapper').find('.ecp-field-help-prefix');
                let helpId = '';
                if ($helpText.length) {
                    helpId = 'ecp-help-desc-' + fieldId;
                    $helpText.attr('id', helpId);
                }

                $input.attr({
                    'aria-label': labelText,
                    'role': $input.attr('type') === 'number' ? 'spinbutton' : null, // Nur für type=number
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
            // Kopier-Icons
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
                // Korrigiere Wert, falls leer und Typ number, oder ungültig
                if ($input.attr('type') === 'number') {
                    value = parseFloat(value);
                    if (isNaN(value)) value = 0; // Oder $input.attr('min') falls vorhanden
                }
                this.values[fieldId] = value;
                $input.val(value); // Stelle sicher, dass der korrigierte Wert im Feld steht
            });
        }


        handleInputChange(event) {
            const $input = $(event.target);
            const fieldId = $input.data('field-id');
            let value = $input.val();

            if ($input.attr('type') === 'number') {
                const numValue = parseFloat(value);
                // Wenn leer und type=number, wird es oft als NaN interpretiert.
                // Setze es auf 0 oder den Min-Wert, wenn leer.
                if (value.trim() === '') {
                    // value = 0; // Oder parseFloat($input.attr('min')) || 0;
                    // Lasse es leer, validateInput wird es ggf. korrigieren oder die Formel mit 0 rechnen
                } else if (isNaN(numValue)) {
                    // value = this.values[fieldId] || 0; // Zurück zum alten Wert oder 0
                } else {
                    // value = numValue; // Stelle sicher, dass es eine Zahl ist
                }
            }
            // $input.val(value); // Aktualisiere das Feld mit dem ggf. korrigierten Wert. Vorsicht bei direkter Manipulation während der Eingabe.
            this.values[fieldId] = parseFloat(value) || 0; // In Formeln immer mit Zahl oder 0 rechnen

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
            // Required wurde entfernt
            // const required = $input.prop('required');

            let $errorContainer = $input.siblings('.ecp-error-message');
            if (!$errorContainer.length) {
                $errorContainer = $('<div class="ecp-error-message" aria-live="assertive"></div>');
                $input.parent().append($errorContainer);
            }
            $errorContainer.empty().hide();
            $input.removeClass('ecp-input-error');


            if (type === 'number') {
                if (value.trim() === '') {
                    // Wenn das Feld leer ist, aber mal einen Wert hatte oder einen Default,
                    // könnte man es auf 0 oder min setzen. Für jetzt lassen wir es,
                    // die Berechnungslogik sollte parseFloat(value) || 0 verwenden.
                    return true; // Leer ist okay, wenn nicht required (was entfernt wurde)
                }
                const numValue = parseFloat(value);
                if (isNaN(numValue)) {
                    this.showFieldError($input, $errorContainer, ecp_frontend.strings.error_invalid_number || 'Ungültige Zahl.');
                    // $input.val(this.values[$input.data('field-id')] || 0); // Optional: alten Wert wiederherstellen
                    return false;
                }
                if (!isNaN(min) && numValue < min) {
                    this.showFieldError($input, $errorContainer, (ecp_frontend.strings.error_min_value || 'Wert muss mind. %min% sein.').replace('%min%', min));
                    // $input.val(min); // Optional: auf Min korrigieren
                    return false;
                }
                if (!isNaN(max) && numValue > max) {
                    this.showFieldError($input, $errorContainer, (ecp_frontend.strings.error_max_value || 'Wert darf max. %max% sein.').replace('%max%', max));
                    // $input.val(max); // Optional: auf Max korrigieren
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
            this.$element.addClass('ecp-calculating'); // Visuelles Feedback starten
            this.debounceTimer = setTimeout(() => {
                this.calculate();
                this.$element.removeClass('ecp-calculating'); // Visuelles Feedback beenden
            }, 250); // Kurze Verzögerung
        }

        calculate() {
            if (this.isCalculating) return;
            this.isCalculating = true;
            // this.$element.addClass('ecp-loading'); // Wurde durch ecp-calculating ersetzt

            // Sammle alle Werte neu, für den Fall, dass validateInput sie geändert hat
            this.initializeValues();

            try {
                this.$element.find('.ecp-output-field').each((index, output) => {
                    const $output = $(output);
                    const formula = $output.data('formula');
                    const format = $output.data('format') || '';
                    const prevValue = $output.text(); // Formatierten Wert für Vergleich speichern

                    if (formula) {
                        try {
                            const result = parser.parse(formula, this.values, false); // Debug auf false
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
                            // console.error('Calculation error for formula:', formula, error);
                            $output.text('Fehler').addClass('ecp-output-error');
                        }
                    }
                });
                this.$element.removeClass('ecp-calculator-error-state').addClass('ecp-calculator-success-state');
            } catch (error) {
                // console.error('General calculation error:', error);
                this.$element.addClass('ecp-calculator-error-state').removeClass('ecp-calculator-success-state');
            } finally {
                this.isCalculating = false;
                // this.$element.removeClass('ecp-loading');
            }
        }

        announceChange($output, value) {
            // ... (bleibt wie zuvor) ...
            const $label = $output.closest('.ecp-output-group').find('label');
            let labelText = $label.length ? $label.first().contents().filter(function () { return this.nodeType === 3; }).text().trim() : '';
            const announcement = `${labelText}: ${value}`;

            let $liveRegion = $('#ecp-live-region');
            if (!$liveRegion.length) {
                $liveRegion = $('<div id="ecp-live-region" class="ecp-sr-only" aria-live="polite" aria-atomic="true"></div>');
                $('body').append($liveRegion);
            }
            $liveRegion.text(announcement); // Update text to trigger announcement
        }

        formatNumber(number, format) {
            // ... (bleibt wie zuvor, aber stelle sicher, dass ecp_frontend.strings für Währungssymbole verfügbar ist) ...
            if (!isFinite(number) || isNaN(number)) {
                return '0'; // Oder Fehlerstring, z.B. '#ZAHL!'
            }

            const settings = window.ecp_frontend ? window.ecp_frontend.settings : {};
            const locale = settings.number_format || 'de-CH'; // z.B. 'de-CH', 'en-US'
            const currency = settings.currency || 'CHF';
            const currencySymbols = settings.currency_symbols || { CHF: 'CHF', EUR: '€', USD: '$' };


            const targetLocale = locale.replace('_', '-'); // Wandelt 'de_CH' in 'de-CH' um

            try {
                switch (format) {
                    case 'currency':
                        const currencySymbol = currencySymbols[currency] || currency + ' ';
                        return number.toLocaleString(targetLocale, {
                            style: 'currency',
                            currency: currency, // Wichtig für korrekte Formatierung und Symbolposition
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
                        return String(number); // Einfache String-Konvertierung
                    default: // Standard Zahlenformat
                        let defaultDecimals = 2;
                        if (Math.abs(number) >= 1000) defaultDecimals = 0; // Weniger Dezimalen für grosse Zahlen
                        else if (Math.abs(number) < 1 && Math.abs(number) > 0) defaultDecimals = Math.min(4, (number.toString().split('.')[1] || '').length); // Mehr für kleine Zahlen

                        return number.toLocaleString(targetLocale, {
                            minimumFractionDigits: 0, // Keine unnötigen Nullen
                            maximumFractionDigits: defaultDecimals
                        });
                }
            } catch (e) {
                // console.warn('Localization error for number:', number, "Format:", format, e);
                // Fallback, wenn toLocaleString fehlschlägt
                let fixedNum = number.toFixed(2);
                if (format === 'integer') fixedNum = Math.round(number).toString();
                if (format === 'percentage') fixedNum = (number * 100).toFixed(1) + '%';
                return fixedNum;
            }
        }
        // ... (Rest der ECPCalculator Klasse: recalculate, setValue, getValue, getResults) ...
        recalculate() {
            this.calculate();
        }

        setValue(fieldId, value) {
            const $input = this.$element.find(`.ecp-input-field[data-field-id="${fieldId}"]`);
            if ($input.length) {
                $input.val(value);
                // Triggere die gleichen Events wie bei manueller Eingabe, um Validierung und Berechnung auszulösen
                // $input.trigger('input').trigger('change'); // input für sofort, change für debounce
                this.handleInputChange({ target: $input[0] }); // Direkter Aufruf für Konsistenz
            }
        }


        getValue(fieldId) { // Gibt den rohen Wert zurück, der für Berechnungen verwendet wird
            return this.values[fieldId] || 0;
        }

        getResults() { // Gibt die formatierten Ergebnis-Strings zurück
            const results = {};
            this.$element.find('.ecp-output-field').each(function () {
                const $output = $(this);
                const outputId = $output.data('output-id'); // Sollte der Index oder eine eindeutige ID sein
                const label = $output.closest('.ecp-output-group').find('label').text().trim();
                // Verwende das data-output-id als Schlüssel, oder das Label wenn keine ID vorhanden
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
            if (!$.data(this, 'ecpCalculatorInstance')) { // Verhindert Mehrfachinitialisierung
                $.data(this, 'ecpCalculatorInstance', new ECPCalculator(this));
            }
        });
    };

    $(document).ready(function () {
        $('.ecp-calculator').ecpCalculator();

        // Globale Einstellungen für Lokalisierung (Beispiel)
        if (typeof ecp_frontend === 'undefined') {
            window.ecp_frontend = {
                settings: {
                    number_format: 'de_CH',
                    currency: 'CHF',
                    currency_symbols: { CHF: 'CHF ', EUR: '€ ', USD: '$ ' }
                },
                strings: { // Füge hier übersetzbare Strings hinzu
                    error_invalid_number: 'Ungültige Zahl.',
                    error_min_value: 'Wert muss mind. %min% sein.',
                    error_max_value: 'Wert darf max. %max% sein.',
                    copy_label_prefix: 'Kopiere '
                }
            };
        }
    });

    // API für externe Nutzung (optional)
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