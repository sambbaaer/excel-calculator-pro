/**
 * Excel Calculator Pro - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Globale Variablen
    let currentCalculatorId = 0;
    let fieldCounter = 0;
    let outputCounter = 0;
    let selectedTemplateId = 0;
    
    // DOM Ready
    $(document).ready(function() {
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
        $(document).on('click', '.ecp-edit-calc', editCalculator);
        $(document).on('click', '.ecp-delete-calc', deleteCalculator);
        $(document).on('click', '.ecp-duplicate-calc', duplicateCalculator);
        
        // Editor-Aktionen
        $('#ecp-cancel-edit').on('click', cancelEdit);
        $('#ecp-save-calculator').on('click', saveCalculator);
        $('#ecp-delete-calculator').on('click', deleteCurrentCalculator);
        $('#ecp-duplicate-calculator').on('click', duplicateCurrentCalculator);
        $('#ecp-preview-calculator').on('click', previewCalculator);
        
        // Feld-Management
        $('#ecp-add-field').on('click', addField);
        $('#ecp-add-output').on('click', addOutput);
        $(document).on('click', '.remove-field', removeField);
        $(document).on('click', '.remove-output', removeOutput);
        
        // Vorlagen
        $(document).on('click', '.ecp-use-template', useTemplate);
        $('#ecp-create-from-template').on('click', createFromTemplate);
        
        // Import/Export
        $('#ecp-export-btn').on('click', exportCalculator);
        $('#ecp-import-btn').on('click', importCalculator);
        
        // Modal-Handler
        $(document).on('click', '.ecp-modal-close', closeModal);
        $(document).on('click', '.ecp-modal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Shortcode kopieren
        $(document).on('click', '.ecp-copy-shortcode', copyShortcode);
        
        // Keyboard-Shortcuts
        $(document).on('keydown', handleKeyboardShortcuts);
        
        // Auto-Save für Entwürfe
        setInterval(autoSave, 30000); // Alle 30 Sekunden
    }
    
    /**
     * Sortable-Bereiche initialisieren
     */
    function initializeSortables() {
        $('#ecp-fields-container, #ecp-outputs-container').sortable({
            placeholder: 'ui-sortable-placeholder',
            cursor: 'move',
            tolerance: 'pointer',
            start: function(e, ui) {
                ui.placeholder.height(ui.item.height());
            },
            update: function() {
                showUnsavedChanges();
            }
        });
    }
    
    /**
     * Tooltips initialisieren
     */
    function initializeTooltips() {
        $('[title]').each(function() {
            const $element = $(this);
            const title = $element.attr('title');
            
            if (title) {
                $element.removeAttr('title');
                $element.addClass('ecp-tooltip');
                $element.append('<span class="ecp-tooltiptext">' + title + '</span>');
            }
        });
    }
    
    /**
     * Neuen Kalkulator erstellen
     */
    function newCalculator() {
        resetEditor();
        $('#ecp-calculator-editor').show().addClass('ecp-slide-up');
        $('#ecp-calculators-list').hide();
        $('#calculator-name').focus();
    }
    
    /**
     * Kalkulator bearbeiten
     */
    function editCalculator() {
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
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    populateEditor(response.data);
                    $('#ecp-calculator-editor').show().addClass('ecp-slide-up');
                    $('#ecp-calculators-list').hide();
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }
    
    /**
     * Kalkulator löschen
     */
    function deleteCalculator() {
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
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        checkEmptyState();
                    });
                    showSuccess(ecp_admin.strings.success_deleted);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }
    
    /**
     * Kalkulator duplizieren
     */
    function duplicateCalculator() {
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
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    data.name = data.name + ' (Kopie)';
                    data.id = 0; // Neue ID für Duplikat
                    
                    saveCalculatorData(data, function() {
                        location.reload();
                    });
                } else {
                    hideLoading();
                    showError(response.data);
                }
            },
            error: function() {
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
        $('#ecp-editor-title').text('Neuer Kalkulator');
        $('#ecp-delete-calculator').hide();
        $('#ecp-duplicate-calculator').hide();
        
        clearUnsavedChanges();
    }
    
    /**
     * Bearbeitung abbrechen
     */
    function cancelEdit() {
        if (hasUnsavedChanges() && !confirm('Ungespeicherte Änderungen verwerfen?')) {
            return;
        }
        
        $('#ecp-calculator-editor').hide();
        $('#ecp-calculators-list').show();
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
            settings: {}
        };
        
        saveCalculatorData(calculatorData, function() {
            showSuccess(ecp_admin.strings.success_saved);
            setTimeout(() => location.reload(), 1000);
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
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    currentCalculatorId = response.data.id;
                    $('#calculator-id').val(currentCalculatorId);
                    clearUnsavedChanges();
                    
                    if (callback) callback();
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
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
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        showSuccess(ecp_admin.strings.success_deleted);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(response.data);
                    }
                },
                error: function() {
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
            return;
        }
        
        const duplicateData = {
            id: 0,
            name: name + ' (Kopie)',
            description: $('#calculator-description').val().trim(),
            fields: collectFields(),
            formulas: collectFormulas(),
            settings: {}
        };
        
        saveCalculatorData(duplicateData, function() {
            showSuccess('Kalkulator dupliziert!');
            setTimeout(() => location.reload(), 1000);
        });
    }
    
    /**
     * Kalkulator-Vorschau
     */
    function previewCalculator() {
        const fields = collectFields();
        const formulas = collectFormulas();
        const name = $('#calculator-name').val().trim() || 'Vorschau';
        
        let previewHtml = '<div class="ecp-calculator">';
        previewHtml += '<div class="ecp-calculator-header">';
        previewHtml += '<h3 class="ecp-calculator-title">' + escapeHtml(name) + '</h3>';
        previewHtml += '</div>';
        
        if (fields.length > 0) {
            previewHtml += '<div class="ecp-section ecp-input-fields">';
            previewHtml += '<h4 class="ecp-section-title">Eingaben</h4>';
            
            fields.forEach(function(field) {
                previewHtml += '<div class="ecp-field-group">';
                previewHtml += '<label>' + escapeHtml(field.label) + '</label>';
                previewHtml += '<input type="number" class="ecp-input-field" value="' + (field.default || '0') + '" readonly>';
                previewHtml += '</div>';
            });
            
            previewHtml += '</div>';
        }
        
        if (formulas.length > 0) {
            previewHtml += '<div class="ecp-section ecp-output-fields">';
            previewHtml += '<h4 class="ecp-section-title">Ergebnisse</h4>';
            
            formulas.forEach(function(formula) {
                previewHtml += '<div class="ecp-output-group">';
                previewHtml += '<label>' + escapeHtml(formula.label) + '</label>';
                previewHtml += '<span class="ecp-output-field">0</span>';
                previewHtml += '</div>';
            });
            
            previewHtml += '</div>';
        }
        
        previewHtml += '</div>';
        
        $('#ecp-preview-content').html(previewHtml);
        $('#ecp-preview-modal').show();
    }
    
    /**
     * Feld hinzufügen
     */
    function addField(data = {}) {
        fieldCounter++;
        const fieldId = data.id || 'field_' + fieldCounter;

        const fieldHtml = `
            <div class="ecp-field-row" data-field-id="${fieldId}">
                <button type="button" class="remove-field" title="Feld entfernen">×</button>
                <table class="form-table">
                    <tr>
                        <th>Feld-ID:</th>
                        <td><input type="text" class="field-id regular-text" value="${fieldId}" readonly /></td>
                    </tr>
                    <tr>
                        <th>Label:</th>
                        <td><input type="text" class="field-label regular-text" value="${data.label || ''}" placeholder="z.B. Kreditsumme" /></td>
                    </tr>
                    <tr>
                        <th>Typ:</th>
                        <td>
                            <select class="field-type">
                                <option value="number" ${(data.type || 'number') === 'number' ? 'selected' : ''}>Zahl</option>
                                <option value="text" ${data.type === 'text' ? 'selected' : ''}>Text</option>
                                <option value="email" ${data.type === 'email' ? 'selected' : ''}>E-Mail</option>
                                <option value="tel" ${data.type === 'tel' ? 'selected' : ''}>Telefon</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Standardwert:</th>
                        <td><input type="text" class="field-default regular-text" value="${data.default || ''}" placeholder="0" /></td>
                    </tr>
                    <tr>
                        <th>Min/Max:</th>
                        <td>
                            <input type="number" class="field-min small-text" value="${data.min || ''}" placeholder="Min" />
                            <input type="number" class="field-max small-text" value="${data.max || ''}" placeholder="Max" />
                        </td>
                    </tr>
                    <tr>
                        <th>Einheit:</th>
                        <td><input type="text" class="field-unit regular-text" value="${data.unit || ''}" placeholder="z.B. €, %, kg" /></td>
                    </tr>
                    <tr>
                        <th>Hilfetext:</th>
                        <td><input type="text" class="field-help large-text" value="${data.help || ''}" placeholder="Optionaler Hilfetext" /></td>
                    </tr>
                    <tr>
                        <th>Erforderlich:</th>
                        <td><input type="checkbox" class="field-required" ${(data.required === true || data.required === 'true' || data.required === '1') ? 'checked' : ''} /></td>
                    </tr>
                </table>
            </div>
        `;

        $('#ecp-fields-container').append(fieldHtml);
        showUnsavedChanges();

        // Animation
        $('#ecp-fields-container .ecp-field-row:last-child').hide().slideDown(300);
    }
    
    /**
     * Ausgabefeld hinzufügen
     */
    function addOutput(data = {}) {
        outputCounter++;
        
        const outputHtml = `
            <div class="ecp-output-row">
                <button type="button" class="remove-output" title="Ausgabefeld entfernen">×</button>
                <table class="form-table">
                    <tr>
                        <th>Label:</th>
                        <td><input type="text" class="output-label regular-text" value="${data.label || ''}" placeholder="z.B. Monatliche Rate" /></td>
                    </tr>
                    <tr>
                        <th>Formel:</th>
                        <td>
                            <textarea class="output-formula large-text" rows="3" placeholder="z.B. field_1 * field_2">${data.formula || ''}</textarea>
                            <div class="description">
                                <strong>Verfügbare Funktionen:</strong><br>
                                Mathematisch: +, -, *, /, POW(basis, exponent), SQRT(zahl), ABS(zahl)<br>
                                Logik: WENN(bedingung, wert_wenn_wahr, wert_wenn_falsch)<br>
                                Aggregation: SUMME(wert1, wert2, ...), MIN(wert1, wert2, ...), MAX(wert1, wert2, ...)<br>
                                Rundung: RUNDEN(zahl, dezimalstellen)
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Format:</th>
                        <td>
                            <select class="output-format">
                                <option value="">Standard</option>
                                <option value="currency" ${data.format === 'currency' ? 'selected' : ''}>Währung (€)</option>
                                <option value="percentage" ${data.format === 'percentage' ? 'selected' : ''}>Prozent (%)</option>
                                <option value="integer" ${data.format === 'integer' ? 'selected' : ''}>Ganzzahl</option>
                                <option value="text" ${data.format === 'text' ? 'selected' : ''}>Text</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Einheit:</th>
                        <td><input type="text" class="output-unit regular-text" value="${data.unit || ''}" placeholder="z.B. €, %, Jahre" /></td>
                    </tr>
                    <tr>
                        <th>Hilfetext:</th>
                        <td><input type="text" class="output-help large-text" value="${data.help || ''}" placeholder="Erklärung der Berechnung" /></td>
                    </tr>
                </table>
            </div>
        `;
        
        $('#ecp-outputs-container').append(outputHtml);
        showUnsavedChanges();
        
        // Animation
        $('#ecp-outputs-container .ecp-output-row:last-child').hide().slideDown(300);
    }
    
    /**
     * Feld entfernen
     */
    function removeField() {
        $(this).closest('.ecp-field-row').slideUp(300, function() {
            $(this).remove();
            showUnsavedChanges();
        });
    }
    
    /**
     * Ausgabefeld entfernen
     */
    function removeOutput() {
        $(this).closest('.ecp-output-row').slideUp(300, function() {
            $(this).remove();
            showUnsavedChanges();
        });
    }
    
    /**
     * Felder sammeln
     */
    function collectFields() {
        const fields = [];
        
        $('.ecp-field-row').each(function() {
            const $row = $(this);
            
            const fieldData = {
                id: $row.find('.field-id').val(),
                label: $row.find('.field-label').val(),
                type: $row.find('.field-type').val(),
                default: $row.find('.field-default').val(),
                min: $row.find('.field-min').val(),
                max: $row.find('.field-max').val(),
                unit: $row.find('.field-unit').val(),
                help: $row.find('.field-help').val(),
                required: $row.find('.field-required').is(':checked')
            };
            
            fields.push(fieldData);
        });
        
        return fields;
    }
    
    /**
     * Formeln sammeln
     */
    function collectFormulas() {
        const formulas = [];
        
        $('.ecp-output-row').each(function() {
            const $row = $(this);
            
            const formulaData = {
                label: $row.find('.output-label').val(),
                formula: $row.find('.output-formula').val(),
                format: $row.find('.output-format').val(),
                unit: $row.find('.output-unit').val(),
                help: $row.find('.output-help').val()
            };
            
            formulas.push(formulaData);
        });
        
        return formulas;
    }
    
    /**
     * Editor mit Daten füllen
     */
    function populateEditor(data) {
        currentCalculatorId = data.id;
        $('#calculator-id').val(data.id);
        $('#calculator-name').val(data.name);
        $('#calculator-description').val(data.description || '');
        $('#ecp-editor-title').text('Kalkulator bearbeiten: ' + data.name);
        $('#ecp-delete-calculator').show();
        $('#ecp-duplicate-calculator').show();
        
        // Felder zurücksetzen
        $('#ecp-fields-container').empty();
        $('#ecp-outputs-container').empty();
        fieldCounter = 0;
        outputCounter = 0;
        
        // Felder hinzufügen
        if (data.fields && data.fields.length > 0) {
            data.fields.forEach(function(field) {
                addField(field);
            });
        }
        
        // Ausgabefelder hinzufügen
        if (data.formulas && data.formulas.length > 0) {
            data.formulas.forEach(function(formula) {
                addOutput(formula);
            });
        }
        
        clearUnsavedChanges();
    }
    
    /**
 * Vorlage verwenden - VERBESSERT
 */
    function useTemplate() {
        selectedTemplateId = $(this).data('template-id');
        const templateName = $(this).closest('.ecp-template-card').find('h3').text();

        $('#template-calculator-name').val(templateName);
        $('#ecp-template-modal').show();
    }

    /**
     * Aus Vorlage erstellen - VERBESSERT
     */
    function createFromTemplate() {
        const name = $('#template-calculator-name').val().trim();

        if (!name) {
            showError('Bitte geben Sie einen Namen ein.');
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

                if (response.success) {
                    closeModal();

                    // Neuen Kalkulator sofort zur Bearbeitung laden
                    if (response.data && response.data.id) {
                        editCalculatorById(response.data.id);
                    } else {
                        showSuccess(ecp_admin.strings.template_created);
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showError(response.data);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }

    /**
     * Kalkulator nach ID bearbeiten - NEU
     */
    function editCalculatorById(calculatorId) {
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
                    $('#ecp-calculator-editor').show().addClass('ecp-slide-up');
                    $('#ecp-calculators-list').hide();
                    showSuccess('Kalkulator aus Vorlage erstellt und geöffnet!');
                } else {
                    showError(response.data);
                    setTimeout(() => location.reload(), 1000);
                }
            },
            error: function () {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
                setTimeout(() => location.reload(), 1000);
            }
        });
    }

    /**
     * Editor mit Daten füllen - VERBESSERT
     */
    function populateEditor(data) {
        currentCalculatorId = data.id;
        $('#calculator-id').val(data.id);
        $('#calculator-name').val(data.name);
        $('#calculator-description').val(data.description || '');
        $('#ecp-editor-title').text('Kalkulator bearbeiten: ' + data.name);
        $('#ecp-delete-calculator').show();
        $('#ecp-duplicate-calculator').show();

        // Container leeren
        $('#ecp-fields-container').empty();
        $('#ecp-outputs-container').empty();
        fieldCounter = 0;
        outputCounter = 0;

        // Felder hinzufügen - VERBESSERT mit Array-Prüfung
        if (Array.isArray(data.fields) && data.fields.length > 0) {
            data.fields.forEach(function (field) {
                // Sicherstellen, dass es ein Objekt ist
                if (typeof field === 'object' && field !== null) {
                    addField(field);
                }
            });
        }

        // Ausgabefelder hinzufügen - VERBESSERT mit Array-Prüfung
        if (Array.isArray(data.formulas) && data.formulas.length > 0) {
            data.formulas.forEach(function (formula) {
                // Sicherstellen, dass es ein Objekt ist
                if (typeof formula === 'object' && formula !== null) {
                    addOutput(formula);
                }
            });
        }

        clearUnsavedChanges();
    }
    
    /**
     * Aus Vorlage erstellen
     */
    function createFromTemplate() {
        const name = $('#template-calculator-name').val().trim();
        
        if (!name) {
            showError('Bitte geben Sie einen Namen ein.');
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
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    closeModal();
                    showSuccess(ecp_admin.strings.template_created);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                hideLoading();
                showError(ecp_admin.strings.error_occurred);
            }
        });
    }
    
    /**
     * Kalkulator exportieren
     */
    function exportCalculator() {
        const calculatorId = $('#ecp-export-calculator').val();
        
        if (!calculatorId) {
            showError('Bitte wählen Sie einen Kalkulator aus.');
            return;
        }
        
        // Download-Link erstellen
        const form = $('<form>', {
            method: 'POST',
            action: ecp_admin.ajax_url
        });
        
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'ecp_export_calculator' }));
        form.append($('<input>', { type: 'hidden', name: 'nonce', value: ecp_admin.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'calculator_id', value: calculatorId }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    }
    
    /**
     * Kalkulator importieren
     */
    function importCalculator() {
        const fileInput = $('#ecp-import-file')[0];
        
        if (!fileInput.files.length) {
            showError('Bitte wählen Sie eine Datei aus.');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'ecp_import_calculator');
        formData.append('nonce', ecp_admin.nonce);
        formData.append('import_file', fileInput.files[0]);
        
        showLoading();
        
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showSuccess('Kalkulator erfolgreich importiert!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
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
        e.stopPropagation();
        
        const shortcode = $(this).data('shortcode');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shortcode).then(function() {
                showSuccess('Shortcode kopiert!');
            });
        } else {
            // Fallback für ältere Browser
            const textArea = $('<textarea>').val(shortcode).appendTo('body').select();
            document.execCommand('copy');
            textArea.remove();
            showSuccess('Shortcode kopiert!');
        }
    }
    
    /**
     * Modal schliessen
     */
    function closeModal() {
        $('.ecp-modal').hide();
    }
    
    /**
     * Keyboard-Shortcuts
     */
    function handleKeyboardShortcuts(e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 's':
                    e.preventDefault();
                    if ($('#ecp-calculator-editor').is(':visible')) {
                        saveCalculator();
                    }
                    break;
                case 'Escape':
                    if ($('.ecp-modal').is(':visible')) {
                        closeModal();
                    }
                    break;
            }
        }
    }
    
    /**
     * Auto-Save
     */
    function autoSave() {
        if (!hasUnsavedChanges() || !currentCalculatorId) {
            return;
        }
        
        const calculatorData = {
            id: currentCalculatorId,
            name: $('#calculator-name').val().trim(),
            description: $('#calculator-description').val().trim(),
            fields: collectFields(),
            formulas: collectFormulas(),
            settings: {}
        };
        
        $.ajax({
            url: ecp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_save_calculator',
                nonce: ecp_admin.nonce,
                calculator_id: calculatorData.id,
                name: calculatorData.name,
                description: calculatorData.description,
                fields: calculatorData.fields,
                formulas: calculatorData.formulas,
                settings: calculatorData.settings
            },
            success: function(response) {
                if (response.success) {
                    clearUnsavedChanges();
                    showInfo('Automatisch gespeichert');
                }
            }
        });
    }
    
    /**
     * Ungespeicherte Änderungen anzeigen
     */
    function showUnsavedChanges() {
        $('#ecp-save-calculator').addClass('button-primary-emphasized');
        $(window).on('beforeunload.ecp', function() {
            return 'Sie haben ungespeicherte Änderungen.';
        });
    }
    
    /**
     * Ungespeicherte Änderungen löschen
     */
    function clearUnsavedChanges() {
        $('#ecp-save-calculator').removeClass('button-primary-emphasized');
        $(window).off('beforeunload.ecp');
    }
    
    /**
     * Ungespeicherte Änderungen prüfen
     */
    function hasUnsavedChanges() {
        return $('#ecp-save-calculator').hasClass('button-primary-emphasized');
    }
    
    /**
     * Empty State prüfen
     */
    function checkEmptyState() {
        if ($('.ecp-calculator-card').length === 0) {
            const emptyHtml = `
                <div class="ecp-empty-state">
                    <p>Noch keine Kalkulatoren erstellt.</p>
                    <p>Erstellen Sie Ihren ersten Kalkulator oder verwenden Sie eine Vorlage.</p>
                </div>
            `;
            $('#ecp-calculators-list').html(emptyHtml);
        }
    }
    
    /**
     * Loading anzeigen
     */
    function showLoading() {
        $('body').addClass('ecp-loading');
        
        if (!$('#ecp-loading-overlay').length) {
            $('body').append(`
                <div id="ecp-loading-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(255, 255, 255, 0.8);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="text-align: center;">
                        <div class="spinner is-active" style="float: none; margin: 0 auto 10px;"></div>
                        <p>${ecp_admin.strings.loading}</p>
                    </div>
                </div>
            `);
        }
    }
    
    /**
     * Loading ausblenden
     */
    function hideLoading() {
        $('body').removeClass('ecp-loading');
        $('#ecp-loading-overlay').remove();
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
    function showNotice(message, type = 'info') {
        const noticeId = 'ecp-notice-' + Date.now();
        const noticeClass = type === 'error' ? 'notice-error' : 
                           type === 'success' ? 'notice-success' : 'notice-info';
        
        const notice = $(`
            <div id="${noticeId}" class="notice ${noticeClass} is-dismissible ecp-notice" style="margin-top: 10px;">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Diese Meldung ausblenden.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // Auto-Dismiss für Erfolgs- und Info-Meldungen
        if (type !== 'error') {
            setTimeout(() => {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        // Dismiss-Button
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * HTML escapen
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
})(jQuery);