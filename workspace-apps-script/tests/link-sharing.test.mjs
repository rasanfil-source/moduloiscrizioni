import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
const source=fs.readFileSync(new URL('../src/FogliOperativi.gs',import.meta.url),'utf8');
function setup(access='PRIVATE',permission='NONE',error=null){
 const calls=[];
 const file={getSharingAccess:()=>access,getSharingPermission:()=>permission,setSharing:(a,p)=>{if(error)throw error;calls.push([a,p]);}};
 const context=vm.createContext({DriveApp:{Access:{ANYONE_WITH_LINK:'LINK'},Permission:{VIEW:'VIEW'},getFileById:id=>{assert.equal(id,'event-file');return file;}}});
 vm.runInContext(source,context);return {context,calls};
}
test('event link grants anonymous view only',()=>{
 const {context,calls}=setup();context.abilitaLetturaFoglioEventoConLink_('event-file');assert.deepEqual(calls,[['LINK','VIEW']]);
});
test('already shared event does not rewrite permissions',()=>{
 const {context,calls}=setup('LINK','VIEW');context.abilitaLetturaFoglioEventoConLink_('event-file');assert.equal(calls.length,0);
});
test('anonymous editing is reduced to view, explicit editors are not removed',()=>{
 const {context,calls}=setup('LINK','EDIT');context.abilitaLetturaFoglioEventoConLink_('event-file');assert.deepEqual(calls,[['LINK','VIEW']]);
});
test('domain restriction is not reported as successful sharing',()=>{
 const {context}=setup('PRIVATE','NONE',new Error('DOMAIN_POLICY'));assert.throws(()=>context.abilitaLetturaFoglioEventoConLink_('event-file'),/DOMAIN_POLICY/);
});
