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
| quote_footer_text | text | nota fiscale stampata in fondo al PDF. Testo usato dal socio: "Operazione senza applicazione Iva nei sensi dell'articolo 1, commi 54-89 L. 190/2014, regime forfettario, imposta di bollo assolta su originale" (nel suo PDF c'è "commi 64-89": il riferimento di legge è 54-89, da confermare con lui o col commercialista) |
| default_payment_terms | string, null | "Rimessa diretta". Copiato su `Quote.payment_terms` alla creazione |
| coverage_strategy | enum: `min`, `avg`, `max` | come usare la resa nel calcolo. Default `avg` |
| opened_package_ratio | decimal | quanto vale una confezione aperta ai fini del disponibile. Default 0.5 |
| package_rounding | enum: `up`, `fractional` | arrotondamento confezioni nel fabbisogno. Default `up` |
| trade | enum: `painter` | mestiere, seleziona il calcolatore (vedi `stack.md`). Default `painter`, unico valore in v1 |

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
| cost_price | decimal, null | **prezzo pagato per confezione, IVA inclusa**. È l'unico usato nei calcoli. Null solo se manca nel foglio (§8.2) |
| shipping_excluded | bool | true per i prodotti "(+ sped)" |
| coverage_min | decimal, null | resa minima, `base_unit` → mq (es. 8 mq/lt) |
| coverage_max | decimal, null | resa massima (es. 10) |
| supplier | string, null | fornitore, testo libero |
| min_stock | decimal | scorta minima in confezioni, default 0 |
| notes | text, null | |
| archived_at | datetime, null | materiale archiviato: sparisce da catalogo, liste e suggerimenti, ma resta nello storico dei preventivi chiusi |

`kind`:
- `consumable_quantity`: vernici, stucchi, fissativi. Ha `coverage`, entra nel calcolatore e nel fabbisogno.
- `consumable_checklist`: rulli, nastro, teli, guanti. Non ha resa, non entra nel preventivo. Compare solo nella *lista da portare* (v1.1) e in magazzino.
- `tool`: scala, spruzzatore. Solo in lista da portare. Non ha giacenza "a consumo".

`coverage` effettiva usata dal calcolatore = f(`coverage_strategy` del tenant): `min` → coverage_min, `max` → coverage_max, `avg` → media dei due. Se uno solo è presente, usa quello.

### 3.6 Job (Lavorazione)
Un servizio vendibile a preventivo. Tabella `jobs`: le code di Laravel usano `queue_jobs` per non collidere.

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
| payment_terms | string, null | "Rimessa diretta", copiato da `tenant.default_payment_terms` |
| footer_text | text | copiato dal tenant alla creazione |
| private_notes | text, null | mai stampate |
| subtotal | decimal | somma righe, calcolato |
| vat_amount | decimal | calcolato |
| total | decimal | calcolato |
| estimated_cost | decimal | somma costi materiali, calcolato |
| estimated_margin | decimal | `subtotal - estimated_cost`, calcolato |
| accepted_at, completed_at | datetime, null | |

Chiave unica: (`tenant_id`, `year`, `number`).

Il numero viene dalla tabella tecnica `quote_counters` (`tenant_id`, `year`, `last_number`), letta con lock nella stessa transazione che crea il preventivo (§5.1). `number` e `year` non cambiano più, nemmeno se si modifica `issued_at`.

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
I materiali effettivamente selezionati per quella riga, con il fabbisogno calcolato. Snapshot: se cambia il catalogo, il preventivo non cambia. Anche la regola di consumo (`role`, `consumption_mode`, `consumption_per_unit`, `apply_coats`) è salvata qui, perché un materiale aggiunto a mano non ha un `JobMaterial`. Il "ricalcola" (§5.7) riallinea regola e costi al catalogo dove il `JobMaterial` esiste ancora; gli altri tengono la loro regola e prendono solo il `cost_price` attuale.

| campo | tipo | note |
|---|---|---|
| id, quote_line_id, material_id | | |
| role | enum: `primary`, `secondary` | copiato dal `JobMaterial`; `secondary` per i materiali aggiunti a mano ("+ Altro materiale") |
| consumption_mode | enum: `coverage`, `per_unit` | regola di consumo usata, copiata dal `JobMaterial` o scelta con "+ Altro materiale" (§7) |
| consumption_per_unit | decimal(10,3), null | solo con `per_unit` |
| apply_coats | bool | se il consumo si moltiplica per le mani |
| selected | bool | spuntato dall'utente |
| coverage_used | decimal, null | resa usata nel calcolo |
| required_quantity | decimal | quantità teorica in `base_unit` (es. 40.2 lt) |
| required_packages | decimal(10,3) | confezioni, arrotondate secondo `package_rounding`. 3 decimali: con `fractional` lo snapshot deve ridare lo stesso costo del calcolo |
| unit_cost | decimal | `cost_price` del materiale al momento del calcolo |
| estimated_cost | decimal | `required_packages * unit_cost` |
| sort_order | int | ordine nella riga: quello dei suggeriti della lavorazione, poi i materiali aggiunti a mano |

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
  Dal DDT di esempio del socio (n. 10 del 04/05/2026):
  - il **destinatario** non è per forza il cliente (lì è il socio stesso, a un altro indirizzo) e il **luogo di destinazione** è un campo a parte (il cantiere);
  - causale libera ("Materiale per Lavorazione"), **trasporto a mezzo** mittente / vettore / destinatario, **vettore**, **data ritiro**, **annotazioni**;
  - **tre firme**: mittente, vettore, destinatario;
  - unità di misura libere e lunghe ("Bidoni da 14lt", "Ricambi", "Flaconi"), quantità libere ("Circa 5", "3 +3").

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

1. **Numerazione preventivi**: progressivo per `tenant` e `year`, assegnato al primo salvataggio (anche in bozza). Non si riusa un numero di un preventivo cancellato: il numero viene da un contatore per (`tenant`, `year`) incrementato in transazione con lock, che non torna mai indietro, nemmeno se si cancella l'ultimo preventivo (implementazione in A2.2). Formato stampato: `n. 1 del 10/01/2026`.
2. **Scadenza**: `expires_at = issued_at + quote_validity_days`. Stampata in calce come "Il presente documento scadrà il …".
3. **IVA**: `vat_rate` per documento, default dal tenant. Forfettario → 0 e `footer_text` con la dicitura di legge. Il campo esiste e funziona anche per regime ordinario.
4. **Prezzo di costo = IVA inclusa**, sempre. Il tenant forfettario non detrae l'IVA; per un futuro tenant ordinario si valuterà un flag.
5. **Confezioni**: con `package_rounding = up` il fabbisogno si arrotonda per eccesso alla confezione intera. Con `fractional` resta decimale (solo per confronto con i conti del socio).
6. **Mani**: `coats` moltiplica il consumo solo dei materiali con `apply_coats = true`. Non tocca il prezzo: il prezzo a 1 mano lo corregge l'utente.
7. **Snapshot**: righe e materiali del preventivo copiano descrizione, prezzi, rese e costi al momento del calcolo. Modificare il catalogo non altera preventivi esistenti. Un bottone "ricalcola" aggiorna esplicitamente.
8. **PDF**: stampa intestazione tenant, destinatario e destinazione (cliente e cantiere), righe con descrizione / quantità + unità / prezzo unitario / importo, totali, footer, scadenza. **Mai i materiali, mai costi/margine, mai le note private.** Le righe a ore mostrano la quantità in ore come qualsiasi altra riga: non serve un template separato.
   Struttura ripresa dal PDF Belissi del socio (per A2.5):
   - in alto: nome ditta in grande, sotto indirizzo, P.IVA e codice fiscale; a destra "Preventivo nr [1] del [10/01/2026]";
   - due riquadri affiancati **Destinatario** (cliente) e **Destinazione** (cantiere; se il cantiere non ha indirizzo, quello del cliente);
   - tabella **Descrizione · Quantità · Prezzo unitario · Importo**. Le colonne "Cod Artic." e "Sconto" dell'originale non servono (niente codici, niente sconti). Unità stampata per esteso: "181 mq", "2 ore", "1 unità" (l'originale scrive "mt" per i mq);
   - sotto le righe, nella colonna descrizione: il `footer_text` e la frase di scadenza;
   - in fondo un riquadro: a sinistra **Pagamento:** `payment_terms`; a destra Totale netto, + Imposta IVA, **Totale**. La "Ritenuta d'acconto" dell'originale non si gestisce (sempre 0 per il forfettario);
   - a piè di pagina: "Pag. 1" e "Vi preghiamo di controllare i Vs. dati anagrafici e la Partita IVA. Non ci riteniamo responsabili di eventuali errori."
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

