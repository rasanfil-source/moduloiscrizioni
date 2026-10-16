import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
if(process.argv.includes('--worker')){
  const source=fs.readFileSync(new URL('../modulo-iscrizioni/assets/portal-payments.js',import.meta.url),'utf8');
  const actual=source.slice(source.indexOf('  function showHistory('),source.indexOf('  function invalidate('));
  const cells=[];
  const element=()=>({replaceChildren(){},append(){},setAttribute(){},createTHead(){return {insertRow:element};},createTBody(){return {insertRow(){return {insertCell(){const cell={};cells.push(cell);return cell;}};}};}});
  const context={embedded:false,el:element,document:{createElement:element},money:String};
  vm.createContext(context);vm.runInContext(actual,context);
  context.showHistory([{data:'2026-09-08T22:00:00+00:00',data_locale:'09/09/2026'}]);
  assert.equal(cells[0].textContent,'09/09/2026');
}else{
  for(const tz of ['Europe/Rome','Europe/London','America/New_York'])test('data contabile invariata nel fuso '+tz,()=>{
    const r=spawnSync(process.execPath,[fileURLToPath(import.meta.url),'--worker'],{env:{...process.env,TZ:tz},encoding:'utf8'});
    assert.equal(r.status,0,r.stderr);
  });
}
