# App Artigiani

Gestionale PWA per artigiani (modulo Pittori in v1): preventivi con calcolo materiali, magazzino, chiusura cantiere.
Monorepo: `api/` Laravel 13, `app/` Nuxt 4 SPA + PWA, `docs/` specifiche.

## Prima di toccare qualsiasi cosa

Leggi, in quest'ordine, e non saltarli anche se il task sembra ovvio:

1. `docs/domain.md` – entità, regole di business, calcolatore. È la fonte di verità: se il codice diverge, il codice è sbagliato.
2. `docs/stack.md` – stack e convenzioni fissate.
3. Il paragrafo di `docs/flows.md` o `docs/api.md` citato dal task.
4. Il task in `docs/roadmap.md`, con la sua "fatto quando".

## Come lavorare

- **Un task della roadmap per sessione.** Fai solo quello. Non anticipare task futuri, non "già che ci sei".
- Prima di scrivere codice, esponi in 5 righe cosa farai e quali file toccherai. Poi procedi.
- Il task è finito quando la sua "fatto quando" è verificata, non quando il codice è scritto. Per l'API: test Pest verdi. Per il FE: la pagina funziona nel browser.
- Se per fare il task devi cambiare una regola in `docs/`, **fermati e dillo**: si aggiorna prima il doc, poi il codice.
- Se qualcosa nei doc è ambiguo, chiedi. Non scegliere in silenzio.
- Un commit per task, Conventional Commits: `feat(api): A2.1 PainterCalculator`.
- Il task è finito quando la sua "fatto quando" è verificata E `composer test-style` + `ddev artisan test` sono verdi. Mai committare con test-style rosso.

## Non-goal (non implementare, non proporre, non "preparare")

- Fatture / fatturazione elettronica
- Calendario e DDT (v1.1: solo le migration esistono)
- Offline-first, sync, Capacitor, app native
- Moduli per mestieri diversi dai pittori
- Sconti su righe o totale
- Storico prezzi
- Multi-utente per tenant, ruoli, permessi
- i18n

## Convenzioni rapide

- Codice (entità, campi, route, metodi) in **inglese**. UI, messaggi, PDF in **italiano**.
- Entità esposte via API: UUID v7, `tenant_id` + global scope `BelongsToTenant`.
- Importi `DECIMAL(10,2)`, mai float nei totali.
- API: `{ data }` / `{ data, meta }`, errori 422 standard, 409 per transizioni vietate.
- Enum PHP per stati e categorie, mirror in `app/types/api.ts`.
- Nuxt: solo Nuxt UI 4, icone Lucide, Zod per i form, `useApi()` per le chiamate. Niente altre librerie UI.
- Mobile-first: ogni schermata si prova a 390px prima che a 1280px.

## Comandi

```bash
# API (dentro DDEV)
ddev artisan migrate:fresh --seed
ddev artisan test
ddev exec vendor/bin/pint

# App
cd app && npm run dev        # http://localhost:3000
cd app && npm run lint
```

API in locale: `https://app-artigiani.ddev.site/api`.

## Dati di verifica

Il preventivo di riferimento è in `docs/domain.md §7.3`: 181 mq imbiancatura a 2 mani, Tempera Wallcream resa 8/10 (media 9), bidone 14 lt a 47,69 € → 40,2 lt → 3 bidoni → 143,07 € di costo su 724,00 € di riga. Totale preventivo 974,00 €. Se un calcolo non torna con questi numeri, è sbagliato il calcolo.
