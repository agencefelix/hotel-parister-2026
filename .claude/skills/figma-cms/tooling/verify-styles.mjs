/**
 * Boucle FERMÉE de vérification des styles : mesure le rendu Chrome et le confronte
 * aux tokens Figma. Transforme la « rigueur » prescrite en GATE exécutable — sort en
 * code 1 si un style rendu diverge de la maquette au-delà de la tolérance.
 *
 * Usage (depuis la RACINE du projet, où puppeteer-core est installé) :
 *   node .claude/skills/figma-cms/tooling/verify-styles.mjs <url> <figma-tokens.<page>.json> [options]
 *
 * SÉPARATION CONTENU vs STYLE (3 buckets) — un mur de rouge n'est pas exploitable si une partie
 * vient de ce que le rendu n'a pas la même COPIE que la maquette. Chaque token est classé :
 *   - FIABLE  : texte UNIQUE dans les tokens ET 1 seul élément DOM correspondant → DÉCISIF (ses
 *               écarts de style font échouer le gate) ;
 *   - AMBIGU  : texte en double (plusieurs tokens ou candidats DOM) → mesuré mais NON décisif
 *               (un « Découvrir » répété s'apparie au hasard) — informatif, fiabiliser via --map ;
 *   - CONTENU : texte maquette ABSENT du rendu (non apparié) → signal de CONTENU, pas de style ;
 *               n'échoue le gate qu'avec --strict-unmatched.
 * Seuls les écarts sur éléments FIABLES font échouer le gate.
 *
 * Vérifie : pour les TEXT — font-size/weight/letter-spacing/line-height/text-transform/color ;
 * pour les CONTENEURS auto-layout (FRAME à padding/gap non nul) — padding top/right/bottom/left
 * et `gap` (espacement entre enfants, mesuré géométriquement). Seuls les paddings ATTENDUS non nuls
 * sont vérifiés (un pad=0 Figma ≠ absence de padding CMS : gouttières Bootstrap). SCALE-AWARE : le
 * px Figma attendu est snappé au niveau de l'échelle `$margins` le plus proche (le rendu CMS étant
 * quantifié) — évite les faux échecs sur une valeur Figma hors-échelle. `--no-scale` pour désactiver.
 *
 * Options :
 *   --map <map.json>       mapping explicite { "<nodeId>": "<sélecteur CSS>" } (prime sur le texte)
 *   --tol-px <n>           tolérance fontSize / letterSpacing en px (défaut 1)
 *   --tol-lh <n>           tolérance line-height en px (défaut 2)
 *   --tol-color <n>        tolérance couleur par canal 0-255 (défaut 10)
 *   --tol-box <n>          tolérance padding de conteneur en px (défaut 2)
 *   --no-box               désactive la vérification des paddings (TEXT uniquement)
 *   --scss <chemin>        variables.scss pour l'échelle de marges (défaut assets/scss/front/default/variables.scss)
 *   --bp <clé>             breakpoint de réf de l'échelle (défaut "xxl" = desktop) — aligner avec --width
 *   --no-scale             compare le padding/gap au px Figma BRUT (désactive le snap sur l'échelle)
 *   --width <n>            largeur viewport (défaut 1440) — relancer par breakpoint pour le responsive
 *   --strict-unmatched     échoue aussi si un token texte n'a AUCUN élément correspondant
 *   --only <substr>        ne vérifie que les tokens dont le texte contient <substr> (debug)
 *   --out <report.json>    écrit le rapport détaillé
 *
 * Appariement : chaque token TEXT est relié à l'élément DOM dont le texte (normalisé) correspond.
 * Un CONTENEUR (pas d'identité texte) est relié via le texte de ses TEXT contenus géométriquement
 * (puis match DOM insensible aux espaces). `--map` force un sélecteur dans les deux cas.
 *
 * Prérequis : Chrome installé ; puppeteer-core dans node_modules du projet.
 */
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const args = process.argv.slice(2);
const URL = args[0];
const TOKENS_PATH = args[1];
if (!URL || !TOKENS_PATH) {
  console.error('Usage: node verify-styles.mjs <url> <figma-tokens.json> [--map m.json] [--tol-px 1] [--tol-lh 2] [--tol-color 10] [--tol-box 2] [--no-box] [--scss v.scss] [--bp xxl] [--no-scale] [--width 1440] [--strict-unmatched] [--only txt] [--out r.json]');
  process.exit(2);
}
const opt = (name, def) => {
  const i = args.indexOf(name);
  return i !== -1 && args[i + 1] ? args[i + 1] : def;
};
const flag = (name) => args.includes(name);

