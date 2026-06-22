/**
 * ÉBAUCHE — extracteur des STYLES NOMMÉS du fichier Figma (design system).
 *
 * Le contenu du fichier (scope `file_content:read`, déjà actif) porte un dictionnaire `styles`
 * (id → {name, styleType}) et chaque nœud référence ses styles via `node.styles` ({fill, text}).
 * Cet outil RÉSOUT les valeurs (FILL → hex ; TEXT → taille/poids/police/tracking/interligne/casse)
 * via les nœuds qui les utilisent, compte l'USAGE (global ou par page) et propose des VARIABLES SCSS.
 * → cibles NOMMÉES pour reconcile-typography / reconcile-margins / verify-styles (fini la déduction
 * par fréquence : un « 54px » devient « Sous-titre H3 »). Aucun scope supplémentaire requis.
 *
 * Usage (depuis la RACINE) :
 *   node .claude/skills/figma-cms/tooling/figma-named-styles.mjs [--node <id>] [--file dump.json] [--out r.md]
 *   - --node : limite le COMPTAGE d'usage à une page (les valeurs restent résolues sur tout le fichier).
 *   - --file : dump JSON déjà récupéré (sinon fetch API via FIGMA_FILE_KEY/FIGMA_TOKEN du .env).
 */
import fs from 'node:fs';

const args = process.argv.slice(2);
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const FILE = opt('--file', null);
const NODE = opt('--node', null);
const OUT = opt('--out', null);
const JSON_OUT = opt('--json', null); // sortie machine (consommée par reconcile-typography/margins)

function envVal(key) {
  for (const f of ['.env.local', '.env']) {
    if (!fs.existsSync(f)) continue;
    const m = fs.readFileSync(f, 'utf8').match(new RegExp(`^${key}\\s*=\\s*"?([^"\\n\\r]+)`, 'm'));
    if (m) return m[1].trim();
  }
  return null;
}
async function loadFile() {
  if (FILE) return JSON.parse(fs.readFileSync(FILE, 'utf8'));
  const key = envVal('FIGMA_FILE_KEY'); const tok = envVal('FIGMA_TOKEN');
  if (!key || !tok) { console.error('FIGMA_FILE_KEY/FIGMA_TOKEN absents et pas de --file.'); process.exit(2); }
  const res = await fetch(`https://api.figma.com/v1/files/${key}`, { headers: { 'X-Figma-Token': tok } });
  if (!res.ok) { console.error(`Figma API ${res.status}`); process.exit(2); }
  return res.json();
}

const data = await loadFile();
const dict = data.styles || {};
const doc = data.document;
if (!doc || Object.keys(dict).length === 0) { console.log('Aucun style nommé dans le fichier (dictionnaire `styles` vide).'); process.exit(0); }

const hexOf = (n) => {
  for (const f of n.fills || []) {
    if (f.type === 'SOLID' && f.color) {
      const c = f.color, r = Math.round(c.r * 255), g = Math.round(c.g * 255), b = Math.round(c.b * 255);
      return '#' + [r, g, b].map((x) => x.toString(16).padStart(2, '0')).join('');
    }
  }
  return null;
};
// 1) résolution des valeurs (1er nœud référent gagne) sur TOUT le fichier.
const valFill = {}, valText = {};
const resolve = (n) => {
  const st = n.styles;
  if (st && typeof st === 'object') {
    if (st.fill && !valFill[st.fill]) { const h = hexOf(n); if (h) valFill[st.fill] = h; }
    if (st.text && !valText[st.text] && n.style) {
      const s = n.style;
      valText[st.text] = { size: s.fontSize != null ? Math.round(s.fontSize * 10) / 10 : null, weight: s.fontWeight ?? null, family: s.fontFamily ?? null,
        tracking: s.letterSpacing ? Math.round(s.letterSpacing * 100) / 100 : 0, lh: s.lineHeightPx != null ? Math.round(s.lineHeightPx) : null, case: s.textCase && s.textCase !== 'ORIGINAL' ? s.textCase : null };
    }
  }
  for (const c of n.children || []) resolve(c);
};
resolve(doc);

