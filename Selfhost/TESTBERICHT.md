# TP-Radar 5.0.2 – Prüfung und Grenzen

## Codekorrekturen

- Ungültige UTF-8-Texte in der öffentlichen API und der Admin-API liefern eine verständliche 400-Meldung statt eines PHP-Typfehlers.
- Wiederverwendung einer deaktivierten EAN wird eindeutig mit HTTP 409 statt einem generischen Datenbankfehler abgelehnt.
- Neu angelegte aktive Geschäfte werden ohne bestehende Preismeldung in den Vorschlagslisten angezeigt.
- Der PWA-Service-Worker löscht ausschließlich TP-Radar-Caches; Offline-Fallback nur für die App-Startseite.
- Datei- und Cacheversionen auf 5.0.2 vereinheitlicht.

## Reproduzierbare Tests

Im Projektstamm: `python3 tests/test_static.py` und `php -d zend.assertions=1 -d assert.exception=1 tests/test_totp.php`. Zur Syntaxprüfung: `find . -name "*.php" -exec php -l {} \;` sowie `node --check` für die JavaScript-Dateien.

## Testumfang

- PHP-Lint für alle PHP-Dateien.
- Node-Syntaxprüfung für eigene JS-Dateien und die gebündelte QR-Bibliothek.
- JSON-Validierung des Web-App-Manifests, PNG-Bildprüfung und ZIP-Integrität.
- Statische Konsistenzprüfung der projektinternen Referenzen, Datenbanktabellen und öffentlichen Verzeichnisgrenzen.
- TOTP-Prüfung anhand des öffentlichen RFC-6238-Testvektors (separates reproduzierbares Testskript).

## Noch durch Betreiber zu prüfen

Eine echte MySQL-/MariaDB-Integration, eine HTTPS-Session auf dem Zielwebspace, der vollständige Browser/TOTP-Prozess, PWA-Installation, Kamera und reale Lasttests sind ohne die Zielumgebung nicht vollständig verifizierbar. Die Anwendung verarbeitet nutzergenerierte Preise; eine manipulationssichere Identitätsprüfung ist ohne Benutzerkonten nicht möglich.
