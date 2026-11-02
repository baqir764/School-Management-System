# Copies the project to a fresh copy at the path you specified, then builds
# the two upload-ready zip files THERE. Your original project inside
# C:\xampp\htdocs\School-Management-System is never touched, moved, or
# modified in any way.
#
# How to run:
#   1. Open PowerShell
#   2. cd C:\xampp\htdocs\School-Management-System
#   3. powershell -ExecutionPolicy Bypass -File "Claude outputs\prepare-deploy.ps1"

# IMPORTANT: this turns off PowerShell's progress-bar UI. For folders with
# thousands of small files (like vendor/), that progress bar is what makes
# Compress-Archive look "frozen" - it's not actually stuck, the progress
# redraw itself is the bottleneck. Turning it off makes this dramatically
# faster.
$ProgressPreference = 'SilentlyContinue'

$src  = "C:\xampp\htdocs\School-Management-System"
$work = "C:\Users\AAIWO\Document\Desktop\School-Management-System"

Write-Host "Copying project to: $work"
Write-Host "(this can take a few minutes because of the vendor/ folder - please wait)"

# robocopy /MIR mirrors $src into $work (adds/updates changed files and
# removes anything in $work that isn't in $src) without needing to delete
# the $work folder first - so this works even if $work is your current
# directory. It also copies hidden files like .env and .gitignore, and is
# much faster/more reliable than Copy-Item for large folders such as vendor/.
robocopy $src $work /MIR /NFL /NDL /NJH /NJS | Out-Null

# --- InfinityFree-specific prep ---

# 1. Config.dat: InfinityFree silently deletes any file literally named
#    ".env" the moment it hits the server. This project's bootstrap/app.php
#    checks for a file named "config.dat" at the app base path and uses it
#    instead. Copy the live config.dat into the work copy so app-root.zip
#    includes it (bootstrap/app.php will find it there on the server).
$configSrc = Join-Path $src "Claude outputs\config.dat"
if (Test-Path $configSrc) {
    Copy-Item $configSrc -Destination (Join-Path $work "config.dat") -Force
    Write-Host "Copied config.dat (InfinityFree production env) into work copy"
} else {
    Write-Host "WARNING: Claude outputs\config.dat not found - you must create it manually in the app root on the server"
}

# 2. Remove stale local .env from work copy so it's not in app-root.zip.
#    The server uses config.dat instead; shipping the local .env (which
#    references localhost) is confusing even though it's inert.
$envFile = Join-Path $work ".env"
if (Test-Path $envFile) { Remove-Item $envFile -Force }

# 3. Remove stale compiled caches from bootstrap/cache/ before zipping.
#    On InfinityFree there is no PHP CLI, so you can't run `php artisan
#    route:clear`. If a stale routes-v7.php is shipped, the new routes
#    (like storage.local) won't register. Deleting the cache files lets
#    Laravel rebuild them at runtime on the server on first request.
Get-ChildItem -Path (Join-Path $work "bootstrap\cache") -Filter *.php -ErrorAction SilentlyContinue |
    Remove-Item -Force
Write-Host "Cleared stale bootstrap/cache php files"

# 4. Remove the local public/storage copy from the work copy. Locally,
#    public/storage is a symlink to storage/app/public that
#    `php artisan storage:link` created; robocopy followed it when mirroring,
#    so the work copy may contain a real public/storage directory full of
#    uploaded files. On the server there is no symlink (the host has no PHP
#    CLI to run storage:link), and the app serves uploads through the
#    storage.local route straight from storage/app/public instead. Leaving
#    the copy in would duplicate those files into htdocs/storage/ and let
#    them go stale as soon as new uploads are written to the app storage.
$pubStorage = Join-Path $work "public\storage"
if (Test-Path $pubStorage) {
    Remove-Item $pubStorage -Recurse -Force
    Write-Host "Removed public/storage copy (uploads served via storage.local route)"
}

