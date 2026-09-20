$ErrorActionPreference = 'Stop'
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$destination = Join-Path (Resolve-Path 'dist').Path 'modulo-iscrizioni-3.26.135.zip'
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
[IO.File]::WriteAllText($destination + '.sha256', "$hash  modulo-iscrizioni-3.26.135.zip`n")
Write-Output $destination

$workspaceZip = Join-Path (Resolve-Path 'dist').Path 'Workspace-3.26.135.zip'
if (!(Test-Path -LiteralPath $workspaceZip)) {
 Compress-Archive -Path workspace-apps-script/src,workspace-apps-script/appsscript.json,workspace-apps-script/README.md -DestinationPath $workspaceZip -CompressionLevel Optimal
}
$workspaceArchive = [IO.Compression.ZipFile]::OpenRead($workspaceZip)
try {
 $workspaceFiles = @(Get-ChildItem workspace-apps-script/src -Recurse -File) + @(Get-Item workspace-apps-script/appsscript.json,workspace-apps-script/README.md)
 $workspaceEntries = @($workspaceArchive.Entries | Where-Object { $_.Name })
 if ($workspaceFiles.Count -ne $workspaceEntries.Count) { throw 'Numero file Workspace errato.' }
 foreach ($entry in $workspaceEntries) {
  $local = Join-Path (Resolve-Path 'workspace-apps-script').Path $entry.FullName
  $stream = $entry.Open(); $sha = [Security.Cryptography.SHA256]::Create()
  try { $entryHash = [Convert]::ToHexString($sha.ComputeHash($stream)) } finally { $stream.Dispose(); $sha.Dispose() }
  if ($entryHash -ne (Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash) { throw 'Contenuto Workspace non aggiornato.' }
 }
 Write-Output "Workspace verificato: $($workspaceEntries.Count) file identici ai sorgenti."
} finally { $workspaceArchive.Dispose() }
$workspaceHash = (Get-FileHash -LiteralPath $workspaceZip -Algorithm SHA256).Hash.ToLowerInvariant()
[IO.File]::WriteAllText($workspaceZip + '.sha256', "$workspaceHash  Workspace-3.26.135.zip`n")
