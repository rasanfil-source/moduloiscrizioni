import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const model = readFileSync(new URL('../modulo-iscrizioni/includes/class-mi-modello-email.php', import.meta.url), 'utf8');
const shipment = readFileSync(new URL('../modulo-iscrizioni/includes/class-mi-spedizione-email.php', import.meta.url), 'utf8');
const portal = readFileSync(new URL('../modulo-iscrizioni/includes/class-mi-portal.php', import.meta.url), 'utf8');

assert.match(model, /function stile_default/);
assert.match(model, /function stile_risolto/);
assert.match(model, /\$event_image \?:/);
assert.match(model, /\$group_banner \?: \$style\['banner_url'\]/);
assert.match(model, /layout' => 'INSTITUTIONAL'/);
assert.match(model, /function componi_html_istituzionale/);
assert.match(model, /border-radius:50%/);
assert.match(model, /background-image:url/);
assert.match(shipment, /crea_istantanea_istituzionale/);
assert.match(portal, /_mi_email_style/);
assert.match(portal, /group_email_identity_enabled/);
assert.match(model, /mi_email_event_banner_enabled/);
assert.match(portal, /function event_email_appearance_fields/);
assert.match(portal, /str_replace\( '<details class="mi-confirmation-email">'/);

console.log('Gerarchia grafica e separazione istituzionale verificate.');
