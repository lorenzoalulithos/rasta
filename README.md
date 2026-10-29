# App Artigiani

Gestionale PWA per artigiani (modulo Pittori in v1): preventivi con calcolo materiali, magazzino, chiusura cantiere.

- `api/` – Laravel 13, PHP 8.5 (DDEV)
- `app/` – Nuxt 4 SPA + Nuxt UI 4
- `docs/` – specifiche: [domain](docs/domain.md), [flows](docs/flows.md), [api](docs/api.md), [stack](docs/stack.md), [roadmap](docs/roadmap.md)

Regole di lavoro: [CLAUDE.md](CLAUDE.md).

## Avvio

### API

```shell
ddev start
ddev composer install
ddev artisan migrate --seed
```

URL: `https://app-artigiani.ddev.site`.

Il progetto usa due database:

* ambiente `local`: `db`
* ambiente `testing`: `db_test` (creato dall'hook `post-start` di DDEV, svuotato da `RefreshDatabase`)

### App

Nuxt gira sul Mac, fuori da DDEV (Node da `app/.nvmrc`):

```shell
cd app
npm install
npm run dev        # http://localhost:3000
```

`NUXT_PUBLIC_API_BASE` sovrascrive l'URL delle API (default `https://app-artigiani.ddev.site/api`).

Non usare `ddev npm`: mutagen sincronizza `node_modules` nel container e rompe i binding nativi sul Mac.

## Controlli prima del commit

```shell
ddev artisan test           # Pest
ddev composer test-style    # phpmd, phpmnd, php-cs-fixer, phpcs, phpstan, phpinsights
cd app && npm run lint
```

Correzione automatica dello stile PHP:

```shell
ddev exec -d /var/www/html/api vendor/bin/php-cs-fixer fix
ddev exec -d /var/www/html/api vendor/bin/phpcbf
```

## Coverage

```shell
ddev xdebug
ddev exec -d /var/www/html/api XDEBUG_MODE=coverage php artisan test --coverage
```

## Code

Di default le code sono sincrone (`QUEUE_CONNECTION=sync`, anche nei test via `phpunit.xml`).
In locale, con `QUEUE_CONNECTION=redis`:

```shell
ddev artisan queue:work
```
