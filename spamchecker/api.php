<?php
require_once 'config.php';

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Read JSON input once and store it
$json_input = file_get_contents('php://input');

// Log raw input for debugging
$raw_input_log = substr($json_input, 0, 300);
logActivity('API Raw Input', 'Length: ' . strlen($json_input) . ', Content: ' . $raw_input_log);

// Remove BOM if present
$json_input = preg_replace('/^\xEF\xBB\xBF/', '', $json_input);

// Try to decode JSON
$json_data = json_decode($json_input, true);

// Check for JSON errors
$json_error = json_last_error();
if ($json_error !== JSON_ERROR_NONE) {
    $error_msg = json_last_error_msg();
    logActivity('API Error', 'JSON parse error: ' . $error_msg . ' (code: ' . $json_error . ')');
    
    // If JSON fails, try to parse as form data
    if (!empty($json_input)) {
        parse_str($json_input, $post_data);
        if (!empty($post_data)) {
            $json_data = $post_data;
            logActivity('API Fallback', 'Parsed as form data instead');
        }
    }
} else {
    logActivity('API Success', 'JSON parsed successfully');
}

// Log incoming request for debugging
logActivity('API Request', 'Method: ' . $_SERVER['REQUEST_METHOD'] . ', Action from JSON: ' . ($json_data['action'] ?? 'none') . ', GET action: ' . ($_GET['action'] ?? 'none'));

// Get action from GET, POST, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? ($json_data['action'] ?? '');

// If still no action, return error
if (empty($action)) {
    logActivity('API Error', 'No action found. Method: ' . $_SERVER['REQUEST_METHOD'] . ', GET: ' . ($_GET['action'] ?? 'none') . ', POST: ' . ($_POST['action'] ?? 'none') . ', JSON action: ' . ($json_data['action'] ?? 'none'));
    jsonResponse(['error' => 'Ugyldig handling - ingen action angivet. Metode: ' . $_SERVER['REQUEST_METHOD']], 400);
}

try {
    switch ($action) {
        case 'analyze':
            analyzeMessageEndpoint();
            break;
        
        case 'reports':
            getReportsEndpoint();
            break;
        
        case 'report':
            saveReportEndpoint();
            break;
        
        default:
            jsonResponse(['error' => 'Ugyldig handling'], 400);
    }
} catch (Exception $e) {
    logActivity('API Error', $e->getMessage());
    jsonResponse(['error' => 'Der opstod en fejl: ' . $e->getMessage()], 500);
}

function analyzeMessageEndpoint() {
    global $json_data;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Kun POST metoder er tilladt'], 405);
    }
    
    $input = $json_data;
    
    if (!$input || !isset($input['text']) || empty(trim($input['text']))) {
        jsonResponse(['error' => 'Tekst mangler'], 400);
    }
    
    $text = trim($input['text']);
    $type = $input['type'] ?? 'email';
    
    // Validate type
    if (!in_array($type, ['email', 'sms'])) {
        $type = 'email';
    }
    
    // Analyze the message
    $results = analyzeMessage($text, $type);
    
    // Save report
    $report_id = saveReport($text, $type, $results);
    
    // Log activity
    logActivity('Analyse udført', "Type: $type, Score: {$results['score']}, ID: $report_id");
    
    // Check if this is an AJAX request (JSON) or form submit
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        // Return JSON for AJAX
        jsonResponse([
            'success' => true,
            'report_id' => $report_id,
            'results' => $results
        ]);
    } else {
        // Return HTML for form submit
        displayResultsHTML($results);
        exit;
    }
}

// Helper function to display results as HTML
function displayResultsHTML($results) {
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
                        <a href="index.php" class="btn-secondary">🔄 Ny analyse</a>
                    </div>
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
    <?php
}

