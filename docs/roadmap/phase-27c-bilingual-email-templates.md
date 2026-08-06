# Phase 27c — Bilingual email templates and notification mail audit

**Status:** `not started`

## Objective

Standardize every Atlas-owned outgoing email so mail content is localized, bilingual, branded consistently, and test-protected before production deployment and final verification.

## Dependencies

- [Phase 6 — Core identity and authentication](phase-06-identity-authentication.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 24a — Core export foundation and Admin data integration](phase-24a-core-export-foundation.md)
- [Phase 27 — Optional TimeTracking module](phase-27-time-tracking.md)
- Module: [Identity, authentication, and sessions](../modules/identity-authentication-and-sessions.md)
- Module: [Notifications](../modules/notifications.md)
- Architecture: [Frontend and shared UI architecture](../architecture/frontend-ui.md)

## Related documentation

- Module: [Identity, authentication, and sessions](../modules/identity-authentication-and-sessions.md)
- Module: [Notifications](../modules/notifications.md)
- Module: [Files](../modules/files.md)
- Module: [Exports](../modules/exports.md)
- Architecture: [Security baseline](../architecture/security-baseline.md)
- Operations: [Deployment and operations documentation](../operations/README.md)

## Implementation contract

- Every Atlas-owned email sent through Laravel notifications, mailables, queued jobs, raw mail callbacks, or password/email verification flows must use Atlas-owned translation keys. Hardcoded user-facing mail subjects, lines, actions, footers, and operational notices are forbidden.
- Atlas emails are bilingual by default:
  - the first section uses the currently effective application locale for the delivery context;
  - the second section contains the same message in the other supported Atlas locale;
  - if the effective locale is `pl`, the Polish section is first and English follows;
  - if the effective locale is `en`, the English section is first and Polish follows.
- The effective mail locale must be explicit and deterministic:
  - authenticated user mail prefers the user's UI locale setting when available;
  - team-scoped mail may fall back to the active or owning team's default locale when the recipient user has no UI locale;
  - guest or routed-address mail falls back to `app.locale`;
  - `app.fallback_locale` is only a technical translation fallback, not the primary business language selector.
- The two language sections must use the same Atlas mail layout and the same message structure. The lower section must not be a raw plain-text dump if the upper section is rendered with the branded template.
- The existing accepted Atlas mail appearance is the canonical mail template for the whole application. After the Phase 27c rebuild, every Atlas-owned email must use it by default.
- A new mail template variant may be introduced only for a real layout need, such as a wider report-oriented email, and must be documented, tested, reusable, and explicitly selected. One-off mail layouts are forbidden.
- The standard mail template must include Atlas branding, a concise automatic-message notice, accessible button/action rendering, readable plain-text fallback content, and no unnecessary internal technical details.
- Security-sensitive emails must keep safe wording:
  - verification links must state exactly what is being verified;
  - first-password setup links must state that Atlas does not send generated passwords;
  - account lockout emails must include a localized lock expiry formatted through the accepted Atlas date/time formatter or a backend equivalent for mail;
  - no email may include secrets, raw tokens, internal IDs, or non-user-facing diagnostics.
- Additional notification-address verification emails must use the same bilingual template and must only be sent to the address being verified.
- Notification delivery emails generated from in-app notifications must support localized notification title/body payloads and render both languages when both localized variants exist. Missing localization for a registered notification type must be detectable in tests.
- Operational alert emails may keep technical incident identifiers when they are needed for operators, but their surrounding subject/body/template copy must follow the bilingual mail contract.
- Mail rendering must be covered by tests for Polish-first and English-first output, including subject, primary section, secondary section, action labels, and footer/notice behavior.
- Add an automated guardrail that scans Atlas-owned mail/notification classes and fails when new user-facing mail text is hardcoded directly in `subject`, `line`, `action`, raw mail body, or equivalent mail-builder calls.
- Update canonical module and operations documentation to describe how to add a new mail type, where translation keys live, and how bilingual ordering is selected.

## Tasks

- [ ] Inventory every outgoing Atlas-owned email path, including identity verification, first-password setup, account lockout, notification-address verification, in-app notification email delivery, report/export mail paths, operational alerts, and any raw `Mail::raw` or `MailMessage` usage.
- [ ] Identify the existing accepted Atlas mail template and make it the shared rendering path for all Atlas-owned emails.
- [ ] Create or extend a shared mail rendering service/template that renders the same message in both supported locales with the effective locale first.
- [ ] Move all hardcoded mail subjects, lines, action labels, notices, and footers into stable PL/EN translation keys.
- [ ] Convert `UserEmailVerificationNotification`, `FirstPasswordSetupNotification`, `AccountLockedNotification`, and notification-address verification to the shared bilingual mail template.
- [ ] Convert notification email delivery and operational alert mail to the bilingual contract without exposing raw implementation details to regular users.
- [ ] Ensure date/time values inside emails are localized and formatted consistently with Atlas formatting rules.
- [ ] Add tests for Polish-first and English-first rendering for each security-sensitive mail type.
- [ ] Add tests for notification email preferences and localized notification payload delivery.
- [ ] Add architecture or unit guardrails preventing hardcoded user-facing mail copy in Atlas-owned mail classes.
- [ ] Update identity/authentication, notifications, files/exports if affected, and operations documentation with the bilingual mail contract and implementation procedure.
- [ ] Update `WORKROAD.md` status when the phase is completed.

## Completion criteria

- [ ] Every Atlas-owned outgoing email path follows the bilingual template contract.
- [ ] Every Atlas-owned outgoing email path uses the canonical shared Atlas mail template or a documented reusable variant.
- [ ] Polish-first and English-first rendering are both covered by automated tests.
- [ ] No Atlas-owned mail class contains hardcoded user-facing copy in mail-builder calls.
- [ ] Documentation explains how to add new email types and translations safely.
- [ ] Relevant quality gates pass.
- [ ] The `WORKROAD.md` status is updated to `done`.
