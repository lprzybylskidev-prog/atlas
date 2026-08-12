import { describe, expect, it } from 'vitest';

import { dynamicTranslationFamilies } from './dynamicTranslationFamilies';
import { catalogProblems, inventoryFrontendTranslationUsage } from './translationUsageInventory';
import { statusCatalog } from '../Services/statusCatalog';

const pageFiles = import.meta.glob('../Pages/**/*.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;
const frontendFiles = import.meta.glob('../**/*.{ts,vue}', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;
const translationFiles = import.meta.glob('../../../lang/*.json', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;
const catalogs = {
    pl: JSON.parse(translationFiles['../../../lang/pl.json']) as Record<string, string>,
    en: JSON.parse(translationFiles['../../../lang/en.json']) as Record<string, string>,
};

function pageSource(path: string): string {
    const source = Object.entries(pageFiles).find(([file]) => file.endsWith(`/Pages/${path}`))?.[1];

    expect(source, `${path} must exist in the rendered page inventory`).toBeTypeOf('string');

    return source ?? '';
}

describe('rendered PL/EN view copy audit', () => {
    const pages = Object.entries(pageFiles);

    it('audits every Inertia Vue page and every statically rendered translation key in both locales', () => {
        expect(pages.length).toBeGreaterThanOrEqual(60);

        let auditedKeys = 0;

        for (const [page, source] of pages) {
            const keys = inventoryFrontendTranslationUsage({ [page]: source }).filter(
                ({ key }) => !key.startsWith('[unregistered-dynamic:'),
            );

            expect(keys.length, `${page} has no auditable rendered translation key`).toBeGreaterThan(0);

            for (const { key } of keys) {
                auditedKeys += 1;

                for (const [locale, catalog] of Object.entries(catalogs)) {
                    expect(catalog[key], `${page} renders missing ${locale} key [${key}]`).toBeTypeOf('string');
                    expect(catalog[key]?.trim(), `${page} renders blank ${locale} key [${key}]`).not.toBe('');
                    expect(catalog[key], `${page} exposes untranslated key [${key}] in ${locale}`).not.toBe(key);
                }
            }
        }

        expect(auditedKeys).toBeGreaterThanOrEqual(500);
    });

    it('audits statically rendered translation keys across the production frontend', () => {
        const productionFiles = Object.entries(frontendFiles).filter(([file]) => !file.endsWith('.test.ts'));
        const usages = inventoryFrontendTranslationUsage(Object.fromEntries(productionFiles));

        expect(productionFiles.length).toBeGreaterThanOrEqual(180);
        expect(usages.length).toBeGreaterThanOrEqual(2_300);
        expect(catalogProblems(usages, catalogs)).toEqual([]);
    });

    it('keeps every dynamic translation expression finite, unique, and exercised by production source', () => {
        const productionSource = Object.entries(frontendFiles)
            .filter(([file]) => !file.endsWith('.test.ts'))
            .map(([, source]) => source)
            .join('\n');
        const expressions = dynamicTranslationFamilies.map(({ expression }) => expression);

        expect(new Set(expressions).size).toBe(expressions.length);

        for (const { expression, keys } of dynamicTranslationFamilies) {
            expect(keys.length, `${expression} must enumerate at least one accepted key`).toBeGreaterThan(0);
            expect(new Set(keys).size, `${expression} must not register duplicate keys`).toBe(keys.length);
            expect(productionSource, `${expression} must correspond to a production dynamic lookup`).toContain(`\`${expression}\``);
        }
    });

    it('binds the dynamic DataTable status family to the accepted status catalog', () => {
        const family = dynamicTranslationFamilies.find(({ expression }) => expression === 'datatable.status.${token}');

        expect(family).toBeDefined();
        expect([...(family?.keys ?? [])].sort()).toEqual(
            Object.values(statusCatalog)
                .map(({ key }) => key)
                .sort(),
        );
    });

    it('proves the usage guard fails when a used translation is removed from either locale', () => {
        const usages = inventoryFrontendTranslationUsage(
            Object.fromEntries(Object.entries(frontendFiles).filter(([file]) => !file.endsWith('.test.ts'))),
        );
        const usedKey = 'actions.cancel';

        for (const locale of ['pl', 'en'] as const) {
            const mutated = {
                pl: { ...catalogs.pl },
                en: { ...catalogs.en },
            };
            delete mutated[locale][usedKey];

            expect(catalogProblems(usages, mutated)).toContainEqual(expect.stringContaining(`missing ${locale} key [${usedKey}]`));
        }
    });

    it('keeps known defective phrases and raw product-internal terms out of rendered catalogs', () => {
        const allCopy = Object.values(catalogs).flatMap((catalog) => Object.values(catalog));
        const forbidden = [
            'Pierwsze hasło oczekuje',
            'MFA niepotwierdzone',
            'Wszystkie liczby',
            'drabinka',
            'Próba usunięcia roli została wykonana',
            'Obniżona sprawność',
            'Dry-run',
            'Hard delete',
            'TimeTracking',
        ];

        for (const phrase of forbidden) {
            expect(
                allCopy.some((copy) => copy.toLocaleLowerCase().includes(phrase.toLocaleLowerCase())),
                `legacy rendered copy remains: ${phrase}`,
            ).toBe(false);
        }
    });

    it('keeps regular-user and manager dashboards structurally empty', () => {
        for (const path of ['Dashboard.vue', 'Manager/Panel.vue']) {
            const source = pageSource(path);
            const template = source.match(/<template>([\s\S]*?)<\/template>/)?.[1] ?? '';

            expect(template).toContain('<Head');
            expect(template).toMatch(/<AppLayout[\s\S]*?\/>/);
            expect(template).not.toMatch(/<(SurfaceCard|MetricGrid|NoticeBanner|DataTable|EmptyState)\b/);
        }
    });

    it('names edited objects and never substitutes a raw module key for the module label', () => {
        for (const path of [
            'Admin/Users/Edit.vue',
            'Admin/Teams/Edit.vue',
            'Admin/Authorization/Roles/Edit.vue',
            'Admin/Authorization/Packages/Edit.vue',
        ]) {
            const source = pageSource(path);

            expect(source, `${path} must derive its title from the edited object`).toContain('const pageTitle = computed');
            expect(source).toContain('<Head :title="pageTitle" />');
            expect(source).toMatch(/<AppLayout[^>]*:title="pageTitle"/);
        }

        const moduleDetails = pageSource('Admin/Modules/Show.vue');

        expect(moduleDetails).toContain('const moduleDisplayName = computed');
        expect(moduleDetails).not.toContain(':title="module.moduleKey"');
        expect(moduleDetails).not.toContain('{ module: module.moduleKey }');
    });
});
