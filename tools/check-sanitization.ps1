[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$forbiddenExtensions = @(
  '.xlsx', '.xls', '.xlsm', '.csv', '.tsv', '.ods',
  '.env', '.pem', '.key', '.p12', '.pfx', '.log', '.bak'
)
$forbiddenNames = @(
  '.clasp.json',
  'credentials.json',
  'service-account.json'
)
$excludedDirectories = @('.git', 'node_modules', '.tmp', 'tmp', 'temp')

$patterns = @(
  @{ Label = 'percorso locale Windows'; Regex = '(?i)[A-Z]:[\\/]Users[\\/]' },
  @{ Label = 'percorso locale Unix'; Regex = '(?i)/(Users|home)/[^/\s]+' },
  @{ Label = 'chiave privata'; Regex = '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----' },
  @{ Label = 'token GitHub'; Regex = '\bgh[pousr]_[A-Za-z0-9]{20,}\b' },
  @{ Label = 'access key AWS'; Regex = '\b(AKIA|ASIA)[A-Z0-9]{16}\b' },
  @{ Label = 'IBAN italiano'; Regex = '(?i)\bIT\s*\d{2}(?:\s*[A-Z0-9]){23}\b' },
  @{ Label = 'email non dimostrativa'; Regex = '(?i)\b[A-Z0-9._%+-]+@(?![A-Z0-9.-]*example\.invalid\b)[A-Z0-9.-]+\.[A-Z]{2,}\b' },
  @{ Label = 'provider/link di pagamento operativo vietato'; Regex = '(?i)crowdfunding\.bancaprofilo\.it|donate-with-card/[0-9]+' },
  @{ Label = 'segreto assegnato nel sorgente'; Regex = '(?i)(api[_-]?key|client[_-]?secret|access[_-]?token|password)\s*[:=]\s*["''][^"'']{8,}["'']' }
)

$issues = [System.Collections.Generic.List[string]]::new()
$files = Get-ChildItem -LiteralPath $root -Recurse -File | Where-Object {
  $relativeParts = $_.FullName.Substring($root.Length).TrimStart('\', '/').Split([char[]]'\/')
  -not ($relativeParts | Where-Object { $_ -in $excludedDirectories })
}

foreach ($file in $files) {
  $relative = [System.IO.Path]::GetRelativePath($root, $file.FullName)
  $extension = $file.Extension.ToLowerInvariant()
  $name = $file.Name.ToLowerInvariant()

  if ($extension -in $forbiddenExtensions -or $name -in $forbiddenNames) {
    $issues.Add("file vietato: $relative")
    continue
  }

  if ($extension -notin @('.md', '.json', '.html', '.js', '.css', '.svg', '.ps1', '.yml', '.yaml', '.txt') -and $name -notin @('.gitignore', '.gitattributes')) {
    continue
  }

  $content = Get-Content -LiteralPath $file.FullName -Raw
  foreach ($pattern in $patterns) {
    if ([regex]::IsMatch($content, $pattern.Regex)) {
      $issues.Add("$($pattern.Label): $relative")
    }
  }
}

if ($issues.Count -gt 0) {
  Write-Error ("Sanitizzazione fallita:`n - " + (($issues | Sort-Object -Unique) -join "`n - "))
  exit 1
}

Write-Output "Sanitizzazione superata: nessun indicatore vietato rilevato."
