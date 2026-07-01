<?php
require_once 'config.php';

$report_id = trim($_POST['report_id'] ?? '');

if (empty($report_id)) {
    header('Location: index.php?msg=fejl');
    exit;
}

// Find rapporten og marker den som delt
$success = false;
if (file_exists(DB_FILE)) {
    $reports = json_decode(file_get_contents(DB_FILE), true) ?: [];
    foreach ($reports as &$report) {
        if ($report['id'] === $report_id) {
            $report['shared_in_forum'] = true;
            $report['forum_timestamp']  = date('Y-m-d H:i:s');
            $success = true;
            break;
        }
    }
    unset($report);

    if ($success) {
        file_put_contents(DB_FILE, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logActivity('Rapport delt i forum', "ID: $report_id");
    }
}

// Vis bekræftelsesside
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delt i forum - Velkendt.com</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>🛡️ Velkendt.com</h1>
            <p class="tagline">Tjek om din SMS eller Email er ægte</p>
        </div>
    </header>

    <main class="container">
        <section class="analyzer" style="text-align: center; padding: 60px 30px;">
            <?php if ($success): ?>
                <div style="font-size: 4em; margin-bottom: 20px;">✅</div>
                <h2>Tak! Din rapport er delt.</h2>
                <p style="margin: 20px 0; font-size: 1.2em; color: #6b7280;">
                    Andre kan nu se din analyse og lære af den.
                </p>
            <?php else: ?>
                <div style="font-size: 4em; margin-bottom: 20px;">⚠️</div>
                <h2>Rapport ikke fundet</h2>
                <p style="margin: 20px 0; font-size: 1.2em; color: #6b7280;">
                    Rapporten kunne ikke deles. Prøv igen.
                </p>
            <?php endif; ?>

            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="arkiv.php" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 15px 40px;">
                    📋 Se arkiv med alle delte analyser
                </a>
                <a href="index.php" class="btn-secondary" style="display: inline-block; text-decoration: none; padding: 15px 40px;">
                    🔍 Ny analyse
                </a>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>© 2026 Velkendt.com - Hjælper danskere med at undgå svindel</p>
        </div>
    </footer>
</body>
</html>
