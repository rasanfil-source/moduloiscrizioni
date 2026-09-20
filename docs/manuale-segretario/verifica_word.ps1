$ErrorActionPreference = 'Stop'
$base = $PSScriptRoot
New-Item -ItemType Directory -Path (Join-Path $base 'qa') -Force | Out-Null
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
try {
  $document = $word.Documents.Open((Join-Path $base 'Manuale_del_segretario.docx'), $false, $true)
  $document.ExportAsFixedFormat((Join-Path $base 'qa/manuale.pdf'), 17)
  $document.Close(0)
} finally { $word.Quit() }
