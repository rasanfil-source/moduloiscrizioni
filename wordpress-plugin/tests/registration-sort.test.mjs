import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const script = readFileSync(new URL('../modulo-iscrizioni/assets/portal-management.js', import.meta.url), 'utf8');
const preferences = script.slice(script.indexOf('  const registrationSortOptions'), script.indexOf('  function registrationSortMenu'));

test('payment filter excludes unknown totals but preserves known balances with inconsistent deposits', () => {
  const filter = script.match(/const depositMatch=x=>[^\n]+/)[0];
  const accepts = (row, deposit = 'settled') => runInNewContext(filter + '\ndepositMatch(row)', {row, listContext:{deposit}});
  const row = {status:'PENDING_PAYMENT', balance:0, paid:0, totals_known:false, economics_known:false};
  assert.equal(accepts(row), false);
  assert.equal(accepts(row, ''), true);
  assert.equal(accepts({...row, totals_known:true}), true);
  assert.equal(accepts({...row, totals_known:true, balance:10000}), false);
  assert.equal(accepts({...row, totals_known:true, balance:10000}, 'unpaid'), true);
  assert.equal(accepts({status:'CONFIRMED', balance:0, paid:10000}), true);
});

test('registration ordering defaults to newest first and retains explicit choices', () => {
  for (const stored of [null, '', 'invalid', 'name', 'created_at']) {
    const localStorage = { getItem: () => stored };
    assert.equal(runInNewContext(preferences + '\nreadRegistrationSort()', { localStorage }), stored === 'name' ? 'name' : 'created_at');
  }
  const localStorage = { getItem() { throw new Error('Storage disabled'); } };
  assert.equal(runInNewContext(preferences + '\nreadRegistrationSort()', { localStorage }), 'created_at');
  let saved;
  runInNewContext(preferences + '\nsaveRegistrationSort("name")', { localStorage: { setItem: (key, value) => { saved = [key, value]; } } });
  assert.deepEqual(saved, ['mi-registration-sort', 'name']);
});

test('both event views initialize from the same sorting preference', () => {
  assert.match(script, /allSort=readRegistrationSort\(\)/);
  assert.match(script, /let listContext=\{[^\n]*sort:readRegistrationSort\(\)/);
  assert.match(script, /if\(listEvent!==event\)\{listContext=\{[^\n]*sort:readRegistrationSort\(\)/);
});

test('attendance switches to surname before paging without overwriting the saved preference', () => {
  let writes=0;
  const context={sort:'created_at',direction:'desc',shown:90,query:'Rossi'};
  const env={context,localStorage:{getItem:()=> 'created_at',setItem:()=>writes++}};
  const apply=available=>runInNewContext(preferences+'\napplyAttendanceSort(context,'+available+')',{...env});
  apply(false);
  assert.equal(context.sort,'created_at');
  assert.equal(context.shown,90);
  apply(true);
  assert.equal(context.sort,'name');
  assert.equal(context.direction,'asc');
  assert.equal(context.shown,30);
  assert.equal(context.query,'Rossi');
  // Refreshing or saving attendance must preserve a subsequent manual choice.
  context.sort='created_at';context.direction='desc';context.shown=60;
  apply(true);
  assert.equal(context.sort,'created_at');
  assert.equal(context.shown,60);
  apply(false);
  assert.equal(context.sort,'created_at');
  assert.equal(context.direction,'desc');
  apply(true);
  assert.equal(context.sort,'name');
  assert.equal(writes,0);
  const initial={sort:'created_at',direction:'desc',shown:30};
  runInNewContext(preferences+'\napplyAttendanceSort(context,true)',{...env,context:initial});
  assert.equal(initial.sort,'name');
  assert.equal(initial.direction,'asc');
  assert.ok(script.indexOf('applyAttendanceSort(listContext,data.attendance_availability?.available)')<script.indexOf("request('list_page'"));
});
