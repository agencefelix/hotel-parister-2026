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

log(`\nCartographie CSS natif — ${rel(DIR)}  (${files.length} fichiers .scss)`);
log(`Risques d'écrasement (props typo/espacement/fond) :`);
log(`  ${C.red}!important${C.reset} : ${all.important.length}   |   ${C.yellow}sélecteurs d'élément/globaux${C.reset} : ${all.element.length}   |   ${C.yellow}[class*=]${C.reset} : ${all.broad.length}\n`);

log(`${C.red}### 1. !important sur propriété sensible${C.reset} (gagne quelle que soit la spécificité) — top ${TOP}`);
for (const x of all.important.slice(0, TOP)) log(`  ${C.dim}${rel(x.file)}:${x.line}${C.reset}  ${x.prop}  ${C.dim}{ ${x.sel || '(racine)'} }${C.reset}`);
if (all.important.length > TOP) log(`  … +${all.important.length - TOP} autres`);

log(`\n${C.yellow}### 2. Sélecteurs d'ÉLÉMENT / globaux${C.reset} (s'appliquent partout : h1-h6, p, a, body, :root, *…) — top ${TOP}`);
for (const x of all.element.slice(0, TOP)) log(`  ${C.dim}${rel(x.file)}:${x.line}${C.reset}  { ${x.sel} }`);
if (all.element.length > TOP) log(`  … +${all.element.length - TOP} autres`);

log(`\n${C.yellow}### 3. Sélecteurs larges [class*=]${C.reset}`);
for (const x of all.broad.slice(0, TOP)) log(`  ${C.dim}${rel(x.file)}:${x.line}${C.reset}  { ${x.sel} }`);

log(`\n${C.dim}──────────${C.reset}`);
log(`Stratégie : pour qu'un style intégré GAGNE sans !important, le scoper par l'#id du composant`);
log(`(ex. #footer .title) — l'ID (1,x,x) bat les classes. Sinon, vérifier la règle gagnante dans le`);
log(`CSS compilé / via verify-styles (qui échoue si le rendu est écrasé). Réécrire proprement le CSS`);
log(`d'un composant de layout plutôt qu'empiler des overrides.`);

if (OUT) {
  fs.writeFileSync(OUT, '# Cartographie CSS natif (overriders potentiels)\n\n```\n' + lines.join('\n') + '\n```\n');
  console.log(`\nRapport : ${OUT}`);
}
