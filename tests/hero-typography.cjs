/**
 * RCMI hero typography regression — computed-style checks for the UH
 * display scale (League Gothic 400 uppercase, line-height 1,
 * letter-spacing -0.015625em; 48px <768px / 72px 768-991.99 / 100px >=992).
 *
 * Injects fixture markup into a local DOM (never the DB) and loads the
 * real theme stylesheets (assets/css/rcmi.css + editor.css) in headless
 * Chromium via puppeteer.
 *
 * Requires the `puppeteer` npm package (no install needed if it exists
 * elsewhere — point NODE_PATH at a directory containing it):
 *   NODE_PATH=/path/to/node_modules node tests/hero-typography.cjs
 *
 * Artifacts (log + screenshots) go to /tmp/rcmi-hero-typography/.
 * Exits non-zero if any assertion fails.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const THEME = path.resolve(__dirname, '..');
const OUT = '/tmp/rcmi-hero-typography';
fs.mkdirSync(OUT, { recursive: true });
const logStream = fs.createWriteStream(path.join(OUT, 'hero-typography.log'));
const say = (m) => { process.stdout.write(m + '\n'); logStream.write(m + '\n'); };

const cssFront = fs.readFileSync(path.join(THEME, 'assets/css/rcmi.css'), 'utf8');
// Strip the Google Fonts @import — offline runs must not hit the network;
// computed font-family names are asserted, not rendered glyphs.
const cssEditor = fs.readFileSync(path.join(THEME, 'assets/css/editor.css'), 'utf8')
	.replace(/@import[^;]+;/g, '');

// Frozen production markup from the saved Hero preset.
const legacyHeading = (id) => `<h1 id="${id}" class="wp-block-heading"><strong><span class="has-inline-font-family has-display-font" style="font-family: 'League Gothic', 'Arial Narrow', sans-serif;"><mark><span class="has-inline-line-height" style="line-height: 0.8"><span class="has-inline-font-size" style="font-size: 3.75rem">Community-Engaged Research for Health Equity</span></span></mark></span></strong></h1>`;

const HTML = `<!doctype html><html><body>
<div class="hero"><div class="hero-inner"><div class="hero-grid">
<div class="hero-copy"><h1 id="fx-static">Community-Engaged Research for Health Equity</h1></div>
</div></div></div>
<section class="rcmi-parallax"><div class="rcmi-parallax-inner">
<div class="rcmi-parallax-copy">${legacyHeading('fx-legacy')}</div>
<div class="rcmi-parallax-copy"><h1 id="fx-plain">Improving Health Across the Gulf Coast</h1></div>
<div class="rcmi-parallax-copy"><h1 id="fx-custom"><span class="has-inline-font-size" style="font-size: 2rem">Community-Engaged Research for Health Equity</span></h1></div>
</div></section>
<h1 id="fx-outside">Ordinary page heading outside the hero</h1>
<div class="editor-styles-wrapper"><div class="rcmi-parallax-editor rcmi-parallax"><div class="rcmi-parallax-inner">
<div class="rcmi-parallax-copy">${legacyHeading('fx-editor')}</div>
</div></div></div>
</body></html>`;

const VIEWPORTS = [
	{ w: 375, size: 48 },
	{ w: 767, size: 48 },
	{ w: 768, size: 72 },
	{ w: 991, size: 72 },
	{ w: 992, size: 100 },
	{ w: 1440, size: 100 },
];

let failures = 0;
function check(cond, msg) {
	if (cond) { say(`PASS ${msg}`); } else { failures++; say(`FAIL ${msg}`); }
}
const near = (a, b, tol = 0.5) => Math.abs(a - b) <= tol;

async function measure(page) {
	return page.evaluate(() => {
		const out = {};
		const cs = (id) => {
			const el = document.getElementById(id);
			if (!el) return null;
			const s = getComputedStyle(el);
			return {
				fontSize: parseFloat(s.fontSize),
				letterSpacing: s.letterSpacing === 'normal' ? 0 : parseFloat(s.letterSpacing),
				lineHeight: s.lineHeight === 'normal' ? NaN : parseFloat(s.lineHeight),
				fontWeight: s.fontWeight,
				textTransform: s.textTransform,
				fontFamily: s.fontFamily,
				overflow: el.scrollWidth - el.clientWidth,
			};
		};
		out.static = cs('fx-static');
		out.legacy = cs('fx-legacy');
		out.plain = cs('fx-plain');
		out.custom = cs('fx-custom');
		out.outside = cs('fx-outside');
		out.editor = cs('fx-editor');
		// Inner spans of the legacy heading.
		const leg = document.getElementById('fx-legacy');
		const sizeSpan = leg.querySelector('.has-inline-font-size');
		const lhSpan = leg.querySelector('.has-inline-line-height');
		const dfSpan = leg.querySelector('.has-display-font');
		const strong = leg.querySelector('strong');
		out.legacySizeSpan = parseFloat(getComputedStyle(sizeSpan).fontSize);
		out.legacyLhSpan = parseFloat(getComputedStyle(lhSpan).lineHeight);
		out.legacyDfLs = getComputedStyle(dfSpan).letterSpacing === 'normal' ? 0 : parseFloat(getComputedStyle(dfSpan).letterSpacing);
		out.legacyStrongW = getComputedStyle(strong).fontWeight;
		const cust = document.getElementById('fx-custom').querySelector('.has-inline-font-size');
		out.customSpan = parseFloat(getComputedStyle(cust).fontSize);
		return out;
	});
}

(async () => {
	const browser = await puppeteer.launch({ headless: 'new' });
	try {
		const page = await browser.newPage();
		for (const { w, size } of VIEWPORTS) {
			await page.setViewport({ width: w, height: 900 });
			await page.setContent(HTML, { waitUntil: 'load' });
			await page.addStyleTag({ content: cssFront });
			await page.addStyleTag({ content: cssEditor });
			const m = await measure(page);
			const ls = size * -0.015625; // -0.75 / -1.125 / -1.5625 px
			say(`--- viewport ${w}px (expect ${size}px / ls ${ls}px) ---`);

			for (const key of ['static', 'legacy', 'plain', 'editor']) {
				const r = m[key];
				check(r && near(r.fontSize, size), `${key}: font-size ${r && r.fontSize}px == ${size}px`);
				check(r && near(r.letterSpacing, ls, 0.1), `${key}: letter-spacing ${r && r.letterSpacing}px == ${ls}px`);
				check(r && near(r.lineHeight, r.fontSize, 0.5), `${key}: line-height ${r && r.lineHeight} == font-size`);
				check(r && '400' === r.fontWeight, `${key}: font-weight ${r && r.fontWeight} == 400`);
				check(r && 'uppercase' === r.textTransform, `${key}: text-transform ${r && r.textTransform} == uppercase`);
				check(r && /League Gothic/.test(r.fontFamily), `${key}: font-family is League Gothic (${r && r.fontFamily})`);
				check(r && r.overflow <= 0, `${key}: no headline overflow (${r && r.overflow}px)`);
			}

			// Saved-preset compat: inner spans pinned to the hero scale.
			check(near(m.legacySizeSpan, size), `legacy inner font-size span ${m.legacySizeSpan}px == ${size}px (not frozen 60px)`);
			check(near(m.legacyLhSpan, size), `legacy inner line-height span ${m.legacyLhSpan}px == ${size}px (lh 1)`);
			check(near(m.legacyDfLs, ls, 0.1), `legacy display-font span letter-spacing ${m.legacyDfLs}px == ${ls}px (not 0.01em)`);
			check('400' === m.legacyStrongW, `legacy <strong> weight ${m.legacyStrongW} == 400 (inherits)`);

			// Narrow scope: other inline sizes untouched; outside headings untouched.
			check(near(m.customSpan, 32), `custom 2rem inline span stays ${m.customSpan}px == 32px`);
			const outsideExpected = Math.min(88, Math.max(44, 0.06 * w)); // h1{ clamp(2.75rem,6vw,5.5rem) }
			check(near(m.outside.fontSize, outsideExpected), `outside h1 ${m.outside.fontSize}px == ${outsideExpected}px (not hero ${size}px)`);
			check(near(m.outside.letterSpacing, m.outside.fontSize * 0.01, 0.1), `outside h1 letter-spacing ${m.outside.letterSpacing}px == 0.01em`);

			if ([375, 768, 1440].includes(w)) {
				await page.screenshot({ path: path.join(OUT, `hero-fixtures-${w}.png`), fullPage: true });
			}
		}
	} finally {
		await browser.close();
	}
	say(failures ? `RESULT: ${failures} failure(s)` : 'RESULT: all hero-typography checks passed.');
	logStream.end();
	process.exit(failures ? 1 : 0);
})().catch((e) => { say(`ERROR ${e.stack || e}`); process.exit(1); });
