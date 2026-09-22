import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { ZipArchive } from 'archiver';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const themeDir = path.resolve(__dirname, '..');
const themeName = path.basename(themeDir);
const styleCSS = fs.readFileSync(path.join(themeDir, 'style.css'), 'utf8');
const versionMatch = styleCSS.match(/^[\s*]*Version:\s*(.+)$/m);

if (!versionMatch) {
    console.error('Could not find Version in style.css');
    process.exit(1);
}

const version = versionMatch[1].trim();
const outputName = `${themeName}-${version}.zip`;
const outputPath = path.join(themeDir, '..', outputName);

const output = fs.createWriteStream(outputPath);
const archive = new ZipArchive({ zlib: { level: 9 } });

output.on('close', () => {
    console.log(`Created: ${outputName} (${archive.pointer()} bytes)`);
});

archive.on('error', (err) => {
    console.error('Archive error:', err);
    process.exit(1);
});

archive.pipe(output);

archive.glob('**/*', {
    cwd: themeDir,
    ignore: [
        'node_modules/**',
        'package-lock.json',
        'scripts/**',
        'hot',
    ],
    // Vite writes its production manifest into assets/dist/.vite/manifest.json,
    // which inc/assets.php reads at runtime — must be included in the zip.
    dot: true,
}, { prefix: themeName });

archive.finalize();
