import test from 'node:test';
import assert from 'node:assert/strict';
import {projectionContext,projectionFixture} from './helpers/projection-performance.mjs';
test('view decoding preserves results, parses each JSON once and does not retain earlier input',()=>{
 const projection=projectionFixture(100),before=JSON.stringify(projection),cached=projectionContext(),plain=projectionContext();
 // Use the same renderer without memoization as the reference behavior.
 plain.context.creaCacheDecodificaVista_=()=>({object:plain.context.decodificaOggetto_,list:plain.context.decodificaElenco_,option:(value,key)=>plain.context.decodificaElenco_(value).find(o=>'option_'+String(o.code)===key)});
 const expected=plain.context.generaVistaDaProiezioneDiretta_(projection);
 const actual=cached.context.generaVistaDaProiezioneDiretta_(projection);
 assert.equal(JSON.stringify(actual),JSON.stringify(expected));
 assert.equal(JSON.stringify(projection),before);
 assert.ok(cached.parses()<plain.parses()/4,`${cached.parses()} vs ${plain.parses()} parses`);
 assert.deepEqual(actual.righe.slice(0,3).map(row=>row.valori.participant_number),[1,2,3]);
 projection.participants[0].dati_aggiuntivi_json='{"custom_test":"Changed"}';
 assert.equal(cached.context.generaVistaDaProiezioneDiretta_(projection).righe[0].valori.custom_test,'Changed');
});
test('cache keeps object/list types separate, preserves first option and does not trust revision hashes',()=>{
 const {context:c}=projectionContext(),cache=c.creaCacheDecodificaVista_();
 assert.equal(Array.isArray(cache.list('{}')),true);assert.equal(Array.isArray(cache.object('[]')),false);
 assert.equal(cache.option('[{"code":"x","quantity":1},{"code":"x","quantity":2}]','option_x').quantity,1);
 const columns=[];
 c.aggiungiColonneDomande_(columns,{},[
  {hash_revisione_evento:'same',snapshot_json:'{"event":{"participant_fields":[{"key":"custom_a","label":"A"}]}}'},
  {hash_revisione_evento:'same',snapshot_json:'{"event":{"participant_fields":[{"key":"custom_b","label":"B"}]}}'}
 ],[],cache);
 assert.deepEqual(columns.map(c=>c.key),['custom_a','custom_b']);
});
