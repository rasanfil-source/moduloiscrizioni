# Versione 3.26.219

Gli script frontend accodati da WordPress richiedono ora la strategia nativa `defer`: `mi-core`, `mi-public`, `mi-portal`, `mi-portal-management` e `mi-portal-payments`. WordPress determina la strategia compatibile con dipendenze e script inline; `mi-public` conserva la dipendenza da `mi-core`. Il plugin richiede gia WordPress 6.4, che supporta questa API.

Questa implementazione sostituisce la proposta della PR #128: nessuna ricerca della stringa `defer` nel tag HTML e nessun filtro globale basato sul prefisso `mi-`. Lo script amministrativo non cambia. Gli script dei documenti autonomi gia differiti restano invariati.

Il rilascio consolida anche i sorgenti locali della 3.26.218 e la pulizia documentale gia effettuata. Non corregge i problemi preesistenti della modalita economica `NONE` e della riaccodatura email descritti nell'[analisi corrente](audit-sistema-2026-10-03.md).

Verifiche: 394 test Node, 7 test degli asset, asset generati, sanitizzazione, lint PHP e tutte le suite PHP previste da `tools/verify.ps1`. I test dello shortcode verificano dipendenza e caricamento condizionale; quelli del portale verificano anche la strategia nativa. Nessuna misura prestazionale o verifica del sito installato e inclusa in queste prove.

Pacchetti: `dist/modulo-iscrizioni-3.26.219.zip` per il caricamento locale e `dist/modulo-iscrizioni-3.26.219-pubblico.zip` per GitHub, senza `public-balance-config.json`. Il confezionatore verifica ogni file tramite SHA-256 e scrive i checksum degli archivi. Solo il pacchetto pubblico viene distribuito su GitHub. Nessuna installazione WordPress o distribuzione Google viene eseguita: il caricamento dello ZIP resta a cura dell'utente.

In `dist` restano le versioni 3.26.215–3.26.219; la 3.26.214 e archiviata nella diagnostica locale. I bundle Workspace vengono rigenerati dai sorgenti correnti per mantenere coerente la distribuzione.
