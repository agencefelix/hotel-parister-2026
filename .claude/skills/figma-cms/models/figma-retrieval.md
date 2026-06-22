# Récupération Figma → CMS — cookbook (générique, réutilisable)

> Recettes **concrètes** pour extraire de Figma (REST API, sans navigateur) et insérer dans le CMS.
> Réutilisable tel quel sur tout projet. Compléments : `integration-prompts.md`, `fixtures-examples.md`.

## 0) Auth
```bash
TOK=$(grep -hoE "FIGMA_TOKEN=.*" .env.local .env | head -1 | cut -d= -f2 | tr -d '"'"'"' \r')
KEY=$(grep -hoE "FIGMA_FILE_KEY=.*" .env.local .env | head -1 | cut -d= -f2 | tr -d '"'"'"' \r')
H="-H \"X-Figma-Token: $TOK\""
```

## 1) Découvrir les pages `[page|…]`
`GET /v1/files/$KEY?depth=2` → parcourir `document.children` (récursif) et repérer les frames dont le
`name` matche `\[page|`. Idem pour `[nav]`, `[footer]`, `[socialwall]`, `[newsletter]`, `[teaser|product]`.

## 2) Arbre JSON d'un node (textes, couleurs, positions)
```bash
curl -s $H "https://api.figma.com/v1/files/$KEY/nodes?ids=NODE_ID&depth=8&geometry=paths" -o node.json
```
Parcourir récursivement `nodes[NODE_ID].document` :
- **Texte** : `x.characters`
- **Couleur texte/fond** : `x.fills[]` (type `SOLID` → `color{r,g,b}` → `#rrggbb`)
- **Bordure** : `x.strokes[]`
- **Image** : `x.fills[]` type `IMAGE` → `imageRef`
- **Position/taille** : `x.absoluteBoundingBox {x,y,width,height}` (relatif = `x - frame.x`, `y - frame.y`)

```js
// conversion couleur fill → hex
const hex = f => '#'+['r','g','b'].map(k=>('0'+Math.round((f.color[k]||0)*255).toString(16)).slice(-2)).join('');
```

## 3) Ordre de lecture d'une zone (pour placer les cols dans le bon sens)
Lister tous les éléments (textes + images) de la zone, trier par **`y` puis `x`** (haut→bas, gauche→droite).
Reproduire cet ordre dans `addCol`/`addBlock`. Compter les images → nb de `mediaBlock` à créer (déficit = oubli).

## 4) Exporter un node en IMAGE (PNG/JPG)
```bash
URL=$(curl -s $H "https://api.figma.com/v1/images/$KEY?ids=NODE_ID&format=png&scale=2" | grep -oE 'https://[^"]+')
curl -s "$URL" -o image.png
```
- `scale=2|3|4` pour la netteté (logos, icônes). `format=jpg` pour les photos.
- **Node instance** (id avec `;`, ex. `I542:1602;410:4684`) : **URL-encoder** les ids
  (`python -c "import urllib.parse,sys;print(urllib.parse.quote(sys.argv[1]))" "$IDS"`).
- Le rendu d'un node = l'élément **rogné à son cadre** (utile pour façade, logo, photo de carte).
- Variante COULEUR selon le fond (ex. logo clair pour fond foncé) → exporter le node de la version voulue.

## 5) Images de fond (imageRef) d'une zone
`GET /v1/files/$KEY/images` renvoie `meta.images` (map `imageRef` → URL d'origine). Sinon, exporter le
**node** qui porte le fill image (cf. §4) — méthode la plus fiable.

## 6) Médias par catégorie (où poser les fichiers)
- **Médias de bandes / home** : `.claude/skills/figma-cms/integration/media/home/<nom>.jpg` (importés par `PageFixtures::importMedia`).
- **Logos / photos de marque réutilisées (footer, mega-menu)** : `public/medias/<nom>.png|jpg` (référencés en `asset('medias/...')`).
- **Images produits** : `media/home/room-N.jpg` (rattachées par `CatalogFixtures::generateMediaRelation`).
- **Images actus** : `media/news/news-<slug>.jpg` (rattachées par `NewscastFixtures::generateMediaRelation`).

## 7) Récupérer le CONTENU de la PROD (au-delà de la maquette)
La maquette ne contient pas tout le contenu réel (actus, FAQ, produits…). Inventorier la prod :
- **Index d'un module** (ex. actus) : `WebFetch` l'URL d'index → lister titres + URLs des fiches.
- **Image principale d'une fiche** : sur ce CMS, hero = élément **`.block_entete`** avec
  `style="background-image:url(...)"`. Récupérer via Puppeteer :
```js
// UA navigateur + délai (anti-429), base64 par CHUNKS (sinon stack overflow sur grosses images)
const url = await page.evaluate(()=>{const e=document.querySelector('.block_entete');const m=e&&getComputedStyle(e).backgroundImage.match(/url\(["']?(.*?)["']?\)/);return m?m[1]:null;});
const data = await page.evaluate(async u=>{const b=new Uint8Array(await (await fetch(u)).arrayBuffer());let s='';for(let i=0;i<b.length;i+=8192)s+=String.fromCharCode.apply(null,b.subarray(i,i+8192));return btoa(s);}, url);
fs.writeFileSync(out, Buffer.from(data,'base64'));
```
- **Throttling** : la prod peut renvoyer **429** → User-Agent réaliste + délai ~1s entre requêtes + retry.
- **Continuité SEO** : code URL CMS = slug de la PROD (`generateUrl($entity, $slugProd)`).

## 8) Insertion CMS (rappel) → `fixtures-examples.md`
Tout ce qui est extrait alimente les fixtures (`src/Service/DataFixtures/*`) : zones/cols/blocks
(`PageFixtures`), produits (`CatalogFixtures`), actus (`NewscastFixtures`), menus (`MenuFixtures`),
médias (`UploadedFileFixtures`). Voir les snippets complets dans `fixtures-examples.md`.

## 9) Vérification (obligatoire, cf. RIGUEUR)
`yarn build` + reload (exit code réel) + capture Puppeteer sur le **domaine local** et **comparaison
côte à côte** avec la maquette (ImageMagick `-append` / `montage`). Itérer jusqu'à correspondance.
