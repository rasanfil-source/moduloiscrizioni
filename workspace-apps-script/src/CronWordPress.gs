/** Avvia il cron WordPress quando l'hosting disabilita l'avvio tramite visite.
 * Esecuzione separata dalla proiezione: non tenere lock mentre WordPress richiama GAS.
 */
function avviaCronWordPress() {
  const endpoint = String(PropertiesService.getScriptProperties().getProperty('MI_WORDPRESS_COMMAND_URL') || '');
  const base = endpoint.replace(/\/wp-json\/modulo-iscrizioni\/v1\/workspace\/commands\/?$/, '/');
  if (base === endpoint || !/^https:\/\/[^/?#]+\/$/.test(base)) throw new Error('Collegamento WordPress non valido.');
  const response = UrlFetchApp.fetch(base + 'wp-cron.php', {method:'get',followRedirects:false,muteHttpExceptions:true});
  const code = response.getResponseCode();
  if (code < 200 || code >= 300) throw new Error('Avvio cron WordPress: HTTP ' + code);
  return {ok:true,http_status:code};
}

function attivaCronWordPress() {
  const exists = ScriptApp.getProjectTriggers().some(t=>t.getHandlerFunction()==='avviaCronWordPress');
  if (!exists) ScriptApp.newTrigger('avviaCronWordPress').timeBased().everyMinutes(5).create();
  return {ok:true,creato:!exists};
}
