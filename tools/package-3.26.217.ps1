$ErrorActionPreference = 'Stop'
$releaseVersion = '3.26.217'
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$archivePath = Join-Path (Resolve-Path 'dist').Path "modulo-iscrizioni-$releaseVersion.zip"
if (Test-Path -LiteralPath $archivePath) { throw 'Archivio già esistente: non sovrascrivere senza verifica.' }
if ((Get-Content -Raw -LiteralPath (Join-Path $source 'modulo-iscrizioni.php')) -notmatch ('Version:\s*' + [regex]::Escape($releaseVersion))) { throw 'Versione plugin inattesa.' }
node tools/asset-build/build.cjs --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
Compress-Archive -LiteralPath $source -DestinationPath $archivePath -CompressionLevel Optimal
$archive = [IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    $files = @(Get-ChildItem -LiteralPath $source -Recurse -File)
    $entries = @($archive.Entries | Where-Object { $_.Name })
    if ($entries.Count -ne $files.Count) { throw 'Numero file archivio diverso dai sorgenti.' }
    foreach ($file in $files) {
        $entryName = 'modulo-iscrizioni/' + [IO.Path]::GetRelativePath($source, $file.FullName).Replace('\', '/')
        $entry = $archive.GetEntry($entryName)
        if (-not $entry) { throw "File mancante: $entryName" }
        $stream = $entry.Open()
        try { $actual = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($stream)) }
        finally { $stream.Dispose() }
        if ($actual -ne (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash) { throw "Contenuto diverso: $entryName" }
    }
    Write-Output "ZIP verificato: $($entries.Count) file corrispondenti ai sorgenti."
} finally { $archive.Dispose() }
Get-FileHash -LiteralPath $archivePath -Algorithm SHA256
