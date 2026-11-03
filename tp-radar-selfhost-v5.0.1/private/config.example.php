<?php
// Vorlage: als private/config.php speichern. private/ liegt AUSSERHALB von public/.
return [
    // Exakt die eigene HTTPS-Adresse, ohne Pfad oder abschliessenden Slash.
    'site_origin' => 'https://example.org',
    'dsn' => 'mysql:host=localhost;port=3306;dbname=YOUR_DATABASE;charset=utf8mb4',
    'user' => 'YOUR_DATABASE_USER',
    'password' => 'YOUR_DATABASE_PASSWORD',
    // Mindestens 32 zufaellige Zeichen; fuer jede Installation EINEN EIGENEN Wert erzeugen.
    'secret' => 'CHANGE_TO_A_UNIQUE_RANDOM_64_CHARACTER_SECRET',
];
