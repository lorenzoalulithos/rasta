# Domain – App Artigiani (modulo Pittori) – v1

Documento di riferimento per il modello di dati, le regole di business e il calcolatore.
Vale come fonte di verità: se il codice e questo documento divergono, è il codice a essere sbagliato (o il documento va aggiornato prima).

Convenzione nomi: **entità e campi in inglese nel codice/DB**, etichette UI in italiano. In questo documento il nome inglese è seguito dall'etichetta italiana tra parentesi.

---

## 1. Scope v1

**Dentro**
- Anagrafica azienda (tenant) e clienti
- Catalogo: materiali, lavorazioni, materiali suggeriti per lavorazione, consumabili suggeriti
- Preventivo con calcolatore (righe → fabbisogno materiali → costo stimato → margine) e PDF
- Magazzino: giacenza, impegnato, disponibile, lista da comprare, acquisti con flag pagato
- Chiusura cantiere con conferma consumi → scarico magazzino

**Fuori (non-goal, non implementare nemmeno "per comodità")**
- Fatture / fatturazione elettronica
- Calendario lavori e DDT (v1.1, il modello li prevede già ma non si sviluppano)
- Offline-first / sync
- Capacitor / app native
- Moduli per mestieri diversi dai pittori
- Storico prezzi materiali
- Multi-utente dentro lo stesso tenant (ruoli, permessi)
- Sconti sulle righe o sul totale

---

## 2. Principi

1. **Multi-tenant dal giorno uno.** Ogni tabella di dominio ha `tenant_id`. L'utente attuale è uno solo, ma la struttura non cambia.
2. **Il mestiere è un preset, non un plugin.** Quello che è "da pittore" vive in tre posti: il catalogo seed, il `calculator` (codice), e pochi campi extra sulle entità core (es. `coverage`). Il resto è generico.
3. **La distinta materiali è una proposta.** Una lavorazione *suggerisce* materiali con una regola di consumo; nel preventivo l'utente sceglie quali usare. Non esiste una distinta fissa.
4. **Il magazzino si scarica solo a consumo reale**, mai all'accettazione del preventivo e mai al carico del furgone.
5. **Il prezzo al cliente non dipende dal calcolo.** Il calcolatore produce costo e margine; il prezzo unitario è un dato della lavorazione, modificabile a mano sulla riga.
6. **Il costo per l'azienda è IVA inclusa.** Il tenant è in regime forfettario e non detrae l'IVA sugli acquisti. Vedi §7.3 per una discrepanza con il foglio del socio da chiarire.

---

## 3. Entità

### 3.1 Tenant (Azienda)
Dati dell'intestazione documenti e impostazioni di calcolo.

| campo | tipo | note |
|---|---|---|
| id | uuid | |
| name | string | "Ravizza Patrick" |
| address, zip, city, province | string | |
| vat_number | string | P.IVA |
| tax_code | string | Codice fiscale |
| email, phone | string | |
| logo_path | string, null | opzionale, v1 senza logo |
| tax_regime | enum: `forfettario`, `ordinario` | default `forfettario` |
| default_vat_rate | decimal | default 0 se forfettario, 22 se ordinario |
| quote_validity_days | int | default 90 |
| quote_footer_text | text | nota fiscale stampata in fondo al PDF (es. testo art.1 c.54-89 L.190/2014 + bollo) |
| coverage_strategy | enum: `min`, `avg`, `max` | come usare la resa nel calcolo. Default `avg` |
| opened_package_ratio | decimal | quanto vale una confezione aperta ai fini del disponibile. Default 0.5 |
| package_rounding | enum: `up`, `fractional` | arrotondamento confezioni nel fabbisogno. Default `up` |

### 3.2 User (Utente)
Appartiene a un tenant. Email, password, nome. Auth con Sanctum (token per la PWA).

### 3.3 Customer (Cliente)

| campo | tipo | note |
|---|---|---|
| id, tenant_id | | |
| name | string | nome/cognome o ragione sociale |
| tax_code | string, null | CF |
| vat_number | string, null | P.IVA |
| email, phone | string, null | |
| address, zip, city, province | string, null | |
| notes | text, null | |

### 3.4 Site (Cantiere)
Un cliente può avere più cantieri. Il preventivo punta a un cantiere (che a sua volta ha il cliente).

| campo | tipo | note |
|---|---|---|
| id, tenant_id, customer_id | | |
| name | string | "Condominio Belissi – vano scala" |
| address, zip, city, province | string, null | se nullo, eredita dal cliente |
| notes | text, null | |

