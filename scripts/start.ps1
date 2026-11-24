param([string]$BindAddress = '127.0.0.1', [int]$Port = 8011)
$ErrorActionPreference = 'Stop'
$PortalRoot = Split-Path -Parent $PSScriptRoot
$PhpCommand = Get-Command php -ErrorAction SilentlyContinue
$PhpBinary = if ($PhpCommand) { $PhpCommand.Source } else { Join-Path $PortalRoot '.runtime/php/php.exe' }
$OpenSslConfig = Join-Path (Split-Path -Parent $PhpBinary) 'extras/ssl/openssl.cnf'
if (-not $env:OPENSSL_CONF -and (Test-Path -LiteralPath $OpenSslConfig)) { $env:OPENSSL_CONF = $OpenSslConfig }
if (-not (Test-Path -LiteralPath $PhpBinary)) { throw 'PHP 8.4+ belum tersedia. Instal PHP atau Laravel Herd terlebih dahulu.' }
if (-not (Test-Path -LiteralPath (Join-Path $PortalRoot 'vendor/autoload.php'))) { throw 'Jalankan composer install di folder landing-page-portal-wifi terlebih dahulu.' }
if (-not (Test-Path -LiteralPath (Join-Path $PortalRoot '.env'))) { throw 'Jalankan scripts/setup.ps1 terlebih dahulu.' }
$scheduler = $null
Push-Location -LiteralPath $PortalRoot
try {
    Write-Output "Portal lokal: http://${BindAddress}:$Port"
    Write-Output "Halaman admin: http://${BindAddress}:$Port/admin"
    $schedulerId = [guid]::NewGuid().ToString('N').Substring(0, 8)
    $scheduler = Start-Process -FilePath $PhpBinary -ArgumentList @('artisan', 'schedule:work', '--no-interaction') -WorkingDirectory $PortalRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $env:TEMP ("portal-scheduler-"+$schedulerId+".out.log")) -RedirectStandardError (Join-Path $env:TEMP ("portal-scheduler-"+$schedulerId+".err.log"))
    Start-Sleep -Milliseconds 500
    if ($scheduler.HasExited) { throw 'Scheduler penghapusan otomatis gagal dijalankan.' }
    Write-Output 'Scheduler penghapusan otomatis aktif; pemeriksaan setiap menit.'
    & $PhpBinary -S "${BindAddress}:$Port" -t public server.php
    if ($LASTEXITCODE -ne 0) { throw 'Server PHP berhenti dengan kesalahan.' }
} finally {
    if ($scheduler -and -not $scheduler.HasExited) {
        taskkill.exe /PID $scheduler.Id /T /F | Out-Null
    }
    Pop-Location
}
