# ============================================================
# Angeln Brandenburg – Deploy Script
# Ausfuehren: .\scripts\deploy.ps1
# ============================================================

$SERVER   = "82.198.228.83"
$PORT     = "65002"
$USER     = "u525312957"
$REMOTE   = "/home/$USER/public_html"

# Pfad zu diesem Skript (Repo-Root)
$REPO = Split-Path -Parent $PSScriptRoot

Write-Host ""
Write-Host "=== Angeln Brandenburg Deploy ===" -ForegroundColor Cyan
Write-Host "Server : $SERVER"
Write-Host "Ziel   : $REMOTE"
Write-Host ""

# Passwort abfragen (nicht im Klartext in der Konsole)
$SecurePass = Read-Host "SSH-Passwort" -AsSecureString
$BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecurePass)
$PASS = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)

# Pruefe ob sshpass verfuegbar (fuer nicht-interaktiv)
# Unter Windows nutzen wir plink (PuTTY) oder den eingebauten SSH-Client

function Run-SSH {
    param($cmd)
    echo $PASS | ssh -o StrictHostKeyChecking=no -p $PORT "$USER@$SERVER" $cmd
}

function Upload-File {
    param($local, $remote)
    Write-Host "  Upload: $local -> $remote" -ForegroundColor Yellow
    scp -o StrictHostKeyChecking=no -P $PORT "$local" "$USER@$SERVER`:$remote"
}

# --- 1. maintenance.html hochladen ---
Write-Host "[1/3] Lade maintenance.html hoch..."
scp -o StrictHostKeyChecking=no -P $PORT `
    "$REPO\config\maintenance.html" `
    "$USER@$SERVER`:$REMOTE/maintenance.html"

# --- 2. .htaccess hochladen ---
Write-Host "[2/3] Lade .htaccess hoch..."
scp -o StrictHostKeyChecking=no -P $PORT `
    "$REPO\config\.htaccess" `
    "$USER@$SERVER`:$REMOTE/.htaccess"

# --- 3. robots.txt (Maintenance-Version) hochladen ---
Write-Host "[3/3] Lade robots.txt (maintenance) hoch..."
scp -o StrictHostKeyChecking=no -P $PORT `
    "$REPO\config\robots.txt.maintenance" `
    "$USER@$SERVER`:$REMOTE/robots.txt"

Write-Host ""
Write-Host "Fertig! Maintenance-Seite ist jetzt aktiv." -ForegroundColor Green
Write-Host "Pruefe: https://angeln-brandenburg.de"
Write-Host ""
