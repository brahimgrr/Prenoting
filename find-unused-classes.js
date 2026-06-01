import fs from 'fs';
import { glob } from 'glob';

const htmlFiles = await glob('**/*.blade.php', { ignore: ['node_modules/**', 'vendor/**'] });
const cssFiles  = await glob('**/*.css',        { ignore: ['node_modules/**', 'vendor/**'] });

const extractClasses = (files) =>
  new Set(
    files.map(f => fs.readFileSync(f, 'utf8')).join('\n')
      .match(/\.(-?[a-zA-Z_][\w-]*)/g)
      ?.map(c => c.slice(1)) ?? []
  );

const bootstrapFiles = ['node_modules/bootstrap/dist/css/bootstrap.min.css'];
const projectFiles   = cssFiles.filter(f => !f.includes('bootstrap'));
const knownClasses   = new Set([...extractClasses(bootstrapFiles), ...extractClasses(projectFiles)]);

let totalRemoved = 0;

for (const file of htmlFiles) {
  let content = fs.readFileSync(file, 'utf8');
  let fileChanged = false;

  content = content.replace(/class="([^"]+)"/g, (attr, classStr) => {
    if (classStr.includes('{{') || classStr.includes('<?') || classStr.includes('@')) {
      return attr; // leave dynamic classes untouched
    }

    const original = classStr.split(/\s+/).filter(Boolean);
    const filtered = original.filter(cls => knownClasses.has(cls));
    const removed  = original.filter(cls => !knownClasses.has(cls));

    if (removed.length > 0) {
      console.log(`[${file}] removed: ${removed.join(', ')}`);
      totalRemoved += removed.length;
      fileChanged = true;
    }

    return filtered.length > 0 ? `class="${filtered.join(' ')}"` : '';
  });

  if (fileChanged) {
    fs.writeFileSync(file, content, 'utf8');
    console.log(`  ✅ saved\n`);
  }
}

console.log(`\nDone. ${totalRemoved} unused class(es) removed.`);