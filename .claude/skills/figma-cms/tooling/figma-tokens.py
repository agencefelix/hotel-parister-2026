#!/usr/bin/env python
"""
Extracteur de TOKENS Figma (dev mode) réutilisable — pour ne pas deviner couleurs/tailles/poids.

Usage (depuis la racine du projet) :
  python .claude/skills/figma-cms/tooling/figma-tokens.py <nodeId> [depth]

Lit FIGMA_TOKEN / FIGMA_FILE_KEY depuis .env.local|.env, télécharge le node, et imprime —
trié par position Y — chaque TEXT (caractères, fontSize, fontWeight, letterSpacing, lineHeight,
textCase, couleur fill hex) et chaque conteneur à fond/auto-layout (bg hex, paddings, gap).
Rediriger la sortie vers .claude/skills/figma-cms/integration/figma-styles.md pour la garder consultable.
"""
import json, os, re, sys, urllib.request

def env(key):
    for f in ('.env.local', '.env'):
        if os.path.isfile(f):
            for line in open(f, encoding='utf-8'):
                m = re.match(r'\s*'+key+r'\s*=\s*"?([^"\r\n]+)"?', line)
                if m: return m.group(1).strip()
    return None

node = sys.argv[1] if len(sys.argv) > 1 else None
depth = sys.argv[2] if len(sys.argv) > 2 else '6'
if not node:
    print('usage: figma-tokens.py <nodeId> [depth]'); sys.exit(1)
tok, key = env('FIGMA_TOKEN'), env('FIGMA_FILE_KEY')
url = 'https://api.figma.com/v1/files/%s/nodes?ids=%s&depth=%s&geometry=paths' % (key, node, depth)
req = urllib.request.Request(url, headers={'X-Figma-Token': tok})
d = json.load(urllib.request.urlopen(req))
root = d['nodes'][node]['document']
FY = (root.get('absoluteBoundingBox') or {}).get('y', 0)

def hexf(fills):
    for f in (fills or []):
        if f.get('type') == 'SOLID':
            c = f['color']; return '#%02x%02x%02x' % (round(c['r']*255), round(c['g']*255), round(c['b']*255))
        if f.get('type') == 'IMAGE': return 'IMG'
    return ''

rows = []
def walk(n):
    bb = n.get('absoluteBoundingBox') or {}; y = round(bb.get('y', 0) - FY)
    t = n.get('type')
    if t == 'TEXT':
        st = n.get('style', {})
        rows.append((y, 'TXT sz=%s w=%s ls=%s lh=%s case=%s fill=%s %r' % (
            st.get('fontSize'), st.get('fontWeight'), round(st.get('letterSpacing', 0), 2),
            round(st.get('lineHeightPx', 0)), st.get('textCase', ''), hexf(n.get('fills')),
            n.get('characters', '')[:48])))
    elif t in ('FRAME', 'RECTANGLE', 'GROUP', 'INSTANCE', 'COMPONENT'):
        bg = hexf(n.get('fills')); lm = n.get('layoutMode')
        pad = ''
        if lm and lm != 'NONE':
            pad = 'pad[%s,%s,%s,%s] gap=%s' % (n.get('paddingTop', 0), n.get('paddingRight', 0), n.get('paddingBottom', 0), n.get('paddingLeft', 0), n.get('itemSpacing', 0))
        if bg or pad:
            rows.append((y, '%-3s %s h=%s %s %s %r' % (t[:3], 'bg=' + bg if bg else '', round(bb.get('height', 0)), lm or '', pad, n.get('name', '')[:28])))
    for c in n.get('children', []): walk(c)

walk(root)
rows.sort(key=lambda r: r[0])
for y, info in rows:
    print('%6d %s' % (y, info))
