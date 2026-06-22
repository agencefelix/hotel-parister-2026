/**
 * GATE de LAYOUT — géométrie rendue (Chrome) vs géométrie maquette (Figma).
 *
 * Complément de verify-styles (qui ne mesure QUE le style) : ce gate mesure la COMPOSITION.
 * Pour chaque élément apparié (par texte, ou `--map`), compare la **bounding box rendue** à la
 * **bbox Figma** (tokens x/y/w/h relatifs à la page) :
 *   - FULL-BLEED : un élément ~pleine largeur en maquette DOIT l'être au rendu (capte « hero/bande boxé ») ;
 *   - LARGEUR relative : ratio largeur rendue/viewport ≈ largeur Figma/page (capte image trop petite/grande) ;
 *   - ORDRE vertical : l'ordre de haut en bas doit être préservé (capte bande déplacée/manquante).
 * Échoue (exit 1) si la composition diverge. Empêche d'annoncer « fait » sur une mise en page fausse.
 *
 * Usage (depuis la RACINE, puppeteer-core installé) :
 *   node .claude/skills/figma-cms/tooling/verify-layout.mjs <url> <figma-tokens.<page>.json> [options]
 *
 * Options : --map m.json | --tol-w <0..1, défaut 0.08> | --width <1440> | --strict-unmatched | --out r.json
 *
 * Prérequis : Chrome + puppeteer-core. NB : nécessite le site RENDU (comme verify-styles).
 */
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const args = process.argv.slice(2);
const URL = args[0];
const TOKENS = args[1];
if (!URL || !TOKENS) { console.error('Usage: node verify-layout.mjs <url> <figma-tokens.json> [--map m.json] [--tol-w 0.08] [--width 1440] [--strict-unmatched] [--out r.json]'); process.exit(2); }
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const flag = (n) => args.includes(n);
const TOL_W = parseFloat(opt('--tol-w', '0.08'));
const WIDTH = parseInt(opt('--width', '1440'), 10);
const STRICT = flag('--strict-unmatched');
const OUT = opt('--out', null);
const MAP = opt('--map', null) ? JSON.parse(fs.readFileSync(opt('--map', null), 'utf8')) : {};
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const raw = JSON.parse(fs.readFileSync(TOKENS, 'utf8'));
const items = (raw.items || raw.nodes || raw).filter((n) => typeof n.x === 'number' && typeof n.w === 'number');
const pageW = Math.max(...items.map((n) => n.x + n.w), 1);
const pageH = Math.max(...items.map((n) => n.y + (n.h || 0)), 1);
// Tokens TEXT exploitables (ancrage par texte) avec bbox.
let toks = items.filter((n) => n.type === 'TEXT' && typeof n.characters === 'string' && n.characters.replace(/\s+/g, '').length >= 2)
  .map((n) => ({ id: n.id, text: n.characters, relW: n.w / pageW, relY: n.y / pageH, figmaW: n.w, fullbleed: n.w / pageW >= 0.92 }));
if (toks.length === 0) { console.error('Aucun token TEXT exploitable.'); process.exit(2); }

const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--ignore-certificate-errors', '--no-sandbox', '--disable-gpu'], ignoreHTTPSErrors: true });
const page = await browser.newPage();
await page.setViewport({ width: WIDTH, height: 900 });
await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });
await sleep(1000);
await page.evaluate(async () => { await new Promise((r) => { let y = 0; const t = setInterval(() => { window.scrollBy(0, 800); y += 800; if (y >= document.body.scrollHeight) { clearInterval(t); r(); } }, 60); }); });
await page.evaluate(() => window.scrollTo(0, 0));
await sleep(300);

