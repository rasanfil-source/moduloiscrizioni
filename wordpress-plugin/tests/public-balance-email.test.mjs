import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';

test('saldo: email mascherata invariata, vuota, modificata e non valida',async()=>{
 const source=await readFile(new URL('../modulo-iscrizioni/assets/public-balance.js',import.meta.url),'utf8');
 const start=source.indexOf('const EMAIL_DOMAIN_CORRECTIONS =');
 const end=source.indexOf('// ─── CONFERMA E INVIA',start);
 assert.ok(start>=0&&end>start);
 const input={value:'ro*****co@l***.it',dataset:{registeredMask:'ro*****co@l***.it'},focus(){}};
 const c={emailInput:input,setGlobalStatus(){},escapeHtml:s=>s};vm.createContext(c);vm.runInContext(source.slice(start,end),c);
 assert.equal(c.getValidatedEmail(),'');
 input.value='';assert.equal(c.getValidatedEmail(),'');
 input.value='nuovo@example.invalid';assert.equal(c.getValidatedEmail(),'nuovo@example.invalid');
 input.value='non una email';assert.equal(c.getValidatedEmail(),null);
});
