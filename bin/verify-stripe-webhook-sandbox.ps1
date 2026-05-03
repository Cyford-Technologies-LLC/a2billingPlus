param(
    [string]$BaseUrl = $(if ($env:A2BP_HTTP_PORT) { "http://localhost:$($env:A2BP_HTTP_PORT)" } else { 'http://localhost:8080' }),
    [string]$PayloadPath = '',
    [string]$EventId = '',
    [string]$EventType = 'payment_intent.succeeded',
    [switch]$VerifyDuplicate
)

$ErrorActionPreference = 'Stop'

function Get-RequiredEnv {
    param([string]$Name)

    $value = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrWhiteSpace($value)) {
        throw "$Name is required. Put the same value in .env as STRIPE_WEBHOOK_SECRET, restart the app container, and export it in this shell."
    }

    return $value
}

function New-StripeSignature {
    param(
        [string]$Payload,
        [string]$Secret,
        [int64]$Timestamp
    )

    $encoding = [System.Text.Encoding]::UTF8
    $keyBytes = $encoding.GetBytes($Secret)
    $signedPayload = $encoding.GetBytes("$Timestamp.$Payload")
    $hmac = [System.Security.Cryptography.HMACSHA256]::new($keyBytes)
    try {
        $hash = $hmac.ComputeHash($signedPayload)
    } finally {
        $hmac.Dispose()
    }

    $hex = -join ($hash | ForEach-Object { $_.ToString('x2') })
    return "t=$Timestamp,v1=$hex"
}

function New-DefaultPayload {
    param(
        [string]$Id,
        [string]$Type
    )

    if ($Id -eq '') {
        $Id = 'evt_a2bp_sandbox_' + [guid]::NewGuid().ToString('N')
    }

    $payload = [ordered]@{
        id = $Id
        object = 'event'
        api_version = '2026-05-01'
        created = [int][double]::Parse((Get-Date -UFormat %s))
        livemode = $false
        type = $Type
        data = @{
            object = @{
                id = 'pi_a2bp_sandbox_' + [guid]::NewGuid().ToString('N').Substring(0, 16)
                object = 'payment_intent'
                amount = 1250
                currency = 'usd'
                status = if ($Type -eq 'payment_intent.payment_failed') { 'requires_payment_method' } else { 'succeeded' }
            }
        }
    }

    return $payload | ConvertTo-Json -Depth 8 -Compress
}

$secret = Get-RequiredEnv 'STRIPE_WEBHOOK_SECRET'

if ($PayloadPath -ne '') {
    if (-not (Test-Path -LiteralPath $PayloadPath)) {
        throw "PayloadPath not found: $PayloadPath"
    }
    $payload = Get-Content -LiteralPath $PayloadPath -Raw
} else {
    $payload = New-DefaultPayload -Id $EventId -Type $EventType
}

$timestamp = [int64][double]::Parse((Get-Date -UFormat %s))
$signature = New-StripeSignature -Payload $payload -Secret $secret -Timestamp $timestamp
$uri = "$BaseUrl/api/v1/payment-webhooks.php?provider=stripe"

Write-Host "POST $uri"
Write-Host "Stripe-Signature: t=$timestamp,v1=<computed>"

$headers = @{ 'Stripe-Signature' = $signature }
$response = Invoke-RestMethod -Uri $uri -Method Post -ContentType 'application/json' -Headers $headers -Body $payload
$response | ConvertTo-Json -Depth 8

if ($VerifyDuplicate) {
    Write-Host ''
    Write-Host 'Duplicate replay check'
    $duplicate = Invoke-RestMethod -Uri $uri -Method Post -ContentType 'application/json' -Headers $headers -Body $payload
    $duplicate | ConvertTo-Json -Depth 8
}
