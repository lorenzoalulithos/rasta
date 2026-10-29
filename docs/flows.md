# Flows – App Artigiani (modulo Pittori) – v1

Flussi della PWA schermata per schermata. Riferimenti alle entità in `domain.md`.

Principi UI:
- **Mobile-first.** Tutto deve funzionare a una mano su un Android da 6". Desktop = stesse schermate con layout a due colonne dove indicato.
- **Il preventivo è l'app.** Dalla home si arriva a un nuovo preventivo in un tap. Tutto il resto è di supporto.
- **Mai costringere a uscire dal flusso.** Cliente, cantiere e lavorazione si creano inline da dentro il preventivo.
- **Un preventivo in 5 minuti.** Oggi ne impiega 30-60. Ogni schermata in più va giustificata.
- Niente tabelle da gestionale su mobile: liste di card, bottom sheet per le scelte, form corti.
- Componenti: Nuxt UI 4. Icone Lucide. Nessun componente custom se ne esiste uno in libreria.

---

## 0. Shell e navigazione

**Bottom tab bar (mobile) / sidebar (desktop)** con 4 voci:

| tab | route | contenuto |
|---|---|---|
| Home | `/` | dashboard |
| Preventivi | `/quotes` | lista preventivi |
| Magazzino | `/stock` | giacenze e lista da comprare |
| Altro | `/more` | clienti, catalogo, impostazioni |

FAB "+" sempre visibile su Home e Preventivi → nuovo preventivo.

