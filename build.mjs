// build.mjs — CalendiFlow asset pipeline
// Usage: node build.mjs
import { readFileSync, writeFileSync } from 'fs';
import { minify } from 'terser';
import postcss from 'postcss';
import cssnano from 'cssnano';

// ── JS ──────────────────────────────────────────────────────────────────────
const js = readFileSync('assets/js/calendar.js', 'utf8');
const jsResult = await minify(js, { compress: true, mangle: true });
writeFileSync('assets/js/calendar.min.js', jsResult.code);
const jsSave = (((js.length - jsResult.code.length) / js.length) * 100).toFixed(0);
console.log(`✓ calendar.min.js — ${jsResult.code.length} bytes (−${jsSave}%)`);

// ── CSS ─────────────────────────────────────────────────────────────────────
const css = readFileSync('assets/css/calendar.css', 'utf8');
const cssResult = await postcss([cssnano({ preset: 'default' })]).process(css, { from: undefined });
writeFileSync('assets/css/calendar.min.css', cssResult.css);
const cssSave = (((css.length - cssResult.css.length) / css.length) * 100).toFixed(0);
console.log(`✓ calendar.min.css — ${cssResult.css.length} bytes (−${cssSave}%)`);

console.log('\nBuild complete.');
