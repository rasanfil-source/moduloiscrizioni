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
        'email-model-audit.php', 'email-access-audit.php', 'email-jobs-audit.php', 'email-progress-retention.php', 'email-event-retention.php', 'email-recipients-audit.php', 'email-outcomes-audit.php', 'audit-comproved-email.php', 'audit-schema-index.php', 'audit-32755.php', 'payment-people.php',
        'travel.php', 'demo-services.php', 'demo-cleanup.php', 'event-output-links.php',
        'audit-275-rooms.php', 'audit-275-email-waitlist.php', 'audit-275-editor.php', 'email-regeneration.php', 'php-event-activity-migration.php',
        'group-links.php', 'public-slug.php',
        'audit-263-regressions.php', 'event-first-save.php', 'email-logo-override.php', 'deletion-preserve-file.php', 'event-qr.php', 'operational-email-deadline.php',
        'room-swap-entitlement.php', 'payment-history-date.php',
        'public-balance-card-deposit.php',
        'accommodation-deadline.php', 'none-service-pricing.php', 'email-requeue.php', 'email-newlines.php', 'email-body-typography.php', 'event-card-layout.php',
        'economic-read-consistency.php', 'portal-waitlist-cancellation.php', 'management-page-consistency.php', 'public-balance-one-scope.php', 'management-one-scope.php',
        'public-balance-deposit-consistency.php', 'event-duplication-cancellation.php', 'custom-date-fields.php',
        'option-key-duplicates.php', 'public-balance-deadline-receipt.php', 'management-deposit-totals.php', 'email-queue-fairness.php',
        'payment-date-filter.php', 'deposit-consistency.php', 'workspace-command-envelope.php',
        'audit-confirmed-admin.php', 'public-balance-audit-regressions.php', 'admin-economics.php', 'email-group-identity.php',
        'portal-payment-report.php', 'communication-confirmation.php', 'communication-bold.php', 'communication-cancellation-sample.php', 'communication-personalization.php', 'cancellation-demo.php', 'cancellation-diagnostics.php', 'communication-events-loading.php', 'registration-timestamps.php', 'registration-timestamps-management.php', 'registration-timestamps-projection.php', 'portal-pwa.php', 'portal-loading.php', 'email-background.php', 'management-overview.php', 'management-overview-access.php',
        'public-balance.php', 'public-balance-model.php', 'operational-profile.php', 'workspace-deployment-preflight.php',
        'option-rules.php', 'sheet-open.php', 'event-projection-transfer.php', 'rest-projection.php', 'workspace-schema.php', 'attendance-report.php',
		'group-attendance.php', 'sheet-organization.php', 'audit-regressions.php', 'audit-dates-access-cache.php',
		'reopened-payment-deadline.php', 'management-cross-event.php', 'percentage-deposit-unit.php',
        'event-wizard.php', 'event-transport-save.php', 'management-list.php', 'management-page-sql.php', 'payment-counts.php', 'code-image.php', 'assets.php',
        'registration-failures.php', 'email-transaction-failures.php', 'booking-update-email.php', 'portal-management-http-status.php', 'event-cancellation-retry.php', 'attendance-idempotency.php', 'publication-stored-config.php',
        'audit-registration-regressions.php', 'audit-outbox-recovery.php', 'audit-publication-regressions.php', 'payment-ledger.php'
    )
    foreach ($test in $phpTests) {
        & $phpExecutable @phpArguments (Join-Path 'wordpress-plugin/tests' $test) | Out-Host
        if ($LASTEXITCODE -ne 0) { throw "Test PHP non superato: $test" }
    }
    # La regressione push esegue la cifratura reale, con trasporto HTTP simulato.
    $pushArguments = @($phpArguments)
    $effectiveModules = & $phpExecutable @phpArguments -m
    if ($effectiveModules -notcontains 'openssl') {
        $extensionDirectory = Join-Path (Split-Path -Parent $phpExecutable) 'ext'
        if (-not (Test-Path -LiteralPath (Join-Path $extensionDirectory 'php_openssl.dll'))) { throw 'Estensione PHP openssl richiesta per verificare i retry push.' }
        $pushArguments += @('-d', "extension_dir=$extensionDirectory", '-d', 'extension=openssl')
    }
    $previousOpenSslConf = $env:OPENSSL_CONF
    try {
        $env:OPENSSL_CONF = Join-Path $root 'wordpress-plugin/tests/openssl.cnf'
        & $phpExecutable @pushArguments 'wordpress-plugin/tests/audit-275-push.php' | Out-Host
        if ($LASTEXITCODE -ne 0) { throw 'Regressione push non superata.' }
    } finally { $env:OPENSSL_CONF = $previousOpenSslConf }
    Write-Output 'Verifica locale completata.'
} finally {
    Pop-Location
}
