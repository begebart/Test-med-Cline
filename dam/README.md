# Dam — netværksbaseret spil for 2 spillere (PHP)

Et komplet Dam (Checkers) spil i ren PHP uden eksterne afhængigheder. To spillere
spiller mod hinanden, hver i sin browser, via delt tilstand på serveren.
**Kun 2 spillere** kan deltage ad gangen — en tredje der forsøger at joine får
besked om at spillet er fuldt.

## Krav

- PHP 8.0+ (Apache/Nginx + php-fpm anbefalet; `php -S` til test).
- Skrivbar mappe `data/` (oprettes automatisk med 0775).
- Ingen database, Composer eller eksterne biblioteker.

## Installation på Apache/Nginx + php-fpm

1. Kopiér alle filerne til en mappe på din webserver, fx `/var/www/dam/`.
2. Sørg for at `data/`-mappen er skrivbar af webserver-brugeren:
   ```bash
   mkdir -p /var/www/dam/data
   chown -R www-data:www-data /var/www/dam/data   # Debian/Ubuntu
   # eller apache:apache på RHEL/Fedora
   chmod 775 /var/www/dam/data
   ```
3. **Apache:** sørg for at `AllowOverride All` er sat for mappen så `.htaccess`
   virker (beskytter `data/`). Alternativt flyt reglerne direkte ind i din vhost.
4. **Nginx:** tilføj en location-blok der blokerer `data/`:
   ```nginx
   location ~ ^/dam/data/ { deny all; return 403; }
   ```
5. Åbn `https://din-server/dam/` i **to browsere**. Første = Hvid, næste = Sort.
6. En tredje besøgende får: *"Spillet er fuldt (2 spillere)."*

> Polling er kort (~6s) og robust mod php-fpm `max_execution_time` og proxy-timeout.
> Sæt alligevel gerne `max_execution_time = 30` og `set_time_limit(0)` er ikke krævet.

## Test uden webserver (valgfrit)

```bash
php -l Game.php api.php test_game.php   # syntakstjek
php test_game.php                        # regler-tests (bør vise "ALLE TESTS BESTÅET")
./start.sh 8000                          # lokal udviklingsserver på :8000
```

## Spilregler

- 8×8 bræt, brikker på mørke felter, 12 brikker pr. spiller.
- Hvid (nederst) rykker opad, Sort (øverst) rykker nedad.
- **Tvunget slag:** hvis et slag er muligt, skal du slå.
- **Kaskade-slag:** slår du en brik og kan slå igen med samme brik,
  fortsætter din tur (status viser "fortsæt kaskade-slå!").
- **Konge:** når en brik når modstanderens bagrække, bliver den konge og kan
  rykke/bevæge sig diagonalt i alle retninger. Promovering afslutter brikken tur.
- **Vinder:** modstanderen har ingen brikker tilbage, eller ingen lovlige træk.

## Filer

| Fil          | Beskrivelse                                                      |
|--------------|------------------------------------------------------------------|
| `Game.php`   | Spilmotor: bræt, regler, træk-validering, vinder-detektion.       |
| `api.php`    | HTTP-endpoint: `join`, `status` (kort-poll), `move`, `reset`.    |
| `index.html` | Brugerflade med bræt og styling.                                  |
| `app.js`     | Klient-logik: rendering, klik-træk, polling, tur-indikator.       |
| `.htaccess`  | Apache: directory-index + beskytter `data/`.                      |
| `start.sh`   | (kun test) Starter `php -S` udviklingsserver.                    |
| `test_game.php` | (kun test) Selvtjekkende regler-test.                          |
| `data/`      | Delt tilstand (`state.json`, `players.json`) + flock-lås.         |

## Sådan virker 2-spiller-begrænsningen

`api.php` tildeler to slots `W` og `B` via unikke tokens (gemt i en httpOnly
cookie). Når begge slots er udfyldt, afvises nye `join`-kald med HTTP 409.
Spil-tilstanden beskyttes af en `flock(LOCK_EX)` så to samtidige træk ikke
korrumperer brættet. Klienterne poller `status` (kort cyklus ~6s) for
live-opdateringer.

## Nulstil

Klik "Nulstil spil"-knappen i brugerfladen, eller:

```bash
rm -f /var/www/dam/data/state.json /var/www/dam/data/players.json
```
