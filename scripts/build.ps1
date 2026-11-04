param([switch]$SyncSdk)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$root = Split-Path -Parent $PSScriptRoot
$name = Split-Path -Leaf $root
if ($SyncSdk -and $name -eq 'wp-snapppay') {
    $sdk = Join-Path (Split-Path -Parent $root) 'webinew-license-manager/sdk/class-webinew-license-client.php'
    Copy-Item -LiteralPath $sdk -Destination (Join-Path $root 'wp-snapppay/includes/class-webinew-license-client.php') -Force
}
$dist = Join-Path $root 'dist'
New-Item -ItemType Directory -Path $dist -Force | Out-Null
$folders = @($name)
if ($name -eq 'webinew-license-manager') { $folders += 'sdk' }
Add-Type -AssemblyName System.IO.Compression.FileSystem
$checksums = @()
foreach ($folder in $folders) {
    $zipName = if ($folder -eq 'sdk') { 'webinew-license-sdk.zip' } else { if ($folder -eq 'wp-snapppay') { 'wp-ghesti.zip' } else { "$folder.zip" } }
    $zipPath = Join-Path $dist $zipName
    Compress-Archive -LiteralPath (Join-Path $root $folder) -DestinationPath $zipPath -Force
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        $count = 0
        foreach ($entry in $archive.Entries) {
            if ($entry.FullName.EndsWith('/')) { continue }
            $stream = $entry.Open()
            $sha = [System.Security.Cryptography.SHA256]::Create()
            try { $hash = [BitConverter]::ToString($sha.ComputeHash($stream)).Replace('-', '') }
            finally { $stream.Dispose(); $sha.Dispose() }
            if ($hash -ne (Get-FileHash -LiteralPath (Join-Path $root $entry.FullName) -Algorithm SHA256).Hash) { throw "ZIP mismatch: $($entry.FullName)" }
            $count++
        }
        $expected = @(Get-ChildItem -LiteralPath (Join-Path $root $folder) -File -Recurse).Count
        if ($count -ne $expected) { throw 'ZIP file count mismatch.' }
    } finally { $archive.Dispose() }
    $checksums += ((Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash + '  ' + $zipName)
    Write-Output "PASS: $zipName ($count files)"
}
Set-Content -LiteralPath (Join-Path $dist 'SHA256SUMS.txt') -Value $checksums -Encoding ascii
