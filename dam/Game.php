<?php
declare(strict_types=1);

/**
 * Spilmotor for netværksbaseret Dam (international dam med "land-bagved"-regel).
 *
 * Regler:
 *  - 8x8 bræt, brikker på mørke felter ((r+c) ulige).
 *  - Hvid (W) starter nederst (række 5-7) og rykker OPAD (mod række 0).
 *  - Sort (B) starter øverst (række 0-2) og rykker NEDAD (mod række 7).
 *  - Menig brik: rykker 1 felt diagonalt fremad. Slår ved at hoppe over en
 *    modstander-brik til det tomt felt umiddelbart bagved.
 *  - Konge/Dam (WK/BK): GLIDER et vilkårligt antal tomme felter diagonalt i alle
 *    4 retninger (som et tårn på diagonalen). Ved slag: hopper over en
 *    modstander-brik og SKAL lande på feltet UMMIDELBART bagved denne brik
 *    (ikke længere ude). Dette muliggør fælde-strategi.
 *  - Tvunget slag: hvis mindst ét slag er muligt, SKAL spilleren slå.
 *  - Kaskade-slag: efter et slag, hvis samme brik kan slå igen (i hvilken som
 *    helst retning for Dammen), fortsætter turen.
 *  - Promovering: en menig brik der når modstanderens bagrække bliver Dam.
 *    Promovering afslutter brikken tur.
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
     * Menig brik: 1 felt fremad, slag ved hop over 1 modstander-brik til felt bagved.
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
     * Konge/Dam: glider vilkårligt antal tomme felter diagonalt i alle retninger.
     * Ved slag: hopper over ÉN modstander-brik og SKAL lande på feltet umiddelbart
     * bagved denne brik (streng "land-bagved"-regel — muliggør fælder).
     * @return array{0: list<string>, 1: list<string>}
     */
    private function kingMoves(int $r, int $c, string $piece): array
    {
        $diagonals = [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        $captures = [];
        $simple = [];

        foreach ($diagonals as [$dr, $dc]) {
            $step = 1;
            while (true) {
                $nr = $r + $step * $dr;
                $nc = $c + $step * $dc;
                if (!$this->inBounds($nr, $nc)) {
                    break;
                }
                $cell = $this->board[$nr][$nc];
                if ($cell === '') {
                    // To felt — kan lande her som simpelt træk, eller fortsætte.
                    $simple[] = "$nr,$nc";
                    $step++;
                    continue;
                }
                // Ikke-tom felt: hvis modstander, kan måske slås; hvis egen, blokeret.
                if ($cell[0] === $piece[0]) {
                    break; // egen brik blokerer diagonalen.
                }
                // Modstander-brik fundet på (nr,nc). Landingsfeltet er umiddelbart bagved.
                $landR = $nr + $dr;
                $landC = $nc + $dc;
                if ($this->inBounds($landR, $landC) && $this->board[$landR][$landC] === '') {
                    $captures[] = "$landR,$landC";
                }
                // Uanset om landingen var mulig eller ej, kan vi ikke fortsætte
                // forbi denne modstander på diagonalen (kun ét slag pr. diagonal-prøve).
                break;
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

        // Et slag findes når destinationen er 2 felter væk (menig) ELLER når
        // der ligger en modstander-brik umiddelbart før destinationen (Dam).
        $isCapture = $this->isCaptureMove($fromR, $fromC, $toR, $toC, $piece);
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
     * Er dette træk et slag? For menige: destination 2 felter væk.
     * For Dam: der findes en modstander-brik umiddelbart før destinationen
     * på diagonalen (streng land-bagved-regel).
     */
    private function isCaptureMove(int $fromR, int $fromC, int $toR, int $toC, string $piece): bool
    {
        if (!$this->isKing($piece)) {
            return abs($toR - $fromR) === 2;
        }
        // Dam: find feltet umiddelbart før destinationen.
        $dr = ($toR > $fromR) ? 1 : -1;
        $dc = ($toC > $fromC) ? 1 : -1;
        $preR = $toR - $dr;
        $preC = $toC - $dc;
        if (!$this->inBounds($preR, $preC)) {
            return false;
        }
        $cell = $this->board[$preR][$preC];
        return $cell !== '' && $cell[0] !== $piece[0];
    }

    /**
     * Fjern den slåede brik. For menige: feltet midt imellem.
     * For Dam (land-bagved-regel): feltet umiddelbart før destinationen.
     */
    private function removeCaptured(int $fromR, int $fromC, int $toR, int $toC, string $piece): void
    {
        if (!$this->isKing($piece)) {
            $midR = $fromR + (int) (($toR - $fromR) / 2);
            $midC = $fromC + (int) (($toC - $fromC) / 2);
            $this->board[$midR][$midC] = '';
            return;
        }
        // Dam: fjern feltet umiddelbart før destinationen på diagonalen.
        $dr = ($toR > $fromR) ? 1 : -1;
        $dc = ($toC > $fromC) ? 1 : -1;
        $preR = $toR - $dr;
        $preC = $toC - $dc;
        if ($this->inBounds($preR, $preC)) {
            $this->board[$preR][$preC] = '';
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
