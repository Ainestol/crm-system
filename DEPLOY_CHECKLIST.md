# Deploy checklist — snecinatripu.eu

> Cílový server: FORPSI VPS Basic · Ubuntu 24.04 LTS · IP **194.182.86.81** · 2 vCPU / 3.8 GB RAM / 39 GB SSD
>
> Doména: **snecinatripu.eu**
>
> Stack: Nginx + PHP 8.3-FPM + MariaDB 10.11 + Let's Encrypt SSL

---

## Před začátkem — předpoklady

- [ ] DNS A-záznam `snecinatripu.eu` → `194.182.86.81` (registrátor doménového jména)
- [ ] DNS A-záznam `www.snecinatripu.eu` → `194.182.86.81`
- [ ] DNS propagace dokončena: `dig +short snecinatripu.eu` na VPS musí vrátit `194.182.86.81`
- [ ] Máš SSH přístup root@194.182.86.81

---

## ⚡ Krok 0 — Bezpečnostní hardening (5 minut)

> **Proč hned:** zatím se přihlašuješ jako root přes heslo, což je riziko. Pojďme vytvořit deploy uživatele a SSH klíč.

### 0.1 Vytvořit deploy uživatele

```bash
# Na VPS (jako root)
adduser deploy
# Zadej silné heslo (ideálně z password manageru, 16+ znaků)
# Ostatní fields nech prázdné (Enter)

# Přidej do sudo skupiny
usermod -aG sudo deploy
```

### 0.2 SSH klíč pro deploy uživatele

```bash
# Na svém LOKÁLNÍM počítači (Windows: PowerShell, Linux/Mac: terminál)
ssh-keygen -t ed25519 -C "ainestol@snecinatripu" -f ~/.ssh/snecinatripu

# Nahraj veřejný klíč na VPS
ssh-copy-id -i ~/.ssh/snecinatripu.pub deploy@194.182.86.81
# (poprvé se zeptá na heslo deploye — to, cos zadal v 0.1)

# Test loginu klíčem (heslo už nepotřebuje)
ssh -i ~/.ssh/snecinatripu deploy@194.182.86.81
```

### 0.3 Zakázat SSH login pro root + zakázat heslo (jen klíč)

```bash
# Na VPS jako root (nebo sudo)
sudo nano /etc/ssh/sshd_config
```

Najdi a uprav:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

Uložit (Ctrl+O, Enter, Ctrl+X) a restart SSH:

```bash
sudo systemctl restart ssh
```

> ⚠ **POZOR:** Před restartem SSH **TEĎ otestuj login z nového terminálu** přes klíč jako `deploy`. Pokud nefunguje, neboru SSH a tvůj root login je pořád aktivní (ten ti to napraví).

### 0.4 Časové pásmo na Europe/Prague

```bash
sudo timedatectl set-timezone Europe/Prague
date  # ověř — má ukazovat český čas
```

---

## 🌐 Krok 1 — Web server: Nginx (Apache vypnout)

### 1.1 Zastavit + odinstalovat Apache (pokud běží)

```bash
sudo systemctl stop apache2 2>/dev/null
sudo systemctl disable apache2 2>/dev/null
sudo apt remove --purge apache2 apache2-* -y
sudo apt autoremove -y
```

### 1.2 Ověřit Nginx běží na portu 80

```bash
sudo systemctl status nginx
sudo systemctl enable nginx
curl -I http://localhost  # mělo by vrátit 200 OK
```

---

## 🛠 Krok 2 — Doinstalovat chybějící nástroje

### 2.1 Composer (PHP package manager)

```bash
sudo apt update
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version  # ověř
```

### 2.2 Certbot (Let's Encrypt SSL)

```bash
sudo apt install -y certbot python3-certbot-nginx
certbot --version
```

### 2.3 PHP-FPM (pokud ještě není)

```bash
# Ověř nejdřív
systemctl status php8.3-fpm

# Pokud "service not found":
sudo apt install -y php8.3-fpm
sudo systemctl enable --now php8.3-fpm
```

---

## 🗄 Krok 3 — MariaDB příprava

### 3.1 Hardening (nastavit root heslo, smazat anonymní users, atd.)

