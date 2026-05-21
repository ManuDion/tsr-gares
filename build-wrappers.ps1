param(
    [switch]$SkipWindows,
    [switch]$SkipAndroid,
    [ValidateSet('Debug', 'Release', 'Both')]
    [string]$AndroidVariant = 'Both'
)

$ErrorActionPreference = 'Stop'

$root = 'D:\Application Laravel\TSR_15_05'
$windowsWrapper = Join-Path $root 'wrappers\windows'
$androidWrapper = Join-Path $root 'wrappers\android'
$androidNative = Join-Path $androidWrapper 'android'
$sdkLocal = Join-Path $androidWrapper 'sdk-local'
$jre21 = Join-Path $root 'wrappers\tools\jdk21\jdk-21.0.11+10'
$keytool = Join-Path $jre21 'bin\keytool.exe'

function Invoke-Step([string]$Title, [scriptblock]$Action) {
    Write-Host "`n=== $Title ===" -ForegroundColor Cyan
    & $Action
}

function Invoke-External {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FilePath,
        [string[]]$ArgumentList = @()
    )

    & $FilePath @ArgumentList
    if ($LASTEXITCODE -ne 0) {
        throw "Echec de la commande: $FilePath $($ArgumentList -join ' ')"
    }
}

if (-not (Test-Path $jre21)) {
    throw "JDK 21 introuvable: $jre21"
}
if (-not (Test-Path $sdkLocal)) {
    throw "SDK Android local introuvable: $sdkLocal"
}

$env:JAVA_HOME = $jre21
$env:Path = "$($env:JAVA_HOME)\bin;$($env:Path)"

Invoke-Step 'Preparation Android local.properties' {
    $sdkForProperties = $sdkLocal -replace '\\', '/'
    "sdk.dir=$sdkForProperties" | Set-Content -Encoding ascii (Join-Path $androidNative 'local.properties')
}

if (-not $SkipWindows) {
    Invoke-Step 'Build EXE Windows' {
        Push-Location $windowsWrapper
        try {
            Invoke-External -FilePath 'npm.cmd' -ArgumentList @('install', '--no-audit', '--no-fund')
            Invoke-External -FilePath 'npm.cmd' -ArgumentList @('run', 'build:win')
        } finally {
            Pop-Location
        }
    }
}

if (-not $SkipAndroid) {
    Invoke-Step 'Preparation signature Android release' {
        $storePassword = if ($env:TSR_RELEASE_STORE_PASSWORD) { $env:TSR_RELEASE_STORE_PASSWORD } else { 'TsrRelease@2026' }
        $keyPassword = if ($env:TSR_RELEASE_KEY_PASSWORD) { $env:TSR_RELEASE_KEY_PASSWORD } else { $storePassword }
        $alias = if ($env:TSR_RELEASE_KEY_ALIAS) { $env:TSR_RELEASE_KEY_ALIAS } else { 'tsr_release' }
        $storeFileAbs = Join-Path $androidNative 'keystore\tsr-release.jks'
        $storeFileRel = 'keystore/tsr-release.jks'

        New-Item -ItemType Directory -Force -Path (Join-Path $androidNative 'keystore') | Out-Null

        if (-not (Test-Path $storeFileAbs)) {
            & $keytool -genkeypair -v -storetype PKCS12 -keystore $storeFileAbs -alias $alias -keyalg RSA -keysize 2048 -validity 3650 -storepass $storePassword -keypass $keyPassword -dname 'CN=TSR Cote dIvoire, OU=IT, O=TSR, L=Abidjan, ST=Abidjan, C=CI' | Out-Host
        }

        @(
            "storeFile=$storeFileRel"
            "storePassword=$storePassword"
            "keyAlias=$alias"
            "keyPassword=$keyPassword"
        ) | Set-Content -Encoding ascii (Join-Path $androidNative 'keystore.properties')
    }

    Invoke-Step 'Sync Capacitor Android' {
        Push-Location $androidWrapper
        try {
            Invoke-External -FilePath 'npm.cmd' -ArgumentList @('install', '--no-audit', '--no-fund')
            Invoke-External -FilePath 'npm.cmd' -ArgumentList @('run', 'cap:sync')
        } finally {
            Pop-Location
        }
    }

    Invoke-Step 'Build APK Android' {
        Push-Location $androidNative
        try {
            Invoke-External -FilePath '.\gradlew.bat' -ArgumentList @('--no-daemon', 'clean')

            if ($AndroidVariant -eq 'Debug' -or $AndroidVariant -eq 'Both') {
                Invoke-External -FilePath '.\gradlew.bat' -ArgumentList @('--no-daemon', 'assembleDebug')
            }
            if ($AndroidVariant -eq 'Release' -or $AndroidVariant -eq 'Both') {
                Invoke-External -FilePath '.\gradlew.bat' -ArgumentList @('--no-daemon', 'assembleRelease')
            }
        } finally {
            Pop-Location
        }
    }
}

Write-Host "`n=== Artifacts ===" -ForegroundColor Green
if (-not $SkipWindows) {
    Write-Host "EXE: $windowsWrapper\dist\GestionTSR-win32-x64\GestionTSR.exe"
}
if (-not $SkipAndroid) {
    if ($AndroidVariant -eq 'Debug' -or $AndroidVariant -eq 'Both') {
        Write-Host "APK Debug: $androidNative\app\build\outputs\apk\debug\app-debug.apk"
    }
    if ($AndroidVariant -eq 'Release' -or $AndroidVariant -eq 'Both') {
        Write-Host "APK Release: $androidNative\app\build\outputs\apk\release\app-release.apk"
    }
}
