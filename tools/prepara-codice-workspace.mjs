import { readFile, readdir, writeFile } from 'node:fs/promises';
import vm from 'node:vm';

// Genera un unico sorgente distribuibile, senza includere configurazioni private.
const radice = new URL('../', import.meta.url);
const bootstrap = await readFile(new URL('wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php', radice), 'utf8');
const versione = bootstrap.match(/Version:\s*([\d.]+)/)[1];
const directory = new URL('workspace-apps-script/src/', radice);
const nomi = (await readdir(directory)).filter(nome => nome.endsWith('.gs')).sort();
const componi = async elenco => (await Promise.all(elenco.map(async nome => `// Sorgente: ${nome}\n${await readFile(new URL(nome, directory), 'utf8')}`))).join('\n\n');
const codice = await componi(nomi);
new vm.Script(codice);
const destinazione = new URL(`dist/Codice-Workspace-${versione}.gs`, radice);
await writeFile(destinazione, codice, 'utf8');
console.log(`Sorgente Workspace verificato e generato: ${destinazione.pathname}`);

// Il progetto MODULI conserva queste due interfacce come file Apps Script separati.
// Questo artefatto aggiorna Codice.gs senza dichiararle una seconda volta.
const separatiNelProgetto = new Set(['InterfacciaIscrizioni.gs', 'InterfacciaMovimenti.gs']);
const codiceProgetto = await componi(nomi.filter(nome => !separatiNelProgetto.has(nome)));
new vm.Script(codiceProgetto);
const destinazioneProgetto = new URL(`dist/Codice-Workspace-Progetto-${versione}.gs`, radice);
await writeFile(destinazioneProgetto, codiceProgetto, 'utf8');
console.log(`Sorgente Codice.gs per il progetto MODULI verificato e generato: ${destinazioneProgetto.pathname}`);
