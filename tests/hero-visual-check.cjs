/**
 * RCMI hero typography — live-page visual check.
 *
 * Loads the local dev site (default http://localhost:8000, override with
 * BASE=...) in real Chromium, waits for document.fonts.ready, then
 * measures the actual hero heading's computed typography against the UH
 * display scale (48px <768 / 72px 768-991.99 / 100px >=992, lh 1,
 * ls -0.015625em, League Gothic 400 uppercase) and captures screenshots.
 *
 * If the local page has no hero heading, the frozen production preset
 * markup is injected into the page's .rcmi-parallax-copy/.hero-copy
 * (DOM only — nothing is written to the database).
 *
 * Any inline style on the hero h1 or its descendants that is NOT part of
 * the known saved preset (font-family / color / background-color on the
 * mark, line-height 0.8, font-size 3.75rem) is reported — never
 * overridden.
 *
 * Requires the `puppeteer` npm package:
 *   NODE_PATH=/path/to/node_modules node tests/hero-visual-check.cjs
 *
 * Artifacts go to /tmp/rcmi-hero-typography/ (live-*.png + log).
 * Exits non-zero on typography assertion failures.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const BASE = process.env.BASE || 'http://localhost:8000';
const OUT = '/tmp/rcmi-hero-typography';
fs.mkdirSync(OUT, { recursive: true });
const logStream = fs.createWriteStream(path.join(OUT, 'hero-visual-check.log'));
const say = (m) => { process.stdout.write(m + '\n'); logStream.write(m + '\n'); };

// Frozen production markup — injected only if the page has no hero h1.
const LEGACY = `<h1 class="wp-block-heading"><strong><span class="has-inline-font-family has-display-font" style="font-family: 'League Gothic', 'Arial Narrow', sans-serif;"><mark class="has-inline-color"><span class="has-inline-line-height" style="line-height: 0.8"><span class="has-inline-font-size" style="font-size: 3.75rem">Community-Engaged Research for Health Equity</span></span></mark></span></strong></h1>`;

// Inline properties that are expected on the saved Hero preset markup,
// by class. Anything else inside the hero h1 is reported.
const KNOWN = {
	'has-inline-font-family': ['font-family'],
	'has-display-font': ['font-family'],
	'has-inline-color': ['color', 'background-color'],
	'has-inline-highlight': ['background-color'],
	'has-inline-line-height': ['line-height'],
	'has-inline-font-size': ['font-size'],
};

const VIEWPORTS = [
	{ w: 1440, size: 100 },
	{ w: 768, size: 72 },
	{ w: 375, size: 48 },
];

let failures = 0;
function check(cond, msg) {
	if (cond) { say(`PASS ${msg}`); } else { failures++; say(`FAIL ${msg}`); }
}
const near = (a, b, tol = 0.5) => Math.abs(a - b) <= tol;

async function audit(page, width) {
	return page.evaluate((KNOWN) => {
		const h1 = document.querySelector(
			'.rcmi-parallax-copy h1, .hero-copy h1'
		);
		if (!h1) return { missing: true };
		const cs = getComputedStyle(h1);
		const r = {
			fontSize: parseFloat(cs.fontSize),
			letterSpacing: cs.letterSpacing === 'normal' ? 0 : parseFloat(cs.letterSpacing),
			lineHeight: parseFloat(cs.lineHeight),
			fontWeight: cs.fontWeight,
			textTransform: cs.textTransform,
			fontFamily: cs.fontFamily,
			overflow: h1.scrollWidth - h1.clientWidth,
			text: h1.textContent.trim().slice(0, 80),
			nonpreset: [],
			innermost: null,
			fontsReady: [],
		};
		// Enumerate inline styles on the h1 and every descendant.
		[h1, ...h1.querySelectorAll('*')].forEach((el) => {
			const declared = Array.from(el.style || []);
			const known = [];
			el.classList.forEach((c) => { (KNOWN[c] || []).forEach((p) => known.push(p)); });
			declared.forEach((p) => {
				if (!known.includes(p)) {
					r.nonpreset.push(
						`${el.tagName.toLowerCase()}.${el.className} { ${p}: ${el.style.getPropertyValue(p)} }`
					);
				}
			});
		});
		// Deepest descendant holding the visible text.
		let node = h1;
		while (node.children.length) node = node.children[node.children.length - 1];
		const is2 = getComputedStyle(node);
		r.innermost = {
			tag: node.tagName.toLowerCase() + '.' + node.className,
			fontSize: parseFloat(is2.fontSize),
			lineHeight: parseFloat(is2.lineHeight),
			fontWeight: is2.fontWeight,
			fontFamily: is2.fontFamily,
		};
		// Which font faces are actually loaded.
		document.fonts.forEach((f) => {
			if ('loaded' === f.status) r.fontsReady.push(`${f.family} ${f.weight} ${f.status}`);
		});
		r.leagueGothicLoaded = document.fonts.check('1rem "League Gothic"');
		return r;
	}, KNOWN);
}

(async () => {
	const browser = await puppeteer.launch({ headless: 'new' });
	try {
		const page = await browser.newPage();
		for (const { w, size } of VIEWPORTS) {
			await page.setViewport({ width: w, height: 900 });
			await page.goto(BASE + '/', { waitUntil: 'networkidle2', timeout: 30000 });
			await page.evaluate(() => document.fonts.ready);
			// If no hero heading exists, inject the frozen preset markup.
			const injected = await page.evaluate((LEGACY) => {
				if (document.querySelector('.rcmi-parallax-copy h1, .hero-copy h1')) return false;
				const host = document.querySelector('.rcmi-parallax-copy, .hero-copy');
				if (!host) return 'no-host';
				host.insertAdjacentHTML('afterbegin', LEGACY);
				return true;
			}, LEGACY);
			if ('no-host' === injected) { failures++; say('FAIL no hero copy container found to host heading'); continue; }
			const m = await audit(page, w);
			const ls = size * -0.015625;
			say(`--- ${BASE} @ ${w}px (expect ${size}px / ls ${ls}px)${injected ? ' [markup injected]' : ''} ---`);
			say(`     heading text: "${m.text}"`);
			check(near(m.fontSize, size), `h1 font-size ${m.fontSize}px == ${size}px`);
			check(near(m.letterSpacing, ls, 0.1), `h1 letter-spacing ${m.letterSpacing}px == ${ls}px`);
			check(near(m.lineHeight, m.fontSize, 0.5), `h1 line-height ${m.lineHeight} == font-size`);
			check('400' === m.fontWeight, `h1 font-weight ${m.fontWeight} == 400`);
			check('uppercase' === m.textTransform, `h1 text-transform ${m.textTransform} == uppercase`);
			check(/League Gothic/.test(m.fontFamily), `h1 font-family ${m.fontFamily}`);
			check(m.overflow <= 0, `h1 overflow ${m.overflow}px <= 0`);
			if (m.innermost) {
				check(near(m.innermost.fontSize, size), `innermost (${m.innermost.tag}) font-size ${m.innermost.fontSize}px == ${size}px`);
				check(near(m.innermost.lineHeight, size, 1), `innermost line-height ${m.innermost.lineHeight}px ~= ${size}px`);
				check('400' === m.innermost.fontWeight, `innermost font-weight ${m.innermost.fontWeight} == 400`);
			}
			say(`     League Gothic loaded: ${m.leagueGothicLoaded} (${m.fontsReady.length} faces ready)`);
			m.nonpreset.length
				? m.nonpreset.forEach((n) => say(`     NOTE non-preset inline style: ${n}`))
				: say('     no non-preset inline styles inside hero h1');
			const hero = await page.$('.rcmi-parallax, .hero');
			if (hero) {
				const clip = await hero.boundingBox();
				await page.screenshot({ path: path.join(OUT, `live-${w}.png`), clip }).catch(() =>
					page.screenshot({ path: path.join(OUT, `live-${w}.png`) })
				);
			} else {
				await page.screenshot({ path: path.join(OUT, `live-${w}.png`) });
			}
		}
	} finally {
		await browser.close();
	}
	say(failures ? `RESULT: ${failures} failure(s)` : 'RESULT: live hero typography checks passed.');
	logStream.end();
	process.exit(failures ? 1 : 0);
})().catch((e) => { say(`ERROR ${e.stack || e}`); process.exit(1); });
