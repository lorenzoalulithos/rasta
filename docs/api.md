# API – App Artigiani – v1

Base: `/api`. Tutte le route tranne `/auth/login` richiedono `Authorization: Bearer <token>`. Tutte le risorse sono scoped sul tenant dell'utente: nessun `tenant_id` nelle richieste.

Risposte: `{ data }` per singolo, `{ data, meta }` per liste paginate (`?page=`, `?per_page=`, default 25). Errori di validazione: 422 standard Laravel. Non autorizzato: 401. Transizione di stato non ammessa: 409 con `{ message }`.

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
| DELETE | `/jobs/{id}` | soft: `active = false` se usata in preventivi, hard altrimenti |
| POST | `/jobs/reorder` | `{ ids: [] }` |
| GET | `/materials` | `?search=&category=&kind=`; include `stock` (on_hand, committed, available) |
| POST | `/materials` | crea anche lo `Stock` a zero |
| GET | `/materials/{id}` | include `stock`, `commitments[]` (quote, packages), `movements[]` (ultimi 10) |
| PATCH | `/materials/{id}` | |
| DELETE | `/materials/{id}` | 409 se usato in preventivi non chiusi |

## Preventivi

| metodo | route | note |
|---|---|---|
| GET | `/quotes` | `?status=&search=`; include `customer`, `site`, totali |
| POST | `/quotes` | `{ site_id }` → crea `draft`, assegna numero, copia vat_rate/footer dal tenant |
| GET | `/quotes/{id}` | include `lines[]` con `materials[]` (QuoteLineMaterial + material), `customer`, `site`, totali, `estimated_cost`, `estimated_margin` |
| PATCH | `/quotes/{id}` | `private_notes`, `payment_terms`, `vat_rate`, `issued_at`. Solo in `draft`/`sent` (tranne `private_notes`, sempre) |
| DELETE | `/quotes/{id}` | solo `draft` |
| POST | `/quotes/{id}/duplicate` | nuovo `draft`, nuovo numero, stesse righe e materiali (ricalcolati con il catalogo attuale) |
| POST | `/quotes/{id}/lines` | `{ job_id?, description?, quantity, coats?, unit_price?, materials?: [{ material_id, selected, consumption_mode?, consumption_per_unit?, apply_coats? }] }`. Se `job_id`: precompila da `Job` e crea i `QuoteLineMaterial` dai suggeriti. Per un materiale non suggerito dalla lavorazione ("+ Altro materiale") la regola si passa nell'elemento: default `coverage` se il materiale ha resa, altrimenti `consumption_per_unit` è obbligatorio. Risponde con la riga calcolata |
| PATCH | `/quotes/{id}/lines/{lineId}` | stessi campi; ricalcola |
| DELETE | `/quotes/{id}/lines/{lineId}` | |
| POST | `/quotes/{id}/lines/reorder` | `{ ids: [] }` |
| POST | `/quotes/{id}/calculate` | **ricalcola tutto** con catalogo e impostazioni attuali, aggiorna snapshot. Usato dal bottone "ricalcola" |
| POST | `/quotes/{id}/preview-calculate` | **non salva**: riceve `{ lines: [...] }` nel formato di POST lines, risponde con righe + materiali + totali. Per l'anteprima live nel bottom sheet della riga |
| GET | `/quotes/{id}/pdf` | `application/pdf`, generato al volo. Header `Content-Disposition: inline; filename="Preventivo_1_2026_Belissi.pdf"` |
| POST | `/quotes/{id}/transition` | `{ to: "sent" \| "accepted" \| "rejected" \| "draft" }`. Applica le regole di `domain.md §6`; 409 se non ammessa. Risponde con il preventivo e, per `accepted`, `stock_warnings[]` (materiali andati sotto scorta) |
| GET | `/quotes/{id}/completion` | proposta di chiusura: per ogni materiale selezionato `{ material, estimated_packages, suggested_closed, suggested_opened }` |
| POST | `/quotes/{id}/complete` | `{ consumptions: [{ material_id, closed, opened }] }` → movimenti `consumption`, stato `completed` |

Le righe libere (`job_id` null) non hanno materiali e non entrano nel costo stimato.

## Magazzino

| metodo | route | note |
|---|---|---|
| GET | `/stock` | lista materiali con `on_hand`, `committed`, `available`, `to_buy`, `status` (`ok` \| `low` \| `negative`); `?filter=to_buy&category=&search=`. Per `to_buy` include `commitments[]` |
| POST | `/stock/{materialId}/adjust` | `{ closed, opened, notes? }` → imposta i valori assoluti, genera movimento `adjustment` con il delta |
| PATCH | `/stock/{materialId}` | `{ min_stock }` (scorciatoia su `material.min_stock`) |
| GET | `/purchases` | `?paid=0\|1` |
| POST | `/purchases` | `{ supplier?, purchased_at, paid, lines: [{ material_id, packages, unit_cost }], update_prices?: [material_id] }` → movimenti `purchase`; aggiorna `cost_price` solo per i materiali in `update_prices` |
| GET | `/purchases/{id}` | |
| PATCH | `/purchases/{id}` | solo `paid`, `notes` |
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
