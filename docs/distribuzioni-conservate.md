# Distribuzioni conservate

Pulizia richiesta dall'utente il 9 novembre 2026: mantenere gli ultimi cinque rilasci ed eliminare il precedente materiale di distribuzione.

| Versione | Distribuzioni locali conservate |
| --- | --- |
| 3.27.41 | ZIP locale e pubblico, checksum, sorgente MODULI AUTONOMO e checksum |
| 3.27.40 | ZIP locale e pubblico, checksum |
| 3.27.39 | ZIP locale e pubblico, checksum |
| 3.27.38 | ZIP locale e pubblico, checksum |
| 3.27.37 | ZIP locale e pubblico, checksum |

Conservate inoltre le cinque note e i cinque script di confezionamento corrispondenti. Le versioni 3.27.37–3.27.40 sono correzioni WordPress; non avevano un nuovo sorgente Google distribuito con il rispettivo numero. Il sorgente Google completo corrente e quello della 3.27.41.

Eliminati localmente **684 file**, per **518,60 MiB**: vecchi archivi, esportazioni versionate, checksum, note e script di confezionamento. Rimossi dall'albero Git corrente i 336 file tra questi precedentemente tracciati, inclusi sei vecchi ZIP erroneamente tracciati. I 22 file di distribuzione conservati sono stati verificati mediante SHA-256 prima e dopo la pulizia: nessuna modifica ai loro contenuti.

Restano i sorgenti correnti, i test, la documentazione operativa e le decisioni dell'utente. I file di lavoro e gli strumenti temporanei non fanno parte di questa pulizia. La 3.26.226 non viene modificata o rigenerata: e eliminata insieme alle altre vecchie distribuzioni per la successiva richiesta esplicita di conservare soltanto cinque rilasci.

Nessun nuovo numero di versione o ZIP: il plugin rimane 3.27.41. I numeri delle versioni eliminate restano gia utilizzati e non devono essere riciclati. Il prossimo rilascio dovra comunque superare 3.27.41.

La rimozione dall'albero corrente non cancella i vecchi contenuti dai commit precedenti. La pulizia delle release GitHub e la riscrittura della cronologia Git sono operazioni distinte; la seconda non e eseguita implicitamente da questa pulizia.

## Release pubblicate su GitHub

La sezione Releases aveva dieci vecchie pubblicazioni e non conteneva ancora le 3.27.37–3.27.41. Eliminate le cinque piu vecchie (3.26.179, 3.26.180, 3.26.194, 3.26.195, 3.26.198), inclusi relativi tag e allegati, per **9,70 MiB**. Conservate le cinque pubblicazioni piu recenti gia presenti: **3.26.210, 3.26.217, 3.26.219, 3.26.220 e 3.26.232**. Verificati invariati gli identificativi, i nomi e le dimensioni dei loro allegati.

La sostituzione di queste cinque pubblicazioni con le cinque versioni correnti del progetto e una scelta distinta, sottoposta all'utente. La pulizia dell'albero corrente riguarda il ramo di lavoro `codex/segreteria-3-26-15`; gli altri rami e la cronologia dei commit restano conservati. Non viene eseguito alcun force push.

Gli oggetti Git locali sono stati inoltre compattati con `git gc --no-prune`, passando da circa 73,74 a 23,36 MiB, senza eliminare gli oggetti recuperabili o riscrivere i commit.
