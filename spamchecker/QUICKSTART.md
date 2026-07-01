# Hurtig Start Guide - Velkendt.com

## Kom i gang på 5 minutter

### 1. Test værktøjet (uden installation)

Åbn `test_api.php` i din browser for at se om alt virker:
```
http://localhost/spamchecker/test_api.php
```

Du skal se 7 grønne ✓ tegn hvis alt fungerer.

### 2. Brug hovedsiden

Åbn `index.php` for at bruge værktøjet:
```
http://localhost/spamchecker/index.php
```

### 3. Sådan tester du en besked

**Eksempel på en mistænkelig SMS:**
```
"Kære kunde, din MobilePay konto er blevet suspenderet. 
Klik her for at bekræfte: http://mobilepay-secure.dk/login"
```

Kopier denne tekst, indsæt i værktøjet, vælg "SMS" og klik "Tjek nu".

**Forventet resultat:**
- HØJ RISIKO (75-85%)
- Advarsel: Domænet "mobilepay-secure.dk" er FALSK
- Rigtige domæner: mobilepay.dk
- Anbefaling: Slet beskeden

### 4. Eksempel på legitim besked

**Eksempel på legitim SMS:**
```
"Hej! Du har en pakke til PostNord.hentested.dk. 
Med venlig hilsen PostNord"
```

**Forventet resultat:**
- LAV RISIKO (10-20%)
- Bruger officielt .dk domæne
- Ingen mistænkelige mønstre
- Anbefaling: Ser legitimt ud

## Funktioner du kan bruge

### Hovedværktøjet
1. **Indsæt besked** - Kopier hele SMS/email og indsæt
2. **Vælg type** - SMS eller Email
3. **Klik "Tjek nu"** - Få analyse på sekunder
4. **Læs resultat** - Farvekodet risiko + detaljer

### Forum sektionen
- Se hvad andre har testet
- Filtrer efter type eller risikoniveau
- Lær af andres erfaringer

### Gem til forum
Efter analyse, klik "Gem i forum" for at dele dit fund.

## Fejlfinding

### "Side ikke fundet" (404)
- Sørg for at Apache kører: `sudo apachectl start`
- Tjek at filerne er i web server mappen

### "Ingen resultater" eller fejl
- Åbn `test_api.php` for at se om APIet virker
- Tjek browser konsol (F12) for fejlmeddelelser

### "Permission denied"
```bash
chmod 755 logs
chmod 644 reports.json
```

### Tomt forum
- Forum er tomt til at starte med - det er normalt!
- Vær den første til at teste en besked

## Næste skridt

1. **Test med egne beskeder** - Indsæt mistænkelige SMS/emails du har modtaget
2. **Del med familie** - Vis værktøjet til familie og venner
3. **Lær at kende mønstrene** - Brug "Gode råd" sektionen til at lære mere

## Vigtige tip

✓ **Altid tjek domænet** - Hold musen over links for at se det rigtige webadresse  
✓ **Ring i stedet** - Hvis i tvivl, ring til virksomheden på kendt nummer  
✓ **Del aldrig koder** - Ægte firmaer spørger aldrig efter adgangskoder via SMS/email  
✓ **Tag dig tid** - Svindlere vil have dig til at handle hurtigt  

## Support

Hvis du oplever problemer:
1. Tjek at PHP 7.4+ er installeret: `php -v`
2. Tjek at Apache kører: `sudo apachectl status`
3. Se logs i `logs/` mappen for fejldetaljer

---

**Husk**: Dette er et hjælpeværktøj. Brug din sunde fornuft. Når i tvivl, kontakt virksomheden direkte.