```bash
sudo mariadb-secure-installation
```

Odpovědi:
- `Enter current password for root`: stiskni Enter (default je prázdné)
- `Switch to unix_socket authentication?`: **n**
- `Change the root password?`: **Y** → nové silné heslo (ULOŽ si do password manageru!)
- `Remove anonymous users?`: **Y**
- `Disallow root login remotely?`: **Y**
- `Remove test database?`: **Y**
- `Reload privilege tables now?`: **Y**

### 3.2 Vytvořit DB + uživatele pro CRM

```bash
sudo mariadb -u root -p
# (zadej root heslo z 3.1)
```

V MariaDB shellu:

```sql
CREATE DATABASE snecinatripu_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_app'@'localhost' IDENTIFIED BY 'ZMEN_ME_NA_SILNE_HESLO';
GRANT ALL PRIVILEGES ON snecinatripu_crm.* TO 'crm_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> 🔑 **Heslo `crm_app`**: vygeneruj silné, ulož do password manageru. Bude v `.env` aplikace.

### 3.3 MariaDB tuning (pro 4 GB RAM serveru)

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

V sekci `[mysqld]` přidej / uprav:

```ini
# CRM tuning pro 4 GB RAM
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
max_connections = 100
query_cache_size = 0
query_cache_type = 0
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# UTF-8 fully
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

Restart:

```bash
sudo systemctl restart mariadb
```

---

## 📦 Krok 4 — Nahrát kód CRM

### Varianta A — Git clone (doporučeno)

```bash
# Na VPS jako deploy
cd /var/www
sudo mkdir -p snecinatripu
sudo chown deploy:deploy snecinatripu
cd snecinatripu

# Clone z tvého repa (uprav URL)
git clone https://github.com/TVUJ_USER/snecinatripu.git .

# Nainstalovat composer dependencies (pokud máš composer.json)
composer install --no-dev --optimize-autoloader
```

### Varianta B — SCP upload z lokálního počítače

Na **lokálním** Windows PowerShell:

```powershell
# Vytvoř ZIP (bez node_modules, .git, storage/imports)
cd E:\Snecinatripu
tar --exclude='.git' --exclude='node_modules' --exclude='storage/imports/*' -czf snecinatripu.tar.gz .

# Přenes na VPS
scp -i ~/.ssh/snecinatripu snecinatripu.tar.gz deploy@194.182.86.81:/tmp/
```

Na VPS:

```bash
sudo mkdir -p /var/www/snecinatripu
sudo chown deploy:deploy /var/www/snecinatripu
cd /var/www/snecinatripu
tar -xzf /tmp/snecinatripu.tar.gz
rm /tmp/snecinatripu.tar.gz
```

### 4.1 Práva pro web server

```bash
# Storage musí být zapisovatelná pro PHP-FPM (www-data)
sudo chown -R deploy:www-data /var/www/snecinatripu
sudo find /var/www/snecinatripu -type d -exec chmod 755 {} \;
sudo find /var/www/snecinatripu -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/snecinatripu/storage
```

---

## ⚙ Krok 5 — Konfigurace aplikace (.env)

```bash
cd /var/www/snecinatripu
cp .env.example .env  # pokud existuje
nano .env
```

Vyplň:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://snecinatripu.eu

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=snecinatripu_crm
DB_USER=crm_app
DB_PASS=ZMEN_ME_NA_SILNE_HESLO  # to z kroku 3.2

# Session secret — vygeneruj náhodný 64znakový string
APP_SECRET=NAHODNY_64_ZNAKOVY_RETEZEC

# Email (volitelné — později)
MAIL_FROM=noreply@snecinatripu.eu
```

> Bezpečnost: `chmod 600 .env` — jen owner čte.

```bash
chmod 600 /var/www/snecinatripu/.env
```

---

## 🗃 Krok 6 — Import schématu DB

```bash
cd /var/www/snecinatripu
mariadb -u crm_app -p snecinatripu_crm < sql/schema.sql

