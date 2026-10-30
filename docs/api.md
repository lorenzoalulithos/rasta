# API – App Artigiani – v1

Base: `/api`. Tutte le route tranne `/auth/login` richiedono `Authorization: Bearer <token>`. Tutte le risorse sono scoped sul tenant dell'utente: nessun `tenant_id` nelle richieste.

Risposte: `{ data }` per singolo, `{ data, meta, links }` per liste paginate (paginazione standard Laravel; `?page=`, `?per_page=`, default 25, max 100). Messaggi di errore in italiano. Errori di validazione: 422 standard Laravel. Non autorizzato: 401. Transizione di stato non ammessa: 409 con `{ message }`.

## Auth

| metodo | route | note |
|---|---|---|
| POST | `/auth/login` | `{ email, password, device_name? }` → `{ data: { token, user } }` (user con `tenant`). Credenziali errate: 422 su `email`. Max 5 tentativi al minuto |
| POST | `/auth/logout` | revoca il token corrente → 204 |
| GET | `/auth/me` | `{ data: user }`, con `tenant` annidato |

Il token non scade (la PWA lo tiene finché non si fa logout). Senza token valido: 401 `{ message }`.

## Tenant / impostazioni

| metodo | route | note |
|---|---|---|
| GET | `/tenant` | tutti i campi di `Tenant` |
| PATCH | `/tenant` | aggiornamento parziale |
| POST | `/tenant/seed-catalog` | carica il catalogo del mestiere (`trade` del tenant). 409 se il catalogo non è vuoto |

## Clienti e cantieri

| metodo | route | note |
|---|---|---|
| GET | `/customers` | `?search=`; include `sites_count`, `quotes_count` |
| POST | `/customers` | |
| GET | `/customers/{id}` | include `sites` |
| PATCH | `/customers/{id}` | |
| DELETE | `/customers/{id}` | 409 se ha preventivi |
| GET | `/customers/{id}/sites` | |
| POST | `/customers/{id}/sites` | |
| PATCH | `/sites/{id}` | |
| DELETE | `/sites/{id}` | 409 se ha preventivi |

## Catalogo

| metodo | route | note |
|---|---|---|
| GET | `/jobs` | `?active=1`; include `materials` (JobMaterial con material annidato) |
| POST | `/jobs` | accetta anche `materials[]` inline |
| GET | `/jobs/{id}` | |
| PATCH | `/jobs/{id}` | `materials[]` se presente sostituisce l'intera lista |
| DELETE | `/jobs/{id}` | soft: `active = false` se usata in preventivi (200 con la lavorazione), hard altrimenti (204) |
| POST | `/jobs/reorder` | `{ ids: [] }`: le lavorazioni prendono l'ordine dell'array → 204 |
| GET | `/materials` | `?search=&category=&kind=&archived=1`; include `stock` (on_hand, committed, available). Gli archiviati solo con `archived=1` |
| POST | `/materials` | crea anche lo `Stock` a zero |
| GET | `/materials/{id}` | include `stock`, `commitments[]` (quote, packages), `movements[]` (ultimi 10) |
| PATCH | `/materials/{id}` | |
| DELETE | `/materials/{id}` | 409 se usato in preventivi non chiusi. Se è solo in preventivi chiusi (`completed`, `rejected`) viene **archiviato** e tolto dai suggerimenti delle lavorazioni (200 con il materiale, `archived_at` valorizzato). Mai usato: eliminato (204) |

## Preventivi

| metodo | route | note |
|---|---|---|
| GET | `/quotes` | `?status=&search=`; include `customer`, `site`, totali |
| POST | `/quotes` | `{ site_id }` → crea `draft`, assegna numero, copia vat_rate/footer dal tenant |
| GET | `/quotes/{id}` | include `lines[]` con `materials[]` (QuoteLineMaterial + material), `customer`, `site`, totali, `estimated_cost`, `estimated_margin` |
| PATCH | `/quotes/{id}` | `private_notes`, `payment_terms`, `vat_rate`, `issued_at`. Solo in `draft`/`sent` (tranne `private_notes`, sempre; 409 altrimenti). Su un `sent` ogni modifica oltre alle note lo riporta a `draft`. `issued_at` ricalcola `expires_at`, `vat_rate` ricalcola IVA e totale; numero e anno non cambiano |
| DELETE | `/quotes/{id}` | solo `draft` |
| POST | `/quotes/{id}/duplicate` | nuovo `draft`, nuovo numero, data di oggi, stesso cantiere, IVA, pagamento e testo in calce dell'originale; stesse righe e materiali (ricalcolati con il catalogo attuale). Da qualsiasi stato. 201 con il preventivo |
| POST | `/quotes/{id}/lines` | `{ job_id?, description?, quantity, coats?, unit_price?, materials?: [{ material_id, selected, consumption_mode?, consumption_per_unit?, apply_coats? }] }`. Se `job_id`: precompila da `Job` e crea i `QuoteLineMaterial` dai suggeriti. Per un materiale non suggerito dalla lavorazione ("+ Altro materiale") la regola si passa nell'elemento: default `coverage` se il materiale ha resa, altrimenti `consumption_per_unit` è obbligatorio. Senza `job_id` è una **riga libera**: `description` e `unit_price` obbligatori, `unit?` (default `pc`), nessun materiale (422 se li si manda). `materials[]` **corregge e aggiunge**: un materiale già nella riga cambia solo `selected` (o la regola, se passata), uno nuovo viene aggiunto come `secondary`; per togliere un materiale si manda `selected: false`. I `checklist` e i `none` della lavorazione non entrano nella riga. 201 con `{ data: riga, quote: totali }` |
| PATCH | `/quotes/{id}/lines/{lineId}` | stessi campi; ricalcola. I materiali già nella riga tengono resa e costo dello snapshot (§5.7), cambiano solo quantità, mani e spunte; quelli nuovi prendono il catalogo. Se cambia `job_id` la riga riparte dalla nuova lavorazione. `{ data: riga, quote: totali }` |
| DELETE | `/quotes/{id}/lines/{lineId}` | 204 |
| POST | `/quotes/{id}/lines/reorder` | `{ ids: [] }` → 204 |
| POST | `/quotes/{id}/calculate` | **ricalcola tutto** con catalogo e impostazioni attuali, aggiorna snapshot: rese e `cost_price` correnti; per i materiali ancora suggeriti dalla lavorazione riallinea anche la regola (ruolo, modo, consumo, mani). Non tocca descrizioni, quantità e prezzi al cliente. Usato dal bottone "ricalcola". Risponde con il preventivo |
| POST | `/quotes/{id}/preview-calculate` | **non salva**: riceve `{ lines: [...] }` nel formato di POST lines (più `id?` di una riga esistente, per calcolarla come farebbe il suo PATCH), risponde con `{ data: { lines, totals } }`. Per l'anteprima live nel bottom sheet della riga |