// 2) comptage d'usage (global ou dans --node).
const findNode = (n, id) => { if (n.id === id) return n; for (const c of n.children || []) { const r = findNode(c, id); if (r) return r; } return null; };
const scope = NODE ? findNode(doc, NODE) : doc;
if (!scope) { console.error(`Nœud ${NODE} introuvable.`); process.exit(2); }
const useF = {}, useT = {};
const count = (n) => { const st = n.styles; if (st) { if (st.fill) useF[st.fill] = (useF[st.fill] || 0) + 1; if (st.text) useT[st.text] = (useT[st.text] || 0) + 1; } for (const c of n.children || []) count(c); };
count(scope);

// helpers
const slug = (s) => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
const C = { cyan: '\x1b[36m', green: '\x1b[32m', yellow: '\x1b[33m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };

const entries = Object.entries(dict);
const fills = entries.filter(([, v]) => v.styleType === 'FILL');
const texts = entries.filter(([, v]) => v.styleType === 'TEXT');
const usedHere = (id) => (useF[id] || 0) + (useT[id] || 0);

log(`\nStyles nommés — ${entries.length} (${fills.length} FILL, ${texts.length} TEXT)  |  portée usage : ${NODE ? '«' + (scope.name || NODE) + '»' : 'fichier entier'}`);

log(`\n${C.cyan}### Palette (couleurs nommées) → variables SCSS${C.reset}`);
for (const [id, v] of fills.sort((a, b) => (useF[b[0]] || 0) - (useF[a[0]] || 0))) {
  const hex = valFill[id] || '(non résolu)';
  log(`  ${C.green}$${slug(v.name)}${C.reset}: ${hex};  ${C.dim}// « ${v.name} »${useF[id] ? '  ×' + useF[id] : '  (non utilisé ici)'}${C.reset}`);
}

log(`\n${C.cyan}### Échelle typographique (styles texte nommés)${C.reset}`);
const fmtT = (t) => t ? `${t.size}px / ${t.weight} / ${t.family}${t.tracking ? ' / tracking ' + t.tracking : ''}${t.lh ? ' / lh ' + t.lh : ''}${t.case ? ' / ' + t.case : ''}` : '(non résolu)';
const isMobile = (name) => /mobile/i.test(name);
for (const grp of [['Desktop', (n) => !isMobile(n)], ['Mobile', isMobile]]) {
  const sub = texts.filter(([, v]) => grp[1](v.name));
  if (sub.length === 0) continue;
  log(`  ${C.dim}— ${grp[0]} —${C.reset}`);
  for (const [id, v] of sub.sort((a, b) => (valText[b[0]]?.size || 0) - (valText[a[0]]?.size || 0))) {
    log(`  ${C.green}« ${v.name} »${C.reset} = ${fmtT(valText[id])}${useT[id] ? C.dim + '  ×' + useT[id] + C.reset : C.dim + '  (non utilisé ici)' + C.reset}`);
  }
}

const unresolved = entries.filter(([id, v]) => (v.styleType === 'FILL' && !valFill[id]) || (v.styleType === 'TEXT' && !valText[id]));
if (unresolved.length) log(`\n${C.yellow}Non résolus${C.reset} (aucun nœud référent trouvé) : ${unresolved.map(([, v]) => v.name).join(', ')}`);

log(`\n${C.dim}──────────${C.reset}`);
log(`Usage : reporter la palette dans variables.scss ($creme, $gold…) et caler l'échelle de titres`);
log(`($font-size-h2/h3…) sur les styles « Hn » ; les « Sous-titre Hn » = polices script décoratives`);
log(`(classe dédiée). Ces NOMS expliquent les « orphelins » de reconcile-typography (54px = Sous-titre H3).`);

if (OUT) { fs.writeFileSync(OUT, '# Styles nommés Figma (design system)\n\n```\n' + lines.join('\n') + '\n```\n'); console.log(`\nRapport : ${OUT}`); }

if (JSON_OUT) {
  const machine = {
    fills: fills.map(([id, v]) => ({ name: v.name, slug: slug(v.name), hex: valFill[id] || null, used: useF[id] || 0 })),
    textStyles: texts.map(([id, v]) => ({ name: v.name, ...(valText[id] || {}), used: useT[id] || 0 })),
  };
  fs.writeFileSync(JSON_OUT, JSON.stringify(machine, null, 2));
  console.log(`JSON : ${JSON_OUT}`);
}
