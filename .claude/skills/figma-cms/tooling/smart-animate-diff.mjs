/**
 * ÉBAUCHE — diff source ↔ cible d'une transition SMART_ANIMATE du prototype Figma.
 *
 * SMART_ANIMATE morphe un calque vers le calque de MÊME NOM de la frame cible. Cet outil compare,
 * pour chaque interaction SMART_ANIMATE (source → destinationId), les calques homonymes des deux états
 * et calcule les DELTAS (position relative au cadre, taille, opacité) → pistes CSS (`translate`/`scale`/
 * `opacity`) + durée/easing. Sortie indicative : à affiner à l'œil (l'ébauche ne gère pas rotation,
 * couleurs, courbes de spring exactes).
 *
 * Usage (depuis la RACINE) :
 *   node .claude/skills/figma-cms/tooling/smart-animate-diff.mjs --file dump.json [--pair <srcId> <dstId>] [--out r.md]
 *   - --file : dump JSON déjà récupéré (sinon fetch API via FIGMA_FILE_KEY/FIGMA_TOKEN du .env).
 *   - sans --pair : diffe automatiquement chaque recette SMART_ANIMATE (1 paire représentative par cible).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const FILE = opt('--file', null);
const OUT = opt('--out', null);
const pairIdx = args.indexOf('--pair');
const PAIR = pairIdx !== -1 ? [args[pairIdx + 1], args[pairIdx + 2]] : null;

function envVal(key) {
  for (const f of ['.env.local', '.env']) {
    if (!fs.existsSync(f)) continue;
    const m = fs.readFileSync(f, 'utf8').match(new RegExp(`^${key}\\s*=\\s*"?([^"\\n\\r]+)`, 'm'));
    if (m) return m[1].trim();
  }
  return null;
}
async function loadDoc() {
  if (FILE) { const d = JSON.parse(fs.readFileSync(FILE, 'utf8')); return d.document || d; }
  const key = envVal('FIGMA_FILE_KEY'); const tok = envVal('FIGMA_TOKEN');
  if (!key || !tok) { console.error('FIGMA_FILE_KEY/FIGMA_TOKEN absents et pas de --file.'); process.exit(2); }
  const res = await fetch(`https://api.figma.com/v1/files/${key}`, { headers: { 'X-Figma-Token': tok } });
  if (!res.ok) { console.error(`Figma API ${res.status}`); process.exit(2); }
  return (await res.json()).document;
}

const doc = await loadDoc();

// Index id → node + collecte des paires SMART_ANIMATE.
const byId = new Map();
const pairs = [];
const index = (n) => {
  byId.set(n.id, n);
  for (const it of n.interactions || []) {
    for (const a of it.actions || []) {
      if (a.transition?.type === 'SMART_ANIMATE' && a.destinationId) {
        pairs.push({ srcId: n.id, dstId: a.destinationId, durMs: Math.round((a.transition.duration || 0) * 1000), easing: a.transition.easing?.type || null, trigger: it.trigger?.type || '?' });
      }
    }
  }
  for (const c of n.children || []) index(c);
};
index(doc);

// Géométrie relative au cadre, par nom de calque (descendants).
const geomByName = (frame) => {
  const fb = frame.absoluteBoundingBox || { x: 0, y: 0, width: 1, height: 1 };
  const map = new Map();
  const walk = (n) => {
    const b = n.absoluteBoundingBox;
    if (b && n.name && !map.has(n.name)) {
      map.set(n.name, { x: b.x - fb.x, y: b.y - fb.y, w: b.width, h: b.height, op: n.opacity ?? 1 });
    }
    for (const c of n.children || []) walk(c);
  };
  for (const c of frame.children || []) walk(c);
  return map;
};

const easingCss = (e) => ({ GENTLE: 'ease-in-out /* spring Gentle ≈ */', EASE_OUT: 'ease-out', EASE_IN: 'ease-in', EASE_IN_AND_OUT: 'ease-in-out', LINEAR: 'linear' }[e] || 'ease');

const C = { cyan: '\x1b[36m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };

let toDiff;
if (PAIR) toDiff = [{ srcId: PAIR[0], dstId: PAIR[1], durMs: null, easing: null, trigger: 'manuel' }];
else { const seen = new Set(); toDiff = pairs.filter((p) => { const k = p.srcId + '>' + p.dstId; if (seen.has(k)) return false; seen.add(k); return true; }); }

if (toDiff.length === 0) { console.log('Aucune transition SMART_ANIMATE trouvée.'); process.exit(0); }
log(`\nSMART_ANIMATE — ${pairs.length} occurrence(s), ${toDiff.length} paire(s) unique(s) à diffuser`);

for (const p of toDiff.slice(0, 12)) {
  const src = byId.get(p.srcId), dst = byId.get(p.dstId);
  log(`\n${C.cyan}▶ ${src ? '«' + (src.name || src.id) + '»' : p.srcId} → ${dst ? '«' + (dst.name || dst.id) + '»' : p.dstId}${C.reset}  ${C.dim}${p.trigger}${p.durMs != null ? ', ' + p.durMs + 'ms' : ''}${p.easing ? ', ' + p.easing : ''}${C.reset}`);
  if (!src || !dst) { log(`  ${C.yellow}(nœud source ou cible introuvable dans le dump)${C.reset}`); continue; }
  const g1 = geomByName(src), g2 = geomByName(dst);
  let changes = 0;
  for (const [name, a] of g1) {
    const b = g2.get(name);
    if (!b) continue;
    const dx = Math.round(b.x - a.x), dy = Math.round(b.y - a.y);
    const sw = a.w ? +(b.w / a.w).toFixed(3) : 1, sh = a.h ? +(b.h / a.h).toFixed(3) : 1;
    const dop = +(b.op - a.op).toFixed(2);
    if (Math.abs(dx) < 1 && Math.abs(dy) < 1 && Math.abs(sw - 1) < 0.01 && Math.abs(sh - 1) < 0.01 && Math.abs(dop) < 0.01) continue;
    changes++;
    const css = [];
    if (dx || dy) css.push(`translate(${dx}px, ${dy}px)`);
    if (Math.abs(sw - 1) >= 0.01 || Math.abs(sh - 1) >= 0.01) css.push(`scale(${sw}, ${sh})`);
    const transform = css.length ? `transform: ${css.join(' ')};` : '';
    const opacity = Math.abs(dop) >= 0.01 ? `opacity: ${a.op} → ${b.op};` : '';
    log(`  ${C.green}«${name}»${C.reset}  ${transform} ${opacity}`.trimEnd());
  }
  if (changes === 0) log(`  ${C.dim}(aucun delta de position/taille/opacité — morph de couleur/contenu ? à vérifier à l'œil)${C.reset}`);
  log(`  ${C.dim}→ transition CSS : ${p.durMs != null ? (p.durMs + 'ms') : '<durée>'} ${easingCss(p.easing)} ; déclencheur ${p.trigger === 'ON_HOVER' ? ':hover' : p.trigger}${C.reset}`);
}

if (OUT) { fs.writeFileSync(OUT, '# Diff SMART_ANIMATE (ébauche)\n\n```\n' + lines.join('\n') + '\n```\n'); console.log(`\nRapport : ${OUT}`); }
