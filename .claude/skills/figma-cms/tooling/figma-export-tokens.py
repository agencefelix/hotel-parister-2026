#!/usr/bin/env python
"""
Export JSON COMPLET des tokens d'un node Figma (tous les sous-nodes, tous les styles).

Usage (depuis la racine du projet) :
  python .claude/skills/figma-cms/tooling/figma-export-tokens.py <nodeId> <outFile.json> [depth]
  ex: ... 542:1592 .claude/skills/figma-cms/integration/figma-tokens.home.json 12

Lit FIGMA_TOKEN / FIGMA_FILE_KEY depuis .env.local|.env. Pour CHAQUE node descendant, exporte :
id, name, type, position/taille (x,y,w,h relatifs au node racine), fontSize, fontWeight,
fontFamily, letterSpacing, lineHeightPx, textCase, textAlign, characters (texte),
fills (couleurs hex + type IMAGE/GRADIENT), strokes (hex) + strokeWeight, cornerRadius,
effects (ombres : type, couleur, offset, blur, spread), layoutMode + paddings + itemSpacing, opacity.
→ référence exhaustive « par token » (le .md reste le résumé lisible).
"""
import json, os, re, sys, urllib.request

def env(key):
    for f in ('.env.local', '.env'):
        if os.path.isfile(f):
            for line in open(f, encoding='utf-8'):
                m = re.match(r'\s*' + key + r'\s*=\s*"?([^"\r\n]+)"?', line)
                if m: return m.group(1).strip()
    return None

def hexc(c):
    return '#%02x%02x%02x' % (round(c.get('r', 0) * 255), round(c.get('g', 0) * 255), round(c.get('b', 0) * 255))

def fills(arr):
    out = []
    for f in (arr or []):
        t = f.get('type')
        if t == 'SOLID': out.append({'type': 'SOLID', 'hex': hexc(f['color']), 'opacity': round(f.get('opacity', f['color'].get('a', 1)), 2)})
        elif t == 'IMAGE': out.append({'type': 'IMAGE', 'imageRef': f.get('imageRef')})
        elif t and 'GRADIENT' in t:
            out.append({'type': t, 'stops': [hexc(s['color']) for s in f.get('gradientStops', [])]})
    return out

def effects(arr):
    out = []
    for e in (arr or []):
        if not e.get('visible', True): continue
        o = e.get('offset', {})
        out.append({'type': e.get('type'), 'hex': hexc(e['color']) if e.get('color') else None,
                    'x': o.get('x'), 'y': o.get('y'), 'blur': e.get('radius'), 'spread': e.get('spread')})
    return out

node = sys.argv[1] if len(sys.argv) > 1 else None
out_file = sys.argv[2] if len(sys.argv) > 2 else None
depth = sys.argv[3] if len(sys.argv) > 3 else '12'
if not node or not out_file:
    print('usage: figma-export-tokens.py <nodeId> <outFile.json> [depth]'); sys.exit(1)
tok, key = env('FIGMA_TOKEN'), env('FIGMA_FILE_KEY')
url = 'https://api.figma.com/v1/files/%s/nodes?ids=%s&depth=%s&geometry=paths' % (key, node, depth)
d = json.load(urllib.request.urlopen(urllib.request.Request(url, headers={'X-Figma-Token': tok})))
root = d['nodes'][node]['document']
FX = (root.get('absoluteBoundingBox') or {}).get('x', 0)
FY = (root.get('absoluteBoundingBox') or {}).get('y', 0)

items = []
def walk(n):
    bb = n.get('absoluteBoundingBox') or {}
    st = n.get('style', {})
    rec = {
        'id': n.get('id'), 'name': n.get('name'), 'type': n.get('type'),
        'x': round(bb.get('x', 0) - FX) if bb else None, 'y': round(bb.get('y', 0) - FY) if bb else None,
        'w': round(bb.get('width', 0)) if bb else None, 'h': round(bb.get('height', 0)) if bb else None,
    }
    if n.get('opacity') is not None and n['opacity'] != 1: rec['opacity'] = n['opacity']
    if st:
        for k_src, k_dst in (('fontSize','fontSize'),('fontWeight','fontWeight'),('fontFamily','fontFamily'),
                             ('letterSpacing','letterSpacing'),('lineHeightPx','lineHeightPx'),
                             ('textCase','textCase'),('textAlignHorizontal','textAlign')):
            if st.get(k_src) is not None: rec[k_dst] = st[k_src]
    if n.get('type') == 'TEXT' and n.get('characters'): rec['characters'] = n['characters']
    f = fills(n.get('fills'));    rec['fills'] = f if f else None
    s = fills(n.get('strokes'));
    if s: rec['strokes'] = s; rec['strokeWeight'] = n.get('strokeWeight')
    ef = effects(n.get('effects'))
    if ef: rec['effects'] = ef
    if n.get('cornerRadius'): rec['cornerRadius'] = n['cornerRadius']
    lm = n.get('layoutMode')
    if lm and lm != 'NONE':
        rec['layout'] = {'mode': lm, 'padTop': n.get('paddingTop', 0), 'padRight': n.get('paddingRight', 0),
                         'padBottom': n.get('paddingBottom', 0), 'padLeft': n.get('paddingLeft', 0), 'gap': n.get('itemSpacing', 0)}
    rec = {k: v for k, v in rec.items() if v is not None}
    items.append(rec)
    for c in n.get('children', []): walk(c)

walk(root)
json.dump({'node': node, 'count': len(items), 'items': items}, open(out_file, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print('exported %d nodes -> %s' % (len(items), out_file))
