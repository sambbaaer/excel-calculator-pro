/**
 * Excel Calculator Pro - Admin JavaScript
 */

(function ($) {
    'use strict';

    // Globale Variablen
    let currentCalculatorId = 0;
    let fieldCounter = 0;
    let outputCounter = 0;
    let selectedTemplateId = 0;
    let unsavedChangesExist = false; // Status für ungespeicherte Änderungen

    // DOM Ready
    $(document).ready(function () {
        initializeAdmin();
    });

    /**
     * Admin initialisieren
     */
    function initializeAdmin() {
        bindEvents();
        initializeSortables();
        initializeTooltips();
        checkEmptyState(); // Prüfen, ob Kalkulatoren vorhanden sind

        // Fade-in Animation für Cards
        $('.ecp-calculator-card, .ecp-template-card').addClass('ecp-fade-in');

        // Initialisiere den Editor-Status (z.B. Löschen/Duplizieren-Button ausblenden, wenn kein Kalkulator geladen ist)
        if (!$('#calculator-id').val() || $('#calculator-id').val() === '0') {
            $('#ecp-delete-calculator').hide();
            $('#ecp-duplicate-calculator').hide();
        }
    }

    /**
     * Event-Handler binden
     */
    function bindEvents() {
        // Kalkulator-Management
        $('#ecp-new-calculator').on('click', newCalculator);
        $(document).on('click', '.ecp-edit-calc', onClickEditCalculator);
        $(document).on('click', '.ecp-delete-calc', onClickDeleteCalculator);
        $(document).on('click', '.ecp-duplicate-calc', onClickDuplicateCalculator);

        // Editor-Aktionen
        $('#ecp-cancel-edit').on('click', cancelEdit);
        $('#ecp-save-calculator').on('click', saveCalculator);
        $('#ecp-delete-calculator').on('click', deleteCurrentCalculator); // Für den Button im Editor
        $('#ecp-duplicate-calculator').on('click', duplicateCurrentCalculator); // Für den Button im Editor

        // Feld-Management
        $('#ecp-add-field').on('click', function () { addField(); }); // Sicherstellen, dass es ohne Argumente aufgerufen wird
        $('#ecp-add-output').on('click', function () { addOutput(); }); // Sicherstellen, dass es ohne Argumente aufgerufen wird
        $(document).on('click', '.remove-field', removeField);
        $(document).on('click', '.remove-output', removeOutput);
        // Event-Handler für Änderungen in Eingabefeldern des Editors, um ungespeicherte Änderungen zu markieren
        $(document).on('input change', '#ecp-calculator-editor input, #ecp-calculator-editor textarea, #ecp-calculator-editor select', showUnsavedChanges);


        // Vorlagen
        $(document).on('click', '.ecp-use-template', useTemplate);
        $('#ecp-create-from-template').on('click', createFromTemplate);

        // Import/Export
        $('#ecp-export-btn').on('click', onClickExportCalculator);
        $('#ecp-import-btn').on('click', onClickImportCalculator);

        // Modal-Handler
        $(document).on('click', '.ecp-modal-close', closeModal);
        $(document).on('click', '.ecp-modal', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Shortcode kopieren
        $(document).on('click', '.ecp-copy-shortcode', copyShortcode);

        // Keyboard-Shortcuts
        $(document).on('keydown', handleKeyboardShortcuts);

        // Warnung beim Verlassen der Seite, wenn ungespeicherte Änderungen vorhanden sind
        $(window).on('beforeunload', function () {
            if (unsavedChangesExist && $('#ecp-calculator-editor').is(':visible')) {
                return ecp_admin.strings.unsaved_changes_confirm || 'Sie haben ungespeicherte Änderungen. Sind Sie sicher, dass Sie die Seite verlassen möchten?';
            }
        });
    }

    /**
     * Sortable-Bereiche initialisieren
     */
    function initializeSortables() {
        $('#ecp-fields-container, #ecp-outputs-container').sortable({
            placeholder: 'ui-sortable-placeholder',
            cursor: 'move',
            handle: '.ecp-sort-handle', // Nur am Handle ziehen
            tolerance: 'pointer',
            start: function (e, ui) {
                ui.placeholder.height(ui.item.height());
                ui.item.addClass('ecp-dragging');
            },
            stop: function (e, ui) {
                ui.item.removeClass('ecp-dragging');
                showUnsavedChanges(); // Änderungen nach dem Sortieren markieren
            }
        }).disableSelection();
    }

    /**
     * Tooltips initialisieren (einfach, via title-Attribut)
     */
    function initializeTooltips() {
        // WordPress verwendet standardmäßig jQuery UI Tooltip, wenn es geladen ist.
        // Hier eine einfache Implementierung, falls nicht oder für spezifische Elemente.
        $('.ecp-tooltip-trigger').tooltip({ // Annahme: jQuery UI Tooltip ist verfügbar
            content: function () {
                return $(this).prop('title');
            }
        });
    }

    /**
     * Neuen Kalkulator erstellen
     */
    function newCalculator() {
        if (hasUnsavedChanges() && !confirm(ecp_admin.strings.unsaved_changes_confirm_new || 'Ungespeicherte Änderungen gehen verloren. Fortfahren?')) {
            return;
        }
        resetEditor();
        $('#ecp-calculators-list').hide();
        $('#ecp-calculator-editor').show().addClass('ecp-slide-up');
        $('#calculator-name').focus();
        $('#ecp-editor-title').text(ecp_admin.strings.new_calculator || 'Neuer Kalkulator');
        $('#ecp-delete-calculator').hide(); // Ausblenden für neuen Kalkulator
        $('#ecp-duplicate-calculator').hide(); // Ausblenden für neuen Kalkulator
        showUnsavedChanges(); // Markiere als geändert, da ein neuer Entwurf gestartet wird
    }

    /**
     * Kalkulator bearbeiten (Event Handler)
     */
    function onClickEditCalculator() {
        if (hasUnsavedChanges() && !confirm(ecp_admin.strings.unsaved_changes_confirm_load || 'Ungespeicherte Änderungen gehen verloren. Fortfahren?')) {
            return;
        }
        const calculatorId = $(this).data('id');
        loadCalculatorForEditing(calculatorId);
    }

    /**
     * Kalkulator zum Bearbeiten laden
     */
    function loadCalculatorForEditing(calculatorId) {
        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_get_calculator',
                nonce: ecp_admin.nonce,
                calculator_id: calculatorId
            },
            success: function (response) {
                hideLoading();
                if (response.success) {
                    populateEditor(response.data);
                    $('#ecp-calculators-list').hide();
                    $('#ecp-calculator-editor').show().addClass('ecp-slide-up');
                    clearUnsavedChanges(); // Nach erfolgreichem Laden gibt es keine ungespeicherten Änderungen
                } else {
                    showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }


    /**
     * Kalkulator löschen (Event Handler für Liste)
     */
    function onClickDeleteCalculator() {
        if (!confirm(ecp_admin.strings.confirm_delete)) {
            return;
        }

        const calculatorId = $(this).data('id');
        const $card = $(this).closest('.ecp-calculator-card');

        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_delete_calculator',
                nonce: ecp_admin.nonce,
                calculator_id: calculatorId
            },
            success: function (response) {
                hideLoading();
                if (response.success) {
                    $card.fadeOut(300, function () {
                        $(this).remove();
                        checkEmptyState(); // Prüfen, ob die Liste jetzt leer ist
                    });
                    showSuccess(ecp_admin.strings.success_deleted);
                } else {
                    showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }

    /**
     * Kalkulator duplizieren (Event Handler für Liste)
     */
    function onClickDuplicateCalculator() {
        const calculatorId = $(this).data('id');
        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_get_calculator', // Erst die Daten des zu duplizierenden Kalkulators holen
                nonce: ecp_admin.nonce,
                calculator_id: calculatorId
            },
            success: function (response) {
                if (response.success) {
                    const originalData = response.data;
                    const duplicateData = {
                        id: 0, // Wichtig: ID auf 0 setzen für neuen Eintrag
                        name: (originalData.name || ecp_admin.strings.calculator || 'Kalkulator') + ' (' + (ecp_admin.strings.copy || 'Kopie') + ')',
                        description: originalData.description || '',
                        fields: originalData.fields || [],
                        formulas: originalData.formulas || [],
                        settings: originalData.settings || {}
                    };

                    // Duplikat speichern
                    saveCalculatorData(duplicateData, function (newId) {
                        hideLoading();
                        showSuccess(ecp_admin.strings.success_duplicated || 'Kalkulator erfolgreich dupliziert!');
                        // Option 1: Liste neu laden
                        // location.reload();
                        // Option 2: Duplikat direkt zum Bearbeiten laden
                        if (newId) {
                            $('#ecp-calculator-editor').hide(); // Aktuellen Editor ausblenden, falls offen
                            $('#ecp-calculators-list').show();  // Liste kurz zeigen
                            // Füge die neue Karte dynamisch hinzu oder lade die Liste neu
                            refreshCalculatorsList(function () { // Callback, um nach dem Neuladen zu laden
                                loadCalculatorForEditing(newId);
                            });
                        } else {
                            refreshCalculatorsList(); // Fallback: Nur Liste neu laden
                        }
                    });

                } else {
                    hideLoading();
                    showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }


    /**
     * Editor zurücksetzen
     */
    function resetEditor() {
        currentCalculatorId = 0;
        fieldCounter = 0; // Zähler für eindeutige IDs zurücksetzen (obwohl Date.now() schon viel hilft)
        outputCounter = 0;

        $('#calculator-id').val('');
        $('#calculator-name').val('');
        $('#calculator-description').val('');
        $('#ecp-fields-container').empty();
        $('#ecp-outputs-container').empty();
        // Titel wird beim Öffnen/Laden gesetzt
        // Buttons werden beim Öffnen/Laden gesteuert

        clearUnsavedChanges(); // Status für ungespeicherte Änderungen zurücksetzen
    }

    /**
     * Bearbeitung abbrechen
     */
    function cancelEdit() {
        if (hasUnsavedChanges() && !confirm(ecp_admin.strings.unsaved_changes_confirm_cancel || 'Ungespeicherte Änderungen verwerfen?')) {
            return;
        }
        $('#ecp-calculator-editor').hide();
        $('#ecp-calculators-list').show();
        resetEditor();
    }

    /**
     * Kalkulator speichern
     */
    function saveCalculator() {
        const name = $('#calculator-name').val().trim();
        if (!name) {
            showError(ecp_admin.strings.error_name_required);
            $('#calculator-name').focus();
            return;
        }

        const calculatorData = {
            id: parseInt($('#calculator-id').val()) || 0, // Stelle sicher, dass es eine Zahl ist
            name: name,
            description: $('#calculator-description').val().trim(),
            fields: collectFields(),
            formulas: collectFormulas(),
            settings: {} // Placeholder für zukünftige Einstellungen, z.B. aus einem Einstellungs-Tab im Editor
        };

        saveCalculatorData(calculatorData, function (savedId) {
            currentCalculatorId = savedId;
            $('#calculator-id').val(savedId); // Wichtig für den Fall, dass es ein neuer Kalkulator war
            $('#ecp-editor-title').text((ecp_admin.strings.edit_calculator || 'Kalkulator bearbeiten') + ': ' + calculatorData.name);
            $('#ecp-delete-calculator').show();
            $('#ecp-duplicate-calculator').show();
            showSuccess(ecp_admin.strings.success_saved);
            clearUnsavedChanges();
            refreshCalculatorsList(); // Aktualisiere die Liste im Hintergrund
        });
    }


    /**
     * Kalkulator-Daten via AJAX speichern
     */
    function saveCalculatorData(data, callback) {
        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_save_calculator',
                nonce: ecp_admin.nonce,
                calculator_id: data.id, // Wird als intval($_POST['calculator_id']) in PHP erwartet
                name: data.name,
                description: data.description,
                fields: data.fields, // Wird als Array erwartet
                formulas: data.formulas, // Wird als Array erwartet
                settings: data.settings // Wird als Array erwartet
            },
            success: function (response) {
                hideLoading();
                if (response.success && response.data && response.data.id) {
                    if (callback) callback(response.data.id);
                } else {
                    showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }

    /**
     * Aktuellen Kalkulator aus dem Editor löschen
     */
    function deleteCurrentCalculator() {
        const calcId = parseInt($('#calculator-id').val());
        if (calcId > 0 && confirm(ecp_admin.strings.confirm_delete)) {
            showLoading();
            $.ajax({
                url: ecp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecp_delete_calculator',
                    nonce: ecp_admin.nonce,
                    calculator_id: calcId
                },
                success: function (response) {
                    hideLoading();
                    if (response.success) {
                        showSuccess(ecp_admin.strings.success_deleted);
                        $('#ecp-calculator-editor').hide();
                        $('#ecp-calculators-list').show();
                        resetEditor();
                        // Entferne die Karte aus der Liste
                        $(`.ecp-calculator-card .ecp-edit-calc[data-id="${calcId}"]`).closest('.ecp-calculator-card').remove();
                        checkEmptyState();
                    } else {
                        showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    hideLoading();
                    showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
                }
            });
        } else if (calcId === 0) { // Wenn es ein neuer, noch nicht gespeicherter Kalkulator ist
            cancelEdit(); // Einfach abbrechen
        }
    }


    /**
     * Aktuellen Kalkulator aus dem Editor duplizieren
     */
    function duplicateCurrentCalculator() {
        const name = $('#calculator-name').val().trim();
        if (!name && (parseInt($('#calculator-id').val()) > 0)) { // Nur Name prüfen, wenn es ein existierender Kalkulator ist
            showError(ecp_admin.strings.error_name_required);
            $('#calculator-name').focus();
            return;
        }

        const duplicateData = {
            id: 0, // Wichtig: ID auf 0 setzen für neuen Eintrag
            name: (name || ecp_admin.strings.new_calculator || 'Neuer Kalkulator') + ' (' + (ecp_admin.strings.copy || 'Kopie') + ')',
            description: $('#calculator-description').val().trim(),
            fields: collectFields(),
            formulas: collectFormulas(),
            settings: {} // Aktuell keine spezifischen Einstellungen im Editor
        };

        saveCalculatorData(duplicateData, function (newId) {
            showSuccess(ecp_admin.strings.success_duplicated || 'Kalkulator erfolgreich dupliziert!');
            if (newId) {
                // Lade das Duplikat direkt zum Bearbeiten
                loadCalculatorForEditing(newId);
                refreshCalculatorsList(); // Aktualisiere die Liste im Hintergrund
            } else {
                refreshCalculatorsList(); // Fallback
            }
        });
    }


    /**
     * Feld hinzufügen
     */
    function addField(data = {}) {
        fieldCounter++;
        const uniqueSuffix = Date.now() + '_' + fieldCounter;
        // Die Feld-ID sollte idealerweise beim Speichern serverseitig generiert werden,
        // aber für die UI brauchen wir eine temporäre oder benutzerdefinierbare.
        // Wenn `data.id` vorhanden ist (beim Laden), verwenden wir diese. Sonst eine neue generieren.
        const fieldId = data.id || 'feld_' + uniqueSuffix; // Eindeutigere Standard-ID

        // MODIFIZIERT: Schrittweite und Platzhalter
        const stepValue = data.step || 'beliebig';
        const stepPlaceholder = ecp_admin.strings.step_placeholder || 'beliebig / Zahl (z.B. 0.1)';
        const defaultFieldValue = data.default || ''; // Standardwert für das Feld

        const fieldHtml = `
            <div class="ecp-field-row" data-field-internal-id="${uniqueSuffix}">
                <span class="ecp-sort-handle dashicons dashicons-menu" title="${ecp_admin.strings.sort_field || 'Feld sortieren'}"></span>
                <button type="button" class="remove-field" title="${ecp_admin.strings.remove_field || 'Feld entfernen'}"><span class="dashicons dashicons-no-alt"></span></button>
                <table class="form-table">
                    <tr>
                        <th><label for="field-id-${uniqueSuffix}">${ecp_admin.strings.field_id || 'ID'}:</label></th>
                        <td><input type="text" id="field-id-${uniqueSuffix}" class="field-id regular-text" value="${fieldId}" placeholder="${ecp_admin.strings.field_id_placeholder || 'z.B. kreditsumme (klein, keine Leerzeichen)'}" />
                            <p class="description">${ecp_admin.strings.field_id_desc || 'Eindeutige ID für dieses Feld. Wird in Formeln verwendet.'}</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="field-label-${uniqueSuffix}">${ecp_admin.strings.field_label || 'Label'}:</label></th>
                        <td><input type="text" id="field-label-${uniqueSuffix}" class="field-label regular-text" value="${data.label || ''}" placeholder="${ecp_admin.strings.field_label_placeholder || 'z.B. Kreditsumme'}" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-type-${uniqueSuffix}">${ecp_admin.strings.field_type || 'Typ'}:</label></th>
                        <td>
                            <select id="field-type-${uniqueSuffix}" class="field-type">
                                <option value="number" ${((data.type || 'number') === 'number') ? 'selected' : ''}>${ecp_admin.strings.field_type_number || 'Zahl'}</option>
                                <option value="text" ${data.type === 'text' ? 'selected' : ''}>${ecp_admin.strings.field_type_text || 'Text'}</option>
                                <!-- Weitere Typen könnten hierhin kommen -->
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="field-default-${uniqueSuffix}">${ecp_admin.strings.field_default || 'Standardwert'}:</label></th>
                        <td><input type="text" id="field-default-${uniqueSuffix}" class="field-default regular-text" value="${defaultFieldValue}" placeholder="${ecp_admin.strings.field_default_placeholder || 'z.B. 0 oder leer'}" /></td>
                    </tr>
                    <tr class="number-field-options" style="${(data.type || 'number') === 'number' ? '' : 'display:none;'}">
                        <th><label for="field-min-${uniqueSuffix}">${ecp_admin.strings.field_min_max || 'Min/Max'}:</label></th>
                        <td>
                            <input type="number" id="field-min-${uniqueSuffix}" class="field-min small-text" value="${data.min || ''}" placeholder="${ecp_admin.strings.min || 'Min'}" />
                            <input type="number" id="field-max-${uniqueSuffix}" class="field-max small-text" value="${data.max || ''}" placeholder="${ecp_admin.strings.max || 'Max'}" />
                        </td>
                    </tr>
                    <tr class="number-field-options" style="${(data.type || 'number') === 'number' ? '' : 'display:none;'}">
                        <th><label for="field-step-${uniqueSuffix}">${ecp_admin.strings.field_step || 'Schrittweite'}:</label></th>
                        <td><input type="text" id="field-step-${uniqueSuffix}" class="field-step small-text" value="${stepValue}" placeholder="${stepPlaceholder}" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-unit-${uniqueSuffix}">${ecp_admin.strings.field_unit || 'Einheit'}:</label></th>
                        <td><input type="text" id="field-unit-${uniqueSuffix}" class="field-unit regular-text" value="${data.unit || ''}" placeholder="${ecp_admin.strings.field_unit_placeholder || 'z.B. €, %, kg'}" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-placeholder-${uniqueSuffix}">${ecp_admin.strings.field_placeholder_input || 'Platzhalter (im Feld)'}:</label></th>
                        <td><input type="text" id="field-placeholder-${uniqueSuffix}" class="field-placeholder regular-text" value="${data.placeholder || ''}" placeholder="${ecp_admin.strings.field_placeholder_input_desc || 'Optionaler Text im Eingabefeld'}" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-help-${uniqueSuffix}">${ecp_admin.strings.field_help || 'Hilfetext (Tooltip/Info)'}:</label></th>
                        <td><input type="text" id="field-help-${uniqueSuffix}" class="field-help large-text" value="${data.help || ''}" placeholder="${ecp_admin.strings.field_help_desc || 'Optionale Erklärung für Benutzer'}" /></td>
                    </tr>
                </table>
            </div>
        `;
        const $newField = $(fieldHtml);
        $('#ecp-fields-container').append($newField);
        $newField.hide().slideDown(300);

        // Event-Handler für Typ-Änderung, um Min/Max/Step ein-/auszublenden
        $newField.find('.field-type').on('change', function () {
            const $row = $(this).closest('.ecp-field-row');
            if ($(this).val() === 'number') {
                $row.find('.number-field-options').slideDown(200);
            } else {
                $row.find('.number-field-options').slideUp(200);
            }
            showUnsavedChanges();
        }).trigger('change'); // Einmal auslösen, um den initialen Zustand zu setzen

        showUnsavedChanges();
    }


    /**
     * Ausgabefeld hinzufügen
     */
    function addOutput(data = {}) {
        outputCounter++;
        const uniqueSuffix = Date.now() + '_' + outputCounter;
        // Die ID des Ausgabefeldes ist weniger kritisch für Formeln, aber gut für die UI-Verwaltung.
        const outputInternalId = data.internal_id || 'ausgabe_' + uniqueSuffix; // Für UI-Zwecke

        // MODIFIZIERT: Verbesserte Formelerklärung
        const formulaHelpText = ecp_admin.strings.formula_help || `
            Verwenden Sie Feld-IDs (z.B. <code>feld_id_1</code>, <code>kreditsumme</code>).
            <br><strong>Funktionen:</strong> <code>WENN(bedingung;dann;sonst)</code>, <code>RUNDEN(zahl;stellen)</code>, <code>MIN(a;b;...)</code>, <code>MAX(a;b;...)</code>, <code>SUMME(a;b;...)</code>, <code>MITTELWERT(a;b;...)</code>, <code>ABS(zahl)</code>, <code>WURZEL(zahl)</code>, <code>POTENZ(basis;exponent)</code>, <code>LOG(zahl;basis)</code>.
            <br><strong>Datumsfunktionen:</strong> <code>HEUTE()</code>, <code>JAHR(datum_excel)</code>, <code>MONAT(datum_excel)</code>, <code>TAG(datum_excel)</code>. (Hinweis: Datum muss im Excel-Serialformat sein oder von HEUTE() kommen).
            <br><strong>Operatoren:</strong> <code>+</code>, <code>-</code>, <code>*</code>, <code>/</code>, <code>^</code> (Potenz).
            <br><strong>Vergleiche (für WENN):</strong> <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code>, <code>=</code> (oder <code>==</code>), <code>!=</code> (oder <code>&lt;&gt;</code>).
            <br><strong>Konstanten:</strong> <code>PI</code>, <code>E</code>.
            <br><strong>Beispiel:</strong> <code>WENN(feld_umsatz > 1000; feld_umsatz * 0.1; feld_umsatz * 0.05)</code>
        `;


        const outputHtml = `
            <div class="ecp-output-row" data-output-internal-id="${outputInternalId}">
                <span class="ecp-sort-handle dashicons dashicons-menu" title="${ecp_admin.strings.sort_output || 'Ausgabefeld sortieren'}"></span>
                <button type="button" class="remove-output" title="${ecp_admin.strings.remove_output || 'Ausgabefeld entfernen'}"><span class="dashicons dashicons-no-alt"></span></button>
                <table class="form-table">
                    <tr>
                        <th><label for="output-label-${uniqueSuffix}">${ecp_admin.strings.output_label || 'Label'}:</label></th>
                        <td><input type="text" id="output-label-${uniqueSuffix}" class="output-label regular-text" value="${data.label || ''}" placeholder="${ecp_admin.strings.output_label_placeholder || 'z.B. Monatliche Rate'}" /></td>
                    </tr>
                    <tr>
                        <th><label for="output-formula-${uniqueSuffix}">${ecp_admin.strings.output_formula || 'Formel'}:</label></th>
                        <td>
                            <textarea id="output-formula-${uniqueSuffix}" class="output-formula large-text" rows="3" placeholder="${ecp_admin.strings.output_formula_placeholder || 'z.B. feld_1 * feld_2'}">${data.formula || ''}</textarea>
                            <p class="description">${formulaHelpText}</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="output-format-${uniqueSuffix}">${ecp_admin.strings.output_format || 'Format'}:</label></th>
                        <td>
                            <select id="output-format-${uniqueSuffix}" class="output-format">
                                <option value="" ${(!data.format || data.format === '') ? 'selected' : ''}>${ecp_admin.strings.format_standard || 'Standard (Automatisch)'}</option>
                                <option value="currency" ${data.format === 'currency' ? 'selected' : ''}>${ecp_admin.strings.format_currency || 'Währung'}</option>
                                <option value="percentage" ${data.format === 'percentage' ? 'selected' : ''}>${ecp_admin.strings.format_percentage || 'Prozent (%)'}</option>
                                <option value="integer" ${data.format === 'integer' ? 'selected' : ''}>${ecp_admin.strings.format_integer || 'Ganzzahl'}</option>
                                <option value="text" ${data.format === 'text' ? 'selected' : ''}>${ecp_admin.strings.format_text || 'Text (Keine Formatierung)'}</option>
                                <!-- Weitere Formate könnten hierhin kommen -->
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="output-unit-${uniqueSuffix}">${ecp_admin.strings.output_unit || 'Einheit (nach Zahl)'}:</label></th>
                        <td><input type="text" id="output-unit-${uniqueSuffix}" class="output-unit regular-text" value="${data.unit || ''}" placeholder="${ecp_admin.strings.output_unit_placeholder || 'z.B. €, Jahre (wenn nicht durch Format abgedeckt)'}" /></td>
                    </tr>
                    <tr>
                        <th><label for="output-help-${uniqueSuffix}">${ecp_admin.strings.output_help || 'Hilfetext (Tooltip/Info)'}:</label></th>
                        <td><input type="text" id="output-help-${uniqueSuffix}" class="output-help large-text" value="${data.help || ''}" placeholder="${ecp_admin.strings.output_help_desc || 'Optionale Erklärung der Berechnung für Benutzer'}" /></td>
                    </tr>
                </table>
            </div>
        `;
        const $newOutput = $(outputHtml);
        $('#ecp-outputs-container').append($newOutput);
        $newOutput.hide().slideDown(300);
        showUnsavedChanges();
    }


    /**
     * Feld entfernen
     */
    function removeField() {
        $(this).closest('.ecp-field-row').slideUp(300, function () { $(this).remove(); showUnsavedChanges(); });
    }

    /**
     * Ausgabefeld entfernen
     */
    function removeOutput() {
        $(this).closest('.ecp-output-row').slideUp(300, function () { $(this).remove(); showUnsavedChanges(); });
    }

    /**
     * Felder sammeln
     */
    function collectFields() {
        const fields = [];
        $('.ecp-field-row').each(function () {
            const $row = $(this);
            const fieldData = {
                // Die ID hier ist die benutzerdefinierte ID, nicht die interne UI-ID
                id: $row.find('.field-id').val().trim() || $row.data('field-internal-id'), // Fallback auf interne ID, falls Nutzer ID löscht
                label: $row.find('.field-label').val().trim(),
                type: $row.find('.field-type').val(),
                default: $row.find('.field-default').val().trim(),
                min: $row.find('.field-min').val().trim(),
                max: $row.find('.field-max').val().trim(),
                step: $row.find('.field-step').val().trim(),
                unit: $row.find('.field-unit').val().trim(),
                placeholder: $row.find('.field-placeholder').val().trim(),
                help: $row.find('.field-help').val().trim()
                // 'required' wurde entfernt
            };
            // Nur Felder mit einer ID und einem Label hinzufügen, um leere/unvollständige Einträge zu vermeiden
            if (fieldData.id && fieldData.label) {
                fields.push(fieldData);
            }
        });
        return fields;
    }

    /**
     * Formeln sammeln
     */
    function collectFormulas() {
        const formulas = [];
        $('.ecp-output-row').each(function () {
            const $row = $(this);
            const formulaData = {
                // Eine ID für Ausgabefelder ist nicht zwingend für die Formelverarbeitung,
                // aber könnte für spätere Features nützlich sein (z.B. Referenzierung von Ergebnissen).
                // internal_id: $row.data('output-internal-id'), // Für UI-Konsistenz
                label: $row.find('.output-label').val().trim(),
                formula: $row.find('.output-formula').val().trim(),
                format: $row.find('.output-format').val(),
                unit: $row.find('.output-unit').val().trim(),
                help: $row.find('.output-help').val().trim()
            };
            // Nur Ausgaben mit Label und Formel hinzufügen
            if (formulaData.label && formulaData.formula) {
                formulas.push(formulaData);
            }
        });
        return formulas;
    }


    /**
     * Editor mit Daten füllen
     */
    function populateEditor(data) {
        resetEditor(); // Stellt sicher, dass alles sauber ist
        currentCalculatorId = data.id; // data.id ist die ID aus der Datenbank
        $('#calculator-id').val(data.id);
        $('#calculator-name').val(data.name);
        $('#calculator-description').val(data.description || '');
        $('#ecp-editor-title').text((ecp_admin.strings.edit_calculator || 'Kalkulator bearbeiten') + ': ' + data.name);

        // Buttons für existierenden Kalkulator anzeigen
        $('#ecp-delete-calculator').show();
        $('#ecp-duplicate-calculator').show();

        if (Array.isArray(data.fields)) {
            data.fields.forEach(field => addField(field));
        }
        if (Array.isArray(data.formulas)) {
            // Stelle sicher, dass Formeln eine interne ID für die UI bekommen, falls nicht vorhanden
            data.formulas.forEach((formula, index) => {
                formula.internal_id = formula.internal_id || 'ausgabe_geladen_' + index;
                addOutput(formula);
            });
        }
        clearUnsavedChanges(); // Nach dem Laden gibt es keine "neuen" ungespeicherten Änderungen
    }


    /**
     * Vorlage verwenden (Modal öffnen)
     */
    function useTemplate() {
        if (hasUnsavedChanges() && !confirm(ecp_admin.strings.unsaved_changes_confirm_template || 'Ungespeicherte Änderungen im aktuellen Editor gehen verloren. Fortfahren?')) {
            return;
        }
        selectedTemplateId = $(this).closest('.ecp-template-card').data('template-id');
        const templateName = $(this).closest('.ecp-template-card').find('h3').text();
        $('#template-calculator-name').val(templateName); // Name im Modal vorschlagen
        $('#ecp-template-modal').fadeIn(200);
        $('#template-calculator-name').focus();
    }

    /**
     * Aus Vorlage erstellen (AJAX)
     */
    function createFromTemplate() {
        const name = $('#template-calculator-name').val().trim();
        if (!name) {
            showError(ecp_admin.strings.error_name_required);
            $('#template-calculator-name').focus();
            return;
        }
        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_create_from_template',
                nonce: ecp_admin.nonce,
                template_id: selectedTemplateId,
                name: name
            },
            success: function (response) {
                hideLoading();
                if (response.success && response.data && response.data.id) {
                    closeModal();
                    showSuccess(ecp_admin.strings.template_created);
                    // Neuen Kalkulator direkt zum Bearbeiten laden
                    loadCalculatorForEditing(response.data.id);
                    // Reiter wechseln zu "Kalkulatoren" (falls nicht schon dort)
                    // Dies ist optional, da der Editor sowieso geöffnet wird.
                    // $('.nav-tab-wrapper a[href="?page=excel-calculator-pro&tab=calculators"]').trigger('click');
                    refreshCalculatorsList(); // Liste im Hintergrund aktualisieren
                } else {
                    showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }

    /**
     * Kalkulator exportieren (Event Handler)
     */
    function onClickExportCalculator() {
        const calculatorId = $('#ecp-export-calculator').val();
        if (!calculatorId) {
            showError(ecp_admin.strings.select_calculator_to_export || 'Bitte wählen Sie einen Kalkulator zum Exportieren aus.');
            return;
        }
        // Erstelle ein temporäres Formular für den Download
        const form = $('<form></form>');
        form.attr('method', 'POST');
        form.attr('action', ecp_admin.ajax_url); // AJAX URL für den Export
        form.append($('<input>').attr('type', 'hidden').attr('name', 'action').val('ecp_export_calculator'));
        form.append($('<input>').attr('type', 'hidden').attr('name', 'nonce').val(ecp_admin.nonce)); // Nonce für Sicherheit
        form.append($('<input>').attr('type', 'hidden').attr('name', 'calculator_id').val(calculatorId));
        $('body').append(form);
        form.submit();
        form.remove(); // Formular nach dem Absenden entfernen
    }

    /**
     * Kalkulator importieren (Event Handler)
     */
    function onClickImportCalculator() {
        const fileInput = $('#ecp-import-file')[0];
        if (!fileInput.files.length) {
            showError(ecp_admin.strings.select_import_file || 'Bitte wählen Sie eine Datei zum Importieren aus.');
            return;
        }
        const file = fileInput.files[0];
        if (file.type !== 'application/json') {
            showError(ecp_admin.strings.invalid_json_file || 'Ungültige Datei. Bitte laden Sie eine JSON-Datei hoch.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'ecp_import_calculator');
        formData.append('nonce', ecp_admin.nonce);
        formData.append('import_file', file);

        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false, // Wichtig für FormData
            contentType: false, // Wichtig für FormData
            success: function (response) {
                hideLoading();
                if (response.success && response.data && response.data.id) {
                    showSuccess(ecp_admin.strings.import_successful || 'Kalkulator erfolgreich importiert!');
                    $('#ecp-import-file').val(''); // Dateiauswahl zurücksetzen
                    // Optional: Direkt zum neuen Kalkulator wechseln oder Liste aktualisieren
                    loadCalculatorForEditing(response.data.id);
                    // $('.nav-tab-wrapper a[href="?page=excel-calculator-pro&tab=calculators"]').trigger('click');
                    refreshCalculatorsList();
                } else {
                    showError(response.data.message || response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }


    /**
     * Shortcode kopieren
     */
    function copyShortcode(e) {
        e.preventDefault();
        e.stopPropagation();

        const $button = $(this);
        const shortcodeText = $button.siblings('code').text().trim(); // Hole den Text aus dem <code> Tag

        if (!navigator.clipboard) {
            // Fallback für ältere Browser (weniger sicher, kann document.execCommand verwenden)
            const textArea = document.createElement("textarea");
            textArea.value = shortcodeText;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                feedback(true);
            } catch (err) {
                feedback(false);
                showError('Fehler beim Kopieren (Fallback): ' + err);
            }
            document.body.removeChild(textArea);
            return;
        }

        navigator.clipboard.writeText(shortcodeText).then(function () {
            feedback(true);
        }).catch(function (err) {
            feedback(false);
            showError('Fehler beim Kopieren: ' + err);
        });

        function feedback(success) {
            const originalHtml = $button.html();
            const icon = success ? '✅' : '❌';
            const message = success ? (ecp_admin.strings.copied || 'Kopiert!') : (ecp_admin.strings.copy_failed || 'Fehler!');
            $button.html(icon + ' ' + message).addClass(success ? 'ecp-copied' : 'ecp-copy-failed');
            setTimeout(function () {
                $button.html(originalHtml).removeClass('ecp-copied ecp-copy-failed');
            }, 1500);
        }
    }


    /**
     * Modal schliessen
     */
    function closeModal() {
        $('.ecp-modal').fadeOut(200);
    }

    /**
     * Keyboard-Shortcuts
     */
    function handleKeyboardShortcuts(e) {
        // Ctrl+S oder Cmd+S zum Speichern im Editor
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            if ($('#ecp-calculator-editor').is(':visible')) {
                e.preventDefault();
                saveCalculator();
            }
        }
        // Escape-Taste
        if (e.key === 'Escape') {
            if ($('.ecp-modal').is(':visible')) {
                closeModal();
            } else if ($('#ecp-calculator-editor').is(':visible')) {
                cancelEdit(); // Nur abbrechen, wenn der Editor sichtbar ist
            }
        }
    }


    /**
     * Ungespeicherte Änderungen markieren
     */
    function showUnsavedChanges() {
        if ($('#ecp-calculator-editor').is(':visible')) { // Nur wenn Editor offen ist
            unsavedChangesExist = true;
            $('#ecp-save-calculator').addClass('button-primary-highlight'); // Visueller Hinweis
        }
    }

    /**
     * Markierung für ungespeicherte Änderungen entfernen
     */
    function clearUnsavedChanges() {
        unsavedChangesExist = false;
        $('#ecp-save-calculator').removeClass('button-primary-highlight');
    }

    /**
     * Prüfen, ob ungespeicherte Änderungen vorhanden sind
     */
    function hasUnsavedChanges() {
        return unsavedChangesExist && $('#ecp-calculator-editor').is(':visible');
    }

    /**
     * Empty State für Kalkulatoren-Liste prüfen und ggf. anzeigen
     */
    function checkEmptyState() {
        const $listContainer = $('#ecp-calculators-list');
        if ($listContainer.find('.ecp-calculator-card').length === 0) {
            if ($listContainer.find('.ecp-empty-state').length === 0) { // Nur hinzufügen, wenn nicht schon da
                const emptyHtml = `
                    <div class="ecp-empty-state">
                        <span class="dashicons dashicons-calculator ecp-empty-icon"></span>
                        <h3>${ecp_admin.strings.no_calculators_yet_title || 'Noch keine Kalkulatoren'}</h3>
                        <p>${ecp_admin.strings.no_calculators_yet || 'Sie haben noch keine Kalkulatoren erstellt.'}</p>
                        <p>${ecp_admin.strings.create_first_calculator || 'Klicken Sie auf "Neuer Kalkulator" oder verwenden Sie eine Vorlage, um zu beginnen.'}</p>
                    </div>`;
                $listContainer.html(emptyHtml);
            }
        } else {
            $listContainer.find('.ecp-empty-state').remove(); // Entferne Empty State, wenn Karten vorhanden sind
        }
    }

    /**
     * Kalkulatoren-Liste neu laden/aktualisieren
     */
    function refreshCalculatorsList(callback) {
        // Diese Funktion könnte AJAX verwenden, um die Liste neu zu laden,
        // oder einfach die Seite neu laden, wenn das einfacher ist.
        // Für eine bessere UX wäre ein AJAX-Refresh ideal.
        // Beispiel für einen einfachen Reload:
        // location.reload();
        // Für dieses Beispiel simulieren wir einen AJAX-Refresh (vereinfacht):
        showLoading();
        // Annahme: Es gibt eine PHP-Funktion, die nur die Liste rendert
        // und via AJAX aufgerufen werden kann. Hier nicht implementiert, daher Fallback.
        // Stattdessen laden wir die ganze Seite neu, wenn kein Callback da ist,
        // oder rufen den Callback direkt auf, wenn einer da ist (für Duplizieren).
        if (typeof callback === 'function') {
            // Wenn ein Callback da ist (z.B. nach Duplizieren, um das neue Element zu laden),
            // dann nicht die ganze Seite neu laden, sondern den Callback ausführen.
            // Die Liste selbst wird durch das Laden des neuen Items ggf. aktualisiert.
            // Idealerweise würde man hier die Liste per AJAX neu holen und dann den Callback.
            // Für jetzt:
            hideLoading(); // Loading ausblenden, da kein echter Refresh
            if (callback) callback();
        } else {
            // Vollständiger Seiten-Reload als Fallback, wenn kein spezifischer Callback.
            // Besser wäre, die Liste per AJAX zu aktualisieren.
            // $.ajax({ ... success: function(html) { $('#ecp-calculators-list').html(html); hideLoading(); if(callback) callback(); } });
            location.reload();
        }
    }


    /**
     * Loading-Spinner anzeigen
     */
    function showLoading() {
        let $overlay = $('#ecp-loading-overlay');
        if (!$overlay.length) {
            $overlay = $(`
                <div id="ecp-loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.7); z-index: 99999; display: flex; align-items: center; justify-content: center;">
                    <div class="ecp-loading-spinner" style="text-align: center;">
                        <span class="spinner is-active" style="width: 20px; height: 20px; margin: 0 auto 10px; background-image: url(${ecp_admin.wp_spinner_url || '/wp-admin/images/spinner.gif'}); background-size: cover;"></span>
                        <p style="margin:0; font-size: 14px; color: #333;">${ecp_admin.strings.loading || 'Lädt...'}</p>
                    </div>
                </div>
            `).appendTo('body');
        }
        $overlay.fadeIn(100);
    }

    /**
     * Loading-Spinner ausblenden
     */
    function hideLoading() {
        $('#ecp-loading-overlay').fadeOut(200, function () { $(this).remove(); });
    }

    /**
     * Erfolgsmeldung anzeigen
     */
    function showSuccess(message) {
        showNotice(message, 'success');
    }

    /**
     * Fehlermeldung anzeigen
     */
    function showError(message) {
        // Wenn message ein Objekt ist (z.B. von AJAX error), versuche data.message zu extrahieren
        if (typeof message === 'object' && message !== null && message.message) {
            message = message.message;
        } else if (typeof message === 'object' && message !== null) {
            message = JSON.stringify(message); // Fallback, wenn es ein unbekanntes Objekt ist
        }
        showNotice(message, 'error');
    }

    /**
     * Info-Meldung anzeigen
     */
    function showInfo(message) {
        showNotice(message, 'info');
    }


    /**
     * WordPress-Admin-Benachrichtigung anzeigen
     */
    let noticeTimeout;
    function showNotice(message, type = 'info') {
        clearTimeout(noticeTimeout);
        $('.ecp-admin-notice-wrapper .notice').remove(); // Alte Nachrichten im Wrapper entfernen

        let $noticeWrapper = $('.ecp-admin-notice-wrapper');
        if (!$noticeWrapper.length) {
            // Platziere den Wrapper nach dem h1 oder dem .wp-heading-inline
            let $heading = $('.wrap > h1').first();
            if (!$heading.length) $heading = $('.wrap .wp-heading-inline').first();
            if (!$heading.length) $heading = $('.wrap').first(); // Fallback

            $noticeWrapper = $('<div class="ecp-admin-notice-wrapper" style="margin-top: 15px;"></div>').insertAfter($heading);
        }


        const noticeClass = `notice-${type} notice is-dismissible`; // WordPress-Standardklassen
        // Stelle sicher, dass die Nachricht HTML escaped wird, um XSS zu vermeiden, falls sie dynamisch ist
        const escapedMessage = $('<div>').text(message).html();

        const noticeHtml = `
            <div class="${noticeClass}" style="display:none; margin-bottom:15px;">
                <p>${escapedMessage}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">${ecp_admin.strings.dismiss_notice || 'Diese Meldung ausblenden.'}</span>
                </button>
            </div>`;

        const $notice = $(noticeHtml).appendTo($noticeWrapper);
        $notice.fadeIn(300);

        $notice.on('click', '.notice-dismiss', function (e) {
            e.preventDefault();
            $(this).closest('.notice').fadeOut(300, function () { $(this).remove(); });
        });

        // Automatisch ausblenden für success und info
        if (type === 'success' || type === 'info') {
            noticeTimeout = setTimeout(function () {
                $notice.fadeOut(300, function () { $(this).remove(); });
            }, 5000); // Nach 5 Sekunden ausblenden
        }
    }


})(jQuery);
