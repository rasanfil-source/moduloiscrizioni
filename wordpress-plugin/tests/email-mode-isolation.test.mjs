import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';

const source=await readFile(new URL('../modulo-iscrizioni/includes/class-mi-spedizione-email.php',import.meta.url),'utf8');
test('la coda seleziona un solo canale coerente con la modalità attuale',()=>{
  assert.match(source,/\$modalita = self::modalita\(\);[\s\S]*?\$operativo = 'OPERATIVO' === \$modalita/s);
  assert.match(source,/\$stati = 'OPERATIVO' === \$modalita \? array\( "'PENDING'" \) : array\( "'TEST_PENDING'" \);/);
  assert.doesNotMatch(source,/if \( \$operativo \) \$stati\[\] = "'PENDING'";\s*if \( \$destinatario_prova \) \$stati\[\] = "'TEST_PENDING'";/);
});
