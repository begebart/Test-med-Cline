<?php
declare(strict_types=1);

/**
 * Spilmotor for netværksbaseret Dam (international dam / "pool checkers"-agtig).
 *
 * Regler:
 *  - 8x8 bræt, brikker på mørke felter ((r+c) ulige).
 *  - Hvid (W) starter nederst (række 5-7) og rykker OPAD (mod række 0).
 *  - Sort (B) starter øverst (række 0-2) og rykker NEDAD (mod række 7).
 *  - Menig brik: rykker 1 felt diagonalt fremad. Slår ved at hoppe over en
 *    modstander-brik til det tomt felt umiddelbart bagved.
 *  - Konge (WK/BK): GLIDER et vilkårligt antal tomme felter diagonalt i alle
 *    4 retninger (som et tårn på diagonalen). Kan slå på afstand ("flyvende
 *    konge"): hopper over PRÆCIS ÉN modstander-brik på diagonalen og lander
 *    på et hvilket som helst tomt felt længere ude på samme diagonal.
 *  - Tvunget slag: hvis mindst ét slag er muligt, SKAL spilleren slå.
 *  - Kaskade-slag: efter et slag, hvis samme brik kan slå igen, fortsætter turen.
 *  - Promovering: en menig brik der når modstanderens bagrække bliver konge.
 *    Promovering afslutter brikken tur (kan ikke fortsætte kaskade efter promovering
 *    — "Harélé"-reglen fravalgt her for enkelthed).
 *  - Vinder: modstanderen har ingen brikker, eller ingen lovlige træk.
 */

final class Game
{
    public const SIZE = 8;
    public const EMPTY = '';

    /** @var array<int, array<int,string>> board række => kolonne => 'W'|'B'|'WK'|'BK'|'' */
    private array $board;

    private string $turn = 'W';          // 'W' eller 'B'
    private ?string $winner = null;      // 'W' | 'B' | null
    private ?string $continuing = null;  // felt "r,c" for en brik midt i en kaskade, ellers null
    private int $moveCount = 0;

    private function __construct(array $board, string $turn, ?string $winner, ?string $continuing, int $moveCount)
    {
        $this->board = self::normalizeBoard($board);
        $this->turn = $turn;
        $this->winner = $winner;
        $this->continuing = $continuing;
        $this->moveCount = $moveCount;
    }

    /**
     * Normaliser boardet til int-nøgler og fuld 8x8 struktur.
     * JSON-decoding giver string-nøgler ("0","1"...) — her sikrer vi int-nøgler.
     */
    private static function normalizeBoard(array $raw): array
    {
        $b = [];
        for ($r = 0; $r < self::SIZE; $r++) {
            $b[$r] = [];
            $rawRow = $raw[$r] ?? ($raw[(string) $r] ?? []);
            if (!is_array($rawRow)) {
                $rawRow = [];
            }
            for ($c = 0; $c < self::SIZE; $c++) {
                $val = $rawRow[$c] ?? ($rawRow[(string) $c] ?? '');
                $b[$r][$c] = is_string($val) ? $val : '';
            }
        }
        return $b;
    }

