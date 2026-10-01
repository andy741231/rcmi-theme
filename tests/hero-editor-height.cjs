/**
 * RCMI hero/parallax — live block-editor height & scroll check.
 *
 * Logs in to a LOCAL loopback dev site only — BASE must be
 * http(s)://localhost / 127.0.0.1 / [::1] (default http://localhost:8000)
 * and credentials must be supplied via WP_USER + WP_PASS env vars.
 * Opens a page in the block editor and inside the canvas iframe verifies:
 *   - the hero section's border-box height equals height% of the iframe
 *     viewport (actual vh, not a min-height floor),
 *   - .rcmi-parallax-inner is capped to the section and scrolls
 *     internally when content exceeds it (scrollHeight > clientHeight),
 *   - the first heading is reachable at scrollTop 0 and the last control
 *     is reachable after scrolling to the bottom,
 *   - layer previews stay clipped to the section box.
 *
 * READ-ONLY: no post content or settings are saved (login itself writes
 * only the usual WP session metadata).
 *
 * Requires the `puppeteer` npm package:
 *   NODE_PATH=/path/to/node_modules WP_USER=... WP_PASS=... node tests/hero-editor-height.cjs
 *   (env: BASE loopback-only, POST_ID default 20, WP_USER, WP_PASS)
 *
 * Artifacts (log + screenshots) go to /tmp/rcmi-hero-height/.
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

const BASE = process.env.BASE || 'http://localhost:8000';
const POST_ID = process.env.POST_ID || 20;
const WP_USER = process.env.WP_USER || '';
const WP_PASS = process.env.WP_PASS || '';

// Loopback-only: this script logs in to a live WP admin — never point it
// at a shared or remote site.
const baseUrl = new URL(BASE);
if (
	!/^https?:$/.test(baseUrl.protocol) ||
	!/^(localhost|127\.0\.0\.1|\[::1\])$/i.test(baseUrl.hostname)
) {
	console.error(`REFUSING: BASE must be a loopback URL (localhost/127.0.0.1/[::1]), got ${BASE}`);
	process.exit(1);
}
if (!WP_USER || !WP_PASS) {
	console.error('REFUSING: set WP_USER and WP_PASS env vars (see AGENTS.md for the local dev account).');
	process.exit(1);
}
const OUT = '/tmp/rcmi-hero-height';
fs.mkdirSync(OUT, { recursive: true });
const logStream = fs.createWriteStream(path.join(OUT, 'hero-editor-height.log'));
const say = (m) => { process.stdout.write(m + '\n'); logStream.write(m + '\n'); };

let failures = 0;
function check(cond, msg) {
	if (cond) { say(`PASS ${msg}`); } else { failures++; say(`FAIL ${msg}`); }
}

(async () => {
	const browser = await puppeteer.launch({ headless: 'new' });
	try {
		const page = await browser.newPage();
		await page.setViewport({ width: 1440, height: 900 });

		// --- login -------------------------------------------------------
		await page.goto(BASE + '/wp-login.php', { waitUntil: 'networkidle2', timeout: 30000 });
		await page.type('#user_login', WP_USER);
		await page.type('#user_pass', WP_PASS);
		await Promise.all([
			page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
			page.click('#wp-submit'),
		]);
		const loggedIn = await page.evaluate(() => document.body.classList.contains('logged-in') || !!document.getElementById('wpadminbar'));
		check(loggedIn, 'wp-admin login succeeded');
		if (!loggedIn) throw new Error('login failed');

		// --- open editor --------------------------------------------------
		await page.goto(`${BASE}/wp-admin/post.php?post=${POST_ID}&action=edit`, {
			waitUntil: 'networkidle2', timeout: 60000,
		});
		// Dismiss the welcome guide / any modal if present.
		await new Promise((r) => setTimeout(r, 2500));
		await page.evaluate(() => {
			const btn = document.querySelector('.components-modal__header .components-button[aria-label="Close"], .edit-site-welcome-guide button[aria-label="Close"], .edit-post-welcome-guide button[aria-label="Close"]');
			if (btn) btn.click();
		});

		// Wait for the iframed canvas.
		let canvas = null;
		for (let i = 0; i < 40 && !canvas; i++) {
			canvas = page.frames().find((f) => 'editor-canvas' === f.name() || /editor-canvas/i.test(f.name() || ''));
			if (!canvas) await new Promise((r) => setTimeout(r, 500));
		}
		check(!!canvas, 'editor canvas iframe found');
		if (!canvas) throw new Error('no editor-canvas frame');

		const frame = await Promise.resolve(canvas);
		await frame.waitForSelector('.rcmi-parallax-editor', { timeout: 30000 });
		await frame.evaluate(() => document.fonts.ready);
		await new Promise((r) => setTimeout(r, 800));

		// --- measure inside the iframe ------------------------------------
		const m = await frame.evaluate(() => {
			const sec = document.querySelector('.rcmi-parallax-editor');
			const inner = sec && sec.querySelector('.rcmi-parallax-inner');
			const r = {
				iframeH: window.innerHeight,
				iframeW: window.innerWidth,
			};
			if (!sec) return { missing: true };
			const sr = sec.getBoundingClientRect();
			// The inline `height` property directly — not the attribute text,
			// which also contains min-height.
			r.section = {
				height: sr.height,
				inlineVh: /vh$/i.test(sec.style.height || '') ? parseFloat(sec.style.height) : null,
				style: sec.getAttribute('style'),
				scrollW: sec.scrollWidth,
				clientW: sec.clientWidth,
			};
			if (inner) {
				r.inner = {
					scrollH: inner.scrollHeight,
					clientH: inner.clientHeight,
					overflowY: getComputedStyle(inner).overflowY,
					maxHeight: getComputedStyle(inner).maxHeight,
				};
				// First text block reachable at scrollTop = 0?
				inner.scrollTop = 0;
				const h1 = sec.querySelector('h1, .wp-block-heading');
				if (h1) {
					const ir = inner.getBoundingClientRect();
					const hr = h1.getBoundingClientRect();
					r.h1AtTop = { topInView: hr.top >= ir.top - 1 && hr.bottom <= ir.bottom + 1, hTop: hr.top, innerTop: ir.top };
				}
				// Last content control reachable at the bottom? Take the
				// LAST matching interactive element in document order — the
				// first match would say nothing about reachability.
				inner.scrollTop = inner.scrollHeight;
				const controls = inner.querySelectorAll('.wp-block-button__link, a.btn, button, a');
				const last = controls[controls.length - 1];
				if (last) {
					const ir = inner.getBoundingClientRect();
					const lr = last.getBoundingClientRect();
					r.lastControl = {
						fullyInView: lr.top >= ir.top - 1 && lr.bottom <= ir.bottom + 1,
						tag: last.tagName.toLowerCase(),
						text: (last.textContent || '').trim().slice(0, 40),
					};
				}
				inner.scrollTop = 0;
			}
			// Image layers are intentionally oversized for parallax travel —
			// paint clip comes from the section's overflow:hidden. Record each
			// layer's overshoot and hit-test just outside the section box to
			// confirm nothing paints beyond it.
			r.sectionOverflow = getComputedStyle(sec).overflow;
			r.layers = Array.from(sec.querySelectorAll('.rcmi-parallax-layer-preview, .rcmi-parallax-layer')).map((l) => {
				const lr = l.getBoundingClientRect();
				const abs = 'absolute' === getComputedStyle(l).position;
				const overshoot = {
					top: Math.round(sr.top - lr.top),
					bottom: Math.round(lr.bottom - sr.bottom),
					left: Math.round(sr.left - lr.left),
					right: Math.round(lr.right - sr.right),
				};
				// A point just outside the section must not hit-test to the
				// layer (only meaningful when the point is in the viewport).
				let paintsOutside = null;
				const px = Math.min(sr.left + sr.width / 2, window.innerWidth - 1);
				const py = sr.bottom + 5;
				if (py < window.innerHeight && overshoot.bottom > 1) {
					const hit = document.elementFromPoint(px, py);
					paintsOutside = hit && (hit === l || l.contains(hit));
				}
				return { absolute: abs, overshoot, paintsOutside };
			});
			// Canvas must not gain a real horizontal scrollbar: oversized
			// layers count in the section's scrollWidth despite the clip.
			r.pageScrollW = document.documentElement.scrollWidth;
			return r;
		});

		say(`iframe viewport ${m.iframeW}x${m.iframeH}`);
		if (m.missing) {
			failures++;
			say('FAIL no .rcmi-parallax-editor section in editor canvas');
		} else {
			say(`section inline style: ${m.section.style}`);
			check(Number.isFinite(m.section.inlineVh), `inline height is a vh value (${m.section.style})`);
			const expected = (m.section.inlineVh || 0) / 100 * m.iframeH;
			check(Math.abs(m.section.height - expected) <= 1.5, `section height ${m.section.height}px == ${m.section.inlineVh}% of iframe viewport ${expected.toFixed(1)}px`);
			check(/min-height:\s*[\d.]+vh/i.test(m.section.style || ''), 'inline style also carries min-height');
			check(m.pageScrollW <= m.iframeW, `no real horizontal page overflow (body scrollWidth ${m.pageScrollW} <= iframe ${m.iframeW})`);
			if (m.inner) {
				check(m.inner.overflowY === 'auto', `inner overflow-y ${m.inner.overflowY} == auto`);
				check(m.inner.maxHeight !== 'none', `inner max-height ${m.inner.maxHeight} constrained`);
				check(m.inner.clientH <= m.section.height + 1, `inner clientH ${m.inner.clientH} <= section ${m.section.height}`);
				say(`     inner scrollHeight ${m.inner.scrollH} vs clientHeight ${m.inner.clientH}`);
				check(m.inner.scrollH > m.inner.clientH, 'inner content exceeds cap -> scrollable');
			} else {
				failures++;
				say('FAIL no .rcmi-parallax-inner found');
			}
			if (m.h1AtTop) check(m.h1AtTop.topInView, `heading reachable at scrollTop=0 (h1 top ${m.h1AtTop.hTop} vs inner ${m.h1AtTop.innerTop})`);
			if (m.lastControl) check(m.lastControl.fullyInView, `last control "${m.lastControl.text}" (${m.lastControl.tag}) fully in view after scrolling`);
			check(/hidden/.test(m.sectionOverflow || ''), `section overflow ${m.sectionOverflow} clips paint`);
			m.layers.forEach((l, i) => {
				check(l.absolute, `layer ${i} absolutely positioned inside section (clip by overflow:hidden)`);
				say(`     layer ${i} overshoot t${l.overshoot.top}/b${l.overshoot.bottom}/l${l.overshoot.left}/r${l.overshoot.right}px`);
				if (false === l.paintsOutside) check(true, `layer ${i} does not paint below the section edge`);
				else if (true === l.paintsOutside) check(false, `layer ${i} PAINTS outside the section`);
			});
		}

		// Selection inside the nested scroller still works: a real click on
		// the hero heading should select it (parent block gets
		// has-child-selected; clicking again selects the parent itself).
		const h1El = await frame.$('.rcmi-parallax-editor h1, .rcmi-parallax-editor .wp-block-heading');
		if (h1El) await h1El.click();
		await new Promise((r) => setTimeout(r, 800));
		const sel = await frame.evaluate(() => {
			const sec = document.querySelector('.rcmi-parallax-editor');
			return !!(sec && (sec.classList.contains('is-selected') || sec.classList.contains('has-child-selected')));
		});
		check(sel, 'block selection inside scrollable inner works');

		// Screenshots: top of section, and inner scrolled to bottom.
		await page.screenshot({ path: path.join(OUT, 'editor-hero-top.png'), fullPage: false });
		await frame.evaluate(() => {
			const inner = document.querySelector('.rcmi-parallax-editor .rcmi-parallax-inner');
			if (inner) inner.scrollTop = inner.scrollHeight;
		});
		await new Promise((r) => setTimeout(r, 300));
		await page.screenshot({ path: path.join(OUT, 'editor-hero-scrolled.png'), fullPage: false });
		say('screenshots: editor-hero-top.png, editor-hero-scrolled.png');
	} finally {
		await browser.close();
	}
	say(failures ? `RESULT: ${failures} failure(s)` : 'RESULT: live editor height/scroll checks passed.');
	logStream.end();
	process.exit(failures ? 1 : 0);
})().catch((e) => { say(`ERROR ${e.stack || e}`); process.exit(1); });
