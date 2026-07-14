import { removeE2eViteHotFile } from './vite-hot-file';

export default async function globalTeardown(): Promise<void> {
    await removeE2eViteHotFile();
}