function getReportsEndpoint() {
    $limit = intval($_GET['limit'] ?? 50);
    $limit = min(100, max(1, $limit));
    
    $all_reports = getReports($limit);
    
    // Remove full text from list view for privacy
    $public_reports = array_map(function($report) {
        $text_preview = mb_substr($report['text'], 0, 100);
        if (mb_strlen($report['text']) > 100) {
            $text_preview .= '...';
        }
        return [
            'id'            => $report['id'],
            'timestamp'     => $report['timestamp'],
            'type'          => $report['type'],
            'text_preview'  => $text_preview,
            'score'         => $report['results']['score'],
            'verdict'       => $report['results']['verdict'],
            'verdict_class' => $report['results']['verdict_class'],
            'warning_count' => count($report['results']['warnings']),
            'user_comment'  => $report['results']['user_comment'] ?? '',
            'shared'        => !empty($report['shared_in_forum'])
        ];
    }, $all_reports);
    
    logActivity('Rapporter hentet', 'Antal: ' . count($public_reports));
    
    jsonResponse([
        'success' => true,
        'reports' => array_values($public_reports),
        'total'   => count($public_reports)
    ]);
}

function saveReportEndpoint() {
    global $json_data;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Kun POST metoder er tilladt'], 405);
    }

    // Hent comment og report_id fra POST eller JSON
    $comment   = $_POST['comment']   ?? ($json_data['comment']   ?? '');
    $report_id = $_POST['report_id'] ?? ($json_data['report_id'] ?? '');

    // Hvis vi har et report_id, marker rapporten som "delt i forum"
    if (!empty($report_id)) {
        if (!file_exists(DB_FILE)) {
            jsonResponse(['error' => 'Ingen rapporter fundet'], 404);
        }

        $reports = json_decode(file_get_contents(DB_FILE), true) ?: [];

        $found = false;
        foreach ($reports as &$report) {
            if ($report['id'] === $report_id) {
                $report['shared_in_forum'] = true;
                $report['forum_timestamp'] = date('Y-m-d H:i:s');
                if (!empty($comment)) {
                    $report['results']['user_comment'] = $comment;
                }
                $found = true;
                break;
            }
        }
        unset($report);

        if (!$found) {
            jsonResponse(['error' => 'Rapport ikke fundet'], 404);
        }

        file_put_contents(DB_FILE, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        logActivity('Rapport delt i forum', "ID: $report_id");

        jsonResponse([
            'success' => true,
            'message' => 'Rapport delt i forum',
            'report_id' => $report_id
        ]);
    }

    // Fallback: opret ny rapport fra tekst (bruges fra AJAX i app.js)
    $input = $json_data ?: [];
    // Prøv også $_POST
    if (empty($input['text'])) {
        $input['text'] = $_POST['text'] ?? '';
    }
    if (empty($input['type'])) {
        $input['type'] = $_POST['type'] ?? 'email';
    }

    if (empty(trim($input['text'] ?? ''))) {
        jsonResponse(['error' => 'Tekst eller rapport-ID mangler'], 400);
    }

    $text = trim($input['text']);
    $type = $input['type'] ?? 'email';

    if (!in_array($type, ['email', 'sms'])) {
        $type = 'email';
    }

    $results = analyzeMessage($text, $type);

    if (!empty($comment)) {
        $results['user_comment'] = $comment;
    }

    $new_id = saveReport($text, $type, $results);

    // Marker direkte som delt i forum
    $reports = json_decode(file_get_contents(DB_FILE), true) ?: [];
    foreach ($reports as &$report) {
        if ($report['id'] === $new_id) {
            $report['shared_in_forum'] = true;
            $report['forum_timestamp'] = date('Y-m-d H:i:s');
            break;
        }
    }
    unset($report);
    file_put_contents(DB_FILE, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    logActivity('Rapport gemt og delt', "Type: $type, Score: {$results['score']}, ID: $new_id");

    jsonResponse([
        'success' => true,
        'message' => 'Rapport gemt succesfuldt',
        'report_id' => $new_id
    ]);
}
?>