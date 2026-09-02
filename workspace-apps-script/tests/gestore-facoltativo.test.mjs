import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const codice = await readFile(new URL('../src/FogliOperativi.gs', import.meta.url), 'utf8');

for (const email of [undefined, '', 'gestore@example.invalid']) {
  test(`creazione foglio con gestore ${email || 'assente'}`, () => {
    const ambiente = vm.createContext({
      MI_SHEETS: { EVENTS: 'Eventi' },
      normalizzaTesto_: valore => String(valore || '').trim(),
      neutralizzaFormula_: valore => valore,
      normalizzaValoreElenco_: valore => valore,
      normalizzaUrlPubblico_: valore => valore || '',
      ottieniSchedaObbligatoria_: () => ({ appendRow() {} }),
      convertiRigheInOggetti_: () => [],
      aggiungiControllo_() {},
    });
    vm.runInContext(codice, ambiente);
    // Isoliamo Drive: nessuna chiamata esterna o modifica ai permessi durante il test.
    ambiente.apriFoglioOperativoEvento = () => ({ id_foglio: 'foglio-prova', url_foglio: 'https://docs.google.com/spreadsheets/d/foglio-prova', creato: true });
    ambiente.aggiornaCollegamentiProduzioneEvento_ = () => {};
    const risultato = ambiente.preparaProduzioniEventoDaWordPress_({ id_evento: '123', titolo: 'Evento dimostrativo', email_gestore: email });
    assert.equal(risultato.ok, true);
    assert.equal(risultato.condivisione.email, email || '');
    assert.equal(risultato.condivisione.ok, false);
    assert.throws(() => ambiente.normalizzaEmailGestore_('non-valida'), /non valido/);
  });
}
