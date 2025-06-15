/**
 * Excel Calculator Pro - Data Sharing System
 * Verwaltet Local Storage basierte Datenübertragung zwischen Kalkulatoren
 */

(function ($) {
    'use strict';

    /**
     * Data Sharing Manager Klasse
     */
    class ECPDataSharing {
        constructor() {
            this.storageKey = 'ecp_shared_calculator_data';
            this.settings = window.ecpDataSharing?.settings || {};
            this.strings = window.ecpDataSharing?.strings || {};
            this.debug = window.ecpDataSharing?.debug || false;
            this.retentionDays = 30; // Default
            this.currentCalculatorId = null;
            this.calculatorInstance = null;

            this.init();
        }

        init() {
            // Warten bis DOM bereit ist
            $(document).ready(() => {
                this.bindEvents();
                this.initializeCalculators();
                this.cleanupOldData();
            });
        }

        bindEvents() {
            // Calculator bereit Event
            $(document).on('ecp_calculator_ready', (event, calculatorInstance) => {
                this.onCalculatorReady(calculatorInstance);
            });

            // Eingabe-Events für automatisches Speichern
            $(document).on('input change', '.ecp-input-field', (e) => {
                this.onInputChange(e);
            });

            // Berechnung abgeschlossen Event
            $(document).on('ecp_calculation_complete', (event, calculatorId, results) => {
                this.onCalculationComplete(calculatorId, results);
            });

            // Shared Data Badge Clicks
            $(document).on('click', '.ecp-shared-data-badge', (e) => {
                this.showDataSourceInfo(e);
            });

            // Clear Data Button (falls vorhanden)
            $(document).on('click', '.ecp-clear-shared-data', (e) => {
                this.clearSharedData();
            });
        }

        /**
         * Alle Kalkulatoren auf der Seite initialisieren
         */
        initializeCalculators() {
            $('.ecp-calculator').each((index, element) => {
                const $calculator = $(element);
                const calculatorId = $calculator.data('calculator-id');

                if (calculatorId && this.isCalculatorEnabled(calculatorId)) {
                    this.initializeCalculator($calculator, calculatorId);
                }
            });
        }

        /**
         * Einzelnen Kalkulator initialisieren
         */
        initializeCalculator($calculator, calculatorId) {
            this.currentCalculatorId = calculatorId;
            const calculatorSettings = this.settings[calculatorId];

            if (!calculatorSettings) {
                return;
            }

            // Import aktiviert? -> Daten laden
            if (calculatorSettings.import_enabled) {
                this.loadSharedDataForCalculator($calculator, calculatorId);
            }

            this.log(`Kalkulator ${calculatorId} für Data Sharing initialisiert`, calculatorSettings);
        }

        /**
         * Kalkulator bereit Event Handler
         */
        onCalculatorReady(calculatorInstance) {
            this.calculatorInstance = calculatorInstance;
            const calculatorId = calculatorInstance.$element.data('calculator-id');

            if (calculatorId) {
                this.currentCalculatorId = calculatorId;
                this.log(`Kalkulator ${calculatorId} ist bereit`);
            }
        }

        /**
         * Eingabe-Änderung Event Handler
         */
        onInputChange(event) {
            const $input = $(event.target);
            const calculatorId = $input.closest('.ecp-calculator').data('calculator-id');

            if (!calculatorId || !this.isExportEnabled(calculatorId)) {
                return;
            }

            // Debounce für bessere Performance
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => {
                this.saveInputData(calculatorId, $input);
            }, 300);
        }

        /**
         * Berechnung abgeschlossen Event Handler
         */
        onCalculationComplete(calculatorId, results) {
            if (!this.isExportEnabled(calculatorId)) {
                return;
            }

            this.saveCalculationResults(calculatorId, results);
        }

        /**
         * Geteilte Daten für Kalkulator laden
         */
        loadSharedDataForCalculator($calculator, calculatorId) {
            const sharedData = this.getSharedData();
            const calculatorSettings = this.settings[calculatorId];
            let dataLoaded = false;

            // Durch alle verfügbaren geteilten Daten iterieren
            Object.keys(sharedData).forEach(sourceCalculatorId => {
                if (sourceCalculatorId === calculatorId.toString()) {
                    return; // Eigene Daten überspringen
                }

                const sourceData = sharedData[sourceCalculatorId];
                if (!sourceData || !sourceData.inputs) {
                    return;
                }

                // Feld-Mappings anwenden
                this.applyFieldMappings($calculator, sourceData, calculatorId, sourceCalculatorId, calculatorSettings);
                dataLoaded = true;
            });

            if (dataLoaded) {
                // Neuberechnung auslösen falls Daten geladen wurden
                setTimeout(() => {
                    this.triggerRecalculation($calculator);
                }, 100);
            }
        }

        /**
         * Feld-Mappings anwenden
         */
        applyFieldMappings($calculator, sourceData, targetCalculatorId, sourceCalculatorId, calculatorSettings) {
            const sourceCalculatorName = this.getCalculatorName(sourceCalculatorId);

            // Automatische Mappings (gleiche Feld-IDs)
            Object.keys(sourceData.inputs).forEach(fieldId => {
                const $targetField = $calculator.find(`.ecp-input-field[data-field-id="${fieldId}"]`);

                if ($targetField.length > 0 && !$targetField.val()) {
                    this.setFieldValue($targetField, sourceData.inputs[fieldId], sourceCalculatorName, sourceData.timestamp);
                }
            });

            // Benutzerdefinierte Mappings
            if (calculatorSettings.field_mappings && calculatorSettings.field_mappings.length > 0) {
                calculatorSettings.field_mappings.forEach(mapping => {
                    if (mapping.source_calculator == 0 || mapping.source_calculator == sourceCalculatorId) {
                        const $targetField = $calculator.find(`.ecp-input-field[data-field-id="${mapping.target_field}"]`);

                        if ($targetField.length > 0 && sourceData.inputs[mapping.source_field] !== undefined) {
                            this.setFieldValue(
                                $targetField,
                                sourceData.inputs[mapping.source_field],
                                sourceCalculatorName,
                                sourceData.timestamp,
                                true
                            );
                        }
                    }
                });
            }
        }

        /**
         * Feldwert setzen mit UI-Feedback
         */
        setFieldValue($field, value, sourceName, timestamp, isCustomMapping = false) {
            $field.val(value);

            const calculatorSettings = this.settings[this.currentCalculatorId];
            if (calculatorSettings?.show_ui_hints !== false) {
                this.addSharedDataBadge($field, sourceName, timestamp, isCustomMapping);
            }

            // Animation für visuelles Feedback
            $field.addClass('ecp-data-loaded-animation');
            setTimeout(() => {
                $field.removeClass('ecp-data-loaded-animation');
            }, 1000);

            this.log(`Feld ${$field.data('field-id')} mit Wert ${value} aus ${sourceName} befüllt`);
        }

        /**
         * Shared Data Badge hinzufügen
         */
        addSharedDataBadge($field, sourceName, timestamp, isCustomMapping) {
            const $wrapper = $field.closest('.ecp-input-wrapper');

            // Vorhandenen Badge entfernen
            $wrapper.find('.ecp-shared-data-badge').remove();

            const badgeText = isCustomMapping ? '🔗' : '📋';
            const tooltipText = this.strings.data_shared_tooltip || 'Automatisch befüllt';

            const $badge = $(`
                <span class="ecp-shared-data-badge" 
                      title="${tooltipText}"
                      data-source="${sourceName}"
                      data-timestamp="${timestamp}">
                    ${badgeText}
                </span>
            `);

            $wrapper.append($badge);

            // Badge mit Animation einblenden
            setTimeout(() => {
                $badge.addClass('ecp-badge-visible');
            }, 50);
        }

        /**
         * Eingabedaten speichern
         */
        saveInputData(calculatorId, $input) {
            const fieldId = $input.data('field-id');
            const value = $input.val();

            if (!fieldId || value === '') {
                return;
            }

            const sharedData = this.getSharedData();
            if (!sharedData[calculatorId]) {
                sharedData[calculatorId] = {
                    inputs: {},
                    outputs: {},
                    timestamp: new Date().toISOString(),
                    calculator_name: this.getCalculatorName(calculatorId)
                };
            }

            sharedData[calculatorId].inputs[fieldId] = value;
            sharedData[calculatorId].timestamp = new Date().toISOString();

            this.saveSharedData(sharedData);
            this.log(`Eingabedaten für Kalkulator ${calculatorId} gespeichert:`, { fieldId, value });
        }

        /**
         * Berechnungsergebnisse speichern
         */
        saveCalculationResults(calculatorId, results) {
            const sharedData = this.getSharedData();

            if (!sharedData[calculatorId]) {
                sharedData[calculatorId] = {
                    inputs: {},
                    outputs: {},
                    timestamp: new Date().toISOString(),
                    calculator_name: this.getCalculatorName(calculatorId)
                };
            }

            // Aktuelle Eingabewerte auch speichern
            const $calculator = $(`.ecp-calculator[data-calculator-id="${calculatorId}"]`);
            $calculator.find('.ecp-input-field').each((index, input) => {
                const $input = $(input);
                const fieldId = $input.data('field-id');
                const value = $input.val();

                if (fieldId && value !== '') {
                    sharedData[calculatorId].inputs[fieldId] = value;
                }
            });

            // Ergebnisse speichern
            sharedData[calculatorId].outputs = results;
            sharedData[calculatorId].timestamp = new Date().toISOString();

            this.saveSharedData(sharedData);
            this.log(`Ergebnisse für Kalkulator ${calculatorId} gespeichert:`, results);
        }

        /**
         * Geteilte Daten aus LocalStorage abrufen
         */
        getSharedData() {
            try {
                const data = localStorage.getItem(this.storageKey);
                return data ? JSON.parse(data) : {};
            } catch (e) {
                this.log('Fehler beim Laden der geteilten Daten:', e);
                return {};
            }
        }

        /**
         * Geteilte Daten in LocalStorage speichern
         */
        saveSharedData(data) {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(data));
            } catch (e) {
                this.log('Fehler beim Speichern der geteilten Daten:', e);
            }
        }

        /**
         * Alte Daten bereinigen
         */
        cleanupOldData() {
            const sharedData = this.getSharedData();
            const now = new Date();
            let hasChanges = false;

            Object.keys(sharedData).forEach(calculatorId => {
                const data = sharedData[calculatorId];
                if (!data.timestamp) {
                    return;
                }

                const dataDate = new Date(data.timestamp);
                const daysDiff = (now - dataDate) / (1000 * 60 * 60 * 24);

                const retentionDays = this.settings[calculatorId]?.data_retention_days || this.retentionDays;

                if (daysDiff > retentionDays) {
                    delete sharedData[calculatorId];
                    hasChanges = true;
                    this.log(`Alte Daten für Kalkulator ${calculatorId} gelöscht (${Math.round(daysDiff)} Tage alt)`);
                }
            });

            if (hasChanges) {
                this.saveSharedData(sharedData);
            }
        }

        /**
         * Data Source Info anzeigen
         */
        showDataSourceInfo(event) {
            event.preventDefault();
            const $badge = $(event.currentTarget);
            const sourceName = $badge.data('source');
            const timestamp = $badge.data('timestamp');

            if (!sourceName || !timestamp) {
                return;
            }

            const date = new Date(timestamp);
            const formattedDate = date.toLocaleString();

            const message = `
                <strong>${this.strings.data_source_calculator?.replace('%s', sourceName) || `Quelle: ${sourceName}`}</strong><br>
                ${this.strings.data_timestamp?.replace('%s', formattedDate) || `Zeitpunkt: ${formattedDate}`}
            `;

            // Einfaches Tooltip-System oder Alert
            if (typeof this.showTooltip === 'function') {
                this.showTooltip($badge, message);
            } else {
                alert(message.replace(/<[^>]*>/g, ''));
            }
        }

        /**
         * Geteilte Daten löschen
         */
        clearSharedData() {
            if (confirm(this.strings.clear_shared_data || 'Möchten Sie alle geteilten Daten löschen?')) {
                localStorage.removeItem(this.storageKey);

                // Badges entfernen
                $('.ecp-shared-data-badge').remove();

                this.log('Alle geteilten Daten gelöscht');

                // Erfolgsmeldung
                if (this.strings.data_cleared) {
                    this.showNotification(this.strings.data_cleared, 'success');
                }
            }
        }

        /**
         * Kalkulator-Name abrufen
         */
        getCalculatorName(calculatorId) {
            const $calculator = $(`.ecp-calculator[data-calculator-id="${calculatorId}"]`);
            const name = $calculator.find('.ecp-calculator-title').first().text().trim();

            return name || `Kalkulator ${calculatorId}`;
        }

        /**
         * Neuberechnung auslösen
         */
        triggerRecalculation($calculator) {
            const calculatorInstance = $calculator.data('ecpCalculatorInstance');
            if (calculatorInstance && typeof calculatorInstance.recalculate === 'function') {
                calculatorInstance.recalculate();
            }
        }

        /**
         * Prüft ob Kalkulator aktiviert ist
         */
        isCalculatorEnabled(calculatorId) {
            return !!this.settings[calculatorId];
        }

        /**
         * Prüft ob Export für Kalkulator aktiviert ist
         */
        isExportEnabled(calculatorId) {
            return !!(this.settings[calculatorId]?.export_enabled);
        }

        /**
         * Prüft ob Import für Kalkulator aktiviert ist
         */
        isImportEnabled(calculatorId) {
            return !!(this.settings[calculatorId]?.import_enabled);
        }

        /**
         * Benachrichtigung anzeigen
         */
        showNotification(message, type = 'info') {
            // Integration mit bestehendem Notification-System falls vorhanden
            if (window.ECPAdmin && window.ECPAdmin.notifications) {
                window.ECPAdmin.notifications.show(message, type);
            } else {
                console.log(`ECP Data Sharing: ${message}`);
            }
        }

        /**
         * Debug-Logging
         */
        log(...args) {
            if (this.debug) {
                console.log('[ECP Data Sharing]', ...args);
            }
        }

        /**
         * API für externe Nutzung
         */
        getAPI() {
            return {
                getSharedData: () => this.getSharedData(),
                clearSharedData: () => this.clearSharedData(),
                saveInputData: (calculatorId, fieldId, value) => {
                    const $input = $(`.ecp-calculator[data-calculator-id="${calculatorId}"] .ecp-input-field[data-field-id="${fieldId}"]`);
                    if ($input.length) {
                        $input.val(value);
                        this.saveInputData(calculatorId, $input);
                    }
                },
                isEnabled: (calculatorId) => this.isCalculatorEnabled(calculatorId)
            };
        }
    }

    // System initialisieren
    $(document).ready(() => {
        // Nur initialisieren wenn Data Sharing Einstellungen vorhanden sind
        if (window.ecpDataSharing && window.ecpDataSharing.settings) {
            const dataSharing = new ECPDataSharing();

            // API global verfügbar machen
            window.ECPDataSharingAPI = dataSharing.getAPI();

            // Custom Event für andere Scripts
            $(document).trigger('ecp_data_sharing_ready', [dataSharing]);
        }
    });

})(jQuery);