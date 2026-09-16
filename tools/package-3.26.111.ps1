$ErrorActionPreference = 'Stop'
$source = (Resolve-Path 'wordpress-plugin/modulo-iscrizioni').Path
$destination = Join-Path (Resolve-Path 'dist').Path 'modulo-iscrizioni-3.26.111.zip'
if (Test-Path -LiteralPath $destination) { throw 'ZIP già esistente: nessuna sovrascrittura.' }
Compress-Archive -LiteralPath $source -DestinationPath $destination -CompressionLevel Optimal
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [IO.Compression.ZipFile]::OpenRead($destination)
try {
    $files = @(Get-ChildItem -LiteralPath $source -Recurse -File)
    $entries = @($archive.Entries | Where-Object { $_.Name })
    if ($entries.Count -ne $files.Count) { throw 'Numero file diverso dal sorgente.' }
    foreach ($entry in $entries) {
        $name = $entry.FullName.Replace('\','/')
        if (-not $name.StartsWith('modulo-iscrizioni/')) { throw 'Cartella principale errata.' }
        $local = Join-Path $source $name.Substring(18)
        $stream = $entry.Open()
        $sha = [Security.Cryptography.SHA256]::Create()
        try { $hash = [Convert]::ToHexString($sha.ComputeHash($stream)) } finally { $stream.Dispose(); $sha.Dispose() }
        if ($hash -ne (Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash) { throw "File differente: $name" }
    }
    $main = $archive.GetEntry('modulo-iscrizioni/modulo-iscrizioni.php')
    $reader = [IO.StreamReader]::new($main.Open())
    try { $text = $reader.ReadToEnd() } finally { $reader.Dispose() }
    if ($text -notmatch 'Version: 3\.26\.111') { throw 'Versione errata.' }
    Write-Output "Verificati direttamente nello ZIP: $($entries.Count) file, tutti identici al sorgente; versione 3.26.111."
} finally { $archive.Dispose() }
$checksum = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash.ToLowerInvariant()
[IO.File]::WriteAllText($destination + '.sha256', $checksum + '  ' + [IO.Path]::GetFileName($destination) + "`n")
Write-Output $destination
Write-Output $checksum