# Pokud máš seed data
mariadb -u crm_app -p snecinatripu_crm < sql/seed.sql
```

---

## 🌍 Krok 7 — Nginx vhost pro snecinatripu.eu

```bash
sudo nano /etc/nginx/sites-available/snecinatripu.eu
```

Obsah:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name snecinatripu.eu www.snecinatripu.eu;

    # Pro Let's Encrypt validaci
    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    # Vše ostatní → HTTPS (po SSL setupu)
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name snecinatripu.eu www.snecinatripu.eu;

    root /var/www/snecinatripu/public;
    index index.php index.html;

    # SSL certifikáty (vyplní certbot v dalším kroku)
    # ssl_certificate /etc/letsencrypt/live/snecinatripu.eu/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/snecinatripu.eu/privkey.pem;

    # Bezpečnostní hlavičky
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Zabraň přístupu k citlivým souborům
    location ~ /\.(git|env|htaccess) { deny all; return 404; }
    location ~ ^/(app|sql|storage|tools|docs)/ { deny all; return 404; }

    # Velikost uploadu (pro CSV import)
    client_max_body_size 200M;

    # PHP přes FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120s;
    }

    # Pretty URLs (front controller pattern)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Statické soubory: cache
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Logy
    access_log /var/log/nginx/snecinatripu.access.log;
    error_log /var/log/nginx/snecinatripu.error.log;
}
```

### 7.1 Aktivovat vhost + Let's Encrypt

```bash
# Vytvoř adresář pro Let's Encrypt validaci
sudo mkdir -p /var/www/letsencrypt

# Aktivuj vhost
sudo ln -s /etc/nginx/sites-available/snecinatripu.eu /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default  # odstraň default

# Otestuj nginx config
sudo nginx -t
```

> ⚠ **Při prvním nastavení** zakomentuj v vhost dočasně řádek `return 301 https://...` a celý druhý server block (HTTPS), aby Nginx běžel na HTTP a certbot mohl validovat doménu. Po získání cert blok odkomentuj.

### 7.2 Získat SSL certifikát

```bash
sudo certbot --nginx -d snecinatripu.eu -d www.snecinatripu.eu \
    --email ainestol@gmail.com --agree-tos --no-eff-email \
    --redirect
```

Certbot:
- Validuje doménu přes HTTP-01 challenge
- Vystaví cert (3 měsíce platný)
- **Automaticky upraví Nginx config** — vyplní cesty k cert souborům
- Nastaví automatické obnovení (cron `/etc/cron.d/certbot`)

### 7.3 Restart Nginx + ověření

```bash
sudo systemctl reload nginx
curl -I https://snecinatripu.eu  # mělo by vrátit 200 OK + HTTPS hlavičky
```

---

## 🔧 Krok 8 — PHP-FPM tuning

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

Uprav:

```ini
memory_limit = 256M
max_execution_time = 120
post_max_size = 200M
upload_max_filesize = 200M
max_input_time = 120

# Timezone (kvůli date funkcím v PHP)
date.timezone = Europe/Prague

# Bezpečnost
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php8.3-fpm.error.log
```

Restart:

```bash
sudo systemctl restart php8.3-fpm
```

---

## ⏰ Krok 9 — Cron jobs

```bash
sudo crontab -u www-data -e
# (vyber nano, pokud se zeptá)
```

Přidej:

```cron
# Daily čistič — staré logy, dočasné soubory
0 3 * * * find /var/www/snecinatripu/storage/imports -mtime +30 -delete

# Let's Encrypt už má svůj cron přes /etc/cron.d/certbot

# Volitelné: ping výročí widgetu (denní notifikace majiteli)
# 0 9 * * * curl -s https://snecinatripu.eu/admin/cron/anniversary-check
```

---

## 💾 Krok 10 — Backupy

### 10.1 Daily DB backup script

```bash
sudo nano /usr/local/bin/backup-crm.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/crm"
DATE=$(date +%Y%m%d_%H%M)
mkdir -p "$BACKUP_DIR"

# DB dump
mariadb-dump -u crm_app -p'TVOJE_DB_HESLO' snecinatripu_crm | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Storage (nahrané CSV imports — volitelné)
tar -czf "$BACKUP_DIR/storage_$DATE.tar.gz" /var/www/snecinatripu/storage 2>/dev/null

# Smaž backupy starší než 30 dní
find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete
```

