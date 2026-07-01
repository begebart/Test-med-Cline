<?php
// Diagnostics - slet denne fil efter brug!
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title>Diagnose - Velkendt.com</title>
    <style>
        body { font-family: monospace; padding: 20px; font-size: 14px; }
        .ok { color: green; font-weight: bold; }
        .fejl { color: red; font-weight: bold; }
        .info { color: #333; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        h2 { margin-top: 30px; border-bottom: 2px solid #333; }
    </style>
</head>
<body>
<h1>🔍 Diagnose for Velkendt.com</h1>

<h2>1. Stier</h2>
<p>DB_FILE: <code><?php echo DB_FILE; ?></code></p>
<p>LOG_DIR: <code><?php echo LOG_DIR; ?></code></p>
<p>__DIR__: <code><?php echo __DIR__; ?></code></p>

<h2>2. reports.json</h2>
<?php
if (file_exists(DB_FILE)) {
    $perms = substr(sprintf('%o', fileperms(DB_FILE)), -4);
    $size  = filesize(DB_FILE);
    $write = is_writable(DB_FILE);
    echo "<p class='ok'>✓ Filen eksisterer</p>";
    echo "<p>Størrelse: <b>$size bytes</b></p>";
    echo "<p>Tilladelser: <b>$perms</b></p>";
    echo "<p>Skrivbar: <b>" . ($write ? "<span class='ok'>JA</span>" : "<span class='fejl'>NEJ</span>") . "</b></p>";
    $data = json_decode(file_get_contents(DB_FILE), true);
    echo "<p>Antal rapporter: <b>" . count($data ?? []) . "</b></p>";
    if (!empty($data)) {
        echo "<pre>" . htmlspecialchars(json_encode(array_slice($data, 0, 2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    }
} else {
    echo "<p class='fejl'>✗ Filen eksisterer IKKE</p>";
    // Prøv at oprette den
    $result = file_put_contents(DB_FILE, '[]');
    if ($result !== false) {
        echo "<p class='ok'>✓ Oprettet tom reports.json</p>";
    } else {
        echo "<p class='fejl'>✗ Kan ikke oprette filen! Tjek mappe-rettigheder.</p>";
    }
}
?>

<h2>3. logs/ mappe</h2>
<?php
if (is_dir(LOG_DIR)) {
    $write = is_writable(LOG_DIR);
    echo "<p class='ok'>✓ Mappen eksisterer</p>";
    echo "<p>Skrivbar: <b>" . ($write ? "<span class='ok'>JA</span>" : "<span class='fejl'>NEJ</span>") . "</b></p>";
} else {
    echo "<p class='fejl'>✗ Mappen eksisterer ikke - forsøger at oprette...</p>";
    if (mkdir(LOG_DIR, 0755, true)) {
        echo "<p class='ok'>✓ Oprettet!</p>";
    } else {
        echo "<p class='fejl'>✗ Kunne ikke oprette mappen</p>";
    }
}
?>

<h2>4. Test: Gem rapport nu</h2>
<?php
$test_text = "TEST: Denne besked er en diagnostisk test fra " . date('Y-m-d H:i:s');
$test_results = analyzeMessage($test_text, 'sms');
$test_id = saveReport($test_text, 'sms', $test_results);

if ($test_id) {
    echo "<p class='ok'>✓ Rapport gemt! ID: <b>$test_id</b></p>";
    
    // Tjek den faktisk er der
    $reports = getReports(5);
    echo "<p>Antal rapporter efter test: <b>" . count($reports) . "</b></p>";
    if (count($reports) > 0) {
        echo "<p class='ok'>✓ Hentning virker!</p>";
    }
} else {
    echo "<p class='fejl'>✗ Kunne ikke gemme rapport!</p>";
}
?>

<h2>5. Test: API endpoint</h2>
<?php
$url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api.php?action=reports&limit=5';
echo "<p>API URL: <code>$url</code></p>";

$response = @file_get_contents($url);
if ($response !== false) {
    $data = json_decode($response, true);
    echo "<p class='ok'>✓ API svarer</p>";
    echo "<p>Antal rapporter i API: <b>" . count($data['reports'] ?? []) . "</b></p>";
    if (!empty($data['reports'])) {
        echo "<p class='ok'>✓ Forum data tilgængeligt!</p>";
    } else {
        echo "<p class='fejl'>! API returnerer ingen rapporter endnu</p>";
    }
} else {
    echo "<p class='fejl'>✗ Kunne ikke kalde API - allow_url_fopen er måske slået fra</p>";
}
?>

<h2>6. PHP info</h2>
<p>PHP version: <b><?php echo PHP_VERSION; ?></b></p>
<p>allow_url_fopen: <b><?php echo ini_get('allow_url_fopen') ? 'ON' : 'OFF'; ?></b></p>
<p>max_execution_time: <b><?php echo ini_get('max_execution_time'); ?>s</b></p>

<hr>
<p><a href="index.php">← Tilbage til forsiden</a> | <b style="color:red">Slet denne fil fra serveren efter brug!</b></p>
</body>
</html>
