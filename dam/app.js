'use strict';

const $ = (id) => document.getElementById(id);
let mySlot = null;        // 'W' | 'B' | null
let knownRev = 0;
let game = null;          // seneste game-state
let legal = {};           // server-beregnete lovlige træk: { "r,c": ["r,c", ...] }
let slots = { W: false, B: false };
let selected = null;      // {r,c}
let currentTargets = new Set();

async function api(action, params = {}, method = 'GET') {
  const url = new URL('api.php', location.href);
  url.searchParams.set('action', action);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  const opts = { method, credentials: 'same-origin' };
  const res = await fetch(url.toString(), opts);
  let data;
  try { data = await res.json(); } catch { throw new Error('Ugyldigt svar fra server'); }
  if (!res.ok || !data.ok) {
    const e = new Error(data.error || `HTTP ${res.status}`);
    e.payload = data;
    throw e;
  }
  return data;
}

async function ensureJoined() {
  if (mySlot) return;
  try {
    const d = await api('join', {}, 'POST');
    mySlot = d.slot;
  } catch (e) {
    $('status').textContent = 'Kunne ikke deltage: ' + e.message;
  }
}

function pieceClass(p) {
  if (!p) return '';
  let cls = 'piece ' + (p[0] === 'W' ? 'w' : 'b');
  if (p.length === 2) cls += ' king';
  return cls;
}

function render() {
  const boardEl = $('board');
  boardEl.innerHTML = '';
  if (!game) return;

  const cont = game.continuing;

  for (let r = 0; r < 8; r++) {
    for (let c = 0; c < 8; c++) {
      const sq = document.createElement('div');
      const isDark = (r + c) % 2 === 1;
      sq.className = 'square ' + (isDark ? 'dark' : 'light');
      sq.dataset.r = r;
      sq.dataset.c = c;
      if (cont === `${r},${c}`) sq.classList.add('lastmove');

      const p = game.board[r][c];
      if (p) {
        const piece = document.createElement('div');
        piece.className = pieceClass(p);
        sq.appendChild(piece);
      }

      if (selected && currentTargets.has(`${r},${c}`)) sq.classList.add('target');
      if (selected && selected.r === r && selected.c === c) sq.classList.add('selected');

      sq.addEventListener('click', onSquareClick);
      boardEl.appendChild(sq);
    }
  }

  const cols = 'abcdefgh';
  $('collabels').innerHTML = cols.split('').map(x => `<div>${x}</div>`).join('');
  $('rowlabels').innerHTML = [7,6,5,4,3,2,1,0].map(n => `<div>${n+1}</div>`).join('');

  updateStatus();
}

async function onSquareClick(e) {
  if (!game || game.winner) return;
  const r = +e.currentTarget.dataset.r;
  const c = +e.currentTarget.dataset.c;
  const piece = game.board[r][c];

  // Trin 1: Hvis en brik er valgt og dette er et grønt målfelt -> udfør træk.
  if (selected && currentTargets.has(`${r},${c}`)) {
    const from = `${selected.r},${selected.c}`;
    try {
      await api('move', { from, to: `${r},${c}` }, 'POST');
      selected = null;
      currentTargets.clear();
      pollNow();
    } catch (err) {
      flash(err.message);
      selected = null;
      currentTargets.clear();
      render();
    }
    return;
  }

  // Trin 2: Vælg en brik — med tydelig feedback.
  if (piece) {
    if (!mySlot) {
      flash('Du er ikke forbundet til spillet endnu. Vent et øjeblik eller genindlæs siden.');
      return;
    }
    if (piece[0] !== mySlot) {
      flash('Det er modstanderens brik.');
      return;
    }
    if (game.turn !== mySlot) {
      flash('Det er ikke din tur endnu.');
      return;
    }
    if (game.continuing && game.continuing !== `${r},${c}`) {
      flash('Du skal fortsætte med den brik der lige slog.');
      return;
    }
    // Brug serverens lovlige træk (autoritativt).
    const key = `${r},${c}`;
    const targets = legal[key] || [];
    currentTargets = new Set(targets);
    if (currentTargets.size === 0) {
      const anyLegal = Object.keys(legal).length > 0;
      if (anyLegal) {
        flash('Du skal flytte en anden brik (tvunget slag gælder).');
      } else {
        flash('Ingen lovlige træk tilgængelige — vent på serveren.');
      }
      selected = null;
    } else {
      selected = { r, c };
    }
    render();
  } else {
    // Klik på tomt felt uden at være et mål -> afvælg.
    if (selected) {
      selected = null;
      currentTargets.clear();
      render();
    }
  }
}

function flash(msg) {
  const s = $('status');
  s.textContent = '⚠ ' + msg;
  setTimeout(updateStatus, 3000);
}

function updateStatus() {
  const s = $('status');
  const slotBadge = (slot, label) => {
    const filled = slots[slot];
    const me = mySlot === slot;
    return `<span class="slot ${filled ? (slot === 'W' ? 'w' : 'b') : 'empty'}"></span>${label}${me ? ' <span class="you">(dig)</span>' : ''}`;
  };
  let line;
  if (!game) {
    line = 'Forbinder…';
  } else if (game.winner) {
    const w = game.winner === 'W' ? 'Hvid (W)' : 'Sort (B)';
    line = `🏆 ${w} vandt! Træk i alt: ${game.moveCount}`;
  } else if (!mySlot) {
    line = '⏳ Forbinder til spillet…';
  } else {
    const turnName = game.turn === 'W' ? 'Hvid (W)' : 'Sort (B)';
    const mine = game.turn === mySlot;
    const cont = game.continuing ? ' — fortsæt kaskade-slå!' : '';
    const wait = !mine ? ' — vent på modstander' : '';
    line = `Tur: ${turnName}${mine ? ' — din tur, klik en brik' : ''}${cont}${wait}`;
  }
  s.innerHTML = `${slotBadge('W','Hvid')} &nbsp;&nbsp; ${slotBadge('B','Sort')}<br>${line}`;
}

let polling = false;

async function pollNow() {
  if (polling) return;
  polling = true;
  try {
    const d = await api('status', { rev: knownRev });
    const changed = d.rev !== knownRev;
    knownRev = d.rev;
    game = d.game;
    legal = d.legal || {};
    slots = d.slots;
    // Ryd kun valg hvis brættet faktisk ændrede sig (ikke ved gentagne identiske poll).
    if (changed) {
      selected = null;
      currentTargets.clear();
    }
    render();
  } catch (e) {
    $('status').textContent = 'Forbindelsesfejl: ' + e.message;
  } finally {
    polling = false;
  }
}

async function pollLoop() {
  while (true) {
    await pollNow();
    await new Promise(r => setTimeout(r, 500));
  }
}

$('reset').addEventListener('click', async () => {
  if (!confirm('Nulstil spil og smid begge spillere ud?')) return;
  try {
    await api('reset', {}, 'POST');
    mySlot = null;
    selected = null;
    currentTargets.clear();
    knownRev = 0;
    await ensureJoined();
    pollNow();
  } catch (e) {
    flash(e.message);
  }
});

(async () => {
  await ensureJoined();
  await pollNow();
  pollLoop();
})();
