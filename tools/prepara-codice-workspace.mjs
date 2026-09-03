import { readFile, readdir, writeFile } from 'node:fs/promises';
import vm from 'node:vm';

// Genera un unico sorgente distribuibile, senza includere configurazioni private.
const radice = new URL('../', import.meta.url);
const bootstrap = await readFile(new URL('wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php', radice), 'utf8');
const versione = bootstrap.match(/Version:\s*([\d.]+)/)[1];
const directory = new URL('workspace-apps-script/src/', radice);
const nomi = (await readdir(directory)).filter(nome => nome.endsWith('.gs')).sort();
const codice = (await Promise.all(nomi.map(async nome => `// Sorgente: ${nome}\n${await readFile(new URL(nome, directory), 'utf8')}`))).join('\n\n');
new vm.Script(codice);
const destinazione = new URL(`dist/Codice-Workspace-${versione}.gs`, radice);
await writeFile(destinazione, codice, 'utf8');
console.log(`Sorgente Workspace verificato e generato: ${destinazione.pathname}`);
