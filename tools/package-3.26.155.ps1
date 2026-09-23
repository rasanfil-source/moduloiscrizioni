param([string]$Version = '3.26.155')
$ErrorActionPreference = 'Stop'
$version = $Version
$bootstrap = Get-Content -LiteralPath 'wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php' -Raw
if ($bootstrap -notmatch ('Version:\s*' + [regex]::Escape($version))) { throw 'La versione richiesta non coincide con il plugin.' }
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$dist = (Resolve-Path 'dist').Path
$destination = Join-Path $dist "modulo-iscrizioni-$version.zip"
if (Test-Path -LiteralPath $destination) { throw 'ZIP già esistente.' }
node tools/asset-build/build.cjs --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
node tools/prepara-codice-workspace.mjs
if ($LASTEXITCODE -ne 0) { throw 'Bundle GAS non valido.' }
Compress-Archive -LiteralPath $source -DestinationPath $destination -CompressionLevel Optimal
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [IO.Compression.ZipFile]::OpenRead($destination)
try {
    $files = @(Get-ChildItem -LiteralPath $source -Recurse -File)
    $entries = @($archive.Entries | Where-Object { $_.Name })
    if ($files.Count -ne $entries.Count) { throw 'Numero file errato.' }
    foreach ($entry in $entries) {
        $name = $entry.FullName.Replace('\','/')
        if (!$name.StartsWith('modulo-iscrizioni/')) { throw 'Root ZIP errata.' }
        $local = Join-Path $source $name.Substring(18)
        $stream = $entry.Open(); $sha = [Security.Cryptography.SHA256]::Create()
        try { $hash = [Convert]::ToHexString($sha.ComputeHash($stream)) } finally { $stream.Dispose(); $sha.Dispose() }
        if ($hash -ne (Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash) { throw "Hash errato: $name" }
    }
    Write-Output "ZIP plugin verificato: $($entries.Count) file identici ai sorgenti."
} finally { $archive.Dispose() }
$workspaceStage = Join-Path (Resolve-Path '.tmp').Path "workspace-package-$version"
New-Item -ItemType Directory -Path $workspaceStage -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $dist "Codice-Workspace-Progetto-$version.gs") -Destination (Join-Path $workspaceStage 'Codice.gs')
Copy-Item -LiteralPath 'workspace-apps-script/appsscript.json' -Destination (Join-Path $workspaceStage 'appsscript.json')
$workspaceZip = Join-Path $dist "Workspace-$version.zip"
if (Test-Path -LiteralPath $workspaceZip) { throw 'ZIP Workspace già esistente.' }
Compress-Archive -Path (Join-Path $workspaceStage '*') -DestinationPath $workspaceZip -CompressionLevel Optimal
$archive = [IO.Compression.ZipFile]::OpenRead($workspaceZip)
try {
    if ($archive.Entries.Count -ne 2) { throw 'Contenuto ZIP Workspace errato.' }
    foreach ($entry in $archive.Entries) {
        if ($entry.FullName -notin @('Codice.gs', 'appsscript.json')) { throw 'File Workspace inatteso.' }
        $stream = $entry.Open(); $sha = [Security.Cryptography.SHA256]::Create()
        try { $hash = [Convert]::ToHexString($sha.ComputeHash($stream)) } finally { $stream.Dispose(); $sha.Dispose() }
        if ($hash -ne (Get-FileHash -LiteralPath (Join-Path $workspaceStage $entry.FullName) -Algorithm SHA256).Hash) { throw 'Hash Workspace errato.' }
    }
    Write-Output 'ZIP Workspace verificato: Codice.gs e manifest identici ai sorgenti.'
} finally { $archive.Dispose() }
$publicZip = Join-Path $dist "modulo-iscrizioni-$version-pubblico.zip"
if (Test-Path -LiteralPath $publicZip) { throw 'ZIP pubblico già esistente.' }
# Pubblicare soltanto file del plugin tracciati da Git: la configurazione privata resta nello ZIP locale.
$publicFiles = @(git ls-files -- wordpress-plugin/modulo-iscrizioni)
if ($LASTEXITCODE -ne 0 -or !$publicFiles.Count) { throw 'Elenco sorgenti pubblici non disponibile.' }
$publicArchive = [IO.Compression.ZipFile]::Open($publicZip, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $publicFiles) {
        if ($file -match 'public-balance-config\.json$') { throw 'Configurazione privata tracciata da Git.' }
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($publicArchive, (Join-Path (Get-Location) $file), $file.Substring(17), [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $publicArchive.Dispose() }
$publicArchive = [IO.Compression.ZipFile]::OpenRead($publicZip)
try {
    if ($publicArchive.Entries.Count -ne $publicFiles.Count) { throw 'Numero file pubblici errato.' }
    foreach ($entry in $publicArchive.Entries) {
        if (!$entry.FullName.StartsWith('modulo-iscrizioni/')) { throw 'Root pubblica errata.' }
        $stream = $entry.Open(); $sha = [Security.Cryptography.SHA256]::Create()
        try { $hash = [Convert]::ToHexString($sha.ComputeHash($stream)) } finally { $stream.Dispose(); $sha.Dispose() }
        if ($hash -ne (Get-FileHash -LiteralPath (Join-Path 'wordpress-plugin' $entry.FullName)).Hash) { throw 'Hash pubblico errato.' }
    }
    Write-Output "ZIP pubblico verificato: $($publicArchive.Entries.Count) file tracciati, configurazione privata esclusa."
} finally { $publicArchive.Dispose() }
foreach ($artifact in @($destination, $publicZip, $workspaceZip, (Join-Path $dist "Codice-Workspace-Progetto-$version.gs"))) {
    $hash = (Get-FileHash -LiteralPath $artifact -Algorithm SHA256).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText($artifact + '.sha256', "$hash  $([IO.Path]::GetFileName($artifact))`n")
    Write-Output $artifact
}
