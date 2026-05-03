<?php

declare(strict_types=1);

function a2bp_legacy_direct_card_flow_enabled(): bool
{
    $value = getenv('A2BP_FEATURE_LEGACY_DIRECT_CARD_FLOW');
    return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function a2bp_redacted_payment_post(): array
{
    $blocked = ['card_number', 'cc_number', 'cvv', 'cvc', 'cvv2', 'security_code'];
    $redacted = [];
    foreach ($_POST as $key => $value) {
        $redacted[$key] = in_array(strtolower((string)$key), $blocked, true) ? '[redacted]' : $value;
    }

    return $redacted;
}