### 3.5 Material (Materiale)
Tutto ciò che si compra: vernici, stucchi, consumabili, attrezzi.

| campo | tipo | note |
|---|---|---|
| id, tenant_id | | |
| name | string | "Tempera Wallcream" |
| category | enum | `paint_interior`, `paint_exterior`, `enamel`, `wood`, `filler`, `primer`, `solvent`, `consumable`, `tool`, `other` |
| kind | enum: `consumable_quantity`, `consumable_checklist`, `tool` | vedi sotto |
| base_unit | enum: `lt`, `kg`, `m`, `pc` | unità in cui si consuma |
| package_size | decimal | contenuto di una confezione in `base_unit` (14 per il bidone da 14 lt) |
| package_label | string | "bidone 14 lt", "sacco 5 kg", "rotolo" |
| list_price | decimal, null | prezzo listino unitario, informativo |
| discount_notes | string, null | "35%+20%", solo informativo |
| cost_price | decimal | **prezzo pagato per confezione, IVA inclusa**. È l'unico usato nei calcoli |
| shipping_excluded | bool | true per i prodotti "(+ sped)" |
| coverage_min | decimal, null | resa minima, `base_unit` → mq (es. 8 mq/lt) |
| coverage_max | decimal, null | resa massima (es. 10) |
| supplier | string, null | fornitore, testo libero |
| min_stock | decimal | scorta minima in confezioni, default 0 |
| notes | text, null | |

`kind`:
- `consumable_quantity`: vernici, stucchi, fissativi. Ha `coverage`, entra nel calcolatore e nel fabbisogno.
- `consumable_checklist`: rulli, nastro, teli, guanti. Non ha resa, non entra nel preventivo. Compare solo nella *lista da portare* (v1.1) e in magazzino.
- `tool`: scala, spruzzatore. Solo in lista da portare. Non ha giacenza "a consumo".

`coverage` effettiva usata dal calcolatore = f(`coverage_strategy` del tenant): `min` → coverage_min, `max` → coverage_max, `avg` → media dei due. Se uno solo è presente, usa quello.

### 3.6 Job (Lavorazione)
Un servizio vendibile a preventivo.

| campo | tipo | note |
|---|---|---|
| id, tenant_id | | |
| name | string | "Imbiancatura interna" |
| unit | enum: `sqm`, `hour`, `pc`, `pair`, `lm` | mq, ore, unità, coppia, metri lineari |
| unit_price | decimal | prezzo al cliente per unità, IVA esclusa |
| default_coats | int | numero mani incluse nel prezzo. Default 2. Null per lavorazioni a ore/unità |
| avg_time_notes | string, null | "1:30/2:00 h a stanza (40/45 mq)", informativo |
| estimated_hours_per_unit | decimal, null | per stima ore interna. Opzionale v1 |
| show_on_quote_pdf | bool | default true |
| sort_order | int | |
| active | bool | |

### 3.7 JobMaterial (Materiale suggerito per lavorazione)
Relazione molti-a-molti con regola di consumo. **È una proposta**: nel preventivo l'utente spunta quali usare.

| campo | tipo | note |
|---|---|---|
| job_id, material_id | | |
| role | enum: `primary`, `secondary`, `checklist` | `primary` = la vernice/prodotto principale; `secondary` = fissativo, stucco…; `checklist` = consumabili e attrezzi |
| consumption_mode | enum: `coverage`, `per_unit`, `none` | vedi §7 |
| consumption_per_unit | decimal, null | usato solo con `per_unit`: quantità di `base_unit` per unità di lavorazione (es. 0.4 kg di stucco per mq di rasatura) |
| apply_coats | bool | se true il consumo si moltiplica per le mani. True per vernici, false per stucchi/fissativo |
| selected_by_default | bool | spuntato di default nel preventivo |
| sort_order | int | |

### 3.8 Quote (Preventivo)

| campo | tipo | note |
|---|---|---|
| id, tenant_id, site_id | | |
| number | int | progressivo **per anno** |
| year | int | |
| issued_at | date | |
| expires_at | date | `issued_at + tenant.quote_validity_days` |
| status | enum: `draft`, `sent`, `accepted`, `rejected`, `completed` | vedi §6 |
| vat_rate | decimal | copiato dal tenant alla creazione, modificabile |
| payment_terms | string, null | "Rimessa diretta" |
| footer_text | text | copiato dal tenant alla creazione |
| private_notes | text, null | mai stampate |
| subtotal | decimal | somma righe, calcolato |
| vat_amount | decimal | calcolato |
| total | decimal | calcolato |
| estimated_cost | decimal | somma costi materiali, calcolato |
| estimated_margin | decimal | `subtotal - estimated_cost`, calcolato |
| accepted_at, completed_at | datetime, null | |

