<?php

declare(strict_types=1);

function a2bp_legacy_admin_config_editors_enabled(): bool
{
    $value = getenv('A2BP_FEATURE_LEGACY_CONFIG_EDITORS');
    return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function a2bp_is_production_environment(): bool
{
    foreach (['APP_ENV', 'A2BP_APP_ENV'] as $name) {
        $value = getenv($name);
        if (is_string($value) && in_array(strtolower($value), ['production', 'prod'], true)) {
            return true;
        }
    }

    return false;
}

function a2bp_assert_legacy_admin_config_editor_allowed(): void
{
    if (!a2bp_is_production_environment() || a2bp_legacy_admin_config_editors_enabled()) {
        return;
    }

    Header('HTTP/1.0 403 Forbidden');
    echo 'Legacy configuration editors are disabled in production. Use the safe admin settings API.';
    exit();
}
