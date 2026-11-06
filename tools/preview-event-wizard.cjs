// Anteprima locale del wizard PHP reale, senza salvataggi né servizi remoti.
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const php=process.env.MI_PHP_BINARY||'php';
const root=path.resolve(__dirname,'..');
const bootstrap=fs.readFileSync(path.join(root,'wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php'),'utf8');
const version=bootstrap.match(/Version:\s*([\d.]+)/)[1];
const folder=path.join(root,'output',`revisione-evento-${version}`);
fs.mkdirSync(folder,{recursive:true});
const destination=path.join(folder,'anteprima.html');
if(fs.existsSync(destination))throw new Error('Anteprima già generata: conservarla invariata.');
const markup=execFileSync(php,[path.join(root,'wordpress-plugin/tests/event-wizard.php'),'42','--html'],{cwd:root,encoding:'utf8'});
const css=fs.readFileSync(path.join(root,'wordpress-plugin/modulo-iscrizioni/assets/portal.css'),'utf8');
const script=fs.readFileSync(path.join(root,'wordpress-plugin/modulo-iscrizioni/assets/portal.js'),'utf8').replaceAll('</script','<\\/script');
const setup=`
const wizard=document.querySelector('.mi-event-wizard');
wizard.addEventListener('submit',event=>{event.preventDefault();document.querySelector('[data-preview-status]').textContent='Revisione completata. Questa anteprima locale non salva dati.';});
wizard.elements.title.value='Giornata ad Assisi';
wizard.elements.closes_at.value='';wizard.elements.starts_at.value='02/11/2027 12:00';
wizard.querySelector('[data-mi-copy-event]').disabled=true; wizard.elements.travel_instructions.value='';
`;
const html=`<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; form-action 'none'; base-uri 'none'"><title>Revisione creazione evento ${version}</title><style>${css}\nbody{margin:0;background:#f5f7fa;font:16px/1.5 system-ui}*{box-sizing:border-box}.mi-portal{max-width:980px;margin:auto;padding:20px}[hidden]{display:none!important}.preview-note{padding:14px 18px;margin:0 0 20px;border:1px solid #cbd5e1;border-radius:12px;background:#fff}.preview-note p{margin:6px 0}</style></head><body><main class="mi-portal" data-mi-portal-scope="reserved"><aside class="preview-note"><strong>Revisione del percorso di creazione evento · ${version}</strong><p>Dati dimostrativi già compilati: usa Continua e Indietro per esplorare le nove schermate e cambiare le scelte. Questa anteprima locale non salva dati.</p><p data-preview-status role="status"></p></aside>${markup}</main><script>${setup}</script><script>${script}</script></body></html>`;
fs.writeFileSync(destination,html,{flag:'wx'});
console.log(destination);