Chiave unica: (`tenant_id`, `year`, `number`).

### 3.9 QuoteLine (Riga preventivo)

| campo | tipo | note |
|---|---|---|
| id, quote_id, job_id | | `job_id` nullable per righe libere |
| description | string | copiata dalla lavorazione, modificabile |
| unit | enum | copiata |
| quantity | decimal | |
| coats | int, null | copiato da `default_coats`, modificabile (1 mano) |
| unit_price | decimal | copiato, modificabile |
| amount | decimal | `quantity * unit_price` |
| sort_order | int | |

Non esiste campo sconto: l'utente modifica quantità o prezzo.

### 3.10 QuoteLineMaterial (Materiale scelto sulla riga)
I materiali effettivamente selezionati per quella riga, con il fabbisogno calcolato. Snapshot: se cambia il catalogo, il preventivo non cambia.

| campo | tipo | note |
|---|---|---|
| id, quote_line_id, material_id | | |
| selected | bool | spuntato dall'utente |
| coverage_used | decimal, null | resa usata nel calcolo |
| required_quantity | decimal | quantità teorica in `base_unit` (es. 40.2 lt) |
| required_packages | decimal | confezioni, arrotondate secondo `package_rounding` |
| unit_cost | decimal | `cost_price` del materiale al momento del calcolo |
| estimated_cost | decimal | `required_packages * unit_cost` |

### 3.11 Stock (Giacenza)
Una riga per materiale.

| campo | tipo | note |
|---|---|---|
| material_id (pk), tenant_id | | |
| closed_packages | int | confezioni intere chiuse |
| opened_packages | int | confezioni aperte (ognuna vale `opened_package_ratio`) |

Valori derivati (non salvati, calcolati on read):
- `on_hand` = `closed_packages + opened_packages * ratio`
- `committed` = Σ `required_packages` dei `QuoteLineMaterial` selezionati di preventivi in stato `accepted`
- `available` = `on_hand - committed`
- `to_buy` = `max(0, min_stock - available)` arrotondato per eccesso

### 3.12 StockMovement (Movimento di magazzino)
Tutto ciò che cambia la giacenza passa da qui. La giacenza è ricalcolabile dai movimenti.

| campo | tipo | note |
|---|---|---|
| id, tenant_id, material_id | | |
| type | enum: `purchase`, `consumption`, `adjustment` | |
| closed_delta, opened_delta | int | variazione (può essere negativa) |
| quote_id | uuid, null | per `consumption` |
| purchase_id | uuid, null | per `purchase` |
| notes | string, null | |
| occurred_at | datetime | |

### 3.13 Purchase (Acquisto)

| campo | tipo | note |
|---|---|---|
| id, tenant_id | | |
| supplier | string, null | |
| purchased_at | date | |
| paid | bool | default false |
| total | decimal, null | opzionale |
| notes | text, null | |

Righe `PurchaseLine`: material_id, packages, unit_cost. Alla conferma dell'acquisto si creano i `StockMovement` di tipo `purchase` e opzionalmente si aggiorna `material.cost_price` (chiedere conferma in UI).

### 3.14 Previste in v1.1 (solo per non chiudere porte nel modello)
- **CalendarEvent**: site_id, date, start/end time, notes, quote_id null. Lista da portare = materiali `checklist` delle lavorazioni del preventivo + consumabili.
- **DeliveryNote (DDT)**: numero per anno, causale, destinazione = cantiere, righe materiale con unità e quantità libera (anche "1 e 1/2"), firme. Non muove il magazzino.

---

## 4. Relazioni

```
Tenant 1─n User
Tenant 1─n Customer 1─n Site 1─n Quote 1─n QuoteLine 1─n QuoteLineMaterial n─1 Material
Tenant 1─n Material 1─1 Stock
Tenant 1─n Job n─n Material  (via JobMaterial)
QuoteLine n─1 Job
Material 1─n StockMovement
Purchase 1─n PurchaseLine n─1 Material
```

---

## 5. Regole di business

