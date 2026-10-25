$ErrorActionPreference = 'Stop'
$releaseVersion = '3.26.258'
$releaseRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$releaseSource = Join-Path $releaseRoot 'wordpress-plugin/modulo-iscrizioni'
$releaseDist = Join-Path $releaseRoot 'dist'
$previousArchives = @(Get-ChildItem -LiteralPath $releaseDist -Filter '*.zip' | Get-FileHash -Algorithm SHA256)
foreach ($previous in $previousArchives) {
 if ([IO.Path]::GetFileName($previous.Path) -match '^modulo-iscrizioni-(\d+\.\d+\.\d+)(?:-pubblico)?\.zip$' -and [version]$Matches[1] -ge [version]$releaseVersion) { throw 'La versione deve superare tutti i pacchetti esistenti.' }
}
$notes = Get-Content -LiteralPath (Join-Path $releaseRoot "docs/rilascio-$releaseVersion.md") -Raw
if (!$notes.Contains($releaseVersion)) { throw 'Note di rilascio incoerenti.' }
$bootstrap = Get-Content -LiteralPath (Join-Path $releaseSource 'modulo-iscrizioni.php') -Raw
if ($bootstrap -notmatch ('Version:\s*' + [regex]::Escape($releaseVersion) + '\b') -or $bootstrap -notmatch "define\( 'MI_VERSION', '$([regex]::Escape($releaseVersion))' \)") { throw 'Versione plugin incoerente.' }
node (Join-Path $PSScriptRoot 'asset-build/build.cjs') --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
$releaseFiles = @(Get-ChildItem -LiteralPath $releaseSource -Recurse -File -Force)
$targets = @(
 (Join-Path $releaseDist "modulo-iscrizioni-$releaseVersion.zip"),
 (Join-Path $releaseDist "modulo-iscrizioni-$releaseVersion-pubblico.zip")
)
foreach ($target in $targets) {
 if (Test-Path -LiteralPath $target) { throw "Archivio gia esistente: $target" }
 if (Test-Path -LiteralPath ($target + '.sha256')) { throw "Checksum gia esistente: $target.sha256" }
}
foreach ($public in @($false, $true)) {
 $suffix = if ($public) { '-pubblico' } else { '' }
 $destination = Join-Path $releaseDist "modulo-iscrizioni-$releaseVersion$suffix.zip"
 $selected = @($releaseFiles | Where-Object { !$public -or $_.Name -ne 'public-balance-config.json' })
 $archive = [IO.Compression.ZipFile]::Open($destination, [IO.Compression.ZipArchiveMode]::Create)
 try {
  foreach ($file in $selected) {
   $entryName = 'modulo-iscrizioni/' + [IO.Path]::GetRelativePath($releaseSource, $file.FullName).Replace('\','/')
   [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $entryName, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
  }
 } finally { $archive.Dispose() }
 $archive = [IO.Compression.ZipFile]::OpenRead($destination)
 try {
  if ($archive.Entries.Count -ne $selected.Count) { throw 'Conteggio file errato.' }
  foreach ($file in $selected) {
   $entryName = 'modulo-iscrizioni/' + [IO.Path]::GetRelativePath($releaseSource, $file.FullName).Replace('\','/')
   $entry = $archive.GetEntry($entryName)
   if (!$entry) { throw "File mancante: $entryName" }
   $stream = $entry.Open()
   try { $actual = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($stream)) }
   finally { $stream.Dispose() }
   if ($actual -ne (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash) { throw "Contenuto diverso: $entryName" }
  }
 } finally { $archive.Dispose() }
 $hash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash.ToLowerInvariant()
 "$hash  $([IO.Path]::GetFileName($destination))" | Set-Content -LiteralPath ($destination + '.sha256') -Encoding utf8
 Write-Output "Verificato: $([IO.Path]::GetFileName($destination)), $($selected.Count) file."
}
foreach ($previous in $previousArchives) {
 if ((Get-FileHash -LiteralPath $previous.Path -Algorithm SHA256).Hash -ne $previous.Hash) { throw "Archivio precedente alterato: $($previous.Path)" }
}
Write-Output "Verificati $($previousArchives.Count) archivi precedenti: tutti invariati."
