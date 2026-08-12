import { dynamicTranslationFamilies } from './dynamicTranslationFamilies';

export interface TranslationUsage {
    key: string;
    source: string;
}

const directCallPattern = /\b(?:t|translate)\(\s*['"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)['"]/g;
const keyPropertyPattern =
    /\b(?:label|title|description|message|body|subject|heading|action|confirm|key)Key\s*:\s*['"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)['"]/g;
const dynamicKeyPattern = /`((?:pages|navigation|actions|auth|breadcrumbs|datatable|filters)\.[^`]*\$\{[^`]+)`/g;
const dynamicKeyPropertyPattern = /\b(?:title|description|confirm|message|label)Key\s*:\s*`([^`]*\$\{[^`]+)`/g;
const dynamicDerivedKeyPattern = /`(\$\{[^}]+}\.(?:confirm|description|title))`/g;

function matches(source: string, pattern: RegExp): string[] {
    return [...source.matchAll(pattern)].map((match) => match[1] ?? '');
}

export function inventoryFrontendTranslationUsage(files: Record<string, string>): TranslationUsage[] {
    const usages: TranslationUsage[] = [];
    const registeredExpressions = new Set(dynamicTranslationFamilies.map(({ expression }) => expression));
    const encounteredExpressions = new Set<string>();

    for (const [source, contents] of Object.entries(files)) {
        for (const key of [...matches(contents, directCallPattern), ...matches(contents, keyPropertyPattern)]) {
            usages.push({ key, source });
        }

        for (const expression of [
            ...matches(contents, dynamicKeyPattern),
            ...matches(contents, dynamicKeyPropertyPattern),
            ...matches(contents, dynamicDerivedKeyPattern),
        ]) {
            encounteredExpressions.add(expression);

            if (!registeredExpressions.has(expression)) {
                usages.push({ key: `[unregistered-dynamic:${expression}]`, source });
            }
        }
    }

    for (const { expression, keys } of dynamicTranslationFamilies) {
        if (!encounteredExpressions.has(expression)) {
            continue;
        }

        for (const key of keys) {
            usages.push({ key, source: 'dynamicTranslationFamilies.ts' });
        }
    }

    return usages.sort((left, right) => `${left.key}:${left.source}`.localeCompare(`${right.key}:${right.source}`));
}

export function catalogProblems(usages: readonly TranslationUsage[], catalogs: Record<string, Record<string, string>>): string[] {
    const problems: string[] = [];

    for (const usage of usages) {
        if (usage.key.startsWith('[unregistered-dynamic:')) {
            problems.push(`${usage.source} uses ${usage.key}`);
            continue;
        }

        for (const [locale, catalog] of Object.entries(catalogs)) {
            const value = catalog[usage.key];

            if (typeof value !== 'string' || value.trim() === '' || value === usage.key) {
                problems.push(`${usage.source} uses missing ${locale} key [${usage.key}]`);
            }
        }
    }

    return [...new Set(problems)].sort();
}
