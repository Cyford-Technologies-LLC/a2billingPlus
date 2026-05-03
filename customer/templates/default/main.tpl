{include file="header.tpl"}
{if ($popupwindow == 0)}
{if ($EXPORT == 0)}
<div id="left-sidebar">
<div id="leftmenu-top">
<div id="leftmenu-down">
<div id="leftmenu-middle">


<ul id="nav">

	<div class="toggle_menu"><li><a href="userinfo.php"><strong>{"ACCOUNT INFO"|gettext}</strong></a></li></div>

	{if $ACXVOICEMAIL>0 }
	<div class="toggle_menu"><li><a href="A2B_entity_voicemail.php"><strong>{"VOICEMAIL"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXSIP_IAX>0 }
	<div class="toggle_menu"><li><a href="A2B_entity_sipiax_info.php"><strong>{"SIP/IAX INFO"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXCALL_HISTORY >0 }
	<div class="toggle_menu"><li><a href="call-history.php"><strong>{"CALL HISTORY"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXPAYMENT_HISTORY >0 }
	<div class="toggle_menu"><li><a href="payment-history.php"><strong>{"PAYMENT HISTORY"|gettext}</strong></a></li></div>
	{/if}


	{if $ACXVOUCHER >0 }
	<div class="toggle_menu"><li><a href="A2B_entity_voucher.php?form_action=list"><strong>{"VOUCHERS"|gettext}</strong></a></li></div>
	{/if}


	{if $ACXINVOICES >0 }
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img5"
	{if ($section == "5")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"INVOICES"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="5")}
		style="">
	{else}
	style="display:none;">
	{/if}
	<ul>
		<li>
			<ul>
				<li><a href="A2B_entity_receipt.php?section=5"><strong>{"View Receipts"|gettext}</strong></a></li>
				<li><a href="A2B_entity_invoice.php?section=5"><strong>{"View Invoices"|gettext}</strong></a></li>
				<li><a href="A2B_billing_preview.php?section=5"><strong>{"Preview Next Billing"|gettext}</strong></a></li>
			</ul>
		</li>
	</ul>
	</div>
	{/if}


	{if $ACXDID >0 }
	<div class="toggle_menu"><li><a href="A2B_entity_did.php?form_action=list"><strong>{"DID"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXSPEED_DIAL >0 }
	<div class="toggle_menu"><li><a href="A2B_entity_speeddial.php?atmenu=speeddial&stitle=Speed+Dial"><strong>{"SPEED DIAL"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXRATECARD >0 }
	<div class="toggle_menu"><li><a href="A2B_entity_ratecard.php?form_action=list"><strong>{"RATECARD"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXSIMULATOR >0 }
	<div class="toggle_menu"><li><a href="simulator.php"><strong>{"SIMULATOR"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXCALL_BACK >0 }
	<div class="toggle_menu"><li><a href="callback.php"><strong>{"CALLBACK"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXCALLER_ID >0 }
	<div class="toggle_menu"><li><a href="A2B_entity_callerid.php?atmenu=callerid&stitle=CallerID"><strong>{"ADD CALLER ID"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXPASSWORD>0 }
	<div class="toggle_menu"><li><a href="A2B_entity_password.php?atmenu=password&form_action=ask-edit&stitle=Password"><strong>{"PASSWORD"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXSUPPORT >0 }
	<div class="toggle_menu"><li><a href="A2B_support.php"><strong>{"SUPPORT"|gettext}</strong></a></li></div>
	{/if}

	{if $ACXNOTIFICATION >0 }
	<div class="toggle_menu"><li><a href="A2B_notification.php?form_action=ask-edit"><strong>{"NOTIFICATION"|gettext}</strong></a></li></div>
	{/if}


</ul>

<br/>
<ul id="nav"><li>
	<ul><li><a href="logout.php?logout=true" target="_top"><img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/logout.png"> <font color="#DD0000"><STRONG>&nbsp;&nbsp;{"LOGOUT"|gettext}</STRONG></font> </a></li></ul>
</li></ul>

</div>
</div>
</div>


<table width="90%" cellspacing="15">
<tr>
   <td>
		<a href="{$PAGE_SELF}?ui_language=english"><img src="templates/{$SKIN_NAME}/images/flags/gb.gif" border="0" title="English" alt="English"></a>
		<a href="{$PAGE_SELF}?ui_language=spanish"><img src="templates/{$SKIN_NAME}/images/flags/es.gif" border="0" title="Spanish" alt="Spanish"></a>
		<a href="{$PAGE_SELF}?ui_language=french"><img src="templates/{$SKIN_NAME}/images/flags/fr.gif" border="0" title="French" alt="French"></a>
		<a href="{$PAGE_SELF}?ui_language=german"><img src="templates/{$SKIN_NAME}/images/flags/de.gif" border="0" title="German" alt="German"></a>
		<a href="{$PAGE_SELF}?ui_language=portuguese"><img src="templates/{$SKIN_NAME}/images/flags/pt.gif" border="0" title="Portuguese" alt="Portuguese"></a>
		<a href="{$PAGE_SELF}?ui_language=brazilian"><img src="templates/{$SKIN_NAME}/images/flags/br.gif" border="0" title="Brazilian" alt="Brazilian"></a>
		<a href="{$PAGE_SELF}?ui_language=italian"><img src="templates/{$SKIN_NAME}/images/flags/it.gif" border="0" title="Italian" alt="Italian"></a>
		<a href="{$PAGE_SELF}?ui_language=romanian"><img src="templates/{$SKIN_NAME}/images/flags/ro.gif" border="0" title="Romanian"alt="Romanian"></a>
		<a href="{$PAGE_SELF}?ui_language=chinese"><img src="templates/{$SKIN_NAME}/images/flags/cn.gif" border="0" title="Chinese" alt="Chinese"></a>
		<a href="{$PAGE_SELF}?ui_language=polish"><img src="templates/{$SKIN_NAME}/images/flags/pl.gif" border="0" title="Polish" alt="Polish"></a>
		<a href="{$PAGE_SELF}?ui_language=russian"><img src="templates/{$SKIN_NAME}/images/flags/ru.gif" border="0" title="russian" alt="russian"></a>
		<a href="{$PAGE_SELF}?ui_language=turkish"><img src="templates/{$SKIN_NAME}/images/flags/tr.gif" border="0" title="Turkish" alt="Turkish"></a>
		<a href="{$PAGE_SELF}?ui_language=urdu"><img src="templates/{$SKIN_NAME}/images/flags/pk.gif" border="0" title="Urdu" alt="Urdu"></a>
		<a href="{$PAGE_SELF}?ui_language=ukrainian"><img src="templates/{$SKIN_NAME}/images/flags/ua.gif" border="0" title="Ukrainian" alt="Ukrainian"></a>
		<a href="{$PAGE_SELF}?ui_language=farsi"><img src="templates/{$SKIN_NAME}/images/flags/ir.gif" border="0" title="Farsi" alt="Farsi"></a>
		<a href="{$PAGE_SELF}?ui_language=greek"><img src="templates/{$SKIN_NAME}/images/flags/gr.gif" border="0" title="Greek" alt="Greek"></a>
		<a href="{$PAGE_SELF}?ui_language=indonesian"><img src="templates/{$SKIN_NAME}/images/flags/id.gif" border="0" title="Indonesian" alt="Indonesian"></a>
   </td>
</tr>
</table>
</div>

<div id="main-content">
<br/>
{else}
	<div>
{/if}

{else}
	<div>
{/if}

{$MAIN_MSG}
