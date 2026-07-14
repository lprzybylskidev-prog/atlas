## Phase 2 — Laravel foundation

### Implementation contract

- Use the latest stable Laravel and the latest stable PHP version supported by it at implementation time.
- Beta, RC, preview, nightly, and experimental versions are forbidden unless explicitly approved.
- Verify compatibility, license, maintenance status, and current stable release before adding every dependency.
- PostgreSQL is the only relational database.
- Redis is used for cache, sessions, queues, locks, and rate limiting.
- Laravel Boost is the first development package installed after Laravel itself.
- Fortify provides authentication backend behavior; Inertia/Vue provides the UI.
- Horizon manages Redis queues.
- Scout and Meilisearch are installed, but Meilisearch is used only for justified large full-text search, never as a replacement for ordinary filtering or reporting.
- Use Sentry for application error monitoring.
- Use `spatie/laravel-permission` with teams.
- Use centralized breadcrumbs and Ziggy according to the frontend routing contract.
- Use Pest/PHPUnit and PHPStan/Larastan at the highest practical level.
- Add `declare(strict_types=1);` to all applicable PHP files and generators.
- Technical timestamps may be stored in UTC, while business calendar logic and presentation use the configured application timezone.
- Every request, job, integration, and audit-relevant flow must support a correlation/request ID.
- The frontend and backend share one release/version identity.

- [x] Install latest stable Laravel inside the workspace root.
- [x] Use the latest stable PHP version supported by the selected Laravel release.
- [x] Configure PostgreSQL as the only application database.
- [x] Configure Redis for cache, sessions, queues, locks, and rate limits.
- [x] Install Laravel Boost as the first development package after Laravel exists.
- [ ] Install and configure Fortify.
- [ ] Install and configure Horizon.
- [ ] Install Scout.
- [ ] Install Meilisearch integration.
- [ ] Install Sentry.
- [ ] Install `spatie/laravel-permission` with teams support.
- [ ] Install `diglactic/laravel-breadcrumbs`.
- [ ] Install Ziggy.
- [ ] Install Pest and required Laravel testing support.
- [ ] Install PHPStan and Larastan.
- [x] Configure Pint.
- [x] Add `declare(strict_types=1);` to all applicable PHP files and generation templates.
- [ ] Define immutable Money value object with integer minor units and ISO 4217 currency.
- [ ] Configure default application currency as `PLN` without permitting implicit currency loss.
- [ ] Add tests preventing mixed-currency arithmetic/comparison without explicit conversion.
- [x] Configure centralized application timezone.
- [ ] Configure technical timestamps and presentation timezone rules.
- [ ] Configure base logging with correlation/request IDs.
- [ ] Add application version and release ID foundations.
- [ ] Add startup configuration validation.
- [ ] Commit Laravel foundation.
