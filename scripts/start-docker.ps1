param(
    [string]$ProjectName = 'landing-page-portal-wifi',
    [string]$EnvFile = 'docker.env',
    [switch]$NoBuild
)
$ErrorActionPreference = 'Stop'
$portalRoot = Split-Path -Parent $PSScriptRoot
if ($ProjectName -notmatch '^[a-z0-9][a-z0-9_-]*$') { throw 'Nama project Compose tidak valid.' }
Get-Command docker -ErrorAction Stop | Out-Null
& docker version --format '{{.Server.Version}}' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Engine belum aktif. Jalankan Docker Desktop atau Docker Engine terlebih dahulu.' }
$environmentPath = if ([IO.Path]::IsPathRooted($EnvFile)) { [IO.Path]::GetFullPath($EnvFile) } else { [IO.Path]::GetFullPath((Join-Path $portalRoot $EnvFile)) }
if (-not (Test-Path -LiteralPath $environmentPath -PathType Leaf)) {
    if ($EnvFile -ne 'docker.env') { throw 'Berkas konfigurasi yang dipilih belum tersedia.' }
    Copy-Item -LiteralPath (Join-Path $portalRoot 'docker.env.example') -Destination $environmentPath
    Write-Output 'docker.env dibuat dari contoh; konfigurasi yang sudah ada tetap dipertahankan.'
}
$compose = @('compose', '-f', (Join-Path $portalRoot 'compose.yaml'), '-p', $ProjectName, '--env-file', $environmentPath)
& docker @compose config --quiet
if ($LASTEXITCODE -ne 0) { throw 'Konfigurasi Docker Compose tidak valid.' }
$arguments = @('up', '-d', '--wait', '--wait-timeout', '180')
if (-not $NoBuild) { $arguments += '--build' }
& docker @compose @arguments
if ($LASTEXITCODE -ne 0) { throw 'Portal Docker belum siap. Periksa log service app dan scheduler melalui Docker Compose.' }
$configJson = & docker @compose config --format json
if ($LASTEXITCODE -ne 0) { throw 'Alamat portal gagal dibaca.' }
$config = ($configJson -join "`n") | ConvertFrom-Json
Write-Output ('Portal Docker siap: '+$config.services.app.environment.APP_URL)
Write-Output 'Pemasangan baru: buat akun admin dengan perintah portal:admin melalui service app (lihat README.md).'
