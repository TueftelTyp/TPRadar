<?php
// Vorlage: als private/setup.php speichern, nur fuer die Ersteinrichtung.
// Nach erfolgreicher TOTP-Einrichtung private/setup.php wieder loeschen.
// private/ darf niemals im oeffentlichen Webverzeichnis liegen.
return [
    // Gleiche HTTPS-Adresse wie in private/config.php, ohne Pfad.
    'site_origin' => 'https://example.org',
    // Zufallswert mit mindestens 32 Zeichen aus einem Passwortmanager.
    'setup_key' => 'REPLACE_WITH_32_OR_MORE_RANDOM_CHARACTERS',
];
