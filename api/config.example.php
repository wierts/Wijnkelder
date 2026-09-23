<?php
// Kopieer dit bestand naar config.php en vul je eigen gegevens in.
// config.php wordt NIET meegenomen in git (zie .gitignore) en blijft
// dus alleen op de server staan — je wachtwoord komt nooit in de repo.

define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Etiketscan (Google Gemini, gratis sleutel via https://aistudio.google.com/apikey)
// Plak je sleutel tussen de aanhalingstekens:
define('GEMINI_API_KEY', '');
// Optioneel ander model: define('GEMINI_MODEL', 'gemini-3.5-flash-lite');
