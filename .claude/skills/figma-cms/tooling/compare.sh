#!/usr/bin/env bash
# Comparaison maquette ↔ rendu — met les deux à la MÊME largeur et produit :
#   <out>-side.png  : côte à côte (maquette | rendu) à largeur identique
#   <out>-diff.png  : différences pixel surlignées (rouge) — ce qui ne colle PAS saute aux yeux
#
# Usage :
#   bash .claude/skills/figma-cms/tooling/compare.sh <maquette.png> <rendu.png> <out_prefix> [width]
#
# Pourquoi : comparer à des échelles différentes = on « voit » conforme à tort. Toujours normaliser
# la largeur et regarder le -diff (les écarts de couleur/position/taille/poids ressortent en rouge).
set -e
MAQ="$1"; REN="$2"; OUT="${3:-compare}"; W="${4:-720}"
TMPM="${OUT}-m.png"; TMPR="${OUT}-r.png"
magick "$MAQ" -resize "${W}x" "$TMPM"
magick "$REN" -resize "${W}x" "$TMPR"
# Côte à côte (séparateur gris).
magick "$TMPM" "$TMPR" +append -background '#bbbbbb' -splice 6x0+${W}+0 "${OUT}-side.png"
# Diff : recadrer à hauteur commune puis comparer.
H=$(magick identify -format '%h' "$TMPM")
magick "$TMPR" -crop "${W}x${H}+0+0" +repage "$TMPR" 2>/dev/null || true
magick compare -metric AE -fuzz 12% "$TMPM" "$TMPR" -highlight-color red "${OUT}-diff.png" 2>"${OUT}-diff.txt" || true
echo "side -> ${OUT}-side.png"
echo "diff -> ${OUT}-diff.png (pixels différents : $(cat "${OUT}-diff.txt" 2>/dev/null))"
rm -f "$TMPM" "$TMPR"
