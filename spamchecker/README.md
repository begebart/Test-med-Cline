# Velkendt.com - Tjek om din SMS eller Email er ægte

Et simpelt og brugervenligt værktøj til at tjekke om mistænkelige SMS og emails er legitimt eller svindel. Specifikt designet til personer over 50, som ikke er så vant til IT-sikkerhed.

## 🎯 Formål

At hjælpe danskere med at:
- Hurtigt tjekke om en modtaget SMS eller email er troværdig
- Lære at kende tegn på svindel
- Se hvad andre har fundet af mistænkelige beskeder

## ✨ Funktioner

### Hovedværktøj
- **Simpel analyse**: Kopier og indsæt hele beskeden - vi tjekker den for dig
- **SMS og Email support**: Vælg typen af besked du vil analysere
- **Sundhedstjek**: Får en farvekodet risiko-bedømmelse (0-100%)
- **Detaljerede advarsler**: Se præcis hvad der er mistænkeligt
- **Link-analyse**: Tjekker om links bruger rigtige domæner
- **Email-analyse**: Tjekker om afsender-domæne matcher påstået virksomhed

### Forum/Community
- **Se andres test**: Læs hvad andre har fundet af mistænkelige beskeder
- **Filtre**: Filtrer efter type (SMS/Email) eller risikoniveau
- **Del erfaringer**: Gem dine egne fund til gavn for andre

### Viden og råd
- **Gode råd**: 6 konkrete tips til at undgå svindel
- **Eksempler**: Lær at kende de typiske tegn på svindel

## 🚀 Installation

### Forudsætninger
- PHP 7.4 eller højere
- Apache web server med PHP module
- Session support aktiveret i PHP

### Installationsvejledning

1. **Kopier filer til web server**:
   ```bash
   # Eksempel for Apache på macOS/Linux
   cp -r spamchecker/* /usr/local/var/www/velkendt/
   ```

2. **Sæt korrekte tilladelser**:
   ```bash
   # Gør logs mappen skrivbar
   chmod 755 spamchecker/logs
   sudo chown -R www-data:www-data spamchecker/logs  # På Linux
   # ELLER
   sudo chown -R _www:_www spamchecker/logs  # På macOS
   ```

3. **Konfigurer Apache** (hvis nødvendigt):
   - Opret en virtual host eller placer filer i web serverens document root
   - Sørg for at .htaccess understøttes (mod_rewrite enabled)

4. **Start Apache**:
   ```bash
   # På macOS
   sudo apachectl start
   
   # På Linux
   sudo systemctl start apache2
   ```

5. **Åbn applikationen**:
   ```
   http://localhost/velkendt/
   ```

## 📖 Brug

### Sådan tjekker du en besked:

1. **Kopier beskeden**: Markér hele den mistænkelige SMS eller email og kopier den (Ctrl+C)

2. **Indsæt i værktøjet**: Klik i tekstfeltet på hjemmesiden og indsæt (Ctrl+V eller højre-klik → Indsæt)

3. **Vælg type**: Vælg om det er en SMS eller Email

4. **Klik "Tjek nu"**: Vi analyserer beskeden og fortæller dig om den er troværdig

5. **Læs resultatet**: Du får:
   - En farvekodet risiko-bedømmelse (0-100%)
   - Liste over specifikke advarsler
   - Analyse af links og email-adresser
   - Konkrete anbefalinger til hvad du skal gøre

### Sådan gemmer du i forum:

Efter en analyse kan du klikke "Gem i forum" for at dele dit fund med andre. Dette hjælper andre med at lære at kende nye svindelmåder.

## 🔧 Tekniske detaljer

### Analyse-logik

Værktøjet tjekker for:
- **Presserende sprog**: "Hurtigt", "omgående", "sidste chance"
- **Forespørgsel om personlige oplysninger**: CPR-nummer, adgangskoder, kortoplysninger
- **Mistænkelige links**: Opfordring til at klikke på links
- **Trusler**: "Konto bliver lukket", "juridiske skridt"
- **For godt til at være sandt**: "Du har vundet", "gratis gave"
- **Domæne-match**: Tjekker om links passer til påstået virksomhed
- **Email-match**: Tjekker om afsender matcher påstået virksomhed

### Kendte danske virksomheder

Systemet indeholder en database over kendte danske virksomheder og deres domæner:
- Banker: Danske Bank, Nordea, Jyske Bank, Sydbank, Lunar
- Offentlige: NemID, Borger.dk, SKAT, ATP
- Forsikring: Tryg, Codan, Topdanmark, Alm. Brand, Gjensidige
- Fødevarer: Netto, REMA 1000, Føtex, Meny, Bilka
- Internationale: IKEA, Amazon, Netflix, Spotify, Apple, Google, Microsoft

### Point-system

- **Høj risiko (70-100%)**: Sandsynligvis svindel
- **Middel risiko (40-69%)**: Vær forsigtig
- **Lav risiko (20-39%)**: Ser rimelig ud
- **Meget lav risiko (0-19%)**: Ser legitimt ud

