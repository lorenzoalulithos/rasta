# Stack – App Artigiani – v1

Decisioni tecniche chiuse. Non si cambiano senza aggiornare questo file.

## Repo

Monorepo:

```
app-artigiani/
├── CLAUDE.md
├── docs/               domain.md, flows.md, api.md, stack.md, roadmap.md, seed/
├── api/                Laravel 13
├── app/                Nuxt 4 (SPA + PWA)
└── .ddev/              docroot api/public
```

- Un solo `.gitignore` alla root, uno per sottoprogetto per i loro artefatti.
- Nessun workspace npm condiviso: `api/` e `app/` hanno i propri `composer.json` / `package.json`. I tipi condivisi si generano, non si condividono a mano (vedi sotto).

## Backend – `api/`

- **Laravel 13**, PHP 8.5, MySQL 8 (DDEV).
- **Auth**: Laravel Sanctum, token API (non cookie SPA): la PWA salva il token e lo manda come Bearer. Un solo guard.
- **Multi-tenant**: colonna `tenant_id` su tutte le tabelle di dominio + global scope `BelongsToTenant` applicato dal tenant dell'utente autenticato. Nessun package di tenancy.
- **ID**: UUID v7 (`HasUuids`) su tutte le entità esposte via API.
- **Struttura**: standard Laravel, niente DDD/moduli. Logica di dominio in `app/Services` (es. `PainterCalculator`, `QuoteNumbering`, `StockService`), controller sottili, Form Request per la validazione, API Resource per l'output.
- **PDF**: `barryvdh/laravel-dompdf`, template Blade in `resources/views/pdf/quote.blade.php`. CSS inline, niente font esterni (DejaVu Sans). Generazione sincrona.
- **Calcolatore**: `App\Services\Calculator\PainterCalculator` implementa `CalculatorInterface`. Il tenant ha un campo `trade` (default `painter`) che seleziona l'implementazione. È l'unico punto "a moduli" del codice.
- **Seed**: `php artisan db:seed --class=PainterCatalogSeeder` legge `docs/seed/*.json` e popola catalogo del tenant. Usato anche dall'endpoint "carica esempio" del primo avvio.
- **Test**: Pest 5 (su PHPUnit 13), `pestphp/pest-plugin-laravel`. Nei test usare le funzioni `Pest\Laravel\get()` & co. invece di `$this->get()` (PHPStan non conosce `$this` nelle closure Pest); per lo stesso motivo niente `$this->x` in `beforeEach`: usare helper come `signInTenant()` in `tests/helpers.php`. Obbligatori per calcolatore, numerazione, transizioni di stato, impegnato/disponibile. Feature test sugli endpoint principali.
- **Formatting e analisi statica**: `composer test-style` (phpmd, phpmnd, php-cs-fixer, phpcs, phpstan livello 8, phpinsights), standard del template Lithos. Deve essere verde prima di ogni commit. Pint resta installato ma non è il riferimento.
- **Money**: decimali `DECIMAL(10,2)` nel DB, calcoli con `brick/money` o `bcmath`, mai float sui totali.

## Frontend – `app/`

- **Nuxt 4** in modalità SPA: `ssr: false`. Deploy come statico (`nuxt generate`). Node 24 (`.nvmrc`).
- **Nuxt UI 4** (incluso Tailwind 4). Nessun'altra UI library. Icone via `@nuxt/icon` con set Lucide.
- **PWA**: `@vite-pwa/nuxt`. Manifest, `display: standalone`, workbox con `NetworkFirst` per `/api/*` e `CacheFirst` per asset.
- **State**: Pinia solo per auth e per il preventivo in editing. Il resto è fetch diretto con `useFetch`/`$fetch` via un composable `useApi()` che aggiunge base URL e Bearer.
- **Form**: `UForm` di Nuxt UI con schema **Zod**.
- **Tipi**: i tipi delle risorse API vivono in `app/types/api.ts`, scritti a mano a partire da `docs/api.md`. Si aggiornano insieme alle API Resource lato Laravel. (Se diventa pesante: generazione da OpenAPI, non ora.)
- **Routing**: file-based, le route di `docs/flows.md`. Layout `default` con bottom bar su mobile e sidebar da `lg:` in su.
- **i18n**: no. UI in italiano hardcoded. I nomi di campo nel codice restano in inglese.
- **Test**: Vitest solo per composable e utility di calcolo (se si replica il calcolatore client-side per l'anteprima live). Niente e2e in v1.
- **Lint**: `@nuxt/eslint`, config di default.

## Ambiente locale

- **DDEV** per API: progetto `app-artigiani`, tipo `laravel`, docroot `api/public`, MySQL 8, PHP 8.5. URL `https://app-artigiani.ddev.site`.
- Nuxt gira fuori da DDEV con `npm run dev` su `http://localhost:3000`. `NUXT_PUBLIC_API_BASE=https://app-artigiani.ddev.site/api`.
- CORS lato Laravel: `config/cors.php` con `allowed_origins` = origin del Nuxt dev e del dominio di produzione.

## Convenzioni di codice

- Entità, campi, route, metodi in **inglese**. Etichette UI, messaggi, PDF in **italiano**.
- Enum PHP (`enum QuoteStatus: string`) per tutti gli stati e le categorie; mirror in `app/types/api.ts`.
- Date in API: ISO 8601. Importi: numero decimale (non stringa), 2 decimali.
- Risposte API: `{ data: ... }` per singolo e lista, `{ data, meta }` con paginazione. Errori di validazione: standard Laravel 422.
- Commit: Conventional Commits (`feat:`, `fix:`, `chore:`), un commit per task della roadmap.

## CI

GitHub Actions (`.github/workflows/`), su PR e push su `main`:

- `tests.yml`: job `api` (Pest su PHP 8.5 + MySQL 8, in `api/`) e job `app` (lint + `nuxt generate` su Node da `app/.nvmrc`, in `app/`).
- `style-checks.yml`: `composer test-style`.
- `security-checks.yml`: `composer audit` e `npm audit --audit-level=high`.

## Produzione (quando serve, non ora)

VPS Hetzner o Laravel Forge per l'API; Nuxt statico su Netlify/Cloudflare Pages o servito dallo stesso nginx. HTTPS obbligatorio (PWA). Backup DB giornaliero. Si decide alla fine della v1.
