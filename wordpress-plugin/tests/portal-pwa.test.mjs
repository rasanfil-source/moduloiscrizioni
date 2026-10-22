import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function fixture(asset, {installed = false, panelsPresent = true} = {}) {
  const handlers = {};
  const button = {hidden:true,disabled:false,addEventListener:(_, handler) => { button.click = handler; }};
  const status = {textContent:''}, help = {hidden:false};
  const panel = {hidden:false,querySelector:selector => ({'[data-mi-pwa-prompt]':button,'[data-mi-pwa-status]':status,'[data-mi-pwa-help]':help}[selector])};
  const screen = {matches:installed,addEventListener:(_, handler) => { screen.change = handler; }};
  const document = {
    readyState:'complete',
    querySelectorAll:() => panelsPresent ? [panel] : [],
    querySelector:() => ({dataset:{miPwaGroup:'11'}}),
    addEventListener:(name, handler) => { handlers['document:'+name] = handler; }
  };
  const window = {matchMedia:()=>screen,addEventListener:(name, handler)=>{handlers[name]=handler;}};
  vm.runInNewContext(fs.readFileSync(new URL('../modulo-iscrizioni/assets/'+asset, import.meta.url),'utf8'), {document,window,navigator:{},location:{href:'https://example.invalid/?mi_portal=1',origin:'https://example.invalid'},URL});
  return {handlers,button,status,help,panel,screen};
}

for (const asset of ['portal-pwa.js','min/portal-pwa.js']) {
  test(asset+': installazione solo su richiesta, annullamento e nuovo tentativo',async()=>{
    const f=fixture(asset); let prompts=0, prevented=0;
    assert.equal(f.button.hidden,true);
    f.handlers.beforeinstallprompt({preventDefault(){prevented++;},async prompt(){prompts++;},userChoice:Promise.resolve({outcome:'dismissed'})});
    assert.equal(prevented,1); assert.equal(prompts,0); assert.equal(f.button.hidden,false);
    await f.button.click(); await f.button.click();
    assert.equal(prompts,1); assert.equal(f.button.hidden,true); assert.match(f.status.textContent,/browser/);
    f.handlers.beforeinstallprompt({preventDefault(){},async prompt(){throw Error('Unavailable');},userChoice:Promise.resolve({outcome:'dismissed'})});
    await f.button.click(); assert.match(f.status.textContent,/menu del browser/); assert.equal(f.button.disabled,false);
  });
  test(asset+': app installata mantiene accessibile la personalizzazione',()=>{
    const f=fixture(asset,{installed:true});
    assert.equal(f.panel.hidden,false); assert.equal(f.help.hidden,true); assert.equal(f.button.hidden,true);
    const normal=fixture(asset); normal.handlers.appinstalled();
    assert.equal(normal.panel.hidden,false); assert.equal(normal.help.hidden,true);
    const login=fixture(asset,{panelsPresent:false}); let prevented=false;
    login.handlers.beforeinstallprompt({preventDefault(){prevented=true;}});
    assert.equal(prevented,false);
  });
  test(asset+': contesto icona conservato solo nei collegamenti del portale',()=>{
    const f=fixture(asset);
    const navigate=(href,reserved=true)=>{
      const link={href,closest:()=>reserved};
      f.handlers['document:click']({target:{closest:()=>link}});
      return new URL(link.href);
    };
    assert.equal(navigate('https://example.invalid/?mi_portal=1&mi_portal_view=manage').searchParams.get('mi_pwa_group'),'11');
    assert.equal(navigate('https://example.invalid/?mi_portal=1&mi_pwa_group=22').searchParams.get('mi_pwa_group'),'22');
    for(const url of ['https://other.example.invalid/?mi_portal=1','https://example.invalid/?mi_status=1','https://example.invalid/?mi_portal=1&mi_cancel_token=synthetic']) assert.equal(navigate(url).href,url);
    assert.equal(navigate('https://example.invalid/?mi_portal=1',false).searchParams.has('mi_pwa_group'),false);
  });
}
