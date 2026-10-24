# Menjalankan Presensi Aman agar bisa dibuka dari HP di jaringan WiFi yang sama.
# Jalankan lewat start-lan.bat di folder D:\ade (atau klik dua kali file tersebut).

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$HttpsPort = 8443
$LaravelPort = 8080
$AiPort = 8000
$FirewallRuleName = 'Presensi Aman HTTPS'
$Python = Join-Path $Root 'ai-service\.venv\Scripts\python.exe'
$CertDir = Join-Path $PSScriptRoot 'cert'
$CertFile = Join-Path $CertDir 'cert.pem'
$KeyFile = Join-Path $CertDir 'key.pem'
$CertIpFile = Join-Path $CertDir 'ip.txt'
$CaFile = Join-Path $CertDir 'ca.pem'
$CaKeyFile = Join-Path $CertDir 'ca-key.pem'
$CertVersion = 'v2'

function Test-PortListening([int] $Port) {
    $null -ne (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
}

function Get-LanIp {
    $wifi = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.InterfaceAlias -match 'Wi-Fi|WLAN|Wireless' -and $_.IPAddress -notlike '169.254.*' } |
        Select-Object -First 1 -ExpandProperty IPAddress
    if ($wifi) { return $wifi }

    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -ErrorAction SilentlyContinue |
        Sort-Object RouteMetric | Select-Object -First 1
    if ($route) {
        return Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $route.InterfaceIndex |
            Select-Object -First 1 -ExpandProperty IPAddress
    }

    return $null
}

function Get-OpenSsl {
    $command = Get-Command openssl -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }

    foreach ($path in @(
        "$env:ProgramFiles\Git\usr\bin\openssl.exe",
        "$env:ProgramFiles\Git\mingw64\bin\openssl.exe",
        "${env:ProgramFiles(x86)}\Git\usr\bin\openssl.exe"
    )) {
        if (Test-Path $path) { return $path }
    }

    return $null
}

if (-not (Test-Path $Python)) {
    throw "Python virtualenv tidak ditemukan: $Python"
}

$ip = Get-LanIp
if (-not $ip) {
    throw 'Tidak menemukan IP WiFi. Pastikan laptop sudah terhubung ke WiFi.'
}

# 1. Sertifikat HTTPS
#    - CA lokal dibuat sekali saja, supaya HP cukup memasangnya satu kali.
#    - Sertifikat server ditandatangani CA itu dan dibuat ulang jika IP laptop berubah.
function Invoke-OpenSsl([string[]] $Arguments) {
    # openssl menulis progres ke stderr; jangan anggap itu error.
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & $script:OpenSsl @Arguments 2>$null | Out-Null
    $exitCode = $LASTEXITCODE
    $ErrorActionPreference = $previous
    if ($exitCode -ne 0) { throw "openssl gagal: $($Arguments -join ' ')" }
}

$certMarker = "$CertVersion $ip"
$existingMarker = if (Test-Path $CertIpFile) { (Get-Content $CertIpFile -Raw).Trim() } else { '' }
$needsCa = -not (Test-Path $CaFile) -or -not (Test-Path $CaKeyFile)
$needsServerCert = $needsCa -or -not (Test-Path $CertFile) -or -not (Test-Path $KeyFile) -or $existingMarker -ne $certMarker

