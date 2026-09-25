param()
$ErrorActionPreference = 'Stop'
$releaseVersion = '3.26.175'
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$dist = (Resolve-Path 'dist').Path
if ((Get-Content -Raw -LiteralPath (Join-Path $source 'modulo-iscrizioni.php')) -notmatch ('Version:\s*' + [regex]::Escape($releaseVersion))) { throw 'Versione plugin inattesa.' }
node tools/asset-build/build.cjs --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
Add-Type -AssemblyName System.IO.Compression.FileSystem
$publicFiles = @(git ls-files -- wordpress-plugin/modulo-iscrizioni)
if ($LASTEXITCODE -ne 0 -or !$publicFiles.Count) { throw 'Elenco file pubblici non disponibile.' }
$publicFiles = @($publicFiles + 'wordpress-plugin/modulo-iscrizioni/templates/portal-payment-report.php' | Sort-Object -Unique)
if ($publicFiles -match 'public-balance-config\.json$') { throw 'Configurazione privata tracciata.' }
$destinations = @((Join-Path $dist "modulo-iscrizioni-$releaseVersion.zip"), (Join-Path $dist "modulo-iscrizioni-$releaseVersion-pubblico.zip"))
foreach ($destination in $destinations) { if (Test-Path -LiteralPath $destination) { throw "File già esistente: $destination" } }
Compress-Archive -LiteralPath $source -DestinationPath $destinations[0] -CompressionLevel Optimal
$publicArchive = [IO.Compression.ZipFile]::Open($destinations[1], [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $publicFiles) {
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($publicArchive, (Join-Path (Get-Location) $file), $file.Substring(17), [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $publicArchive.Dispose() }
foreach ($destination in $destinations) {
    $archive = [IO.Compression.ZipFile]::OpenRead($destination)
    try {
        $entries = @($archive.Entries | Where-Object { $_.Name })
        if (!($entries | Where-Object { $_.FullName.Replace('\','/') -eq 'modulo-iscrizioni/templates/portal-payment-report.php' })) { throw 'Template report mancante.' }
        $expected = if ($destination -eq $destinations[0]) { @(Get-ChildItem -LiteralPath $source -Recurse -File).Count } else { $publicFiles.Count }
        if ($entries.Count -ne $expected) { throw 'Numero file ZIP errato.' }
        foreach ($entry in $entries) {
            $name = $entry.FullName.Replace('\','/')
            if (!$name.StartsWith('modulo-iscrizioni/')) { throw 'Root ZIP errata.' }
            $local = Join-Path $source $name.Substring(18)
            $stream = $entry.Open(); $sha = [Security.Cryptography.SHA256]::Create()
            try { $hash = [Convert]::ToHexString($sha.ComputeHash($stream)) } finally { $stream.Dispose(); $sha.Dispose() }
            if ($hash -ne (Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash) { throw "Hash errato: $name" }
        }
        Write-Output "ZIP verificato: $($entries.Count) file identici ai sorgenti."
    } finally { $archive.Dispose() }
    $checksum = Get-FileHash -LiteralPath $destination -Algorithm SHA256
    "$($checksum.Hash.ToLowerInvariant())  $([IO.Path]::GetFileName($destination))" | Set-Content -LiteralPath ($destination + '.sha256') -Encoding utf8
}
Write-Output 'Rilascio solo WordPress: nessuna modifica o distribuzione Google Apps Script.'
