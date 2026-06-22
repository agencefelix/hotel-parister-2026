/**
 * Capture Chrome réutilisable pour la vérification d'intégration (≥95% iso).
 *
 * Usage (depuis la RACINE du projet, où puppeteer-core est installé) :
 *   node .claude/skills/figma-cms/tooling/capture.mjs <url> <outDir> [zoneId1 zoneId2 ...]
 *
 * - Sans zoneIds : capture la page entière → <outDir>/_full.png
 * - Avec zoneIds : capture chaque #zone-<id> (ou #<id>) → <outDir>/<id>.png
 *
 * Masque automatiquement bandeaux cookies / GDPR / toolbar Symfony.
 * Prérequis : Chrome installé ; puppeteer-core dans node_modules du projet.
 * Astuce : copier ce fichier à la racine si l'import puppeteer-core ne résout pas.
 */
import puppeteer from 'puppeteer-core';

const URL = process.argv[2] || 'https://localhost/';
const OUT = process.argv[3] || '.';
const ZONES = process.argv.slice(4);
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--ignore-certificate-errors', '--no-sandbox', '--disable-gpu'],
  ignoreHTTPSErrors: true,
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });
await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });
await sleep(1600);

// Masquer cookies / GDPR / toolbar.
await page.evaluate(() => {
  for (const s of ['#gdpr', '[class*="cookie"]', '[id*="cookie"]', '[id*="gdpr"]', '[class*="consent"]', '[class*="axeptio"]', '#sfToolbar', '.sf-toolbar']) {
    document.querySelectorAll(s).forEach((e) => e.remove());
  }
  document.querySelectorAll('body *').forEach((e) => {
    const t = e.textContent || '';
    if (e.children.length <= 10 && (t.includes('Gestion des cookies') || t.includes('OK pour moi')) && e.offsetWidth > 200 && e.offsetWidth < 900) e.remove();
  });
  document.querySelectorAll('body *').forEach((e) => {
    const st = getComputedStyle(e);
    if ((st.position === 'fixed' || st.position === 'sticky') && parseInt(st.zIndex || 0) >= 900 && e.offsetHeight > 120) e.style.display = 'none';
  });
});
await sleep(300);

// Déclenche le lazy-load.
await page.evaluate(async () => {
  await new Promise((r) => { let y = 0; const t = setInterval(() => { window.scrollBy(0, 800); y += 800; if (y >= document.body.scrollHeight) { clearInterval(t); r(); } }, 80); });
});
await sleep(1000);
await page.evaluate(() => window.scrollTo(0, 0));
await sleep(500);

if (ZONES.length === 0) {
  await page.screenshot({ path: `${OUT}/_full.png`, fullPage: true });
  console.log('full → ' + OUT + '/_full.png (h=' + (await page.evaluate(() => document.body.scrollHeight)) + ')');
} else {
  for (const id of ZONES) {
    let el = await page.$('#zone-' + id);
    if (!el) el = await page.$('#' + id);
    if (!el) { console.log('MISS ' + id); continue; }
    await el.evaluate((e) => e.scrollIntoView());
    await sleep(400);
    await el.screenshot({ path: `${OUT}/${id}.png` });
    console.log('OK ' + id);
  }
}
await browser.close();
