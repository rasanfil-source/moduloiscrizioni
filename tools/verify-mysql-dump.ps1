param(
    [Parameter(Mandatory = $true)]
    [string]$Path
)

$ErrorActionPreference = 'Stop'
$resolvedPath = (Resolve-Path -LiteralPath $Path).Path
$requiredTables = @(
    'wp_mi_registrations',
    'wp_mi_participants',
    'wp_mi_payments',
    'wp_mi_registration_events',
    'wp_mi_registration_items',
    'wp_mi_email_outbox',
    'wp_mi_management_requests',
    'wp_mi_management_state'
)

$inputStream = [System.IO.File]::OpenRead($resolvedPath)
try {
    $gzipStream = [System.IO.Compression.GZipStream]::new(
        $inputStream,
        [System.IO.Compression.CompressionMode]::Decompress
    )
    $reader = [System.IO.StreamReader]::new(
        $gzipStream,
        [System.Text.Encoding]::UTF8,
        $true
    )
    try {
        $sql = $reader.ReadToEnd()
    }
    finally {
        $reader.Dispose()
        $gzipStream.Dispose()
    }
}
finally {
    $inputStream.Dispose()
}

$missingTables = @()
foreach ($table in $requiredTables) {
    $quotedTable = [char]96 + $table + [char]96
    if (-not $sql.Contains("CREATE TABLE $quotedTable")) {
        $missingTables += $table
    }
}

$result = [ordered]@{
    FileName                 = [System.IO.Path]::GetFileName($resolvedPath)
    GzipValid                = $true
    CompressedBytes          = (Get-Item -LiteralPath $resolvedPath).Length
    UncompressedBytes        = [System.Text.Encoding]::UTF8.GetByteCount($sql)
    Sha256                   = (Get-FileHash -LiteralPath $resolvedPath -Algorithm SHA256).Hash
    PhpMyAdminHeader         = $sql.Contains('phpMyAdmin SQL Dump')
    CreateTableStatements    = [regex]::Matches($sql, '(?mi)^CREATE TABLE ').Count
    InsertStatements         = [regex]::Matches($sql, '(?mi)^INSERT INTO ').Count
    RequiredTablesAllDefined = $missingTables.Count -eq 0
    MissingRequiredTables    = $missingTables
}

$sql = $null
$result | ConvertTo-Json -Depth 3