const TOL_PX = parseFloat(opt('--tol-px', '1'));
const TOL_LH = parseFloat(opt('--tol-lh', '2'));
const TOL_COLOR = parseInt(opt('--tol-color', '10'), 10);
const TOL_BOX = parseFloat(opt('--tol-box', '2'));
const NO_BOX = flag('--no-box');
const SCALE_SCSS = opt('--scss', 'assets/scss/front/default/variables.scss');
const SCALE_BP = opt('--bp', 'xxl');
const NO_SCALE = flag('--no-scale');
const WIDTH = parseInt(opt('--width', '1440'), 10);
const STRICT_UNMATCHED = flag('--strict-unmatched');
const ONLY = opt('--only', null);
const OUT = opt('--out', null);
const MAP_PATH = opt('--map', null);
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const raw = JSON.parse(fs.readFileSync(TOKENS_PATH, 'utf8'));
const items = raw.items || raw.nodes || (Array.isArray(raw) ? raw : []);
const selectorMap = MAP_PATH ? JSON.parse(fs.readFileSync(MAP_PATH, 'utf8')) : {};

// Tokens TEXT exploitables : un libellé d'au moins 2 caractères.
let tokens = items.filter((n) => (n.type === 'TEXT') && typeof n.characters === 'string' && n.characters.replace(/\s+/g, '').length >= 2);
if (ONLY) {
  const needle = ONLY.toLowerCase();
  tokens = tokens.filter((t) => t.characters.toLowerCase().includes(needle));
}

if (tokens.length === 0) {
  console.error('Aucun token TEXT exploitable dans ' + TOKENS_PATH);
  process.exit(2);
}

// Fréquence du texte (normalisé) parmi les tokens : un texte porté par >1 token est AMBIGU
// (un « Découvrir » répété s'apparie au hasard à un DOM) → mesuré mais non décisif pour le gate.
const normKey = (s) => (s || '').replace(/\s+/g, ' ').trim().toLowerCase();
const tokTextFreq = new Map();
for (const t of tokens) tokTextFreq.set(normKey(t.characters), (tokTextFreq.get(normKey(t.characters)) || 0) + 1);

// Conteneurs (auto-layout) à padding NON NUL → vérification des paddings rendus.
// Le conteneur n'a pas d'identité texte → on la reconstruit par CONTENANCE GÉOMÉTRIQUE :
// les TEXT dont le centre tombe dans la bbox du conteneur (coords relatives au même root).
const allTexts = items.filter((n) => n.type === 'TEXT' && typeof n.characters === 'string' && n.characters.trim() !== '' && typeof n.x === 'number');
let boxes = [];
if (!NO_BOX) {
  for (const n of items) {
    const L = n.layout;
    if (!L || typeof n.x !== 'number' || typeof n.w !== 'number') continue;
    const pads = { top: L.padTop || 0, right: L.padRight || 0, bottom: L.padBottom || 0, left: L.padLeft || 0 };
    const gap = L.gap || 0;
    if (!pads.top && !pads.right && !pads.bottom && !pads.left && !gap) continue; // rien à vérifier
    const inside = allTexts.filter((t) => {
      const cx = t.x + (t.w || 0) / 2, cy = t.y + (t.h || 0) / 2;
      return cx >= n.x && cx <= n.x + n.w && cy >= n.y && cy <= n.y + n.h;
    });
    if (inside.length === 0) continue;
    inside.sort((a, b) => a.y - b.y);
    const text = inside.map((t) => t.characters).join(' ').replace(/\s+/g, ' ').trim();
    if (text.replace(/\s+/g, '').length < 3) continue;
    boxes.push({ id: n.id, name: (n.name || '').slice(0, 24), pads, gap, mode: L.mode || 'VERTICAL', text, area: n.w * (n.h || 0) });
  }
  // Dédupe par texte : en cas de conteneurs imbriqués au même texte, garder le plus GRAND
  // (conteneur extérieur = celui dont le CMS pilote le padding : zone/col/bloc).
  const byText = new Map();
  for (const b of boxes) {
    const k = b.text.toLowerCase();
    if (!byText.has(k) || b.area > byText.get(k).area) byText.set(k, b);
  }
  boxes = [...byText.values()];
}
// Fréquence du texte de conteneur (pour la même règle d'ambiguïté côté paddings).
const boxTextFreq = new Map();
for (const b of boxes) boxTextFreq.set(normKey(b.text), (boxTextFreq.get(normKey(b.text)) || 0) + 1);

