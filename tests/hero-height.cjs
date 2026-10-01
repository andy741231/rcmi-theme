/**
 * RCMI hero/parallax section-height regression — the block's Section
 * height (viewport %) is the section's ACTUAL border-box height, not a
 * minimum floor: a 55vh section measures exactly 55% of the viewport at
 * every width, regardless of image aspect ratio or content height.
 *
 * Injects fixture sections into a local DOM (never the DB) and loads the
 * real theme stylesheet (assets/css/rcmi.css) in headless Chromium via
 * puppeteer.
 *
 * Requires the `puppeteer` npm package (no install needed if it exists
 * elsewhere — point NODE_PATH at a directory containing it):
 *   NODE_PATH=/path/to/node_modules node tests/hero-height.cjs
 *
 * Artifacts (log + screenshots) go to /tmp/rcmi-hero-height/.
 * Exits non-zero if any assertion fails.
 */
'use strict';

const fs = require('fs');
const path = require('path');
let puppeteer;
try {
	puppeteer = require('puppeteer');
} catch (e) {
	console.error('puppeteer not found — run with NODE_PATH pointing at a node_modules containing it.');
	process.exit(1);
}

const THEME = path.resolve(__dirname, '..');
const OUT = '/tmp/rcmi-hero-height';
fs.mkdirSync(OUT, { recursive: true });
const logStream = fs.createWriteStream(path.join(OUT, 'hero-height.log'));
const say = (m) => { process.stdout.write(m + '\n'); logStream.write(m + '\n'); };

const css = fs.readFileSync(path.join(THEME, 'assets/css/rcmi.css'), 'utf8');

const img = (w, h, label) =>
	`data:image/svg+xml,${encodeURIComponent(
		`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><rect width="100%" height="100%" fill="#8a8078"/><text x="10" y="30" font-size="20" fill="#fff">${label}</text></svg>`
	)}`;

const SHORT_COPY = '<div class="rcmi-parallax-copy"><h1>Short heading</h1></div>';
const TALL_COPY = `<div class="rcmi-parallax-copy"><h1>Community-Engaged Research for Health Equity</h1>${'<p class="lede">Partnering with communities to improve chronic disease outcomes across Houston and the Gulf Coast region.</p>'.repeat(12)}</div>`;

// Real emitted markup shape: height + min-height both inline.
const parallax = (id, copy, imgW, imgH) => `<section id="${id}" class="rcmi-parallax alignfull" style="height: 55vh;min-height: 55vh;">
	<picture><img class="rcmi-parallax-layer rcmi-parallax-layer-background" src="${img(imgW, imgH, 'wide')}" alt="" aria-hidden="true" style="position:absolute;top:50%;left:50%;width:200%;height:200%;object-fit:cover;z-index:0;transform:translate(-50%,-50%);"/></picture>
	<div class="rcmi-parallax-scrim" aria-hidden="true"></div>
	<div class="wrap rcmi-parallax-inner" style="z-index:4">${copy}</div>
</section>`;
const hero = (id, copy) => `<section id="${id}" class="hero -tight" style="height: 55vh;min-height: 55vh;">
	<div class="hero-media" aria-hidden="true" style="background:#f8f5ee"></div>
	<div class="rcmi-parallax-scrim" aria-hidden="true"></div>
	<div class="hero-inner"><div class="hero-grid"><div class="hero-copy">${copy}</div></div></div>
</section>`;

const HTML = `<!doctype html><html><body>
${parallax('px-short-wide', SHORT_COPY, 2400, 900)}
${parallax('px-tall-tallimg', TALL_COPY, 600, 2400)}
${hero('hero-short', SHORT_COPY)}
${hero('hero-tall', TALL_COPY)}
</body></html>`;

const VIEWPORTS = [
	{ w: 1440, h: 900, expect: 495 },   // 0.55 * 900
	{ w: 1440, h: 700, expect: 385 },   // 0.55 * 700
	{ w: 375, h: 812, expect: 446.6 },  // 0.55 * 812 — no mobile auto-growth
];

let failures = 0;
function check(cond, msg) {
	if (cond) { say(`PASS ${msg}`); } else { failures++; say(`FAIL ${msg}`); }
}

(async () => {
	const browser = await puppeteer.launch({ headless: 'new' });
	try {
		const page = await browser.newPage();
		for (const { w, h, expect } of VIEWPORTS) {
			await page.setViewport({ width: w, height: h });
			await page.setContent(HTML, { waitUntil: 'load' });
			await page.addStyleTag({ content: css });
			const m = await page.evaluate(() => {
				const out = {};
				['px-short-wide', 'px-tall-tallimg', 'hero-short', 'hero-tall'].forEach((id) => {
					const el = document.getElementById(id);
					const r = el.getBoundingClientRect();
					out[id] = {
						height: r.height,
						// Whether content taller than the section is contained:
						innerScrollable: null,
					};
					const inner = el.querySelector('.rcmi-parallax-inner, .hero-inner');
					if (inner) {
						out[id].innerScrolls = inner.scrollHeight > inner.clientHeight;
						out[id].innerOverflowsSection = inner.scrollHeight > el.clientHeight;
					}
				});
				return out;
			});
			say(`--- viewport ${w}x${h} (expect ${expect}px) ---`);
			for (const id of Object.keys(m)) {
				const r = m[id];
				check(Math.abs(r.height - expect) <= 1, `${id}: section height ${r.height}px == ${expect}px`);
			}
			check(m['px-tall-tallimg'].innerOverflowsSection, 'tall parallax copy exceeds section (clipped, not grown)');
		}
		await page.setViewport({ width: 1440, height: 900 });
		await page.setContent(HTML, { waitUntil: 'load' });
		await page.addStyleTag({ content: css });
		await page.screenshot({ path: path.join(OUT, 'height-fixtures-1440x900.png'), fullPage: true });
		await page.setViewport({ width: 375, height: 812 });
		await page.screenshot({ path: path.join(OUT, 'height-fixtures-375x812.png'), fullPage: true });
	} finally {
		await browser.close();
	}
	say(failures ? `RESULT: ${failures} failure(s)` : 'RESULT: all section-height checks passed.');
	logStream.end();
	process.exit(failures ? 1 : 0);
})().catch((e) => { say(`ERROR ${e.stack || e}`); process.exit(1); });
