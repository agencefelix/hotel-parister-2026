/**
 * RÉCAP UNIFIÉ « design system Figma → SCSS ».
 *
 * Orchestrateur (DRY) : enchaîne les outils existants et assemble UN seul rapport —
 *   1. figma-named-styles  : palette + échelle typo NOMMÉES (source de vérité, via file_content:read) ;
 *   2. reconcile-colors    : couleurs de la page → palette nommée + variables SCSS existantes ;
 *   3. reconcile-typography: tailles de la page → échelle SCSS, annotées du style nommé.
 * But : une vue unique « ce que le design system impose ↔ ce que le SCSS du projet offre / doit ajouter »,
 * AVANT de styler. Aucun scope au-delà de file_content:read.
 *
 * Usage (depuis la RACINE) :
 *   node .claude/skills/figma-cms/tooling/design-system.mjs --tokens integration/figma-tokens.<page>.json \
 *        [--node <pageId>] [--file dump.json] [--scss variables.scss] [--out integration/design-system.md]
 *   - --tokens : figma-tokens.<page>.json (figma-export-tokens.py) — REQUIS (couleurs/tailles de la page).
 *   - --node/--file : transmis à figma-named-styles (sinon il fetch l'API via .env).
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const args = process.argv.slice(2);
const opt = (n, d) => { const i = args.indexOf(n); return i !== -1 && args[i + 1] ? args[i + 1] : d; };
const TOKENS = opt('--tokens', null);
const NODE = opt('--node', null);
const FILE = opt('--file', null);
const SCSS = opt('--scss', null);
const OUT = opt('--out', null);
if (!TOKENS) { console.error('Usage: node design-system.mjs --tokens figma-tokens.<page>.json [--node id] [--file dump.json] [--scss variables.scss] [--out r.md]'); process.exit(2); }

const here = path.dirname(fileURLToPath(import.meta.url));
const tool = (n) => path.join(here, n);
const stripAnsi = (s) => s.replace(/\x1b\[\d+m/g, '');
const run = (script, a) => {
  try { return stripAnsi(execFileSync(process.execPath, [tool(script), ...a], { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 })); }
  catch (e) { return stripAnsi((e.stdout || '') + (e.stderr ? '\n[stderr] ' + e.stderr : '')); } // capture même si exit≠0 (--strict)
};

const named = [];
if (NODE) named.push('--node', NODE);
if (FILE) named.push('--file', FILE);
const reconArgs = (extra = []) => { const a = [TOKENS, '--named', NAMED_JSON]; if (SCSS) a.push('--scss', SCSS); return a.concat(extra); };

const NAMED_JSON = path.join(os.tmpdir(), 'ds-named-' + process.pid + '.json');

console.error('… figma-named-styles');
const sNamed = run('figma-named-styles.mjs', named.concat(['--json', NAMED_JSON])).replace(/^JSON : .*$/m, '').trimEnd();
console.error('… reconcile-colors');
const sColors = fs.existsSync(NAMED_JSON) ? run('reconcile-colors.mjs', reconArgs()) : '(palette nommée indisponible)';
console.error('… reconcile-typography');
const sTypo = run('reconcile-typography.mjs', reconArgs());
try { fs.unlinkSync(NAMED_JSON); } catch {}

const md = `# Design system Figma → SCSS — récap unifié${NODE ? ' (' + NODE + ')' : ''}

> Vue consolidée AVANT intégration : la **vérité nommée** du design system (palette + échelle typo)
> confrontée au **SCSS du projet** (variables existantes à réutiliser / à ajouter). Généré par
> \`tooling/design-system.mjs\` (orchestre figma-named-styles + reconcile-colors + reconcile-typography).

## 1. Styles nommés (palette + échelle typographique)
\`\`\`
${sNamed.trim()}
\`\`\`

## 2. Couleurs de la page → palette nommée + variables SCSS
\`\`\`
${sColors.trim()}
\`\`\`

## 3. Typographie de la page → échelle SCSS (annotée des styles nommés)
\`\`\`
${sTypo.trim()}
\`\`\`

## À reporter dans variables.scss
- **Couleurs** : réutiliser les variables SCSS pointées (✓) ; ajouter celles listées « À AJOUTER ».
- **Titres** : caler \`$font-size-h*\` sur les styles « Hn » ; créer une **classe dédiée** pour les
  styles nommés hors échelle (« Sous-titre Hn »…). Arbitrer les **orphelins anonymes** (one-off).
- **Espacements** : \`reconcile-margins.mjs\` (échelle \`$margins\`) — complément de ce récap.
`;

console.log(md);
if (OUT) { fs.writeFileSync(OUT, md); console.error(`\nRapport : ${OUT}`); }
