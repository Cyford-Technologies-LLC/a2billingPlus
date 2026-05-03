param(
    [switch]$SkipPhp84
)

$ErrorActionPreference = 'Stop'

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
    docker compose exec -T app php -l admin/Public/A2B_provider_setup.php
    docker compose exec -T app php -l bin/migrate-a2billing.php
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
    powershell -ExecutionPolicy Bypass -File development/tools/php8-static-scan.ps1
}

Invoke-Step 'Provider API status smoke' {
    $body = @{ action = 'provider_status'; provider = 'vectavoip' } | ConvertTo-Json -Compress
    Invoke-RestMethod -Uri http://localhost:8080/api/v1/providers.php -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 5
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
    Invoke-RestMethod -Uri http://localhost:8080/api/v1/providers.php -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 6
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
    Invoke-RestMethod -Uri http://localhost:8080/api/v1/providers.php -Method Post -ContentType 'application/json' -Body $body | ConvertTo-Json -Depth 5
}

Invoke-Step 'A2Billing migration dry-run smoke' {
    docker compose exec -T app php bin/migrate-a2billing.php --scope=all --limit=10
}

Write-Host 'Build verification completed.'
