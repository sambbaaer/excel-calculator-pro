# Excel Calculator Pro

Ein leistungsstarkes WordPress-Plugin für die Erstellung interaktiver Excel-ähnlicher Kalkulatoren mit Echtzeit-Berechnung und erweiterten Formelunterstützung.

## ✨ Features

### 🧮 Erweiterte Berechnungen
- **Excel-ähnliche Formeln**: SUMME, MITTELWERT, MIN, MAX, RUNDEN, WENN, etc.
- **Mathematische Funktionen**: SQRT, POW, ABS, LOG, etc.
- **Datumsfunktionen**: HEUTE, JAHR, MONAT, TAG
- **Bedingte Logik**: Komplexe WENN-Bedingungen
- **Verschachtelte Funktionen**: Beliebig tiefe Verschachtelung

### 🎨 Benutzerfreundlichkeit
- **Drag & Drop**: Intuitive Verwaltung von Feldern
- **Live-Vorschau**: Sofortige Vorschau im Admin-Bereich
- **Responsive Design**: Optimiert für alle Geräte
- **Barrierefreiheit**: WCAG 2.1 konform
- **Dark Mode**: Automatische Anpassung an Systemeinstellungen

### 📊 Professionelle Vorlagen
- **Kreditrechner**: Zinsen und Raten berechnen
- **ROI-Rechner**: Return on Investment
- **BMI-Rechner**: Body Mass Index
- **Erweiterbar**: Eigene Vorlagen erstellen

### 🔧 Entwicklerfreundlich
- **Modularer Aufbau**: Saubere Trennung von Logik
- **Hooks & Filter**: WordPress-Standards
- **API-Zugang**: JavaScript-API für externe Integration
- **Debugging**: Entwicklermodus mit detaillierten Logs

## 🚀 Installation

### Automatische Installation
1. WordPress Admin → Plugins → Installieren
2. Nach "Excel Calculator Pro" suchen
3. Plugin aktivieren

### Manuelle Installation
1. Plugin-Dateien nach `/wp-content/plugins/excel-calculator-pro/` hochladen
2. Plugin in WordPress aktivieren
3. Fertig!

## 📖 Schnellstart

### 1. Ersten Kalkulator erstellen
1. WordPress Admin → **Einstellungen** → **Calculator Pro**
2. **"Neuer Kalkulator"** klicken
3. Namen eingeben (z.B. "Kreditrechner")
4. Eingabefelder hinzufügen:
   - **Kreditsumme** (field_1)
   - **Zinssatz** (field_2) 
   - **Laufzeit** (field_3)

### 2. Ausgabefeld konfigurieren
1. **"Ausgabefeld hinzufügen"** klicken
2. Label: "Monatliche Rate"
3. Formel eingeben:
```
RUNDEN((field_1 * (field_2/100/12) * POW(1 + field_2/100/12, field_3*12)) / (POW(1 + field_2/100/12, field_3*12) - 1), 2)
```
4. Format: **Währung**

### 3. Kalkulator einbetten
1. Shortcode kopieren: `[excel_calculator id="1"]`
2. In Beitrag/Seite einfügen
3. Fertig!

## 🛠️ Konfiguration

### Einstellungen
**WordPress Admin → Einstellungen → Calculator Pro → Einstellungen**

- **Standard-Währung**: CHF, EUR, USD
- **Zahlenformat**: Schweiz, Deutschland, USA
- **Theme-Optionen**: Standard, Kompakt, Modern

### Shortcode-Parameter
```
[excel_calculator 
   id="1"                    // Kalkulator-ID (erforderlich)
   title="Mein Titel"        // Benutzerdefinierter Titel
   description="Beschreibung" // Benutzerdefinierte Beschreibung
   theme="modern"            // Design: default, compact, modern
   width="600px"             // Breite: px, %, auto
   class="my-class"          // Zusätzliche CSS-Klassen
]
```

## 📚 Formeln und Funktionen

### Mathematische Funktionen
```javascript
// Grundrechenarten
field_1 + field_2         // Addition
field_1 - field_2         // Subtraktion
field_1 * field_2         // Multiplikation
field_1 / field_2         // Division
field_1 ^ field_2         // Potenz

// Erweiterte Funktionen
ABS(field_1)              // Absolutwert
SQRT(field_1)             // Quadratwurzel
POW(field_1, 2)           // Potenz
RUNDEN(field_1, 2)        // Runden auf 2 Dezimalstellen
MIN(field_1, field_2)     // Minimum
MAX(field_1, field_2)     // Maximum
```

### Aggregationsfunktionen
```javascript
SUMME(field_1, field_2, field_3)     // Summe
MITTELWERT(field_1, field_2, field_3) // Durchschnitt
```

### Bedingte Logik
```javascript
// Einfache Bedingung
WENN(field_1 > 1000, field_1 * 0.1, field_1 * 0.05)

// Verschachtelte Bedingungen
WENN(field_1 > 10000, 
     WENN(field_2 > 5, field_1 * 0.15, field_1 * 0.1), 
     field_1 * 0.05)

// Vergleichsoperatoren
field_1 > field_2         // Grösser als
field_1 >= field_2        // Grösser oder gleich
field_1 < field_2         // Kleiner als
field_1 <= field_2        // Kleiner oder gleich
field_1 = field_2         // Gleich
field_1 != field_2        // Ungleich
```

### Datumsfunktionen
```javascript
HEUTE()                   // Aktuelles Datum (YYYYMMDD)
JAHR(HEUTE())            // Aktuelles Jahr
MONAT(HEUTE())           // Aktueller Monat
TAG(HEUTE())             // Aktueller Tag
```

