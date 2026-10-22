# Modulo Iscrizioni

Modulo WordPress per configurare eventi, raccogliere iscrizioni e gestire partecipanti, servizi, presenze e pagamenti. WordPress e MySQL registrano i dati operativi; Google Apps Script prepara i fogli dei singoli eventi e gestisce le attività affidate a Google.

## Architettura

- **WordPress e MySQL** gestiscono eventi, gruppi, autorizzazioni, iscrizioni, disponibilità, pagamenti e comunicazioni.
- **MODULI AUTONOMO**, il progetto Apps Script collegato a WordPress, aggiorna i fogli evento e restituisce al portale le modifiche operative confermate.
- **I fogli evento** presentano i dati dell'evento e consentono le modifiche operative previste dal portale.

Le richieste tra WordPress e Apps Script sono firmate. MySQL conserva il registro autorevole; i fogli sono viste operative aggiornabili.

## Funzioni

- Moduli pubblici per le iscrizioni, con capienza, tipologie, servizi e domande configurabili.
- Portale per la gestione di gruppi, eventi, persone, sistemazioni e presenze.
- Registro dei pagamenti e dei rimborsi, con importi calcolati in centesimi.
- Comunicazioni email con anteprima e coda gestita dal servizio di posta.
- Foglio operativo dedicato a ciascun evento, collegato ai dati di WordPress.
- Consultazione pubblica della prenotazione e del saldo tramite collegamento dedicato.

## Documentazione

- [Plugin WordPress](wordpress-plugin/modulo-iscrizioni/README.md)
- [Progetto Apps Script MODULI AUTONOMO](workspace-apps-script/README.md)
- [Architettura e flussi operativi](PROGETTO.md)
- [Schema dei dati](docs/SCHEMA_DATI.md)
- [Contratto dell'interfaccia](UX-CONTRACT.md) e [sistema grafico](DESIGN.md)
- [Manuale della segreteria](docs/manuale-segretario/Manuale_del_segretario.docx)
- [Sicurezza](SECURITY.md)

## Requisiti

Il plugin richiede WordPress 6.4 o successivo e PHP 7.4 o successivo. L'integrazione Google usa un progetto Apps Script autonomo, autorizzato dal suo proprietario e configurato con l'indirizzo del servizio WordPress.

## Verifica dei sorgenti

Su Windows, eseguire la verifica disponibile con PowerShell 7:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe"
```

Per la sanitizzazione dei dati dimostrativi:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./tools/check-sanitization.ps1"
```

Usare identità e recapiti dimostrativi negli esempi e nelle prove.
