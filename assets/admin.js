/**
 * Excel Calculator Pro - Verbessertes Admin JavaScript
 */

(function ($) {
    'use strict';

    /**
     * Main Admin Class
     */
    class ECPAdmin {
        constructor() {
            this.currentCalculatorId = 0;
            this.unsavedChanges = false;
            this.fieldCounter = 0;
            this.outputCounter = 0;

            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeSortables();
            this.initializeColorPickers();
            this.setupUnsavedChangesWarning();

            // Initialize modules
            this.calculator = new CalculatorManager(this);
            this.ui = new UIManager(this);
            this.notifications = new NotificationManager();
        }

        bindEvents() {
            // Calculator management
            $(document).on('click', '#ecp-new-calculator, #ecp-new-calculator-empty', () => this.calculator.newCalculator());
            $(document).on('click', '.ecp-edit-calc', (e) => this.calculator.editCalculator($(e.currentTarget).data('id')));
            $(document).on('click', '.ecp-delete-calc', (e) => this.calculator.deleteCalculator($(e.currentTarget).data('id')));
            $(document).on('click', '.ecp-duplicate-calc', (e) => this.calculator.duplicateCalculator($(e.currentTarget).data('id')));

            // Editor actions
            $(document).on('click', '#ecp-save-calculator', () => this.calculator.save());
            $(document).on('click', '#ecp-cancel-edit', () => this.calculator.cancelEdit());
            $(document).on('click', '#ecp-delete-calculator', () => this.calculator.deleteFromEditor());
            $(document).on('click', '#ecp-duplicate-calculator', () => this.calculator.duplicateFromEditor());

            // Field management
            $(document).on('click', '#ecp-add-field', () => this.addField());
            $(document).on('click', '#ecp-add-output', () => this.addOutput());
            $(document).on('click', '.ecp-remove-field', (e) => this.removeField($(e.currentTarget)));
            $(document).on('click', '.ecp-remove-output', (e) => this.removeOutput($(e.currentTarget)));

            // Copy shortcode
            $(document).on('click', '.ecp-copy-shortcode', (e) => this.copyShortcode($(e.currentTarget)));

            // Form changes
            $(document).on('input change', '#ecp-calculator-editor input, #ecp-calculator-editor textarea, #ecp-calculator-editor select',
                () => this.markUnsavedChanges());

            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
        }

        initializeSortables() {
            $('#ecp-fields-container, #ecp-outputs-container').sortable({
                placeholder: 'ecp-sortable-placeholder',
                handle: '.ecp-sort-handle',
                cursor: 'move',
                tolerance: 'pointer',
                start: (e, ui) => {
                    ui.placeholder.height(ui.item.height());
                },
                stop: () => {
                    this.markUnsavedChanges();
                }
            });
        }

        initializeColorPickers() {
            $('.ecp-color-picker').wpColorPicker({
                change: () => this.markUnsavedChanges()
            });
        }

        setupUnsavedChangesWarning() {
            $(window).on('beforeunload', (e) => {
                if (this.unsavedChanges && $('#ecp-calculator-editor').is(':visible')) {
                    const message = ecp_admin.strings.unsaved_changes;
                    e.originalEvent.returnValue = message;
                    return message;
                }
            });
        }

        markUnsavedChanges() {
            this.unsavedChanges = true;
            $('#ecp-save-calculator').addClass('ecp-has-changes');
        }

        clearUnsavedChanges() {
            this.unsavedChanges = false;
            $('#ecp-save-calculator').removeClass('ecp-has-changes');
        }

        addField(data = {}) {
            const fieldId = this.generateShortFieldId('field');
            const fieldHtml = this.generateFieldHTML(fieldId, data);

            const $field = $(fieldHtml);
            $('#ecp-fields-container').append($field);
            $field.hide().slideDown(300);

            this.initializeFieldEvents($field);
            this.markUnsavedChanges();
        }

        addOutput(data = {}) {
            const outputId = this.generateShortFieldId('output');
            const outputHtml = this.generateOutputHTML(outputId, data);

            const $output = $(outputHtml);
            $('#ecp-outputs-container').append($output);
            $output.hide().slideDown(300);

            this.initializeOutputEvents($output);
            this.markUnsavedChanges();
        }

        /**
         * Verbesserte ID-Generierung - viel kürzer und benutzerfreundlicher
         */
        generateShortFieldId(prefix) {
            this.fieldCounter++;

            if (prefix === 'field') {
                // Für Eingabefelder: feld_1, feld_2, etc.
                return `feld_${this.fieldCounter}`;
            } else {
                // Für Ausgabefelder: ergebnis_1, ergebnis_2, etc.
                return `ergebnis_${this.fieldCounter}`;
            }
        }

        generateFieldHTML(fieldId, data) {
            return `
                <div class="ecp-field-item" data-field-id="${fieldId}">
                    <div class="ecp-field-header">
                        <span class="ecp-sort-handle dashicons dashicons-menu"></span>
                        <span class="ecp-field-label">${data.label || 'Neues Feld'}</span>
                        <button type="button" class="ecp-remove-field">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="ecp-field-content">
                        <div class="ecp-form-row">
                            <label>ID:</label>
                            <input type="text" class="field-id" value="${data.id || fieldId}" 
                                   placeholder="z.B. kreditsumme">
                        </div>
                        <div class="ecp-form-row">
                            <label>Label:</label>
                            <input type="text" class="field-label" value="${data.label || ''}" 
                                   placeholder="z.B. Kreditsumme">
                        </div>
                        <div class="ecp-form-row">
                            <label>Typ:</label>
                            <select class="field-type">
                                <option value="number" ${data.type === 'number' ? 'selected' : ''}>Zahl</option>
                                <option value="text" ${data.type === 'text' ? 'selected' : ''}>Text</option>
                            </select>
                        </div>
                        <div class="ecp-form-row ecp-number-options" ${data.type === 'text' ? 'style="display:none;"' : ''}>
                            <label>Min/Max:</label>
                            <input type="number" class="field-min small-text" value="${data.min || ''}" placeholder="Min">
                            <input type="number" class="field-max small-text" value="${data.max || ''}" placeholder="Max">
                        </div>
                        <div class="ecp-form-row">
                            <label>Standardwert:</label>
                            <input type="text" class="field-default" value="${data.default || ''}" placeholder="0">
                        </div>
                        <div class="ecp-form-row">
                            <label>Einheit:</label>
                            <input type="text" class="field-unit" value="${data.unit || ''}" placeholder="€, %, kg">
                        </div>
                        <div class="ecp-form-row">
                            <label>Hilfetext:</label>
                            <input type="text" class="field-help" value="${data.help || ''}" placeholder="Zusätzliche Hinweise">
                        </div>
                    </div>
                </div>
            `;
        }

        generateOutputHTML(outputId, data) {
            return `
                <div class="ecp-output-item" data-output-id="${outputId}">
                    <div class="ecp-output-header">
                        <span class="ecp-sort-handle dashicons dashicons-menu"></span>
                        <span class="ecp-output-label">${data.label || 'Neue Ausgabe'}</span>
                        <button type="button" class="ecp-remove-output">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="ecp-output-content">
                        <div class="ecp-form-row">
                            <label>Label:</label>
                            <input type="text" class="output-label" value="${data.label || ''}" 
                                   placeholder="z.B. Monatliche Rate">
                        </div>
                        <div class="ecp-form-row">
                            <label>Formel:</label>
                            <textarea class="output-formula" rows="3" 
                                      placeholder="z.B. RUNDEN(feld_1 * feld_2, 2)">${data.formula || ''}</textarea>
                            <small class="ecp-formula-help">
                                Verfügbare Funktionen: WENN, RUNDEN, MIN, MAX, SUMME, MITTELWERT, OBERGRENZE, UNTERGRENZE
                            </small>
                        </div>
                        <div class="ecp-form-row">
                            <label>Format:</label>
                            <select class="output-format">
                                <option value="" ${!data.format ? 'selected' : ''}>Standard</option>
                                <option value="currency" ${data.format === 'currency' ? 'selected' : ''}>Währung</option>
                                <option value="percentage" ${data.format === 'percentage' ? 'selected' : ''}>Prozent</option>
                                <option value="integer" ${data.format === 'integer' ? 'selected' : ''}>Ganzzahl</option>
                            </select>
                        </div>
                        <div class="ecp-form-row">
                            <label>Einheit:</label>
                            <input type="text" class="output-unit" value="${data.unit || ''}" 
                                   placeholder="€, %, Stück, kg (optional)">
                            <small class="description">Wird zusätzlich zum formatierten Wert angezeigt</small>
                        </div>
                        <div class="ecp-form-row">
                            <label>Hilfetext:</label>
                            <input type="text" class="output-help" value="${data.help || ''}" 
                                   placeholder="Zusätzliche Erklärung für das Ergebnis">
                        </div>
                    </div>
                </div>
            `;
        }

        initializeFieldEvents($field) {
            $field.find('.field-type').on('change', function () {
                const isNumber = $(this).val() === 'number';
                $field.find('.ecp-number-options').toggle(isNumber);
            });

            $field.find('.field-label').on('input', function () {
                $field.find('.ecp-field-label').text($(this).val() || 'Neues Feld');
            });

            // ID-Vorschläge bei Label-Änderung
            $field.find('.field-label').on('input', function () {
                const $idField = $field.find('.field-id');
                const currentId = $idField.val();
                const newLabel = $(this).val();

                // Nur automatisch ändern, wenn das ID-Feld noch die ursprüngliche Auto-ID hat
                if (currentId.match(/^feld_\d+$/)) {
                    const suggestedId = this.generateIdFromLabel(newLabel);
                    if (suggestedId) {
                        $idField.val(suggestedId);
                    }
                }
            }.bind(this));
        }

        initializeOutputEvents($output) {
            $output.find('.output-label').on('input', function () {
                $output.find('.ecp-output-label').text($(this).val() || 'Neue Ausgabe');
            });

            // Formel-Hilfe erweitern
            $output.find('.output-formula').on('focus', function () {
                $(this).siblings('.ecp-formula-help').html(`
                    <strong>Verfügbare Funktionen:</strong><br>
                    • RUNDEN(wert, dezimalstellen) - z.B. RUNDEN(feld_1, 2)<br>
                    • OBERGRENZE(wert) / UNTERGRENZE(wert) - Auf-/Abrunden<br>
                    • SUMME(feld_1, feld_2, ...) - Addiert alle Werte<br>
                    • MITTELWERT(feld_1, feld_2) - Durchschnitt<br>
                    • MIN/MAX(feld_1, feld_2) - Kleinster/Größter Wert<br>
                    • WENN(bedingung, wert_wenn_wahr, wert_wenn_falsch)<br>
                    • ABS(wert), SQRT(wert), POW(basis, exponent)
                `);
            }).on('blur', function () {
                $(this).siblings('.ecp-formula-help').html('Verfügbare Funktionen: WENN, RUNDEN, MIN, MAX, SUMME, MITTELWERT, OBERGRENZE, UNTERGRENZE');
            });
        }

        /**
         * Generiert eine benutzerfreundliche ID aus einem Label
         */
        generateIdFromLabel(label) {
            if (!label) return '';

            return label
                .toLowerCase()
                .replace(/[äöüß]/g, (match) => {
                    const map = { 'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss' };
                    return map[match] || match;
                })
                .replace(/[^a-z0-9]/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '')
                .substring(0, 30); // Maximale Länge begrenzen
        }

        removeField($button) {
            $button.closest('.ecp-field-item').slideUp(300, function () {
                $(this).remove();
            });
            this.markUnsavedChanges();
        }

        removeOutput($button) {
            $button.closest('.ecp-output-item').slideUp(300, function () {
                $(this).remove();
            });
            this.markUnsavedChanges();
        }

        copyShortcode($button) {
            const shortcode = $button.data('shortcode');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(shortcode).then(() => {
                    this.showCopyFeedback($button, true);
                }).catch(() => {
                    this.showCopyFeedback($button, false);
                });
            } else {
                // Fallback
                const textArea = document.createElement('textarea');
                textArea.value = shortcode;
                document.body.appendChild(textArea);
                textArea.select();
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                this.showCopyFeedback($button, successful);
            }
        }

        showCopyFeedback($button, success) {
            const originalText = $button.html();
            const feedbackIcon = success ? '✅' : '❌';
            const feedbackText = success ? 'Kopiert!' : 'Fehler!';

            $button.html(`${feedbackIcon} ${feedbackText}`);
            setTimeout(() => {
                $button.html(originalText);
            }, 2000);
        }

        handleKeyboardShortcuts(e) {
            // Ctrl+S oder Cmd+S
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                if ($('#ecp-calculator-editor').is(':visible')) {
                    e.preventDefault();
                    this.calculator.save();
                }
            }

            // Escape
            if (e.key === 'Escape') {
                if ($('#ecp-calculator-editor').is(':visible')) {
                    this.calculator.cancelEdit();
                }
            }
        }

        collectFormData() {
            return {
                id: parseInt($('#calculator-id').val()) || 0,
                name: $('#calculator-name').val().trim(),
                description: $('#calculator-description').val().trim(),
                fields: this.collectFields(),
                formulas: this.collectFormulas(),
                settings: {}
            };
        }

        collectFields() {
            const fields = [];

            $('.ecp-field-item').each(function () {
                const $item = $(this);
                const fieldData = {
                    id: $item.find('.field-id').val().trim(),
                    label: $item.find('.field-label').val().trim(),
                    type: $item.find('.field-type').val(),
                    default: $item.find('.field-default').val().trim(),
                    min: $item.find('.field-min').val().trim(),
                    max: $item.find('.field-max').val().trim(),
                    unit: $item.find('.field-unit').val().trim(),
                    help: $item.find('.field-help').val().trim()
                };

                if (fieldData.id && fieldData.label) {
                    fields.push(fieldData);
                }
            });

            return fields;
        }

        collectFormulas() {
            const formulas = [];

            $('.ecp-output-item').each(function () {
                const $item = $(this);
                const formulaData = {
                    label: $item.find('.output-label').val().trim(),
                    formula: $item.find('.output-formula').val().trim(),
                    format: $item.find('.output-format').val(),
                    unit: $item.find('.output-unit').val().trim(),
                    help: $item.find('.output-help').val().trim()
                };

                if (formulaData.label && formulaData.formula) {
                    formulas.push(formulaData);
                }
            });

            return formulas;
        }
    }

    /**
     * Calculator Management Class (unverändert)
     */
    class CalculatorManager {
        constructor(admin) {
            this.admin = admin;
        }

        newCalculator() {
            if (this.checkUnsavedChanges()) {
                this.showEditor();
                this.resetEditor();
                $('#calculator-name').focus();
                $('#ecp-editor-title').text(ecp_admin.strings.new_calculator);
            }
        }

        editCalculator(calculatorId) {
            if (this.checkUnsavedChanges()) {
                this.loadCalculator(calculatorId);
            }
        }

        loadCalculator(calculatorId) {
            this.admin.ui.showLoading();

            $.ajax({
                url: ecp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecp_get_calculator',
                    nonce: ecp_admin.nonce,
                    calculator_id: calculatorId
                },
                success: (response) => {
                    this.admin.ui.hideLoading();

                    if (response.success) {
                        this.populateEditor(response.data);
                        this.showEditor();
                        this.admin.clearUnsavedChanges();
                    } else {
                        this.admin.notifications.error(response.data.message || 'Fehler beim Laden');
                    }
                },
                error: () => {
                    this.admin.ui.hideLoading();
                    this.admin.notifications.error('Verbindungsfehler');
                }
            });
        }

        save() {
            const data = this.admin.collectFormData();

            if (!data.name) {
                this.admin.notifications.error(ecp_admin.strings.error_name_required);
                $('#calculator-name').focus();
                return;
            }

            this.admin.ui.showLoading();

            $.ajax({
                url: ecp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecp_save_calculator',
                    nonce: ecp_admin.nonce,
                    ...data
                },
                success: (response) => {
                    this.admin.ui.hideLoading();

                    if (response.success) {
                        this.admin.currentCalculatorId = response.data.id;
                        $('#calculator-id').val(response.data.id);
                        this.admin.clearUnsavedChanges();
                        this.admin.notifications.success(ecp_admin.strings.success_saved);
                        this.updateEditorTitle(data.name);
                        this.showEditorActions();
                    } else {
                        this.admin.notifications.error(response.data.message || 'Speicherfehler');
                    }
                },
                error: () => {
                    this.admin.ui.hideLoading();
                    this.admin.notifications.error('Verbindungsfehler');
                }
            });
        }

        deleteCalculator(calculatorId) {
            if (!confirm(ecp_admin.strings.confirm_delete)) {
                return;
            }

            this.admin.ui.showLoading();

            $.ajax({
                url: ecp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecp_delete_calculator',
                    nonce: ecp_admin.nonce,
                    calculator_id: calculatorId
                },
                success: (response) => {
                    this.admin.ui.hideLoading();

                    if (response.success) {
                        $(`.ecp-calculator-card[data-id="${calculatorId}"]`).fadeOut(300, function () {
                            $(this).remove();
                            if ($('.ecp-calculator-card').length === 0) {
                                location.reload();
                            }
                        });
                        this.admin.notifications.success('Erfolgreich gelöscht');
                    } else {
                        this.admin.notifications.error(response.data.message || 'Löschfehler');
                    }
                },
                error: () => {
                    this.admin.ui.hideLoading();
                    this.admin.notifications.error('Verbindungsfehler');
                }
            });
        }

        cancelEdit() {
            if (this.checkUnsavedChanges()) {
                this.hideEditor();
                this.resetEditor();
            }
        }

        checkUnsavedChanges() {
            if (this.admin.unsavedChanges) {
                return confirm(ecp_admin.strings.unsaved_changes + ' Fortfahren?');
            }
            return true;
        }

        showEditor() {
            $('#ecp-calculators-list').hide();
            $('#ecp-calculator-editor').show().addClass('ecp-fade-in');
        }

        hideEditor() {
            $('#ecp-calculator-editor').hide();
            $('#ecp-calculators-list').show();
        }

        resetEditor() {
            this.admin.currentCalculatorId = 0;
            $('#calculator-id').val('');
            $('#calculator-name').val('');
            $('#calculator-description').val('');
            $('#ecp-fields-container').empty();
            $('#ecp-outputs-container').empty();
            this.hideEditorActions();
            this.admin.clearUnsavedChanges();
        }

        populateEditor(data) {
            $('#calculator-id').val(data.id);
            $('#calculator-name').val(data.name);
            $('#calculator-description').val(data.description || '');

            this.updateEditorTitle(data.name);
            this.showEditorActions();

            // Populate fields
            if (Array.isArray(data.fields)) {
                data.fields.forEach(field => this.admin.addField(field));
            }

            // Populate formulas
            if (Array.isArray(data.formulas)) {
                data.formulas.forEach(formula => this.admin.addOutput(formula));
            }
        }

        updateEditorTitle(name) {
            $('#ecp-editor-title').text(`${ecp_admin.strings.edit_calculator}: ${name}`);
        }

        showEditorActions() {
            $('#ecp-duplicate-calculator, #ecp-delete-calculator').show();
        }

        hideEditorActions() {
            $('#ecp-duplicate-calculator, #ecp-delete-calculator').hide();
        }

        deleteFromEditor() {
            const calculatorId = parseInt($('#calculator-id').val());
            if (calculatorId > 0) {
                this.deleteCalculator(calculatorId);
                this.hideEditor();
                this.resetEditor();
            }
        }

        duplicateFromEditor() {
            const data = this.admin.collectFormData();
            data.id = 0;
            data.name = `${data.name} (Kopie)`;

            this.admin.ui.showLoading();

            $.ajax({
                url: ecp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecp_save_calculator',
                    nonce: ecp_admin.nonce,
                    ...data
                },
                success: (response) => {
                    this.admin.ui.hideLoading();

                    if (response.success) {
                        this.admin.notifications.success('Erfolgreich dupliziert');
                        this.loadCalculator(response.data.id);
                    } else {
                        this.admin.notifications.error(response.data.message || 'Duplikationsfehler');
                    }
                },
                error: () => {
                    this.admin.ui.hideLoading();
                    this.admin.notifications.error('Verbindungsfehler');
                }
            });
        }
    }

    /**
     * UI Management Class (unverändert)
     */
    class UIManager {
        constructor(admin) {
            this.admin = admin;
            this.$loadingOverlay = null;
        }

        showLoading() {
            if (!this.$loadingOverlay) {
                this.$loadingOverlay = $(`
                    <div class="ecp-loading-overlay">
                        <div class="ecp-loading-spinner">
                            <div class="ecp-spinner"></div>
                            <p>${ecp_admin.strings.loading}</p>
                        </div>
                    </div>
                `);
                $('body').append(this.$loadingOverlay);
            }
            this.$loadingOverlay.fadeIn(200);
        }

        hideLoading() {
            if (this.$loadingOverlay) {
                this.$loadingOverlay.fadeOut(200);
            }
        }
    }

    /**
     * Notification Management Class (unverändert)
     */
    class NotificationManager {
        constructor() {
            this.init();
        }

        init() {
            if (!$('.ecp-notifications').length) {
                $('body').append('<div class="ecp-notifications"></div>');
            }
        }

        show(message, type = 'info', duration = 5000) {
            const $notification = $(`
                <div class="ecp-notification ecp-notification-${type}">
                    <span class="ecp-notification-message">${message}</span>
                    <button class="ecp-notification-close">&times;</button>
                </div>
            `);

            $('.ecp-notifications').append($notification);
            $notification.slideDown(300);

            if (duration > 0) {
                setTimeout(() => {
                    this.hide($notification);
                }, duration);
            }

            $notification.find('.ecp-notification-close').on('click', () => {
                this.hide($notification);
            });
        }

        hide($notification) {
            $notification.slideUp(300, () => {
                $notification.remove();
            });
        }

        success(message, duration = 5000) {
            this.show(message, 'success', duration);
        }

        error(message, duration = 8000) {
            this.show(message, 'error', duration);
        }

        info(message, duration = 5000) {
            this.show(message, 'info', duration);
        }

        warning(message, duration = 6000) {
            this.show(message, 'warning', duration);
        }
    }

    // Initialize admin when document is ready
    $(document).ready(() => {
        window.ECPAdmin = new ECPAdmin();
    });

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

        // Moderne Clipboard API oder Fallback
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(valueToCopy).then(() => {
                this.showCopyFeedback($icon, true);
            }).catch(() => {
                this.fallbackCopyToClipboard(valueToCopy, $icon);
            });
        } else {
            this.fallbackCopyToClipboard(valueToCopy, $icon);
        }
    }

    fallbackCopyToClipboard(text, $icon) {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showCopyFeedback($icon, successful);
        } catch (err) {
            console.error('ECP: Fallback-Kopieren fehlgeschlagen: ', err);
            this.showCopyFeedback($icon, false);
        }
    }

    showCopyFeedback($icon, success) {
        const originalIconContent = $icon.html();
        const feedbackIcon = success ? '✅' : '❌';
        $icon.html(feedbackIcon).addClass('ecp-copied-feedback');

        setTimeout(() => {
            $icon.html(originalIconContent).removeClass('ecp-copied-feedback');
        }, success ? 1500 : 2000);
    }

})(jQuery);