# --- Zip creation ---
#
# Uses .NET's ZipArchive instead of Compress-Archive. Compress-Archive is
# extremely slow (and effectively hangs) on folders with thousands of tiny
# files like vendor/ (~9,500 files, ~70 MB) - the built-in cmdlet timed out
# at 5+ minutes. ZipArchive with CompressionLevel::Fastest finishes in well
# under a minute.

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$out = Join-Path $work "_deploy"
New-Item -ItemType Directory -Path $out -Force | Out-Null

# Adds every file under $dir to $zip, preserving paths relative to $baseDir.
function Add-TreeToZip {
    param($zip, $dir, $baseDir)
    $offset = $baseDir.Length + 1
    foreach ($f in [System.IO.Directory]::GetFiles($dir)) {
        $rel = $f.Substring($offset) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip, $f, $rel, [System.IO.Compression.CompressionLevel]::Fastest) | Out-Null
    }
    foreach ($d in [System.IO.Directory]::GetDirectories($dir)) {
        Add-TreeToZip $zip $d $baseDir
    }
}

Write-Host "Zipping public/ contents (these go INSIDE htdocs/ on the server)..."
$htdocsZip = Join-Path $out "htdocs-contents.zip"
Remove-Item $htdocsZip -Force -ErrorAction SilentlyContinue
$pub = Join-Path $work "public"
$zip = [System.IO.Compression.ZipFile]::Open($htdocsZip, [System.IO.Compression.ZipArchiveMode]::Create)
foreach ($it in (Get-ChildItem -Path $pub -Force)) {
    if ($it.PSIsContainer) { Add-TreeToZip $zip $it.FullName $pub }
    else {
        $rel = $it.FullName.Substring($pub.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip, $it.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
}
$zip.Dispose()

Write-Host "Zipping the rest of the app (these go ONE LEVEL UP, next to htdocs/)..."
$exclude = @(
    'public', '_deploy', 'node_modules', '.git',
    '.phpunit.result.cache', 'Claude outputs', 'storage.zip',
    'package.json', 'package-lock.json', 'vite.config.js',
    '.env', '.env.example'
)
$appZip = Join-Path $out "app-root.zip"
Remove-Item $appZip -Force -ErrorAction SilentlyContinue
$zip = [System.IO.Compression.ZipFile]::Open($appZip, [System.IO.Compression.ZipArchiveMode]::Create)
$items = Get-ChildItem -Path $work -Force | Where-Object { $exclude -notcontains $_.Name }
foreach ($it in $items) {
    if ($it.PSIsContainer) { Add-TreeToZip $zip $it.FullName $work }
    else {
        $rel = $it.FullName.Substring($work.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip, $it.FullName, $rel, [System.IO.Compression.CompressionLevel]::Fastest) | Out-Null
    }
}
$zip.Dispose()

Write-Host ""
Write-Host "Done! Your original project in $src was NOT touched."
Write-Host "The copy is here: $work"
Write-Host "Zips ready in: $out"
Write-Host ""
Write-Host "Upload instructions for InfinityFree (school-management-system.freedev.app):"
Write-Host ""
Write-Host "  1. Extract app-root.zip contents into the account root"
Write-Host "     (the folder that CONTAINS htdocs)."
Write-Host "     - config.dat sits at the root, next to bootstrap/."
Write-Host ""
Write-Host "  2. Extract htdocs-contents.zip into htdocs/."
Write-Host "     - This includes public/index.php, public/build/,"
Write-Host "       .htaccess, vendor/jsqr.min.js, etc."
Write-Host ""
Write-Host "  3. Run database migrations + seeders (if first deploy):"
Write-Host "     php artisan migrate:fresh --seed"
Write-Host "     (Use InfinityFree's PHP-cli or a web-based runner"
Write-Host "     if available, or import the DB separately.)"
Write-Host ""
Write-Host "  4. Set permissions on storage and bootstrap/cache"
Write-Host "     to 775 via FTP or InfinityFree panel if 500 errors occur."
Write-Host ""
Write-Host "  5. Demo logins:"
Write-Host "     admin@school.local / Passw0rd!123"
Write-Host "     attendance.clerk@school.local / Passw0rd!123"
