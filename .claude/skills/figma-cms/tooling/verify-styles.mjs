/**
 * Boucle FERMÉE de vérification des styles : mesure le rendu Chrome et le confronte
 * aux tokens Figma. Transforme la « rigueur » prescrite en GATE exécutable — sort en
 * code 1 si un style rendu diverge de la maquette au-delà de la tolérance.
 *
 * Usage (depuis la RACINE du projet, où puppeteer-core est installé) :
 *   node .claude/skills/figma-cms/tooling/verify-styles.mjs <url> <figma-tokens.<page>.json> [options]
 *
 * Vérifie : pour les TEXT — font-size/weight/letter-spacing/line-height/text-transform/color ;
 * pour les CONTENEURS auto-layout (FRAME à padding/gap non nul) — padding top/right/bottom/left
 * et `gap` (espacement entre enfants, mesuré géométriquement). Seuls les paddings ATTENDUS non nuls
 * sont vérifiés (un pad=0 Figma ≠ absence de padding CMS : gouttières Bootstrap).
 *
 * Options :
 *   --map <map.json>       mapping explicite { "<nodeId>": "<sélecteur CSS>" } (prime sur le texte)
 *   --tol-px <n>           tolérance fontSize / letterSpacing en px (défaut 1)
 *   --tol-lh <n>           tolérance line-height en px (défaut 2)
 *   --tol-color <n>        tolérance couleur par canal 0-255 (défaut 10)
 *   --tol-box <n>          tolérance padding de conteneur en px (défaut 2)
 *   --no-box               désactive la vérification des paddings (TEXT uniquement)
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
  console.error('Usage: node verify-styles.mjs <url> <figma-tokens.json> [--map m.json] [--tol-px 1] [--tol-lh 2] [--tol-color 10] [--tol-box 2] [--no-box] [--width 1440] [--strict-unmatched] [--only txt] [--out r.json]');
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
    if (selectorMap[tk.id]) { el = document.querySelector(selectorMap[tk.id]); how = 'map'; }
    if (!el) { const k = norm(tk.characters); const c = byOwn.get(k); if (c && c.length) { el = c[0]; how = 'own-text'; } }
    if (!el) { const k = norm(tk.characters); const c = byFull.get(k); if (c && c.length) { el = c[c.length - 1]; how = 'full-text'; } }
    return { id: tk.id, text: tk.characters.replace(/\s+/g, ' ').trim().slice(0, 40), matched: !!el, how, m: el ? measure(el) : null };
  });
  const boxRows = boxes.map((bx) => {
    let el = null;
    let how = null;
    if (selectorMap[bx.id]) { el = document.querySelector(selectorMap[bx.id]); how = 'map'; }
    if (!el) { const c = byFull.get(norm(bx.text)); if (c && c.length) { el = c[0]; how = 'full-text'; } }
    if (!el) { const c = byCompact.get(norm(bx.text).replace(/\s/g, '')); if (c && c.length) { el = c[0]; how = 'compact'; } }
    return { id: bx.id, matched: !!el, how, pad: el ? measurePad(el) : null, gap: el ? measureGap(el, bx.mode) : null };
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
let fails = 0;
let unmatched = 0;
for (const r of measured) {
  const tk = tokById.get(r.id);
  if (!r.matched) {
    unmatched++;
    rows.push({ id: r.id, text: r.text, matched: false, checks: [] });
    continue;
  }
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
  if (rowFail) fails++;
  rows.push({ id: r.id, text: r.text, matched: true, how: r.how, checks });
}

// ---- Comparaison paddings (conteneurs auto-layout) ----
const boxById = new Map(boxes.map((b) => [b.id, b]));
const boxRows = [];
let boxFails = 0;
let boxUnmatched = 0;
for (const r of result.boxRows) {
  const bx = boxById.get(r.id);
  if (!r.matched) {
    boxUnmatched++;
    boxRows.push({ id: r.id, name: bx.name, text: bx.text.slice(0, 40), matched: false, checks: [] });
    continue;
  }
  const checks = [];
  // Ne vérifier QUE les paddings attendus non nuls (un pad=0 côté Figma ne signifie pas absence de
  // padding côté CMS — gouttières Bootstrap → trop de faux positifs).
  for (const side of ['top', 'right', 'bottom', 'left']) {
    if (bx.pads[side] > 0) {
      checks.push({ prop: 'padding-' + side, ok: near(bx.pads[side], r.pad[side], TOL_BOX), exp: bx.pads[side] + 'px', got: r.pad[side] + 'px' });
    }
  }
  if (bx.gap > 0 && r.gap != null) {
    checks.push({ prop: 'gap', ok: near(bx.gap, r.gap, TOL_BOX), exp: bx.gap + 'px', got: r.gap + 'px' });
  }
  if (checks.length === 0) {
    boxRows.push({ id: r.id, name: bx.name, text: bx.text.slice(0, 40), matched: true, how: r.how, checks, skipped: true });
    continue;
  }
  if (checks.some((c) => !c.ok)) boxFails++;
  boxRows.push({ id: r.id, name: bx.name, text: bx.text.slice(0, 40), matched: true, how: r.how, checks });
}

// ---- Rapport ----
const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
console.log(`\nVérification styles — ${URL}  (viewport ${WIDTH}px)`);
console.log(`Tokens TEXT vérifiés : ${rows.length}  |  appariés : ${rows.length - unmatched}  |  non appariés : ${unmatched}\n`);

for (const row of rows) {
  if (!row.matched) {
    console.log(`${C.yellow}∅ NON APPARIÉ${C.reset}  «${row.text}»  ${C.dim}(${row.id})${C.reset}`);
    continue;
  }
  const bad = row.checks.filter((c) => !c.ok);
  if (bad.length === 0) {
    console.log(`${C.green}✓${C.reset} «${row.text}»  ${C.dim}${row.how}${C.reset}`);
  } else {
    console.log(`${C.red}✗ «${row.text}»${C.reset}  ${C.dim}${row.how} (${row.id})${C.reset}`);
    for (const c of bad) console.log(`    ${C.red}${c.prop}${C.reset} : attendu ${c.exp}, rendu ${c.got}`);
  }
}

if (boxRows.length) {
  console.log(`\n${C.dim}Paddings de conteneurs (auto-layout) :${C.reset}`);
  for (const row of boxRows) {
    if (row.skipped) continue;
    if (!row.matched) {
      console.log(`${C.yellow}∅ NON APPARIÉ${C.reset} [box] «${row.text}»`);
      continue;
    }
    const bad = row.checks.filter((c) => !c.ok);
    if (bad.length === 0) {
      console.log(`${C.green}✓${C.reset} [box] «${row.text}»  ${C.dim}${row.how}${C.reset}`);
    } else {
      console.log(`${C.red}✗ [box] «${row.text}»${C.reset}`);
      for (const c of bad) console.log(`    ${C.red}${c.prop}${C.reset} : attendu ${c.exp}, rendu ${c.got}`);
    }
  }
}

const matchedCount = rows.length - unmatched;
const boxMatched = boxRows.length - boxUnmatched;
console.log(`\n${C.dim}──────────${C.reset}`);
console.log(`Textes conformes : ${matchedCount - fails}/${matchedCount} (écart ${fails}, non appariés ${unmatched})`);
if (boxRows.length) {
  console.log(`Paddings conformes : ${boxMatched - boxFails}/${boxMatched} (écart ${boxFails}, non appariés ${boxUnmatched})`);
}

if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({ url: URL, width: WIDTH, total: rows.length, matched: matchedCount, fails, unmatched, rows, boxes: boxRows, boxFails, boxUnmatched }, null, 2));
  console.log(`Rapport : ${OUT}`);
}

const failed = fails > 0 || boxFails > 0 || (STRICT_UNMATCHED && (unmatched > 0 || boxUnmatched > 0));
if (failed) {
  console.log(`${C.red}GATE STYLES : ÉCHEC${C.reset} (${fails} texte(s), ${boxFails} padding(s)${STRICT_UNMATCHED ? `, ${unmatched + boxUnmatched} non apparié(s)` : ''})`);
  process.exit(1);
}
console.log(`${C.green}GATE STYLES : OK${C.reset}`);
process.exit(0);
