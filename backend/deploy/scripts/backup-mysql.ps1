param(
    [Parameter(Mandatory = $true)][string]$DefaultsFile,
    [string]$MySqlDumpPath = 'C:\xampp\mysql\bin\mysqldump.exe',
    [Parameter(Mandatory = $true)][string]$Database,
    [Parameter(Mandatory = $true)][string]$BackupDirectory,
    [int]$RetentionDays = 30,
    [string]$GpgRecipient = '',
    [string]$GpgPath = 'gpg.exe'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $DefaultsFile)) { throw "MySQL defaults file was not found: $DefaultsFile" }
if (-not (Test-Path -LiteralPath $MySqlDumpPath)) { throw "mysqldump was not found: $MySqlDumpPath" }
New-Item -ItemType Directory -Force -Path $BackupDirectory | Out-Null

$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ')
$temporarySql = Join-Path $BackupDirectory ".talentxpanse-$stamp.sql"
$compressed = Join-Path $BackupDirectory "talentxpanse-$stamp.sql.gz"

try {
    $process = Start-Process -FilePath $MySqlDumpPath -ArgumentList "--defaults-extra-file=$DefaultsFile", '--single-transaction', '--quick', '--routines', '--events', '--triggers', '--set-gtid-purged=OFF', $Database -NoNewWindow -Wait -PassThru -RedirectStandardOutput $temporarySql
    if ($process.ExitCode -ne 0) { throw "mysqldump failed with exit code $($process.ExitCode)." }

    $input = [System.IO.File]::OpenRead($temporarySql)
    $output = [System.IO.File]::Create($compressed)
    $gzip = New-Object System.IO.Compression.GZipStream($output, [System.IO.Compression.CompressionLevel]::Optimal)
    try { $input.CopyTo($gzip) } finally { $gzip.Dispose(); $output.Dispose(); $input.Dispose() }

    if ($GpgRecipient) {
        $encrypted = "$compressed.gpg"
        & $GpgPath --batch --yes --trust-model always --encrypt --recipient $GpgRecipient --output $encrypted $compressed
        if ($LASTEXITCODE -ne 0) { throw 'GPG encryption failed; the unencrypted backup was retained for recovery.' }
        Remove-Item -LiteralPath $compressed -Force
        $compressed = $encrypted
    }

    Get-ChildItem -LiteralPath $BackupDirectory -Filter 'talentxpanse-*.sql.gz*' | Where-Object { $_.LastWriteTimeUtc -lt (Get-Date).ToUniversalTime().AddDays(-$RetentionDays) } | Remove-Item -Force
    Write-Output "Backup created: $compressed"
} finally {
    if (Test-Path -LiteralPath $temporarySql) { Remove-Item -LiteralPath $temporarySql -Force }
}
