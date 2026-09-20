import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
test('custom question keys with hyphens remain editable, totals never do',()=>{
 const c=vm.createContext({});vm.runInContext(fs.readFileSync(new URL('../src/SincronizzazioneManuale.gs',import.meta.url),'utf8'),c);
 assert.equal(c.campoModificabileFoglio_('custom_a-b'),true);
 for(const key of ['paid','balance','option_alloggio-singola','constructor'])assert.equal(c.campoModificabileFoglio_(key),false);
});
test('numeric group names and slugs are checked without throwing TypeError',()=>{
 const c=vm.createContext({normalizzaTesto_:v=>String(v),MI_SHEETS:{GROUPS:'groups'},ottieniSchedaObbligatoria_:()=>({}),convertiRigheInOggetti_:()=>[{nome:123,slug:123}]});
 vm.runInContext(fs.readFileSync(new URL('../src/Gruppi.gs',import.meta.url),'utf8'),c);
 assert.throws(()=>c.aggiungiGruppo({nome:'123'}),/Il gruppo esiste già/);
});