if ($needsServerCert) {
    $script:OpenSsl = Get-OpenSsl
    if (-not $script:OpenSsl) {
        throw 'openssl tidak ditemukan. Install Git for Windows (sudah termasuk openssl).'
    }

    New-Item -ItemType Directory -Force $CertDir | Out-Null

    if ($needsCa) {
        Write-Host 'Membuat CA lokal (sekali saja)...'
        Invoke-OpenSsl @('req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-days', '3650',
            '-keyout', $CaKeyFile, '-out', $CaFile, '-subj', '/O=Presensi Aman/CN=Presensi Aman Local CA',
            '-addext', 'basicConstraints=critical,CA:TRUE',
            '-addext', 'keyUsage=critical,keyCertSign,cRLSign')
    }

    Write-Host "Membuat sertifikat HTTPS untuk $ip ..."
    $csrFile = Join-Path $CertDir 'server.csr'
    $extFile = Join-Path $CertDir 'server.ext'
    Set-Content -Path $extFile -Encoding ascii -Value @(
        'basicConstraints=critical,CA:FALSE',
        'keyUsage=critical,digitalSignature,keyEncipherment',
        'extendedKeyUsage=serverAuth',
        "subjectAltName=IP:$ip,IP:127.0.0.1,DNS:localhost"
    )
    Invoke-OpenSsl @('req', '-newkey', 'rsa:2048', '-nodes', '-sha256',
        '-keyout', $KeyFile, '-out', $csrFile, '-subj', "/O=Presensi Aman/CN=$ip")
    Invoke-OpenSsl @('x509', '-req', '-in', $csrFile, '-CA', $CaFile, '-CAkey', $CaKeyFile, '-CAcreateserial',
        '-out', $CertFile, '-days', '397', '-sha256', '-extfile', $extFile)
    Remove-Item $csrFile, $extFile -ErrorAction SilentlyContinue
    Set-Content -Path $CertIpFile -Value $certMarker -Encoding ascii
}

# 2. Izinkan port HTTPS di Windows Firewall (sekali saja, butuh izin Administrator)
if (-not (Get-NetFirewallRule -DisplayName $FirewallRuleName -ErrorAction SilentlyContinue)) {
    Write-Host "Meminta izin Administrator untuk membuka port $HttpsPort di firewall..."
    $ruleCommand = "New-NetFirewallRule -DisplayName '$FirewallRuleName' -Direction Inbound -Protocol TCP -LocalPort $HttpsPort -Action Allow -Profile Any"
    try {
        Start-Process powershell -Verb RunAs -Wait -ArgumentList '-NoProfile', '-Command', $ruleCommand
    } catch {
        Write-Warning "Firewall belum dibuka. HP mungkin tidak bisa terhubung. Jalankan sebagai Administrator: $ruleCommand"
    }
}

# 3. Jalankan AI service dan Laravel di jendela terpisah (kalau belum berjalan)
if (Test-PortListening $AiPort) {
    Write-Host "AI service sudah berjalan di port $AiPort."
} else {
    Start-Process powershell -WorkingDirectory (Join-Path $Root 'ai-service') -ArgumentList '-NoExit', '-Command',
        "`$Host.UI.RawUI.WindowTitle = 'AI service :$AiPort'; & '$Python' -m uvicorn main:app --host 127.0.0.1 --port $AiPort"
}

if (Test-PortListening $LaravelPort) {
    Write-Host "Laravel sudah berjalan di port $LaravelPort."
} else {
    Start-Process powershell -WorkingDirectory (Join-Path $Root 'web') -ArgumentList '-NoExit', '-Command',
        "`$Host.UI.RawUI.WindowTitle = 'Laravel :$LaravelPort'; php artisan serve --host=127.0.0.1 --port=$LaravelPort"
}

# 4. Proxy HTTPS untuk HP (berjalan di jendela ini)
Write-Host ''
Write-Host '==================================================================' -ForegroundColor Cyan
Write-Host "  Buka di HP (WiFi yang sama):  https://${ip}:$HttpsPort" -ForegroundColor Green
Write-Host "  Buka di laptop ini:           http://127.0.0.1:$LaravelPort" -ForegroundColor Green
Write-Host ''
Write-Host "  Pertama kali di HP, buka dulu:  http://${ip}:$HttpsPort" -ForegroundColor Yellow
Write-Host '  lalu ikuti petunjuk memasang sertifikat (sekali saja per HP).'
Write-Host ''
Write-Host '  Tekan Ctrl+C untuk menghentikan proxy.'
Write-Host '==================================================================' -ForegroundColor Cyan
Write-Host ''

& $Python (Join-Path $PSScriptRoot 'https_proxy.py') $CertFile $KeyFile $CaFile $HttpsPort $LaravelPort
