# Phase 34 — Module-owned routing, navigation, breadcrumbs, and application surfaces

**Status:** `not started`

## Objective

Finish the architectural move from large root-owned route and breadcrumb files toward explicit module-owned route, navigation, and breadcrumb declarations while preserving sensible public URLs and canonical Atlas authorization.

This is code ownership and extension architecture. It does not require an `/app/...` URL prefix. Existing sensible URLs such as `/calendar`, `/user/...`, `/manager/...`, and `/admin/...` remain appropriate unless an independently justified migration is required.

## Dependencies

- [Phase 33 — Foundation extension-point, duplication, and consumer audit](phase-33-foundation-extension-points-and-duplication-audit.md) must be complete.
- [Modular-monolith architecture](../architecture/modular-monolith.md)
- Existing Authorization, ModuleGate, navigation registry, localization, breadcrumbs, frontend shell, and explicit module-provider contracts.

## Related documentation

- Architecture: [Modular-monolith architecture](../architecture/modular-monolith.md)
- Architecture: [Frontend UI](../architecture/frontend-ui.md)
- Modules: [Authorization](../modules/authorization.md)
- Operations: [Testing environment](../operations/testing-environment.md)

## Implementation contract

### Application surfaces

Use the conceptual application surfaces:

- App/User;
- Manager;
- Admin.

A surface is a delivery and navigation context, not an Authorization role. Never authorize through logic equivalent to `role === manager` because a route is in the Manager surface. Authorization remains permission-, use-case-, policy-, Team-context-, and ModuleGate-driven. Roles remain permission bundles.

### Module ownership

Each module owns its applicable web-route declarations. The physical convention must make module and surface ownership obvious, conceptually through files such as `Presentation/Routes/app.php`, `user.php`, `manager.php`, and `admin.php`, or an equivalent explicit structure.

Do not create empty files for unused surfaces. Explicit module providers/registration remain the integration point; do not add directory scanning. Root route files remain only for genuinely root/shared/bootstrap surfaces, and no central god-router may import controllers from unrelated modules.

### Navigation

Preserve one canonical resolved application-navigation model. Module-owned contributions feed that resolver rather than creating page-local or competing registries. Items declare or derive their owning module, surface, route, localized label, canonical icon/order, permission visibility, and ModuleGate/availability behavior. Disabled or inaccessible modules leave no functional navigation entries.

### Breadcrumbs

Breadcrumb declarations live with module route/navigation ownership, not ad hoc in Vue pages or controllers. Dynamic resolution must respect Authorization and ModuleGate, use safe public contracts, avoid exposing inaccessible object names, use localization, and adopt Phase 36 safe references where appropriate after that capability exists.

### Route contracts

Preserve globally unique route names, canonical middleware, authentication, Admin Mode, permission checks, module activation, and existing deep links unless deliberately migrated. Do not change URLs merely to match directories or weaken canonical route/action permissions.

### Architecture guards

Add permanent guards for route ownership, duplicate route names or registrations, module-disabled behavior, forbidden role-name authorization, cross-module controller leaks, breadcrumb/navigation declarations bypassing ownership, and inaccessible dynamic breadcrumb leakage.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P34-W01 — Route, breadcrumb, navigation, and shell inventory

- [ ] Inventory every route, controller owner, middleware stack, route name, breadcrumb, navigation contribution, shell, and application surface.
- [ ] Classify genuinely root/shared/bootstrap declarations separately from module-owned declarations.
- [ ] Record URL compatibility and dynamic breadcrumb privacy risks.

### P34-W02 — Canonical module and surface registration contract

- [ ] Define the explicit module-owned route, navigation, and breadcrumb contribution contracts.
- [ ] Define App/User, Manager, and Admin surface metadata without role-name authorization.
- [ ] Integrate declarations through explicit module providers without scanning magic.
- [ ] Add collision and registration-order validation.

### P34-W03 — Module-owned App/User routes and breadcrumbs

- [ ] Move App/User route and breadcrumb declarations to their owning modules.
- [ ] Preserve natural URLs, route names, middleware, permission checks, ModuleGate behavior, and deep links.
- [ ] Remove migrated declarations from unrelated central files.

### P34-W04 — Module-owned Manager routes and breadcrumbs

- [ ] Move Manager route and breadcrumb declarations to their owning modules.
- [ ] Preserve permission-, policy-, hierarchy-, Team-, and module-aware behavior without role-name checks.
- [ ] Remove migrated declarations from unrelated central files.

### P34-W05 — Module-owned Admin routes and breadcrumbs

- [ ] Move Admin route and breadcrumb declarations to their owning modules.
- [ ] Preserve Admin Mode, high-risk, permission, localization, and ModuleGate contracts.
- [ ] Remove migrated declarations from unrelated central files.

### P34-W06 — Canonical navigation contribution and resolution hardening

- [ ] Migrate module navigation declarations into the canonical resolver.
- [ ] Enforce surface, route, label, icon/order, permission, and ModuleGate metadata.
- [ ] Verify desktop/mobile parity and absence of functional entries for inaccessible modules.

### P34-W07 — Dynamic breadcrumb privacy and safe deep-link behavior

- [ ] Move dynamic breadcrumb resolution behind owner-owned safe public contracts.
- [ ] Collapse inaccessible object data to privacy-safe output and prevent label leakage.
- [ ] Verify localized text-only shell context and backward-compatible deep links.

### P34-W08 — Architecture guards, browser acceptance, documentation, and legacy cleanup

- [ ] Add permanent route, ownership, collision, role-check, ModuleGate, and breadcrumb privacy guards.
- [ ] Add backend and browser coverage for every surface, navigation parity, disabled modules, and deep links.
- [ ] Remove obsolete central god-route and god-breadcrumb declarations.
- [ ] Update canonical architecture, module, frontend, and testing documentation.

## Out of scope

- Adding an `/app` URL prefix solely for physical organization.
- Treating surfaces as roles or weakening permission-driven authorization.
- Directory-scanning registration magic.
- Page-local navigation or breadcrumb registries.
- Rewriting unrelated URLs without an independent reason.

## Completion criteria

- [ ] No known module route remains in a central unrelated god-router.
- [ ] No known module breadcrumb remains in a central unrelated god-breadcrumb file.
- [ ] Navigation resolves centrally while declarations and ownership are module-aware.
- [ ] URLs remain sensible and backward-compatible where appropriate.
- [ ] Authorization behavior is unchanged or stronger.
- [ ] Architecture guards, browser acceptance, and canonical documentation are complete.
- [ ] `WORKROAD.md` status is updated to `complete`.
