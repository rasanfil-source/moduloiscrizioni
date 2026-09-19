# Asset minificati

Installazione riproducibile: `npm ci` in questa cartella. Generazione: `npm run build`. Controlli prima del pacchetto: `npm run check` e `npm test`. Il test PHP `wordpress-plugin/tests/assets.php` verifica selezione URL, cache e debug.

esbuild è bloccato dal package-lock. I 14 sorgenti JS/CSS restano in assets; i derivati e il manifest sono in assets/min. La trasformazione riduce gli spazi senza bundling, rinomina degli identificatori o ottimizzazioni sintattiche. I nomi globali e le dipendenze WordPress restano invariati.

MI_Assets seleziona il derivato solo se gli hash di sorgente e output corrispondono al manifest. Il controllo è memorizzato per richiesta. Se manca un file o cambia il sorgente senza rebuild viene servito il sorgente. SCRIPT_DEBUG forza i sorgenti. Il digest dell'output è aggiunto all'URL, conservando anche la versione esistente.

Sono inclusi enqueue WordPress e pagine autonome del portale/saldo. Non concatenare gli asset: sono caricati in contesti e con dipendenze differenti. I file generati vanno inclusi nel pacchetto insieme ai sorgenti; node_modules resta soltanto uno strumento locale.

Misura iniziale: 521149 byte originali, 445000 minificati (-14,6%); gzip 125036 → 111684 byte (-10,7%). Il peso realmente trasferito dipende dalla compressione HTTP del server. Non è stato eseguito un collaudo visuale completo nel browser di ogni schermata.
