import { readFile, rm } from 'node:fs/promises';
import { resolve } from 'node:path';

const e2eViteUrl = 'http://127.0.0.1:5174';
const hotFile = resolve(process.cwd(), 'public/hot');

export async function removeE2eViteHotFile(): Promise<void> {
    try {
        const contents = (await readFile(hotFile, 'utf8')).trim();

        if (contents === e2eViteUrl) {
            await rm(hotFile, { force: true });
        }
    } catch (error) {
        if (typeof error === 'object' && error !== null && 'code' in error && error.code === 'ENOENT') {
            return;
        }

        throw error;
    }
}
