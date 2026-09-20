param([Parameter(Mandatory=$true)][string]$InputPath)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$source = [Drawing.Bitmap]::new($InputPath)
try {
  foreach ($point in @(@(0,0),@(($source.Width-1),0),@(0,($source.Height-1)),@(($source.Width-1),($source.Height-1)))) {
    if ($source.GetPixel($point[0],$point[1]).A -ne 0) { throw 'Angoli non trasparenti: immagine rifiutata.' }
  }
  $payloads = @()
  foreach ($size in @(16,32,48,64,180,192,256,512)) {
    $bitmap = [Drawing.Bitmap]::new($size,$size,[Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [Drawing.Graphics]::FromImage($bitmap)
    try {
      $graphics.Clear([Drawing.Color]::Transparent)
      $graphics.CompositingMode = [Drawing.Drawing2D.CompositingMode]::SourceCopy
      $graphics.InterpolationMode = [Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
      $graphics.PixelOffsetMode = [Drawing.Drawing2D.PixelOffsetMode]::HighQuality
      $graphics.DrawImage($source,0,0,$size,$size)
      $memory = [IO.MemoryStream]::new()
      try {
        $bitmap.Save($memory,[Drawing.Imaging.ImageFormat]::Png)
        $bytes = $memory.ToArray()
        if ($size -in @(32,180,192,512)) { [IO.File]::WriteAllBytes((Join-Path (Get-Location) "wordpress-plugin/modulo-iscrizioni/assets/portal-icon-$size.png"),$bytes) }
        if ($size -in @(16,32,48,64,256)) { $payloads += @{Size=$size; Bytes=$bytes} }
      } finally { $memory.Dispose() }
    } finally { $graphics.Dispose(); $bitmap.Dispose() }
  }
  $stream = [IO.MemoryStream]::new()
  $writer = [IO.BinaryWriter]::new($stream)
  try {
    $writer.Write([uint16]0); $writer.Write([uint16]1); $writer.Write([uint16]$payloads.Count)
    $offset = 6 + 16 * $payloads.Count
    foreach ($payload in $payloads) {
      $dimension = if ($payload.Size -eq 256) { 0 } else { $payload.Size }
      $writer.Write([byte]$dimension); $writer.Write([byte]$dimension)
      $writer.Write([uint16]0); $writer.Write([uint16]1); $writer.Write([uint16]32)
      $writer.Write([uint32]$payload.Bytes.Length); $writer.Write([uint32]$offset)
      $offset += $payload.Bytes.Length
    }
    foreach ($payload in $payloads) { $writer.Write([byte[]]$payload.Bytes) }
    [IO.File]::WriteAllBytes((Join-Path (Get-Location) 'dist/segreteria-eventi.ico'),$stream.ToArray())
  } finally { $writer.Dispose(); $stream.Dispose() }
  Write-Output 'Sostituite quattro PNG nel plugin e icona desktop multirisoluzione. Trasparenza angoli verificata.'
} finally { $source.Dispose() }