```bash
sudo chmod 700 /usr/local/bin/backup-crm.sh
```

### 10.2 Cron pro backupy (3:30 ráno)

```bash
sudo crontab -e
```

Přidej:

```cron
30 3 * * * /usr/local/bin/backup-crm.sh
```

---

## 🧪 Krok 11 — Smoke test po deployi

V prohlížeči:

- [ ] `https://snecinatripu.eu` se otevře (zelený zámek)
- [ ] `https://snecinatripu.eu/login` formulář se renderuje
- [ ] Login s admin účtem (vytvoř ho v phpMyAdmin nebo přes seed)
- [ ] `https://snecinatripu.eu/dashboard` načte
- [ ] `https://snecinatripu.eu/admin/datagrid` zobrazí prázdnou tabulku (DB je prázdná)
- [ ] `https://snecinatripu.eu/admin/import` formulář funguje
- [ ] Test mini-import 10 řádků z `docs/import_sample.csv`
- [ ] V `/admin/datagrid` vidíš naimportované kontakty

V terminálu:

```bash
# Logy aplikace
sudo tail -f /var/log/nginx/snecinatripu.error.log
sudo tail -f /var/log/php8.3-fpm.error.log

# Pokud něco selže — zkontroluj práva
sudo ls -la /var/www/snecinatripu/storage/
```

---

## 📧 Email (později — pokud bude potřeba)

> Zatím přihlašování řešíme **manuálně** — admin (ty) vytvoří uživatele přes `/admin/users` a heslo pošle uživateli mimo CRM (Signal/SMS). Při prvním loginu si uživatel heslo změní (`must_change_password = 1`).
>
> Až budeš chtít „Zapomenuté heslo" funkcionalitu, integrujeme **FORPSI SMTP** (mail.forpsi.com:587) nebo **Resend** (free tier 3 000/měs).

---

## 🚨 Užitečné příkazy pro správu

```bash
# Restart služeb
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo systemctl restart mariadb

# Logy v reálu
sudo journalctl -u nginx -f
sudo tail -f /var/log/nginx/snecinatripu.error.log

# DB shell
mariadb -u crm_app -p snecinatripu_crm

# Free disk
df -h /

# Top procesy
htop  # nebo sudo apt install htop

# Aktualizace systému (1× měsíčně)
sudo apt update && sudo apt upgrade -y
```

---

## 🆘 Když něco nejede

| Problém | Řešení |
|---|---|
| 502 Bad Gateway | PHP-FPM neběží: `sudo systemctl restart php8.3-fpm` |
| 403 Forbidden | Špatná práva: `sudo chown -R deploy:www-data /var/www/snecinatripu` |
| 500 Internal | Koukni do `tail -f /var/log/php8.3-fpm.error.log` |
| DB connection error | Zkontroluj heslo v `.env` + `mariadb -u crm_app -p` ručně |
| SSL nefunguje | `sudo certbot renew --dry-run` ověří, že obnova bude fungovat |
| HTTPS redirect cyklus | Zkontroluj že Nginx vhost má jen JEDEN `server_name` block na 443 |

---

## ✅ Final checklist před produkcí

- [ ] DNS: `snecinatripu.eu` → `194.182.86.81` (`dig +short snecinatripu.eu`)
- [ ] SSH: deploy user funguje, root login zakázán
- [ ] Nginx: HTTPS funguje, zelený zámek v prohlížeči
- [ ] PHP-FPM: 8.3, memory 256M, upload 200M
- [ ] MariaDB: tuning aplikován (`innodb_buffer_pool_size = 1G`)
- [ ] DB: schema načteno, admin uživatel vytvořen
- [ ] App: `.env` vyplněn, `chmod 600`
- [ ] Práva: storage zapisovatelná pro www-data
- [ ] Cron: backup denně, čistící cron v crontab
- [ ] Backup: prvotní záloha `/var/backups/crm/`
- [ ] Test: login, dashboard, import 10 řádků, datagrid
- [ ] Activity feed: po importu se objeví entries
- [ ] Workflow audit: změny stavů se logují
