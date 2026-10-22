$ErrorActionPreference = 'Stop'
$root = (Resolve-Path '.').Path
$destination = Join-Path $root 'dist/modulo-iscrizioni-3.26.217-pubblico.zip'
if (Test-Path -LiteralPath $destination) { throw 'Archivio già esistente.' }
$files = @(git ls-files --cached --others --exclude-standard -- wordpress-plugin/modulo-iscrizioni | Sort-Object -Unique)
if ($LASTEXITCODE -ne 0 -or !$files.Count) { throw 'Elenco sorgenti non disponibile.' }
if ($files -match 'public-balance-config\.json$') { throw 'Configurazione privata inclusa.' }
$archive = [IO.Compression.ZipFile]::Open($destination, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $files) {
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, (Join-Path $root $file), $file.Substring(17), [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $archive.Dispose() }
$archive = [IO.Compression.ZipFile]::OpenRead($destination)
try {
    if ($archive.Entries.Count -ne $files.Count) { throw 'Conteggio archivio errato.' }
    foreach ($file in $files) {
        $entry = $archive.GetEntry($file.Substring(17))
        if (!$entry) { throw "File mancante: $file" }
        $stream = $entry.Open()
        try { $hash = [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($stream)) }
        finally { $stream.Dispose() }
        if ($hash -ne (Get-FileHash -LiteralPath (Join-Path $root $file)).Hash) { throw "Hash errato: $file" }
    }
} finally { $archive.Dispose() }
$checksum = (Get-FileHash -LiteralPath $destination).Hash.ToLowerInvariant()
"$checksum  $([IO.Path]::GetFileName($destination))" | Set-Content -LiteralPath ($destination + '.sha256') -Encoding utf8
Write-Output "Archivio pubblico verificato: $($files.Count) file."
