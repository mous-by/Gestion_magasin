<?php

// Routes Web (session, guard `web`). Un fichier par module dans routes/web/*.php (D7) : ils sont chargés
// automatiquement, dans l'ordre alphabétique, et héritent du groupe de middleware `web`.
// /up (santé) est déclarée dans bootstrap/app.php.

$files = glob(__DIR__.'/web/*.php') ?: [];
sort($files);

foreach ($files as $file) {
    require $file;
}
