<?php
// Rydder PHP opcache og bekræfter fil-versioner.
// Åbn denne én gang i browseren, slet den bagefter.
header('Content-Type: text/plain; charset=utf-8');
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache_reset() kaldt — cache ryddet.\n\n";
} else {
    echo "opcache ikke tilgængelig (ingen reset nødvendig).\n\n";
}

echo "=== api.php ===\n";
echo "Størrelse: " . filesize(__DIR__ . '/api.php') . " bytes\n";
$src = file_get_contents(__DIR__ . '/api.php');
echo "Indeholder 'buildStatus'-funktion: " . (strpos($src, 'buildStatus') !== false ? 'JA (ny version)' : 'NEJ (gammel version)') . "\n";
echo "Indeholder 'legal' i status-svar: " . (strpos($src, "'legal'") !== false ? 'JA (ny version)' : 'NEJ (gammel version)') . "\n\n";

echo "=== app.js ===\n";
echo "Størrelse: " . filesize(__DIR__ . '/app.js') . " bytes\n";
$js = file_get_contents(__DIR__ . '/app.js');
echo "Indeholder 'd.legal': " . (strpos($js, 'd.legal') !== false ? 'JA (ny version)' : 'NEJ (gammel version)') . "\n";
