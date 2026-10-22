import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, existsSync} from 'node:fs';
import {createHash, createHmac, randomUUID} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const root = new URL('../../', import.meta.url);
const secret = 'synthetic-workspace-secret-32-characters';
function send(payload) {
  let envelope;
  const context = vm.createContext({
    PropertiesService: {getScriptProperties: () => ({getProperty: () => 'https://example.test/commands'})},
    ottieniSegretoScript_: () => secret,
    Utilities: {
      getUuid: randomUUID, DigestAlgorithm: {SHA_256:'sha256'}, Charset: {UTF_8:'utf8'},
      computeDigest: (algorithm, value) => [...createHash(algorithm).update(value).digest()].map(v => v > 127 ? v - 256 : v),
      computeHmacSha256Signature: (value, key) => [...createHmac('sha256',key).update(value).digest()],
      base64EncodeWebSafe: value => Buffer.from(value).toString('base64url')
    },
    UrlFetchApp: {fetch: (_url, options) => {
      envelope = JSON.parse(options.payload);
      return {getResponseCode: () => 200, getContentText: () => '{"ok":true}'};
    }}
  });
  for (const file of ['Core.gs','Segreteria.gs']) vm.runInContext(readFileSync(new URL(`workspace-apps-script/src/${file}`,root),'utf8'),context);
  context.inviaComandoWordPress_('QUEUE_OPERATIONAL_EMAILS',payload);
  return envelope;
}
const payload = {message:'Città 🌍 / prova', extra:{}, list:[], nested:{empty:{}, values:[{},[]]}, amount:1};
test('GAS commands sign the exact serialized payload hash', () => {
  const e = send(payload);
  assert.equal(e.protocollo,2);
  assert.equal(e.payload,undefined);
  assert.deepEqual(JSON.parse(e.payload_firmato),payload);
  assert.equal(e.payload_hash,createHash('sha256').update(e.payload_firmato).digest('hex'));
  assert.equal(e.signature,createHmac('sha256',secret).update(`${e.timestamp}\n${e.nonce}\n${e.action}\n${e.payload_hash}`).digest('base64url'));
});
const php = process.env.MI_TEST_PHP || fileURLToPath(new URL('.tmp/php-runtime/php.exe',root));
test('Actual GAS envelope is accepted and dispatched by PHP', {skip:!existsSync(php)}, () => {
  const result = spawnSync(php,[fileURLToPath(new URL('wordpress-plugin/tests/workspace-command-envelope.php',root)),'--stdin'],{input:JSON.stringify(send(payload)),encoding:'utf8'});
  assert.equal(result.status,0,result.stdout+result.stderr);
  const received = JSON.parse(result.stdout).received;
  assert.equal(received.message,payload.message);
  assert.deepEqual(received.nested.values,[[],[]]); // PHP arrays after authentication.
  assert.equal(received.amount,1);
});
