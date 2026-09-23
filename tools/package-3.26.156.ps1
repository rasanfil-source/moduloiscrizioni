$ErrorActionPreference = 'Stop'
& (Join-Path $PSScriptRoot 'package-3.26.155.ps1') -Version '3.26.156'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
