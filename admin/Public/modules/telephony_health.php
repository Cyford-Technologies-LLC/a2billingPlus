<?php

declare(strict_types=1);

include_once dirname(__DIR__) . '/../lib/admin.defines.php';
include_once dirname(__DIR__) . '/../lib/admin.module.access.php';

use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Telephony\AsteriskConfigCheckService;

if (!has_rights(ACX_DASHBOARD)) {
    Header("HTTP/1.0 401 Unauthorized");
    Header("Location: PP_error.php?c=accessdenied");
    die();
}

$projectRoot = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
$runtime = new ModernAdminRuntime($projectRoot);
$asterisk = ['success' => false, 'checks' => []];
$errorMessage = '';

try {
    $pdo = $runtime->pdo();
    $service = new AsteriskConfigCheckService($pdo);
    $asterisk = $service->check([
        'version' => $runtime->envString('A2BP_ASTERISK_VERSION', '20.0.0'),
        'ami_user' => $runtime->envString('ASTERISK_AMI_USER'),
        'ami_password' => $runtime->envString('ASTERISK_AMI_PASSWORD'),
        'ari_user' => $runtime->envString('ASTERISK_ARI_USER'),
        'ari_password' => $runtime->envString('ASTERISK_ARI_PASSWORD'),
        'channel_driver' => $runtime->envString('A2BP_ASTERISK_CHANNEL_DRIVER', 'pjsip'),
        'realtime_enabled' => $runtime->envString('A2BP_ASTERISK_REALTIME', 'yes'),
        'ami_host' => $runtime->envString('A2BP_ASTERISK_AMI_HOST', 'asterisk'),
        'ami_port' => $runtime->envString('A2BP_ASTERISK_AMI_PORT', '5038'),
        'probe_runtime' => 'yes',
    ]);
} catch (Throwable $exception) {
    $errorMessage = $exception->getMessage();
}

$failing = array_values(array_filter(
    $asterisk['checks'],
    static fn (array $check): bool => ($check['success'] ?? false) !== true
));
$passing = array_values(array_filter(
    $asterisk['checks'],
    static fn (array $check): bool => ($check['success'] ?? false) === true
));
?>

<?php echo gettext("Overall");?>&nbsp;:&nbsp;
<font style="color:<?php echo $asterisk['success'] ? '#2d8a34' : '#c63d3d'; ?>;font-weight:bold;">
    <?php echo $asterisk['success'] ? gettext("Ready") : gettext("Review"); ?>
</font>
<br/>
<?php if ($errorMessage !== ''): ?>
<?php echo gettext("Probe Error");?>&nbsp;:&nbsp;<?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?><br/>
<?php else: ?>
<?php echo gettext("Passing Checks");?>&nbsp;:&nbsp;<?php echo count($passing); ?><br/>
<?php echo gettext("Failing Checks");?>&nbsp;:&nbsp;<?php echo count($failing); ?><br/>
<?php foreach (array_slice($failing, 0, 4) as $check): ?>
<span style="color:#c63d3d;"><?php echo htmlspecialchars((string)($check['name'] ?? ''), ENT_QUOTES); ?></span>
&nbsp;:&nbsp;<?php echo htmlspecialchars((string)($check['message'] ?? ''), ENT_QUOTES); ?><br/>
<?php endforeach; ?>
<?php if (!$failing): ?>
<?php foreach (array_slice($passing, 0, 3) as $check): ?>
<span style="color:#2d8a34;"><?php echo htmlspecialchars((string)($check['name'] ?? ''), ENT_QUOTES); ?></span>
&nbsp;:&nbsp;<?php echo htmlspecialchars((string)($check['message'] ?? ''), ENT_QUOTES); ?><br/>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
<br/>
<a href="A2B_telephony_workspace.php?section=7"><?php echo gettext("Open Telephony Workspace"); ?></a>