Righe e `calculate` modificano il preventivo solo in `draft`/`sent` (409 altrimenti); su un `sent` le modifiche alle righe lo riportano a `draft`. `calculate` aggiorna solo i costi interni e non cambia lo stato. `preview-calculate` non salva niente e risponde in ogni stato.
| GET | `/quotes/{id}/pdf` | `application/pdf`, generato al volo (DomPDF, font DejaVu Sans in subset, ~25 KB). Header `Content-Disposition: inline; filename="Preventivo_1_2026_Condominio_Belissi.pdf"` (nome del cliente senza spazi né accenti) |
| POST | `/quotes/{id}/transition` | `{ to: "sent" \| "accepted" \| "rejected" \| "draft" }`. Applica le regole di `domain.md §6`; 409 con `{ message }` se non ammessa. Risponde con `{ data: preventivo, stock_warnings: [] }`; per `accepted` gli avvisi sono i materiali andati sotto scorta: `{ material_id, name, package_label, available, min_stock, to_buy }` |
| GET | `/quotes/{id}/completion` | proposta di chiusura: per ogni materiale selezionato `{ material, estimated_packages, suggested_closed, suggested_opened }` |
| POST | `/quotes/{id}/complete` | `{ consumptions: [{ material_id, closed, opened }] }` → movimenti `consumption`, stato `completed` |

Le righe libere (`job_id` null) non hanno materiali e non entrano nel costo stimato.

## Magazzino

| metodo | route | note |
|---|---|---|
| GET | `/stock` | lista materiali con `on_hand`, `committed`, `available`, `to_buy`, `status` (`ok` \| `low` \| `negative`); `?filter=to_buy&category=&search=`. Per `to_buy` include `commitments[]`. Esclusi attrezzi (`kind = tool`) e materiali archiviati. `negative` = disponibile < 0, `low` = disponibile < `min_stock` |
| POST | `/stock/{materialId}/adjust` | `{ closed, opened, notes? }` → imposta i valori assoluti, genera movimento `adjustment` con il delta (nessun movimento se non cambia niente). Risponde con la riga di magazzino |
| PATCH | `/stock/{materialId}` | `{ min_stock }` (scorciatoia su `material.min_stock`) |
| GET | `/purchases` | `?paid=0\|1`, dal più recente, con `lines_count` |
| POST | `/purchases` | `{ supplier?, purchased_at, paid, notes?, lines: [{ material_id, packages, unit_cost }], update_prices?: [material_id] }` → un movimento `purchase` per riga (`closed_delta = packages`, carica confezioni chiuse); `total` = Σ `packages × unit_cost`; aggiorna `cost_price` solo per i materiali in `update_prices`, che devono essere fra quelli delle righe (422 altrimenti). Niente attrezzi né materiali archiviati. 201 con le righe |
| GET | `/purchases/{id}` | con `lines[]` (+ `material`) |
| PATCH | `/purchases/{id}` | solo `paid`, `notes` (gli altri campi sono ignorati: le righe hanno già mosso il magazzino). Nessuna DELETE: un errore si corregge con una rettifica |
| GET | `/stock/movements` | `?material_id=`, paginato |

## Home

| metodo | route | note |
|---|---|---|
| GET | `/dashboard` | `{ to_buy_count, open_quotes: [...5], to_complete: [...] }` in una chiamata |

## Formato del calcolo (risposta di lines / calculate / preview-calculate)

```json
{
  "id": "…",
  "description": "Imbiancatura interna",
  "unit": "sqm",
  "quantity": 181,
  "coats": 2,
  "unit_price": 4.00,
  "amount": 724.00,
  "materials": [
    {
      "material_id": "…",
      "material": { "name": "Tempera Wallcream", "package_label": "bidone 14 lt", "base_unit": "lt" },
      "role": "primary",
      "consumption_mode": "coverage",
      "consumption_per_unit": null,
      "apply_coats": true,
      "selected": true,
      "coverage_used": 9,
      "required_quantity": 40.222,
      "required_packages": 3,
      "unit_cost": 47.69,
      "estimated_cost": 143.07
    }
  ],
  "estimated_cost": 143.07
}
```

A livello preventivo: `subtotal`, `vat_amount`, `total`, `estimated_cost`, `estimated_margin`, `estimated_margin_pct`, `estimated_hours` (null se non calcolabile).
