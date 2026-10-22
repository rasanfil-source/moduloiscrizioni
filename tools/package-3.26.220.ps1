$ErrorActionPreference = 'Stop'
$releaseVersion = '3.26.220'
$releaseRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$releaseSource = Join-Path $releaseRoot 'wordpress-plugin/modulo-iscrizioni'
$releaseDist = Join-Path $releaseRoot 'dist'
$bootstrap = Get-Content -LiteralPath (Join-Path $releaseSource 'modulo-iscrizioni.php') -Raw
if ($bootstrap -notmatch ('Version:\s*' + [regex]::Escape($releaseVersion) + '\b') -or $bootstrap -notmatch "define\( 'MI_VERSION', '$([regex]::Escape($releaseVersion))' \)") { throw 'Versione plugin incoerente.' }
node (Join-Path $PSScriptRoot 'asset-build/build.cjs') --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
$releaseFiles = @(Get-ChildItem -LiteralPath $releaseSource -Recurse -File -Force)
foreach ($public in @($false, $true)) {
 $suffix = if ($public) { '-pubblico' } else { '' }
 $destination = Join-Path $releaseDist "modulo-iscrizioni-$releaseVersion$suffix.zip"
 if (Test-Path -LiteralPath $destination) { throw "Archivio gia esistente: $destination" }
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
