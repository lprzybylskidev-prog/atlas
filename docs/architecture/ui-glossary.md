# Atlas UI glossary

This document is the binding Polish/English naming contract for Atlas product surfaces. The executable catalog is `App\Shared\Presentation\Localization\AtlasUiGlossary`; Laravel JSON translation files remain the source of rendered copy, and glossary binding tests keep menus, breadcrumbs, page titles, forms, messages, mail, documentation, and frontend views aligned.

Technical internal names remain English identifiers in code, routes, schemas, APIs, audit metadata, and operational diagnostics. They must not be humanized into invented labels. A missing user-facing label is a translation defect; Admin may show the explicit technical identifier only when it is operationally necessary and clearly identified as technical.

## Canonical concepts

| Technical internal name | Polish: singular / plural | Polish: menu / page / form | English: singular / plural | English: menu / page / form |
| --- | --- | --- | --- | --- |
| `dashboard` | Pulpit / Pulpity | Pulpit / Pulpit aplikacji / Pulpit | Dashboard / Dashboards | Dashboard / Application dashboard / Dashboard |
| `user` | Użytkownik / Użytkownicy | Użytkownicy / Użytkownicy / Użytkownik | User / Users | Users / Users / User |
| `team` | Zespół / Zespoły | Zespoły / Zespoły / Zespół | Team / Teams | Teams / Teams / Team |
| `role` | Rola / Role | Role / Role / Rola | Role / Roles | Roles / Roles / Role |
| `onboarding_package` | Szablon uprawnień / Szablony uprawnień | Szablony uprawnień / Szablony uprawnień / Szablon uprawnień | Authorization preset / Authorization presets | Authorization presets / Authorization presets / Authorization preset |
| `permission` | Uprawnienie / Uprawnienia | Uprawnienia / Uprawnienia / Uprawnienie | Permission / Permissions | Permissions / Permissions / Permission |
| `direct_permission` | Uprawnienie bezpośrednie / Uprawnienia bezpośrednie | Uprawnienia bezpośrednie / Uprawnienia bezpośrednie / Uprawnienie bezpośrednie | Direct permission / Direct permissions | Direct permissions / Direct permissions / Direct permission |
| `email_address` | Adres e-mail / Adresy e-mail | Adresy e-mail / Adresy e-mail / Adres e-mail | Email address / Email addresses | Email addresses / Email addresses / Email address |
| `manager` | Manager / Managerowie | Managerowie / Managerowie / Manager | Manager / Managers | Managers / Managers / Manager |
| `status` | Status / Statusy | Statusy / Statusy / Status | Status / Statuses | Statuses / Statuses / Status |
| `activation` | Aktywacja / Aktywacje | Aktywacja / Aktywacja / Aktywacja | Activation / Activations | Activation / Activation / Activation |
| `public_id` | Identyfikator publiczny / Identyfikatory publiczne | Identyfikatory publiczne / Identyfikatory publiczne / Identyfikator publiczny | Public identifier / Public identifiers | Public identifiers / Public identifiers / Public identifier |
| `time_tracking` | Ewidencja czasu pracy / Ewidencje czasu pracy | Czas pracy / Czas pracy / Ewidencja czasu pracy | Work time record / Work time records | Work time / Work time / Work time records |
| `managed_process` | Proces zarządzany / Procesy zarządzane | Procesy zarządzane / Procesy zarządzane / Proces zarządzany | Managed process / Managed processes | Managed processes / Managed processes / Managed process |
| `authorization_reason` | Powód zmiany uprawnień / Powody zmian uprawnień | Powody zmian uprawnień / Powody zmian uprawnień / Powód zmiany uprawnień | Authorization change reason / Authorization change reasons | Authorization change reasons / Authorization change reasons / Authorization change reason |

`Panel użytkownika`, `Panel managera`, and `Panel administratora` name shell contexts. `Pulpit` names a dashboard page. These terms are not interchangeable.

## Canonical action verbs

| Technical action | Polish | English |
| --- | --- | --- |
| `create` | Utwórz | Create |
| `edit` | Edytuj | Edit |
| `save` | Zapisz | Save |
| `apply` | Zastosuj | Apply |
| `activate` | Aktywuj | Activate |
| `deactivate` | Dezaktywuj | Deactivate |
| `delete` | Usuń | Delete |

Object-page actions include the object label in the page and browser title, for example `Edytuj użytkownika: Anna Kowalska` / `Edit user: Anna Kowalska`. Destructive variants such as permanent deletion are named by their actual effect and do not reuse ordinary delete or deactivate copy.

## Canonical status labels

| Technical status | Polish | English |
| --- | --- | --- |
| `active` | Aktywny | Active |
| `inactive` | Nieaktywny | Inactive |
| `enabled` | Włączony | Enabled |
| `disabled` | Wyłączony | Disabled |
| `degraded` | Ograniczona dostępność | Degraded |

Status labels describe state; action verbs describe a requested transition. Do not use `Aktywuj` as a state or `Aktywny` as an action. Boolean values use the shared yes/no presentation only when the field is genuinely boolean and a more precise status is not available.

## Copy rules

- Polish regular-user and manager surfaces use natural Polish, including `Brak połączenia`, `Tryb podglądu`, and `Nieodwracalne usunięcie`; raw product/internal terms such as `Offline`, `Feed`, `Dry-run`, `Hard delete`, and `TimeTracking` are forbidden there.
- `Adres e-mail` is the product label. Technical names such as `email`, database fields, and external protocol terminology remain internal.
- Unknown enum/module tokens are never title-cased or split into plausible copy. Atlas renders a translated unknown-value label and, only on an operational Admin surface, may append the original technical token.
- The regular-user dashboard and manager dashboard intentionally render only their shell context. Empty-state cards, quick links, artificial metrics, and placeholder explanations require a separate accepted product decision.
- Every static translation key rendered by an Inertia Vue page must exist and be non-blank in both `lang/pl.json` and `lang/en.json`. The rendered-copy audit is intentionally non-vacuous and covers the complete page inventory.
