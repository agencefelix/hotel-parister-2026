/**
 * Boucle FERMÉE de vérification des styles : mesure le rendu Chrome et le confronte
 * aux tokens Figma. Transforme la « rigueur » prescrite en GATE exécutable — sort en
 * code 1 si un style rendu diverge de la maquette au-delà de la tolérance.
 *
 * Usage (depuis la RACINE du projet, où puppeteer-core est installé) :
 *   node .claude/skills/figma-cms/tooling/verify-styles.mjs <url> <figma-tokens.<page>.json> [options]
 *
 * Options :
 *   --map <map.json>       mapping explicite { "<nodeId>": "<sélecteur CSS>" } (prime sur le texte)
 *   --tol-px <n>           tolérance fontSize / letterSpacing en px (défaut 1)
 *   --tol-lh <n>           tolérance line-height en px (défaut 2)
 *   --tol-color <n>        tolérance couleur par canal 0-255 (défaut 10)
 *   --width <n>            largeur viewport (défaut 1440) — relancer par breakpoint pour le responsive
 *   --strict-unmatched     échoue aussi si un token texte n'a AUCUN élément correspondant
 *   --only <substr>        ne vérifie que les tokens dont le texte contient <substr> (debug)
 *   --out <report.json>    écrit le rapport détaillé
 *
 * Appariement : par défaut, chaque token TEXT est relié à l'élément DOM dont le texte
 * (normalisé : espaces compactés, casse ignorée) correspond. `--map` force un sélecteur.
 *
 * Prérequis : Chrome installé ; puppeteer-core dans node_modules du projet.
 */
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const args = process.argv.slice(2);
const URL = args[0];
const TOKENS_PATH = args[1];
if (!URL || !TOKENS_PATH) {
  console.error('Usage: node verify-styles.mjs <url> <figma-tokens.json> [--map m.json] [--tol-px 1] [--tol-lh 2] [--tol-color 10] [--width 1440] [--strict-unmatched] [--only txt] [--out r.json]');
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
const measured = await page.evaluate((tokens, selectorMap) => {
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
  for (const el of all) {
    if (!el.offsetParent && el.tagName !== 'BODY') { /* gardé quand même : peut être visible via position */ }
    const o = norm(ownText(el));
    if (o.length >= 2) { (byOwn.get(o) || byOwn.set(o, []).get(o)).push(el); }
    const f = norm(el.textContent);
    if (f.length >= 2) { (byFull.get(f) || byFull.set(f, []).get(f)).push(el); }
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

  return tokens.map((tk) => {
    let el = null;
    let how = null;
    if (selectorMap[tk.id]) { el = document.querySelector(selectorMap[tk.id]); how = 'map'; }
    if (!el) { const k = norm(tk.characters); const c = byOwn.get(k); if (c && c.length) { el = c[0]; how = 'own-text'; } }
    if (!el) { const k = norm(tk.characters); const c = byFull.get(k); if (c && c.length) { el = c[c.length - 1]; how = 'full-text'; } }
    return { id: tk.id, text: tk.characters.replace(/\s+/g, ' ').trim().slice(0, 40), matched: !!el, how, m: el ? measure(el) : null };
  });
}, tokens, selectorMap);

await browser.close();

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

const matchedCount = rows.length - unmatched;
console.log(`\n${C.dim}──────────${C.reset}`);
console.log(`Appariés conformes : ${matchedCount - fails}/${matchedCount}  |  en écart : ${fails}  |  non appariés : ${unmatched}`);

if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({ url: URL, width: WIDTH, total: rows.length, matched: matchedCount, fails, unmatched, rows }, null, 2));
  console.log(`Rapport : ${OUT}`);
}

const failed = fails > 0 || (STRICT_UNMATCHED && unmatched > 0);
if (failed) {
  console.log(`${C.red}GATE STYLES : ÉCHEC${C.reset} (${fails} écart(s)${STRICT_UNMATCHED ? `, ${unmatched} non apparié(s)` : ''})`);
  process.exit(1);
}
console.log(`${C.green}GATE STYLES : OK${C.reset}`);
process.exit(0);
