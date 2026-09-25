param([switch]$SkipPhp, [string]$PhpPath = '')

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Push-Location $root
try {
    if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
        throw 'Node.js non disponibile. Installare Node.js 22 o successivo.'
    }
    node --test workspace-apps-script/tests/*.test.mjs wordpress-plugin/tests/*.test.mjs
    if ($LASTEXITCODE -ne 0) { throw 'Suite Node non superata.' }
    node tools/asset-build/build.cjs --check
    if ($LASTEXITCODE -ne 0) { throw 'Asset minificati non aggiornati.' }
    node tools/asset-build/verify.cjs
    if ($LASTEXITCODE -ne 0) { throw 'Verifica degli asset non superata.' }
    & (Join-Path $PSScriptRoot 'check-sanitization.ps1')
    if ($LASTEXITCODE -ne 0) { throw 'Controllo di sanitizzazione non superato.' }

    if ($SkipPhp) {
        Write-Warning 'Controlli PHP omessi esplicitamente.'
        return
    }
    if ($PhpPath) {
        $phpExecutable = (Resolve-Path -LiteralPath $PhpPath -ErrorAction Stop).Path
    } else {
        $phpCommand = Get-Command php -ErrorAction SilentlyContinue
        if (-not $phpCommand) { throw 'PHP CLI non disponibile. Fornire -PhpPath con PHP 8.3 oppure usare -SkipPhp solo per verifiche parziali.' }
        $phpExecutable = $phpCommand.Source
    }
    $phpVersionId = & $phpExecutable -r 'echo PHP_VERSION_ID;'
    if ($LASTEXITCODE -ne 0 -or [int]$phpVersionId -lt 80300) { throw 'PHP CLI 8.3 o successivo richiesto per la verifica locale.' }
    $phpArguments = @()
    $loadedModules = & $phpExecutable -m
    if ($LASTEXITCODE -ne 0) { throw 'PHP CLI non eseguibile.' }
    if ($loadedModules -notcontains 'mbstring') {
        $extensionDirectory = Join-Path (Split-Path -Parent $phpExecutable) 'ext'
        if (-not (Test-Path -LiteralPath (Join-Path $extensionDirectory 'php_mbstring.dll'))) { throw 'Estensione PHP mbstring non disponibile.' }
        $phpArguments = @('-n', '-d', "extension_dir=$extensionDirectory", '-d', 'extension=mbstring')
    }
    foreach ($file in (rg --files wordpress-plugin/modulo-iscrizioni -g '*.php')) {
        & $phpExecutable @phpArguments -l $file | Out-Host
        if ($LASTEXITCODE -ne 0) { throw "Sintassi PHP non valida: $file" }
    }
    $phpTests = @(
        'portal-payment-report.php', 'communication-confirmation.php',
        'public-balance.php', 'public-balance-model.php', 'operational-profile.php', 'workspace-deployment-preflight.php',
        'option-rules.php', 'sheet-open.php', 'event-projection-transfer.php', 'rest-projection.php', 'workspace-schema.php', 'attendance-report.php',
		'group-attendance.php', 'sheet-organization.php', 'audit-regressions.php', 'audit-dates-access-cache.php',
		'event-wizard.php', 'management-list.php', 'management-page-sql.php', 'payment-counts.php', 'code-image.php', 'assets.php',
        'registration-failures.php', 'email-transaction-failures.php', 'event-cancellation-retry.php', 'attendance-idempotency.php', 'publication-stored-config.php'
    )
    foreach ($test in $phpTests) {
        & $phpExecutable @phpArguments (Join-Path 'wordpress-plugin/tests' $test) | Out-Host
        if ($LASTEXITCODE -ne 0) { throw "Test PHP non superato: $test" }
    }
    Write-Output 'Verifica locale completata.'
} finally {
    Pop-Location
}
