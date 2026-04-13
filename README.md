# MMBbS Didaktische Jahresplanung

Ein webbasiertes Tool zur Erstellung und Visualisierung didaktischer Jahresplanungen für Berufsschulklassen. Das Tool zur didaktischen Jahresplanung ermöglicht es, Fächer, Lernfelder und Lernsituationen strukturiert zu erfassen und in einer übersichtlichen Blockplanung darzustellen. Die Datenhaltung erfolgt rein dateibasiert über eine JSON-Datei, was die Installation und Backups extrem vereinfacht.

## 🚀 Features

  - **Zwei-Modul-System:**
      - **Viewer (`index.php`):** Öffentliche Ansicht der fertigen Jahresplanungen für Schüler und Lehrer. Bietet eine Filterfunktion nach Klassen.
      - **Admin-Bereich (`admin.php`):** Passwortgeschützter Bereich zum Anlegen, Bearbeiten und Verwalten von Klassen, Vorlagen und Planungsdaten.
  - **Klassen- & Vorlagenverwaltung:**
      - Anlegen von spezifischen Klassen (z.B. "FISI24A").
      - Erstellen von Vorlagen, um Planungsstrukturen für neue Klassenjahresgänge wiederzuverwenden.
  - **Detaillierte Fachplanung:**
      - Erfassung von Fächern/Lernfeldern mit Kürzel, vollem Namen, Soll-Stunden und Bereich (Berufsbezogen/Übergreifend).
  - **Lernsituationen-Editor:**
      - Definition von Lernsituationen (LS) mit Titel, Nummer, Dauer (Stunden) und zeitlicher Einordnung (Start-/Endwoche).
      - Individuelle Farbkodierung für jede Lernsituation zur visuellen Abgrenzung im Diagramm.
  - **Interaktive Blockplanung:** Grafische Darstellung der Lernsituationen als farbige Balken über 13 Planungsblöcke im Viewer.
  - **Backup & Import:**
      - Exportfunktion für einzelne Klassen/Vorlagen als JSON-Datei.
      - Importfunktion für Backups mit optionaler Namensänderung und Typkonvertierung (Klasse \<-\> Vorlage).
  - **Sicherheit:** Einfacher Passwortschutz für den Admin-Bereich.
  - **Responsive UI:** Modernes, klares Design (angelehnt an Tailwind/GitHub-Style) mit Schullogo-Integration.

## 🛠️ Installation & Konfiguration

1.  **Voraussetzungen:**

      - Webserver mit PHP 7.4 oder höher (z.B. Apache, Nginx).
      - Schreibrechte im Projektverzeichnis für die JSON-Datenbank.

2.  **Dateien kopieren:**
    Lade alle Projektdateien (`index.php`, `admin.php`, `didakt_data.json`, `logo.png`) in ein Verzeichnis auf deinem Webserver hoch.

3.  **Berechtigungen:**
    Stelle sicher, dass der Webserver Schreibzugriff auf die Datei `didakt_data.json` hat:

    ```bash
    chmod 664 didakt_data.json
    ```

4.  **Admin-Passwort ändern:**
    Öffne die `admin.php` in einem Texteditor und ändere das Standardpasswort in Zeile 10:

    ```php
    $password = "DEIN_NEUES_PASSWORT"; // Standard: MMBbS2026
    ```

## 📊 Dateistruktur

  - `index.php`: Das öffentliche Frontend zur Ansicht der Jahresplanungen.
  - `admin.php`: Der geschützte Backend-Bereich zur Datenverwaltung.
  - `didakt_data.json`: Die zentrale Datenbank im JSON-Format. Enthält alle Klassen, Vorlagen und Planungsdaten.
  - `logo.png`: Das Logo der Multi-Media-Berufsbildende Schulen Hannover (MMBbS).

## 🖥️ Verwendete Technologien

  - **Backend:** PHP (rein serverseitig, keine externe DB erforderlich).
  - **Frontend:** HTML5, CSS3 (Grid-Layout für die Planung, modernes UI-Design).
  - **Datenformat:** JSON.

## 📄 Lizenz

Dieses Projekt ist freie Software und lizenziert unter der **GNU Affero General Public License v3.0 (AGPL-3.0)** – siehe die [LICENSE](https://www.google.com/search?q=LICENSE) Datei für Details. Der Quellcode ist im Repository verfügbar.

-----

*Entwickelt für die Multi Media Berufsbildenden Schulen Hannover (MMBbS).*