<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of A2Billing (http://www.a2billing.net/)
 *
 * A2Billing, Commercial Open Source Telecom Billing platform,
 * powered by Star2billing S.L. <http://www.star2billing.com/>
 *
 * @copyright   Copyright (C) 2004-2015 - Star2billing S.L.
 * @author      Belaid Arezqui <areski@gmail.com>
 * @license     http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @package     A2Billing
 *
 * Software License Agreement (GNU Affero General Public License)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
**/

include '../lib/admin.defines.php';
include '../lib/admin.module.access.php';
include '../lib/Form/Class.FormHandler.inc.php';
include './form_data/FG_var_did.inc';
include '../lib/admin.smarty.php';

use A2BillingPlus\Admin\ModernAdminRuntime;
use A2BillingPlus\Module\Telephony\LegacyDidImportService;

if (!has_rights(ACX_DID)) {
    Header("HTTP/1.0 401 Unauthorized");
    Header("Location: PP_error.php?c=accessdenied");
    die();
}

$HD_Form->setDBHandler(DbConnect());
$HD_Form->init();

$projectionMessages = [];
$projectionErrors = [];
$projectionDiagnostics = [];
$projectionSamples = [];
if (isset($_REQUEST['project_provider_inventory']) && $_REQUEST['project_provider_inventory'] === '1') {
    try {
        $legacyPdo = legacyAdminPdo();
        $result = (new LegacyDidImportService($legacyPdo))->importAllCachedInventory();
        $projectionMessages[] = sprintf(
            'Projected provider inventory into core DID table. Imported %d, skipped %d.',
            (int) $result['imported'],
            (int) $result['skipped']
        );
    } catch (Throwable $exception) {
        $projectionErrors[] = 'Provider inventory projection failed: ' . $exception->getMessage();
    }
}

try {
    $projectRoot = realpath(__DIR__ . '/../..') ?: dirname(__DIR__, 2);
    $runtime = new ModernAdminRuntime($projectRoot);
    $modernDsn = $runtime->envString('A2BP_DB_DSN');
    if ($modernDsn === '') {
        $modernDsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $runtime->envString('A2BP_DB_HOST', 'db'),
            $runtime->envString('A2BP_DB_NAME', 'mya2billing')
        );
    }
    $legacyDsn = legacyAdminDsn();
    $projectionDiagnostics[] = 'Legacy DID page DB: ' . $legacyDsn;
    $projectionDiagnostics[] = 'Modern/provider DB: ' . $modernDsn;
    if ($legacyDsn !== $modernDsn) {
        $projectionErrors[] = 'Legacy admin pages and modern/provider modules are using different database targets.';
    }
} catch (Throwable $exception) {
    $projectionDiagnostics[] = 'Could not resolve DB diagnostics: ' . $exception->getMessage();
}

try {
    $legacyPdo = legacyAdminPdo();
    $projectionDiagnostics[] = 'Legacy cc_did count: ' . legacyCount($legacyPdo, 'cc_did');
    $projectionDiagnostics[] = 'Legacy provider cache count: ' . legacyCount($legacyPdo, 'cc_vectavoip_did_inventory');
    $projectionSamples = legacyProjectionSamples($legacyPdo);
} catch (Throwable $exception) {
    $projectionDiagnostics[] = 'Could not load projection samples: ' . $exception->getMessage();
}

if ($id != "" || !is_null($id)) {
    $HD_Form->FG_EDITION_CLAUSE = str_replace("%id", "$id", $HD_Form->FG_EDITION_CLAUSE);
}

if (!isset ($form_action))
    $form_action = "list"; //ask-add

if (!isset ($action))
    $action = $form_action;

$list = $HD_Form->perform_action($form_action);

// #### HEADER SECTION
$smarty->display('main.tpl');

// #### HELP SECTION
if ($form_action == 'list')
    echo $CC_help_list_did;
else
    echo $CC_help_edit_did;

