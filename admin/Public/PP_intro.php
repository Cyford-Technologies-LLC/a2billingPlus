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

include_once '../lib/admin.defines.php';
include_once '../lib/admin.module.access.php';
include_once '../lib/admin.smarty.php';

if (!$ACXACCESS) {
    Header ("HTTP/1.0 401 Unauthorized");
    Header ("Location: PP_error.php?c=accessdenied");
    die();
}

$smarty->display('main.tpl');

?>
<br/><br/>
<center>
<table align="center" width="90%" bgcolor="white" cellpadding="15" cellspacing="15" style="border: solid 1px">
    <tr>
        <td width="340" align="center" style="font-family: Arial, Helvetica, sans-serif;">
            <h2 style="margin: 0; color: #2b5d87;">VectaVoIP</h2>
            <p style="margin: 8px 0 0 0;">Billing Platform</p>
        </td>
        <?php if (SHOW_DONATION) { ?>
        <td align="left">
        For platform information and support, please visit <a href="https://vectavoip.com/" target="_blank">https://vectavoip.com/</a><br><br>
        </td>
        <?php } ?>
    </tr>

    <tr>
        <td colspan="2">
        <center>
            <b><i>This platform includes AGPL-licensed software components.</i></b>
            <br><a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank">GNU Affero General Public License v3</a>
            </center>

        <div class="scroll">
<pre>
<?php echo (file_get_contents("../lib/COPYING")); ?>
</pre>
</div>

        </td>
    </tr>

</table>

<br>

<table align=center width="90%" bgcolor="white" cellpadding="5" cellspacing="5" style="border: solid 1px">
    <tr>
        <td align="center">
            <?php if (SHOW_DONATION) { ?>
            <center>
                <?php echo gettext("For VectaVoIP platform support, please contact VectaVoIP.");?>
                <p><a href="https://vectavoip.com/" target="_blank">https://vectavoip.com/</a></p>
            </center>
            <br>
            <?php } ?>
        </td>
    </tr>
</table>

</center>

<?php

$smarty->display('footer.tpl');
