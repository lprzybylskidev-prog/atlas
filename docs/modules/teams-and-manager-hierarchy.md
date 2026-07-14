# Teams and manager hierarchy

Canonical behavior for teams, assignments, effective dates, manager DAG, direct reports, subtree scope, and manager administration.

## Teams and Managers

A user may belong to multiple teams but has one active team per session.

Manager relationships are team-scoped.

Support:

- multiple direct managers;
- managers supervising managers;
- hierarchical directed acyclic graphs;
- head manager flag per team assignment;
- `valid_from`;
- `valid_to`;
- full history;
- no self-management;
- no cycles.

A normal manager sees direct reports only.

A head manager sees the entire subtree under them, still constrained by permissions.

The Admin panel must support:

- assigning managers;
- ending manager relationships;
- assigning head managers;
- viewing hierarchy trees;
- filtering by team;
- history;
- validity periods;
- cycle validation;
- impact preview;
- mandatory reason;
- audit.

---