// Échelle de marges (optionnelle) : rend la gate « scale-aware ». Le rendu CMS est QUANTIFIÉ sur
// l'échelle $margins (niveaux), donc on snappe le px Figma attendu au niveau le plus proche (par axe)
// avant comparaison — sinon une valeur Figma hors-échelle (ex. 80→niveau 90) ferait un faux échec.
let scale = null;
if (!NO_SCALE && !NO_BOX && fs.existsSync(SCALE_SCSS)) {
  try {
    const scss = fs.readFileSync(SCALE_SCSS, 'utf8');
    const toPx = (v, u) => u === 'rem' ? parseFloat(v) * 16 : parseFloat(v);
    const parseInner = (s) => {
      const map = { 0: 0 };
      for (const m of (s || '').matchAll(/'([a-z0-9]+)'\s*:\s*([\d.]+)(px|rem)/g)) map[m[1]] = toPx(m[2], m[3]);
      return Object.values(map);
    };
    const block = scss.match(new RegExp(`'${SCALE_BP}'\\s*:\\s*\\(\\s*'x'\\s*:\\s*\\(([^)]*)\\)\\s*,\\s*'y'\\s*:\\s*\\(([^)]*)\\)`));
    if (block) scale = { x: parseInner(block[1]), y: parseInner(block[2]) };
  } catch { /* échelle indisponible → comparaison px brute */ }
}
// Snappe une valeur au niveau d'échelle le plus proche sur l'axe (ou la renvoie telle quelle).
const snap = (axis, value) => {
  if (!scale || !scale[axis]) return value;
  let best = value, bestD = Infinity;
  for (const lvl of scale[axis]) { const d = Math.abs(lvl - value); if (d < bestD) { bestD = d; best = lvl; } }
  return best;
};

const browser = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--ignore-certificate-errors', '--no-sandbox', '--disable-gpu'],
  ignoreHTTPSErrors: true,
});
const page = await browser.newPage();
await page.setViewport({ width: WIDTH, height: 900 });
await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });
await sleep(1200);
// Déclenche le lazy-load puis revient en haut (mêmes précautions que capture.mjs).
await page.evaluate(async () => {
  await new Promise((r) => { let y = 0; const t = setInterval(() => { window.scrollBy(0, 800); y += 800; if (y >= document.body.scrollHeight) { clearInterval(t); r(); } }, 60); });
});
await page.evaluate(() => window.scrollTo(0, 0));
await sleep(400);

