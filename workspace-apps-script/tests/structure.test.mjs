import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const sourceDir = new URL('../src/', import.meta.url);
const files = (await readdir(sourceDir)).filter((name) => name.endsWith('.gs'));
const sources = Object.fromEntries(await Promise.all(files.map(async (name) => [name, await readFile(new URL(name, sourceDir), 'utf8')])));
const combined = Object.values(sources).join('\n');
const segreteriaHtml = await readFile(new URL('Segreteria.html', sourceDir), 'utf8');

test('tutti i file Apps Script hanno sintassi valida', () => {
  for (const [name, source] of Object.entries(sources)) assert.doesNotThrow(() => new vm.Script(source, { filename: name }));
  const embeddedScript = segreteriaHtml.match(/<script>([\s\S]*?)<\/script>/i)?.[1].replace(/<\?!=[\s\S]*?\?>/g, 'TEST') || '';
  assert.doesNotThrow(() => new vm.Script(embeddedScript, { filename: 'Segreteria.html' }));
});

test('il setup dichiara tutte le schede operative', () => {
  for (const name of ['Configurazione', 'Eventi', 'Iscrizioni', 'Partecipanti', 'Inserimento pagamenti', 'Pagamenti', 'Coda email', 'Registro controlli']) assert.match(combined, new RegExp(name));
  assert.match(sources['Setup.gs'], /configuraCartellaDiLavoro/);
  assert.match(sources['Setup.gs'], /console\.log\('Struttura aggiornata/);
  assert.match(sources['Setup.gs'], /rinominaSchedePrecedenti_/);
  assert.match(sources['Setup.gs'], /requireValueInList/);
});

test('i modelli report personalizzati sono validati e non sovrascrivono quelli standard', () => {
  assert.match(sources['Config.gs'], /REPORT_TEMPLATES:\s*'Modelli report'/);
  assert.match(sources['Report.gs'], /function elencaModelliReport/);
  assert.match(sources['Report.gs'], /function salvaModelloReport/);
  assert.match(sources['Report.gs'], /function generaReportDaModello/);
  assert.match(sources['Report.gs'], /generaElencoOperativo_/);
  assert.match(sources['Report.gs'], /'PERSONALIZZATO'/);
  assert.match(sources['Report.gs'], /normalizzaScelteReport_/);
  assert.match(sources['Report.gs'], /campiElencoOperativo_/);
  assert.match(sources['Segreteria.gs'], /const grouping = normalizzaScelteReport_/);
  assert.match(sources['Segreteria.gs'], /SOLID_MEDIUM/);
  assert.match(segreteriaHtml, /name="reportGroup"/);
  assert.match(segreteriaHtml, /name="reportOrder"/);
  assert.doesNotMatch(sources['Report.gs'], /deleteRow|clearContent|setValues/);
});

test('Workspace gestisce gruppi espliciti e migra soltanto l’intestazione storica', () => {
  assert.match(sources['Config.gs'], /GROUPS:\s*'Gruppi'/);
  assert.match(sources['Config.gs'], /'Eventi': \['id_evento', 'id_gruppo'/);
  assert.match(sources['Config.gs'], /MI_INTESTAZIONI_PRECEDENTI[\s\S]*'id_attivita'/);
  assert.match(sources['Setup.gs'], /inizializzaGruppi_/);
  assert.match(sources['Gruppi.gs'], /function elencaGruppi/);
  assert.match(sources['Gruppi.gs'], /function aggiungiGruppo/);
  assert.match(sources['Gruppi.gs'], /CREATE_GROUP/);
  assert.match(sources['Gruppi.gs'], /sincronizzaGruppiConWordPress/);
  assert.match(sources['Gruppi.gs'], /https:\\\/\\\//);
  assert.match(segreteriaHtml, /MODE==='GRUPPI'/);
  assert.match(segreteriaHtml, /function groupsUI/);
});

test('il web endpoint fallisce chiuso e richiede HMAC e anti replay', () => {
  assert.match(sources['Config.gs'], /MI_SHARED_SECRET/);
  assert.match(sources['WebApp.gs'], /computeHmacSha256Signature/);
	assert.match(sources['WebApp.gs'], /REPLAYED_REQUEST/);
	assert.match(sources['WebApp.gs'], /getScriptLock/);
	assert.match(sources['WebApp.gs'], /MI_USED_NONCES/);
	assert.match(sources['WebApp.gs'], /120000/);
  assert.match(sources['WebApp.gs'], /envelope\.action === 'PING'/);
  assert.match(sources['WebApp.gs'], /group_headers/);
  assert.match(sources['WebApp.gs'], /report_template_headers/);
  assert.doesNotMatch(combined, /API_KEY\s*=\s*['"]\s*['"]/);
});

test('il sorgente non incorpora destinazioni o coordinate operative', () => {
  assert.doesNotMatch(combined, /docs\.google\.com\/spreadsheets\/d\/[A-Za-z0-9_-]{20,}/);
  assert.match(sources['Segreteria.gs'], /spreadsheet\.getId\(\)/);
  assert.doesNotMatch(combined, /@[a-z0-9.-]+\.[a-z]{2,}/i);
  assert.doesNotMatch(combined, /\bIT\d{2}[A-Z]\d{10,}/);
});

test('i pagamenti ammettono solo bonifico carta e contanti senza dati carta', () => {
  assert.match(sources['Config.gs'], /BONIFICO/);
  assert.match(sources['Config.gs'], /CARTA/);
  assert.match(sources['Config.gs'], /CONTANTE/);
  assert.match(sources['Payments.gs'], /contienePossibileNumeroCarta_/);
  assert.match(sources['Payments.gs'], /id_inserimento_origine/);
	assert.match(sources['Payments.gs'], /FREE_ORDER/);
	assert.match(sources['Payments.gs'], /OVERPAYMENT/);
	assert.match(sources['Payments.gs'], /EXCESS_REFUND/);
});

test('le funzioni Apps Script applicative hanno nomi italiani', () => {
  const allowedPlatformFunctions = new Set(['onOpen', 'doGet', 'doPost']);
  const forbiddenNames = new Set(['setupWorkbook', 'validateSelectedPayments', 'validatePendingPayments', 'normalizeEnum_', 'normalizeText_', 'neutralizeFormula_', 'euroToCents_', 'containsCardNumberLike_', 'stableStringify_', 'makeOpaqueId_', 'jsonResponse_', 'appendAudit_', 'headerIndex_', 'rowsAsObjects_', 'getBoundSpreadsheet_', 'getRequiredSheet_', 'getScriptSecret_', 'initializeSheet_', 'initializeConfig_', 'initializePaymentValidation_', 'applySoftProtections_', 'verifyEnvelope_', 'constantTimeEquals_', 'appendRegistration_']);
  const names = [...combined.matchAll(/^function\s+([A-Za-z0-9_]+)\s*\(/gm)].map((match) => match[1]);
  for (const name of names) {
    if (allowedPlatformFunctions.has(name)) continue;
    assert.equal(forbiddenNames.has(name), false, `Nome inglese non ammesso: ${name}`);
  }
});

test('la migrazione aggiunge riepilogo economico e sistemazioni operative', () => {
	assert.match(sources['Config.gs'], /MI_SCHEMA_VERSION = '1\.8\.0'/);
  assert.match(sources['Config.gs'], /modalita_economica/);
  assert.match(sources['Config.gs'], /primo_versamento_centesimi/);
  assert.match(sources['Config.gs'], /saldo_centesimi/);
  assert.match(sources['Setup.gs'], /MI_INTESTAZIONI_PRECEDENTI/);
  assert.match(sources['WebApp.gs'], /payment_methods/);
	assert.match(sources['Config.gs'], /ACCOMMODATIONS: 'Sistemazioni'/);
});

test('la console Sheets consulta prenotazioni e genera elenchi operativi per evento', () => {
	assert.doesNotMatch(sources['Setup.gs'], /Apri segreteria/);
	assert.match(sources['Setup.gs'], /Configura elenco operativo/);
	assert.doesNotMatch(sources['Setup.gs'], /Comunicazioni operative/);
	assert.match(sources['Segreteria.gs'], /showSidebar/);
	assert.match(sources['Segreteria.gs'], /showModelessDialog/);
	assert.doesNotMatch(sources['Segreteria.gs'], /SpreadsheetApp\.create/);
	assert.doesNotMatch(sources['Segreteria.gs'], /creaIniziativaGuidata|CREATE_EVENT_DRAFT/);
	assert.match(sources['Config.gs'], /Operazioni segreteria/);
	assert.match(sources['Segreteria.gs'], /MI_SHEETS\.SECRETARY_OPERATIONS/);
	assert.match(sources['Segreteria.gs'], /MI_SHEETS\.OPERATIONAL_VIEWS/);
	assert.match(sources['Segreteria.gs'], /generaElencoOperativo_/);
	assert.match(sources['Segreteria.gs'], /cercaPrenotazioniSegreteria/);
  assert.match(sources['Segreteria.gs'], /cambiaSistemazioneSegreteria/);
  assert.match(sources['Segreteria.gs'], /Opzione dimostrativa predefinita/);
  assert.match(sources['Segreteria.gs'], /Data di nascita/);
	assert.match(sources['Segreteria.gs'], /destinatariComunicazioneOperativa_/);
	assert.match(sources['Segreteria.gs'], /statoComunicazioniOperative/);
	assert.match(sources['Segreteria.gs'], /Contatto di emergenza/);
	assert.match(sources['Segreteria.gs'], /creaUrlStampaElenco_/);
	assert.match(segreteriaHtml, /Conferma modifiche/);
	assert.match(segreteriaHtml, /Aggiungi versamento/);
	assert.match(segreteriaHtml, /posti liberi/);
	assert.match(segreteriaHtml, /allow_operational/);
});

test('le viste operative seguono il tipo di evento e comprimono il dettaglio degli incassi', () => {
	assert.match(sources['Segreteria.gs'], /function caricaVistaOperativaEvento/);
	assert.match(sources['Segreteria.gs'], /function approvaVistaOperativaEvento/);
	assert.match(sources['Segreteria.gs'], /function aggiornaDatiVistaOperativaEvento/);
	assert.match(sources['Segreteria.gs'], /function rigeneraStrutturaVistaOperativaEvento/);
	assert.match(sources['Segreteria.gs'], /function leggiVistaOperativaConservata_/);
	assert.match(sources['Segreteria.gs'], /MI_DATA_AGGIORNAMENTO/);
	assert.match(segreteriaHtml, /Approva e conserva/);
	assert.match(segreteriaHtml, /Aggiorna dati/);
	assert.match(segreteriaHtml, /Rigenera struttura/);
	assert.match(segreteriaHtml, /function formattaDataOra/);
	assert.doesNotMatch(segreteriaHtml, /\bformatDate\s*\(/);
	assert.match(sources['Segreteria.gs'], /MINIMO/);
	assert.match(sources['Segreteria.gs'], /QUOTA_UNICA/);
	assert.match(sources['Segreteria.gs'], /SERVIZI_MULTIPLI/);
	assert.match(sources['Segreteria.gs'], /VIAGGIO_COMPLESSO/);
	assert.match(sources['Segreteria.gs'], /snapshot_json/);
	assert.match(sources['Segreteria.gs'], /operational_profile/);
	assert.match(sources['Segreteria.gs'], /paid_cash/);
	assert.match(sources['Segreteria.gs'], /paid_transfer/);
	assert.match(sources['Segreteria.gs'], /paid_card/);
	assert.match(segreteriaHtml, /Navigazione segreteria/);
	assert.match(segreteriaHtml, /Dettaglio incassi/);
	assert.match(segreteriaHtml, /mostraDettagliColonne/);
	assert.match(segreteriaHtml, /Registrare.*modific/);
});

test('la segreteria essenziale apre un foglio operativo dedicato per evento', () => {
	assert.match(sources['FogliOperativi.gs'], /function apriFoglioOperativoEvento/);
	assert.match(sources['FogliOperativi.gs'], /SpreadsheetApp\.create/);
	assert.match(sources['FogliOperativi.gs'], /MI_SHEETS\.EVENT_WORKSPACES/);
	assert.match(sources['FogliOperativi.gs'], /function raggruppaColonneFoglioOperativo_/);
	assert.match(sources['FogliOperativi.gs'], /function preparaSincronizzazioneFoglioOperativo/);
	assert.match(sources['FogliOperativi.gs'], /function confermaSincronizzazioneFoglioOperativo/);
	assert.match(sources['FogliOperativi.gs'], /Il foglio è cambiato dopo l’anteprima/);
	assert.match(segreteriaHtml, /Apri il foglio operativo dell’evento/);
	assert.match(segreteriaHtml, /Controlla modifiche del foglio/);
	assert.doesNotMatch(segreteriaHtml.match(/function navigazioneSegreteria[\s\S]*?\n/)[0], /Viste operative|Camere e pullman/);
});

test('la preparazione del foglio non modifica le condivisioni e accetta il gestore assente', () => {
  const source = sources['FogliOperativi.gs'];
  assert.match(source, /normalizzaEmailGestore_\(payload\.email_gestore\)/);
  const preparazione = source.slice(0, source.indexOf('function condividiFoglioSoltantoConGestore_'));
  assert.doesNotMatch(preparazione, /addEditor|removeEditor|removeViewer|setSharing/);
  assert.match(preparazione, /payload\.email_gestore \? normalizzaEmailGestore_\(payload\.email_gestore\) : ''/);
  assert.match(source, /addEditor\(emailGestore\)/);
});

test('WordPress può preparare il foglio dell evento senza duplicarlo', () => {
	assert.match(sources['WebApp.gs'], /PREPARA_PRODUZIONI_EVENTO/);
	assert.match(sources['FogliOperativi.gs'], /function preparaProduzioniEventoDaWordPress_/);
	assert.match(sources['FogliOperativi.gs'], /String\(riga\.id_evento\) === idEvento/);
	assert.match(sources['FogliOperativi.gs'], /apriFoglioOperativoEvento\(\{ id_evento: idEvento \}\)/);
	assert.match(sources['FogliOperativi.gs'], /mode: 'PREVIEW'/);
	assert.match(sources['FogliOperativi.gs'], /Evento ' \+ idEvento \+ ' - '/);
	assert.match(sources['FogliOperativi.gs'], /function spostaFoglioAccantoAlDatabase_/);
	assert.match(sources['FogliOperativi.gs'], /DriveApp\.getFileById\(database\.getId\(\)\)/);
	assert.match(sources['FogliOperativi.gs'], /moveTo\(cartella\)/);
	assert.match(sources['FogliOperativi.gs'], /function aggiornaCollegamentiProduzioneEvento_/);
	assert.match(sources['FogliOperativi.gs'], /url_iscrizione/);
	assert.match(sources['FogliOperativi.gs'], /url_saldo/);
});

test('Workspace non espone una seconda pagina Segreteria eventi', () => {
	assert.doesNotMatch(sources['Setup.gs'], /apriSegreteriaWeb/);
	assert.doesNotMatch(sources['WebApp.gs'], /view \|\| ''\) === 'segreteria'/);
	assert.doesNotMatch(sources['WebApp.gs'], /utenteSegreteriaAutorizzato_/);
	assert.match(sources['WebApp.gs'], /service: 'modulo-iscrizioni-workspace'/);
	assert.match(sources['WebApp.gs'], /mode: 'PREVIEW'/);
});

test('lo stato individuale dei partecipanti arriva nelle schede e negli elenchi', () => {
	assert.match(sources['Config.gs'], /stato_partecipante/);
	assert.match(sources['Config.gs'], /data_annullamento/);
	assert.match(sources['WebApp.gs'], /participant\.status/);
	assert.match(sources['WebApp.gs'], /participant\.cancelled_at/);
	assert.match(sources['Segreteria.gs'], /row\.stato_partecipante/);
});

test('camere e pullman si assegnano collettivamente senza superare la capienza', () => {
	assert.match(sources['Setup.gs'], /Assegna camere e pullman/);
	assert.match(sources['Segreteria.gs'], /function apriAssegnazioniEvento/);
	assert.match(sources['Assegnazioni.gs'], /function caricaAssegnazioniEvento/);
	assert.match(sources['Assegnazioni.gs'], /function salvaAssegnazioniEvento/);
	assert.match(sources['Assegnazioni.gs'], /finalRoomCounts\[code\] > rooms\[code\]\.capacity/);
	assert.match(sources['Assegnazioni.gs'], /BULK_ASSIGNMENTS/);
	assert.match(segreteriaHtml, /Assegnazioni collettive/);
	assert.match(segreteriaHtml, /data-assignment/);
});

test('la console non richiede mai foto o scansioni dei documenti', () => {
	assert.doesNotMatch(segreteriaHtml, /type\s*=\s*["']file["']/i);
	assert.doesNotMatch(segreteriaHtml, /foto(?:grafia)?\s+(?:del|di)\s+document|scansione\s+(?:del|di)\s+document/i);
	assert.doesNotMatch(combined, /DriveApp\.createFile|Utilities\.newBlob/);
});

test('la modalità email GAS è fail-closed e sostituisce sempre il destinatario', () => {
  assert.match(sources['Email.gs'], /modalita_email/);
  assert.match(sources['Email.gs'], /MI_EMAIL_TEST_RECIPIENT/);
  assert.match(sources['Email.gs'], /MailApp\.sendEmail/);
  assert.match(sources['Email.gs'], /to:\s*recipient/);
  assert.doesNotMatch(sources['Email.gs'], /to:\s*row\.destinatario/);
});

test('la replica è riconciliante e viene confermata solo quando completa', () => {
  assert.match(sources['WebApp.gs'], /IDEMPOTENCY_CONFLICT/);
  assert.match(sources['WebApp.gs'], /participantSheet\.deleteRow/);
  assert.match(sources['WebApp.gs'], /participantCount === participants\.length/);
  assert.match(sources['WebApp.gs'], /complete:\s*complete/);
  assert.match(sources['WebApp.gs'], /existing && existing\.data_creazione/);
});

test('revisioni, consensi, tipologie e opzioni hanno colonne esplicite', () => {
  for (const token of ['id_revisione_evento', 'snapshot_json', 'id_consenso_privacy', 'id_consenso_marketing', 'opzioni_ordine_json', 'codice_tipologia', 'indice_tipologia', 'opzioni_json']) assert.match(sources['Config.gs'], new RegExp(token));
  assert.match(sources['Setup.gs'], /usesItalianPrevious \|\| usesPreviousHeaders/);
  assert.match(sources['WebApp.gs'], /marketing_accepted_at/);
});

test('APPEND_REGISTRATION replica i movimenti pagamento senza duplicarli', () => {
  assert.match(sources['WebApp.gs'], /sincronizzaPagamenti_\(orderCode, payload\.payments\)/);
  assert.match(sources['WebApp.gs'], /id_inserimento_origine/);
  assert.match(sources['WebApp.gs'], /WP\|/);
  assert.match(sources['WebApp.gs'], /kind \+ '\\|' \+ installment \+ '\\|'/);
  assert.match(sources['WebApp.gs'], /effectiveDate/);
  assert.match(sources['WebApp.gs'], /isNaN\(effectiveDate\.getTime\(\)\)/);
  assert.match(sources['WebApp.gs'], /INVALID_EFFECTIVE_AT/);
});
