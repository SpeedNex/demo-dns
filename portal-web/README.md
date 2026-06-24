# portal-web

Laravel + Vue 3 package for:

- authentication
- member center
- profile CRUD and publish
- whitelist and blacklist management
- subscription and billing presentation
- query log and statistics display
- wallet recharge, order, checkout and invoice flow
- admin audit, menu config and member catalog management

## Package layout

- `app/`, `routes/`, `database/`: Laravel API and domain services
- `web/`: Vue 3 + Vite frontend source
- `dist/`: frontend production build output

## Local development

- API: `php artisan serve --host=0.0.0.0 --port=8080`
- Frontend: `npm run dev`
- Frontend build: `npm run build`
- API regression: `php artisan test --filter=ApiTest`
- Workspace regression: `php artisan test --filter=MemberWorkspaceTest`

## Closed Loop

- DNS query logs are ingested into `query_log_entries` and now auto-register member devices by `device_id` or source IP.
- Usage data can be aggregated into billing records, then linked to orders, payment transactions, wallet balance changes, invoices, and publish snapshots.
- Member center supports profile management, device management, wallet recharge, and DNS endpoint delivery from the same API surface.
- Admin console supports menu configuration, member catalog configuration, audit logs, plans, billing, and publish task operations.
- Control-plane to resolver integration: `heartbeat -> should_pull_config -> GET /config (Global Config) -> GET /profiles/{id} (Lazy Profile) -> local cache hot reload`.
- DoH integration is verified against the live local resolver on `http://127.0.0.1:18444/dns-query`: the node matched `127.0.0.1 -> dev-localhost -> profile`, applied version `2`, and returned a valid DNS answer for `example.com`.

## Integration Evidence

- `php artisan test --filter=ApiTest`
- `php artisan test --filter=MemberWorkspaceTest`
- `npm run build`
- `go test ./...`
- `go vet ./...`
- resolver smoke link:
  `portal-web(127.0.0.1:8081) -> geodns(127.0.0.1:5354) -> dns-resolver(127.0.0.1:18444/15355)`

## Planned modules

- `app/Domain/Auth`
- `app/Domain/Profile`
- `app/Domain/Rule`
- `app/Domain/Device`
- `app/Domain/Plan`
- `app/Domain/Billing`
- `app/Domain/Usage`
- `app/Domain/Audit`
- `app/Infrastructure/DnsConsole`

## First APIs to implement

- `POST /api/v1/public/auth/register`
- `POST /api/v1/public/auth/login`
- `GET /api/v1/member/me`
- `GET|POST /api/v1/member/profiles`
- `PUT|DELETE /api/v1/member/profiles/{profile_id}`
- `GET|POST /api/v1/member/profiles/{profile_id}/rules`
- `POST /api/v1/member/profiles/{profile_id}/publish`
- `GET /api/v1/member/profiles/{profile_id}/logs`
