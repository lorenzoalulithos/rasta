# Roadmap v1

Un task = una sessione di Claude Code = un commit (o pochi). Ogni task ha una "definizione di fatto" verificabile. Non iniziare un task se il precedente non è verde.

Prefisso: **A** = api, **F** = app (frontend), **M** = manuale (Lollo).

## Fase 0 – Scheletro (manuale)

- [x] **M0.1** Monorepo, `api/` con Laravel 13 fresh, `app/` con Nuxt 4 fresh + Nuxt UI 4, DDEV configurato, `docs/` con questi file, `CLAUDE.md`.
- [x] **M0.2** `GET /api/ping` da Laravel e una pagina Nuxt che lo chiama e stampa la risposta. CORS ok. Primo commit.

## Fase 1 – Fondamenta API

- [x] **A1.1** Migration + model di tutte le entità di `domain.md §3` (incluse quelle v1.1 come sole migration). UUID v7, `tenant_id`, enum PHP. Fatto quando: `php artisan migrate:fresh` pulito, model con relazioni e cast.
- [ ] **A1.2** Auth Sanctum: login/logout/me, global scope `BelongsToTenant`, command `app:create-tenant` che crea tenant + utente. Fatto quando: feature test login → me → logout.
- [ ] **A1.3** Seeder `PainterCatalogSeeder` da `docs/seed/*.json` + endpoint `POST /tenant/seed-catalog`. Fatto quando: dopo il seed, `GET /jobs` restituisce 7 lavorazioni attive con i materiali suggeriti e `GET /materials` 72 materiali con stock a zero.
- [ ] **A1.4** CRUD clienti e cantieri, tenant GET/PATCH. Resource, Form Request, test.
- [ ] **A1.5** CRUD lavorazioni (con `materials[]` inline e reorder) e materiali (con creazione Stock).

## Fase 2 – Calcolatore e preventivi

- [ ] **A2.1** `PainterCalculator` + `CalculatorInterface`. Solo servizio, nessun endpoint. Fatto quando: test Pest che riproducono `domain.md §7.3` (181 mq, 2 mani, resa avg → 3 bidoni, 143,07 €) e le varianti `min`/`max`/`fractional`, `per_unit`, `apply_coats = false`.
- [ ] **A2.2** `QuoteNumbering` (progressivo per anno, lock transazionale) + POST/GET/PATCH/DELETE quotes. Test: due creazioni concorrenti non collidono.
- [ ] **A2.3** Righe: POST/PATCH/DELETE/reorder con calcolo e snapshot. `preview-calculate` e `calculate`. Test sui totali del preventivo Belissi (974,00 €).
- [ ] **A2.4** Transizioni di stato (`/transition`) con le regole di `domain.md §6`, duplicate. Test di tutte le transizioni ammesse e di 3 vietate.
- [ ] **A2.5** PDF con DomPDF: template che riproduce la struttura del preventivo Belissi (intestazione, destinatario/destinazione, righe, totali, footer, scadenza). Fatto quando: il PDF del preventivo di test si apre e i numeri coincidono.

## Fase 3 – Magazzino

- [ ] **A3.1** `StockService`: on_hand/committed/available/to_buy, `GET /stock`, adjust, movimenti. Test: preventivo `accepted` impegna, `rejected`/`completed` no.
- [ ] **A3.2** Acquisti: POST/GET/PATCH con movimenti e aggiornamento prezzi opzionale.
- [ ] **A3.3** Chiusura cantiere: `GET /completion` + `POST /complete`. Test: la giacenza scende, l'impegnato sparisce.
- [ ] **A3.4** `GET /dashboard`.

## Fase 4 – PWA: shell e preventivo

- [ ] **F4.1** Shell: layout con bottom bar / sidebar, pagina login, `useApi()`, store auth con token persistente, guard sulle route. PWA manifest + service worker. Fatto quando: si installa su Android e dopo riapertura è ancora loggato.
- [ ] **F4.2** Tipi in `types/api.ts` da `api.md`. Composable `useQuotes`, `useCustomers`, `useCatalog`, `useStock` (solo fetch, niente logica).
- [ ] **F4.3** Lista preventivi + nuovo preventivo step 1 (cliente/cantiere con creazione inline). `flows.md §2.1–2.2`.
- [ ] **F4.4** Dettaglio preventivo: righe, picker lavorazione con "+ Nuova lavorazione" e riga libera, footer con totali e costo/margine. `flows.md §2.3`.
- [ ] **F4.5** Bottom sheet riga: quantità, mani, prezzo, materiali con calcolo live via `preview-calculate` (debounce 300 ms). `flows.md §2.4`.
- [ ] **F4.6** Anteprima PDF, condivisione (Web Share API con fallback download), transizioni di stato e toast. `flows.md §2.5–2.7`.

**Checkpoint:** qui il socio può fare un preventivo vero dal telefono. Prima demo, prima raccolta di feedback. Fermarsi e correggere prima di continuare.

## Fase 5 – PWA: resto

- [ ] **F5.1** Catalogo lavorazioni (form completo con materiali suggeriti e checklist) e materiali. `flows.md §4`.
- [ ] **F5.2** Magazzino: lista con i tre numeri e colori, dettaglio con rettifica, filtro da comprare. `flows.md §3.1–3.2`.
- [ ] **F5.3** Acquisti: registrazione e lista da pagare. `flows.md §3.3–3.4`.
- [ ] **F5.4** Chiusura cantiere. `flows.md §6`.
- [ ] **F5.5** Clienti, impostazioni, home con i tre blocchi, stati vuoti e primo avvio con seed. `flows.md §1, §5, §7, §8`.
- [ ] **F5.6** Layout desktop: due colonne su preventivo, griglia su home, sidebar. Passata di rifinitura su tutte le pagine a 1280px.

## Fase 6 – Rilascio

- [ ] **M6.1** Deploy API + app statica, HTTPS, backup DB. Tenant reale del socio creato, catalogo seedato, prezzi controllati da lui.
- [ ] **M6.2** Il socio usa l'app per 2 settimane. Raccolta feedback. Decisione su v1.1 (calendario, DDT).

## Note per Claude Code

- Leggere `docs/domain.md` e il paragrafo di `docs/flows.md` o `docs/api.md` citato dal task prima di scrivere codice.
- Un task alla volta. Se un task richiede di cambiare un doc, cambiare prima il doc e dirlo.
- Non anticipare task futuri "perché tanto servono dopo".
- Non toccare le entità v1.1 (CalendarEvent, DeliveryNote) oltre alle migration.
