/**
 * Pont espacements Figma → échelle de marges SCSS du projet.
 *
 * Les marges/paddings du CMS sont une ÉCHELLE SÉMANTIQUE (niveaux 0/xs/sm/md/lg/xl/xxl), responsive
 * et dépendante de l'axe (x ≠ y), définie dans variables.scss (`$margins`) + _mixin-margin*.scss.
 * Cet outil confronte les paddings/gaps d'auto-layout d'une page (figma-tokens.<page>.json) à cette
 * échelle au breakpoint de référence (desktop par défaut) et, pour chaque conteneur, propose le
 * TOKEN de classe à poser (`pt-md`, `pe-lg`, `mb-md`…) ou signale une valeur ORPHELINE (hors-échelle).
 *
 * Usage (depuis la RACINE du projet) :
 *   node .claude/skills/figma-cms/tooling/reconcile-margins.mjs <figma-tokens.<page>.json> [options]
 *
 * Options :
 *   --scss <chemin>   variables.scss (défaut assets/scss/front/default/variables.scss)
 *   --bp <clé>        breakpoint de référence dans $margins (défaut "xxl" = desktop)
 *   --tol <px>        tolérance d'appariement à un niveau (défaut 8)
 *   --strict          sort en code 1 s'il reste des espacements orphelins
 *   --out <r.json>    écrit le rapport
 *
 * Advisory par défaut (exit 0) : aide au choix du niveau, pas une gate de rendu (≠ verify-styles).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const TOKENS = args[0];
if (!TOKENS) {
  console.error('Usage: node reconcile-margins.mjs <figma-tokens.json> [--scss variables.scss] [--bp xxl] [--tol 8] [--strict] [--out r.json]');
  process.exit(2);
}
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const flag = (n) => args.includes(n);
const SCSS = opt('--scss', 'assets/scss/front/default/variables.scss');
const BP = opt('--bp', 'xxl');
const TOL = parseFloat(opt('--tol', '8'));
const STRICT = flag('--strict');
const OUT = opt('--out', null);

// --- 1. Échelle de marges du projet (bloc du breakpoint de référence dans $margins) ---
const scss = fs.readFileSync(SCSS, 'utf8');
const px = (v, unit) => unit === 'rem' ? parseFloat(v) * 16 : parseFloat(v);
const parseInner = (s) => {
  const map = { 0: 0 }; // niveau "0" = aucun espacement
  for (const m of (s || '').matchAll(/'([a-z0-9]+)'\s*:\s*([\d.]+)(px|rem)/g)) {
    map[m[1]] = px(m[2], m[3]);
  }
  return map;
};
// Bloc "'<bp>': ( 'x': (...), 'y': (...) )" — au breakpoint de réf, les maps internes n'ont pas
// de parenthèses imbriquées (les ratios ne concernent que le niveau 'sm').
const block = scss.match(new RegExp(`'${BP}'\\s*:\\s*\\(\\s*'x'\\s*:\\s*\\(([^)]*)\\)\\s*,\\s*'y'\\s*:\\s*\\(([^)]*)\\)`));
if (!block) {
  console.error(`Bloc $margins['${BP}'] introuvable dans ${SCSS}.`);
  process.exit(2);
}
const scale = { x: parseInner(block[1]), y: parseInner(block[2]) };
const levelsStr = (axis) => Object.entries(scale[axis]).sort((a, b) => a[1] - b[1]).map(([k, v]) => `${k}:${v}`).join(' ');

// --- 2. Conteneurs Figma (auto-layout à padding/gap non nul) + texte par contenance géométrique ---
const raw = JSON.parse(fs.readFileSync(TOKENS, 'utf8'));
const items = raw.items || raw.nodes || (Array.isArray(raw) ? raw : []);
const texts = items.filter((n) => n.type === 'TEXT' && typeof n.characters === 'string' && n.characters.trim() && typeof n.x === 'number');
const containers = [];
for (const n of items) {
  const L = n.layout;
  if (!L || typeof n.x !== 'number') continue;
  const sides = { padTop: ['y', 'pt'], padBottom: ['y', 'pb'], padLeft: ['x', 'ps'], padRight: ['x', 'pe'] };
  const spacings = [];
  for (const [key, [axis, tok]] of Object.entries(sides)) {
    if ((L[key] || 0) > 0) spacings.push({ what: tok, axis, px: L[key], kind: 'padding' });
  }
  if ((L.gap || 0) > 0) {
    const axis = L.mode === 'HORIZONTAL' ? 'x' : 'y';
    spacings.push({ what: axis === 'x' ? 'me' : 'mb', axis, px: L.gap, kind: 'gap (→ marge enfant)' });
  }
  if (spacings.length === 0) continue;
  const inside = texts.filter((t) => {
    const cx = t.x + (t.w || 0) / 2, cy = t.y + (t.h || 0) / 2;
    return cx >= n.x && cx <= n.x + n.w && cy >= n.y && cy <= n.y + n.h;
  }).sort((a, b) => a.y - b.y);
  const label = (inside.map((t) => t.characters).join(' ').replace(/\s+/g, ' ').trim() || n.name || n.id).slice(0, 40);
  containers.push({ id: n.id, label, spacings });
}

// --- 3. Appariement au niveau le plus proche ---
const nearest = (axis, value) => {
  let best = null;
  for (const [lvl, lpx] of Object.entries(scale[axis])) {
    const d = Math.abs(lpx - value);
    if (best === null || d < best.d) best = { lvl, lpx, d };
  }
  return best;
};

const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
console.log(`\nÉchelle marges (${SCSS}, bp "${BP}")`);
console.log(`  y (top/bottom) : ${levelsStr('y')}`);
console.log(`  x (left/right) : ${levelsStr('x')}`);
console.log(`Conteneurs à espacement : ${containers.length}  |  tolérance ${TOL}px\n`);

let orphans = 0;
const rows = [];
for (const c of containers) {
  const lines = c.spacings.map((s) => {
    const n = nearest(s.axis, s.px);
    const orphan = n.d > TOL;
    if (orphan) orphans++;
    return { ...s, level: n.lvl, levelPx: n.lpx, delta: n.d, orphan, token: `${s.what}-${n.lvl}` };
  });
  rows.push({ id: c.id, label: c.label, lines });
  console.log(`▶ «${c.label}»`);
  for (const l of lines) {
    const tag = `${l.kind} ${l.px}px`;
    if (l.orphan) {
      console.log(`   ${C.red}✗ ${tag}${C.reset} → ${C.yellow}${l.token}${C.reset} ${C.dim}(niveau ${l.level}=${l.levelPx}px, Δ${l.delta.toFixed(0)}px — HORS ÉCHELLE)${C.reset}`);
    } else {
      console.log(`   ${C.green}✓ ${tag}${C.reset} → ${C.green}${l.token}${C.reset} ${C.dim}(${l.level}=${l.levelPx}px${l.delta ? `, Δ${l.delta.toFixed(0)}px` : ''})${C.reset}`);
    }
  }
}

const total = rows.reduce((a, r) => a + r.lines.length, 0);
console.log(`\n${C.dim}──────────${C.reset}`);
console.log(`Espacements : ${total - orphans}/${total} alignés sur l'échelle  |  orphelins : ${orphans}`);
if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({ scss: SCSS, bp: BP, tol: TOL, scale, rows }, null, 2));
  console.log(`Rapport : ${OUT}`);
}
if (STRICT && orphans > 0) {
  console.log(`${C.red}RECONCILIATION MARGES : ${orphans} orphelin(s)${C.reset}`);
  process.exit(1);
}
console.log(`${C.green}Reconciliation marges : OK${C.reset}`);
process.exit(0);