Transizioni ammesse da `POST /quotes/{id}/transition` (tutte le altre: 409):

| da | a | effetto |
|---|---|---|
| `draft` | `sent` | serve almeno una riga |
| `draft` | `rejected` | |
| `sent` | `accepted` | `accepted_at`; impegna il magazzino; avvisa dei materiali che vanno sotto scorta |
| `sent` | `rejected` | |
| `sent` | `draft` | "torna in bozza" |

- Un `draft` non si accetta direttamente: prima si segna inviato.
- `accepted` → `completed` passa solo dalla chiusura cantiere (`/complete`, A3.3).
- `rejected` → `draft` non è una transizione: si **duplica** (nuovo numero, righe ricalcolate col catalogo attuale). Duplica funziona da qualsiasi stato.

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

Precisione: `required_qty` e `required_pkgs` si salvano a 3 decimali; si arrotonda a 2 decimali solo `estimated_cost` (e gli altri importi), alla fine del calcolo.

### 7.2 Formula per materiale `per_unit`

```
required_qty  = line.quantity * consumption_per_unit * (coats se apply_coats)
… poi come sopra da packages_raw in giù
```

### 7.3 Verifica sul preventivo Belissi

Preventivo reale del socio: **n. 1 del 10/01/2026**, Condominio Belissi, forfettario (IVA 0), pagamento "Rimessa diretta".

