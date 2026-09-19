$ErrorActionPreference = 'Stop'
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$destination = Join-Path (Resolve-Path 'dist').Path 'modulo-iscrizioni-3.26.119.zip'
if (Test-Path -LiteralPath $destination) { throw 'ZIP già esistente.' }
node tools/asset-build/build.cjs --check
if ($LASTEXITCODE -ne 0) { throw 'Asset non aggiornati.' }
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
 Write-Output "ZIP verificato: $($entries.Count) file identici ai sorgenti."
} finally { $archive.Dispose() }
$hash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash.ToLowerInvariant()
[IO.File]::WriteAllText($destination + '.sha256', "$hash  modulo-iscrizioni-3.26.119.zip`n")
Write-Output $destination
