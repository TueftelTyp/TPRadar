# TP-Radar 5.0.1 – Self-Hosting-Anleitung

Diese Ausgabe ist eine **frische, installationsneutrale Veröffentlichung** ohne Benutzerdaten, echte Passwörter, Domain-Zuordnung oder Datenbankexport. Die Software ist für die Installation im **Domain-Stammverzeichnis** ausgelegt (nicht in einem Unterordner, da die Admin-Cookies `/adm` verwenden).

## Voraussetzungen

- PHP **8.1 oder neuer** mit `pdo_mysql`, `mbstring`, `session`, `hash`, `json` und `openssl` (PHP-Standard-Erweiterungen nach Hosting-Umgebung)
- MySQL oder MariaDB, Zugriff auf Datenbank und einmalig `CREATE`-Berechtigung zum Import
- HTTPS/TLS mit gültigem Zertifikat und HTTPS-Unterstützung in PHP
- Eigene Domain bzw. Subdomain, deren Document Root auf `public/` zeigt
- Zugriff auf Webspace-Dateien und idealerweise phpMyAdmin
- Authenticator-App für die einmalige Admin-Einrichtung

## Projektstruktur

```text
TP-Radar/
├── README.md                 # Kurzbeschreibung
├── INSTALLATION.md           # Diese Anleitung
├── schema.sql                # NUR fuer eine neue, leere Datenbank
├── private/                  # AUSSERHALB der Domain-Document-Root
│   ├── config.example.php    # Vorlage fuer config.php
│   ├── setup.example.php     # Vorlage fuer setup.php
│   └── moderation.php        # Einstellungen der Preispruefung
└── public/                   # Document Root: Nur dieser Ordner ist im Web erreichbar
    ├── index.html, app.js, api.php, sw.js, manifest.webmanifest
    ├── favicon.ico, favicon-*.png, apple-touch-icon.png, icons/
    └── adm/                  # Geschuetztes Admin-Frontend und Einrichtungsassistent
```

## 1. Datenbank erstellen

1. Erstelle in deinem Hosting-Panel eine **neue, leere** MySQL-/MariaDB-Datenbank und einen eigenen Datenbankbenutzer.
2. Melde dich bei phpMyAdmin an und wähle **explizit deine neue Datenbank** aus.
3. Importiere `schema.sql`. Das Skript hat **keinen** `DROP`- oder `USE`-Befehl; es legt genau acht TP-Radar-Tabellen und einen Sicherheitsstartdatensatz an.
4. Bei bereits vorhandenen TP-Radar-Tabellen **nicht erneut importieren**. Dies ist kein Update-Skript; sichere bei bestehenden Installationen zuerst die Daten.
5. Prüfe optional in phpMyAdmin:

   ```sql
   SHOW TABLES;
   SELECT id,last_totp_step FROM admin_security_state;
   ```

   Die letzte Abfrage sollte `1` und `-1` zurückgeben.

## 2. Webverzeichnis korrekt setzen

Lege auf dem Webspace die Verzeichnisse `public/` und `private/` als **Geschwisterordner** ab. Konfiguriere den Document Root deiner Domain auf `public/` – **niemals** auf den Projektordner. Nur Dateien unter `public/` dürfen per HTTP abrufbar sein. Auf Shared Hosting ohne änderbaren Document Root muss `private/` in einem garantiert nicht öffentlich erreichbaren Verzeichnis liegen und die Pfadstruktur entsprechend angepasst werden; diese Variante ist nicht ohne Codeanpassung unterstützt.

Beispiel auf einem Webspace:

```text
/home/account/tp-radar/private/
/home/account/tp-radar/public/       <- Document Root deiner Domain
```

Teste: `https://DEINE-DOMAIN/` zeigt die App; `https://DEINE-DOMAIN/adm/version.php` zeigt `TP-Radar Admin 5.0.1`. Private Dateien müssen per URL **nicht** abrufbar sein.

## 3. Private Konfiguration anlegen

Kopiere `private/config.example.php` nach `private/config.php` und trage die **eigenen** Werte ein:

- `site_origin`: `https://DEINE-DOMAIN` (exakt, ohne abschließenden Slash oder Pfad)
- `dsn`: MySQL-Host, Port, **eigener** Datenbankname; `charset=utf8mb4` beibehalten
- `user`/`password`: eigener MySQL-Benutzer und dessen Passwort
- `secret`: **eigener** zufälliger geheimer Wert, mindestens 32 Zeichen (besser 64), aus einem Passwortmanager

Bei einem produktiven Webspace erhält der Anwendungskonto nur die erforderlichen **SELECT, INSERT, UPDATE, DELETE**-Rechte; Schemaänderungen werden nur für die Einrichtung benötigt. Die Zugangsdaten gehören **nicht** ins ZIP und **nicht** ins `public/`-Verzeichnis. Sichere `private/` gegen unbefugte Zugriffe und nimm die privaten Konfigurationsdateien aus Git-Repositories heraus.

