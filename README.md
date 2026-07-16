# CRM Inertia.js Project

A modern CRM application built with Laravel, Vue.js 3, and Inertia.js, using pnpm for package management.

## Tech Stack

- **Backend**: Laravel 12
- **Frontend**: Vue.js 3 with Composition API
- **Full-stack framework**: Inertia.js
- **Build tool**: Vite
- **Styling**: Tailwind CSS
- **Package Manager**: pnpm

## Features

- Seamless SPA experience with server-side routing
- Vue 3 Composition API for modern component development
- Tailwind CSS for rapid UI development
- Hot module replacement with Vite
- Efficient dependency management with pnpm

## Prerequisites

- PHP 8.4+
- Node.js 18+
- pnpm 10+
- Composer
- SQLite (default) or MySQL/PostgreSQL

### To run the project use:
| Step                                       | Command                                                                       |
|--------------------------------------------|------------------------------------------------------------------------------|
| Install dependencies                       | `composer install`                                                           |
| Create environment file                    | `cp .env.example .env`                                                       |
| Generate app key                           | `php artisan key:generate`                                                   |
| Start containers locally                   | `./vendor/bin/sail -f docker-compose.yml -f compose.local.yml up -d` |
| Start containers with traefik              | `./vendor/bin/sail -f docker-compose.yml -f compose.traefik.yml up -d` |
| Migrate database                           | `./vendor/bin/sail artisan migrate`                                          |
| Seed database with initial data            | `./vendor/bin/sail artisan migrate:fresh --seed`                             |
| Install JS dependencies (in container)     | `./vendor/bin/sail pnpm install`                                             |
| Start Vite dev server                      | `./vendor/bin/sail composer run dev`                                         |


you can merge compose files by using     multiple -f flags, for example:
    ```bash
    docker compose -f docker-compose.yml -f compose.local.yml up -d
    ```

    Choose specific compose files to merge depending on your    enviroment.


## Credentials

### Admin Access
- **Email**: `spoluprace@fondly.cz`
- **Password**: `admin`

> **Note**: The application is protected by authentication. Only public links for calculations with a valid access token are accessible without logging in.


## Development

### Quick Start (Both servers)
Run both Laravel and Vite development servers concurrently:
```bash
pnpm run dev:concurrent
```

### Individual Servers

Start the Laravel development server:
```bash
php artisan serve
```

Start the Vite development server (in a new terminal):
```bash
pnpm run dev
```

### Building for Production

Build assets for production:
```bash
pnpm run build
```

## Deployment (Portainer + Traefik)

Production runs from **`compose.prod.yml`** + **`docker/prod/Dockerfile`**. The image bakes in
everything (composer without dev dependencies, built Vite assets) and runs under supervisord —
web (`artisan serve`), queue worker and scheduler. It does not depend on `vendor/` being committed,
so Portainer can build it straight from GitHub.

The stack is two services: the app (behind Traefik) and MariaDB (internal network only).

**Steps:**

1. Generate the app key locally:
   ```bash
   php artisan key:generate --show
   ```
2. Create a **GitHub personal access token** (read-only, access to the `fondly-cz` org). `composer.json`
   depends on the private repo `fondly-cz/ares-connector`, so the build fails with a 404 without it.
   Thanks to the multi-stage build the token stays in the intermediate stage and is **not** present in
   the final image (verify with `docker history`).
3. In Portainer: **Stacks → Add stack → Repository**
   - Repository URL: this GitHub repo
   - Reference: `refs/heads/main`
   - **Compose path:** `compose.prod.yml`
   - Enable **Automatic updates** (webhook) if you want a redeploy on push.
4. Under **Environment variables** fill in the values from
   [`.env.production.example`](.env.production.example) — required: `APP_KEY`, `DOMAIN_NAME`, `DB_*`,
   `GITHUB_TOKEN`, `NETWORK_NAME`, `CERT_RESOLVER`; then SMTP, Google OAuth and the first admin as needed.
5. **Deploy the stack.** Portainer builds the image, starts MariaDB, waits for it, then runs
   `migrate --force`, seeds the service catalog (first deploy only), generates the Passport keys,
   creates the admin, runs `optimize` and starts the app.

**First admin:** set `ADMIN_EMAIL` + `ADMIN_PASSWORD` (and optionally `ADMIN_NAME`). The user is only
created if that e-mail does not exist yet, so it is safe to leave the variables in place. Note that
`php artisan db:seed` (the full `DatabaseSeeder`) **cannot** run in production — it uses factories,
i.e. `fakerphp/faker`, which is a dev dependency and is not in the image. Only `ServiceSeeder` (the
service catalog, run once on an empty database) is used.

> Migrations run on every container start. TLS is handled by Traefik (`CERT_RESOLVER`). Persistent
> data lives in two volumes: `mojimili-db` (database) and `mojimili-storage` (uploads, logs and the
> Passport OAuth keys — these **must** survive deploys, otherwise every connected MCP client is
> disconnected).

Local smoke test of the production image:

```bash
docker build -f docker/prod/Dockerfile \
  --secret id=composer_auth,src=$HOME/.composer/auth.json \
  -t mojimili-crm:test .
```

(`docker-compose.yml` + `compose.local.yml` are the Sail-based setup used for local development.)

## MCP Server (Claude Desktop)

The CRM exposes an [MCP](https://modelcontextprotocol.io) server at `POST /mcp`, so Claude can browse
the service catalog and build customer calculations for you. It is protected by OAuth 2.1 (Laravel
Passport): you connect with your own CRM account and Claude gets exactly the permissions that account
has in the UI — service management stays admin-only, and users without a role are rejected.

### Connecting

1. In Claude Desktop go to **Settings → Connectors → Add custom connector**.
2. Enter the URL `https://<your-domain>/mcp` (locally `http://localhost:8000/mcp`).
3. Claude registers itself, sends you to the CRM login and shows an approval screen. Approve it, and
   the tools become available in your conversations.

Access tokens are valid for 30 days, refresh tokens for 60.

### Available tools

| Tool | Who can use it | What it does |
| --- | --- | --- |
| `list-services` | any CRM role | Service catalog with IDs, prices and payment periods |
| `create-service` | admin | Adds a service to the catalog |
| `update-service` | admin | Updates an existing service |
| `list-companies` | any CRM role | Companies and their contacts (for `company_id`) |
| `list-calculations` | any CRM role | Lists calculations |
| `get-calculation` | any CRM role | Calculation detail including items |
| `create-calculation` | any CRM role | Creates a calculation and returns the public customer URL |

### Using it

Just ask in plain language — Claude looks up the service IDs itself and never invents them:

> Add a "Webhosting" service, category Hosting, cost 100 CZK, margin 30 %, billed monthly.

> Create a calculation for Jan Novák (jan@example.com, +420 777 123 456): a custom website with
> webhosting nested underneath it, and send me the public link.

Items can be nested (give an item a `key` and set the child's `parent_key` to it). Anything you leave
out — price, days, payment period, description — falls back to the service catalog.

### Deployment

The [Portainer + Traefik stack](#deployment-portainer--traefik) handles this for you: it runs
`php artisan migrate` on every start and `php artisan passport:keys` once, storing the keys on the
`mojimili-storage` volume. The keys are gitignored and **must survive deploys**, otherwise every
connected client is disconnected. `APP_URL` must be the production HTTPS domain (`compose.prod.yml`
derives it from `DOMAIN_NAME`), as it doubles as the OAuth issuer.

See [docs/mcp.md](docs/mcp.md) for the full details.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
