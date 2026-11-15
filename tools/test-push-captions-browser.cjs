const fs=require('node:fs'),http=require('node:http'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets',process.env.MI_TEST_MIN?'min':'');
const oldCss=execFileSync('git',['show','62e3a7d5ac604f92aa18793a0d3dcb5cde2a5648:wordpress-plugin/modulo-iscrizioni/assets/portal.css']);
const php=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/includes/class-mi-web-push.php','utf8'),button=php.match(/<button class="mi-secondary mi-web-push__button".*?<\/button>/)[0];
const server=http.createServer(async(req,res)=>{
 if(req.url==='/baseline.css'){res.setHeader('Content-Type','text/css');return res.end(oldCss);}
 if(req.url==='/ajax'){for await(const chunk of req){}res.setHeader('Content-Type','application/json');return res.end('{"success":true,"data":{}}');}
 if(req.url.endsWith('.css')||req.url.endsWith('.js')){const file=path.join(assets,path.basename(req.url));res.setHeader('Content-Type',req.url.endsWith('.js')?'text/javascript':'text/css');return res.end(fs.readFileSync(file));}
 res.end('<!doctype html><html><meta charset="utf-8"><link rel="stylesheet" href="'+(req.url.includes('baseline')?'/baseline.css':'/portal.css')+'"><main class="mi-portal" data-mi-portal-scope="reserved"><div class="mi-web-push" data-mi-web-push data-ajax-url="/ajax" data-worker-scope="/" data-worker-url="/worker.js" data-public-key="AA">'+button+'<span data-mi-web-push-status role="status"></span></div></main><script src="/portal-push.js"></script></html>');
});
(async()=>{await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const inspect=async(baseline,permission,width)=>{
  const page=await browser.newPage({viewport:{width,height:600}});await page.addInitScript(initial=>{
   const notification={permission:initial,requestPermission:async()=>{notification.permission='granted';return 'granted';}};Object.defineProperty(window,'Notification',{value:notification});
   let subscription=null;const manager={getSubscription:async()=>subscription,subscribe:async()=>{subscription={endpoint:'https://example.invalid/push',toJSON:()=>({endpoint:'https://example.invalid/push'}),unsubscribe:async()=>{subscription=null;return true;}};return subscription;}};
   Object.defineProperty(navigator,'serviceWorker',{value:{register:async()=>({active:{state:'activated'},pushManager:manager})}});window.PushManager=function(){};
  },permission);
  await page.goto('http://127.0.0.1:'+server.address().port+'/'+(baseline?'baseline':''));const toggle=page.locator('[data-mi-web-push-toggle]'),label=page.locator('[data-mi-web-push-label]');
  const look=()=>toggle.evaluate(el=>{const svg=el.querySelector('svg'),style=getComputedStyle(el),rect=svg.getBoundingClientRect();return {svg:svg.outerHTML,width:rect.width,height:rect.height,color:style.color,background:style.backgroundColor,border:style.border,shadow:style.boxShadow,opacity:style.opacity,slash:getComputedStyle(svg.querySelector('.mi-web-push__slash')).display,pressed:el.getAttribute('aria-pressed'),label:el.getAttribute('aria-label'),title:el.title,disabled:el.disabled};});
  const states=[await look()];if(!baseline)assert.equal(await label.isVisible(),false);
  if(permission!=='denied'){
   await toggle.click();await page.waitForFunction(()=>document.querySelector('[data-mi-web-push-toggle]').getAttribute('aria-pressed')==='true');states.push(await look());if(!baseline)assert.equal(await label.isVisible(),false);
   await toggle.click();await page.waitForFunction(()=>document.querySelector('[data-mi-web-push-toggle]').getAttribute('aria-pressed')==='false');states.push(await look());
  }
  await page.close();return states;
 };
 for(const width of [1280,390])for(const permission of ['default','denied'])assert.deepEqual(await inspect(false,permission,width),await inspect(true,permission,width));
 console.log('PASS bell: captions hidden on desktop/mobile, unchanged SVG, dimensions, colors, slash, active/inactive/blocked states and accessible commands.');
}finally{await browser.close();server.close();}})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