| riga | lavorazione | quantità | prezzo | importo | materiali |
|---|---|---|---|---|---|
| Imbiancatura vano scala interno a due mani con pittura a tempera | Imbiancatura interna | 181 mq, 2 mani | 4,00 | 724,00 | Tempera Wallcream |
| Stuccature interne | Stuccature | 2 ore | 50,00 | 100,00 | nessuno (lo stucco è secondario, non spuntato) |
| Carteggiatura e tinteggiatura portone esterno con smalto in tinta | riga libera | 1 unità | 150,00 | 150,00 | nessuno |
| **Totale** | | | | **974,00** | costo stimato 143,07 → margine 830,93 (85,3%) |

Calcolo della prima riga:

Riga: Imbiancatura vano scala, 181 mq, 2 mani, 4,00 €/mq → importo 724,00 €.
Materiale primario: Tempera Wallcream, bidone 14 lt, resa 8/10, cost_price 47,69 € (IVA incl.).

```
coverage       = 9 mq/lt
required_qty   = 181 * 2 / 9        = 40,222 lt
packages_raw   = 40,222 / 14        = 2,873
required_pkgs  = 3  (rounding up)
estimated_cost = 3 * 47,69          = 143,07 €
margine riga   = 724,00 - 143,07    = 580,93 €  (80,2%)
```

Con `fractional`: 2,873 × 47,69 = 137,01 €.

**Discrepanza da chiarire col socio.** Nel suo foglio il "costo medio per 100 mq" dell'imbiancatura è 60,51 €. Rifacendo i conti torna solo con: 100 mq × 2 mani / 9,2 mq/lt = 21,7 lt = 1,55 bidoni × **39,09 € (prezzo senza IVA)** ≈ 60,7 € (vicino ai 60,51 del foglio; nessun'altra combinazione si avvicina). Cioè lui calcola il costo con confezioni frazionarie e **prezzo IVA esclusa**, ma da forfettario l'IVA la paga davvero. Due opzioni:
- (a) teniamo `cost_price` IVA inclusa (più onesto: è quello che esce dal conto) e gli spieghiamo che i margini che vedrà sono un po' più bassi dei suoi
- (b) aggiungiamo un flag tenant `cost_includes_vat` e lo lasciamo scegliere
- (c) come (a), più una cifra **solo di confronto** "a costi netti IVA" in UI

Proposta: **(c)** in v1, e il seed importa `cost_price` dalla colonna "Prezzo finale + IVA". Da confermare con lui.

Regole di (c):
- Calcolo, snapshot, magazzino e margine principale usano **solo** `cost_price` IVA inclusa. Nessun campo nuovo nel DB, nessun flag tenant.
- In UI, toccando costo/margine del preventivo, compare una riga secondaria: `a costi netti IVA: costo X € · margine Y%`, con `X = estimated_cost / 1,22`. Calcolata al volo lato client, mai salvata, mai nel PDF, mai nella dashboard.
- Serve solo a confrontare i numeri col foglio del socio. Approssimata: assume IVA 22% su tutti i materiali.

