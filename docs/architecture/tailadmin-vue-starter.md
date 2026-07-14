# TailAdmin Vue Starter

Canonical source checkpoint and usage rules for TailAdmin Vue Starter in Atlas.

## Source

Atlas uses TailAdmin Vue Starter as the approved Free TailAdmin reference for frontend layout and component patterns.

Current reviewed source:

- upstream: `https://github.com/TailAdmin/vue-tailwind-admin-dashboard`;
- commit: `e618c08d7906cd5563a99627d52ab91c6e6f2310`;
- upstream version: `2.3.0`;
- upstream commit date: `2026-04-28`;
- official installation method: clone the upstream repository, then run the frontend package manager install/build commands.

The reviewed upstream template is built with Vue 3, Vite, TypeScript, and Tailwind CSS 4.

## Atlas integration

Atlas does not vendor the upstream TailAdmin Vue Starter source tree.

Atlas already has a Laravel/Inertia/Vue frontend foundation, so importing the full starter would duplicate routing, layout, application bootstrapping, state management, icons, linting, and build configuration.

Use TailAdmin Vue Starter as a pattern reference in this order:

1. reuse existing Atlas shared components;
2. adapt TailAdmin Free layout and component ideas into project-owned Atlas components;
3. use Tailwind utilities;
4. write custom CSS only as a last resort.

Do not copy upstream components verbatim unless the exact asset/license status is verified for the intended Atlas delivery model.

## License boundary

The reviewed upstream snapshot identifies itself as Free and open-source, but the cloned repository does not include a standalone `LICENSE` file.

Because Atlas may be transferred as source, agents must treat this as a source-copying guard:

- TailAdmin Free may guide project-owned implementation patterns;
- TailAdmin Pro assets remain forbidden until the recorded Pro license state is updated;
- upstream source files are not copied into Atlas without an explicit license/source-transfer verification;
- any future copied third-party TailAdmin source must include its source, version, license basis, and redistribution decision in this document or a successor canonical document.
