# TP-Radar

**TP-Radar** ist eine selbst hostbare, deutschsprachige Community-Web-App zum Vergleich von Toilettenpapierpreisen. Sie bewertet Angebote unter anderem nach Preis pro Blatt, pro Rolle und unter Berücksichtigung der Lagen.

## Funktionen

- Responsive Oberfläche mit automatischem Dark Mode und PWA-Installation
- Produkt- und Preismeldungen ohne Registrierung, optional mit Barcode-Erkennung
- Preisverlauf, Vergleichsansichten und Community-Bestätigungen
- Konfigurierbare Plausibilitätsprüfung für neue Preise
- Geschützter Adminbereich für Produkte, Geschäfte, Preise und Protokolle
- Administrator-Anmeldung mit Passwort und zeitbasiertem Einmalcode (TOTP)

## Technik

HTML, CSS und Vanilla JavaScript im Frontend; PHP 8.1+, PDO MySQL und MySQL/MariaDB im Backend. HTTPS ist für die sichere Einrichtung und den PWA-Betrieb erforderlich. Keine öffentlichen Benutzerkonten.

## Selbst hosten

Die **vollständige Schritt-für-Schritt-Anleitung** steht in [INSTALLATION.md](https://github.com/TueftelTyp/TPRadar/blob/main/tp-radar-selfhost-v5.0.1/README.md). Die leere Datenbank wird mit `schema.sql` angelegt. Eigene Zugangsdaten gehören ausschließlich in `private/config.php` außerhalb des Webverzeichnisses.

**Hinweis:** Bei diesem Projekt handelt es sich um eine Community-Anwendung; Preise werden von Nutzern gemeldet und sind nicht garantiert korrekt oder aktuell. Ein Live-Sicherheitstest auf jeder Hosting-Plattform ist nicht enthalten.
