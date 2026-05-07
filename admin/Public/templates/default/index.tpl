<HTML>
<HEAD>
	<link rel="shortcut icon" href="images/ico/a2billing-icon-32x32.ico">
	<title>..:: {$CCMAINTITLE} ::..</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		{if ($CSS_NAME!="" && $CSS_NAME!="default")}
			   <link href="templates/default/css/{$CSS_NAME}.css" rel="stylesheet" type="text/css">
		{else}
			   <link href="templates/default/css/main.css" rel="stylesheet" type="text/css">
			   <link href="templates/default/css/menu.css" rel="stylesheet" type="text/css">
			   <link href="templates/default/css/style-def.css" rel="stylesheet" type="text/css">
		{/if}
        <script type="text/javascript" src="./javascript/jquery/jquery-1.2.6.min.js"></script>
</HEAD>

<BODY leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">


{literal}
<script LANGUAGE="JavaScript">
<!--
	function test()
	{
		if(document.form.pr_login.value=="" || document.form.pr_password.value=="")
		{
			alert("You must enter an user and a password!");
			return false;
		}
		else
		{
			return true;
		}
	}
-->
</script>

{/literal}

	<form name="form" method="POST" action="PP_intro.php" onsubmit="return test()">
	<input type="hidden" name="done" value="submit_log">
	<input type="hidden" name="return_to" value="{$return_to}">


	<div id="login-wrapper" class="login-border-up">
	<div class="login-border-down">
	<div class="login-border-center">
	<center>
	<table border="0" cellpadding="3" cellspacing="12">
	<tr>
		<td colspan="2" align="center">
			<img src="templates/{$SKIN_NAME}/images/a2billingplus-logo.svg" alt="A2BillingPlus" style="width:260px;height:auto;border:0;">
		</td>
	</tr>
	<tr>
		<td colspan="2" align="center" style="font-family:Arial, Helvetica, Sans-Serif;color:#333333;font-size:12px;line-height:18px;">
			<div style="font-size:18px;font-weight:bold;color:#0f6b7d;">VectaVoIP</div>
			<div style="font-size:14px;font-weight:bold;color:#f05a28;">Billing Platform</div>
			<div style="margin-top:8px;">This platform includes AGPL-licensed software components.</div>
			<div><a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank">GNU Affero General Public License v3</a></div>
			<div style="margin-top:8px;">For VoIP termination, please visit <a href="https://vectavoip.com/" target="_blank">https://vectavoip.com/</a></div>
		</td>
	</tr>
	<tr>
		<td class="login-title" colspan="2">
			 {$AUTHENTICATION_LABEL}
		</td>
	</tr>
	<tr>
		<td ><img src="templates/{$SKIN_NAME}/images/kicons/lock_bg.png"></td>
		<td align="center" style="padding-right: 10px">
			<table width="90%">
			<tr align="center">
				<td align="left"><font size="2" face="Arial, Helvetica, Sans-Serif"><b>{$USER_LABEL}:</b></font></td>
				<td><input class="form_input_text" type="text" name="pr_login" size="15"></td>
			</tr>
			<tr align="center">
				<td align="left"><font face="Arial, Helvetica, Sans-Serif" size="2"><b>{$PASSWORD_LABEL}:</b></font></td>
				<td><input class="form_input_text" type="password" name="pr_password" size="15"></td>
			</tr>
            <tr >
                <td colspan="2"> &nbsp;</td>
            </tr>
			<tr align="right" >
            <td>
                <select name="ui_language"  id="ui_language" class="icon-menu form_input_select">
                    <option style="background-image:url(templates/{$SKIN_NAME}/images/flags/gb.gif);" value="english" {if $LANGUAGE == "english"}selected{/if} >English</option>
                    <option style="background-image:url(templates/{$SKIN_NAME}/images/flags/br.gif);" value="brazilian" {if $LANGUAGE == "brazilian"}selected{/if}>Brazilian</option>
                    <option style="background-image:url(templates/{$SKIN_NAME}/images/flags/ro.gif);" value="romanian" {if $LANGUAGE == "romanian"}selected{/if} >Romanian</option>
                    <option style="background-image:url(templates/{$SKIN_NAME}/images/flags/fr.gif);" value="french" {if $LANGUAGE == "french"}selected{/if} >French</option>
                    <option style="background-image:url(templates/{$SKIN_NAME}/images/flags/gr.gif);" value="greek" {if $LANGUAGE == "greek"}selected{/if} >Greek</option>
                </select>
            </td>
			<td><input type="submit" name="submit" value="{$LOGIN_LABEL}" class="form_input_button"></td>
			</tr>

			</table>
		</td>
	</tr>
  	</table>
  	</center>
  	</div>
  	</div>

    <div style="color:#BC2222;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;padding-left:10px;" >
  	{if ($error == 1)}
			{$ERROR_AUTH_REFUSED}
    {elseif ($error==2)}
			{$ERROR_SESSION_EXPIRED}
    {elseif ($error==3)}
			{$ERROR_INACTIVE_ACCOUNT}
    {elseif ($error==4)}
			{$ERROR_BLOCKED_ACCOUNT}
    {/if}
    </div>

    <div id="footer_index"><div style=" border: solid 1px #F4F4F4; text-align:center;">{$COPYRIGHT}</div></div>

  	</div>
	</form>

{literal}
<script LANGUAGE="JavaScript">
	document.form.pr_login.focus();
        $("#ui_language").change(function () {
          self.location.href= "index.php?ui_language="+$("#ui_language option:selected").val();
        });
</script>
{/literal}
