const TEAM_SCOPED_PREFIXES = ['atlas.team.', 'atlas.tables.team.'];

export function clearTeamScopedState(): void {
    clearStorage(localStorage);
    clearStorage(sessionStorage);
}

function clearStorage(storage: Storage): void {
    for (let index = storage.length - 1; index >= 0; index -= 1) {
        const key = storage.key(index);

        if (key !== null && TEAM_SCOPED_PREFIXES.some((prefix) => key.startsWith(prefix))) {
            storage.removeItem(key);
        }
    }
}
