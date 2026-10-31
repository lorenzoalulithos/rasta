# Deploy di prova – gratis

Per la demo al socio (checkpoint della Fase 4). Scelte in `stack.md` "Ambiente di prova".

| cosa | dove | indirizzo |
|---|---|---|
| App (PWA) | GitHub Pages | `https://lorenzoalulithos.github.io/rasta/` |
| API | VM Oracle Cloud Always Free | `https://duemani.duckdns.org/api` |

Una volta fatti i passi 1–5, ogni push su `main` con `Tests` verde aggiorna da solo app e API.

## 1. VM su Oracle Cloud

1. Account su [cloud.oracle.com](https://cloud.oracle.com) (chiede la carta solo per verifica: con le risorse "Always Free" non addebita niente).
2. **Compute → Instances → Create instance**
   - Image: **Ubuntu 24.04** (Canonical).
   - Shape: **Ampere → VM.Standard.A1.Flex**, 2 OCPU e 12 GB bastano (gratis fino a 4 OCPU / 24 GB). Se dice "out of capacity", riprova più tardi o cambia availability domain.
   - Networking: lascia la VCN proposta, con **IP pubblico**.
   - SSH keys: carica la tua chiave pubblica (`~/.ssh/id_ed25519.pub`) o scarica quella generata.
3. **IP fisso**: Instance → Attached VNICs → IPv4 addresses → modifica l'IP pubblico in **Reserved public IP** (gratis, così non cambia).
4. **Porte 80 e 443**: Networking → Virtual cloud networks → la VCN → Security Lists → Default → **Add Ingress Rules**: source `0.0.0.0/0`, TCP, destination port `80`; poi un'altra uguale con `443`.
5. Facoltativo ma consigliato: gli account solo "Free Tier" possono vedersi spegnere le VM inattive da Oracle. Passando a **Pay As You Go** (Billing → Upgrade) non succede, e finché resti nelle risorse Always Free il conto resta 0 €.

## 2. Dominio DuckDNS

1. [duckdns.org](https://www.duckdns.org), entra (anche con GitHub).
2. Crea il sottodominio **`duemani`** e in "current ip" metti l'**IP pubblico riservato** della VM → `update ip`.
3. Controlla dal Mac: `ping duemani.duckdns.org` deve rispondere con quell'IP.

## 3. Preparare la VM (una volta)

```bash
ssh ubuntu@duemani.duckdns.org
curl -fsSL https://raw.githubusercontent.com/lorenzoalulithos/rasta/main/deploy/oracle/provision.sh | bash
```

Lo script (`deploy/oracle/provision.sh`) installa PHP 8.5, Composer, MySQL 8 e Caddy, apre 80/443 nel firewall della VM, clona il repo in `/srv/duemani`, crea database e `.env` (password casuale, `APP_KEY`), lancia le migration e accende Caddy, che prende da solo il certificato HTTPS. Si può rilanciare senza danni.

Poi, sempre sulla VM, l'utente vero e il catalogo:

```bash
cd /srv/duemani/api
php artisan app:create-tenant "Ravizza Patrick" email@del-socio.it      # chiede la password
php artisan db:seed --class=PainterCatalogSeeder --force                # catalogo pittori
```

Prova: `curl https://duemani.duckdns.org/api/ping` → `{"data":{"status":"ok",…}}`.

I dati dell'azienda (indirizzo, P.IVA, testo in calce del PDF) per ora si mettono con `PATCH /api/tenant`; la pagina Impostazioni arriva con F5.5.

## 4. GitHub Pages

Repo → **Settings → Pages** → Source: **GitHub Actions**.

## 5. Deploy automatico

Sul Mac, una chiave solo per GitHub Actions:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/duemani_deploy -N "" -C "github-actions duemani"
ssh-copy-id -i ~/.ssh/duemani_deploy.pub ubuntu@duemani.duckdns.org
ssh-keyscan duemani.duckdns.org 2>/dev/null | grep ed25519     # riga per ORACLE_KNOWN_HOSTS
```

Repo → **Settings → Secrets and variables → Actions**:

| tipo | nome | valore |
|---|---|---|
| Secret | `ORACLE_HOST` | `duemani.duckdns.org` |
| Secret | `ORACLE_SSH_KEY` | contenuto di `~/.ssh/duemani_deploy` (la privata) |
| Secret | `ORACLE_KNOWN_HOSTS` | la riga di `ssh-keyscan` |
| Variable | `DEPLOY_APP` | `true` |
| Variable | `DEPLOY_API` | `true` |

Finché le due variabili non sono `true` i workflow di deploy non partono (niente errori rossi prima che la VM esista).

Primo giro: **Actions → Deploy app → Run workflow** e **Deploy API → Run workflow**. Poi parte tutto da solo dopo ogni `Tests` verde su `main`.

## A mano, se serve

```bash
ssh ubuntu@duemani.duckdns.org 'bash /srv/duemani/deploy/oracle/deploy.sh'   # aggiorna le API
ssh ubuntu@duemani.duckdns.org 'tail -n 50 /srv/duemani/api/storage/logs/laravel-$(date +%F).log'
```

## Sul telefono del socio

Apre `https://lorenzoalulithos.github.io/rasta/` in Chrome, entra, e dal menu ⋮ → **Installa app** (o "Aggiungi a schermata Home"). È anche la prova che chiude F4.1: dopo averla chiusa e riaperta deve essere ancora dentro.
