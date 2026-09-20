import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=await readFile(new URL('../src/Segreteria.gs',import.meta.url),'utf8');
const reportSource=await readFile(new URL('../src/Report.gs',import.meta.url),'utf8');
function context(){const ctx=vm.createContext({});vm.runInContext(source,ctx);return ctx;}
test('report: ammissione e annullamento individuale restano distinti',()=>{
 const ctx=context();
 assert.equal(ctx.valoreCampoElenco_('status',{}, {stato:'WAITLISTED'}, {stato_partecipante:'ACTIVE'}, {}, []),'Lista d’attesa');
 assert.equal(ctx.valoreCampoElenco_('status',{}, {stato:'WAITLIST_OFFERED'}, {stato_partecipante:'ACTIVE'}, {}, []),'Posto proposto');
 assert.equal(ctx.valoreCampoElenco_('status',{}, {stato:'CONFIRMED'}, {stato_partecipante:'CANCELLED'}, {}, []),'Annullata');
});
test('report: importi una volta per ordine anche dopo ordinamento non contiguo',()=>{
 const ctx=context(),row=(code,name,total,paid)=>Object.assign([name,total,paid],{_orderCode:code});
 const rows=[row('A','Ada',100,30),row('B','Bruno',200,50),row('A','Carla',100,30)];
 ctx.limitaImportiAUnaRigaPerOrdine_(rows,['first_name','total','paid']);
 assert.deepEqual(Array.from(rows[2]),['Carla','','']);
 assert.equal(rows.reduce((n,r)=>n+Number(r[1]),0),300);
 assert.equal(rows.reduce((n,r)=>n+Number(r[2]),0),80);
});
test('report: filtri combinati rispettano persona, stato e assegnazioni',()=>{
 const ctx=context();ctx.normalizzaTesto_=value=>String(value).trim();vm.runInContext(reportSource,ctx);
 const registration={stato:'WAITLISTED',codice_ordine:'ORD-1'},person={nome:'Bruno',cognome:'Prova'},data={room:'A',pullman:'BUS1'};
 const filters=ctx.normalizzaFiltriEsecuzioneReport_({query:'Bruno Prova',status:'WAITLISTED',room:'A',transport:'BUS1'});
 assert.equal(ctx.corrispondeFiltriReport_(filters,registration,person,data),true);
 assert.equal(ctx.corrispondeFiltriReport_({...filters,status:'CONFIRMED'},registration,person,data),false);
 assert.equal(ctx.corrispondeFiltriReport_({...filters,room:'B'},registration,person,data),false);
 assert.equal(ctx.corrispondeFiltriReport_({...filters,query:'Altro'},registration,person,data),false);
 assert.throws(()=>ctx.normalizzaFiltriEsecuzioneReport_({inventato:'valore'}),/non disponibile/);
 assert.throws(()=>ctx.normalizzaFiltriEsecuzioneReport_({status:'ACTIVE'}),/non valido/);
});
