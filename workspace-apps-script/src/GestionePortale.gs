/** Servizi privati chiamati dal proxy WordPress dopo autorizzazione sull'evento. */
function versioneGestione_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, JSON.stringify(value), Utilities.Charset.UTF_8).map(function(b){return ('0'+(b&255).toString(16)).slice(-2);}).join('');
}
