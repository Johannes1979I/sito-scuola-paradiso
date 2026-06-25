# Come usare e modificare il sito (guida pratica)

Il sito è una semplice **cartella di file**. Per **vederlo** basta un browser; per **modificarlo con il
pannello/editor** (cartella `admin/`) serve **PHP attivo**, perché l'editor salva i contenuti sul server.

> ⚠️ Aprire i file con **doppio click** (indirizzo `file://…`) mostra il sito ma **NON** fa funzionare l'editor
> e non mostra le modifiche salvate. Usa sempre un server locale (sotto).

---

## 1) Porta la cartella sul Mac
Copia tutta la cartella `Sito Scuola` sul Mac (AirDrop, chiavetta, ZIP, cloud…). Contiene già tutto:
pagine, immagini, `admin/` (con le credenziali), `content.json`, `site.json`, `uploads/`.

---

## 2) Avvia il sito in locale sul Mac — scegli UNA delle due strade

### Strada A — MAMP (più semplice, senza terminale) ✅ consigliata
1. Scarica **MAMP** (versione gratuita) da https://www.mamp.info e installalo.
2. Apri MAMP → **Preferences/Settings → Server** → imposta **Document Root** sulla cartella `Sito Scuola`
   (oppure copia la cartella dentro `/Applications/MAMP/htdocs/`).
3. Premi **Start** (avvio dei server).
4. Apri nel browser:
   - sito: **http://localhost:8888/**
   - editor: **http://localhost:8888/admin/**
   - (se hai messo la cartella in `htdocs/Sito-Scuola`, aggiungi quel nome: `http://localhost:8888/Sito-Scuola/admin/`)

### Strada B — Terminale (veloce, se te la cavi)
1. Installa PHP una volta sola (richiede Homebrew, da https://brew.sh):
   ```
   brew install php
   ```
2. Entra nella cartella e avvia il server:
   ```
   cd ~/Desktop/"Sito Scuola"
   php -S localhost:8000
   ```
3. Apri nel browser:
   - sito: **http://localhost:8000/**
   - editor: **http://localhost:8000/admin/**
4. Per fermare il server: **Ctrl + C** nel Terminale.

> Serve **PHP 7.4 o superiore** (MAMP e Homebrew installano versioni recenti: ok).

---

## 3) Accedi all'editor
Vai su `…/admin/` → **utente:** `Gianpaolo Zarletti` → **password:** (la tua).
Da lì: modifichi testi e formattazione, cambi/carichi immagini e media, inserisci media in qualsiasi punto,
crei / rinomini / elimini pagine. Premi **Salva** e ricarica il sito per vedere le modifiche.

---

## 4) Note utili
- **Permessi di scrittura**: l'editor scrive su `content.json`, `site.json`, nella cartella `uploads/` e crea/elimina
  pagine `.html`. Se un salvataggio dà errore, dai permessi di scrittura alla cartella del sito.
- **Video/file grandi**: PHP di base limita gli upload a ~2 MB. Per caricare video più pesanti aumenta
  `upload_max_filesize` e `post_max_size` nel `php.ini` (in MAMP c'è l'impostazione PHP; su hosting si fa da
  pannello o `.user.ini`).
- **Le tue modifiche** vivono in `content.json` (testi/immagini), `site.json` (menu/pagine) e `uploads/` (media caricati),
  oltre alle eventuali nuove pagine `.html`.

---

## 5) Quando vai online
1. Procurati un **hosting con PHP** (anche quello del vecchio sito va bene).
2. Carica **tutta la cartella** via FTP nello spazio web.
3. Assicurati che `content.json`, `site.json` e `uploads/` siano **scrivibili** dal server.
4. L'editor sarà su **https://iltuodominio/admin/** con le stesse credenziali.
5. Consigliato: **HTTPS** attivo (così la password non viaggia in chiaro).

Il flusso ideale: modifichi in locale sul Mac → quando sei soddisfatto, ricarichi la cartella (o solo i file
cambiati: `content.json`, `site.json`, `uploads/`, nuove pagine) sull'hosting.
