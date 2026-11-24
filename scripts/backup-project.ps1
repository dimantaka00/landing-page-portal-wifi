param([string]$BackupRoot, [string]$HistoryRepository)
$ErrorActionPreference = 'Stop'
$ProjectRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if (-not $BackupRoot) { $BackupRoot = Join-Path (Split-Path -Parent $ProjectRoot) 'landing-page-portal-wifi-backups' }
$BackupRoot = [IO.Path]::GetFullPath($BackupRoot)
if ($BackupRoot -eq $ProjectRoot -or $BackupRoot.StartsWith($ProjectRoot.TrimEnd('\')+'\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Backup harus berada di luar proyek.' }
$PhpCommand = Get-Command php -ErrorAction SilentlyContinue
$PhpBinary = if ($PhpCommand) { $PhpCommand.Source } else { Join-Path $ProjectRoot '.runtime/php/php.exe' }
if (-not (Test-Path -LiteralPath $PhpBinary)) { throw 'PHP 8.4+ diperlukan untuk uji restore SQLite.' }
$Snapshot = Join-Path $BackupRoot ((Get-Date -Format 'yyyyMMdd_HHmmss')+'_'+[guid]::NewGuid().ToString('N').Substring(0,8))
New-Item -ItemType Directory -Path $Snapshot -Force | Out-Null
$OwnerSid = (Get-Acl -LiteralPath $ProjectRoot).GetOwner([Security.Principal.SecurityIdentifier]).Value
$CurrentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
icacls.exe $Snapshot /inheritance:r /grant:r "*${OwnerSid}:(OI)(CI)(F)" "*${CurrentSid}:(OI)(CI)(F)" '*S-1-5-18:(OI)(CI)(F)' '*S-1-5-32-544:(OI)(CI)(F)' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Pembatasan izin snapshot gagal.' }
function Write-BackupJson($Value, [string]$Path) { [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 12), [Text.UTF8Encoding]::new($false)) }
$Manifest = [ordered]@{status='incomplete';created_at=(Get-Date).ToString('o');project=$ProjectRoot}
Write-BackupJson $Manifest (Join-Path $Snapshot 'manifest.json')
Write-Output "Folder backup: $Snapshot"
if (-not $HistoryRepository -and (Test-Path -LiteralPath (Join-Path $ProjectRoot '.git'))) { $HistoryRepository = $ProjectRoot }
if ($HistoryRepository) {
    git -C $HistoryRepository bundle create (Join-Path $Snapshot 'repository.bundle') --all
    if ($LASTEXITCODE -ne 0) { throw 'Bundle riwayat Git gagal.' }
    git -C $HistoryRepository bundle verify (Join-Path $Snapshot 'repository.bundle')
    if ($LASTEXITCODE -ne 0) { throw 'Verifikasi bundle Git gagal.' }
    $Manifest.git = @{repository=$HistoryRepository;commit=(git -C $HistoryRepository rev-parse HEAD).Trim();purpose='history provenance'}
}
Add-Type -AssemblyName System.IO.Compression.FileSystem
$Inventory = [Collections.Generic.List[object]]::new()
$Files = @(Get-ChildItem -LiteralPath $ProjectRoot -Force -Recurse -File)
$Reparse = @(Get-ChildItem -LiteralPath $ProjectRoot -Force -Recurse | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint })
if ($Reparse.Count -gt 0) { throw 'Proyek berisi reparse point; backup dihentikan.' }
$ZipPath = Join-Path $Snapshot 'project.zip'
$Zip = [IO.Compression.ZipFile]::Open($ZipPath, 'Create')
try {
    foreach ($File in $Files) {
        $Relative = $File.FullName.Substring($ProjectRoot.Length+1).Replace('\','/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($Zip, $File.FullName, $Relative, [IO.Compression.CompressionLevel]::Fastest) | Out-Null
        $Inventory.Add([ordered]@{path=$Relative;bytes=$File.Length;sha256=(Get-FileHash -LiteralPath $File.FullName -Algorithm SHA256).Hash})
    }
} finally { $Zip.Dispose() }
Write-BackupJson $Inventory (Join-Path $Snapshot 'file-inventory.json')
Write-Output ('Memverifikasi arsip lengkap: '+$Inventory.Count+' file...')
$Expected = @{}
foreach ($Record in $Inventory) { $Expected[$Record.path] = $Record }
$RestoreRoot = Join-Path $Snapshot 'restore-check'
New-Item -ItemType Directory -Path $RestoreRoot | Out-Null
$Zip = [IO.Compression.ZipFile]::OpenRead($ZipPath)
try {
    if ($Zip.Entries.Count -ne $Inventory.Count) { throw 'Jumlah file arsip tidak sesuai.' }
    foreach ($Entry in $Zip.Entries) {
        $Stream = $Entry.Open()
        $Hash = [Security.Cryptography.SHA256]::Create()
        try { $Actual = [BitConverter]::ToString($Hash.ComputeHash($Stream)).Replace('-','') } finally { $Hash.Dispose(); $Stream.Dispose() }
        if ($Actual -ne $Expected[$Entry.FullName].sha256) { throw ('Checksum arsip tidak cocok: '+$Entry.FullName) }
    }
    foreach ($Required in @('.env','database/database.sqlite','.runtime/php/php.exe','vendor/autoload.php','README.md')) {
        if (-not $Zip.GetEntry($Required)) { throw "File penting tidak ditemukan: $Required" }
    }
    $DatabasePath = Join-Path $RestoreRoot 'database.sqlite'
    [IO.Compression.ZipFileExtensions]::ExtractToFile($Zip.GetEntry('database/database.sqlite'), $DatabasePath, $false)
    $Probe = @"
`$db = new PDO('sqlite:'.`$argv[1]);
`$integrity = `$db->query('PRAGMA integrity_check')->fetchColumn();
`$foreign = `$db->query('PRAGMA foreign_key_check')->fetchAll();
if (`$integrity !== 'ok' || count(`$foreign) !== 0) { exit(1); }
echo json_encode(['integrity'=>`$integrity,'announcements'=>(int)`$db->query('SELECT COUNT(*) FROM announcements')->fetchColumn(),'photos'=>(int)`$db->query('SELECT COUNT(*) FROM announcement_photos')->fetchColumn(),'paths'=>`$db->query('SELECT path FROM announcement_photos')->fetchAll(PDO::FETCH_COLUMN)]);
"@
    $ProbeOutput = & $PhpBinary -r $Probe $DatabasePath
    if ($LASTEXITCODE -ne 0) { throw 'Uji restore SQLite gagal.' }
    $Data = $ProbeOutput | ConvertFrom-Json
    foreach ($Photo in $Data.paths) {
        $EntryName = 'storage/app/private/'+$Photo
        $Entry = $Zip.GetEntry($EntryName)
        if (-not $Entry) { throw 'Foto yang direferensikan database tidak ada dalam arsip.' }
        $Destination = [IO.Path]::GetFullPath((Join-Path $RestoreRoot $EntryName))
        if (-not $Destination.StartsWith($RestoreRoot+'\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Path foto restore tidak valid.' }
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Destination) | Out-Null
        [IO.Compression.ZipFileExtensions]::ExtractToFile($Entry, $Destination, $false)
        if ((Get-FileHash -LiteralPath $Destination).Hash -ne $Expected[$EntryName].sha256) { throw 'Checksum foto hasil restore tidak cocok.' }
    }
} finally { $Zip.Dispose() }
$RestoreResult = [ordered]@{status='passed';sqlite_integrity=$Data.integrity;foreign_keys='passed';announcements=$Data.announcements;photos=$Data.photos;photo_restore='passed'}
Write-BackupJson $RestoreResult (Join-Path $Snapshot 'restore-test.json')
Copy-Item -LiteralPath (Join-Path $ProjectRoot 'README.md') -Destination (Join-Path $Snapshot 'RECOVERY.md')
$Manifest.files = $Inventory.Count
$Manifest.restore_test = $RestoreResult
$Manifest.artifacts = @(Get-ChildItem -LiteralPath $Snapshot -File | Where-Object {$_.Name -ne 'manifest.json'} | ForEach-Object {[ordered]@{name=$_.Name;bytes=$_.Length;sha256=(Get-FileHash -LiteralPath $_.FullName).Hash}})
$Manifest.status = 'verified'
Write-BackupJson $Manifest (Join-Path $Snapshot 'manifest.json')
Write-Output "Backup lengkap dan uji restore SQLite/foto terverifikasi: $Snapshot"