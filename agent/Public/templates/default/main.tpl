{include file="header.tpl"}


{if ($popupwindow == 0)}
<div id="left-sidebar">
<div id="leftmenu-top">
<div id="leftmenu-down">
<div id="leftmenu-middle">

<ul id="nav">
	<li>
	<a href="PP_intro.php" target="_top"><img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/house.png"> <b>&nbsp;&nbsp;{"HOME"|gettext}</b> </a>
	</li>
	{if ($ACXMYACCOUNT > 0) }
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"><img id="img1"
	{if ($section == "0")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if}
 onmouseover="this.style.cursor='hand';" >&nbsp; <strong>{"MY ACCOUNT"|gettext}</strong></a></li></div>
	<div class="tohide"
	{if ($section =="4")}
	style="">
	{else}
	style="display:none;">
	{/if}
	<ul>
		<li><ul>
				<li><a href="agentinfo.php?section=4">{"Account information"|gettext}</a></li>
				<li><a href="A2B_entity_password.php?section=4">{"Password"|gettext}</a></li>
				<li><a href="A2B_entity_remittance_request.php?section=4">{"Historic Remittance"|gettext}</a></li>
		</ul></li>
	</ul>
	</div>
	{/if}

	{if ($ACXCUSTOMER > 0) }
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img1"
	{if ($section == "1")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"CUSTOMERS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="1")}
		style="">
	{else}
	style="display:none;">
	{/if}
	<ul>
		<li><ul>
				<li><a href="A2B_entity_card.php?section=1">{"List Customers"|gettext}</a></li>
				<li><a href="A2B_entity_callerid.php?section=1">{"Caller-ID"|gettext}</a></li>
				{if ($ACXCALLREPORT > 0) }
				<li><a href="card-history.php?section=1">{"Card History"|gettext}</a></li>
				{/if}
				{if ($ACXVOIPCONF > 0) }
				<li><a href="A2B_entity_friend.php?section=1">{"VOIP Config"|gettext}</a></li>
				{/if}
		</ul></li>
	</ul>
	</div>
	{/if}


	{if ($ACXSIGNUP > 0) }
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img8"
	{if ($section == "8")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"SIGNUP"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="8")}
		style="">
	{else}
	style="display:none;">
	{/if}
	<ul>
		<li><ul>
				<li><a href="A2B_entity_signup_agent.php?section=8">{"Signup Url List"|gettext}</a></li>
				<li><a href="A2B_signup_agent.php?section=8">{"Add New Signup Url"|gettext}</a></li>
		</ul></li>
	</ul>
	</div>
	{/if}


	{if ($ACXBILLING > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img2"
	{if ($section == "2")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"BILLING"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="2")}
		style="">
	{else}
	style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_moneysituation.php?section=2">{"Account balance"|gettext}</a></li>
				<li><a href="A2B_entity_logrefill_agent.php?section=2">{"Own Refills"|gettext}</a></li>
				<li><a href="A2B_entity_payment_agent.php?section=2">{"Own Payments"|gettext}</a></li>
				<li><a href="A2B_entity_logrefill.php?section=2">{"Customer's Refills"|gettext}</a></li>
				<li><a href="A2B_entity_payment.php?section=2">{"Customer's Payment"|gettext}</a></li>
				<li><a href="A2B_entity_paymentlog.php?section=2">{"Payment Log"|gettext}</a></li>
				<li><a href="A2B_entity_commission.php?section=2">{"Commission"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXRATECARD > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img3"
	{if ($section == "3")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"RATECARD"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="3")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_def_ratecard.php?section=3">{"Browse Rates"|gettext} </a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXCALLREPORT > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img6"
	{if ($section == "6")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"CALL REPORT"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="6")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
					<li><a href="call-log-customers.php?nodisplay=1&posted=1&section=6">{"CDR Report"|gettext}</a></li>
					<li><a href="call-last-month.php?section=6">{"Monthly Traffic"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXSUPPORT  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img7"
	{if ($section == "7")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"SUPPORT"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="7")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_ticket.php?section=7">{"Customer Tickets"|gettext}</a></li>
				<li><a href="A2B_support.php">{"View and Create Tickets"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


</ul>
</br>
<ul id="nav">
	<li>
	<a href="logout.php?logout=true" target="_top"><img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/logout.png"> <font color="#DD0000"><b>&nbsp;&nbsp;{"LOGOUT"|gettext}</b></font> </a>
	</li>
</ul>

</div>
</div>
</div>


<table width="100%" cellspacing="15">
<tr>
	<td>
		<a href="PP_intro.php?ui_language=english" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/gb.gif" border="0" title="English" alt="English"></a>
		<a href="PP_intro.php?ui_language=brazilian" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/br.gif" border="0" title="Brazilian" alt="Brazilian"></a>
		<a href="PP_intro.php?ui_language=romanian" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/ro.gif" border="0" title="Romanian"alt="Romanian"></a>
		<a href="PP_intro.php?ui_language=french" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/fr.gif" border="0" title="French" alt="French"></a>
		<a href="PP_intro.php?ui_language=spanish" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/es.gif" border="0" title="Spanish" alt="Spanish"></a>
		<a href="PP_intro.php?ui_language=greek" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/gr.gif" border="0" title="Greek" alt="Greek"></a>
		<a href="PP_intro.php?ui_language=italian" target="_parent"><img src="templates/{$SKIN_NAME}/images/flags/it.gif" border="0" title="Italian" alt="Italian"></a>
	</td>
</tr>
</table>

<div id="osx-modal-content">
	<div id="osx-modal-title">VectaVoIP Legal Notice</div>
	<div id="osx-modal-data">
		<h2>License Notice</h2>
		<p>This platform includes software components distributed under the GNU Affero General Public License v3.</p>
		<p>License details are available at <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank">https://www.gnu.org/licenses/agpl-3.0.html</a>.</p>
		<p>For VectaVoIP platform support, visit <a href="https://vectavoip.com/" target="_blank">https://vectavoip.com/</a>.</p>
		<p><button class="simplemodal-close">Close</button></p>
	</div>
</div>


</div>

<div id="main-content">
<br/>
{else}
<div>
{/if}

{if ($LCMODAL  > 0)}
<script type="text/javascript">
    loadLicenceModal();
</script>
{/if}

{$MAIN_MSG}

