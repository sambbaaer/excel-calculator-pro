/**
 * Excel Calculator Pro - Data Sharing Admin JavaScript
 * Verwaltung der Data Sharing Einstellungen im Admin-Bereich
 */

(function ($) {
    'use strict';

    /**
     * Data Sharing Admin Manager
     */
    class ECPDataSharingAdmin {
        constructor() {
            this.settings = {};
            this.calculators = {};
            this.hasUnsavedChanges = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.loadCalculatorData();
            this.initializeSettingsCards();
            this.setupUnsavedChangesWarning();
        }

        bindEvents() {
            // Checkbox-Events für Export/Import
            $(document).on('change', '.ecp-enable-export, .ecp-enable-import', (e) => {
                this.onExportImportToggle(e);
            });

            // Speichern-Button
            $(document).on('click', '.ecp-save-calculator-settings', (e) => {
                this.saveCalculatorSettings(e);
            });

            // Mapping hinzufügen
            $(document).on('click', '.ecp-add-mapping', (e) => {
                this.addFieldMapping(e);
            });

            // Mapping entfernen
            $(document).on('click', '.ecp-remove-mapping', (e) => {
                this.removeFieldMapping(e);
            });

            // Übersicht aktualisieren
            $(document).on('click', '#ecp-refresh-overview', (e) => {
                this.refreshOverview();
            });

            // Alle Daten löschen
            $(document).on('click', '#ecp-clear-all-sharing-data', (e) => {
                this.clearAllSharingData();
            });

            // Form-Änderungen tracken
            $(document).on('change input', '.ecp-calculator-sharing-card input, .ecp-calculator-sharing-card select', () => {
                this.markUnsavedChanges();
            });
        }

        /**
         * Kalkulator-Daten laden
         */
        loadCalculatorData() {
            // Simuliere das Laden von Kalkulator-Daten (normalerweise per AJAX)
            $('.ecp-calculator-sharing-card').each((index, card) => {
                const $card = $(card);
                const calculatorId = $card.data('calculator-id');
                const calculatorName = $card.find('h4').text().trim();

                this.calculators[calculatorId] = {
                    id: calculatorId,
                    name: calculatorName,
                    fields: this.getCalculatorFields(calculatorId)
                };
            });
        }

        /**
         * Kalkulator-Felder abrufen (Simulation - normalerweise per AJAX)
         */
        getCalculatorFields(calculatorId) {
            // Diese Funktion würde normalerweise die Felder per AJAX vom Server abrufen
            // Für Demo-Zwecke geben wir Beispielfelder zurück
            return [
                { id: 'betrag', label: 'Betrag' },
                { id: 'zinssatz', label: 'Zinssatz' },
                { id: 'laufzeit', label: 'Laufzeit' },
                { id: 'anzahl', label: 'Anzahl' },
                { id: 'gewicht', label: 'Gewicht' }
            ];
        }

        /**
         * Settings-Karten initialisieren
         */
        initializeSettingsCards() {
            $('.ecp-calculator-sharing-card').each((index, card) => {
                this.initializeSettingsCard($(card));
            });
        }

        /**
         * Einzelne Settings-Karte initialisieren
         */
        initializeSettingsCard($card) {
            const calculatorId = $card.data('calculator-id');

            // Initial-Status der Mappings-Sektion setzen
            this.updateMappingsVisibility($card);

            // Feld-Mappings rendern
            this.renderFieldMappings($card, calculatorId);
        }

        /**
         * Export/Import Toggle Event Handler
         */
        onExportImportToggle(event) {
            const $checkbox = $(event.target);
            const $card = $checkbox.closest('.ecp-calculator-sharing-card');

            this.updateMappingsVisibility($card);
            this.updateCardStatus($card);
            this.markUnsavedChanges();
        }

        /**
         * Mappings-Sektion Sichtbarkeit aktualisieren
         */
        updateMappingsVisibility($card) {
            const exportEnabled = $card.find('.ecp-enable-export').is(':checked');
            const importEnabled = $card.find('.ecp-enable-import').is(':checked');

            const $mappingsSection = $card.find('.ecp-field-mappings');

            if (importEnabled) {
                $mappingsSection.slideDown(300);
            } else {
                $mappingsSection.slideUp(300);
            }
        }

        /**
         * Karten-Status visuell aktualisieren
         */
        updateCardStatus($card) {
            const exportEnabled = $card.find('.ecp-enable-export').is(':checked');
            const importEnabled = $card.find('.ecp-enable-import').is(':checked');

            if (exportEnabled || importEnabled) {
                $card.addClass('ecp-enabled');
            } else {
                $card.removeClass('ecp-enabled');
            }
        }

        /**
         * Feld-Mappings rendern
         */
        renderFieldMappings($card, calculatorId) {
            const $container = $card.find('.ecp-mappings-container');
            const targetFields = this.calculators[calculatorId]?.fields || [];

            // Alle verfügbaren Quell-Felder sammeln
            const sourceFields = this.getAllAvailableFields(calculatorId);

            // Container leeren
            $container.empty();

            if (sourceFields.length === 0) {
                $container.html('<p class="description">Keine anderen Kalkulatoren mit Export-Funktion gefunden.</p>');
                return;
            }

            // Beispiel-Mapping hinzufügen (normalerweise würden hier gespeicherte Mappings geladen)
            this.addMappingRow($container, sourceFields, targetFields);
        }

        /**
         * Alle verfügbaren Felder von anderen Kalkulatoren
         */
        getAllAvailableFields(excludeCalculatorId) {
            const fields = [];

            Object.values(this.calculators).forEach(calculator => {
                if (calculator.id != excludeCalculatorId) {
                    calculator.fields.forEach(field => {
                        fields.push({
                            ...field,
                            calculatorId: calculator.id,
                            calculatorName: calculator.name,
                            fullId: `${calculator.id}_${field.id}`,
                            displayName: `${calculator.name}: ${field.label}`
                        });
                    });
                }
            });

            return fields;
        }

        /**
         * Feld-Mapping hinzufügen
         */
        addFieldMapping(event) {
            const $button = $(event.target);
            const $card = $button.closest('.ecp-calculator-sharing-card');
            const $container = $card.find('.ecp-mappings-container');
            const calculatorId = $card.data('calculator-id');

            const targetFields = this.calculators[calculatorId]?.fields || [];
            const sourceFields = this.getAllAvailableFields(calculatorId);

            this.addMappingRow($container, sourceFields, targetFields);
            this.markUnsavedChanges();
        }

        /**
         * Mapping-Reihe hinzufügen
         */
        addMappingRow($container, sourceFields, targetFields, mapping = {}) {
            const rowId = Date.now() + Math.random();

            const $row = $(`
                <div class="ecp-mapping-row" data-row-id="${rowId}">
                    <select class="ecp-source-field" name="source_field">
                        <option value="">Quell-Feld wählen...</option>
                        ${sourceFields.map(field =>
                `<option value="${field.fullId}" ${mapping.source_field === field.fullId ? 'selected' : ''}>
                                ${field.displayName}
                            </option>`
            ).join('')}
                    </select>
                    
                    <div class="ecp-mapping-arrow">→</div>
                    
                    <select class="ecp-target-field" name="target_field">
                        <option value="">Ziel-Feld wählen...</option>
                        ${targetFields.map(field =>
                `<option value="${field.id}" ${mapping.target_field === field.id ? 'selected' : ''}>
                                ${field.label}
                            </option>`
            ).join('')}
                    </select>
                    
                    <button type="button" class="ecp-remove-mapping" title="Mapping entfernen">
                        ×
                    </button>
                </div>
            `);

            $container.append($row);

            // Animation
            $row.hide().slideDown(300);
        }

        /**
         * Feld-Mapping entfernen
         */
        removeFieldMapping(event) {
            const $button = $(event.target);
            const $row = $button.closest('.ecp-mapping-row');

            $row.slideUp(300, () => {
                $row.remove();
            });

            this.markUnsavedChanges();
        }

        /**
         * Kalkulator-Einstellungen speichern
         */
        saveCalculatorSettings(event) {
            const $button = $(event.target);
            const $card = $button.closest('.ecp-calculator-sharing-card');
            const calculatorId = $card.data('calculator-id');

            const settings = this.collectCardSettings($card);

            // Loading-State
            $button.prop('disabled', true).text('Speichert...');

            // AJAX-Request simulieren
            setTimeout(() => {
                this.saveSettingsToServer(calculatorId, settings)
                    .then(() => {
                        this.showSuccessMessage($card, 'Einstellungen gespeichert!');
                        this.clearUnsavedChanges();
                    })
                    .catch((error) => {
                        this.showErrorMessage($card, 'Fehler beim Speichern: ' + error);
                    })
                    .finally(() => {
                        $button.prop('disabled', false).text('Speichern');
                    });
            }, 500);
        }

        /**
         * Einstellungen einer Karte sammeln
         */
        collectCardSettings($card) {
            const settings = {
                enable_export: $card.find('.ecp-enable-export').is(':checked'),
                enable_import: $card.find('.ecp-enable-import').is(':checked'),
                show_ui_hints: $('#ecp-global-ui-hints').is(':checked'),
                data_retention_days: parseInt($('#ecp-default-retention').val()) || 30,
                field_mappings: []
            };

            // Feld-Mappings sammeln
            $card.find('.ecp-mapping-row').each((index, row) => {
                const $row = $(row);
                const sourceField = $row.find('.ecp-source-field').val();
                const targetField = $row.find('.ecp-target-field').val();

                if (sourceField && targetField) {
                    // Source-Field aufteilen (calculatorId_fieldId)
                    const [sourceCalculatorId, sourceFieldId] = sourceField.split('_', 2);

                    settings.field_mappings.push({
                        source_calculator: parseInt(sourceCalculatorId),
                        source_field: sourceFieldId,
                        target_field: targetField
                    });
                }
            });

            return settings;
        }

        /**
         * Einstellungen an Server senden
         */
        saveSettingsToServer(calculatorId, settings) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ecp_save_data_sharing_settings',
                        nonce: ecp_admin.nonce,
                        calculator_id: calculatorId,
                        settings: settings
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data.message || 'Unbekannter Fehler');
                        }
                    },
                    error: () => {
                        reject('Verbindungsfehler');
                    }
                });
            });
        }

        /**
         * Übersicht aktualisieren
         */
        refreshOverview() {
            const $button = $('#ecp-refresh-overview');
            const originalText = $button.text();

            $button.prop('disabled', true).text('Lädt...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ecp_get_sharing_overview',
                    nonce: ecp_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateOverviewStats(response.data);
                    }
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        /**
         * Übersicht-Statistiken aktualisieren
         */
        updateOverviewStats(stats) {
            $('#ecp-enabled-calculators').text(stats.enabled_calculators || 0);
            $('#ecp-shared-fields').text(stats.shared_fields || 0);
            $('#ecp-custom-mappings').text(stats.custom_mappings || 0);
        }

        /**
         * Alle geteilten Daten löschen
         */
        clearAllSharingData() {
            if (!confirm('Möchten Sie wirklich alle Data Sharing Konfigurationen löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ecp_clear_sharing_data',
                    nonce: ecp_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showGlobalSuccessMessage('Alle Data Sharing Konfigurationen wurden zurückgesetzt.');
                        // Seite neu laden
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showGlobalErrorMessage(response.data.message || 'Fehler beim Löschen');
                    }
                },
                error: () => {
                    this.showGlobalErrorMessage('Verbindungsfehler');
                }
            });
        }

        /**
         * Ungespeicherte Änderungen markieren
         */
        markUnsavedChanges() {
            this.hasUnsavedChanges = true;
            $('.ecp-save-calculator-settings').addClass('button-primary').text('Änderungen speichern');
        }

        /**
         * Ungespeicherte Änderungen löschen
         */
        clearUnsavedChanges() {
            this.hasUnsavedChanges = false;
            $('.ecp-save-calculator-settings').removeClass('button-primary').text('Speichern');
        }

        /**
         * Warnung bei ungespeicherten Änderungen
         */
        setupUnsavedChangesWarning() {
            $(window).on('beforeunload', (e) => {
                if (this.hasUnsavedChanges) {
                    const message = 'Sie haben ungespeicherte Änderungen. Möchten Sie die Seite wirklich verlassen?';
                    e.originalEvent.returnValue = message;
                    return message;
                }
            });
        }

        /**
         * Erfolgsmeldung für Karte anzeigen
         */
        showSuccessMessage($card, message) {
            const $success = $(`<div class="ecp-card-success">${message}</div>`);
            $card.find('.ecp-card-footer').prepend($success);

            setTimeout(() => {
                $success.fadeOut(() => {
                    $success.remove();
                });
            }, 3000);
        }

        /**
         * Fehlermeldung für Karte anzeigen
         */
        showErrorMessage($card, message) {
            const $error = $(`<div class="ecp-card-error">${message}</div>`);
            $card.find('.ecp-card-footer').prepend($error);

            setTimeout(() => {
                $error.fadeOut(() => {
                    $error.remove();
                });
            }, 5000);
        }

        /**
         * Globale Erfolgsmeldung
         */
        showGlobalSuccessMessage(message) {
            this.showGlobalMessage(message, 'success');
        }

        /**
         * Globale Fehlermeldung
         */
        showGlobalErrorMessage(message) {
            this.showGlobalMessage(message, 'error');
        }

        /**
         * Globale Nachricht anzeigen
         */
        showGlobalMessage(message, type) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

            $('.ecp-data-sharing-admin').prepend($notice);

            // Auto-remove nach 5 Sekunden
            setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            }, 5000);
        }
    }

    // CSS für Admin-Meldungen
    const adminCSS = `
        .ecp-card-success {
            color: #28a745;
            font-size: 12px;
            margin-right: 10px;
            padding: 4px 8px;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
        }
        
        .ecp-card-error {
            color: #dc3545;
            font-size: 12px;
            margin-right: 10px;
            padding: 4px 8px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 4px;
        }
        
        .ecp-calculator-sharing-card.ecp-enabled {
            border-left: 4px solid var(--ecp-primary-color, #007cba);
        }
        
        .ecp-save-calculator-settings.button-primary {
            background: #28a745 !important;
            border-color: #28a745 !important;
        }
    `;

    // CSS hinzufügen
    $('<style>').text(adminCSS).appendTo('head');

    // System initialisieren wenn DOM bereit ist
    $(document).ready(() => {
        // Nur auf der Data Sharing Seite initialisieren
        if ($('.ecp-data-sharing-admin').length > 0) {
            window.ECPDataSharingAdmin = new ECPDataSharingAdmin();
        }
    });

})(jQuery);