Esempio Belissi: costo 143,07 € → netto 117,27 €; margine preventivo 85,3% → netto 88,0%.

### 7.4 Ore stimate (opzionale v1)
Se `estimated_hours_per_unit` è valorizzato: `hours = quantity * estimated_hours_per_unit`. Somma a livello preventivo, mostrata solo in UI, mai in PDF. Per le righe in `hour`, ore = quantità.

---

## 8. Seed – modulo Pittori

Tutto importato dalla lista prezzi del socio, normalizzata. File in `docs/seed/`.

### 8.1 Lavorazioni v1 (7)

| name | unit | unit_price | default_coats | materiale primario |
|---|---|---|---|---|
| Imbiancatura interna | sqm | 4,00 | 2 | Tempera Wallcream 14 lt |
| Imbiancatura esterna / Lavabile | sqm | 10,00 | 2 | Lavabile quarzo PaintTop 15 lt |
| Ringhiere (smalto) | sqm | 10,00 | 2 | Smalto antiruggine UltraGlobal 2,5 lt |
| Rasatura Con Stucco Gesso | sqm | 8,50 | 1 | Stucco gesso Tamstucco 5 kg |
| Rasature Con Stucco fibrato | sqm | 12,00 | 1 | Stucco fibrato 20 kg |
| Stuccature | hour | 50,00 | – | nessuno (stucco come secondario a `per_unit`, se vuole) |
| Legno – impregnante | sqm | 6,50 | 2 | Impregnante Borma Wachs 2,5 lt |

Rasatura: due varianti (gesso / fibrato) → due lavorazioni separate, come in `jobs.json`.

Secondari suggeriti per imbiancatura interna: Fissativo (coverage, apply_coats false, selected_by_default false), Stucco gesso (per_unit, selected_by_default false). Checklist: rullo, rullini, pennello, nastro carta, teli nylon, teli feltro, paperella, scala, asta.

### 8.2 Regole di normalizzazione dei dati del foglio
- Resa "8/10 mq/lt" → `coverage_min 8`, `coverage_max 10`. Valore singolo → entrambi uguali.
- Sconti a catena "35%+20%" → solo in `discount_notes`. Non si calcola nulla.
- Prezzo "(+ sped)" → `shipping_excluded = true`.
- `cost_price` = colonna "Prezzo finale + IVA". Dove manca, lasciare null e segnalare.
- Materiali senza prezzo e senza resa → importati comunque come `checklist` o `tool`, prezzo null.
- Le 11 lavorazioni non in v1 restano nel seed con `active = false` (18 in tutto, 7 attive).

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

- [ ] Confermare col socio il punto 7.3: proposta (c), costo IVA inclusa + cifra di confronto netta IVA
- [ ] Confermare i 6 prezzi delle lavorazioni v1 e i materiali primari
- [x] Rasatura: due lavorazioni (gesso / fibrato), come nel seed
- [x] Eliminare un materiale usato solo in preventivi chiusi: si **archivia** (`archived_at`), sparisce dal catalogo e dai suggerimenti delle lavorazioni, resta nello storico
- [x] Righe complete del preventivo Belissi: nel §7.3, dal PDF reale del socio
- [ ] `estimated_hours_per_unit`: lo popoliamo in v1 o no
- [ ] `Job.show_on_quote_pdf = false`: cosa vuol dire? Nascondere la riga dal PDF farebbe tornare i totali diversi dalla somma delle righe stampate. Per ora il PDF stampa sempre tutte le righe
- [ ] Scadenza: il socio scrive "scadrà a tre mesi dalla data di creazione"; noi usiamo `quote_validity_days` (90) e stampiamo la data. Va bene la data precisa?
- [ ] Prezzi lavorazioni: la tabella "Prezzo Finale" del foglio del socio ha valori diversi dalla tabella lavorazioni usata per il seed (es. Antimacchia 30 € vs 20/50 €, Ringhiere 8/9/10 € per tipo di smalto, Stucco veneziano 70 € vs 50/70 €). Il seed usa la tabella lavorazioni: confermare quale vale
