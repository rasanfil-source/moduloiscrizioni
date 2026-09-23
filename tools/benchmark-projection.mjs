// Synthetic CPU/parse benchmark. Google API and network latency are excluded.
import assert from 'node:assert/strict';
import {performance} from 'node:perf_hooks';
import {projectionContext,projectionFixture} from '../workspace-apps-script/tests/helpers/projection-performance.mjs';
const baseline=process.argv[2];
for(const count of [100,1000,5000]){
 const projection=projectionFixture(count),results=[];
 for(const [name,dir] of baseline?[['before',baseline],['after',null]]:[['current',null]]){
  const fixture=projectionContext(dir),times=[];let output;
  for(let run=0;run<5;run++){
   fixture.reset();const start=performance.now();output=fixture.context.generaVistaDaProiezioneDiretta_(projection);times.push(performance.now()-start);
  }
  times.sort((a,b)=>a-b);results.push({name,median_ms:Math.round(times[2]*100)/100,json_parses:fixture.parses(),output:JSON.stringify(output)});
 }
 if(results.length===2)assert.equal(results[0].output,results[1].output);
 console.log(JSON.stringify({participants:count,rows_equal:true,results:results.map(({output,...rest})=>rest)}));
}
