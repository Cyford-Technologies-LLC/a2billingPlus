param(
    [string]$BaseUrl = 'https://api.vectavoip.com'
)

$ErrorActionPreference = 'Stop'

$registerBody = @{
    install_key = 'a2bp_smoke_' + [guid]::NewGuid().ToString('N')
    company_name = 'VectaVoIP Smoke Test'
    company_domain = 'smoke.vectavoip.test'
    contact_name = 'Smoke Test'
    contact_email = 'smoke@vectavoip.test'
    contact_phone = '+15555550100'
    details = 'Deployment smoke test'
    app_name = 'A2BillingPlus'
    app_version = '0.1.0-alpha.1'
} | ConvertTo-Json -Compress

$registration = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/installations/register.php" -Method Post -ContentType 'application/json' -Body $registerBody

$headers = @{
    Authorization = 'Bearer ' + $registration.api_key
    'X-VectaVoIP-Secret' = $registration.api_secret
}

$status = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/installations/status.php" -Headers $headers
$rates = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/rates/preview.php?rate_deck=retail&currency=USD" -Headers $headers
$rotation = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/credentials/rotate.php" -Method Post -Headers $headers

$rotatedHeaders = @{
    Authorization = 'Bearer ' + $rotation.api_key
    'X-VectaVoIP-Secret' = $rotation.api_secret
}
$rotatedStatus = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/installations/status.php" -Headers $rotatedHeaders

[pscustomobject]@{
    success = $true
    installation_id = $registration.installation_id
    status = $status.status
    rate_rows = $rates.total_rows
    rotated_status = $rotatedStatus.status
} | ConvertTo-Json -Depth 5
