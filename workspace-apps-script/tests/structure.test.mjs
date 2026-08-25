import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const sourceDir = new URL('../src/', import.meta.url);
const files = (await readdir(sourceDir)).filter((name) => name.endsWith('.gs'));
const sources = Object.fromEntries(await Promise.all(files.map(async (name) => [name, await readFile(new URL(name, sourceDir), 'utf8')])));
const combined = Object.values(sources).join('\n');

test('tutti i file Apps Script hanno sintassi valida', () => {
  for (const [name, source] of Object.entries(sources)) assert.doesNotThrow(() => new vm.Script(source, { filename: name }));
});

test('il setup dichiara tutte le schede operative', () => {
  for (const name of ['Config', 'Events', 'Registrations', 'Participants', 'PaymentIntake', 'Payments', 'EmailOutbox', 'AuditLog']) assert.match(combined, new RegExp(name));
  assert.match(sources['Setup.gs'], /setupWorkbook/);
  assert.match(sources['Setup.gs'], /requireValueInList/);
});

test('il web endpoint fallisce chiuso e richiede HMAC e anti replay', () => {
  assert.match(sources['Config.gs'], /MI_SHARED_SECRET/);
  assert.match(sources['WebApp.gs'], /computeHmacSha256Signature/);
  assert.match(sources['WebApp.gs'], /REPLAYED_REQUEST/);
  assert.match(sources['WebApp.gs'], /envelope\.action === 'PING'/);
  assert.doesNotMatch(combined, /API_KEY\s*=\s*['"]\s*['"]/);
});

test('il sorgente non incorpora destinazioni o coordinate operative', () => {
  assert.doesNotMatch(combined, /docs\.google\.com\/spreadsheets\/d\//);
  assert.doesNotMatch(combined, /@[a-z0-9.-]+\.[a-z]{2,}/i);
  assert.doesNotMatch(combined, /\bIT\d{2}[A-Z]\d{10,}/);
});

test('i pagamenti ammettono solo bonifico carta e contanti senza dati carta', () => {
  assert.match(sources['Config.gs'], /BANK_TRANSFER/);
  assert.match(sources['Config.gs'], /CARD/);
  assert.match(sources['Config.gs'], /CASH/);
  assert.match(sources['Payments.gs'], /containsCardNumberLike_/);
  assert.match(sources['Payments.gs'], /source_intake_id/);
});
