<?php

declare(strict_types=1);

function mi_workspace_capabilities(array $response): array {
	$required = array(
		'direct_projection' => true,
		'standalone'        => true,
		'projection_pull'   => true,
		'central_workbook'  => false,
	);
	$errors = array();
	if (($response['ok'] ?? false) !== true) {
		$errors[] = 'La Web App non ha restituito ok=true.';
	}
	foreach ($required as $key => $expected) {
		if (!array_key_exists($key, $response) || $response[$key] !== $expected) {
			$errors[] = sprintf('%s deve essere %s.', $key, $expected ? 'true' : 'false');
		}
	}
	return $errors;
}

function mi_workspace_request_body(string $secret): string {
	$action = 'STATO_SCHEMA';
	$timestamp = (int) floor(microtime(true) * 1000);
	$nonce = bin2hex(random_bytes(16));
	$payload = '{"source":"WORDPRESS"}';
	$payloadHash = hash('sha256', $payload);
	$message = $timestamp . "\n" . $nonce . "\n" . $action . "\n" . $payloadHash;
	$signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $message, $secret, true)), '+/', '-_'), '=');
	return json_encode(array(
		'protocollo'      => 2,
		'timestamp'       => $timestamp,
		'nonce'           => $nonce,
		'action'          => $action,
		'payload_firmato' => $payload,
		'payload_hash'    => $payloadHash,
		'signature'       => $signature,
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mi_workspace_http(string $url, string $body): array {
	$marker = "\n__MI_HTTP__";
	$command = array(
		'curl.exe', '--silent', '--show-error', '--location', '--max-redirs', '3',
		'--proto', '=https', '--proto-redir', '=https', '--max-time', '25',
		'--header', 'Content-Type: application/json; charset=utf-8',
		'--request', 'POST', '--data-binary', $body,
		'--write-out', $marker . '%{http_code}\n__MI_URL__%{url_effective}',
		$url,
	);
	$descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$process = proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true));
	if (!is_resource($process)) throw new RuntimeException('Impossibile avviare curl.exe.');
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($process);
	if ($exit !== 0) throw new RuntimeException('Richiesta non riuscita: ' . trim((string) $error));
	$position = strrpos((string) $output, $marker);
	if ($position === false) throw new RuntimeException('Risposta HTTP non riconoscibile.');
	$metadata = substr((string) $output, $position + strlen($marker));
	$responseBody = substr((string) $output, 0, $position);
	if (!preg_match('/^(\d{3})\n__MI_URL__(https:\/\/[^\s]+)$/', $metadata, $matches)) throw new RuntimeException('Metadati HTTP non riconoscibili.');
	$host = strtolower((string) parse_url($matches[2], PHP_URL_HOST));
	if ($host !== 'script.google.com' && $host !== 'script.googleusercontent.com' && !str_ends_with($host, '.googleusercontent.com')) {
		throw new RuntimeException('Reindirizzamento fuori dai domini Google consentiti.');
	}
	return array('status' => (int) $matches[1], 'body' => $responseBody, 'url' => $matches[2]);
}

function mi_workspace_preflight_main(array $argv): int {
	$options = getopt('', array('url:', 'help'));
	if (isset($options['help'])) {
		fwrite(STDOUT, "Imposta MI_WORKSPACE_SECRET e usa --url=https://script.google.com/macros/s/.../exec\n");
		return 0;
	}
	$url = trim((string) ($options['url'] ?? getenv('MI_WORKSPACE_URL') ?: ''));
	$secret = (string) (getenv('MI_WORKSPACE_SECRET') ?: '');
	if (!preg_match('#^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$#', $url) || strlen($secret) < 32) {
		fwrite(STDERR, "Configura un URL Web App Google valido e MI_WORKSPACE_SECRET (almeno 32 caratteri).\n");
		return 2;
	}
	try {
		$http = mi_workspace_http($url, mi_workspace_request_body($secret));
		$decoded = json_decode($http['body'], true, 32, JSON_THROW_ON_ERROR);
		if (!is_array($decoded)) throw new RuntimeException('La risposta non è un oggetto JSON.');
		$errors = mi_workspace_capabilities($decoded);
		if ($http['status'] !== 200 && ($decoded['ok'] ?? false) !== true) $errors[] = 'Stato HTTP inatteso: ' . $http['status'] . '.';
		if ($errors) {
			fwrite(STDERR, "Deployment NON idoneo:\n- " . implode("\n- ", $errors) . "\n");
			return 5;
		}
		fwrite(STDOUT, sprintf("Deployment idoneo: schema %s, autonomo, proiezione diretta, DB_MODULI escluso.\n", (string) ($decoded['schema_version'] ?? 'non dichiarato')));
		return 0;
	} catch (Throwable $error) {
		fwrite(STDERR, 'Controllo non completato: ' . $error->getMessage() . "\n");
		return 3;
	}
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
	exit(mi_workspace_preflight_main($argv));
}
