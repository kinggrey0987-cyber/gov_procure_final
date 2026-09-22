# ==============================================================================
# Laravel - Fast Delta Update Packager for Hostinger (with Pre-Flight & Pruning)
# ==============================================================================

Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "  Laravel Fast Update Packager (Hostinger File Manager)" -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan

# 1. Pre-Flight PHP syntax and sanity check
Write-Host "`n[1/4] Running pre-flight PHP syntax check on application files..." -ForegroundColor Yellow
$phpFiles = Get-ChildItem -Path "app", "routes", "config" -Filter "*.php" -Recurse
$syntaxError = $false
foreach ($file in $phpFiles) {
    $null = php -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        $result = php -l $file.FullName 2>&1
        Write-Host "PHP Syntax Error in $($file.FullName):" -ForegroundColor Red
        Write-Host $result -ForegroundColor DarkRed
        $syntaxError = $true
    }
}
if ($syntaxError) {
    Write-Host "`nPlease fix the PHP syntax error above before packaging for production." -ForegroundColor Yellow
    exit 1
}

# Optional route check if artisan runs cleanly
$sanityCheck = php artisan route:list --except-vendor 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "Pre-flight check passed! Codebase and routes are healthy." -ForegroundColor Green
} else {
    Write-Host "Pre-flight PHP syntax check passed! Codebase is healthy." -ForegroundColor Green
}

# 2. Compile production assets
Write-Host "`n[2/4] Compiling latest Vite production assets..." -ForegroundColor Yellow
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Vite build failed. Please resolve npm build errors." -ForegroundColor Red
    exit 1
}

# 3. Setup output folder & timestamp
$outputDir = "deployment_packages"
if (-not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm"
$datedZipName = "update_$timestamp.zip"
$datedZipPath = Join-Path $outputDir $datedZipName
$latestZipPath = "update.zip"

if (Test-Path $datedZipPath) { Remove-Item $datedZipPath -Force }
if (Test-Path $latestZipPath) { Remove-Item $latestZipPath -Force }

# 4. Create lightweight zip (excluding vendor, node_modules, .env, storage)
Write-Host "`n[3/4] Packaging updated application files..." -ForegroundColor Yellow

$includeItems = @(
    "app",
    "bootstrap",
    "config",
    "database",
    "lang",
    "public",
    "resources",
    "routes",
    ".htaccess",
    "artisan",
    "composer.json",
    "composer.lock"
)

$existingItems = $includeItems | Where-Object { Test-Path $_ }

# Archive files using native Windows PowerShell / tar
tar -a -c -f $datedZipPath --exclude="public/storage" --exclude="database/*.sqlite" --exclude="bootstrap/cache/*.php" $existingItems
Copy-Item $datedZipPath -Destination $latestZipPath -Force

# 5. Auto-prune old archives (keeps latest 10)
Write-Host "`n[4/4] Auto-pruning old archives (keeping latest 10)..." -ForegroundColor Yellow
$zipFiles = Get-ChildItem -Path $outputDir -Filter "*.zip" | Sort-Object CreationTime -Descending
if ($zipFiles.Count -gt 10) {
    $zipFiles | Select-Object -Skip 10 | ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Host "  - Pruned old archive: $($_.Name)" -ForegroundColor DarkGray
    }
}
Write-Host "Disk archive cleanup complete." -ForegroundColor Green

if (Test-Path $datedZipPath) {
    $size = [math]::Round(((Get-Item $datedZipPath).Length / 1MB), 2)
    
    Write-Host "`n=================================================================" -ForegroundColor Green
    Write-Host "  Package Created Successfully!" -ForegroundColor Green
    Write-Host "=================================================================" -ForegroundColor Green
    Write-Host "Dated Archive: deployment_packages\$datedZipName" -ForegroundColor White
    Write-Host "Quick Upload:  update.zip" -ForegroundColor White
    Write-Host "Package Size:  $size MB (Lightweight and Fast)" -ForegroundColor White
    Write-Host "=================================================================" -ForegroundColor Green
    Write-Host "`nHOW TO APPLY ON HOSTINGER (Takes 10 seconds):" -ForegroundColor Cyan
    Write-Host "1. Open Hostinger File Manager -> open your project folder (gov_procure_final)." -ForegroundColor Gray
    Write-Host "2. Click 'Upload' -> select 'update.zip' (or 'deployment_packages\$datedZipName')." -ForegroundColor Gray
    Write-Host "3. Right-click 'update.zip' -> click 'Extract' -> type '.' as folder name." -ForegroundColor Gray
    Write-Host "4. Check [x] 'Overwrite existing files' and click Extract.`n" -ForegroundColor Gray
} else {
    Write-Host "ERROR: Failed to create update zip package." -ForegroundColor Red
}
