import { cp, lstat, mkdir, readdir, rm } from 'node:fs/promises';
import { dirname, extname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const publicRoot = join(projectRoot, 'public');
const outputRoot = join(projectRoot, 'dist');

await rm(outputRoot, { recursive: true, force: true });
await mkdir(outputRoot, { recursive: true });
await copyPublicFiles(publicRoot, outputRoot);

async function copyPublicFiles(sourceDirectory, destinationDirectory) {
    const entries = await readdir(sourceDirectory, { withFileTypes: true });

    for (const entry of entries) {
        if (entry.name.startsWith('.') || entry.name === 'forge' || entry.name === 'storage') {
            continue;
        }

        const sourcePath = join(sourceDirectory, entry.name);
        const destinationPath = join(destinationDirectory, entry.name);
        const sourceStats = await lstat(sourcePath);

        if (sourceStats.isSymbolicLink()) {
            continue;
        }

        if (sourceStats.isDirectory()) {
            await mkdir(destinationPath, { recursive: true });
            await copyPublicFiles(sourcePath, destinationPath);

            continue;
        }

        if (sourceStats.isFile() && extname(entry.name).toLowerCase() !== '.php') {
            await cp(sourcePath, destinationPath);
        }
    }
}
