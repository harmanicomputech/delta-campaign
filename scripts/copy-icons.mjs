// Copies the Lucide icons listed in scripts/icons.json into resources/icons,
// stripping the outer <svg> attributes so <x-icon> controls size and stroke.
import { readFileSync, writeFileSync, readdirSync, unlinkSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'node_modules/lucide-static/icons');
const target = join(root, 'resources/icons');
const names = JSON.parse(readFileSync(join(root, 'scripts/icons.json'), 'utf8'));

mkdirSync(target, { recursive: true });
for (const file of readdirSync(target)) {
    if (file.endsWith('.svg') && !names.includes(file.slice(0, -4))) unlinkSync(join(target, file));
}

let missing = 0;
for (const name of names) {
    let svg;
    try {
        svg = readFileSync(join(source, `${name}.svg`), 'utf8');
    } catch {
        console.error(`Missing Lucide icon: ${name}`);
        missing++;
        continue;
    }
    const inner = svg
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/^[\s\S]*?<svg[^>]*>/, '')
        .replace(/<\/svg>\s*$/, '')
        .replace(/\s*\n\s*/g, '')
        .trim();
    writeFileSync(join(target, `${name}.svg`), inner + '\n');
}

console.log(`Copied ${names.length - missing} icons to resources/icons`);
process.exit(missing ? 1 : 0);