const measured = await page.evaluate((toks, MAP) => {
  const norm = (s) => (s || '').replace(/\s+/g, ' ').trim().toLowerCase();
  const ownText = (el) => { let t = ''; for (const n of el.childNodes) if (n.nodeType === 3) t += n.textContent; return t; };
  const all = Array.from(document.querySelectorAll('body *'));
  const byOwn = new Map(), byFull = new Map();
  for (const el of all) {
    const o = norm(ownText(el)); if (o.length >= 2) (byOwn.get(o) || byOwn.set(o, []).get(o)).push(el);
    const f = norm(el.textContent); if (f.length >= 2) (byFull.get(f) || byFull.set(f, []).get(f)).push(el);
  }
  const renderedW = document.documentElement.clientWidth;
  const renderedH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
  const rectOf = (el) => { const r = el.getBoundingClientRect(); return { top: r.top + window.scrollY, left: r.left, w: r.width, h: r.height }; };
  return {
    renderedW, renderedH,
    rows: toks.map((tk) => {
      let el = null, how = null;
      if (MAP[tk.id]) { el = document.querySelector(MAP[tk.id]); how = 'map'; }
      if (!el) { const c = byOwn.get(norm(tk.text)); if (c && c.length) { el = c[0]; how = 'own'; } }
      if (!el) { const c = byFull.get(norm(tk.text)); if (c && c.length) { el = c[c.length - 1]; how = 'full'; } }
      return { id: tk.id, matched: !!el, how, rect: el ? rectOf(el) : null };
    }),
  };
}, toks, MAP);
await browser.close();

const byId = new Map(toks.map((t) => [t.id, t]));
const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };
log(`\nGATE layout — ${URL}  (viewport ${WIDTH}px ; page Figma ${Math.round(pageW)}×${Math.round(pageH)})`);

let fails = 0, unmatched = 0;
const matchedRows = [];
for (const r of measured.rows) {
  const tk = byId.get(r.id);
  const label = tk.text.replace(/\s+/g, ' ').trim().slice(0, 26);
  if (!r.matched) { unmatched++; log(`${C.yellow}∅${C.reset} «${label}» ${C.dim}non apparié${C.reset}`); continue; }
  const renderedRelW = r.rect.w / measured.renderedW;
  matchedRows.push({ id: r.id, label, figmaRelY: tk.relY, renderedTop: r.rect.top });
  const checks = [];
  if (tk.fullbleed) checks.push({ k: 'full-bleed', ok: renderedRelW >= 0.92, exp: '≈pleine largeur', got: (renderedRelW * 100).toFixed(0) + '%' });
  else checks.push({ k: 'largeur', ok: Math.abs(renderedRelW - tk.relW) <= TOL_W, exp: (tk.relW * 100).toFixed(0) + '%', got: (renderedRelW * 100).toFixed(0) + '%' });
  const bad = checks.filter((c) => !c.ok);
  if (bad.length) { fails++; log(`${C.red}✗ «${label}»${C.reset}  ${bad.map((c) => `${c.k}: attendu ${c.exp}, rendu ${c.got}`).join(' ; ')}`); }
  else log(`${C.green}✓${C.reset} «${label}» ${C.dim}(${tk.fullbleed ? 'full-bleed' : 'largeur ' + (renderedRelW * 100).toFixed(0) + '%'})${C.reset}`);
}

// Ordre vertical : inversions entre l'ordre Figma (relY) et l'ordre rendu (top).
const figOrder = [...matchedRows].sort((a, b) => a.figmaRelY - b.figmaRelY).map((r) => r.id);
const rndOrder = [...matchedRows].sort((a, b) => a.renderedTop - b.renderedTop).map((r) => r.id);
let inversions = 0;
for (let i = 0; i < figOrder.length; i++) if (figOrder[i] !== rndOrder[i]) inversions++;

log(`\n${C.dim}──────────${C.reset}`);
log(`Appariés : ${matchedRows.length}  |  écarts largeur/full-bleed : ${fails}  |  inversions d'ordre vertical : ${inversions}  |  non appariés : ${unmatched}`);
if (OUT) { fs.writeFileSync(OUT, JSON.stringify({ url: URL, pageW, pageH, renderedW: measured.renderedW, renderedH: measured.renderedH, fails, inversions, rows: measured.rows }, null, 2)); console.log(`Rapport : ${OUT}`); }

const failed = fails > 0 || inversions > Math.max(1, Math.floor(matchedRows.length * 0.1)) || (STRICT && unmatched > 0);
if (failed) { log(`${C.red}GATE LAYOUT : ÉCHEC${C.reset} (${fails} largeur, ${inversions} inversions${STRICT ? ', ' + unmatched + ' non appariés' : ''})`); process.exit(1); }
log(`${C.green}GATE LAYOUT : OK${C.reset}`);
process.exit(0);
