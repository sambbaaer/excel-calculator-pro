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

        // Fade-in Animation für Cards
        $('.ecp-calculator-card, .ecp-template-card').addClass('ecp-fade-in');
    }

    /**
     * Event-Handler binden
     */
    function bindEvents() {
        // Kalkulator-Management
        $('#ecp-new-calculator').on('click', newCalculator);
        $(document).on('click', '.ecp-edit-calc', onClickEditCalculator); // Renamed to avoid conflict with function name
        $(document).on('click', '.ecp-delete-calc', onClickDeleteCalculator); // Renamed
        $(document).on('click', '.ecp-duplicate-calc', onClickDuplicateCalculator); // Renamed

        // Editor-Aktionen
        $('#ecp-cancel-edit').on('click', cancelEdit);
        $('#ecp-save-calculator').on('click', saveCalculator);
        $('#ecp-delete-calculator').on('click', deleteCurrentCalculator);
        $('#ecp-duplicate-calculator').on('click', duplicateCurrentCalculator);
        // $('#ecp-preview-calculator').on('click', previewCalculator); // Preview button removed

        // Feld-Management
        $('#ecp-add-field').on('click', addField);
        $('#ecp-add-output').on('click', addOutput);
        $(document).on('click', '.remove-field', removeField);
        $(document).on('click', '.remove-output', removeOutput);

        // Vorlagen
        $(document).on('click', '.ecp-use-template', useTemplate);
        $('#ecp-create-from-template').on('click', createFromTemplate);

        // Import/Export
        $('#ecp-export-btn').on('click', onClickExportCalculator); // Renamed
        $('#ecp-import-btn').on('click', onClickImportCalculator); // Renamed

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

        // Auto-Save für Entwürfe (optional, kann viel AJAX erzeugen)
        // setInterval(autoSave, 30000); // Alle 30 Sekunden
    }

    /**
     * Sortable-Bereiche initialisieren
     */
    function initializeSortables() {
        $('#ecp-fields-container, #ecp-outputs-container').sortable({
            placeholder: 'ui-sortable-placeholder',
            cursor: 'move',
            tolerance: 'pointer',
            start: function (e, ui) {
                ui.placeholder.height(ui.item.height());
            },
            update: function () {
                showUnsavedChanges();
            }
        }).disableSelection();
    }

    /**
     * Tooltips initialisieren
     */
    function initializeTooltips() {
        // Simple title attribute tooltips are fine for admin usually.
        // If more complex tooltips are needed, a library like Tippy.js could be integrated.
    }

    /**
     * Neuen Kalkulator erstellen
     */
    function newCalculator() {
        resetEditor();
        $('#ecp-calculators-list').hide();
        $('#ecp-calculator-editor').show().addClass('ecp-slide-up');
        $('#calculator-name').focus();
    }

    /**
     * Kalkulator bearbeiten (Event Handler)
     */
    function onClickEditCalculator() {
        const calculatorId = $(this).data('id');
        loadCalculatorForEditing(calculatorId);
    }

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
                } else {
                    showError(response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }


    /**
     * Kalkulator löschen (Event Handler)
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
                        checkEmptyState();
                    });
                    showSuccess(ecp_admin.strings.success_deleted);
                } else {
                    showError(response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }

    /**
     * Kalkulator duplizieren (Event Handler)
     */
    function onClickDuplicateCalculator() {
        const calculatorId = $(this).data('id');
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
                if (response.success) {
                    const data = response.data;
                    data.name = (data.name || 'Kalkulator') + ' (Kopie)';
                    data.id = 0; // Neue ID für Duplikat

                    saveCalculatorData(data, function (newId) { // Pass newId to callback
                        showSuccess('Kalkulator dupliziert!');
                        // Option 1: Reload list
                        // location.reload();
                        // Option 2: Load the new calculator for editing
                        if (newId) {
                            $('#ecp-calculator-editor').hide(); // Hide current editor if open
                            $('#ecp-calculators-list').show(); // Show list briefly
                            loadCalculatorForEditing(newId); // Then load the new one
                        } else {
                            location.reload(); // Fallback
                        }
                    });
                } else {
                    hideLoading();
                    showError(response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }


    /**
     * Editor zurücksetzen
     */
    function resetEditor() {
        currentCalculatorId = 0;
        fieldCounter = 0;
        outputCounter = 0;

        $('#calculator-id').val('');
        $('#calculator-name').val('');
        $('#calculator-description').val('');
        $('#ecp-fields-container').empty();
        $('#ecp-outputs-container').empty();
        $('#ecp-editor-title').text(ecp_admin.strings.new_calculator || 'Neuer Kalkulator');
        $('#ecp-delete-calculator').hide();
        $('#ecp-duplicate-calculator').hide();

        clearUnsavedChanges();
    }

    /**
     * Bearbeitung abbrechen
     */
    function cancelEdit() {
        if (hasUnsavedChanges() && !confirm(ecp_admin.strings.unsaved_changes_confirm || 'Ungespeicherte Änderungen verwerfen?')) {
            return;
        }
        $('#ecp-calculator-editor').hide();
        $('#ecp-calculators-list').show();
        resetEditor(); // Stellt sicher, dass der Editor sauber ist für das nächste Mal
        clearUnsavedChanges();
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
            id: currentCalculatorId,
            name: name,
            description: $('#calculator-description').val().trim(),
            fields: collectFields(),
            formulas: collectFormulas(),
            settings: {} // Placeholder für zukünftige Einstellungen
        };

        saveCalculatorData(calculatorData, function (savedId) {
            showSuccess(ecp_admin.strings.success_saved);
            // Aktualisiere die Liste oder lade den Editor neu, anstatt die Seite komplett neu zu laden
            currentCalculatorId = savedId; // Wichtig für den Fall, dass es ein neuer Kalkulator war
            $('#calculator-id').val(savedId);
            $('#ecp-editor-title').text((ecp_admin.strings.edit_calculator || 'Kalkulator bearbeiten') + ': ' + name);
            $('#ecp-delete-calculator').show();
            $('#ecp-duplicate-calculator').show();

            // Optional: Liste im Hintergrund aktualisieren, falls sie sichtbar würde
            // refreshCalculatorsList();
            clearUnsavedChanges();
            // Anstatt reload:
            // Wenn es ein neuer Kalkulator war und der Nutzer auf der Liste war, sollte die Liste aktualisiert werden.
            // Wenn der Nutzer im Editor bleibt, ist es okay.
            // Für Einfachheit, wenn ein Reload gewünscht ist: setTimeout(() => location.reload(), 1000);
        });
    }


    /**
     * Kalkulator-Daten speichern
     */
    function saveCalculatorData(data, callback) {
        showLoading();
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_save_calculator',
                nonce: ecp_admin.nonce,
                calculator_id: data.id,
                name: data.name,
                description: data.description,
                fields: data.fields,
                formulas: data.formulas,
                settings: data.settings
            },
            success: function (response) {
                hideLoading();
                if (response.success && response.data && response.data.id) {
                    clearUnsavedChanges();
                    if (callback) callback(response.data.id); // Pass ID to callback
                } else {
                    showError(response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                hideLoading();
                showError(ecp_admin.strings.error_occurred + (errorThrown ? ': ' + errorThrown : ''));
            }
        });
    }

    /**
     * Aktuellen Kalkulator löschen
     */
    function deleteCurrentCalculator() {
        if (currentCalculatorId > 0 && confirm(ecp_admin.strings.confirm_delete)) {
            showLoading();
            $.ajax({
                url: ecp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecp_delete_calculator',
                    nonce: ecp_admin.nonce,
                    calculator_id: currentCalculatorId
                },
                success: function (response) {
                    hideLoading();
                    if (response.success) {
                        showSuccess(ecp_admin.strings.success_deleted);
                        $('#ecp-calculator-editor').hide();
                        $('#ecp-calculators-list').show();
                        resetEditor(); // Editor zurücksetzen
                        // Hier die Liste aktualisieren, anstatt die ganze Seite neu zu laden
                        $(`.ecp-calculator-card .ecp-edit-calc[data-id="${currentCalculatorId}"]`).closest('.ecp-calculator-card').remove();
                        checkEmptyState();
                    } else {
                        showError(response.data || ecp_admin.strings.error_occurred);
                    }
                },
                error: function () {
                    hideLoading();
                    showError(ecp_admin.strings.error_occurred);
                }
            });
        }
    }


    /**
     * Aktuellen Kalkulator duplizieren
     */
    function duplicateCurrentCalculator() {
        const name = $('#calculator-name').val().trim();
        if (!name) {
            showError(ecp_admin.strings.error_name_required);
            $('#calculator-name').focus();
            return;
        }

        const duplicateData = {
            id: 0, // Wichtig: ID auf 0 setzen für neuen Eintrag
            name: name + ' (Kopie)',
            description: $('#calculator-description').val().trim(),
            fields: collectFields(),
            formulas: collectFormulas(),
            settings: {}
        };

        saveCalculatorData(duplicateData, function (newId) {
            showSuccess('Kalkulator dupliziert!');
            if (newId) {
                $('#ecp-calculator-editor').hide();
                $('#ecp-calculators-list').show();
                loadCalculatorForEditing(newId); // Duplikat zum Bearbeiten laden
            } else {
                location.reload(); // Fallback
            }
        });
    }

    // Preview-Funktion wurde entfernt, da der Button entfernt wurde.
    // function previewCalculator() { ... }

    /**
     * Feld hinzufügen
     */
    function addField(data = {}) {
        fieldCounter++;
        // Stelle sicher, dass die ID einzigartig ist, falls keine übergeben wird
        const fieldId = data.id || 'field_' + Date.now() + '_' + fieldCounter;


        const fieldHtml = `
            <div class="ecp-field-row" data-field-id="${fieldId}">
                <span class="ecp-sort-handle dashicons dashicons-menu"></span>
                <button type="button" class="remove-field button-link-delete" title="Feld entfernen"><span class="dashicons dashicons-no-alt"></span></button>
                <table class="form-table">
                    <tr>
                        <th><label for="field-id-${fieldId}">ID:</label></th>
                        <td><input type="text" id="field-id-${fieldId}" class="field-id regular-text" value="${fieldId}" readonly /></td>
                    </tr>
                    <tr>
                        <th><label for="field-label-${fieldId}">Label:</label></th>
                        <td><input type="text" id="field-label-${fieldId}" class="field-label regular-text" value="${data.label || ''}" placeholder="z.B. Kreditsumme" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-type-${fieldId}">Typ:</label></th>
                        <td>
                            <select id="field-type-${fieldId}" class="field-type">
                                <option value="number" ${((data.type || 'number') === 'number') ? 'selected' : ''}>Zahl</option>
                                <option value="text" ${data.type === 'text' ? 'selected' : ''}>Text</option>
                                <option value="email" ${data.type === 'email' ? 'selected' : ''}>E-Mail</option>
                                <option value="tel" ${data.type === 'tel' ? 'selected' : ''}>Telefon</option>
                                </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="field-default-${fieldId}">Standardwert:</label></th>
                        <td><input type="text" id="field-default-${fieldId}" class="field-default regular-text" value="${data.default || ''}" placeholder="0" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-min-${fieldId}">Min/Max:</label></th>
                        <td>
                            <input type="number" id="field-min-${fieldId}" class="field-min small-text" value="${data.min || ''}" placeholder="Min" />
                            <input type="number" id="field-max-${fieldId}" class="field-max small-text" value="${data.max || ''}" placeholder="Max" />
                        </td>
                    </tr>
                     <tr>
                        <th><label for="field-step-${fieldId}">Schrittweite:</label></th>
                        <td><input type="text" id="field-step-${fieldId}" class="field-step small-text" value="${data.step || 'any'}" placeholder="any oder Zahl" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-unit-${fieldId}">Einheit:</label></th>
                        <td><input type="text" id="field-unit-${fieldId}" class="field-unit regular-text" value="${data.unit || ''}" placeholder="z.B. €, %, kg" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-placeholder-${fieldId}">Platzhalter:</label></th>
                        <td><input type="text" id="field-placeholder-${fieldId}" class="field-placeholder regular-text" value="${data.placeholder || ''}" placeholder="Optional" /></td>
                    </tr>
                    <tr>
                        <th><label for="field-help-${fieldId}">Hilfetext:</label></th>
                        <td><input type="text" id="field-help-${fieldId}" class="field-help large-text" value="${data.help || ''}" placeholder="Optionaler Hilfetext" /></td>
                    </tr>
                    <?php /* MODIFIZIERT: "Erforderlich" Tabellenzeile entfernt
                    <tr>
                        <th>Erforderlich:</th>
                        <td><input type="checkbox" class="field-required" ${(data.required === true || data.required === 'true' || data.required === '1') ? 'checked' : ''} /></td>
                    </tr>
                    */ ?>
                </table>
            </div>
        `;

        $('#ecp-fields-container').append(fieldHtml).find('.ecp-field-row:last-child').hide().slideDown(300);
        showUnsavedChanges();
    }

    /**
     * Ausgabefeld hinzufügen
     */
    function addOutput(data = {}) {
        outputCounter++;
        const outputId = data.id || 'output_' + Date.now() + '_' + outputCounter; // Eindeutige ID für den Fall

        const outputHtml = `
            <div class="ecp-output-row">
                <button type="button" class="remove-output" title="Ausgabefeld entfernen"></button> {/* Icon wird durch CSS hinzugefügt */}
                <table class="form-table">
                    <tr>
                        <th>Label:</th>
                        <td><input type="text" class="output-label regular-text" value="${data.label || ''}" placeholder="z.B. Monatliche Rate" /></td>
                    </tr>
                    <tr>
                        <th>Formel:</th>
                        <td>
                            <textarea class="output-formula large-text" rows="3" placeholder="z.B. field_1 * (field_2 / 100) + field_3">${data.formula || ''}</textarea>
                            <div class="ecp-formula-help">
                                <h4>Formel-Assistent</h4>
                                <p>Verwenden Sie Feld-IDs (z.B. <code>field_1</code>, <code>field_2</code>) und folgende Funktionen/Operatoren:</p>
                                
                                <h5>Grundoperatoren:</h5>
                                <ul>
                                    <li>Addition: <code>+</code> (z.B. <code>field_1 + field_2</code>)</li>
                                    <li>Subtraktion: <code>-</code> (z.B. <code>field_1 - field_2</code>)</li>
                                    <li>Multiplikation: <code>*</code> (z.B. <code>field_1 * field_2</code>)</li>
                                    <li>Division: <code>/</code> (z.B. <code>field_1 / field_2</code>)</li>
                                    <li>Potenz: <code>^</code> oder <code>POW(basis, exponent)</code> (z.B. <code>field_1 ^ 2</code>)</li>
                                </ul>

                                <h5>Vergleichsoperatoren (für WENN-Funktion):</h5>
                                <ul>
                                    <li>Größer als: <code>&gt;</code></li>
                                    <li>Größer gleich: <code>&gt;=</code></li>
                                    <li>Kleiner als: <code>&lt;</code></li>
                                    <li>Kleiner gleich: <code>&lt;=</code></li>
                                    <li>Gleich: <code>=</code> oder <code>==</code></li>
                                    <li>Ungleich: <code>!=</code> oder <code>&lt;&gt;</code></li>
                                </ul>
                                
                                <h5>Excel-ähnliche Funktionen:</h5>
                                <ul>
                                    <li><code>WENN(bedingung, wert_wenn_wahr, wert_wenn_falsch)</code>: Bedingte Logik. Beispiel: <code>WENN(field_1 &gt; 100, field_1 * 0.1, field_1 * 0.05)</code></li>
                                    <li><code>SUMME(wert1, wert2, ...)</code>: Summiert Werte. Beispiel: <code>SUMME(field_1, field_2, 50)</code></li>
                                    <li><code>MITTELWERT(wert1, wert2, ...)</code>: Berechnet den Durchschnitt.</li>
                                    <li><code>MIN(wert1, wert2, ...)</code>: Kleinster Wert.</li>
                                    <li><code>MAX(wert1, wert2, ...)</code>: Größter Wert.</li>
                                    <li><code>RUNDEN(zahl, dezimalstellen)</code>: Rundet eine Zahl. Beispiel: <code>RUNDEN(field_1 / 3, 2)</code></li>
                                    <li><code>ABS(zahl)</code>: Absolutwert.</li>
                                    <li><code>WURZEL(zahl)</code> oder <code>SQRT(zahl)</code>: Quadratwurzel.</li>
                                    <li><code>POTENZ(basis, exponent)</code> oder <code>POW(basis, exponent)</code>: Potenzierung.</li>
                                    <li><code>LOG(zahl, basis)</code>: Logarithmus (Standardbasis e). Beispiel: <code>LOG(field_1)</code> oder <code>LOG(field_1, 10)</code></li>
                                </ul>

                                <h5>Datumsfunktionen (geben numerische Werte zurück):</h5>
                                <ul>
                                    <li><code>HEUTE()</code>: Aktuelles Datum als YYYYMMDD.</li>
                                    <li><code>JAHR(datum_oder_heute)</code>: Jahr aus Datum (z.B. <code>JAHR(HEUTE())</code>).</li>
                                    <li><code>MONAT(datum_oder_heute)</code>: Monat aus Datum.</li>
                                    <li><code>TAG(datum_oder_heute)</code>: Tag aus Datum.</li>
                                </ul>

                                <h5>Weitere Funktionen:</h5>
                                <ul>
                                    <li><code>OBERGRENZE(zahl)</code> oder <code>CEILING(zahl)</code>: Aufrunden zur nächsten Ganzzahl.</li>
                                    <li><code>UNTERGRENZE(zahl)</code> oder <code>FLOOR(zahl)</code>: Abrunden zur nächsten Ganzzahl.</li>
                                    <li><code>ZUFALLSZAHL()</code> oder <code>RAND()</code>: Zufallszahl zwischen 0 und 1.</li>
                                </ul>
                                
                                <h5>Konstanten:</h5>
                                <ul>
                                    <li><code>PI</code> (Kreiszahl Pi, ca. 3.14159)</li>
                                    <li><code>E</code> (Eulersche Zahl, ca. 2.71828)</li>
                                    <li><code>PHI</code> (Goldener Schnitt, ca. 1.61803)</li>
                                </ul>

                                <div class="ecp-formula-example">
                                    <strong>Beispiel für eine verschachtelte Formel:</strong>
                                    <code>WENN(field_1 &gt; SUMME(field_2, field_3), RUNDEN(field_1 * PI, 2), MAX(field_2, field_3) * 0.5)</code>
                                    <p>Diese Formel prüft, ob <code>field_1</code> größer ist als die Summe von <code>field_2</code> und <code>field_3</code>. Wenn ja, wird <code>field_1</code> mit PI multipliziert und auf 2 Dezimalstellen gerundet. Andernfalls wird der größere Wert von <code>field_2</code> und <code>field_3</code> mit 0.5 multipliziert.</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    {/* ... (restliche Tabellenzeilen für Format, Einheit, Hilfetext) ... */}
                </table>
            </div>
        `;
        $('#ecp-outputs-container').append(outputHtml).find('.ecp-output-row:last-child').hide().slideDown(300);
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
                id: $row.find('.field-id').val().trim() || $row.data('field-id'), // Fallback auf data-Attribut
                label: $row.find('.field-label').val().trim(),
                type: $row.find('.field-type').val(),
                default: $row.find('.field-default').val().trim(),
                min: $row.find('.field-min').val().trim(),
                max: $row.find('.field-max').val().trim(),
                step: $row.find('.field-step').val().trim(),
                unit: $row.find('.field-unit').val().trim(),
                placeholder: $row.find('.field-placeholder').val().trim(),
                help: $row.find('.field-help').val().trim()
                // MODIFIZIERT: "required" entfernt
                // required: $row.find('.field-required').is(':checked')
            };
            if (fieldData.label) { // Nur Felder mit Label hinzufügen
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
                label: $row.find('.output-label').val().trim(),
                formula: $row.find('.output-formula').val().trim(),
                format: $row.find('.output-format').val(),
                unit: $row.find('.output-unit').val().trim(),
                help: $row.find('.output-help').val().trim()
            };
            if (formulaData.label) { // Nur Ausgaben mit Label hinzufügen
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
        currentCalculatorId = data.id;
        $('#calculator-id').val(data.id);
        $('#calculator-name').val(data.name);
        $('#calculator-description').val(data.description || '');
        $('#ecp-editor-title').text((ecp_admin.strings.edit_calculator || 'Kalkulator bearbeiten') + ': ' + data.name);
        $('#ecp-delete-calculator').show();
        $('#ecp-duplicate-calculator').show();

        if (Array.isArray(data.fields)) {
            data.fields.forEach(field => addField(field));
        }
        if (Array.isArray(data.formulas)) {
            data.formulas.forEach(formula => addOutput(formula));
        }
        clearUnsavedChanges();
    }


    /**
     * Vorlage verwenden
     */
    function useTemplate() {
        selectedTemplateId = $(this).closest('.ecp-template-card').data('template-id');
        const templateName = $(this).closest('.ecp-template-card').find('h3').text();
        $('#template-calculator-name').val(templateName); // Name im Modal vorschlagen
        $('#ecp-template-modal').fadeIn(200);
    }

    /**
     * Aus Vorlage erstellen
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
                    // Reiter wechseln zu "Kalkulatoren"
                    $('.nav-tab-wrapper a[href="?page=excel-calculator-pro&tab=calculators"]').trigger('click');

                } else {
                    showError(response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
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
        form.attr('action', ecp_admin.ajax_url);
        form.append($('<input>').attr('type', 'hidden').attr('name', 'action').val('ecp_export_calculator'));
        form.append($('<input>').attr('type', 'hidden').attr('name', 'nonce').val(ecp_admin.nonce));
        form.append($('<input>').attr('type', 'hidden').attr('name', 'calculator_id').val(calculatorId));
        $('body').append(form);
        form.submit();
        form.remove();
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
            processData: false,
            contentType: false,
            success: function (response) {
                hideLoading();
                if (response.success && response.data && response.data.id) {
                    showSuccess(ecp_admin.strings.import_successful || 'Kalkulator erfolgreich importiert!');
                    $('#ecp-import-file').val(''); // Dateiauswahl zurücksetzen
                    // Optional: Direkt zum neuen Kalkulator wechseln
                    loadCalculatorForEditing(response.data.id);
                    $('.nav-tab-wrapper a[href="?page=excel-calculator-pro&tab=calculators"]').trigger('click');
                } else {
                    showError(response.data || ecp_admin.strings.error_occurred);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }


    /**
     * Shortcode kopieren
     */
    function copyShortcode(e) {
        e.preventDefault();
        e.stopPropagation(); // Verhindert, dass das Klicken auf den Button das Bearbeiten auslöst, falls der Button im Card-Header ist

        const $button = $(this);
        const shortcode = $button.data('shortcode');

        navigator.clipboard.writeText(shortcode).then(function () {
            const originalText = $button.html();
            $button.html('Kopiert!').addClass('ecp-copied');
            setTimeout(function () {
                $button.html(originalText).removeClass('ecp-copied');
            }, 1500);
        }).catch(function (err) {
            showError('Fehler beim Kopieren: ' + err);
        });
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
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { // Ctrl+S oder Cmd+S
            if ($('#ecp-calculator-editor').is(':visible')) {
                e.preventDefault();
                saveCalculator();
            }
        }
        if (e.key === 'Escape') { // Escape-Taste
            if ($('.ecp-modal').is(':visible')) {
                closeModal();
            } else if ($('#ecp-calculator-editor').is(':visible')) {
                cancelEdit();
            }
        }
    }

    /**
     * Auto-Save (Beispiel, muss robust implementiert werden)
     */
    // function autoSave() { ... } // Auskommentiert für jetzt


    /**
     * Ungespeicherte Änderungen anzeigen
     */
    let unsavedChangesExist = false;
    function showUnsavedChanges() {
        unsavedChangesExist = true;
        $('#ecp-save-calculator').addClass('button-primary-highlight'); // Visueller Hinweis
        // Warnung beim Verlassen der Seite
        $(window).on('beforeunload.ecpUnsaved', function () {
            return ecp_admin.strings.unsaved_changes_confirm || 'Sie haben ungespeicherte Änderungen. Sind Sie sicher, dass Sie die Seite verlassen möchten?';
        });
    }

    /**
     * Ungespeicherte Änderungen löschen
     */
    function clearUnsavedChanges() {
        unsavedChangesExist = false;
        $('#ecp-save-calculator').removeClass('button-primary-highlight');
        $(window).off('beforeunload.ecpUnsaved');
    }

    /**
     * Ungespeicherte Änderungen prüfen
     */
    function hasUnsavedChanges() {
        return unsavedChangesExist;
    }

    /**
     * Empty State prüfen
     */
    function checkEmptyState() {
        if ($('#ecp-calculators-list .ecp-calculator-card').length === 0) {
            const emptyHtml = `
                <div class="ecp-empty-state">
                    <p>${ecp_admin.strings.no_calculators_yet || 'Noch keine Kalkulatoren erstellt.'}</p>
                    <p>${ecp_admin.strings.create_first_calculator || 'Erstellen Sie Ihren ersten Kalkulator oder verwenden Sie eine Vorlage.'}</p>
                </div>`;
            $('#ecp-calculators-list').html(emptyHtml);
        } else {
            $('#ecp-calculators-list .ecp-empty-state').remove();
        }
    }


    /**
     * Loading anzeigen
     */
    function showLoading() {
        let $overlay = $('#ecp-loading-overlay');
        if (!$overlay.length) {
            $overlay = $(`
                <div id="ecp-loading-overlay">
                    <div class="ecp-loading-spinner">
                        <span class="spinner is-active"></span>
                        <p>${ecp_admin.strings.loading}</p>
                    </div>
                </div>
            `).appendTo('body');
        }
        $overlay.fadeIn(100);
    }

    /**
     * Loading ausblenden
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
        showNotice(message, 'error');
    }

    /**
     * Info-Meldung anzeigen
     */
    function showInfo(message) {
        showNotice(message, 'info');
    }


    /**
     * Benachrichtigung anzeigen
     */
    let noticeTimeout;
    function showNotice(message, type = 'info') {
        clearTimeout(noticeTimeout);
        $('#ecp-admin-notice').remove(); // Alte Nachricht entfernen

        const noticeClass = `notice-${type}`; // WordPress-Standardklassen
        const noticeHtml = `
            <div id="ecp-admin-notice" class="notice ${noticeClass} is-dismissible" style="display:none;">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">${ecp_admin.strings.dismiss_notice || 'Diese Meldung ausblenden.'}</span>
                </button>
            </div>`;

        $('.wrap > h1').first().after(noticeHtml);
        const $notice = $('#ecp-admin-notice');
        $notice.fadeIn(300);

        $notice.on('click', '.notice-dismiss', function () {
            $notice.fadeOut(300, function () { $(this).remove(); });
        });

        if (type === 'success' || type === 'info') {
            noticeTimeout = setTimeout(function () {
                $notice.fadeOut(300, function () { $(this).remove(); });
            }, 4000); // Nach 4 Sekunden ausblenden
        }
    }


    /**
     * HTML escapen
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            if (typeof text === 'object' && text !== null && text.message) {
                text = text.message; // Handle error objects
            } else {
                text = String(text); // Fallback to string conversion
            }
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

})(jQuery);