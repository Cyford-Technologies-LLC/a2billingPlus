param(
    [string[]]$Paths = @("admin", "agent", "customer", "common"),
    [int]$MaxFindings = 200,
    [switch]$IncludeThirdParty
)

$ErrorActionPreference = "Stop"

$thirdPartyPatterns = @(
    '\\jpgraph_lib\\',
    '\\lib\\mail\\',
    '\\lib\\phpagi\\',
    '\\pqp\\',
    '\\fpdf',
    '\\lib\\font\\makefont\\',
    '\\phpsysinfo\\',
    '\\tcpdf',
    '\\smarty',
    '\\vendor\\'
)

function Test-ThirdPartyPath {
    param([string]$Path)
    foreach ($pattern in $thirdPartyPatterns) {
        if ($Path -match $pattern) { return $true }
    }
    return $false
}

$files = @()
foreach ($path in $Paths) {
    if (Test-Path $path) {
        $files += Get-ChildItem -Path $path -Recurse -File -Include *.php,*.inc |
            Where-Object { $IncludeThirdParty -or !(Test-ThirdPartyPath $_.FullName) }
    }
}

$findings = @()

foreach ($file in $files) {
    $matches = Select-String -Path $file.FullName -Pattern '\$[A-Za-z_][A-Za-z0-9_]*\[[A-Za-z_][A-Za-z0-9_]*\]' -AllMatches
    foreach ($line in $matches) {
        foreach ($match in $line.Matches) {
            $findings += [pscustomobject]@{
                File = $file.FullName
                Line = $line.LineNumber
                Rule = "bare-array-key"
                Match = $match.Value
            }
        }
    }

    $matches = Select-String -Path $file.FullName -Pattern '(?<![A-Za-z0-9_\\])count\(\$[A-Za-z_][A-Za-z0-9_]*\)' -AllMatches
    foreach ($line in $matches) {
        foreach ($match in $line.Matches) {
            $findings += [pscustomobject]@{
                File = $file.FullName
                Line = $line.LineNumber
                Rule = "count-maybe-non-countable"
                Match = $match.Value
            }
        }
    }

    $matches = Select-String -Path $file.FullName -Pattern 'FILTER_SANITIZE_STRING|(?<![A-Za-z0-9_])mysql_[A-Za-z_]+\(' -AllMatches
    foreach ($line in $matches) {
        foreach ($match in $line.Matches) {
            $findings += [pscustomobject]@{
                File = $file.FullName
                Line = $line.LineNumber
                Rule = if ($match.Value -eq "FILTER_SANITIZE_STRING") { "deprecated-filter-sanitize-string" } else { "legacy-mysql-extension" }
                Match = $match.Value
            }
        }
    }
}

Write-Host "Scanned files: $($files.Count)"
Write-Host "Findings: $($findings.Count)"

$summary = $findings | Group-Object Rule | Sort-Object Name | ForEach-Object {
    [pscustomobject]@{ Rule = $_.Name; Count = $_.Count }
}
if ($summary) {
    $summary | Format-Table -AutoSize
}

if ($findings.Count -gt 0) {
    $findings |
        Sort-Object Rule, File, Line |
        Select-Object -First $MaxFindings |
        Format-Table -AutoSize -Wrap
    if ($findings.Count -gt $MaxFindings) {
        Write-Host "Showing first $MaxFindings findings. Increase -MaxFindings for more."
    }
    exit 1
}
