import sharp from 'sharp';
import { readFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const publicDir = join(__dirname, '..', 'public');
const imagesDir = join(publicDir, 'images');

const svgBuffer = readFileSync(join(imagesDir, 'quantock-van-tech-logo.svg'));

const sizes = [
    { name: 'pwa-192x192.png', size: 192 },
    { name: 'pwa-512x512.png', size: 512 },
    { name: 'pwa-1024x1024.png', size: 1024 },
    { name: 'apple-touch-icon.png', size: 180 },
];

async function generate() {
    for (const { name, size } of sizes) {
        await sharp(svgBuffer)
            .resize(size, size)
            .png()
            .toFile(join(imagesDir, name));
        console.log(`Generated ${name} (${size}x${size})`);
    }
    console.log('Done!');
}

generate().catch(console.error);
