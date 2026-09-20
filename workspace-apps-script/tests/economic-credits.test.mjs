// Regression checks: credits do not reduce the amount actually received.
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const context=vm.createContext({});
vm.runInContext(fs.readFileSync('workspace-apps-script/src/Segreteria.gs','utf8'),context);
for (const [name,total,balance,paid] of [['credit_only',10000,0,12000],['mixed_credit_debt',20000,10000,15000]]) {
 const result=context.posizioneEconomicaRegistrazione_({totale_centesimi:total,saldo_centesimi:balance},paid);
 console.log(name,JSON.stringify({actual:result,expected_paid:paid}));
 assert.equal(result.paid,paid);
}

assert.equal(context.posizioneEconomicaRegistrazione_({totale_centesimi:10000,saldo_centesimi:0,versato_centesimi:12000},99999).paid,12000);
