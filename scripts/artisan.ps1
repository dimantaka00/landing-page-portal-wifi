param([Parameter(ValueFromRemainingArguments = $true)][string[]]$ArtisanArguments)
$ErrorActionPreference = 'Stop'
$PortalRoot = Split-Path -Parent $PSScriptRoot
$PhpBinary = Join-Path $PortalRoot '.runtime/php/php.exe'
if (-not (Test-Path -LiteralPath $PhpBinary)) { throw 'PHP portable tidak ditemukan.' }
$OpenSslConfig = Join-Path (Split-Path -Parent $PhpBinary) 'extras/ssl/openssl.cnf'
if (-not $env:OPENSSL_CONF -and (Test-Path -LiteralPath $OpenSslConfig)) { $env:OPENSSL_CONF = $OpenSslConfig }
Push-Location -LiteralPath $PortalRoot
try {
    & $PhpBinary artisan @ArtisanArguments
    $PortalExitCode = $LASTEXITCODE
} finally { Pop-Location }
exit $PortalExitCode