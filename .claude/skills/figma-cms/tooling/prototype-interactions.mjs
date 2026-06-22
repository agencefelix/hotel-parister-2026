/**
 * Extracteur d'INTERACTIONS / ANIMATIONS du prototype Figma.
 *
 * Le prototype encode les animations dans `node.interactions` (REST) : `{trigger:{type}, actions:[{type,
 * navigation, destinationId, transition:{type, easing:{type}, duration}}]}` (+ champs legacy
 * `transitionNodeID/Duration/Easing`). En intégration ces animations sont souvent RATÉES faute d'outil
 * pour les remonter (8000+ entrées brutes à la main). Cet outil les extrait, DÉDUPLIQUE en « recettes »
 * et liste ce qu'il y a à reproduire (trigger → transition + durée + easing, source → cible).
 *
 * Usage (depuis la RACINE du projet) :
 *   node .claude/skills/figma-cms/tooling/prototype-interactions.mjs [--node 542:1592] [--file dump.json] [--out r.md] [--top 40]
 *   - sans --file : fetch l'API (FIGMA_FILE_KEY/FIGMA_TOKEN du .env) ; --node limite à une page.
 *   - --file : lit un dump JSON déjà récupéré (offline / test).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const FILE = opt('--file', null);
const NODE = opt('--node', null);
const OUT = opt('--out', null);
const TOP = parseInt(opt('--top', '40'), 10);

function envVal(key) {
  for (const f of ['.env.local', '.env']) {
    if (!fs.existsSync(f)) continue;
    const m = fs.readFileSync(f, 'utf8').match(new RegExp(`^${key}\\s*=\\s*"?([^"\\n\\r]+)`, 'm'));
    if (m) return m[1].trim();
  }
  return null;
}

async function loadDoc() {
  if (FILE) {
    const d = JSON.parse(fs.readFileSync(FILE, 'utf8'));
    if (d.document) return d.document;
    if (d.nodes) return { children: Object.values(d.nodes).map((n) => n.document) };
    return d;
  }
  const key = envVal('FIGMA_FILE_KEY'); const tok = envVal('FIGMA_TOKEN');
  if (!key || !tok) { console.error('FIGMA_FILE_KEY/FIGMA_TOKEN absents (.env) et pas de --file.'); process.exit(2); }
  const url = NODE ? `https://api.figma.com/v1/files/${key}/nodes?ids=${encodeURIComponent(NODE)}` : `https://api.figma.com/v1/files/${key}`;
  const res = await fetch(url, { headers: { 'X-Figma-Token': tok } });
  if (!res.ok) { console.error(`Figma API ${res.status}`); process.exit(2); }
  const d = await res.json();
  if (NODE) return { children: Object.values(d.nodes).map((n) => n.document) };
  return d.document;
}

const doc = await loadDoc();

// Récolte des interactions (forme moderne `interactions`, repli legacy `transitionNodeID`).
const rows = [];
const walk = (n) => {
  const name = n.name || n.id || '?';
  for (const it of n.interactions || []) {
    const trigger = it.trigger?.type || '?';
    for (const a of it.actions || []) {
      const t = a.transition || {};
      rows.push({
        trigger, action: a.type || '?', navigation: a.navigation || null, dest: a.destinationId || null,
        transition: t.type || (a.type === 'NODE' ? 'INSTANT' : null), easing: t.easing?.type || null,
        durMs: typeof t.duration === 'number' ? Math.round(t.duration * 1000) : null, src: name, srcId: n.id,
      });
    }
  }
  if ((!n.interactions || n.interactions.length === 0) && n.transitionNodeID) {
    rows.push({ trigger: 'LEGACY', action: 'NODE', navigation: null, dest: n.transitionNodeID,
      transition: 'TRANSITION', easing: n.transitionEasing || null,
      durMs: typeof n.transitionDuration === 'number' ? Math.round(n.transitionDuration) : null, src: name, srcId: n.id });
  }
  for (const c of n.children || []) walk(c);
};
walk(doc);

if (rows.length === 0) {
  console.log('Aucune interaction de prototype trouvée.');
  process.exit(0);
}

// Comptages.
const tally = (key) => { const m = new Map(); for (const r of rows) m.set(r[key] || '∅', (m.get(r[key] || '∅') || 0) + 1); return [...m.entries()].sort((a, b) => b[1] - a[1]); };
// Recettes dédupliquées : trigger + transition + easing + durée (arrondie ~50ms).
const bucket = (r) => `${r.trigger} | ${r.transition} | ${r.easing || '-'} | ${r.durMs != null ? Math.round(r.durMs / 50) * 50 + 'ms' : '-'}`;
const recipes = new Map();
for (const r of rows) {
  const k = bucket(r);
  const e = recipes.get(k) || { count: 0, ex: r };
  e.count++; recipes.set(k, e);
}

const C = { cyan: '\x1b[36m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };

log(`\nInteractions de prototype — ${rows.length} action(s) sur ${new Set(rows.map((r) => r.srcId)).size} nœud(s)`);
log(`Triggers : ${tally('trigger').map(([k, n]) => `${k}×${n}`).join('  ')}`);
log(`Transitions : ${tally('transition').map(([k, n]) => `${k}×${n}`).join('  ')}`);
log(`Easings : ${tally('easing').map(([k, n]) => `${k}×${n}`).join('  ')}\n`);

log(`${C.cyan}### Recettes d'animation (dédupliquées) — à reproduire en CSS/JS${C.reset}`);
for (const [k, e] of [...recipes.entries()].sort((a, b) => b[1].count - a[1].count)) {
  log(`  ${C.green}×${e.count}${C.reset}  ${k}  ${C.dim}(ex. « ${e.ex.src} »${e.ex.dest ? ' → ' + e.ex.dest : ''})${C.reset}`);
}

log(`\n${C.cyan}### Détail (top ${TOP})${C.reset}`);
for (const r of rows.slice(0, TOP)) {
  log(`  ${r.trigger} → ${r.action}${r.navigation ? '/' + r.navigation : ''}  ${r.transition}${r.durMs != null ? ' ' + r.durMs + 'ms' : ''}${r.easing ? ' ' + r.easing : ''}  ${C.dim}« ${r.src} »${r.dest ? ' → ' + r.dest : ''}${C.reset}`);
}
if (rows.length > TOP) log(`  … +${rows.length - TOP} autres`);

log(`\n${C.dim}──────────${C.reset}`);
log(`SMART_ANIMATE = morph entre 2 frames : comparer l'état source et l'état cible (destinationId) pour`);
log(`en déduire la transition CSS (translate/scale/opacity) + duration/easing. Trigger ON_HOVER → :hover,`);
log(`ON_CLICK → JS/état, AFTER_TIMEOUT → animation auto. Reproduire chaque RECETTE une fois (souvent une card).`);

if (OUT) {
  fs.writeFileSync(OUT, '# Interactions / animations du prototype Figma\n\n```\n' + lines.join('\n') + '\n```\n');
  console.log(`\nRapport : ${OUT}`);
}
