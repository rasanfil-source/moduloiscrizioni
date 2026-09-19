const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),path=require('path');
const root=path.resolve(__dirname,'../../wordpress-plugin/modulo-iscrizioni/assets');
for(const name of fs.readdirSync(root).filter(n=>n.endsWith('.js'))){new vm.Script(fs.readFileSync(path.join(root,'min',name),'utf8'));}
function core(folder){const c=vm.createContext({});vm.runInContext(fs.readFileSync(path.join(root,folder,'core.js'),'utf8'),c);return c.MIRegistrationCore;}
const a=core(''),b=core('min');
for(const input of ['3331234567','+49123456789','00393331234567','abc','',null]){
assert.equal(a.normalizePhone(input),b.normalizePhone(input));assert.equal(a.isValidPhone(input),b.isValidPhone(input));
}
for(const value of ['3','-1','99','x',null])assert.equal(a.clampQuantity(value,5),b.clampQuantity(value,5));
// Execute the existing cache behavior suite against the generated asset in an isolated temporary copy.
const file=path.resolve(__dirname,'../../wordpress-plugin/tests/portal-cache.test.mjs');
let test=fs.readFileSync(file,'utf8').replace("assets/portal.js","assets/min/portal.js");
// Minification changes whitespace and quotes, not the DOMContentLoaded boundary.
test=test.replace('source.indexOf("document.addEventListener(\'DOMContentLoaded\'")','source.indexOf(\'document.addEventListener("DOMContentLoaded"\')');
const tmp=path.join(path.dirname(file),'asset-cache-generated.test.mjs');
try{fs.writeFileSync(tmp,test);require('child_process').execFileSync(process.execPath,['--test',tmp],{stdio:'inherit'});}finally{fs.unlinkSync(tmp);}
console.log('All generated JS parsed; core behavior and generated cache suite passed.');