## 🎨 Anpassungen

### CSS-Anpassungen
```css
/* Kalkulator-Container */
.ecp-calculator {
    background: #f9f9f9;
    border-radius: 15px;
}

/* Eingabefelder */
.ecp-input-field {
    border-color: #007cba;
}

/* Ausgabefelder */
.ecp-output-field {
    background: linear-gradient(135deg, #e8f4f8, #ffffff);
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .ecp-calculator {
        padding: 15px;
    }
}
```

### JavaScript-API
```javascript
// Kalkulator-Instanz abrufen
const calculator = ECPCalculator.getInstance('#mein-kalkulator');

// Wert setzen
calculator.setValue('field_1', 1000);

// Wert abrufen
const value = calculator.getValue('field_1');

// Neu berechnen
calculator.recalculate();

// Ergebnisse abrufen
const results = calculator.getResults();

// Alle Kalkulatoren neu berechnen
ECPCalculator.recalculateAll();
```

## 📋 Vorlagen

### Vorgefertigte Vorlagen
1. **Kreditrechner**
   - Kreditsumme, Zinssatz, Laufzeit
   - Berechnet monatliche Rate und Gesamtkosten

2. **ROI-Rechner**
   - Investition, Gewinn
   - Berechnet Return on Investment

3. **BMI-Rechner**
   - Gewicht, Grösse
   - Berechnet Body Mass Index und Kategorie

### Eigene Vorlage erstellen
1. Kalkulator erstellen und konfigurieren
2. Als Vorlage exportieren
3. JSON-Datei speichern
4. Bei Bedarf importieren

## 🔧 Import/Export

### Kalkulator exportieren
1. **Calculator Pro** → **Import/Export**
2. Kalkulator auswählen
3. **"Exportieren"** klicken
4. JSON-Datei herunterladen

### Kalkulator importieren
1. **Calculator Pro** → **Import/Export**
2. JSON-Datei auswählen
3. **"Importieren"** klicken
4. Kalkulator wird erstellt

## 🐛 Troubleshooting

### Häufige Probleme

**Kalkulator wird nicht angezeigt**
- Shortcode korrekt? `[excel_calculator id="1"]`
- Plugin aktiviert?
- Kalkulator existiert?

**Berechnungen funktionieren nicht**
- JavaScript-Fehler in Browser-Konsole prüfen
- Formeln auf Syntax prüfen
- Feldnamen korrekt verwendet?

**Responsive Probleme**
- Cache leeren
- Theme-Konflikte prüfen
- CSS-Überschreibungen prüfen

### Debug-Modus
```php
// In wp-config.php
define('WP_DEBUG', true);
define('ECP_DEBUG', true);
```

Zeigt detaillierte Formel-Informationen im Frontend an.

## 🔒 Sicherheit

### Validierung
- Alle Eingaben werden validiert
- SQL-Injection-Schutz
- XSS-Schutz durch Escaping
- Sichere Formel-Evaluierung

### Berechtigungen
- Nur Administratoren können Kalkulatoren verwalten
- Frontend-Berechnungen sind read-only
- Nonce-Verifizierung für alle AJAX-Requests

## 🚀 Performance

### Optimierungen
- **Lazy Loading**: Scripts nur bei Bedarf
- **Debouncing**: Verzögerte Berechnungen
- **Caching**: Minimale Datenbankzugriffe
- **Minifizierung**: Komprimierte Assets

### Monitoring
- Performance-Warnungen bei vielen Kalkulatoren
- Automatische Optimierungen
- Error-Logging

## 🔄 Updates

### Automatische Updates
- WordPress Auto-Updates unterstützt
- Sicherheitsupdates priorisiert
- Datenbank-Migrationen automatisch

### Changelog prüfen
Immer vor Updates das Changelog lesen und Backup erstellen.

## 📞 Support

### Dokumentation
- [Online-Dokumentation](https://ihre-website.com/docs)
- [Video-Tutorials](https://ihre-website.com/tutorials)
- [FAQ](https://ihre-website.com/faq)

### Community
- [Forum](https://ihre-website.com/forum)
- [GitHub Issues](https://github.com/ihr-username/excel-calculator-pro)
- [Discord](https://discord.gg/excel-calculator-pro)

### Professioneller Support
- E-Mail: support@ihre-website.com
- Reaktionszeit: 24h
- Kostenpflichtige Anpassungen verfügbar

## 📄 Lizenz

GPL v2 oder höher - siehe [LICENSE](LICENSE) Datei.

## 🤝 Mitwirken

Beiträge sind willkommen! Siehe [CONTRIBUTING.md](CONTRIBUTING.md) für Details.

### Entwicklung
```bash
# Repository klonen
git clone https://github.com/ihr-username/excel-calculator-pro.git

# Dependencies installieren
npm install

# Development Server starten
npm run dev

# Build für Produktion
npm run build
```

## 🏆 Credits

Entwickelt mit ❤️ für die WordPress-Community.

**Hauptentwickler**: Ihr Name  
**Contributors**: [Liste der Mitwirkenden](CONTRIBUTORS.md)  
**Icons**: [Dashicons](https://developer.wordpress.org/resource/dashicons/)  
**Testing**: WordPress-Community

---

⭐ **Gefällt Ihnen das Plugin?** Geben Sie uns einen Stern auf GitHub!

📧 **Fragen?** Kontaktieren Sie uns: support@ihre-website.com