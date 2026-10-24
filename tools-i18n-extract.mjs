// Appends __() strings from the React admin (TSX) to the .pot, since wp i18n make-pot does not parse TSX.
import fs from 'fs';
import path from 'path';
const root = 'admin-app/src';
const pot = 'languages/answer-engine-optimization.pot';
const found = new Map();
const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).forEach((e) => {
  const f = path.join(d, e.name);
  if (e.isDirectory()) return walk(f);
  if (!/\.(tsx?|jsx?)$/.test(e.name)) return;
  const src = fs.readFileSync(f, 'utf8');
  const re = /\b__\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*\)/g;
  let m;
  while ((m = re.exec(src))) {
    const str = m[2].replace(/\\(['"\\])/g, '$1');
    const line = src.slice(0, m.index).split('\n').length;
    if (!found.has(str)) found.set(str, []);
    found.get(str).push(`${f}:${line}`);
  }
});
walk(root);
let out = fs.readFileSync(pot, 'utf8');
const existing = new Set([...out.matchAll(/^msgid "((?:\\.|[^"])*)"$/gm)].map((m) => m[1]));
const esc = (s) => s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
let added = 0;
for (const [str, refs] of found) {
  if (existing.has(esc(str))) continue;
  const fmt = /%(\d+\$)?[sd]/.test(str) ? '#, javascript-format\n' : '';
  out += `\n#: ${refs.slice(0, 3).join(' ')}\n${fmt}msgid "${esc(str)}"\nmsgstr ""\n`;
  added++;
}
fs.writeFileSync(pot, out);
console.log(`admin strings: ${found.size}, added: ${added}`);