1. **Numerazione preventivi**: progressivo per `tenant` e `year`, assegnato al primo salvataggio (anche in bozza). Non si riusa un numero di un preventivo cancellato. Formato stampato: `n. 1 del 10/01/2026`.
2. **Scadenza**: `expires_at = issued_at + quote_validity_days`. Stampata in calce come "Il presente documento scadrà il …".
3. **IVA**: `vat_rate` per documento, default dal tenant. Forfettario → 0 e `footer_text` con la dicitura di legge. Il campo esiste e funziona anche per regime ordinario.
4. **Prezzo di costo = IVA inclusa**, sempre. Il tenant forfettario non detrae l'IVA; per un futuro tenant ordinario si valuterà un flag.
5. **Confezioni**: con `package_rounding = up` il fabbisogno si arrotonda per eccesso alla confezione intera. Con `fractional` resta decimale (solo per confronto con i conti del socio).
6. **Mani**: `coats` moltiplica il consumo solo dei materiali con `apply_coats = true`. Non tocca il prezzo: il prezzo a 1 mano lo corregge l'utente.
7. **Snapshot**: righe e materiali del preventivo copiano descrizione, prezzi, rese e costi al momento del calcolo. Modificare il catalogo non altera preventivi esistenti. Un bottone "ricalcola" aggiorna esplicitamente.
8. **PDF**: stampa intestazione tenant, destinatario e destinazione (cliente e cantiere), righe con descrizione / quantità + unità / prezzo unitario / importo, totali, footer, scadenza. **Mai i materiali, mai costi/margine, mai le note private.** Le righe a ore mostrano la quantità in ore come qualsiasi altra riga: non serve un template separato.
9. **Impegnato**: solo preventivi `accepted`. `draft`, `sent`, `rejected`, `completed` non impegnano.
10. **Scarico**: alla chiusura (`completed`) l'app propone per ogni materiale selezionato il consumo stimato in confezioni (chiuse/aperte); l'utente conferma o corregge; si creano `StockMovement` di tipo `consumption` e la giacenza scende. L'impegnato di quel preventivo si azzera automaticamente perché lo stato non è più `accepted`.
11. **Lista da comprare**: tutti i materiali con `available < min_stock`, mostrando quantità e i cantieri (preventivi accettati) che li richiedono.

---

## 6. Stati del preventivo

```
draft ──► sent ──► accepted ──► completed
  │         │
  └─────────┴──► rejected
```

- `draft`: modificabile liberamente.
- `sent`: PDF generato/inviato. Modificabile, ma la modifica riporta a `draft` (chiedere conferma).
- `accepted`: righe e materiali bloccati. Impegna il magazzino. Ammessa solo la modifica delle note private.
- `completed`: chiuso con consumi confermati. Sola lettura.
- `rejected`: sola lettura. Riapribile a `draft` come copia (nuovo numero).

---

## 7. Calcolatore (modulo Pittori)

Codice, non configurazione. Classe `PainterCalculator` lato API, un endpoint `POST /quotes/{id}/calculate` (o calcolo live sulle righe) che riceve righe + materiali selezionati e restituisce fabbisogno, costi e margine.

### 7.1 Formula per materiale `coverage`

```
coverage      = resa effettiva secondo coverage_strategy   (es. avg(8,10) = 9 mq/lt)
coats         = line.coats se apply_coats, altrimenti 1
required_qty  = line.quantity * coats / coverage            (in base_unit)
packages_raw  = required_qty / material.package_size
required_pkgs = ceil(packages_raw)  se package_rounding = up, altrimenti packages_raw
estimated_cost = required_pkgs * material.cost_price
```

### 7.2 Formula per materiale `per_unit`

```
required_qty  = line.quantity * consumption_per_unit * (coats se apply_coats)
… poi come sopra da packages_raw in giù
```

### 7.3 Verifica sul preventivo Belissi

Riga: Imbiancatura vano scala, 181 mq, 2 mani, 4,00 €/mq → importo 724,00 €.
Materiale primario: Tempera Wallcream, bidone 14 lt, resa 8/10, cost_price 47,69 € (IVA incl.).

```
coverage       = 9 mq/lt
required_qty   = 181 * 2 / 9        = 40,2 lt
packages_raw   = 40,2 / 14          = 2,87
required_pkgs  = 3  (rounding up)
estimated_cost = 3 * 47,69          = 143,07 €
margine riga   = 724,00 - 143,07    = 580,93 €  (80%)
```

Con `fractional`: 2,87 × 47,69 = 136,96 €.