// Mesure dans le contexte de la page : apparie chaque token à un élément et relève ses computed styles.
const result = await page.evaluate((tokens, boxes, selectorMap) => {
  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim().toLowerCase();
  const rgbToHex = (rgb) => {
    const m = (rgb || '').match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const [r, g, b] = m[1].split(',').map((v) => parseInt(v.trim(), 10));
    return '#' + [r, g, b].map((x) => x.toString(16).padStart(2, '0')).join('');
  };
  const ownText = (el) => {
    let t = '';
    for (const n of el.childNodes) if (n.nodeType === 3) t += n.textContent;
    return t;
  };

  // Index des éléments par texte propre (le wrapper direct du texte) puis par texte de sous-arbre.
  const all = Array.from(document.querySelectorAll('body *'));
  const byOwn = new Map();
  const byFull = new Map();
  const byCompact = new Map(); // texte sous-arbre sans espaces : le textContent DOM ne met pas
                               // d'espace entre éléments frères → robustifie le match des conteneurs
  for (const el of all) {
    if (!el.offsetParent && el.tagName !== 'BODY') { /* gardé quand même : peut être visible via position */ }
    const o = norm(ownText(el));
    if (o.length >= 2) { (byOwn.get(o) || byOwn.set(o, []).get(o)).push(el); }
    const f = norm(el.textContent);
    if (f.length >= 2) { (byFull.get(f) || byFull.set(f, []).get(f)).push(el); }
    const fc = f.replace(/\s/g, '');
    if (fc.length >= 2) { (byCompact.get(fc) || byCompact.set(fc, []).get(fc)).push(el); }
  }

  const measure = (el) => {
    const cs = getComputedStyle(el);
    return {
      fontSizePx: parseFloat(cs.fontSize),
      fontWeight: parseInt(cs.fontWeight, 10) || cs.fontWeight,
      letterSpacingPx: cs.letterSpacing === 'normal' ? 0 : parseFloat(cs.letterSpacing),
      lineHeightPx: cs.lineHeight === 'normal' ? null : parseFloat(cs.lineHeight),
      textTransform: cs.textTransform,
      colorHex: rgbToHex(cs.color),
      tag: el.tagName.toLowerCase(),
    };
  };
  const measurePad = (el) => {
    const cs = getComputedStyle(el);
    return { top: parseFloat(cs.paddingTop) || 0, right: parseFloat(cs.paddingRight) || 0, bottom: parseFloat(cs.paddingBottom) || 0, left: parseFloat(cs.paddingLeft) || 0 };
  };
  // Gap GÉOMÉTRIQUE entre enfants consécutifs (médiane) : robuste quel que soit le moyen
  // (CSS gap, marges, gouttières). Descend dans un wrapper unique (track de carrousel…).
  const measureGap = (el, mode) => {
    let kids = Array.from(el.children);
    let guard = 0;
    while (kids.length === 1 && kids[0].children.length > 1 && guard++ < 3) kids = Array.from(kids[0].children);
    const rects = kids.map((k) => k.getBoundingClientRect()).filter((r) => r.width > 0 && r.height > 0);
    if (rects.length < 2) return null;
    const horizontal = mode === 'HORIZONTAL';
    rects.sort((a, b) => horizontal ? a.left - b.left : a.top - b.top);
    const gaps = [];
    for (let i = 1; i < rects.length; i++) {
      gaps.push(Math.max(0, Math.round(horizontal ? rects[i].left - rects[i - 1].right : rects[i].top - rects[i - 1].bottom)));
    }
    gaps.sort((a, b) => a - b);
    return gaps[Math.floor(gaps.length / 2)];
  };

  const textRows = tokens.map((tk) => {
    let el = null;
    let how = null;
    let cands = 0;
    if (selectorMap[tk.id]) { el = document.querySelector(selectorMap[tk.id]); how = 'map'; cands = el ? 1 : 0; }
    if (!el) { const k = norm(tk.characters); const c = byOwn.get(k); if (c && c.length) { el = c[0]; how = 'own-text'; cands = c.length; } }
    if (!el) { const k = norm(tk.characters); const c = byFull.get(k); if (c && c.length) { el = c[c.length - 1]; how = 'full-text'; cands = c.length; } }
    return { id: tk.id, text: tk.characters.replace(/\s+/g, ' ').trim().slice(0, 40), matched: !!el, how, cands, m: el ? measure(el) : null };
  });
  const boxRows = boxes.map((bx) => {
    let el = null;
    let how = null;
    let cands = 0;
    if (selectorMap[bx.id]) { el = document.querySelector(selectorMap[bx.id]); how = 'map'; cands = el ? 1 : 0; }
    if (!el) { const c = byFull.get(norm(bx.text)); if (c && c.length) { el = c[0]; how = 'full-text'; cands = c.length; } }
    if (!el) { const c = byCompact.get(norm(bx.text).replace(/\s/g, '')); if (c && c.length) { el = c[0]; how = 'compact'; cands = c.length; } }
    return { id: bx.id, matched: !!el, how, cands, pad: el ? measurePad(el) : null, gap: el ? measureGap(el, bx.mode) : null };
  });
  return { textRows, boxRows };
}, tokens, boxes, selectorMap);

