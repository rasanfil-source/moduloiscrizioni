param([string]$PhpPath = '.tmp/php-runtime/php.exe', [string]$PlaywrightModule = '')
$ErrorActionPreference = 'Stop'
Push-Location (Join-Path $PSScriptRoot '..')
try {
    $campaignPhp = (Resolve-Path -LiteralPath $PhpPath).Path
    $campaignArgs = @()
    if ($IsWindows) { $campaignArgs = @('-d', ('extension_dir=' + (Join-Path (Split-Path $campaignPhp) 'ext')), '-d', 'extension=mbstring', '-d', 'extension=mysqli') }
    # Existing fixtures use only localhost:33317 and the synthetic mi_ledger_test database.
    $campaignTests = @('payment-ledger-innodb.php','management-innodb.php','payment-people-innodb.php',
        'individual-management-innodb.php','combined-services-innodb.php','attendance-inline-innodb.php',
        'accommodation-change-innodb.php','percentage-deposit-unit.php','sheet-receipts-innodb.php',
        'economic-attribution-innodb.php','room-postcommit-innodb.php')
    foreach ($campaignTest in $campaignTests) {
        Write-Output "RUN $campaignTest"
        & $campaignPhp @campaignArgs (Join-Path 'wordpress-plugin/tests' $campaignTest)
        if ($LASTEXITCODE -ne 0) { throw "Fallito: $campaignTest" }
    }
    if ($PlaywrightModule) { $env:MI_PLAYWRIGHT_MODULE = $PlaywrightModule }
    foreach ($campaignBrowser in @('management-full-event','individual-management','payment-people','accommodation-change','room-assignment','inline-payment')) {
        Write-Output "RUN browser $campaignBrowser"
        node (Join-Path 'tools' "test-$campaignBrowser-browser.cjs")
        if ($LASTEXITCODE -ne 0) { throw "Fallito browser: $campaignBrowser" }
    }
    Write-Output 'Campagna: 11 suite PHP/InnoDB e 6 suite browser completate.'
} finally { Pop-Location }
