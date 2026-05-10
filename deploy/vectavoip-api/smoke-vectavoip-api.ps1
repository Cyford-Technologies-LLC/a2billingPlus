param(
    [string]$BaseUrl = 'https://api.vectavoip.com',
    [string]$ApiPrefix = '/v1'
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

$apiRoot = $BaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')

$registration = Invoke-RestMethod -Uri "$apiRoot/installations/register" -Method Post -ContentType 'application/json' -Body $registerBody

$headers = @{
    Authorization = 'Bearer ' + $registration.api_key
    'X-VectaVoIP-Secret' = $registration.api_secret
}

$status = Invoke-RestMethod -Uri "$apiRoot/installations/status" -Headers $headers
$rates = Invoke-RestMethod -Uri "$apiRoot/rates/preview?rate_deck=retail&currency=USD" -Headers $headers
$rotation = Invoke-RestMethod -Uri "$apiRoot/credentials/rotate" -Method Post -Headers $headers

$rotatedHeaders = @{
    Authorization = 'Bearer ' + $rotation.api_key
    'X-VectaVoIP-Secret' = $rotation.api_secret
}
$rotatedStatus = Invoke-RestMethod -Uri "$apiRoot/installations/status" -Headers $rotatedHeaders
$accountBody = @{
    name = 'Smoke Account'
    email = 'smoke-account@vectavoip.test'
    phone = '+15555550101'
    contact_methods = @('email', 'text', 'sip')
} | ConvertTo-Json -Compress
$account = Invoke-RestMethod -Uri "$apiRoot/accounts/create" -Method Post -Headers $rotatedHeaders -ContentType 'application/json' -Body $accountBody
$dids = Invoke-RestMethod -Uri "$apiRoot/dids/available?limit=5&country=US" -Headers $rotatedHeaders
if ([int]$dids.total -lt 1) {
    throw 'No available VectaVoIP DIDs returned; seed/sync DID inventory before production smoke verification.'
}

$selectedDid = $dids.dids[0].did
$purchaseBody = @{
    account_id = $account.account.id
    did = $selectedDid
    features = @('voice', 'sms')
    routing_destination = 'sip:smoke-account@vectavoip.test'
} | ConvertTo-Json -Compress
$purchase = Invoke-RestMethod -Uri "$apiRoot/dids/purchase" -Method Post -Headers $rotatedHeaders -ContentType 'application/json' -Body $purchaseBody

$smsBody = @{
    account_id = $account.account.id
    from = $selectedDid
    to = '+15555550101'
    body = 'VectaVoIP smoke SMS'
} | ConvertTo-Json -Compress
$sms = Invoke-RestMethod -Uri "$apiRoot/sms/send" -Method Post -Headers $rotatedHeaders -ContentType 'application/json' -Body $smsBody
$inboundBody = @{
    from = '+15555550101'
    to = $selectedDid
    body = 'VectaVoIP smoke inbound SMS'
    message_id = 'smoke-' + [guid]::NewGuid().ToString('N')
} | ConvertTo-Json -Compress
$inboundSms = Invoke-RestMethod -Uri "$apiRoot/sms/inbound" -Method Post -Headers $rotatedHeaders -ContentType 'application/json' -Body $inboundBody

[pscustomobject]@{
    success = $true
    installation_id = $registration.installation_id
    status = $status.status
    rate_rows = $rates.total_rows
    rotated_status = $rotatedStatus.status
    account_id = $account.account.id
    available_dids = $dids.total
    purchased_did = $purchase.purchase.did
    sms_message_id = $sms.message_id
    inbound_sms_id = $inboundSms.message_id
} | ConvertTo-Json -Depth 5
