/**
 * Cartographie du CSS NATIF susceptible d'ÉCRASER une nouvelle intégration.
 *
 * Le projet pré-existant embarque beaucoup de modules natifs (SCSS dans assets/scss/front/default)
 * dont des règles à forte spécificité ou en `!important` peuvent prendre le dessus sur le CSS intégré.
 * À lancer AVANT la 1ʳᵉ intégration pour CONNAÎTRE ces règles (et savoir ce qu'il faudra battre).
 *
 * Usage (depuis la RACINE du projet) :
 *   node .claude/skills/figma-cms/tooling/css-baseline.mjs [scssDir] [--out baseline.md] [--top 50]
 *   (scssDir défaut : assets/scss/front/default)
 *
 * Repère 3 familles de risques sur les propriétés typo/espacement/fond
 * (font-size, font-weight, line-height, letter-spacing, color, text-transform, margin, padding, background, gap) :
 *   1. !important            → gagne quelle que soit la spécificité ;
 *   2. sélecteurs d'ÉLÉMENT / globaux (h1-h6, p, a, ul, li, body, :root, *, …) → s'appliquent partout ;
 *   3. sélecteurs LARGES `[class*=…]` → ratissent de nombreuses classes.
 * Pour chacun : sélecteur (chemin imbriqué) + fichier:ligne + propriété.
 */
import fs from 'node:fs';
import path from 'node:path';

const args = process.argv.slice(2);
const DIR = (args[0] && !args[0].startsWith('--')) ? args[0] : 'assets/scss/front/default';
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const OUT = opt('--out', null);
const TOP = parseInt(opt('--top', '50'), 10);

const RISKY = /^(font-size|font-weight|line-height|letter-spacing|color|text-transform|text-align|margin|margin-top|margin-right|margin-bottom|margin-left|padding|padding-top|padding-right|padding-bottom|padding-left|background|background-color|gap|row-gap|column-gap)$/;
const ELEMENT = /(^|\s|,|>|\+|~)(html|body|h[1-6]|p|a|ul|ol|li|img|button|figure|figcaption|span|strong|em|small|blockquote|table|tr|td|input|textarea|label|:root|\*)(\s|,|>|\+|~|:|$)/i;
const BROAD = /\[class\*=/;
// Règle CONDITIONNÉE : ne s'applique que si une classe/attribut d'ÉTAT est posé sur body/html/:root
// (mode accessibilité `body.as-accessibility`, thème `[data-bs-theme=dark]`, etc.) → inactive par défaut,
// donc PAS une menace d'écrasement par défaut. `body .card` (avec espace) n'est PAS conditionné.
const STATE = /(?:^|[\s,>+~(])(?:html|body|:root)(?:\.[\w-]+|\[[^\]]+\])|\[data-[\w-]*theme/i;
const condOf = (sel) => { const m = (sel || '').match(STATE); return m ? m[0].replace(/^[\s,>+~(]+/, '').trim() : null; };

function walk(dir) {
  const out = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) out.push(...walk(p));
    else if (e.name.endsWith('.scss')) out.push(p);
  }
  return out;
}

const stripComments = (s) => s.replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' ')).replace(/\/\/[^\n]*/g, '');

// Parse SCSS en gardant la pile de sélecteurs imbriqués + le n° de ligne.
function scan(file) {
  const raw = stripComments(fs.readFileSync(file, 'utf8'));
  const findings = { important: [], element: [], broad: [] };
  const stack = [];
  let buf = '';
  let line = 1;
  const selPath = () => stack.join(' ').replace(/\s+/g, ' ').trim();
  for (let i = 0; i < raw.length; i++) {
    const ch = raw[i];
    if (ch === '\n') line++;
    if (ch === '{') {
      const sel = buf.trim();
      buf = '';
      if (sel.startsWith('@')) { stack.push(''); continue; } // at-rule (media…) : pas un sélecteur
      stack.push(sel);
      if (ELEMENT.test(sel)) findings.element.push({ sel: selPath(), line, file });
      if (BROAD.test(sel)) findings.broad.push({ sel: selPath(), line, file });
    } else if (ch === '}') {
      stack.pop();
      buf = '';
    } else if (ch === ';') {
      const decl = buf.trim(); buf = '';
      const prop = decl.split(':', 1)[0].trim().toLowerCase();
      if (RISKY.test(prop) && /!important/i.test(decl)) {
        findings.important.push({ sel: selPath(), line, file, prop });
      }
    } else {
      buf += ch;
    }
  }
  return findings;
}