await browser.close();
const measured = result.textRows;

// ---- Comparaison token ↔ mesure ----
const txtCaseToTransform = { UPPER: 'uppercase', LOWER: 'lowercase', TITLE: 'capitalize' };
const tokById = new Map(tokens.map((t) => [t.id, t]));
const near = (a, b, tol) => (a == null || b == null) ? false : Math.abs(a - b) <= tol;
const colorNear = (a, b) => {
  if (!a || !b) return false;
  const pa = a.match(/\w\w/g).map((x) => parseInt(x, 16));
  const pb = b.match(/\w\w/g).map((x) => parseInt(x, 16));
  return pa.every((v, i) => Math.abs(v - pb[i]) <= TOL_COLOR);
};

const rows = [];
let fails = 0;          // écarts sur éléments FIABLES (décisifs pour le gate)
let unmatched = 0;      // CONTENU : texte maquette absent du rendu
let ambig = 0;          // AMBIGU : texte en double (mesuré, non décisif)
let ambigFails = 0;     // écarts parmi les ambigus (informatif)
for (const r of measured) {
  const tk = tokById.get(r.id);
  if (!r.matched) {
    unmatched++;
    rows.push({ id: r.id, text: r.text, matched: false, bucket: 'content', checks: [] });
    continue;
  }
  // Ambigu si le texte est porté par plusieurs tokens OU a plusieurs candidats DOM (sauf --map).
  const isAmbiguous = r.how !== 'map' && ((tokTextFreq.get(normKey(tk.characters)) || 1) > 1 || r.cands > 1);
  const checks = [];
  const push = (prop, ok, exp, got) => checks.push({ prop, ok, exp, got });

  push('font-size', near(tk.fontSize, r.m.fontSizePx, TOL_PX), tk.fontSize + 'px', r.m.fontSizePx + 'px');

  if (tk.fontWeight != null) {
    const expW = tk.fontWeight === 'normal' ? 400 : (tk.fontWeight === 'bold' ? 700 : tk.fontWeight);
    push('font-weight', String(r.m.fontWeight) === String(expW), String(expW), String(r.m.fontWeight));
  }
  if (tk.letterSpacing != null) {
    push('letter-spacing', near(tk.letterSpacing, r.m.letterSpacingPx, Math.max(TOL_PX, 0.5)), tk.letterSpacing + 'px', r.m.letterSpacingPx + 'px');
  }
  if (tk.lineHeightPx != null && r.m.lineHeightPx != null) {
    push('line-height', near(tk.lineHeightPx, r.m.lineHeightPx, TOL_LH), Math.round(tk.lineHeightPx) + 'px', r.m.lineHeightPx + 'px');
  }
  if (tk.textCase && txtCaseToTransform[tk.textCase]) {
    push('text-transform', r.m.textTransform === txtCaseToTransform[tk.textCase], txtCaseToTransform[tk.textCase], r.m.textTransform);
  }
  const expHex = tk.fills && tk.fills[0] && tk.fills[0].hex ? tk.fills[0].hex.toLowerCase() : null;
  if (expHex) {
    push('color', colorNear(expHex, r.m.colorHex), expHex, r.m.colorHex);
  }

  const rowFail = checks.some((c) => !c.ok);
  if (isAmbiguous) {
    ambig++;
    if (rowFail) ambigFails++;
  } else if (rowFail) {
    fails++;
  }
  rows.push({ id: r.id, text: r.text, matched: true, how: r.how, bucket: isAmbiguous ? 'ambiguous' : 'reliable', checks });
}

