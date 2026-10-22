import { readFile, readdir, writeFile } from 'node:fs/promises';
import vm from 'node:vm';

// Genera il sorgente MODULI AUTONOMO senza includere configurazioni private.
const radice = new URL('../', import.meta.url);
const bootstrap = await readFile(new URL('wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php', radice), 'utf8');
const versione = bootstrap.match(/Version:\s*([\d.]+)/)[1];
const directory = new URL('workspace-apps-script/src/', radice);
const nomi = (await readdir(directory)).filter(nome => nome.endsWith('.gs')).sort();
const componi = async elenco => (await Promise.all(elenco.map(async nome => `// Sorgente: ${nome}\n${await readFile(new URL(nome, directory), 'utf8')}`))).join('\n\n');
const codice = await componi(nomi);
// La modalità autonoma limita il progetto ai fogli evento collegati a WordPress.
const codiceProgetto = `const MI_STANDALONE_MODE = true;\n` + codice;
new vm.Script(codiceProgetto);
const destinazioneProgetto = new URL(`dist/Codice-Workspace-Progetto-${versione}.gs`, radice);
await writeFile(destinazioneProgetto, codiceProgetto, 'utf8');
console.log(`Sorgente MODULI AUTONOMO verificato e generato: ${destinazioneProgetto.pathname}`);
