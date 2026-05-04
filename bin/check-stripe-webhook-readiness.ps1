param(
    [string]$BaseUrl = $(if ($env:A2BP_HTTP_PORT) { "http://localhost:$($env:A2BP_HTTP_PORT)" } else { 'http://localhost:8080' }),
    [string]$Container = 'a2billingplus-app-1'
)

$ErrorActionPreference = 'Stop'

function Test-EnvPresent {
    param([string]$Name)
    return -not [string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($Name))
}

function Test-ContainerEnvPresent {
    param(
        [string]$ContainerName,
        [string]$Name
    )

    $output = docker exec $ContainerName printenv $Name 2>$null
    return $LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($output)
}

function Add-Check {
    param(
        [System.Collections.Generic.List[object]]$Checks,
        [string]$Name,
        [bool]$Passed,
        [string]$Detail
    )

    $Checks.Add([pscustomobject]@{
        check = $Name
        passed = $Passed
        detail = $Detail
    })
}

$checks = [System.Collections.Generic.List[object]]::new()

$shellHasGenericSecret = Test-EnvPresent 'STRIPE_WEBHOOK_SECRET'
$shellHasModeSecret = (Test-EnvPresent 'STRIPE_TEST_WEBHOOK_SECRET') -or (Test-EnvPresent 'STRIPE_LIVE_WEBHOOK_SECRET')
Add-Check $checks 'shell_webhook_secret' ($shellHasGenericSecret -or $shellHasModeSecret) 'Shell has STRIPE_WEBHOOK_SECRET or mode-specific webhook secret.'

$envStripeExists = Test-Path -LiteralPath '.env.stripe'
$envLocalExists = Test-Path -LiteralPath '.env.local'
Add-Check $checks 'local_env_overlay' ($envStripeExists -or $envLocalExists) '.env.stripe or .env.local exists for Docker Compose overlay loading.'

$containerRunning = $false
try {
    $containerId = docker ps --filter "name=^/${Container}$" --format '{{.ID}}'
    $containerRunning = -not [string]::IsNullOrWhiteSpace($containerId)
} catch {
    $containerRunning = $false
}
Add-Check $checks 'app_container_running' $containerRunning "Container $Container is running."

if ($containerRunning) {
    $containerHasGenericSecret = Test-ContainerEnvPresent -ContainerName $Container -Name 'STRIPE_WEBHOOK_SECRET'
    $containerHasTestSecret = Test-ContainerEnvPresent -ContainerName $Container -Name 'STRIPE_TEST_WEBHOOK_SECRET'
    $containerHasLiveSecret = Test-ContainerEnvPresent -ContainerName $Container -Name 'STRIPE_LIVE_WEBHOOK_SECRET'
    Add-Check $checks 'container_webhook_secret' ($containerHasGenericSecret -or $containerHasTestSecret -or $containerHasLiveSecret) 'Container has STRIPE_WEBHOOK_SECRET or mode-specific webhook secret.'
}

$healthOk = $false
try {
    $health = Invoke-WebRequest -Uri "$BaseUrl/health.php" -UseBasicParsing -TimeoutSec 5
    $healthOk = $health.StatusCode -eq 200
} catch {
    $healthOk = $false
}
Add-Check $checks 'app_health' $healthOk "GET $BaseUrl/health.php returns HTTP 200."

$endpointReachable = $false
try {
    Invoke-WebRequest -Uri "$BaseUrl/api/v1/payment-webhooks.php?provider=stripe" -Method Post -ContentType 'application/json' -Body '{}' -UseBasicParsing -TimeoutSec 5 | Out-Null
} catch {
    $response = $_.Exception.Response
    if ($response -and [int]$response.StatusCode -in @(401, 422)) {
        $endpointReachable = $true
    }
}
Add-Check $checks 'webhook_endpoint_reachable' $endpointReachable 'Webhook endpoint responds with an expected validation/signature error.'

$checks | ConvertTo-Json -Depth 4

$failed = @($checks | Where-Object { -not $_.passed })
if ($failed.Count -gt 0) {
    exit 1
}
