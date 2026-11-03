<?php
// Nach private/moderation.php kopieren. Kein Geheimnis enthalten.
return [
    // true: Warnung bei >10%, Zurueckhalten bei >30%, Bestaetigungen erforderlich.
    // false: neue valide Meldungen direkt veroeffentlichen (Eingabepruefung, Duplikate
    // und Rate-Limits bleiben aktiv). Bestehende pending bleiben unveraendert.
    'enabled' => true,
    'warning_threshold' => 0.10,
    'hold_threshold' => 0.30,
    'confirmations_required' => 2,
];
