import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';

const source = await readFile(new URL('../src/WebApp.gs', import.meta.url), 'utf8');
test('il vecchio writer non è più distribuito e l’impronta della proiezione resta disponibile', async () => {
  const management = await readFile(new URL('../src/Core.gs', import.meta.url), 'utf8');
  assert.doesNotMatch(management, /function (schedaGestionePortale_|aggiornaGestionePortale_|rispostaAggiornamentoGestione_)/);
  assert.match(management, /function versioneGestione_/);
  const c=vm.createContext({});vm.runInContext(source,c);
  c.verificaBusta_=()=>({ok:true});c.creaRispostaJson_=v=>v;
  for(const action of ['SCHEDA_GESTIONE_PORTALE','AGGIORNA_GESTIONE_PORTALE']) assert.equal(c.doPost({postData:{contents:JSON.stringify({action,payload:{}})}}).error,'USE_MYSQL_MANAGEMENT');
});
test('la vecchia scheda firmata rimanda a MySQL senza leggere la replica', () => {
  let reads = 0;
  const context = vm.createContext({
    schedaGestionePortale_: () => { reads++; return {ok: true}; }
  });
  vm.runInContext(source, context);
  context.verificaBusta_ = () => ({ok: true});
  context.creaRispostaJson_ = value => value;
  const result = context.doPost({postData: {contents: JSON.stringify({action: 'SCHEDA_GESTIONE_PORTALE', payload: {order_code: 'TEST'}})}});
  assert.equal(result.ok, false);
  assert.equal(result.error, 'USE_MYSQL_MANAGEMENT');
  assert.equal(reads, 0);
  context.verificaBusta_ = () => ({ok: false, error: 'INVALID_SIGNATURE'});
  assert.equal(context.doPost({postData: {contents: '{"action":"SCHEDA_GESTIONE_PORTALE"}'}}).error, 'INVALID_SIGNATURE');
});