**Discrepanza da chiarire col socio.** Nel suo foglio il "costo medio per 100 mq" dell'imbiancatura è 60,51 €. Rifacendo i conti torna solo con: 100 mq × 2 mani / 9,2 mq/lt = 21,7 lt = 1,55 bidoni × **39,09 € (prezzo senza IVA)** ≈ 60,5 €. Cioè lui calcola il costo con confezioni frazionarie e **prezzo IVA esclusa**, ma da forfettario l'IVA la paga davvero. Due opzioni:
- (a) teniamo `cost_price` IVA inclusa (più onesto: è quello che esce dal conto) e gli spieghiamo che i margini che vedrà sono un po' più bassi dei suoi
- (b) aggiungiamo un flag tenant `cost_includes_vat` e lo lasciamo scegliere

Proposta: (a) in v1, e il seed importa `cost_price` dalla colonna "Prezzo finale + IVA". Da confermare con lui.

### 7.4 Ore stimate (opzionale v1)
Se `estimated_hours_per_unit` è valorizzato: `hours = quantity * estimated_hours_per_unit`. Somma a livello preventivo, mostrata solo in UI, mai in PDF. Per le righe in `hour`, ore = quantità.

---

## 8. Seed – modulo Pittori

Tutto importato dalla lista prezzi del socio, normalizzata. File in `docs/seed/`.

### 8.1 Lavorazioni v1 (6)

| name | unit | unit_price | default_coats | materiale primario |
|---|---|---|---|---|
| Imbiancatura interna | sqm | 4,00 | 2 | Tempera Wallcream 14 lt |
| Imbiancatura esterna / Lavabile | sqm | 10,00 | 2 | Lavabile quarzo PaintTop 15 lt |
| Ringhiere (smalto) | sqm | 10,00 | 2 | Smalto antiruggine UltraGlobal 2,5 lt |
| Rasatura | sqm | 8,50 / 12,00 | 1 | Stucco gesso Tamstucco 5 kg / Stucco fibrato 20 kg |
| Stuccature | hour | 50,00 | – | nessuno (stucco come secondario a `per_unit`, se vuole) |
| Legno – impregnante | sqm | 6,50 | 2 | Impregnante Borma Wachs 2,5 lt |

Rasatura: due varianti (gesso / fibrato) → due lavorazioni separate nel seed, o una sola con due materiali primari alternativi. Decidere in UI, io propongo due lavorazioni.

Secondari suggeriti per imbiancatura interna: Fissativo (coverage, apply_coats false, selected_by_default false), Stucco gesso (per_unit, selected_by_default false). Checklist: rullo, rullini, pennello, nastro carta, teli nylon, teli feltro, paperella, scala, asta.

### 8.2 Regole di normalizzazione dei dati del foglio
- Resa "8/10 mq/lt" → `coverage_min 8`, `coverage_max 10`. Valore singolo → entrambi uguali.
- Sconti a catena "35%+20%" → solo in `discount_notes`. Non si calcola nulla.
- Prezzo "(+ sped)" → `shipping_excluded = true`.
- `cost_price` = colonna "Prezzo finale + IVA". Dove manca, lasciare null e segnalare.
- Materiali senza prezzo e senza resa → importati comunque come `checklist` o `tool`, prezzo null.
- Le 12 lavorazioni non in v1 restano nel seed con `active = false`.

---

## 9. Decisioni prese (e perché)

| decisione | motivo |
|---|---|
| Niente zone/stanze nel preventivo | il socio misura a totale |
| Niente sconto | non lo usa, scala i metri |
| Un solo template PDF | le ore compaiono come quantità delle righe a ore |
| Distinta = proposta spuntabile | sceglie i materiali caso per caso |
| Resa media di default, configurabile | "uso la media" |
| Confezioni intere nel fabbisogno | si compra a bidoni |
| Giacenza chiuse + aperte | "lo segnerei come aperto" |
| Scarico a fine cantiere con conferma | "solo alla fine, quando l'ho usato davvero" |
| Acquisti: solo flag pagato | non vuole scadenze |
| Consumabili fuori dal preventivo | "sono per fare le liste da portare" |
| Numerazione per anno | "si riparte ogni anno" |
| IVA a 0, campo presente | forfettario per ora |
| Multi-tenant dal giorno uno | aggiungerlo dopo è una rottura |

## 10. Aperti

- [ ] Confermare col socio il punto 7.3 (costo IVA inclusa vs esclusa)
- [ ] Confermare i 6 prezzi delle lavorazioni v1 e i materiali primari
- [ ] Rasatura: una lavorazione o due
- [ ] `estimated_hours_per_unit`: lo popoliamo in v1 o no
