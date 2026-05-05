param(
    [switch]$SkipPhp84
)

$ErrorActionPreference = 'Stop'
$HttpPort = if ($env:A2BP_HTTP_PORT) { $env:A2BP_HTTP_PORT } else { '8080' }
$BaseUrl = "http://localhost:$HttpPort"

function Invoke-Step {
    param(
        [string]$Name,
        [scriptblock]$Command
    )

    Write-Host "==> $Name"
    & $Command
    Write-Host ""
}

Invoke-Step 'PHP syntax: core changed entrypoints' {
    docker compose exec -T app php -l install.php
    docker compose exec -T app php -l api/v1/providers.php
    docker compose exec -T app php -l api/v1/provider-webhooks.php
    docker compose exec -T app php -l api/vectavoip/v1/bootstrap.php
    docker compose exec -T app php -l api/vectavoip/v1/installations/register.php
    docker compose exec -T app php -l api/vectavoip/v1/installations/status.php
    docker compose exec -T app php -l api/vectavoip/v1/credentials/rotate.php
    docker compose exec -T app php -l api/vectavoip/v1/rates/preview.php
    docker compose exec -T app php -l admin/Public/A2B_provider_setup.php
    docker compose exec -T app php -l admin/Public/A2B_payment_workspace.php
    docker compose exec -T app php -l admin/Public/A2B_ui_theme_manager.php
    docker compose exec -T app php -l admin/Public/A2B_customer_workspace.php
    docker compose exec -T app php -l admin/Public/A2B_customer_detail.php
    docker compose exec -T app php -l bin/migrate-a2billing.php
    docker compose exec -T app php -l bin/apply-install-migrations.php
}

Invoke-Step 'PHPUnit on PHP 8.2' {
    docker compose exec -T app vendor/bin/phpunit
}

if (-not $SkipPhp84) {
    Invoke-Step 'PHPUnit on PHP 8.4' {
        docker compose exec -T app84 vendor/bin/phpunit
    }
}

Invoke-Step 'PHP 8 static scan' {
    powershell -ExecutionPolicy Bypass -File bin/php8-static-scan.ps1
}

Invoke-Step 'Provider API status smoke' {
    $body = @{ action = 'provider_status'; provider = 'vectavoip' } | ConvertTo-Json -Compress
    Invoke-RestMethod -Uri "$BaseUrl/api/v1/providers.php" -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 5
}

Invoke-Step 'Provider rate preview smoke' {
    $body = @{
        action = 'preview_rates'
        provider = 'vectavoip'
        base_url = 'http://localhost/api/sandbox'
        api_key = 'sandbox_key'
        rate_deck = 'retail'
        currency = 'USD'
    } | ConvertTo-Json -Compress
    Invoke-RestMethod -Uri "$BaseUrl/api/v1/providers.php" -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 6
}

Invoke-Step 'Provider dry-run import smoke' {
    $body = @{
        action = 'import_preview_rates'
        provider = 'vectavoip'
        base_url = 'http://localhost/api/sandbox'
        api_key = 'sandbox_key'
        target_ratecard_id = '5'
        rate_deck = 'retail'
        currency = 'USD'
        dry_run = '1'
        update_existing = '0'
    } | ConvertTo-Json -Compress
    Invoke-RestMethod -Uri "$BaseUrl/api/v1/providers.php" -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 5
}

Invoke-Step 'VectaVoIP production-compatible registration smoke' {
    $installKey = 'a2bp_verify_' + [guid]::NewGuid().ToString('N')
    $body = @{
        install_key = $installKey
        company_name = 'Verify Co'
        company_domain = 'verify.example'
        contact_name = 'Verify Admin'
        contact_email = 'verify@example.test'
        contact_phone = '+15551234567'
        details = 'Build verification registration'
        app_name = 'A2BillingPlus'
        app_version = '0.1.0-alpha'
    } | ConvertTo-Json -Compress

    $registration = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/installations/register.php" -Method Post -ContentType 'application/json' -Body $body
    $headers = @{
        Authorization = 'Bearer ' + $registration.api_key
        'X-VectaVoIP-Secret' = $registration.api_secret
    }

    Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/installations/status.php" -Headers $headers | ConvertTo-Json -Depth 5
    Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/rates/preview.php?rate_deck=retail&currency=USD" -Headers $headers | ConvertTo-Json -Depth 6
    $rotation = Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/credentials/rotate.php" -Method Post -Headers $headers
    $rotatedHeaders = @{
        Authorization = 'Bearer ' + $rotation.api_key
        'X-VectaVoIP-Secret' = $rotation.api_secret
    }
    Invoke-RestMethod -Uri "$BaseUrl/api/vectavoip/v1/installations/status.php" -Headers $rotatedHeaders | ConvertTo-Json -Depth 5
}

Invoke-Step 'A2Billing migration dry-run smoke' {
    docker compose exec -T app php bin/migrate-a2billing.php --scope=all --limit=10
}

Write-Host 'Build verification completed.'
