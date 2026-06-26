# ocer-dns

`ocer-dns` is the implementation workspace derived from `ai-doc-v1/START.md`.

This repository contains exactly **three** target packages
(`dns-console-web` was merged into `portal-web` on 2026-06-15):

- `portal-web`   — Laravel + Vue 3. Member control plane, admin / node /
  publish / geo-DNS / rule library / system config / audit logs, and the
  internal in-process services that `geodns` and `dns-resolver` call.
- `dns-resolver` — Go. DNS protocol ingress, rule engine, cache, log
  buffer, heartbeat, config pull from `portal-web`.
- `geodns`       — Go. Authoritative DNS, region / weight routing,
  health view pulled from `portal-web`.

Current status:

- Documentation is sufficient to start development.
- The generated content in this directory is an `L2` to early `L3` scaffold.
- It is not a production-ready delivery.

## Source of truth

All product, API, schema, migration, and delivery constraints come from
the sibling `ai-doc-v1/` tree:

- `../ai-doc-v1/START.md`
- `../ai-doc-v1/project-doc/*`
- `../ai-doc-v1/specs/*`
- `../ai-doc-v1/contracts/*`
- `../ai-doc-v1/migrations/*`

## Workspace layout

```text
ocer-dns/
├── portal-web/    (member + admin SPA, agent / internal / admin APIs)
├── dns-resolver/
└── geodns/
```

## Important assumptions

- `portal-web` is scaffolded as a Laravel + Vue 3 project; framework
  dependencies are not installed in this workspace.
- `dns-resolver` and `geodns` are scaffolded as Go services with
  compilable entrypoints and internal package layout.
- Shared contracts and migrations are referenced from
  `../ai-doc-v1/contracts/` and `../ai-doc-v1/migrations/`.

## Known document inconsistencies resolved here

- `START.md` mentions `project-doc/05-delivery-criteria.md`, but the actual file is `project-doc/08-DELIVERY-CRITERIA.md`.
- `START.md` mentions `project-doc/06-CLOSED-LOOP-AND-DATA-DESTINATIONS.md`, but the actual file is `project-doc/09-CLOSED-LOOP-AND-DATA-DESTINATIONS.md`.
- `START.md` mentions `project-doc/07-NEXTDNS-LITE-BILLING.md` and `08-MEMBER-CENTER-V1.md`, while the actual files are `10-NEXTDNS-LITE-BILLING.md` and `11-MEMBER-CENTER-V1.md`.

## Next implementation order

1. Install PHP and Node dependencies for `portal-web`.
2. Implement database models, migrations, and API controllers against
   `../ai-doc-v1/contracts/openapi.yaml`.
3. Complete resolver rule engine, config pull, heartbeat, and query log
   batch flow.
4. Complete GeoDNS health view pull and routing logic.