const files = walk(DIR);
const all = { important: [], element: [], broad: [] };
for (const f of files) {
  const r = scan(f);
  for (const k of Object.keys(all)) all[k].push(...r[k]);
}

const rel = (f) => f.replace(/\\/g, '/');
const C = { red: '\x1b[31m', yellow: '\x1b[33m', green: '\x1b[32m', dim: '\x1b[2m', reset: '\x1b[0m' };
const lines = [];
const log = (s = '') => { lines.push(s.replace(/\x1b\[\d+m/g, '')); console.log(s); };

// Partition ACTIF (toujours appliqué) vs CONDITIONNÉ (gated par une classe d'état sur body/html).
const split = (arr) => {
  const active = [], cond = [];
  for (const x of arr) { const g = condOf(x.sel); if (g) cond.push({ ...x, gate: g }); else active.push(x); }
  return { active, cond };
};
const P = { important: split(all.important), element: split(all.element), broad: split(all.broad) };
const condTotal = P.important.cond.length + P.element.cond.length + P.broad.cond.length;
// Regroupe les conditionnés par classe d'état.
const byGate = new Map();
for (const k of ['important', 'element', 'broad']) for (const x of P[k].cond) byGate.set(x.gate, (byGate.get(x.gate) || 0) + 1);

log(`\nCartographie CSS natif — ${rel(DIR)}  (${files.length} fichiers .scss)`);
log(`Overriders TOUJOURS ACTIFS (props typo/espacement/fond) :`);
log(`  ${C.red}!important${C.reset} : ${P.important.active.length}   |   ${C.yellow}sélecteurs d'élément/globaux${C.reset} : ${P.element.active.length}   |   ${C.yellow}[class*=]${C.reset} : ${P.broad.active.length}`);
log(`  ${C.dim}(+ ${condTotal} conditionnés par une classe d'état body/html — inactifs par défaut, cf. fin)${C.reset}\n`);

log(`${C.red}### 1. !important ACTIF sur propriété sensible${C.reset} (gagne quelle que soit la spécificité) — top ${TOP}`);
for (const x of P.important.active.slice(0, TOP)) log(`  ${C.dim}${rel(x.file)}:${x.line}${C.reset}  ${x.prop}  ${C.dim}{ ${x.sel || '(racine)'} }${C.reset}`);
if (P.important.active.length > TOP) log(`  … +${P.important.active.length - TOP} autres`);

log(`\n${C.yellow}### 2. Sélecteurs d'ÉLÉMENT / globaux ACTIFS${C.reset} (h1-h6, p, a, body, :root, *…) — top ${TOP}`);
for (const x of P.element.active.slice(0, TOP)) log(`  ${C.dim}${rel(x.file)}:${x.line}${C.reset}  { ${x.sel} }`);
if (P.element.active.length > TOP) log(`  … +${P.element.active.length - TOP} autres`);

log(`\n${C.yellow}### 3. Sélecteurs larges [class*=] ACTIFS${C.reset}`);
for (const x of P.broad.active.slice(0, TOP)) log(`  ${C.dim}${rel(x.file)}:${x.line}${C.reset}  { ${x.sel} }`);

log(`\n${C.dim}### 4. CONDITIONNÉS — n'agissent QUE si la classe d'état est posée sur body/html (inactifs par défaut)${C.reset}`);
log(`${C.dim}   ${condTotal} règle(s), par classe d'état :${C.reset}`);
for (const [gate, n] of [...byGate.entries()].sort((a, b) => b[1] - a[1])) log(`  ${C.dim}${gate} : ${n}${C.reset}`);

log(`\n${C.dim}──────────${C.reset}`);
log(`Priorité : traiter les overriders ACTIFS (1-3). Les conditionnés (4) ne concernent que le mode/thème`);
log(`correspondant. Stratégie : pour qu'un style intégré GAGNE sans !important, le scoper par l'#id du`);
log(`composant (ex. #home-hero .title — l'ID bat les classes). Sinon inspecter la règle gagnante dans le`);
log(`CSS compilé / via verify-styles (qui échoue si le rendu est écrasé).`);

if (OUT) {
  fs.writeFileSync(OUT, '# Cartographie CSS natif (overriders potentiels)\n\n```\n' + lines.join('\n') + '\n```\n');
  console.log(`\nRapport : ${OUT}`);
}
