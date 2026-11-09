param([string]$PhpPath = '.tmp/php-runtime/php.exe')
$ErrorActionPreference = 'Stop'
$testRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$testPhp = (Resolve-Path -LiteralPath (Join-Path $testRoot $PhpPath)).Path
$testExtensions = Join-Path (Split-Path -Parent $testPhp) 'ext'
Push-Location $testRoot
try {
 & $testPhp -n -d "extension_dir=$testExtensions" -d extension=mysqli -d extension=mbstring wordpress-plugin/tests/email-bulk-integration.php
 if ($LASTEXITCODE -ne 0) { throw 'Campagna sintetica email non superata.' }
} finally { Pop-Location }