Optional lässt sich `private/moderation.php` anpassen: `enabled`, Abweichungsschwellen und benötigte Bestätigungen. Eingabevalidierung/Rate-Limits bleiben auch bei deaktivierter Moderation aktiv.

## 4. Administrator mit TOTP einrichten – ohne lokales PHP

1. Kopiere `private/setup.example.php` nach `private/setup.php`.
2. Trage unter `site_origin` dieselbe HTTPS-Adresse wie in `private/config.php` ein.
3. Setze `setup_key` auf eine neue zufällige Zeichenfolge mit **mindestens 32 Zeichen** aus einem Passwortmanager. Dieser Schlüssel ist **nicht** dein Admin-Passwort.
4. Rufe `https://DEINE-DOMAIN/adm/setup.php` auf.
5. Gib Einrichtungsschlüssel, gewünschten Benutzernamen und ein eigenes Admin-Passwort (mindestens 16 Zeichen) ein.
6. Scanne den angezeigten QR-Code mit einer Authenticator-App (TOTP, sechs Stellen, 30 Sekunden) und bestätige einen gültigen Code.
7. **Nach erfolgreicher Einrichtung `private/setup.php` vom Server löschen.** Ein geheimes TOTP-Backup sicher, getrennt vom Passwort, verwahren.
8. Melde dich unter `https://DEINE-DOMAIN/adm/` an.

Der Administrator wird erst durch den erfolgreichen TOTP-Schritt aktiviert. Das Authenticator-Geheimnis wird nicht an einen QR-Dienst übertragen. Ein öffentliches Benutzer-Registrierungssystem existiert nicht.

## 5. Anwendung prüfen

1. `https://DEINE-DOMAIN/api.php?action=list` liefert JSON mit `ok: true` und zu Beginn leere Arrays.
2. Die App lädt unter `/`, das Favicon ist unter `/favicon.ico` erreichbar.
3. `/adm/version.php` zeigt die passende Version.
4. Admin-Setup, TOTP-Anmeldung und Testgeschäft anlegen; Änderung im Admin-Protokoll kontrollieren.
5. Testprodukt und Preis hinzufügen. In einer frischen Datenbank wird ein gültiger erster Preis direkt veröffentlicht.
6. Auf einem Smartphone PWA-Installation und automatischen Dark Mode prüfen. Kamera-/Barcode-Funktionen sind browserabhängig.

## Fehlerbehebung

- **Datenbankverbindung fehlgeschlagen:** `private/config.php` auf Datenbankname, Host, Benutzer, Passwort prüfen. MySQL-Login und Rechte auf dieser Datenbank kontrollieren.
- **Admin-Migration fehlt:** `schema.sql` muss vollständig in genau dieser Datenbank importiert sein. Nicht auf Verdacht in eine bestehende DB erneut importieren.
- **Einrichtung bereits abgeschlossen:** `private/admin.php` existiert. Nicht unüberlegt entfernen; sie enthält Hash und TOTP-Secret. Nur mit sicherem Backup und bewusstem Reset neu einrichten.
- **Zugriff verweigert:** HTTPS, `site_origin`, Browser-Sitzung/Cookies und Sicherheits-Header prüfen; nicht CSRF-Schutz abschalten.
- **PHP 404 / alte Version:** Document Root, `public/`-Upload und `/adm/version.php` prüfen.
- **MySQL-Import schlägt fehl:** Stimmt die ausgewählte Datenbank? Haben die verwendeten Import-Zugangsdaten `CREATE`-Rechte? Sind dort schon Tabellen vorhanden?
- **PWA zeigt alte Oberfläche:** Seite aktualisieren, bestehende App vollständig schließen, Service-Worker-Cache im Browser prüfen.

## Sicherheit und Betrieb

- Die öffentliche App ist ohne Benutzerkonten zugänglich. Eine Gerätekennung ist **keine verlässliche Identitätsprüfung**; Community-Preise können manipuliert sein.
- Das Admin-Passwort wird gehasht, TOTP ist erforderlich, Admin-POST-Anfragen benötigen einen CSRF-Token, und Login-Versuche werden begrenzt.
- HTTPS und eine sichere Trennung von `public/` und `private/` sind Pflicht.
- Regelmäßige Datenbankbackups und PHP-Sicherheitsupdates durchführen. Die Offline-PWA speichert nur die öffentliche Oberfläche, nicht die Admin-API oder Preisdaten.
- Die mitgelieferte QR-Code-Bibliothek enthält eine eigene Lizenzdatei in `public/adm/QR-LICENSE.txt`.
- Diese Ausgabe ist für **Neuinstallationen**. Für bestehende Installationen ist ein gesondertes Datenbank- und Dateimigrationsverfahren nötig.

## Teststatus

Quellcode kann statisch auf Syntax und Referenzen überprüft werden. Ein vollständiger MySQL-, HTTPS-, PWA- und TOTP-Live-Test muss beim jeweiligen Hosting-Anbieter erfolgen. Keine Garantie für jeden Shared-Hosting-Proxy oder Browser.