Hver advarsel gives point baseret på alvorlighed:
- Høj: 30 point
- Medium: 20 point
- Lav: 10 point
- Domæne-mismatch: 40 point
- Suspicious link: 15 point

## 🛡️ Sikkerhed

- Ingen personlige oplysninger gemmes
- Kun anonymiserede rapporter vises i forum
- Alle inputs saniteres med `htmlspecialchars()`
- Directory listing deaktiveret
- Følsomme filer beskyttet af .htaccess
- XSS-beskyttelse implementeret

## 📁 Filstruktur

```
spamchecker/
├── index.php          # Hovedside med formulær og forum
├── api.php            # API endpoint til analyse
├── config.php         # Konfiguration og analyse-logik
├── .htaccess          # Sikkerhedsregler
├── css/
│   └── style.css      # Styling (responsivt design)
├── js/
│   └── app.js         # Frontend funktionalitet
├── logs/              # Aktivitetslogs (beskyttet)
│   └── .htaccess
└── reports.json       # Gemte rapporter (oprettes automatisk)
```

## 🎨 Design

- **Responsivt**: Virker på computer, tablet og telefon
- **Stor tekst**: Letlæselig for ældre brugere
- **Enkel navigation**: Intet komplekst menuesystem
- **Farvekodet**: Grøn=tryg, Gul=forsigtig, Rød=fare
- **Trin-for-trin guide**: Nem at følge for alle

## 🔍 Eksempel på brug

### Eksempel 1: Mistænkelig email
```
Input: "Kære kunde, din konto er blevet suspenderet. 
Klik her for at bekræfte din identitet: 
http://danskebank-secure.com/login"

Resultat: HØJ RISIKO (85%)
- Bruger trusler om suspenderet konto
- Opfordrer til at klikke på link
- Domæne "danskebank-secure.com" er FALSK
  (rigtige: danskebank.dk)
- Påtænkt at være Danske Bank

Anbefaling: Slet beskeden, kontakt banken direkte
```

### Eksempel 2: Legitim SMS
```
Input: "Hej! Du har en pakke til afhentning på 
PostNord.hentested.dk. Pakken kan afhentes frem 
til den 15. juni. Med venlig hilsen PostNord"

Resultat: LAV RISIKO (15%)
- Bruger officielt .dk domæne
- Intet presserende sprog
- Ingen anmodning om personlige oplysninger

Anbefaling: Ser legitimt ud
```

## 🛠️ Udvikling

### Tilføj flere virksomheder

Rediger `config.php` og tilføj til `$legitimate_companies` array:

```php
'Ny Virksomhed' => ['domæne1.dk', 'domæne2.com'],
```

### Tilføj flere mønstre

Rediger `$suspicious_patterns` i `config.php`:

```php
'ny_kategori' => [
    'patterns' => ['ord1', 'ord2', 'ord3'],
    'warning' => 'Beskrivelse af hvad der er mistænkeligt',
    'severity' => 'high' // eller 'medium' eller 'low'
],
```

### Juster pointgrænser

Rediger `analyzeMessage()` funktionen i `config.php`:

```php
if ($results['score'] >= 70) {  // Ændre denne værdi
    // Høj risiko
}
```

## 📊 Logning

Alle handlinger logges i `logs/` mappen:
- `activity_YYYY-MM-DD.txt` - Daglige logs
- Indeholder: timestamp, handling, detaljer

## 🌐 Browser support

- Chrome/Edge: ✓
- Firefox: ✓
- Safari: ✓
- Mobile browsere: ✓

## ⚠️ Vigtige bemærkninger

1. **Ikke 100% pålidelig**: Dette er et hjælpeværktøj, ikke en garanti
2. **Brug sunde fornuft**: Hvis i tvivl, kontakt virksomheden direkte
3. **Opdateret database**: Vi forsøger at holde virksomhedslisten opdateret, men nye domæner kan opstå
4. **Intet er umuligt**: Selv "legitime" beskeder kan være falske

## 🤝 Bidrag

Hvis du finder en ny svindelmåde eller en fejl i systemet, er du velkommen til at rapportere det.

## 📝 Licens

Fri at bruge og modificere til ikke-kommercielt brug.

## 📞 Support

Hvis du har spørgsmål eller oplever problemer, tjek først:
1. Er PHP installeret og aktiveret?
2. Har du sat korrekte filrettigheder?
3. Er Apache kørende?

## 🎯 Målgruppe

Dette værktøj er primært designet til:
- Personer over 50 år
- Personer med begrænset IT-erfaring
- Alle som vil være mere sikre online

Designet er simpelt, med stor tekst og tydelig sprog for at være tilgængeligt for alle.

---

**Husk**: Det bedste forsvar mod svindel er din sunde fornuft! Når i tvivl, ring til virksomheden på et telefonnummer du selv finder.