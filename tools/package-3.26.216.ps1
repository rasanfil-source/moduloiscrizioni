$ErrorActionPreference = 'Stop'
$releaseVersion = '3.26.216'
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$dist = (Resolve-Path 'dist').Path
if ((Get-Content -Raw -LiteralPath (Join-Path $source 'modulo-iscrizioni.php')) -notmatch ('Version:\s*' + [regex]::Escape($releaseVersion))) { throw 'Versione plugin inattesa.' }
node tools/asset-build/build.cjs --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
Compress-Archive -LiteralPath $source -DestinationPath (Join-Path $dist "modulo-iscrizioni-$releaseVersion.zip") -CompressionLevel Optimal
Write-Output "Archivio preparato: modulo-iscrizioni-$releaseVersion.zip. Nessuna installazione sul sito eseguita."
