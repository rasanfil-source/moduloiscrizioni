import {createRequire} from 'node:module';
import vm from 'node:vm';
const require=createRequire(new URL('./asset-build/build.cjs',import.meta.url));
const {transform}=require('esbuild');

// Punti di ingresso Apps Script: i trigger vengono richiamati da Google per nome.
export const entryPoints=['doGet','doPost','onOpen','avviaCronWordPress','attivaCronWordPress','manutenzioneRicevuteEmail','verificaMittentiEmailAutorizzati'];
export async function standaloneSource(source){
  const result=await transform(source+'\nexport { '+entryPoints.join(', ')+' };\n',{
    loader:'js',format:'esm',target:'es2020',treeShaking:true,minifySyntax:true,minifyIdentifiers:false,minifyWhitespace:false,
    define:{MI_STANDALONE_MODE:'true'},charset:'utf8',legalComments:'inline',
  });
  // GAS richiede funzioni globali, non un modulo ESM. Nomi e corpi restano invariati.
  const code=result.code.replace(/\nexport \{[^}]+\};\s*$/, '\n');
  if(code===result.code)throw Error('Export GAS non riconosciuti');
  new vm.Script(code);
  return '// MODULI AUTONOMO — soli punti di ingresso operativi.\n'+code;
}
