import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const c=vm.createContext({});
for(const file of ['Segreteria.gs','SincronizzazioneManuale.gs'])vm.runInContext(await readFile(new URL('../src/'+file,import.meta.url),'utf8'),c);
test('attendance is projected in Italian and cannot be edited from sheet',()=>{
 for(const [state,label]of [['PRESENT','Presente'],['ABSENT','Assente'],['UNRECORDED','Non rilevata']])assert.equal(c.valoreCampoElenco_('attendance',{},{},{},{attendance:state},[]),label);
 assert.equal(c.campoModificabileFoglio_('attendance'),false);
 assert.equal(c.valoreCampoElenco_('attendance',{},{},{},{},[]),'Non rilevata');
});