**Auth**: `/login` con email + password. Token Sanctum in storage persistente della PWA. Nessuna registrazione self-service in v1 (l'utente lo crea un seeder/command).

**PWA**: manifest con nome, icona, `display: standalone`. Service worker cache-first per asset, network-first per API. Se offline: banner "Sei offline, puoi solo consultare" e le pagine lista mostrano l'ultima risposta in cache. Nessuna scrittura offline.

---

## 1. Home `/`

Dashboard minima, tre blocchi:

1. **Da comprare** – badge con il numero di materiali sotto scorta/disponibile negativo. Tap → `/stock?filter=to_buy`. Se zero, il blocco mostra "Magazzino ok".
2. **Preventivi aperti** – lista degli ultimi 5 in stato `draft`, `sent`, `accepted`, con numero, cliente/cantiere, totale, badge stato. Tap → dettaglio. Link "Vedi tutti".
3. **Cantieri da chiudere** – preventivi `accepted` con `accepted_at` più vecchio di 14 giorni (euristica, configurabile dopo): "Belissi – accettato il 12/01, hai finito?". Tap → flusso chiusura (§6).

Desktop: i tre blocchi in griglia a 3 colonne.

---

## 2. Preventivi

### 2.1 Lista `/quotes`

- Barra di ricerca (cliente, cantiere, numero).
- Filtro a chip per stato: Tutti / Bozze / Inviati / Accettati / Chiusi / Rifiutati.
- Card per preventivo: `n. 3/2026` · cliente · cantiere · totale · badge stato · data.
- Ordinamento: più recente prima.
- FAB "+".

### 2.2 Nuovo preventivo – step 1: cliente e cantiere `/quotes/new`

Un'unica schermata.

- **Cliente**: campo con ricerca; tap apre bottom sheet con lista filtrabile e in testa "+ Nuovo cliente". Il nuovo cliente è un form inline con solo `name` obbligatorio; il resto (CF, P.IVA, indirizzo, telefono) è espandibile "Altri dati".
- **Cantiere**: appare dopo aver scelto il cliente. Bottom sheet con i cantieri del cliente + "+ Nuovo cantiere". Form: `name` obbligatorio, indirizzo opzionale con toggle "Stesso indirizzo del cliente" (default on).
- Se il cliente ha un solo cantiere, è preselezionato.
- Bottone **Continua** → crea il `Quote` in `draft` (assegna numero), va allo step 2.

### 2.3 Preventivo – step 2: righe `/quotes/{id}`

È la schermata principale, serve sia per creare che per modificare.

**Header**: `n. 3/2026` · cliente · cantiere · badge stato · menu ⋯ (duplica, elimina, note private).

**Lista righe** (card):
```
Imbiancatura interna
181 mq × 4,00 €                     724,00 €
2 mani · 3 bidoni Tempera Wallcream
```
La terza riga compare solo se ci sono materiali selezionati. Tap sulla card → §2.4. Swipe sinistra → elimina.

**Bottone "+ Aggiungi lavorazione"** → bottom sheet picker:
- ricerca
- lista delle `Job` attive del tenant (nome · unità · prezzo)
- in testa, sempre: **"+ Nuova lavorazione"** → form inline con `name`, `unit` (segmented: mq / ore / unità / coppia / ml), `unit_price`, `default_coats` (visibile solo se mq). Salva nel catalogo e la usa subito.
- in fondo: **"Riga libera"** → riga senza `job_id`, descrizione e prezzo scritti a mano.

Scelta la lavorazione, si apre direttamente §2.4 per inserire la quantità.

**Footer sticky**:
```
Totale 974,00 €        Costo stimato 143 € · Margine 85%
[ Anteprima PDF ]  [ Invia ▸ ]
```
Costo e margine sono in grigio piccolo: dati interni, mai nel PDF. Il bottone a destra cambia con lo stato (vedi §2.6).

Tap su costo/margine → si espande una riga di confronto (da confermare col socio, `domain.md §7.3` opzione c):
```
a costi netti IVA: costo 117 € · margine 88%
```
Solo informativa, calcolata al volo (`estimated_cost / 1,22`), stesso grigio piccolo. Nuovo tap la richiude.

Desktop: righe a sinistra (60%), a destra pannello fisso con riepilogo, totali, note private, azioni.

### 2.4 Riga `/quotes/{id}/lines/{lineId}` (bottom sheet su mobile, pannello laterale su desktop)

Form:
- **Descrizione** (precompilata dalla lavorazione, modificabile)
- **Quantità** – input numerico grande, tastiera numerica, suffisso con l'unità. È il campo con autofocus.
- **Mani** – segmented 1 / 2 / 3, visibile solo se la lavorazione ha `default_coats`. Default dalla lavorazione.
- **Prezzo unitario** – precompilato, modificabile.
- **Importo** – calcolato, sola lettura, grande.

**Sezione "Materiali"** (collassata se la lavorazione non ha `JobMaterial`):
- Lista dei materiali suggeriti con checkbox, ordinati per `role` (primary, secondary) e `sort_order`. I `checklist` non compaiono qui.
- Per ogni materiale spuntato, sotto il nome: `40,2 lt → 3 bidoni · 143,07 €`. Si ricalcola live al cambio di quantità/mani (chiamata `calculate` con debounce, o calcolo client-side con le stesse formule – vedi api.md).
- Link "+ Altro materiale" → picker materiali `consumable_quantity`, aggiunge una riga con `consumption_mode = coverage` se il materiale ha resa, altrimenti chiede un consumo per unità.

Bottone **Salva** → torna a §2.3.

### 2.5 Anteprima PDF `/quotes/{id}/preview`

- Il PDF renderizzato (iframe/`<object>` su desktop, su Android si apre nel viewer di sistema).
- Bottoni: **Condividi** (Web Share API con il file; fallback: download) · **Segna come inviato** (→ `sent`).
- Il PDF viene generato lato API ogni volta (nessuna cache in v1).

### 2.6 Azioni per stato

| stato | bottone principale | altre azioni |
|---|---|---|
| draft | Anteprima PDF | elimina, duplica |
| sent | Segna accettato | segna rifiutato, torna in bozza (conferma: "le modifiche riportano il preventivo in bozza"), rigenera PDF |
| accepted | Chiudi cantiere → §6 | note private, duplica |
| completed | – | duplica |
| rejected | – | duplica (nuovo numero, stato draft) |

Al passaggio a `accepted`: toast "Magazzino aggiornato: 3 bidoni di Tempera impegnati". Se qualcosa va sotto scorta: toast con link "Vai alla lista da comprare".

### 2.7 Note private

Textarea in un bottom sheet dal menu ⋯. Mai nel PDF. Visibili in ogni stato.

---

## 3. Magazzino `/stock`

### 3.1 Lista

- Chip filtro: **Tutti** / **Da comprare** / per `category`.
- Ricerca.
- Card per materiale:
```
Tempera Wallcream · bidone 14 lt
In magazzino 2 + 1 aperta   Impegnati 3   Disponibili -0,5   ⚠
```
Il colore della card segnala: rosso se `available < 0`, giallo se `< min_stock`, normale altrimenti.
- Tap → §3.2.

Il filtro "Da comprare" mostra solo rosso/giallo, con sotto ogni card i cantieri che impegnano quel materiale: `Belissi (3) · Rossi (1)`.

Azione in alto: **"Registra acquisto"** → §3.3.

### 3.2 Dettaglio materiale `/stock/{materialId}`

- I tre numeri in grande: in magazzino (chiuse + aperte), impegnati, disponibili.
- **Rettifica** – due stepper: chiuse, aperte. Salva → `StockMovement` tipo `adjustment`. È il modo con cui il socio "va giù, dà un'occhiata e sistema".
- Scorta minima: stepper.
- Lista impegni: preventivi `accepted` che lo richiedono.
- Ultimi 10 movimenti.
- Link "Modifica scheda materiale" → §4.2.

### 3.3 Registra acquisto `/stock/purchases/new`

- Fornitore (testo, con suggerimenti dai precedenti), data (default oggi).
- Righe: picker materiale + confezioni + prezzo per confezione (precompilato con `cost_price`).
- Toggle **Pagato** (default off).
- Salva → movimenti `purchase`. Se il prezzo inserito è diverso da `cost_price`: dialog "Aggiorno il prezzo di listino di Tempera Wallcream a 48,50 €?" Sì/No.

### 3.4 Acquisti `/stock/purchases`

Lista con filtro Da pagare / Pagati. Tap → dettaglio con toggle Pagato. Raggiungibile da "Altro" e da un link in §3.1.

---

## 4. Catalogo (da "Altro")

### 4.1 Lavorazioni `/catalog/jobs`

- Lista ordinabile (drag o frecce) con nome · unità · prezzo · toggle attiva.
- Tap → form: `name`, `unit`, `unit_price`, `default_coats`, `avg_time_notes`, `estimated_hours_per_unit` (opzionale), `show_on_quote_pdf`.
- Sezione **Materiali suggeriti**: lista con ruolo (primario / secondario), modalità consumo (resa / per unità + valore), "moltiplica per le mani", "spuntato di default". "+ Aggiungi materiale".
- Sezione **Da portare in cantiere**: lista di consumabili/attrezzi (`role = checklist`). Solo elenco, nessun numero.

Questa è la "sezione lista" che il socio ha chiesto: tutto editabile, niente di bloccato.

### 4.2 Materiali `/catalog/materials`

- Lista con ricerca e filtro categoria. Card: nome · confezione · prezzo · resa.
- Tap → form con tutti i campi di `Material`. La resa è due campi affiancati "da / a" con suffisso `mq/lt` (o `mq/kg`). `kind` come segmented: "Si consuma (vernici, stucchi)" / "Consumabile (rulli, nastro)" / "Attrezzo".
- Creare un materiale crea anche il suo `Stock` a zero.

---

## 5. Clienti `/customers`

- Lista con ricerca. Card: nome · città · n. preventivi.
- Dettaglio: dati, cantieri (con "+ Nuovo cantiere"), preventivi del cliente.
- Form cliente: solo `name` obbligatorio.

---

## 6. Chiusura cantiere `/quotes/{id}/complete`

Entrata da: dettaglio preventivo `accepted`, o dal blocco Home.

Schermata unica, una card per ogni materiale selezionato nel preventivo:
```
Tempera Wallcream · bidone 14 lt
Previsto: 3 bidoni
Usato:   [ 2 ] chiuse   [ 1 ] aperta
```
Stepper precompilati con la stima (3 chiuse → l'utente corregge in 2 + 1 aperta). Sotto, materiali non previsti: "+ Altro materiale usato".

Bottone **Chiudi cantiere** → dialog di conferma con riepilogo → `StockMovement` tipo `consumption` per ogni riga, stato `completed`, toast "Cantiere chiuso, magazzino aggiornato".

Se un consumo porta la giacenza sotto zero: warning inline "Avresti usato più di quanto avevi, controlla", ma si può confermare lo stesso (fa una rettifica implicita).

---

## 7. Impostazioni `/settings`

- **Azienda**: tutti i campi `Tenant` dell'intestazione (nome, indirizzo, P.IVA, CF, email, telefono, logo opzionale).
- **Documenti**: regime (forfettario/ordinario), IVA default, validità preventivo in giorni, testo in calce al PDF, condizioni di pagamento default.
- **Calcolo**: resa usata (minima / media / massima), arrotondamento confezioni (intere / frazionarie), valore confezione aperta (slider 0–1, default 0,5).
- **Account**: cambio password, logout.

---

## 8. Stati vuoti e primo avvio

- Primo login con catalogo vuoto: Home mostra una card "Inizia dal catalogo: carica le lavorazioni di esempio" → importa il seed pittori. In alternativa "Parti da zero".
- Lista preventivi vuota: illustrazione + "Crea il primo preventivo".
- Magazzino vuoto: "I materiali compaiono qui quando li aggiungi al catalogo".

---

## 9. Fuori da v1 (per non dimenticarli)

- Calendario con lista da portare (usa i `checklist` delle lavorazioni del preventivo).
- DDT stampabile dal preventivo accettato.
- Foto del cantiere allegate al preventivo.
- Notifiche push "cantiere da chiudere".
- Scrittura offline.
