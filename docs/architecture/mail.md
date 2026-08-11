# Mail architecture

Atlas-owned e-mail has one delivery contract for account, notification, module, and operational paths.

## Shared bilingual contract

Every Atlas-owned e-mail renders through `resources/views/mail/atlas-bilingual.blade.php` and the shared branded Laravel Markdown layout. The message contains Polish and English sections with the same heading/body/action structure. Markdown produces both HTML and plain-text MIME parts.

User-facing copy is stored under stable `mail.*` Laravel translation keys in `lang/pl.json` and `lang/en.json`. Runtime notification values may supply localized operator-authored text, but application source must not embed mail subjects, headings, body copy, actions, greetings, or salutations. `Mail::raw`, `Mail::html`, parallel mail templates, and one-language Atlas mail are forbidden by the mail architecture test.

Secure account links may carry the framework's signed or one-time credential in the action URL. Atlas never prints credentials, generated passwords, reset tokens, internal database identifiers, exception messages, request payloads, or diagnostic context as mail body copy. Operational alert e-mail gives safe translated guidance and directs operators to protected diagnostics; the separately configured webhook retains its sanitized operational payload.

## Effective locale order

`MailLocaleSelector` deterministically selects the first section and subject language:

1. the recipient's stored `user.ui.locale` setting;
2. for team-scoped mail, the stored `team.default_locale` only when the user has no stored locale;
3. `app.locale`;
4. `app.fallback_locale` as a technical fallback;
5. Polish only as the defensive final value when both configuration values are unsupported.

The other supported language is always second. Queued notification delivery passes the recipient and notification-team internal identifiers only to this selector; those identifiers are never rendered.

## Delivery paths

The shared contract covers account e-mail verification, first-password setup, password reset, suspicious-login account lock, additional notification-address verification, queued notification e-mail (including Exports, ManagedProcesses, and TimeTracking producers), and operational alerts. Notification e-mail still requires a verified address and an enabled per-address, per-team notification-type preference.

New Atlas mail must reuse `BilingualMailContent`, `AtlasBilingualMailFactory`, and either the shared `AtlasBilingualMail` mailable or its `MailMessage` output. Add both translations and PL-first/EN-first rendering coverage with the new path.
