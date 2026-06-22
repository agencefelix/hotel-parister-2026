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
 *   --named <m.json>  JSON de `figma-named-styles.mjs --json` : ANNOTE chaque taille de son STYLE
 *                     NOMMÉ (ex. 54px ← « Sous-titre H3 ») et requalifie les orphelins nommés en
 *                     « style nommé hors échelle » (intentionnel → classe dédiée), pas en bruit.
 *   --tol <px>        tolérance d'appariement en px (défaut 1.5)
 *   --strict          sort en code 1 s'il reste des orphelines ANONYMES (les styles nommés sont OK)
 *   --out <r.json>    écrit le rapport
 *
 * Advisory par défaut (exit 0) : c'est un rapport d'aide à l'intégration, pas une gate de rendu
 * (la gate de rendu = verify-styles.mjs).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const TOKENS = args[0];
if (!TOKENS) {
  console.error('Usage: node reconcile-typography.mjs <figma-tokens.json> [--scss variables.scss] [--named named.json] [--tol 1.5] [--strict] [--out r.json]');
  process.exit(2);
}
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const flag = (n) => args.includes(n);
const SCSS = opt('--scss', 'assets/scss/front/default/variables.scss');
const TOL = parseFloat(opt('--tol', '1.5'));
const STRICT = flag('--strict');
const OUT = opt('--out', null);
const NAMED = opt('--named', null); // JSON de figma-named-styles --json : annote chaque taille de son STYLE NOMMÉ

// Map taille → noms de styles texte (depuis figma-named-styles).
const sizeNames = new Map();
if (NAMED) {
  const nd = JSON.parse(fs.readFileSync(NAMED, 'utf8'));
  for (const t of nd.textStyles || []) {
    if (typeof t.size !== 'number') continue;
    const s = Math.round(t.size * 10) / 10;
    if (!sizeNames.has(s)) sizeNames.set(s, []);
    sizeNames.get(s).push(t.name);
  }
}
const namesFor = (size) => {
  let best = null;
  for (const [s, names] of sizeNames) { const d = Math.abs(s - size); if (d <= 0.6 && (best === null || d < best.d)) best = { names, d }; }
  return best ? best.names : [];
};

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
  return { size: f.size, count: f.count, sample: f.sample, slot: ok ? n.slot : null, delta: n ? n.delta : null, orphan: !ok, named: namesFor(f.size) };
});

// --- 4. Rapport ---
const C = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', cyan: '\x1b[36m', dim: '\x1b[2m', reset: '\x1b[0m' };
console.log(`\nÉchelle SCSS (${SCSS}) : ${slotList.map((s) => s.size + 'px').join(', ')}`);
console.log(`Tailles Figma sur la page : ${figmaList.length}  |  tolérance ${TOL}px${NAMED ? '  |  styles nommés : ' + sizeNames.size + ' tailles' : '  (sans --named : pas d\'annotation de style)'}\n`);

for (const r of rows) {
  const tag = r.named.length ? `  ${C.cyan}← « ${r.named.join(' / ')} »${C.reset}` : '';
  const head = `${String(r.size).padStart(5)}px  ×${String(r.count).padEnd(3)}  «${r.sample}»`;
  if (!r.orphan) {
    console.log(`${C.green}✓${C.reset} ${head}  → ${r.slot.labels.join(' / ')}${r.delta > 0 ? `  ${C.dim}(Δ${r.delta.toFixed(1)}px)${C.reset}` : ''}${tag}`);
  } else if (r.named.length) {
    // Orpheline MAIS = style nommé du design system → intentionnel : lui dédier une variable/classe.
    console.log(`${C.cyan}◆ STYLE NOMMÉ${C.reset} ${head}  ${C.cyan}« ${r.named.join(' / ')} »${C.reset} ${C.dim}— hors échelle SCSS → variable/classe dédiée (intentionnel, pas du bruit)${C.reset}`);
  } else {
    const closest = nearest(r.size);
    console.log(`${C.red}✗ ORPHELINE${C.reset} ${head}  ${C.yellow}aucun slot (≤${TOL}px)${C.reset} ${C.dim}— plus proche : ${closest.slot.size}px (${closest.slot.labels.join('/')}, Δ${closest.delta.toFixed(1)}px)${C.reset}`);
  }
}

const orphans = rows.filter((r) => r.orphan);
const namedOrphans = orphans.filter((r) => r.named.length);   // hors échelle MAIS style nommé (intentionnel)
const anonOrphans = orphans.filter((r) => !r.named.length);   // hors échelle ET sans nom (vrai à arbitrer)
const slugify = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
console.log(`\n${C.dim}──────────${C.reset}`);
console.log(`Mappées échelle : ${rows.length - orphans.length}/${rows.length}  |  styles nommés hors échelle : ${namedOrphans.length}  |  orphelines anonymes : ${anonOrphans.length}`);
if (namedOrphans.length) {
  console.log(`${C.cyan}STYLES NOMMÉS hors échelle → classe/variable DÉDIÉE (reprendre le nom Figma)${C.reset} :`);
  for (const o of namedOrphans) {
    console.log(`  • ${o.size}px « ${o.named.join(' / ')} » → ex. \`.fz-${slugify(o.named[0])}\` { @include rfs(${o.size}px); } (ou $font-size-${slugify(o.named[0])}).`);
  }
}
if (anonOrphans.length) {
  console.log(`${C.yellow}ORPHELINES anonymes (pas de style nommé) — à arbitrer/ajouter${C.reset} :`);
  for (const o of anonOrphans) {
    const code = String(Math.round(o.size)).replace('.', '-');
    console.log(`  • ${o.size}px (×${o.count}, «${o.sample}») → \`'${code}': ('rfs': true, 'size': ${o.size}px, …)\` dans \$font-sizes-app (.fz-${code}) ou variable dédiée.`);
  }
}

if (OUT) {
  fs.writeFileSync(OUT, JSON.stringify({ scss: SCSS, tol: TOL, slots: slotList, rows }, null, 2));
  console.log(`Rapport : ${OUT}`);
}

if (STRICT && anonOrphans.length) {
  console.log(`${C.red}RECONCILIATION TYPO : ${anonOrphans.length} orpheline(s) anonyme(s)${C.reset} (les styles nommés hors échelle sont OK : leur donner une classe dédiée)`);
  process.exit(1);
}
console.log(`${C.green}Reconciliation typo : OK${C.reset}`);
process.exit(0);
