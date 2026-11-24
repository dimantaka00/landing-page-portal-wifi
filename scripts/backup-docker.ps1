param(
    [string]$ProjectName = 'landing-page-portal-wifi',
    [string]$EnvFile,
    [string]$BackupRoot
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
if ($ProjectName -notmatch '^[a-z0-9][a-z0-9_-]*$') { throw 'Nama project Compose tidak valid.' }
if (-not $BackupRoot) { $BackupRoot = Join-Path (Split-Path -Parent $root) 'landing-page-portal-wifi-backups' }
$BackupRoot = [IO.Path]::GetFullPath($BackupRoot)
if ($BackupRoot -eq $root -or $BackupRoot.StartsWith($root.TrimEnd('\')+'\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Backup harus di luar proyek.' }
$compose = @('compose', '-f', (Join-Path $root 'compose.yaml'), '-p', $ProjectName)
if ($EnvFile) { $compose += @('--env-file', [IO.Path]::GetFullPath($EnvFile)) }
function Assert-Docker { if ($LASTEXITCODE -ne 0) { throw 'Operasi Docker backup gagal; snapshot sebelumnya tetap dipertahankan.' } }
$container = @(& docker @compose ps -a -q app)
Assert-Docker
if ($container.Count -ne 1) { throw 'Satu container app diperlukan. Jalankan Compose terlebih dahulu.' }
$details = (& docker inspect $container[0] | ConvertFrom-Json)[0]
Assert-Docker
$image = $details.Image
$mounts = @($details.Mounts | Where-Object { $_.Type -eq 'volume' -and $_.Destination -in @('/var/www/html/storage', '/var/www/html/database/data') })
if ($mounts.Count -ne 2) { throw 'Volume data Compose tidak sesuai.' }
foreach ($mount in $mounts) {
    $volume = (& docker volume inspect $mount.Name | ConvertFrom-Json)[0]
    Assert-Docker
    if ($volume.Labels.'com.docker.compose.project' -ne $ProjectName) { throw 'Volume bukan milik project yang dipilih.' }
}
$storageVolume = ($mounts | Where-Object { $_.Destination -eq '/var/www/html/storage' }).Name
$dbVolume = ($mounts | Where-Object { $_.Destination -eq '/var/www/html/database/data' }).Name
$running = @(& docker @compose ps --services --status running)
Assert-Docker
$snapshot = Join-Path $BackupRoot ((Get-Date -Format 'yyyyMMdd_HHmmss')+'_docker_'+[guid]::NewGuid().ToString('N').Substring(0,8))
New-Item -ItemType Directory -Path $snapshot | Out-Null
$owner = (Get-Acl -LiteralPath $root).GetOwner([Security.Principal.SecurityIdentifier]).Value
$current = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
icacls.exe $snapshot /inheritance:r /grant:r "*${owner}:(OI)(CI)(F)" "*${current}:(OI)(CI)(F)" '*S-1-5-18:(OI)(CI)(F)' '*S-1-5-32-544:(OI)(CI)(F)' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Izin backup tidak dapat dibatasi.' }
$manifest = [ordered]@{status='incomplete';project=$ProjectName;image=$image;created_at=(Get-Date).ToString('o')}
function Write-Manifest { [IO.File]::WriteAllText((Join-Path $snapshot 'manifest.json'), ($manifest | ConvertTo-Json -Depth 8), [Text.UTF8Encoding]::new($false)) }
Write-Manifest
$environmentBackup = [ordered]@{environment=$details.Config.Env;ports=$details.HostConfig.PortBindings}
[IO.File]::WriteAllText((Join-Path $snapshot 'container-environment.json'), ($environmentBackup | ConvertTo-Json -Depth 8), [Text.UTF8Encoding]::new($false))
Write-Output "Docker backup: $snapshot"
$scratch = @()
try {
    & docker @compose stop scheduler app
    Assert-Docker
    $expected = (& docker run --rm --network none --entrypoint php --mount "type=volume,src=$storageVolume,dst=/restore-storage,readonly" --mount "type=volume,src=$dbVolume,dst=/restore-db,readonly" $image docker/verify-backup.php)
    Assert-Docker
    & docker image save --output (Join-Path $snapshot 'docker-image.tar') $image
    Assert-Docker
    & docker image load --input (Join-Path $snapshot 'docker-image.tar') | Out-Null
    Assert-Docker
    foreach ($item in @(@{name='storage';volume=$storageVolume}, @{name='database';volume=$dbVolume})) {
        & docker run --rm --network none --entrypoint tar --mount "type=volume,src=$($item.volume),dst=/source,readonly" --mount "type=bind,src=$snapshot,dst=/backup" $image -C /source -cf "/backup/$($item.name).tar" .
        Assert-Docker
        $testVolume = 'portal-restore-check-'+[guid]::NewGuid().ToString('N')
        & docker volume create --label 'portal.backup-check=true' $testVolume | Out-Null
        Assert-Docker
        $scratch += $testVolume
        & docker run --rm --network none --entrypoint tar --mount "type=volume,src=$testVolume,dst=/restore" --mount "type=bind,src=$snapshot,dst=/backup,readonly" $image -C /restore -xf "/backup/$($item.name).tar"
        Assert-Docker
    }
    $actual = (& docker run --rm --network none --entrypoint php --mount "type=volume,src=$($scratch[0]),dst=/restore-storage,readonly" --mount "type=volume,src=$($scratch[1]),dst=/restore-db,readonly" $image docker/verify-backup.php)
    Assert-Docker
    if (($expected -join '') -ne ($actual -join '')) { throw 'Checksum data hasil restore berbeda dari sumber.' }
    $restore = $actual | ConvertFrom-Json
    [IO.File]::WriteAllText((Join-Path $snapshot 'restore-test.json'), ($restore | ConvertTo-Json), [Text.UTF8Encoding]::new($false))
    $manifest.artifacts = @(Get-ChildItem -LiteralPath $snapshot -File | Where-Object { $_.Name -ne 'manifest.json' } | ForEach-Object { @{name=$_.Name;sha256=(Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash;bytes=$_.Length} })
    $manifest.restore_test = 'passed'
    $manifest.status = 'verified'
    Write-Manifest
    Write-Output "Docker image, volume, dan uji pemulihan terverifikasi: $snapshot"
} finally {
    foreach ($name in $scratch) {
        try {
            $check = (& docker volume inspect $name | ConvertFrom-Json)[0]
            if ($LASTEXITCODE -eq 0 -and $name.StartsWith('portal-restore-check-') -and $check.Labels.'portal.backup-check' -eq 'true') {
                & docker volume rm $name | Out-Null
            }
        } catch { Write-Warning 'Volume uji restore perlu dibersihkan secara manual.' }
    }
    if ($running.Count -gt 0) {
        & docker @compose start @running
        if ($LASTEXITCODE -ne 0) { Write-Warning 'Container perlu dijalankan kembali secara manual.' }
    }
}