<?php
require_once 'config.php';

// Get form data
$text = trim($_POST['text'] ?? '');
$type = $_POST['type'] ?? 'email';

if (empty($text)) {
    header('Location: index.php');
    exit;
}

// Validate type
if (!in_array($type, ['email', 'sms'])) {
    $type = 'email';
}

// Analyze the message
$results = analyzeMessage($text, $type);

// Save report to database
$report_id = saveReport($text, $type, $results);

// Log activity
logActivity('Analyse udført', "Type: $type, Score: {$results['score']}, ID: $report_id");
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultat - Velkendt.com</title>
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
        <section class="analyzer">
            <h2>📊 Dine resultater</h2>
            
            <div class="results">
                <div class="result-header">
                    <h3>Resultat</h3>
                    <div class="verdict <?php echo $results['verdict_class']; ?>">
                        <?php echo htmlspecialchars($results['verdict']); ?>
                    </div>
                </div>

                <div class="score-container">
                    <div class="score-circle">
                        <div class="score-value"><?php echo $results['score']; ?></div>
                        <div class="score-label">Risiko</div>
                    </div>
                </div>

                <?php if (!empty($results['warnings'])): ?>
                <div class="warnings-section">
                    <h4>⚠️ Advarsler fundet:</h4>
                    <ul>
                        <?php foreach ($results['warnings'] as $warning): ?>
                            <li><strong><?php echo htmlspecialchars($warning['warning']); ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($results['urls'])): ?>
                <div class="urls-section">
                    <h4>🔗 Links fundet:</h4>
                    <?php foreach ($results['urls'] as $url): ?>
                        <div class="url-item <?php echo $url['check']['status']; ?>">
                            <div class="url-domain"><?php echo htmlspecialchars($url['url']); ?></div>
                            <span class="url-status <?php echo $url['check']['status']; ?>">
                                <?php echo htmlspecialchars($url['check']['message']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($results['emails'])): ?>
                <div class="emails-section">
                    <h4>📧 Email-adresser fundet:</h4>
                    <?php foreach ($results['emails'] as $email): ?>
                        <div class="email-item">
                            <strong><?php echo htmlspecialchars($email['email']); ?></strong>
                            <span style="color: #6b7280; margin-left: 10px;">(<?php echo htmlspecialchars($email['domain']); ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($results['recommendations'])): ?>
                <div class="recommendations-section">
                    <h4>✅ Anbefalinger:</h4>
                    <ul>
                        <?php foreach ($results['recommendations'] as $rec): ?>
                            <li><?php echo htmlspecialchars($rec); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="result-actions">
                    <a href="arkiv.php" class="btn-secondary" style="display:inline-block; text-decoration:none; text-align:center;">📋 Se arkiv</a>
                    <a href="index.php" class="btn-secondary" style="display:inline-block; text-decoration:none; text-align:center;">🔄 Ny analyse</a>
                    <form method="POST" action="del_i_forum.php" style="display:inline;">
                        <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($report_id); ?>">
                        <button type="submit" class="btn-secondary">💾 Del i forum</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>© 2026 Velkendt.com - Hjælper danskere med at undgå svindel</p>
        </div>
    </footer>

    <script>
    const REPORT_ID = '<?php echo htmlspecialchars($report_id); ?>';

    // Gem i forum funktion
    function saveToForum() {
        const comment = prompt('Evt. kommentar til forum (kan være tom):');
        
        if (comment === null) {
            return; // Bruger klikkede Cancel
        }

        const btn = document.querySelector('button[onclick="saveToForum()"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = '⏳ Gemmer...';
        }

        console.log('Gemmer rapport ID:', REPORT_ID);
        
        const formData = new FormData();
        formData.append('report_id', REPORT_ID);
        formData.append('comment', comment);
        
        fetch('api.php?action=report', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                alert('✅ Tak! Din rapport er delt og kan nu ses af andre.');
                if (btn) {
                    btn.textContent = '✅ Delt!';
                    btn.disabled = true;
                }
            } else {
                alert('Fejl: ' + (data.error || 'Kunne ikke gemme rapport'));
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '💾 Gem i forum';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Der opstod en fejl: ' + error.message);
            if (btn) {
                btn.disabled = false;
                btn.textContent = '💾 Gem i forum';
            }
        });
    }
    </script>
</body>
</html>