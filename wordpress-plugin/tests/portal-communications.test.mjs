import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../modulo-iscrizioni/assets/portal-communications.js',import.meta.url),'utf8');
function setup(){
 const node=(value='')=>({value,hidden:false,events:{},addEventListener(name,fn){this.events[name]=fn;}});
 const select=node('A');const choices={A:{dataset:{miMessage:'Testo A',miHelp:'Aiuto A'}},B:{dataset:{miMessage:'Testo B',miHelp:'Aiuto B'}},C:{dataset:{miMessage:'',miHelp:'Scrivi'}}};
 Object.defineProperty(select,'selectedOptions',{get(){return [choices[this.value]];}});
 const field=node('Bozza iniziale A'),help={textContent:'Aiuto A'},reset=node();
 class Option{constructor(text,value){this.textContent=text;this.value=value;}}
 const events=node('');events.options=[new Option('Scegli un evento',''),new Option('Attivo','1'),new Option('Visualizza eventi passati','__mi_toggle_events__')];
 events.replaceChildren=function(...items){this.options=items;};events.add=function(option){this.options.push(option);};
 const status={textContent:''};const form={dataset:{eventsAjax:'/ajax',eventsNonce:'nonce',eventsPast:'0'},querySelector(key){return {'[data-mi-communication-type]':select,'[data-mi-communication-message]':field,'[data-mi-communication-help]':help,'[data-mi-communication-reset]':reset,'select[name="event_id"]':events,'[data-mi-events-help]':status}[key];}};
 const calls=[];const context={document:{querySelector:()=>form,querySelectorAll:()=>[]},Option,AbortController,FormData,setTimeout,clearTimeout,fetch:async(url,opts)=>{calls.push(opts.body.get('action'));return {ok:true,json:async()=>({success:true,data:{events:[{id:2,title:'Passato'}]}})};}};
 vm.runInNewContext(source,context);
 return {select,field,help,reset,events,status,calls,async change(type){select.value=type;await select.events.change();}};
}
test('rapid changes keep each type and edited draft, with no suggestion requests',async()=>{
 const s=setup();await s.change('B');assert.equal(s.field.value,'Testo B');s.field.value='Modificato B';s.field.events.input();
 await s.change('A');assert.equal(s.field.value,'Bozza iniziale A');await s.change('C');assert.equal(s.field.value,'');await s.change('B');assert.equal(s.field.value,'Modificato B');
 s.reset.events.click();assert.equal(s.field.value,'Testo B');assert.equal(s.calls.length,0);
});
test('history is requested only when selected and remains inside event select',async()=>{
 const s=setup();assert.equal(s.calls.length,0);s.events.value='__mi_toggle_events__';await s.events.events.change();
 assert.deepEqual(s.calls,['mi_communication_events']);assert.equal(s.events.options[1].textContent,'Passato');assert.equal(s.events.options.at(-1).textContent,'Torna agli eventi attivi');
 s.events.value='__mi_toggle_events__';await s.events.events.change();assert.equal(s.events.options[1].textContent,'Attivo');assert.equal(s.calls.length,1);assert.equal(s.field.value,'Bozza iniziale A');
});
