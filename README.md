# Samarbejdspapir - Fælles Dokument

Et responsivt PHP-baseret program hvor op til 10 prædefinerede brugere kan skrive på et fælles "papir" med styringsstyring.

## Funktioner

- **Prædefinerede Brugere**: Kun 10 specifikke brugere kan få adgang (Alice, Bob, Charlie, Diana, Eve, Frank, Grace, Henry, Ivy, Jack)
- **Token-system**: Kun én person kan skrive ad gangen ved at "tage token"
- **Automatisk frigivelse**: Token frigives automatisk efter 10 minutters inaktivitet
- **Realtids-opdateringer**: Aktive brugere og token-status opdateres hvert 3. sekund via AJAX polling
- **Aktivitetslog**: Alle handlinger logges med tidsstempler i tekstfiler
- **Responsivt Design**: Virker på desktop, tablet og mobile enheder
- **Auto-gem**: Papirindhold gemmes automatisk under skrivning

## Filestruktur

```
.
├── config.php              # Konfiguration og hjælpefunktioner
├── index.php               # Login side (navneindtastning)
├── paper.php               # Hovedside med fælles papir
├── api.php                 # API endpoint for AJAX forespørgsler
├── logout.php              # Logout funktionalitet
├── paper_content.txt       # Det fælles papirindhold (oprettes automatisk)
└── logs/                   # Mappe til logfiler
    └── .htaccess           # Forhindrer directory listing
```

## Installation

### Forudsætninger

- PHP 7.4 eller højere
- Apache web server med PHP module
- Session support aktiveret i PHP

### Setup trin

1. **Kopier filer til web server mappe**:
   ```bash
   # Eksempel for Apache på macOS/Linux
   sudo cp -r * /usr/local/var/www/samarbejdspapir/
   ```

2. **Sæt korrekte tilladelser**:
   ```bash
   # Gør logs mappen skrivbar
   sudo chmod 755 logs
   sudo chown -R www-data:www-data logs  # På Linux
   # ELLER
   sudo chown -R _www:_www logs  # På macOS
   ```

3. **Konfigurer Apache** (hvis nødvendigt):
   
   Opret en virtual host eller placer filer i din web servers document root.

4. **Start Apache**:
   ```bash
   # På macOS
   sudo apachectl start
   
   # På Linux
   sudo systemctl start apache2
   ```

5. **Åbn applikationen**:
   Åbn din browser og naviger til:
   ```
   http://localhost/samarbejdspapir/index.php
   ```

## Brug

### For brugere

1. **Login**: Indtast et af de prædefinerede navne (Alice, Bob, Charlie, Diana, Eve, Frank, Grace, Henry, Ivy, Jack)
2. **Se papiret**: Se det fælles papir og hvem der er online
3. **Tag token**: Klik "Tag token" knappen for at få skriverettigheder
4. **Skriv**: Skriv i papir området (kun tilgængelig når du har token)
5. **Slip token**: Klik "Slip token" når du er færdig med at skrive
6. **Se logs**: Klik på logfiler i sidebaren for at se aktivitetshistorik
7. **Logout**: Klik "Logout" for at afslutte

### Token regler

- Kun én person kan have token ad gangen
- Token frigives automatisk efter 10 minutters inaktivitet
- Når nogen har token, kan andre se deres navn og et "Skriver" badge
- Textarea er deaktiveret for brugere uden token

## Konfiguration

### Ændring af tilladte brugere

Rediger `config.php` og modificer `$allowed_users` array:

```php
$allowed_users = [
    'Alice',
    'Bob',
    'Charlie',
    // Tilføj eller fjern brugere (max 10)
];
```

### Justering af timeout

Ændre inaktivitets timeout i `config.php`:

```php
define('INACTIVITY_TIMEOUT', 600); // 600 sekunder = 10 minutter
```

### Modificering af log mappe

Ændre log mappe sti i `config.php`:

```php
define('LOG_DIR', __DIR__ . '/logs'); // Standard placering
```

## Tekniske Detaljer

### Session Håndtering

- Bruger PHP sessions til at tracke loggede brugere
- Session data inkluderer:
  - Nuværende brugernavn
  - Sidste aktivitet timestamp
  - Token indehaver og token timestamp

### AJAX Polling

- Client poller `api.php?action=status` hvert 3. sekund
- Opdaterer aktiv bruger liste og token status i realtid
- Ingen WebSockets påkrævet (bruger simple HTTP requests)

### Auto-gem

- Papirindhold gemmes automatisk efter 1 sekunds inaktivitet under skrivning
- Virker kun når bruger har token
- Gemmer til `paper_content.txt`

### Logning

- Daglige logfiler oprettes i `logs/` mappen
- Format: `[YYYY-MM-DD HH:MM:SS] Brugernavn - Handling`
- Handlinger logget: login, logout, tag token, slip token, gem papir, timeout frigivelse

## Browser Kompatibilitet

- Chrome/Edge: ✓
- Firefox: ✓
- Safari: ✓
- Mobile browsere: ✓

## Sikkerhedsnoter

- Ingen adgangskoder påkrævet (afhænger af prædefinerede navne)
- Session-baseret autentificering
- Input sanitering med `htmlspecialchars()`
- Directory listing deaktiveret for logs mappen
- Anbefales ikke til offentlig internet uden yderligere sikkerhedsforanstaltninger

## Fejlfinding

### "Permission denied" fejl
```bash
sudo chmod 755 logs
sudo chown -R www-data:www-data .
```

### Sessions virker ikke
- Tjek PHP session konfiguration i `php.ini`
- Sikr at `session.save_path` er skrivbar

### Apache starter ikke
```bash
# Tjek error logs
sudo tail -f /var/log/apache2/error_log  # Linux
sudo tail -f /usr/local/var/log/httpd/error_log  # macOS
```

## Test

1. Åbn flere browser vinduer/faneblade
2. Login med forskellige navne i hvert vindue
3. Test at tage og slippe token
4. Verificer realtids-opdateringer på tværs af vinduer
5. Tjek at logfiler bliver oprettet
6. Test på mobil enhed for responsivitet

## Licens

Fri at bruge og modificere.
