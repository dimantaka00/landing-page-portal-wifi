param([string]$AdminEmail = 'admin@kantor.local', [switch]$Demo)
$ErrorActionPreference = 'Stop'
$PortalRoot = Split-Path -Parent $PSScriptRoot
$PhpCommand = Get-Command php -ErrorAction SilentlyContinue
$PhpBinary = if ($PhpCommand) { $PhpCommand.Source } else { Join-Path $PortalRoot '.runtime/php/php.exe' }
$OpenSslConfig = Join-Path (Split-Path -Parent $PhpBinary) 'extras/ssl/openssl.cnf'
if (-not $env:OPENSSL_CONF -and (Test-Path -LiteralPath $OpenSslConfig)) { $env:OPENSSL_CONF = $OpenSslConfig }
if (-not (Test-Path -LiteralPath $PhpBinary)) { throw 'PHP 8.4+ belum tersedia.' }
if (-not (Test-Path -LiteralPath (Join-Path $PortalRoot 'vendor/autoload.php'))) { throw 'Jalankan composer install terlebih dahulu.' }
function Assert-PortalCommand { if ($LASTEXITCODE -ne 0) { throw 'Perintah penyiapan Laravel gagal.' } }
function Protect-PortalFile([string]$Path) {
    $WorkspaceOwnerSid = (Get-Acl -LiteralPath $PortalRoot).GetOwner([Security.Principal.SecurityIdentifier]).Value
    $CurrentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    & icacls.exe $Path /inheritance:r /grant:r "*${WorkspaceOwnerSid}:(F)" "*${CurrentSid}:(F)" '*S-1-5-18:(F)' '*S-1-5-32-544:(F)' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membatasi izin berkas privat portal.' }
}
Push-Location -LiteralPath $PortalRoot
try {
    if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
    $HasKey = @(Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^APP_KEY=.+$' }).Count -gt 0
    if (-not $HasKey) { & $PhpBinary artisan key:generate --force; Assert-PortalCommand }
    if (-not (Test-Path -LiteralPath 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null }
    & $PhpBinary artisan migrate --force
    Assert-PortalCommand
    $AdminFile = Join-Path $PortalRoot 'storage/app/private/initial-admin.txt'
    if (-not (Test-Path -LiteralPath $AdminFile)) {
        & $PhpBinary artisan portal:admin $AdminEmail --generate
        Assert-PortalCommand
    }
    & $PhpBinary artisan portal:push-setup --no-interaction
    Assert-PortalCommand
    Protect-PortalFile (Join-Path $PortalRoot 'storage/app/private/web-push-keys.json')
    Protect-PortalFile (Join-Path $PortalRoot '.env')
    Protect-PortalFile $AdminFile
    Protect-PortalFile (Join-Path $PortalRoot 'database/database.sqlite')
    if ($Demo) { & $PhpBinary artisan portal:demo; Assert-PortalCommand }
    Write-Output 'Aplikasi siap. Jalankan powershell -File scripts/start.ps1 dari folder landing-page-portal-wifi.'
    Write-Output 'Akses admin tersimpan privat di storage/app/private/initial-admin.txt.'
} finally { Pop-Location }
