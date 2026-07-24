#!/usr/bin/env powershell
# Automated Railway POS Service Configuration Script
# Run this to set all required environment variables

Write-Host "🚀 Railway POS Service Configuration Script" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan

# Colors
$Success = @{ ForegroundColor = "Green" }
$Error = @{ ForegroundColor = "Red" }
$Info = @{ ForegroundColor = "Blue" }
$Warn = @{ ForegroundColor = "Yellow" }

# Database credentials
$DATABASE_URL = "mysql://root:nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO@switchback.proxy.rlwy.net:54987/railway"

Write-Host "`n📋 Configuration Details:" @Info
Write-Host "  - Service: pos"
Write-Host "  - Database: railway"
Write-Host "  - MySQL Host: switchback.proxy.rlwy.net (public proxy)"
Write-Host "  - MySQL Port: 54987"

# Check if railway CLI is available
try {
    $railwayVersion = railway --version 2>$null
    Write-Host "`n✓ Railway CLI found: $railwayVersion" @Success
} catch {
    Write-Host "`n✗ Railway CLI not found. Please install it first:" @Error
    Write-Host "  npm install -g @railway/cli"
    exit 1
}

# Check if logged in
Write-Host "`n🔐 Checking Railway authentication..." @Info
try {
    $whoami = railway whoami 2>$null
    Write-Host "✓ Logged in as: $whoami" @Success
} catch {
    Write-Host "✗ Not logged in. Run: railway login" @Error
    exit 1
}

# Verify project is linked
Write-Host "`n📍 Verifying project is linked..." @Info
try {
    $status = railway status 2>$null
    if ($status -match "Deligos Company POS") {
        Write-Host "✓ Project linked: Deligos Company POS" @Success
    } else {
        Write-Host "⚠ Project may not be linked. Run: railway project link" @Warn
    }
} catch {
    Write-Host "⚠ Could not verify project status" @Warn
}

# Set environment variables
Write-Host "`n🔧 Setting environment variables on 'pos' service..." @Info
Write-Host ""

$variables = @(
    @{ name = "DATABASE_URL"; value = $DATABASE_URL }
    @{ name = "DB_HOST"; value = "switchback.proxy.rlwy.net" }
    @{ name = "DB_PORT"; value = "54987" }
    @{ name = "DB_NAME"; value = "railway" }
    @{ name = "DB_USER"; value = "root" }
    @{ name = "DB_PASS"; value = "nkeNTtgmLXheOiwwrvFgJYqdzmmqpCoO" }
)

foreach ($var in $variables) {
    Write-Host "  Setting $($var.name)..." -NoNewline
    try {
        # Quote the value properly for PowerShell
        $cmd = "railway variable set $($var.name)='$($var.value)'"
        Invoke-Expression $cmd 2>&1 | Out-Null
        Write-Host " ✓" @Success
    } catch {
        Write-Host " ✗ Failed: $_" @Error
    }
}

Write-Host "`n" @Info
Write-Host "✅ Configuration complete!" @Success
Write-Host ""
Write-Host "📝 Next steps:" @Info
Write-Host "  1. Redeploy the application:"
Write-Host "     - Push a git commit to trigger auto-deployment, OR"
Write-Host "     - Visit Railway dashboard and manually redeploy"
Write-Host ""
Write-Host "  2. Wait 2-3 minutes for deployment to complete"
Write-Host ""
Write-Host "  3. Verify the connection:"
Write-Host "     - Visit: https://deligoscompany.online/test_db_connection.php"
Write-Host "     - Should show: ✓ Database connection successful!"
Write-Host ""
Write-Host "  4. Test the application:"
Write-Host "     - Log in: https://deligoscompany.online"
Write-Host "     - Try adding products, completing sales"
Write-Host ""

Read-Host "Press Enter to exit"