// ---- Comparaison paddings (conteneurs auto-layout) ----
const boxById = new Map(boxes.map((b) => [b.id, b]));
const boxRows = [];
let boxFails = 0;       // écarts paddings FIABLES (décisifs)
let boxUnmatched = 0;   // CONTENU
let boxAmbig = 0;       // AMBIGU
let boxAmbigFails = 0;
for (const r of result.boxRows) {
  const bx = boxById.get(r.id);
  if (!r.matched) {
    boxUnmatched++;
    boxRows.push({ id: r.id, name: bx.name, text: bx.text.slice(0, 40), matched: false, bucket: 'content', checks: [] });
    continue;
  }
  const isAmbiguousBox = r.how !== 'map' && ((boxTextFreq.get(normKey(bx.text)) || 1) > 1 || r.cands > 1);
  const checks = [];
  // Attendu = px Figma SNAPPÉ au niveau d'échelle le plus proche (scale-aware) ; sinon px brut.
  const fmtExp = (snapped, rawv) => snapped === rawv ? snapped + 'px' : `${snapped}px (≈Figma ${rawv})`;
  const sideAxis = { top: 'y', bottom: 'y', left: 'x', right: 'x' };
  // Ne vérifier QUE les paddings attendus non nuls (un pad=0 côté Figma ne signifie pas absence de
  // padding côté CMS — gouttières Bootstrap → trop de faux positifs).
  for (const side of ['top', 'right', 'bottom', 'left']) {
    if (bx.pads[side] > 0) {
      const exp = snap(sideAxis[side], bx.pads[side]);
      checks.push({ prop: 'padding-' + side, ok: near(exp, r.pad[side], TOL_BOX), exp: fmtExp(exp, bx.pads[side]), got: r.pad[side] + 'px' });
    }
  }
  if (bx.gap > 0 && r.gap != null) {
    const exp = snap(bx.mode === 'HORIZONTAL' ? 'x' : 'y', bx.gap);
    checks.push({ prop: 'gap', ok: near(exp, r.gap, TOL_BOX), exp: fmtExp(exp, bx.gap), got: r.gap + 'px' });
  }
  if (checks.length === 0) {
    boxRows.push({ id: r.id, name: bx.name, text: bx.text.slice(0, 40), matched: true, how: r.how, checks, skipped: true });
    continue;
  }
  const boxFail = checks.some((c) => !c.ok);
  if (isAmbiguousBox) {
    boxAmbig++;
    if (boxFail) boxAmbigFails++;
  } else if (boxFail) {
    boxFails++;
  }
  boxRows.push({ id: r.id, name: bx.name, text: bx.text.slice(0, 40), matched: true, how: r.how, bucket: isAmbiguousBox ? 'ambiguous' : 'reliable', checks });
}

