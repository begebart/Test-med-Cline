<?php
// Configuration and helper functions for SpamChecker

// Start session
session_start();

// Database file for storing reported messages
define('DB_FILE', __DIR__ . '/reports.json');

// Log directory
define('LOG_DIR', __DIR__ . '/logs');

// Ensure logs directory exists
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Known legitimate Danish companies and their domains
$legitimate_companies = [
    'Danske Bank' => ['danskebank.dk', 'danskebank.com'],
    'Nordea' => ['nordea.dk', 'nordea.com'],
    'Jyske Bank' => ['jyskebank.dk'],
    'Sydbank' => ['sydbank.dk'],
    'Lunar' => ['lunar.app', 'lunarway.com'],
    'MobilePay' => ['mobilepay.dk'],
    'NemID' => ['nemid.nu', 'digitaliser.dk'],
    'Borger.dk' => ['borger.dk'],
    'SKAT' => ['skat.dk'],
    'ATP' => ['atp.dk'],
    'Pension Danmark' => ['pensiondanmark.dk'],
    'PFA' => ['pfa.dk'],
    'Tryg' => ['tryg.dk'],
    'Codan' => ['codan.dk'],
    'Topdanmark' => ['topdanmark.dk'],
    'Alm. Brand' => ['almbrand.dk'],
    'Gjensidige' => ['gjensidige.dk'],
    'Netto' => ['netto.dk', 'netto.com'],
    'REMA 1000' => ['rema1000.dk'],
    'Føtex' => ['foetex.dk'],
    'Meny' => ['meny.dk'],
    'IKEA' => ['ikea.dk', 'ikea.com'],
    'Amazon' => ['amazon.dk', 'amazon.com'],
    'Bilka' => ['bilka.dk'],
    'Netflix' => ['netflix.com'],
    'Spotify' => ['spotify.com', 'spotify.dk'],
    'Apple' => ['apple.com'],
    'Google' => ['google.com'],
    'Microsoft' => ['microsoft.com'],
    'Facebook' => ['facebook.com'],
    'Instagram' => ['instagram.com'],
];

// Suspicious patterns to check for
$suspicious_patterns = [
    'urgency' => [
        'patterns' => ['hurtigt', 'omgående', 'straks', 'nu', 'i dag', 'frist udløber', 'sidste chance', 'kun 24 timer', 'handling kræves'],
        'warning' => 'Bruger presserende sprog for at få dig til at handle hurtigt',
        'severity' => 'medium'
    ],
    'personal_info' => [
        'patterns' => ['cpr-nummer', 'adgangskode', 'kodeord', 'pin-kode', 'kreditkort', 'bankoplysninger', 'personlige oplysninger', 'bekræft din identitet'],
        'warning' => 'Beder om personlige eller følsomme oplysninger',
        'severity' => 'high'
    ],
    'suspicious_links' => [
        'patterns' => ['klik her', 'følg link', 'bekræft her', 'log ind her', 'bekræft din konto', 'opdater dine oplysninger'],
        'warning' => 'Opfordrer til at klikke på links eller logge ind',
        'severity' => 'high'
    ],
    'threats' => [
        'patterns' => ['konto bliver lukket', 'bliver suspenderet', 'mistet adgang', 'juridiske skridt', 'bøde', 'betaling forfalder'],
        'warning' => 'Bruger trusler om negative konsekvenser',
        'severity' => 'high'
    ],
    'too_good_to_be_true' => [
        'patterns' => ['du har vundet', 'gratis gave', 'tillykke', 'du er blevet udvalgt', 'millioner', 'store gevinster'],
        'warning' => 'Leder til at tro, at det er for godt til at være sandt',
        'severity' => 'medium'
    ],
    'grammar_errors' => [
        'patterns' => ['venligst bekræft', 'kontoen din er', 'din konto vil', 'vi har opdaget'],
        'warning' => 'Mærkelig formulering eller dårlig grammatik',
        'severity' => 'low'
    ]
];

// Helper function to extract URLs from text
function extractUrls($text) {
    $urls = [];
    $pattern = '/https?:\/\/[^\s<>"\'\)]+/i';
    preg_match_all($pattern, $text, $matches);
    return $matches[0] ?? [];
}

// Helper function to extract email addresses
function extractEmails($text) {
    $emails = [];
    $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
    preg_match_all($pattern, $text, $matches);
    return $matches[0] ?? [];
}

// Helper function to check domain legitimacy
function checkDomain($url, $company_name = '') {
    global $legitimate_companies;
    
    $domain = parse_url($url, PHP_URL_HOST);
    if (!$domain) {
        return ['status' => 'unknown', 'message' => 'Kunne ikke parse domæne'];
    }
    
    // Remove www. prefix
    $domain = preg_replace('/^www\./', '', $domain);
    
    // Check if it's a known legitimate company
    if (!empty($company_name)) {
        foreach ($legitimate_companies as $company => $domains) {
            if (stripos($company_name, $company) !== false || stripos($company, $company_name) !== false) {
                foreach ($domains as $legit_domain) {
                    if ($domain === $legit_domain || strpos($domain, $legit_domain) !== false) {
                        return [
                            'status' => 'legitimate',
                            'message' => "Matcher kendt domæne for $company",
                            'company' => $company
                        ];
                    } else {
                        return [
                            'status' => 'suspicious',
                            'message' => "Påstår at være $company, men bruger domænet $domain i stedet for " . implode(' eller ', $domains),
                            'expected' => $domains,
                            'actual' => $domain
                        ];
                    }
                }
            }
        }
    }
    
    // Check for .com vs .dk mismatch (common phishing tactic)
    if (preg_match('/\.dk$/', $domain)) {
        return ['status' => 'legitimate', 'message' => 'Bruger .dk domæne (typisk dansk)'];
    } elseif (preg_match('/\.com$/', $domain) && !preg_match('/\.dk$/', $domain)) {
        return ['status' => 'caution', 'message' => 'Bruger .com domæne - tjek om det er det rigtige firma'];
    }
    
    return ['status' => 'unknown', 'message' => 'Ukendt domæne'];
}

