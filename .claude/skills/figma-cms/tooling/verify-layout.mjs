/**
 * GATE de LAYOUT — géométrie rendue (Chrome) vs géométrie maquette (Figma).
 *
 * Complément de verify-styles (qui ne mesure QUE le style) : ce gate mesure la COMPOSITION.
 * Pour chaque élément apparié (par texte, ou `--map`), compare la **bounding box rendue** à la
 * **bbox Figma** (tokens x/y/w/h relatifs à la page) :
 *   - FULL-BLEED : un élément ~pleine largeur en maquette DOIT l'être au rendu (capte « hero/bande boxé ») ;
 *   - LARGEUR relative : ratio largeur rendue/viewport ≈ largeur Figma/page (capte image trop petite/grande) ;
 *   - ORDRE vertical : l'ordre de haut en bas doit être préservé (capte bande déplacée/manquante).
 *
 * SÉPARATION CONTENU vs COMPOSITION (3 buckets) — un mur de rouge n'est pas exploitable si une
 * partie vient de ce que le rendu n'a pas la même COPIE que la maquette. On classe donc chaque token :
 *   - FIABLE  : texte UNIQUE dans les tokens ET 1 seul élément DOM correspondant → décisif (largeur,
 *               full-bleed, et ancre d'ordre) ;
 *   - AMBIGU  : texte en double (plusieurs tokens ou plusieurs candidats DOM) → mesuré mais NON décisif
 *               (un « Découvrir » répété s'apparie au hasard) — informatif, n'échoue pas le gate ;
 *   - CONTENU : texte maquette ABSENT du rendu (non apparié) → signal de CONTENU, pas de layout ;
 *               n'échoue le gate qu'avec --strict-unmatched.
 * L'ordre vertical n'utilise QUE les ancres FIABLES, et compte les ancres déplacées via LIS (longest
 * increasing subsequence) — robuste aux doublons, contrairement à une comparaison position-à-position.
 *
 * Échoue (exit 1) si la COMPOSITION diverge (largeur/full-bleed ou ordre sur les ancres fiables).
 * Empêche d'annoncer « fait » sur une mise en page fausse — sans confondre « contenu différent ».
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
const normJs = (s) => (s || '').replace(/\s+/g, ' ').trim().toLowerCase();

const raw = JSON.parse(fs.readFileSync(TOKENS, 'utf8'));
const items = (raw.items || raw.nodes || raw).filter((n) => typeof n.x === 'number' && typeof n.w === 'number');
const pageW = Math.max(...items.map((n) => n.x + n.w), 1);
const pageH = Math.max(...items.map((n) => n.y + (n.h || 0)), 1);
// Tokens TEXT exploitables (ancrage par texte) avec bbox.
let toks = items.filter((n) => n.type === 'TEXT' && typeof n.characters === 'string' && n.characters.replace(/\s+/g, '').length >= 2)
  .map((n) => ({ id: n.id, text: n.characters, relW: n.w / pageW, relY: n.y / pageH, figmaW: n.w, fullbleed: n.w / pageW >= 0.92 }));
if (toks.length === 0) { console.error('Aucun token TEXT exploitable.'); process.exit(2); }

// Fréquence du texte (normalisé) parmi les tokens : un texte porté par >1 token est ambigu.
const tokFreq = new Map();
for (const t of toks) tokFreq.set(normJs(t.text), (tokFreq.get(normJs(t.text)) || 0) + 1);

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
      let el = null, how = null, cands = 0;
      if (MAP[tk.id]) { el = document.querySelector(MAP[tk.id]); how = 'map'; cands = el ? 1 : 0; }
      if (!el) { const c = byOwn.get(norm(tk.text)); if (c && c.length) { el = c[0]; how = 'own'; cands = c.length; } }
      if (!el) { const c = byFull.get(norm(tk.text)); if (c && c.length) { el = c[c.length - 1]; how = 'full'; cands = c.length; } }
      return { id: tk.id, matched: !!el, how, cands, rect: el ? rectOf(el) : null };
    }),
  };
}, toks, MAP);
await browser.close();

const byId = new Map(toks.map((t) => [t.id, t]));
const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', cyan: '\x1b[36m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };
log(`\nGATE layout — ${URL}  (viewport ${WIDTH}px ; page Figma ${Math.round(pageW)}×${Math.round(pageH)})`);

// Classement en 3 buckets : FIABLE (décisif), AMBIGU (doublon, informatif), CONTENU (non apparié).
const reliable = [], ambiguous = [], content = [];
for (const r of measured.rows) {
  const tk = byId.get(r.id);
  const label = tk.text.replace(/\s+/g, ' ').trim().slice(0, 26);
  if (!r.matched) { content.push({ id: r.id, label }); continue; }
  const renderedRelW = r.rect.w / measured.renderedW;
  const check = tk.fullbleed
    ? { k: 'full-bleed', ok: renderedRelW >= 0.92, exp: '≈pleine largeur', got: (renderedRelW * 100).toFixed(0) + '%' }
    : { k: 'largeur', ok: Math.abs(renderedRelW - tk.relW) <= TOL_W, exp: (tk.relW * 100).toFixed(0) + '%', got: (renderedRelW * 100).toFixed(0) + '%' };
  const row = { id: r.id, label, figmaRelY: tk.relY, renderedTop: r.rect.top, renderedRelW, check, fullbleed: tk.fullbleed };
  // Ambigu = texte porté par plusieurs tokens OU plusieurs candidats DOM (sauf appariement --map explicite).
  if (r.how !== 'map' && ((tokFreq.get(normJs(tk.text)) || 1) > 1 || r.cands > 1)) ambiguous.push(row);
  else reliable.push(row);
}

// ── COMPOSITION (ancres fiables) : largeur/full-bleed ──
log(`\n${C.cyan}── COMPOSITION (éléments fiables — texte unique, 1 seul match) ──${C.reset}`);
let widthFails = 0;
for (const r of reliable) {
  if (r.check.ok) { log(`${C.green}✓${C.reset} «${r.label}» ${C.dim}(${r.fullbleed ? 'full-bleed' : 'largeur ' + (r.renderedRelW * 100).toFixed(0) + '%'})${C.reset}`); continue; }
  widthFails++;
  log(`${C.red}✗ «${r.label}»${C.reset}  ${r.check.k} : attendu ${r.check.exp}, rendu ${r.check.got}`);
}

// Ordre vertical : ancres FIABLES uniquement ; ancres déplacées = N − LIS (robuste aux doublons).
// L'ordre rendu doit suivre l'ordre Figma ; on mesure la plus longue sous-suite déjà ordonnée.
const anchors = [...reliable].sort((a, b) => a.figmaRelY - b.figmaRelY);
const seqByRendered = [...anchors].sort((a, b) => a.renderedTop - b.renderedTop);
const figRank = new Map(anchors.map((r, i) => [r.id, i]));
const renderedAsFigRanks = seqByRendered.map((r) => figRank.get(r.id));
const lisLen = longestIncreasingSubsequence(renderedAsFigRanks);
const displaced = anchors.length - lisLen; // nb minimal d'ancres à déplacer pour ré-ordonner
const orderThreshold = Math.max(1, Math.floor(anchors.length * 0.1));
log(`${C.dim}ordre vertical : ${displaced} ancre(s) déplacée(s) / ${anchors.length} ancres fiables (seuil ${orderThreshold})${C.reset}`);

// ── CONTENU (texte maquette absent du rendu) ──
log(`\n${C.cyan}── CONTENU (texte maquette absent du rendu) ──${C.reset}`);
log(`${C.yellow}${content.length}${C.reset} token(s) non apparié(s)${STRICT ? '' : ` ${C.dim}(n'échoue pas le gate sauf --strict-unmatched)${C.reset}`}`);
if (content.length) log(`${C.dim}   ex. ${content.slice(0, 6).map((c) => '«' + c.label + '»').join(', ')}${content.length > 6 ? '…' : ''}${C.reset}`);

// ── AMBIGU (doublon, non décisif) ──
if (ambiguous.length) {
  const ambBad = ambiguous.filter((r) => !r.check.ok).length;
  log(`\n${C.cyan}── AMBIGU (texte en double — informatif, non décisif) ──${C.reset}`);
  log(`${C.dim}${ambiguous.length} élément(s) ; ${ambBad} hors tolérance (à vérifier manuellement / via --map)${C.reset}`);
}

log(`\n${C.dim}──────────${C.reset}`);
log(`COMPOSITION : ${widthFails} écart(s) largeur/full-bleed, ${displaced} ancre(s) d'ordre déplacée(s)  |  CONTENU : ${content.length} non apparié(s)  |  AMBIGU : ${ambiguous.length}`);
if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({
    url: URL, width: WIDTH, pageW, pageH, renderedW: measured.renderedW, renderedH: measured.renderedH,
    composition: { widthFails, orderDisplaced: displaced, anchors: anchors.length, orderThreshold },
    content: { unmatched: content.length, items: content },
    ambiguous: { count: ambiguous.length },
    rows: measured.rows,
  }, null, 2));
  console.log(`Rapport : ${OUT}`);
}

const failed = widthFails > 0 || displaced > orderThreshold || (STRICT && content.length > 0);
if (failed) { log(`${C.red}GATE LAYOUT : ÉCHEC${C.reset} (composition : ${widthFails} largeur, ${displaced} ordre${STRICT ? ` ; contenu : ${content.length} non appariés` : ''})`); process.exit(1); }
log(`${C.green}GATE LAYOUT : OK${C.reset}${content.length ? ` ${C.dim}(${content.length} non appariés ignorés — contenu)${C.reset}` : ''}`);
process.exit(0);

/** Longueur de la plus longue sous-suite croissante (O(n log n)) — pour compter les ancres bien ordonnées. */
function longestIncreasingSubsequence(arr) {
  const tails = [];
  for (const x of arr) {
    if (x === undefined) continue;
    let lo = 0, hi = tails.length;
    while (lo < hi) { const mid = (lo + hi) >> 1; if (tails[mid] < x) lo = mid + 1; else hi = mid; }
    tails[lo] = x;
  }
  return tails.length;
}
