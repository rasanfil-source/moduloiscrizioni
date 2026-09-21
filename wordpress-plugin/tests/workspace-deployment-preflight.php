<?php

require_once dirname(__DIR__, 2) . '/tools/verifica-deployment-workspace.php';

$valid = array(
	'ok'                => true,
	'direct_projection' => true,
	'standalone'        => true,
	'projection_pull'   => true,
	'central_workbook'  => false,
);
if (array() !== mi_workspace_capabilities($valid)) throw new RuntimeException('Valid deployment rejected.');
foreach (array_keys($valid) as $key) {
	$invalid = $valid;
	$invalid[$key] = !$invalid[$key];
	if (array() === mi_workspace_capabilities($invalid)) throw new RuntimeException('Invalid capability accepted: ' . $key);
}
$body = json_decode(mi_workspace_request_body(str_repeat('s', 32)), true, 32, JSON_THROW_ON_ERROR);
if (2 !== $body['protocollo'] || 'STATO_SCHEMA' !== $body['action']) throw new RuntimeException('Invalid preflight envelope.');
if (hash('sha256', $body['payload_firmato']) !== $body['payload_hash']) throw new RuntimeException('Invalid payload hash.');
if (!preg_match('/^[A-Za-z0-9_-]+$/', $body['signature'])) throw new RuntimeException('Invalid URL-safe signature.');

echo "PASS: controllo PHP del deployment autonomo e della firma.\n";