if ($form_action == 'list') {
    echo '<div class="a2bp-panel" style="margin:12px 0;padding:12px;border:1px solid #d8dee6;background:#f8fafc;">';
    echo '<strong>Provider Inventory Projection</strong><br>';
    echo 'If provider numbers were synchronized but this core DID list is still empty, project cached provider inventory into the core DID table used by the legacy screens. ';
    echo '<a href="A2B_entity_did.php?section=' . urlencode((string)($_GET['section'] ?? '')) . '&project_provider_inventory=1">Project Provider Inventory</a>';
    if ($projectionMessages !== []) {
        foreach ($projectionMessages as $message) {
            echo '<div style="margin-top:8px;color:#0f5132;">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }
    }
    if ($projectionErrors !== []) {
        foreach ($projectionErrors as $message) {
            echo '<div style="margin-top:8px;color:#842029;">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }
    }
    if ($projectionDiagnostics !== []) {
        foreach ($projectionDiagnostics as $message) {
            echo '<div style="margin-top:8px;color:#555;">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }
    }
    if ($projectionSamples !== []) {
        echo '<div style="margin-top:10px;">';
        echo '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        echo '<thead><tr><th style="text-align:left;border-bottom:1px solid #d8dee6;">Source</th><th style="text-align:left;border-bottom:1px solid #d8dee6;">DID</th><th style="text-align:left;border-bottom:1px solid #d8dee6;">Status</th><th style="text-align:left;border-bottom:1px solid #d8dee6;">Description</th></tr></thead><tbody>';
        foreach ($projectionSamples as $sample) {
            echo '<tr>';
            echo '<td style="padding:4px 0;">' . htmlspecialchars($sample['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td style="padding:4px 0;">' . htmlspecialchars($sample['did'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td style="padding:4px 0;">' . htmlspecialchars($sample['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '<td style="padding:4px 0;">' . htmlspecialchars($sample['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }
    echo '</div>';
}

// #### TOP SECTION PAGE
$HD_Form->create_toppage($form_action);

$HD_Form->create_form($form_action, $list, $id = null);

// #### FOOTER SECTION
$smarty->display('footer.tpl');

function legacyAdminDsn(): string
{
    $host = defined('HOST') ? (string) HOST : 'localhost';
    $port = defined('PORT') ? trim((string) PORT) : '';
    $dbname = defined('DBNAME') ? (string) DBNAME : '';
    $hostPart = $host;
    if ($port !== '' && strpos($host, ':') === false) {
        $hostPart .= ';port=' . $port;
    }

    return 'mysql:host=' . $hostPart . ';dbname=' . $dbname . ';charset=utf8mb4';
}

function legacyAdminPdo(): PDO
{
    $user = defined('USER') ? (string) USER : '';
    $pass = defined('PASS') ? (string) PASS : '';

    return new PDO(legacyAdminDsn(), $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function legacyCount(PDO $pdo, string $table): int
{
    $statement = $pdo->query('SELECT COUNT(*) FROM `' . preg_replace('/[^A-Za-z0-9_]/', '', $table) . '`');
    return $statement ? (int) $statement->fetchColumn() : 0;
}

/**
 * @return list<array{source:string,did:string,status:string,description:string}>
 */
function legacyProjectionSamples(PDO $pdo): array
{
    $samples = [];

    if (legacyTableExists($pdo, 'cc_vectavoip_did_inventory')) {
        $statement = $pdo->query(
            'SELECT did, status, provider_code, provider_trunk_name
             FROM cc_vectavoip_did_inventory
             ORDER BY id DESC
             LIMIT 3'
        );
        $rows = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $samples[] = [
                'source' => 'provider_cache',
                'did' => (string) ($row['did'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'description' => trim((string) ($row['provider_code'] ?? '') . ' ' . (string) ($row['provider_trunk_name'] ?? '')),
            ];
        }
    }

    if (legacyTableExists($pdo, 'cc_did')) {
        $statement = $pdo->query(
            'SELECT did, activated, description
             FROM cc_did
             ORDER BY id DESC
             LIMIT 3'
        );
        $rows = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $samples[] = [
                'source' => 'core_did',
                'did' => (string) ($row['did'] ?? ''),
                'status' => ((string) ($row['activated'] ?? '')) === '1' ? 'active' : 'inactive',
                'description' => (string) ($row['description'] ?? ''),
            ];
        }
    }

    return $samples;
}

function legacyTableExists(PDO $pdo, string $table): bool
{
    $safe = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $statement = $pdo->prepare('SHOW TABLES LIKE ?');
    $statement->execute([$safe]);
    return $statement->fetchColumn() !== false;
}
