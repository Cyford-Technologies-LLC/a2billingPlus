param(
    [string]$BaseUrl = 'http://localhost:8080',
    [int]$TimeoutSeconds = 10
)

$ErrorActionPreference = 'Stop'

$paths = @(
    '/',
    '/admin/Public/index.php',
    '/customer/index.php',
    '/agent/index.php',
    '/api/v1/providers.php',
    '/health.php'
)

$results = @()
foreach ($path in $paths) {
    $uri = $BaseUrl.TrimEnd('/') + $path
    try {
        $response = Invoke-WebRequest -Uri $uri -Method Get -TimeoutSec $TimeoutSeconds -MaximumRedirection 5
        $results += [pscustomobject]@{
            url = $uri
            success = $response.StatusCode -ge 200 -and $response.StatusCode -lt 500
            status = [int]$response.StatusCode
            message = 'loaded'
        }
    } catch {
        $status = 0
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $status = [int]$_.Exception.Response.StatusCode
        }
        $results += [pscustomobject]@{
            url = $uri
            success = $false
            status = $status
            message = $_.Exception.Message
        }
    }
}

$failed = @($results | Where-Object { -not $_.success })
$results | ConvertTo-Json -Depth 4

if ($failed.Count -gt 0) {
    exit 1
}
