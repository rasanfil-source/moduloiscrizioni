import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../modulo-iscrizioni/assets/public-balance.js',import.meta.url),'utf8');
function harness() {
  const elements=new Map(), timers=new Map(), requests=[];let timerId=0;
  const element=id=>{if(!elements.has(id))elements.set(id,{style:{},textContent:'',children:[],replaceChildren(){this.children=[];},appendChild(v){this.children.push(v);},append(...v){this.children.push(...v);}});return elements.get(id);};
  const c={Map,Number,document:{getElementById:element,createElement:()=>element(Symbol())},
    setTimeout(fn){timers.set(++timerId,fn);return timerId;},clearTimeout(id){timers.delete(id);},
    gasPost(payload){return new Promise(resolve=>requests.push({payload,resolve}));},
    setTextFlash(id,text){element(id).textContent=text;},formatEuro:cents=>String(cents),
    renderLoadedSummary(){},getTransfer:card=>card.choices,setBookingStatus(){},
    breakdownBox:element('breakdown'),totalBox:element('total'),emailSection:element('email'),
    persone:[{loaded:true,row:1,cardEl:{choices:{bus:1}},model:{token:'token',version:'v1',fixed:50000,paid:0,deposit:15000,locked:[],services:[{code:'bus',name:'Bus',price:1000,selected:false}]}}]};
  vm.createContext(c);
  const helpers=source.slice(source.indexOf('let _livePreviewTimer'),source.indexOf('const lookupTimers'));
  const live=source.slice(source.indexOf('function updateLiveBreakdown()'),source.indexOf('// Alias per retrocompatibilit'));
  const amounts=source.slice(source.indexOf('function showAmounts('),source.indexOf('// ─── INIT'));
  vm.runInContext(helpers+'\n'+live+'\n'+amounts,c);
  return {c,element,requests,fire(){const [id,fn]=timers.entries().next().value;timers.delete(id);return fn();},timers};
}
const preview={success:true,total:51000,paid:0,deposit:15300,depositPaid:0,depositDue:15300,saldoDue:35700,balance:51000};
test('il riepilogo usa la caparra autorevole senza perdere le righe dei servizi',async()=>{
  const h=harness();h.c.updateLiveBreakdown();
  assert.equal(h.element('caparraDaVersare').textContent,'Calcolo…');
  const rows=h.element('costLines').children.length;
  const done=h.fire();assert.equal(h.requests[0].payload.persone[0].services.bus,1);
  h.requests[0].resolve(preview);await done;
  assert.equal(h.element('caparraDaVersare').textContent,'153');
  assert.equal(h.element('quotaSaldo').textContent,'357');
  assert.equal(h.element('costLines').children.length,rows);
});
test('le risposte obsolete non sovrascrivono una scelta nuova o una conferma',async()=>{
  const h=harness();h.c.updateLiveBreakdown();const first=h.fire();
  h.c.persone[0].cardEl.choices.bus=0;h.c.updateLiveBreakdown();const second=h.fire();
  h.requests[1].resolve({...preview,deposit:15000,depositDue:15000});await second;
  h.requests[0].resolve(preview);await first;
  assert.equal(h.element('caparraDaVersare').textContent,'150');
  h.c.updateLiveBreakdown();const third=h.fire();h.c.cancelLivePreview();
  h.element('caparraDaVersare').textContent='confermato';h.requests[2].resolve(preview);await third;
  assert.equal(h.element('caparraDaVersare').textContent,'confermato');
});
test('errore o rimozione delle persone non lasciano quote obsolete',async()=>{
  const h=harness();h.c.updateLiveBreakdown();h.c.updateLiveBreakdown();assert.equal(h.timers.size,1);
  const done=h.fire();h.requests[0].resolve({success:false,error:'Conflitto'});await done;
  assert.equal(h.element('caparraDaVersare').textContent,'Da verificare alla conferma');
  h.c.updateLiveBreakdown();h.c.persone=[];h.c.updateLiveBreakdown();assert.equal(h.timers.size,0);
});
test('la conferma riallinea caparre, versamenti e versione del modello',()=>{
  const h=harness();h.c.applyConfirmedModels(h.c.persone,{versions:{1:'v2'},people:[{row:1,deposit:15300,paid:5000}]});
  assert.equal(h.c.persone[0].model.deposit,15300);assert.equal(h.c.persone[0].model.paid,5000);
  assert.equal(h.c.persone[0].model.version,'v2');assert.equal(h.c.persone[0].model.services[0].selected,true);
});