// Main analysis function
function analyzeMessage($text, $type = 'email') {
    $results = [
        'warnings' => [],
        'urls' => [],
        'emails' => [],
        'score' => 0, // 0-100, higher = more suspicious
        'verdict' => '',
        'recommendations' => []
    ];
    
    $text_lower = strtolower($text);
    
    // Check for suspicious patterns
    global $suspicious_patterns;
    foreach ($suspicious_patterns as $category => $data) {
        foreach ($data['patterns'] as $pattern) {
            if (strpos($text_lower, $pattern) !== false) {
                $results['warnings'][] = [
                    'category' => $category,
                    'warning' => $data['warning'],
                    'severity' => $data['severity'],
                    'matched' => $pattern
                ];
                
                // Add to score based on severity
                switch ($data['severity']) {
                    case 'high':
                        $results['score'] += 30;
                        break;
                    case 'medium':
                        $results['score'] += 20;
                        break;
                    case 'low':
                        $results['score'] += 10;
                        break;
                }
            }
        }
    }
    
    // Extract and check URLs
    $urls = extractUrls($text);
    foreach ($urls as $url) {
        $domain_check = checkDomain($url);
        $results['urls'][] = [
            'url' => $url,
            'check' => $domain_check
        ];
        
        if ($domain_check['status'] === 'suspicious') {
            $results['score'] += 40;
            $results['warnings'][] = [
                'category' => 'domain_mismatch',
                'warning' => $domain_check['message'],
                'severity' => 'high',
                'matched' => $url
            ];
        } elseif ($domain_check['status'] === 'caution') {
            $results['score'] += 15;
        }
    }
    
    // Extract and check email addresses
    $emails = extractEmails($text);
    foreach ($emails as $email) {
        $domain = explode('@', $email)[1];
        $results['emails'][] = [
            'email' => $email,
            'domain' => $domain
        ];
        
        // Check if email domain matches claimed company
        foreach ($legitimate_companies as $company => $domains) {
            if (stripos($text, $company) !== false) {
                $matches = false;
                foreach ($domains as $legit_domain) {
                    if ($domain === $legit_domain || strpos($domain, $legit_domain) !== false) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    $results['score'] += 35;
                    $results['warnings'][] = [
                        'category' => 'email_mismatch',
                        'warning' => "Påstår at være fra $company, men e-mail kommer fra $domain",
                        'severity' => 'high',
                        'matched' => $email
                    ];
                }
            }
        }
    }
    
    // Cap score at 100
    $results['score'] = min(100, $results['score']);
    
    // Determine verdict
    if ($results['score'] >= 70) {
        $results['verdict'] = 'HØJ RISIKO - Sandsynligvis svindel';
        $results['verdict_class'] = 'danger';
        $results['recommendations'][] = 'Slet beskeden uden at klikke på links';
        $results['recommendations'][] = 'Følg ikke med i nogen handlinger';
        $results['recommendations'][] = 'Rapporter som spam';
    } elseif ($results['score'] >= 40) {
        $results['verdict'] = 'MIDDEL RISIKO - Vær forsigtig';
        $results['verdict_class'] = 'warning';
        $results['recommendations'][] = 'Vær ekstra forsigtig';
        $results['recommendations'][] = 'Kontakt virksomheden direkte via kendte kontaktoplysninger';
        $results['recommendations'][] = 'Følg ikke med i links eller oplysninger';
    } elseif ($results['score'] >= 20) {
        $results['verdict'] = 'LAV RISIKO - Ser rimelig ud';
        $results['verdict_class'] = 'caution';
        $results['recommendations'][] = 'Ser ud til at være legitim, men vær altid forsigtig';
        $results['recommendations'][] = 'Hvis i tvivl, kontakt virksomheden direkte';
    } else {
        $results['verdict'] = 'MEget LAV RISIKO - Ser legitim ud';
        $results['verdict_class'] = 'success';
        $results['recommendations'][] = 'Denne besked ser legitim ud';
        $results['recommendations'][] = 'Normalt er det ikke nødvendigt at bekymre sig';
    }
    
    return $results;
}

// Save report to JSON database
function saveReport($text, $type, $results) {
    $reports = [];
    if (file_exists(DB_FILE)) {
        $reports = json_decode(file_get_contents(DB_FILE), true) ?: [];
    }
    
    $report = [
        'id' => uniqid(),
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'text' => $text,
        'results' => $results,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // Add to beginning of array (newest first)
    array_unshift($reports, $report);
    
    // Keep only last 100 reports
    $reports = array_slice($reports, 0, 100);
    
    file_put_contents(DB_FILE, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $report['id'];
}

// Get all reports
function getReports($limit = 50) {
    if (!file_exists(DB_FILE)) {
        return [];
    }
    
    $reports = json_decode(file_get_contents(DB_FILE), true) ?: [];
    return array_slice($reports, 0, $limit);
}

// Log activity
function logActivity($action, $details = '') {
    $log_file = LOG_DIR . '/activity_' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $action";
    if ($details) {
        $log_entry .= " - $details";
    }
    $log_entry .= "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Send JSON response
function jsonResponse($data, $status_code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>