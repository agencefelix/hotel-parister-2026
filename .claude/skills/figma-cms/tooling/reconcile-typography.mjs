/**
 * Pont tokens Figma → échelle typographique SCSS du projet.
 *
 * Confronte les tailles de police présentes sur une page (figma-tokens.<page>.json) à l'échelle
 * du projet (variables.scss : $font-size-h1..h6, base, card-title, news-teaser-control, et la map
 * $font-sizes-app → classes .fz-*). Pour chaque taille Figma : slot le plus proche dans la tolérance,
 * ou ORPHELINE (aucun emplacement) — auquel cas le rendu « snappe » silencieusement sur la base
 * (16px). But : repérer AVANT intégration les tailles à ajouter (variable ou classe .fz-*).
 *
 * Usage (depuis la RACINE du projet) :
 *   node .claude/skills/figma-cms/tooling/reconcile-typography.mjs <figma-tokens.<page>.json> [options]
 *
 * Options :
 *   --scss <chemin>   variables.scss (défaut assets/scss/front/default/variables.scss)
 *   --tol <px>        tolérance d'appariement en px (défaut 1.5)
 *   --strict          sort en code 1 s'il reste des tailles orphelines
 *   --out <r.json>    écrit le rapport
 *
 * Advisory par défaut (exit 0) : c'est un rapport d'aide à l'intégration, pas une gate de rendu
 * (la gate de rendu = verify-styles.mjs).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const TOKENS = args[0];
if (!TOKENS) {
  console.error('Usage: node reconcile-typography.mjs <figma-tokens.json> [--scss variables.scss] [--tol 1.5] [--strict] [--out r.json]');
  process.exit(2);
}
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const flag = (n) => args.includes(n);
const SCSS = opt('--scss', 'assets/scss/front/default/variables.scss');
const TOL = parseFloat(opt('--tol', '1.5'));
const STRICT = flag('--strict');
const OUT = opt('--out', null);

// --- 1. Échelle SCSS du projet ---
const scss = fs.readFileSync(SCSS, 'utf8');
const slots = new Map(); // size(px) -> Set(labels)
const addSlot = (size, label) => {
  const s = Math.round(parseFloat(size) * 10) / 10;
  if (!Number.isFinite(s) || s <= 0) return;
  if (!slots.has(s)) slots.set(s, new Set());
  slots.get(s).add(label);
};

// $font-size-h1: 60px;  $font-size-base-pixel: 16px;  $font-size-card-title: rem(35px); ...
const varLabels = {
  'h1': 'h1', 'h2': 'h2', 'h3': 'h3', 'h4': 'h4', 'h5': 'h5', 'h6': 'h6',
  'base-pixel': 'base / p', 'card-title': '.card-title', 'news-teaser-control': 'news-teaser',
};
for (const m of scss.matchAll(/\$font-size-(h[1-6]|base-pixel|card-title|news-teaser-control)\s*:\s*(?:rem\(\s*)?([\d.]+)px/g)) {
  addSlot(m[2], varLabels[m[1]] || m[1]);
}
// Map $font-sizes-app : 'xs': (... 'size': 16px ...) -> .fz-xs
const appBlock = scss.match(/\$font-sizes-app\s*:\s*\(([\s\S]*?)\n\)\s*;/);
if (appBlock) {
  for (const m of appBlock[1].matchAll(/'([a-z0-9-]+)'\s*:\s*\([^)]*'size'\s*:\s*([\d.]+)px/g)) {
    addSlot(m[2], '.fz-' + m[1]);
  }
}

const slotList = [...slots.entries()].map(([size, labels]) => ({ size, labels: [...labels] })).sort((a, b) => a.size - b.size);
if (slotList.length === 0) {
  console.error('Aucune taille typographique trouvée dans ' + SCSS + ' (vérifier le chemin).');
  process.exit(2);
}

// --- 2. Tailles Figma de la page ---
const raw = JSON.parse(fs.readFileSync(TOKENS, 'utf8'));
const items = raw.items || raw.nodes || (Array.isArray(raw) ? raw : []);
const figma = new Map(); // size -> {count, sample}
for (const n of items) {
  if (n.type !== 'TEXT' || typeof n.fontSize !== 'number') continue;
  const s = Math.round(n.fontSize * 10) / 10;
  const e = figma.get(s) || { count: 0, sample: '' };
  e.count++;
  if (!e.sample && typeof n.characters === 'string') e.sample = n.characters.replace(/\s+/g, ' ').trim().slice(0, 32);
  figma.set(s, e);
}
const figmaList = [...figma.entries()].map(([size, v]) => ({ size, ...v })).sort((a, b) => b.size - a.size);

// --- 3. Appariement ---
const nearest = (size) => {
  let best = null;
  for (const slot of slotList) {
    const d = Math.abs(slot.size - size);
    if (best === null || d < best.delta) best = { slot, delta: d };
  }
  return best;
};

const rows = figmaList.map((f) => {
  const n = nearest(f.size);
  const ok = n && n.delta <= TOL;
  return { size: f.size, count: f.count, sample: f.sample, slot: ok ? n.slot : null, delta: n ? n.delta : null, orphan: !ok };
});

// --- 4. Rapport ---
const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
console.log(`\nÉchelle SCSS (${SCSS}) : ${slotList.map((s) => s.size + 'px').join(', ')}`);
console.log(`Tailles Figma sur la page : ${figmaList.length}  |  tolérance ${TOL}px\n`);

for (const r of rows) {
  const head = `${String(r.size).padStart(5)}px  ×${String(r.count).padEnd(3)}  «${r.sample}»`;
  if (!r.orphan) {
    console.log(`${C.green}✓${C.reset} ${head}  → ${r.slot.labels.join(' / ')}${r.delta > 0 ? `  ${C.dim}(Δ${r.delta.toFixed(1)}px)${C.reset}` : ''}`);
  } else {
    const closest = nearest(r.size);
    console.log(`${C.red}✗ ORPHELINE${C.reset} ${head}  ${C.yellow}aucun slot (≤${TOL}px)${C.reset} ${C.dim}— plus proche : ${closest.slot.size}px (${closest.slot.labels.join('/')}, Δ${closest.delta.toFixed(1)}px)${C.reset}`);
  }
}

const orphans = rows.filter((r) => r.orphan);
console.log(`\n${C.dim}──────────${C.reset}`);
console.log(`Mappées : ${rows.length - orphans.length}/${rows.length}  |  orphelines : ${orphans.length}`);
if (orphans.length) {
  console.log(`${C.yellow}À AJOUTER dans variables.scss${C.reset} (sinon snap silencieux sur la base) :`);
  for (const o of orphans) {
    const code = String(Math.round(o.size)).replace('.', '-');
    console.log(`  • ${o.size}px (×${o.count}, «${o.sample}») → ajouter une entrée \`'${code}': ('rfs': true, 'size': ${o.size}px, …)\` dans \$font-sizes-app (classe .fz-${code}) ou une variable dédiée.`);
  }
}

if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({ scss: SCSS, tol: TOL, slots: slotList, rows }, null, 2));
  console.log(`Rapport : ${OUT}`);
}

if (STRICT && orphans.length) {
  console.log(`${C.red}RECONCILIATION TYPO : ${orphans.length} taille(s) orpheline(s)${C.reset}`);
  process.exit(1);
}
console.log(`${C.green}Reconciliation typo : OK${C.reset}`);
process.exit(0);