// ---- Rapport (3 buckets : STYLE fiable / CONTENU / AMBIGU) ----
// On sépare ce qui est DÉCISIF (style des éléments fiables — texte unique, 1 seul match) de ce qui
// vient d'un CONTENU différent (token non apparié = la copie du rendu ≠ maquette) et des doublons
// AMBIGUS (texte répété, apparié au hasard) — pour un signal exploitable plutôt qu'un mur de rouge.
const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', cyan: '\x1b[36m', dim: '\x1b[2m', reset: '\x1b[0m' };
const reliableRows = rows.filter((r) => r.bucket === 'reliable');
const ambigRows = rows.filter((r) => r.bucket === 'ambiguous');
const contentRows = rows.filter((r) => r.bucket === 'content');
console.log(`\nVérification styles — ${URL}  (viewport ${WIDTH}px)`);
console.log(`Tokens TEXT : ${rows.length}  |  fiables : ${reliableRows.length}  |  ambigus : ${ambig}  |  non appariés (contenu) : ${unmatched}`);

console.log(`\n${C.cyan}── STYLE (éléments fiables — texte unique, 1 seul match) ──${C.reset}`);
for (const row of reliableRows) {
  const bad = row.checks.filter((c) => !c.ok);
  if (bad.length === 0) { console.log(`${C.green}✓${C.reset} «${row.text}»  ${C.dim}${row.how}${C.reset}`); continue; }
  console.log(`${C.red}✗ «${row.text}»${C.reset}  ${C.dim}${row.how} (${row.id})${C.reset}`);
  for (const c of bad) console.log(`    ${C.red}${c.prop}${C.reset} : attendu ${c.exp}, rendu ${c.got}`);
}

const reliableBoxes = boxRows.filter((r) => r.bucket === 'reliable' && !r.skipped);
if (reliableBoxes.length) {
  console.log(`\n${C.dim}Paddings de conteneurs (fiables) :${C.reset}`);
  for (const row of reliableBoxes) {
    const bad = row.checks.filter((c) => !c.ok);
    if (bad.length === 0) { console.log(`${C.green}✓${C.reset} [box] «${row.text}»  ${C.dim}${row.how}${C.reset}`); continue; }
    console.log(`${C.red}✗ [box] «${row.text}»${C.reset}`);
    for (const c of bad) console.log(`    ${C.red}${c.prop}${C.reset} : attendu ${c.exp}, rendu ${c.got}`);
  }
}

console.log(`\n${C.cyan}── CONTENU (texte maquette absent du rendu) ──${C.reset}`);
console.log(`${C.yellow}${unmatched}${C.reset} token(s) non apparié(s)${STRICT_UNMATCHED ? '' : ` ${C.dim}(n'échoue pas le gate sauf --strict-unmatched)${C.reset}`}`);
if (contentRows.length) console.log(`${C.dim}   ex. ${contentRows.slice(0, 6).map((r) => '«' + r.text.slice(0, 26) + '»').join(', ')}${contentRows.length > 6 ? '…' : ''}${C.reset}`);

if (ambig || boxAmbig) {
  console.log(`\n${C.cyan}── AMBIGU (texte en double — informatif, non décisif) ──${C.reset}`);
  console.log(`${C.dim}${ambig} texte(s) (${ambigFails} hors tolérance), ${boxAmbig} conteneur(s) (${boxAmbigFails} hors tolérance) — fiabiliser via --map si besoin${C.reset}`);
}

console.log(`\n${C.dim}──────────${C.reset}`);
console.log(`STYLE fiable : ${reliableRows.length - fails}/${reliableRows.length} conformes (${fails} écart${reliableBoxes.length ? `, paddings ${reliableBoxes.length - boxFails}/${reliableBoxes.length}` : ''})  |  CONTENU : ${unmatched} non appariés  |  AMBIGU : ${ambig + boxAmbig}`);

if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({
    url: URL, width: WIDTH, total: rows.length,
    style: { reliable: reliableRows.length, fails, boxReliable: reliableBoxes.length, boxFails },
    content: { unmatched, boxUnmatched },
    ambiguous: { texts: ambig, textFails: ambigFails, boxes: boxAmbig, boxFails: boxAmbigFails },
    rows, boxes: boxRows,
  }, null, 2));
  console.log(`Rapport : ${OUT}`);
}

// Le gate n'échoue QUE sur des écarts DÉCISIFS (éléments fiables). Les ambigus n'échouent jamais ;
// le contenu non apparié n'échoue qu'avec --strict-unmatched.
const failed = fails > 0 || boxFails > 0 || (STRICT_UNMATCHED && (unmatched > 0 || boxUnmatched > 0));
if (failed) {
  console.log(`${C.red}GATE STYLES : ÉCHEC${C.reset} (style fiable : ${fails} texte(s), ${boxFails} padding(s)${STRICT_UNMATCHED ? ` ; contenu : ${unmatched + boxUnmatched} non appariés` : ''})`);
  process.exit(1);
}
console.log(`${C.green}GATE STYLES : OK${C.reset}${unmatched ? ` ${C.dim}(${unmatched} non appariés ignorés — contenu)${C.reset}` : ''}`);
process.exit(0);
