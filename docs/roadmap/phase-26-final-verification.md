## Phase 26 — Final foundation verification

### Implementation contract

- Final verification is not a superficial test pass. It must prove that the Atlas can be cloned as a stable corporate base.
- Cross-check every accepted decision against `AGENTS.md`, this file, documentation, ADRs, and tests.
- No accepted behavior may exist only in historical chat.
- Verify module activation, dependency blocking, ineffective permissions, role template behavior, admin mode, impersonation, manager hierarchy, TimeTracking isolation, reports, exports, light/dark themes, translations, backup/restore, deploy/rollback, liveness/readiness, and security controls.
- Review starter cloning and namespace/application identity replacement.
- Tag a stable release only after complete verification.
- `PRODUCTION_DEPLOYED=true` is set only in a Atlas after its first actual production deployment, not merely when the Atlas is released.

- The final repository context uses `AGENTS.md`, `WORKROAD.md`, the project-owner `CHATGPT_PROMPT.md`, and the canonical linked documentation under `docs/`.
- Working-only files such as temporary discussion notes, continuation prompts, and review drafts are not part of the final package.
- Before final delivery, ensure every accepted rule, implementation contract, task, module description, architectural decision, and operational procedure exists in its canonical root or `docs/` location.
- A fresh session must be able to resume by reading the root entry files and only the relevant linked documentation.

- [ ] Run complete backend test suite.
- [ ] Run complete frontend test suite.
- [ ] Run Playwright in Chromium.
- [ ] Run Playwright in Firefox.
- [ ] Run production frontend build.
- [ ] Run PHPStan/Larastan at maximum configured level.
- [ ] Run dependency vulnerability checks.
- [ ] Verify all enabled modules and reduced modes.
- [ ] Verify light and dark themes.
- [ ] Verify UI translation completeness.
- [ ] Verify admin panel.
- [ ] Verify impersonation.
- [ ] Verify module activation.
- [ ] Verify backup.
- [ ] Verify restore.
- [ ] Verify deploy.
- [ ] Verify rollback.
- [ ] Verify readiness and liveness.
- [ ] Review documentation completeness.
- [ ] Review ADR completeness.
- [ ] Review `CHATGPT_PROMPT.md`.
- [ ] Cross-check all accepted decisions against `AGENTS.md`, `WORKROAD.md`, and `OPEN_DISCUSSION.md`.
- [ ] Verify that no accepted rule exists only in historical chat context.
- [ ] Review starter cloning procedure.
- [ ] Mark Atlas stable.
- [ ] Tag the first stable Atlas release.
- [ ] Set `PRODUCTION_DEPLOYED=true` only after the first actual production deployment of a Atlas.
- [ ] Verify the final package contains `AGENTS.md`, the lightweight `WORKROAD.md`, `CHATGPT_PROMPT.md`, and all canonical linked documentation under `docs/`.
- [ ] Verify no accepted decision exists only in a working/context file.
- [ ] Verify a fresh session can resume from the root entry files plus only the relevant linked documentation.
- [ ] Exclude all working-only discussion, continuation, and review files from the final delivery package.
