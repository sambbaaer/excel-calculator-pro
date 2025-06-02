/**
 * TinyMCE Plugin für Excel Calculator Pro
 */

(function() {
    'use strict';
    
    tinymce.PluginManager.add('ecp_shortcode', function(editor, url) {
        
        // Plugin-Button zur Toolbar hinzufügen
        editor.addButton('ecp_shortcode', {
            title: 'Excel Calculator Pro einfügen',
            icon: 'icon dashicons-before dashicons-calculator',
            cmd: 'ecp_insert_shortcode'
        });
        
        // Menü-Item hinzufügen
        editor.addMenuItem('ecp_shortcode', {
            text: 'Calculator einfügen',
            context: 'insert',
            icon: 'icon dashicons-before dashicons-calculator',
            cmd: 'ecp_insert_shortcode'
        });
        
        // Kommando registrieren
        editor.addCommand('ecp_insert_shortcode', function() {
            openCalculatorDialog();
        });
        
        /**
         * Dialog für Kalkulator-Auswahl öffnen
         */
        function openCalculatorDialog() {
            // Verfügbare Kalkulatoren laden
            loadCalculators(function(calculators) {
                
                var calculatorOptions = [];
                
                if (calculators && calculators.length > 0) {
                    calculatorOptions = calculators.map(function(calc) {
                        return {
                            text: calc.name,
                            value: calc.id
                        };
                    });
                } else {
                    calculatorOptions = [{
                        text: 'Keine Kalkulatoren verfügbar',
                        value: ''
                    }];
                }
                
                // Dialog öffnen
                editor.windowManager.open({
                    title: 'Excel Calculator Pro einfügen',
                    width: 500,
                    height: 400,
                    body: [
                        {
                            type: 'container',
                            html: '<div style="margin-bottom: 15px;"><strong>Wählen Sie einen Kalkulator aus:</strong></div>'
                        },
                        {
                            type: 'listbox',
                            name: 'calculator_id',
                            label: 'Kalkulator:',
                            values: calculatorOptions,
                            style: 'width: 100%;'
                        },
                        {
                            type: 'container',
                            html: '<div style="margin: 20px 0; border-top: 1px solid #ddd; padding-top: 15px;"><strong>Erweiterte Optionen:</strong></div>'
                        },
                        {
                            type: 'listbox',
                            name: 'title_display',
                            label: 'Titel anzeigen:',
                            values: [
                                { text: 'Automatisch (aus Kalkulator-Einstellungen)', value: 'auto' },
                                { text: 'Verstecken', value: 'hide' },
                                { text: 'Benutzerdefiniert', value: 'custom' }
                            ]
                        },
                        {
                            type: 'textbox',
                            name: 'custom_title',
                            label: 'Benutzerdefinierter Titel:',
                            placeholder: 'Nur wenn "Benutzerdefiniert" gewählt'
                        },
                        {
                            type: 'listbox',
                            name: 'description_display',
                            label: 'Beschreibung anzeigen:',
                            values: [
                                { text: 'Automatisch (aus Kalkulator-Einstellungen)', value: 'auto' },
                                { text: 'Verstecken', value: 'hide' },
                                { text: 'Benutzerdefiniert', value: 'custom' }
                            ]
                        },
                        {
                            type: 'textbox',
                            name: 'custom_description',
                            label: 'Benutzerdefinierte Beschreibung:',
                            placeholder: 'Nur wenn "Benutzerdefiniert" gewählt',
                            multiline: true,
                            minHeight: 60
                        },
                        {
                            type: 'listbox',
                            name: 'theme',
                            label: 'Design:',
                            values: [
                                { text: 'Standard', value: 'default' },
                                { text: 'Kompakt', value: 'compact' },
                                { text: 'Modern', value: 'modern' }
                            ]
                        },
                        {
                            type: 'textbox',
                            name: 'width',
                            label: 'Breite:',
                            placeholder: 'z.B. 600px, 100%, auto'
                        },
                        {
                            type: 'textbox',
                            name: 'css_class',
                            label: 'CSS-Klassen:',
                            placeholder: 'Zusätzliche CSS-Klassen (optional)'
                        }
                    ],
                    onsubmit: function(e) {
                        var data = e.data;
                        
                        if (!data.calculator_id) {
                            editor.windowManager.alert('Bitte wählen Sie einen Kalkulator aus.');
                            return false;
                        }
                        
                        // Shortcode generieren
                        var shortcode = generateShortcode(data);
                        
                        // Shortcode in Editor einfügen
                        editor.insertContent(shortcode);
                    }
                });
            });
        }
        
        /**
         * Kalkulatoren von WordPress laden
         */
        function loadCalculators(callback) {
            // AJAX-Request an WordPress
            tinymce.util.XHR.send({
                url: ajaxurl,
                type: 'POST',
                data: 'action=ecp_get_calculators_for_tinymce&nonce=' + (window.ecp_tinymce_nonce || ''),
                success: function(response) {
                    try {
                        var data = JSON.parse(response);
                        if (data.success) {
                            callback(data.data || []);
                        } else {
                            console.error('Fehler beim Laden der Kalkulatoren:', data.data);
                            callback([]);
                        }
                    } catch (e) {
                        console.error('Parser-Fehler:', e);
                        callback([]);
                    }
                },
                error: function() {
                    console.error('AJAX-Fehler beim Laden der Kalkulatoren');
                    callback([]);
                }
            });
        }
        
        /**
         * Shortcode aus Dialog-Daten generieren
         */
        function generateShortcode(data) {
            var shortcode = '[excel_calculator id="' + data.calculator_id + '"';
            
            // Titel-Einstellungen
            if (data.title_display && data.title_display !== 'auto') {
                if (data.title_display === 'custom' && data.custom_title) {
                    shortcode += ' title="' + escapeAttribute(data.custom_title) + '"';
                } else if (data.title_display === 'hide') {
                    shortcode += ' title="hide"';
                }
            }
            
            // Beschreibungs-Einstellungen
            if (data.description_display && data.description_display !== 'auto') {
                if (data.description_display === 'custom' && data.custom_description) {
                    shortcode += ' description="' + escapeAttribute(data.custom_description) + '"';
                } else if (data.description_display === 'hide') {
                    shortcode += ' description="hide"';
                }
            }
            
            // Design
            if (data.theme && data.theme !== 'default') {
                shortcode += ' theme="' + data.theme + '"';
            }
            
            // Breite
            if (data.width && data.width !== 'auto') {
                shortcode += ' width="' + escapeAttribute(data.width) + '"';
            }
            
            // CSS-Klassen
            if (data.css_class) {
                shortcode += ' class="' + escapeAttribute(data.css_class) + '"';
            }
            
            shortcode += ']';
            
            return shortcode;
        }
        
        /**
         * Attribut für Shortcode escapen
         */
        function escapeAttribute(value) {
            return value.toString()
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        
        /**
         * Shortcode-Platzhalter im Editor anzeigen
         */
        editor.on('BeforeSetContent', function(event) {
            event.content = replaceShortcodesWithPlaceholders(event.content);
        });
        
        editor.on('PostProcess', function(event) {
            if (event.get) {
                event.content = replacePlaceholdersWithShortcodes(event.content);
            }
        });
        
        /**
         * Shortcodes durch Platzhalter ersetzen
         */
        function replaceShortcodesWithPlaceholders(content) {
            return content.replace(/\[excel_calculator([^\]]*)\]/g, function(match, attributes) {
                var id = extractAttribute(attributes, 'id');
                var title = extractAttribute(attributes, 'title') || 'Calculator';
                
                return '<div class="ecp-shortcode-placeholder" contenteditable="false" data-shortcode="' + 
                       escapeAttribute(match) + '">' +
                       '<div class="ecp-placeholder-content">' +
                       '<span class="ecp-placeholder-icon">📊</span>' +
                       '<span class="ecp-placeholder-title">Excel Calculator Pro</span>' +
                       '<span class="ecp-placeholder-subtitle">ID: ' + (id || '?') + 
                       (title !== 'auto' && title !== 'hide' ? ' - ' + title : '') + '</span>' +
                       '</div>' +
                       '</div>';
            });
        }
        
        /**
         * Platzhalter durch Shortcodes ersetzen
         */
        function replacePlaceholdersWithShortcodes(content) {
            return content.replace(/<div class="ecp-shortcode-placeholder"[^>]*data-shortcode="([^"]*)"[^>]*>.*?<\/div>/g, function(match, shortcode) {
                return shortcode.replace(/&quot;/g, '"').replace(/&amp;/g, '&');
            });
        }
        
        /**
         * Attribut aus Shortcode-Attributen extrahieren
         */
        function extractAttribute(attributes, name) {
            var regex = new RegExp(name + '="([^"]*)"');
            var match = attributes.match(regex);
            return match ? match[1] : null;
        }
        
        /**
         * CSS für Platzhalter hinzufügen
         */
        editor.on('init', function() {
            editor.dom.addStyle(`
                .ecp-shortcode-placeholder {
                    display: inline-block;
                    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
                    border: 2px dashed #2196f3;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 10px 0;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    max-width: 300px;
                    user-select: none;
                }
                
                .ecp-shortcode-placeholder:hover {
                    background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%);
                    border-color: #1976d2;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
                }
                
                .ecp-placeholder-content {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 5px;
                }
                
                .ecp-placeholder-icon {
                    font-size: 24px;
                    line-height: 1;
                }
                
                .ecp-placeholder-title {
                    font-weight: bold;
                    color: #1976d2;
                    font-size: 14px;
                }
                
                .ecp-placeholder-subtitle {
                    font-size: 12px;
                    color: #666;
                    font-style: italic;
                }
                
                .mce-content-body .ecp-shortcode-placeholder {
                    outline: none;
                }
            `);
        });
        
        /**
         * Doppelklick auf Platzhalter -> Shortcode bearbeiten
         */
        editor.on('dblclick', function(e) {
            var placeholder = e.target.closest('.ecp-shortcode-placeholder');
            if (placeholder) {
                var shortcode = placeholder.getAttribute('data-shortcode');
                if (shortcode) {
                    // Aktuellen Shortcode parsen und Dialog mit Werten öffnen
                    openEditShortcodeDialog(shortcode, placeholder);
                }
            }
        });
        
        /**
         * Dialog zum Bearbeiten eines bestehenden Shortcodes
         */
        function openEditShortcodeDialog(shortcode, placeholder) {
            var attributes = parseShortcode(shortcode);
            
            loadCalculators(function(calculators) {
                var calculatorOptions = calculators.map(function(calc) {
                    return {
                        text: calc.name,
                        value: calc.id
                    };
                });
                
                editor.windowManager.open({
                    title: 'Excel Calculator Pro bearbeiten',
                    width: 500,
                    height: 400,
                    body: [
                        {
                            type: 'listbox',
                            name: 'calculator_id',
                            label: 'Kalkulator:',
                            values: calculatorOptions,
                            value: attributes.id || ''
                        },
                        {
                            type: 'listbox',
                            name: 'title_display',
                            label: 'Titel anzeigen:',
                            values: [
                                { text: 'Automatisch', value: 'auto' },
                                { text: 'Verstecken', value: 'hide' },
                                { text: 'Benutzerdefiniert', value: 'custom' }
                            ],
                            value: attributes.title === 'hide' ? 'hide' : 
                                   (attributes.title && attributes.title !== 'auto' ? 'custom' : 'auto')
                        },
                        {
                            type: 'textbox',
                            name: 'custom_title',
                            label: 'Benutzerdefinierter Titel:',
                            value: attributes.title && attributes.title !== 'auto' && attributes.title !== 'hide' ? attributes.title : ''
                        },
                        {
                            type: 'listbox',
                            name: 'theme',
                            label: 'Design:',
                            values: [
                                { text: 'Standard', value: 'default' },
                                { text: 'Kompakt', value: 'compact' },
                                { text: 'Modern', value: 'modern' }
                            ],
                            value: attributes.theme || 'default'
                        },
                        {
                            type: 'textbox',
                            name: 'width',
                            label: 'Breite:',
                            value: attributes.width || ''
                        }
                    ],
                    onsubmit: function(e) {
                        var data = e.data;
                        var newShortcode = generateShortcode(data);
                        var newPlaceholder = replaceShortcodesWithPlaceholders(newShortcode);
                        
                        // Platzhalter ersetzen
                        editor.dom.setOuterHTML(placeholder, newPlaceholder);
                    }
                });
            });
        }
        
        /**
         * Shortcode-Attribute parsen
         */
        function parseShortcode(shortcode) {
            var attributes = {};
            var regex = /(\w+)="([^"]*)"/g;
            var match;
            
            while ((match = regex.exec(shortcode)) !== null) {
                attributes[match[1]] = match[2];
            }
            
            return attributes;
        }
        
        // Plugin-Info für TinyMCE
        return {
            getMetadata: function() {
                return {
                    name: 'Excel Calculator Pro',
                    url: 'https://your-website.com'
                };
            }
        };
    });
    
})();