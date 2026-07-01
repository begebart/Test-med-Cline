<?php
require_once 'config.php';

// Hent alle rapporter direkte fra database
$alle_rapporter = getReports(50);

// Filtrer - kun delte rapporter vises i arkiv
$rapporter = array_filter($alle_rapporter, function($r) {
    return !empty($r['shared_in_forum']);
});
$rapporter = array_values($rapporter);

// Risiko label
function risikoLabel($score) {
    if ($score >= 70) return ['tekst' => '🔴 HØJ RISIKO', 'klasse' => 'high-risk'];
    if ($score >= 40) return ['tekst' => '🟠 MIDDEL RISIKO', 'klasse' => 'medium-risk'];
    if ($score >= 20) return ['tekst' => '🟡 LAV RISIKO', 'klasse' => 'low-risk'];
    return ['tekst' => '🟢 MEGET LAV RISIKO', 'klasse' => 'low-risk'];
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arkiv - Velkendt.com</title>
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
        <section class="forum">
            <h2>📋 Arkiv - Delte analyser</h2>
            <p class="forum-intro">
                Her vises analyser som andre brugere har valgt at dele. 
                Brug det til at lære om typiske svindel-mønstre.
            </p>
            
            <div style="margin-bottom: 20px;">
                <a href="index.php" class="btn-secondary" style="display:inline-block; text-decoration:none; text-align:center;">
                    🔍 Tjek en besked
                </a>
            </div>

            <?php if (empty($rapporter)): ?>
                <div class="no-reports">
                    <p>Ingen rapporter delt endnu.</p>
                    <p>Vær den første! Analyser en besked og klik "Del i forum".</p>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    Viser <strong><?php echo count($rapporter); ?></strong> delte analyser
                </p>
                <div class="reports-list">
                    <?php foreach ($rapporter as $rapport): 
                        $risiko = risikoLabel($rapport['results']['score']);
                        $preview = mb_substr($rapport['text'], 0, 120);
                        if (mb_strlen($rapport['text']) > 120) $preview .= '...';
                        $dato = date('j. M Y \k\l. H:i', strtotime($rapport['forum_timestamp'] ?? $rapport['timestamp']));
                        $kommentar = $rapport['results']['user_comment'] ?? '';
                    ?>
                    <div class="report-card <?php echo $risiko['klasse']; ?>">
                        <div class="report-header">
                            <span class="report-type">
                                <?php echo $rapport['type'] === 'email' ? '📧 Email' : '📱 SMS'; ?>
                            </span>
                            <span class="report-timestamp"><?php echo htmlspecialchars($dato); ?></span>
                        </div>
                        <div class="report-preview">
                            <?php echo htmlspecialchars($preview); ?>
                        </div>
                        <div class="report-score">
                            <span class="score-badge <?php echo $rapport['results']['score'] >= 70 ? 'high' : ($rapport['results']['score'] >= 40 ? 'medium' : 'low'); ?>">
                                <?php echo $rapport['results']['score']; ?>% risiko
                            </span>
                            <span class="report-verdict">
                                <?php echo htmlspecialchars($rapport['results']['verdict']); ?>
                            </span>
                        </div>
                        <?php if (!empty($rapport['results']['warnings'])): ?>
                        <div class="report-warnings" style="margin-top: 10px;">
                            <strong>⚠️ <?php echo count($rapport['results']['warnings']); ?> advarsler fundet</strong>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($kommentar)): ?>
                        <div class="report-comment" style="margin-top: 10px; font-style: italic; color: #6b7280;">
                            💬 <?php echo htmlspecialchars($kommentar); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>© 2026 Velkendt.com - Hjælper danskere med at undgå svindel</p>
        </div>
    </footer>
</body>
</html>