    public static function create(): self
    {
        $b = [];
        for ($r = 0; $r < self::SIZE; $r++) {
            $b[$r] = array_fill(0, self::SIZE, self::EMPTY);
        }
        for ($r = 0; $r < 3; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                if (($r + $c) % 2 === 1) {
                    $b[$r][$c] = 'B';
                }
            }
        }
        for ($r = 5; $r < 8; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                if (($r + $c) % 2 === 1) {
                    $b[$r][$c] = 'W';
                }
            }
        }
        return new self($b, 'W', null, null, 0);
    }

    public static function fromArray(array $state): self
    {
        return new self(
            $state['board'] ?? [],
            $state['turn'] ?? 'W',
            $state['winner'] ?? null,
            $state['continuing'] ?? null,
            $state['moveCount'] ?? 0
        );
    }

    public function toArray(): array
    {
        return [
            'board'       => $this->board,
            'turn'        => $this->turn,
            'winner'      => $this->winner,
            'continuing'  => $this->continuing,
            'moveCount'   => $this->moveCount,
        ];
    }

    public function turn(): string
    {
        return $this->turn;
    }

    public function winner(): ?string
    {
        return $this->winner;
    }

    public function board(): array
    {
        return $this->board;
    }

    public function continuing(): ?string
    {
        return $this->continuing;
    }

    /**
     * Returner alle lovlige træk for den aktuelle spiller som [from => [to,...]].
     * Hvis slag er mulige returneres KUN slag (tvunget slag).
     *
     * @return array<string, array<int,string>>
     */
    public function legalMoves(): array
    {
        $captures = [];
        $moves = [];

        for ($r = 0; $r < self::SIZE; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                $piece = $this->board[$r][$c];
                if ($piece === '' || $piece[0] !== $this->turn) {
                    continue;
                }
                $from = "$r,$c";
                if ($this->continuing !== null && $this->continuing !== $from) {
                    continue;
                }
                [$caps, $mvs] = $this->movesForPiece($r, $c, $piece);
                foreach ($caps as $to) {
                    $captures[$from][] = $to;
                }
                foreach ($mvs as $to) {
                    $moves[$from][] = $to;
                }
            }
        }

        return count($captures) > 0 ? $captures : $moves;
    }

    private function isKing(string $piece): bool
    {
        return strlen($piece) === 2;
    }

    /**
     * @return array{0: list<string>, 1: list<string>} [captures, simpleMoves]
     */
    private function movesForPiece(int $r, int $c, string $piece): array
    {
        return $this->isKing($piece)
            ? $this->kingMoves($r, $c, $piece)
            : $this->manMoves($r, $c, $piece);
    }

    /**
     * Menig brik: 1 felt fremad, slag ved hop over 1 modstander-brik.
     * @return array{0: list<string>, 1: list<string>}
     */
    private function manMoves(int $r, int $c, string $piece): array
    {
        $directions = $piece[0] === 'W'
            ? [[-1, -1], [-1, 1]]
            : [[1, -1], [1, 1]];

        $captures = [];
        $simple = [];

        foreach ($directions as [$dr, $dc]) {
            // Enkelt træk fremad til tomt felt.
            $nr = $r + $dr;
            $nc = $c + $dc;
            if ($this->inBounds($nr, $nc) && $this->board[$nr][$nc] === '') {
                $simple[] = "$nr,$nc";
            }
            // Slag: hop over modstander-brik til felt bagved (2 felter).
            $midR = $r + $dr;
            $midC = $c + $dc;
            $landR = $r + 2 * $dr;
            $landC = $c + 2 * $dc;
            if ($this->inBounds($landR, $landC)) {
                $mid = $this->board[$midR][$midC] ?? '';
                $land = $this->board[$landR][$landC] ?? '';
                if ($mid !== '' && $mid[0] !== $piece[0] && $land === '') {
                    $captures[] = "$landR,$landC";
                }
            }
        }

        return [$captures, $simple];
    }

    /**
     * Konge: glider vilkårligt antal tomme felter diagonalt. Flyvende konge-slag:
     * hop over PRÆCIS ÉN modstander-brik og land på et vilkårligt tomt felt længere
     * ude på samme diagonal.
     * @return array{0: list<string>, 1: list<string>}
     */
    private function kingMoves(int $r, int $c, string $piece): array
    {
        $diagonals = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        $captures = [];
        $simple = [];

        foreach ($diagonals as [$dr, $dc]) {
            $foundEnemy = false;
            $step = 1;
            while (true) {
                $nr = $r + $step * $dr;
                $nc = $c + $step * $dc;
                if (!$this->inBounds($nr, $nc)) {
                    break;
                }
                $cell = $this->board[$nr][$nc];
                if (!$foundEnemy) {
                    if ($cell === '') {
                        // Fri diagonal — kan lande her (simpelt træk) eller fortsætte.
                        $simple[] = "$nr,$nc";
                    } else {
                        // Ramte en brik. Hvis det er en modstander, kan måske slås.
                        if ($cell[0] !== $piece[0]) {
                            $foundEnemy = true;
                            // Næste felter undersøges i næste iteration.
                        } else {
                            // Egen brik blokerer.
                            break;
                        }
                    }
                } else {
                    // Vi har passeret en modstander-brik. Kan lande på tomt felt her.
                    if ($cell === '') {
                        $captures[] = "$nr,$nc";
                    } else {
                        // Anden brik blokerer — kan ikke lande her eller længere.
                        break;
                    }
                }
                $step++;
            }
        }

        return [$captures, $simple];
    }

    private function inBounds(int $r, int $c): bool
    {
        return $r >= 0 && $r < self::SIZE && $c >= 0 && $c < self::SIZE;
    }

    /**
     * Udfør et træk. Kaster InvalidArgumentException ved ulovligt træk.
     * Håndterer slag (fjern slået brik), kaskade, promovering og tur-skift.
     */
    public function move(int $fromR, int $fromC, int $toR, int $toC): void
    {
        if ($this->winner !== null) {
            throw new \InvalidArgumentException('Spillet er slut');
        }
        $legal = $this->legalMoves();
        $from = "$fromR,$fromC";
        $to = "$toR,$toC";
        if (!isset($legal[$from]) || !in_array($to, $legal[$from], true)) {
            throw new \InvalidArgumentException('Ulovligt træk');
        }

        $piece = $this->board[$fromR][$fromC];
        $this->board[$fromR][$fromC] = '';

        $isCapture = ($this->isKing($piece))
            ? $this->isKingCapture($fromR, $fromC, $toR, $toC, $piece)
            : (abs($toR - $fromR) === 2);

        if ($isCapture) {
            $this->removeCaptured($fromR, $fromC, $toR, $toC, $piece);
        }

        // Promovering ved modstanderens bagrække.
        $promoted = false;
        if (!$this->isKing($piece)) {
            if (($piece === 'W' && $toR === 0) || ($piece === 'B' && $toR === self::SIZE - 1)) {
                $piece .= 'K';
                $promoted = true;
            }
        }
        $this->board[$toR][$toC] = $piece;
        $this->moveCount++;

        // Kaskade: hvis det var et slag, ikke promoveret, og brikken kan slå igen.
        if ($isCapture && !$promoted) {
            [$caps,] = $this->movesForPiece($toR, $toC, $piece);
            if (count($caps) > 0) {
                $this->continuing = "$toR,$toC";
                return;
            }
        }

        $this->continuing = null;
        $this->endTurn();
    }

    /**
     * Er dette et konge-slag? Det er et slag hvis der findes en modstander-brik
     * på diagonalen mellem from og to (og alle andre felter derimellem er tomme).
     */
    private function isKingCapture(int $fromR, int $fromC, int $toR, int $toC, string $piece): bool
    {
        $dr = ($toR > $fromR) ? 1 : -1;
        $dc = ($toC > $fromC) ? 1 : -1;
        $dist = abs($toR - $fromR); // |dr|=|dc| på diagonal
        $enemies = 0;
        for ($s = 1; $s < $dist; $s++) {
            $r = $fromR + $s * $dr;
            $c = $fromC + $s * $dc;
            $cell = $this->board[$r][$c] ?? '';
            if ($cell !== '') {
                if ($cell[0] !== $piece[0]) {
                    $enemies++;
                } else {
                    return false; // egen brik blokerer
                }
            }
        }
        return $enemies === 1;
    }

    /**
     * Fjern den slåede brik for et træk (menig eller konge).
     */
    private function removeCaptured(int $fromR, int $fromC, int $toR, int $toC, string $piece): void
    {
        if (!$this->isKing($piece)) {
            // Menig: brikken midt imellem.
            $midR = $fromR + (int) (($toR - $fromR) / 2);
            $midC = $fromC + (int) (($toC - $fromC) / 2);
            $this->board[$midR][$midC] = '';
            return;
        }
        // Konge: find den modstander-brik på diagonalen og fjern den.
        $dr = ($toR > $fromR) ? 1 : -1;
        $dc = ($toC > $fromC) ? 1 : -1;
        $dist = abs($toR - $fromR);
        for ($s = 1; $s < $dist; $s++) {
            $r = $fromR + $s * $dr;
            $c = $fromC + $s * $dc;
            $cell = $this->board[$r][$c] ?? '';
            if ($cell !== '' && $cell[0] !== $piece[0]) {
                $this->board[$r][$c] = '';
                return;
            }
        }
    }

    private function endTurn(): void
    {
        $this->turn = $this->turn === 'W' ? 'B' : 'W';
        $this->checkWinner();
    }

    private function checkWinner(): void
    {
        $counts = ['W' => 0, 'B' => 0];
        foreach ($this->board as $row) {
            foreach ($row as $piece) {
                if ($piece !== '') {
                    $counts[$piece[0]]++;
                }
            }
        }
        if ($counts['W'] === 0) {
            $this->winner = 'B';
            return;
        }
        if ($counts['B'] === 0) {
            $this->winner = 'W';
            return;
        }
        if (count($this->legalMoves()) === 0) {
            $this->winner = $this->turn === 'W' ? 'B' : 'W';
        }
    }
}
