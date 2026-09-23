const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto'),zlib=require('node:zlib');
const esbuild=require('esbuild');
const root=path.resolve(__dirname,'../../wordpress-plugin/modulo-iscrizioni/assets');
const sha=b=>crypto.createHash('sha256').update(b).digest('hex');
(async()=>{
 const manifest={};let before=0,after=0,gzipBefore=0,gzipAfter=0;
 const check=process.argv.includes('--check');
 for(const name of fs.readdirSync(root).filter(n=>/\.(js|css)$/.test(n)).sort()){
  // Git conserva gli asset testuali con LF: l'hash deve essere identico anche
  // quando il checkout Windows presenta CRLF.
  const input=Buffer.from(fs.readFileSync(path.join(root,name),'utf8').replace(/\r\n/g,'\n'));
  // No bundling, property mangling, module conversion or external symbol renaming.
  const result=await esbuild.transform(input.toString(),{loader:name.endsWith('.js')?'js':'css',minifyWhitespace:true,minifySyntax:false,minifyIdentifiers:false,legalComments:'inline',target:'es2020',charset:'utf8'});
  const output=Buffer.from(result.code),out='min/'+name;
  manifest[name]={file:out,source:sha(input),hash:sha(output)};
  if(check){if(!fs.existsSync(path.join(root,out))||!fs.readFileSync(path.join(root,out)).equals(output))throw Error('Stale minified asset: '+name);}
  else{fs.mkdirSync(path.join(root,'min'),{recursive:true});fs.writeFileSync(path.join(root,out),output);}
  before+=input.length;after+=output.length;gzipBefore+=zlib.gzipSync(input).length;gzipAfter+=zlib.gzipSync(output).length;
 }
 const json=JSON.stringify(manifest,null,2)+'\n',file=path.join(root,'min/manifest.json');
 if(check){if(fs.readFileSync(file,'utf8')!==json)throw Error('Stale manifest');}else fs.writeFileSync(file,json);
 console.log(JSON.stringify({assets:Object.keys(manifest).length,before,after,gzipBefore,gzipAfter}));
})().catch(e=>{console.error(e);process.exitCode=1;});
