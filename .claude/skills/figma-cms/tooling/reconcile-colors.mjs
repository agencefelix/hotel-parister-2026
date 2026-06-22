/**
 * Pont couleurs Figma → palette NOMMÉE + variables SCSS du projet.
 *
 * Miroir de reconcile-typography pour les COULEURS. Confronte les hex utilisés sur une page
 * (figma-tokens.<page>.json, champ `fills[].hex`) à :
 *   1. la PALETTE NOMMÉE du design system (figma-named-styles.mjs --json → fills [{name,slug,hex}]) ;
 *   2. les VARIABLES SCSS couleur déjà définies dans variables.scss.
 * Pour chaque couleur : style nommé correspondant (→ $slug), variable SCSS existante, ou ANONYME
 * (hors palette → à arbitrer : one-off, couleur d'image, ou à nommer). Aucun scope supplémentaire.
 *
 * Usage (depuis la RACINE) :
 *   node .claude/skills/figma-cms/tooling/reconcile-colors.mjs <figma-tokens.<page>.json> [options]
 *
 * Options :
 *   --named <m.json>  palette nommée (figma-named-styles --json) — recommandé
 *   --scss <chemin>   variables.scss (défaut assets/scss/front/default/variables.scss)
 *   --tol <n>         tolérance d'appariement couleur, écart max par canal 0-255 (défaut 8)
 *   --strict          sort en code 1 s'il reste des couleurs ANONYMES
 *   --out <r.json>    écrit le rapport
 *
 * Advisory par défaut (exit 0).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const TOKENS = args[0];
if (!TOKENS) { console.error('Usage: node reconcile-colors.mjs <figma-tokens.json> [--named n.json] [--scss variables.scss] [--tol 8] [--strict] [--out r.json]'); process.exit(2); }
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const flag = (n) => args.includes(n);
const NAMED = opt('--named', null);
const SCSS = opt('--scss', 'assets/scss/front/default/variables.scss');
const TOL = parseInt(opt('--tol', '8'), 10);
const STRICT = flag('--strict');
const OUT = opt('--out', null);

const norm = (h) => (h || '').toLowerCase().slice(0, 7);
const rgb = (h) => norm(h).replace('#', '').match(/../g)?.map((x) => parseInt(x, 16)) || null;
const dist = (h1, h2) => { const a = rgb(h1), b = rgb(h2); if (!a || !b) return 999; return Math.max(Math.abs(a[0] - b[0]), Math.abs(a[1] - b[1]), Math.abs(a[2] - b[2])); };
const nearest = (h, list) => { let best = null; for (const c of list) { const d = dist(h, c.hex); if (best === null || d < best.d) best = { ...c, d }; } return best; };

// --- 1. Palette nommée (design system) ---
let palette = [];
if (NAMED) {
  const nd = JSON.parse(fs.readFileSync(NAMED, 'utf8'));
  palette = (nd.fills || []).filter((f) => f.hex).map((f) => ({ name: f.name, slug: f.slug, hex: norm(f.hex) }));
}

// --- 2. Variables SCSS couleur (assignations directes #hex) ---
let scssVars = [];
if (fs.existsSync(SCSS)) {
  const s = fs.readFileSync(SCSS, 'utf8');
  for (const m of s.matchAll(/\$([\w-]+)\s*:\s*(#[0-9a-fA-F]{6})\b/g)) scssVars.push({ slug: m[1], hex: norm(m[2]) });
}

// --- 3. Couleurs de la page (fills hex) ---
const raw = JSON.parse(fs.readFileSync(TOKENS, 'utf8'));
const items = raw.items || raw.nodes || (Array.isArray(raw) ? raw : []);
const colors = new Map(); // hex -> {count, sample}
for (const n of items) {
  for (const f of n.fills || []) {
    if (!f.hex) continue;
    const h = norm(f.hex);
    const e = colors.get(h) || { count: 0, sample: '' };
    e.count++;
    if (!e.sample) e.sample = (n.characters || n.name || '').replace(/\s+/g, ' ').trim().slice(0, 28);
    colors.set(h, e);
  }
}
const list = [...colors.entries()].map(([hex, v]) => ({ hex, ...v })).sort((a, b) => b.count - a.count);
if (list.length === 0) { console.log('Aucune couleur (fills hex) dans ' + TOKENS + ' — l\'export inclut-il les fills ?'); process.exit(0); }

// --- 4. Appariement ---
const rows = list.map((c) => {
  const named = palette.length ? nearest(c.hex, palette) : null;
  const scssv = scssVars.length ? nearest(c.hex, scssVars) : null;
  return { ...c, named: named && named.d <= TOL ? named : null, scss: scssv && scssv.d <= TOL ? scssv : null };
});

const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', cyan: '\x1b[36m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };

log(`\nCouleurs de la page : ${list.length} hex distincts  |  palette nommée : ${palette.length}  |  vars SCSS : ${scssVars.length}  |  tol ${TOL}/canal\n`);
for (const r of rows) {
  const head = `${r.hex}  ×${String(r.count).padEnd(3)}  «${r.sample}»`;
  if (r.named) {
    const sc = r.scss ? `  ${C.dim}[SCSS $${r.scss.slug}]${C.reset}` : `  ${C.yellow}[à ajouter : $${r.named.slug}]${C.reset}`;
    log(`${C.green}✓${C.reset} ${head}  → ${C.cyan}« ${r.named.name} » $${r.named.slug}${C.reset}${r.named.d ? ` ${C.dim}(Δ${r.named.d})${C.reset}` : ''}${sc}`);
  } else if (r.scss) {
    log(`${C.green}✓${C.reset} ${head}  → ${C.dim}$${r.scss.slug} (SCSS, hors palette nommée)${r.scss.d ? ' Δ' + r.scss.d : ''}${C.reset}`);
  } else {
    log(`${C.red}✗ ANONYME${C.reset} ${head}  ${C.yellow}hors palette nommée & SCSS${C.reset} ${C.dim}— one-off / couleur d'image / à nommer${C.reset}`);
  }
}

const anon = rows.filter((r) => !r.named && !r.scss);
log(`\n${C.dim}──────────${C.reset}`);
log(`Palette nommée : ${rows.filter((r) => r.named).length}  |  SCSS seul : ${rows.filter((r) => !r.named && r.scss).length}  |  anonymes : ${anon.length}`);
const toAdd = rows.filter((r) => r.named && !r.scss);
if (toAdd.length) {
  log(`${C.yellow}À AJOUTER dans variables.scss (palette nommée non encore déclarée)${C.reset} :`);
  for (const r of toAdd) log(`  $${r.named.slug}: ${r.named.hex};  // « ${r.named.name} »  ×${r.count}`);
}
if (anon.length) {
  log(`${C.yellow}ANONYMES (hors palette) — à arbitrer${C.reset} :`);
  for (const r of anon) log(`  ${r.hex} ×${r.count} «${r.sample}»`);
}

if (OUT) { fs.writeFileSync(OUT, JSON.stringify({ scss: SCSS, tol: TOL, paletteSize: palette.length, rows }, null, 2)); console.log(`Rapport : ${OUT}`); }
if (STRICT && anon.length) { console.log(`${C.red}RECONCILIATION COULEURS : ${anon.length} anonyme(s)${C.reset}`); process.exit(1); }
console.log(`${C.green}Reconciliation couleurs : OK${C.reset}`);
process.exit(0);
