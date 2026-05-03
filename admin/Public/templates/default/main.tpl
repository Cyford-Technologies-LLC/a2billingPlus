{include file="header.tpl"}


{if ($popupwindow == 0)}
	<div id="top_menu">
		<ul id="menu_horizontal">
			<li class="topmenu-left-button" style="border:none;">
				<div style="width:100%;height:100%;text-align:center;" >
					<a href="PP_intro.php">
							<strong> {"HOME"|gettext}</strong>&nbsp;
						<img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/house.png">
					</a>
				</div>
			</li>
			{if ($ACXDASHBOARD > 0) }
			<li class="topmenu-left-button" >
				<div style="width:100%;height:100%;text-align:center;" >
					<a href="dashboard.php" >
						<strong> {"DASHBOARD"|gettext}</strong>&nbsp;
						<img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/chart_bar.png">
					</a>
				</div>
			</li>
			{/if}
			<li class="topmenu-left-button">
				<div style="width:100%;height:100%;text-align:center;" >
					 <a href="A2B_notification.php" >
						<strong > {"NOTIFICATION"|gettext}</strong>&nbsp;
					<img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/email.png">
					{if ($NEW_NOTIFICATION > 0) }
						<strong style="font-size:8px; color:red;"> NEW</strong>
					{else}
						<strong style="font-size:8px;">&nbsp;</strong>
					{/if}
					  </a>
				</div>
			</li>
			<li class="topmenu-right-button" style="border-right:none;">
				<div style="width:90%;height:100%;text-align:center;" >
					<a href="logout.php?logout=true" target="_top"><font color="#EC3F41"><b>&nbsp;&nbsp;{"LOGOUT"|gettext}</b></font>
					<img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/logout.png"> </a>
				</div>
			</li>
		</ul>

	</div>

{/if}

{if ($popupwindow == 0)}
<div id="left-sidebar">
<div id="leftmenu-top">
<div id="leftmenu-down">
<div id="leftmenu-middle">

<ul id="nav">

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
				<li><a href="A2B_entity_card.php?section=1">{"Add :: Search"|gettext}</a></li>
                <li><a href="CC_card_import.php?section=1">{"Import"|gettext}</a></li>
				<li><a href="A2B_entity_friend.php?atmenu=sip&section=1">{"VoIP Settings"|gettext}</a></li>
				<li><a href="A2B_entity_callerid.php?atmenu=callerid&section=1">{"Caller-ID"|gettext}</a></li>
				<li><a href="A2B_notifications.php?section=1">{"Credit Notification"|gettext}</a></li>
				<li><a href="A2B_entity_card_group.php?section=1">{"Groups"|gettext}</a></li>
				<li><a href="A2B_entity_card_seria.php?section=1">{"Card series"|gettext}</a></li>
				<li><a href="A2B_entity_speeddial.php?atmenu=speeddial&section=1">{"Speed Dial"|gettext}</a></li>
				<li><a href="card-history.php?atmenu=cardhistory&section=1">{"History"|gettext}</a></li>
				<li><a href="A2B_entity_statuslog.php?atmenu=statuslog&section=1">{"Status"|gettext}</a></li>
		</ul></li>
	</ul>
	</div>
	{/if}

	{if ($ACXADMINISTRATOR  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img2"
	{if ($section == "2")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"AGENTS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="2")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_agent.php?atmenu=user&section=2">{"Add :: Search"|gettext}</a></li>
				<li><a href="A2B_entity_signup_agent.php?atmenu=user&section=2">{"Signup URLs"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXADMINISTRATOR  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img3"
	{if ($section == "3")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"ADMINS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="3")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_user.php?atmenu=user&groupID=0&section=3">{"Add :: Search"|gettext}</a></li>
				<li><a href="A2B_entity_user.php?atmenu=user&groupID=1&section=3">{"Access Control"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXSUPPORT > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img4"
	{if ($section == "4")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"SUPPORT"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="4")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="CC_ticket.php?section=4">{"Customer Tickets"|gettext}</a></li>
				<li><a href="A2B_ticket_agent.php?section=4">{"Agent Tickets"|gettext}</a></li>
				<li><a href="CC_support_component.php?section=4">{"Ticket Components"|gettext}</a></li>
				<li><a href="CC_support.php?section=4">{"Support Boxes"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXCALLREPORT > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img5"
	{if ($section == "5")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"CALL REPORTS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="5")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
					<li><a href="call-log-customers.php?nodisplay=1&posted=1&section=5">{"CDRs"|gettext}</a></li>
					<li><a href="call-count-reporting.php?nodisplay=1&posted=1&section=5">{"Call Count"|gettext}</a></li>
					<li><a href="A2B_trunk_report.php?section=5">{"Trunk"|gettext}</a></li>
					<li><a href="call-dnid.php?nodisplay=1&posted=1&section=5">{"DNID"|gettext}</a></li>
					<li><a href="call-pnl-report.php?section=5">{"PNL"|gettext}</a></li>
					<li><a href="call-comp.php?section=5">{"Compare Calls"|gettext}</a></li>
					<li><a href="call-daily-load.php?section=5">{"Daily Traffic"|gettext}</a></li>
					<li><a href="call-last-month.php?section=5">{"Monthly Traffic"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXRATECARD > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img6"
	{if ($section == "6")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"RATES"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="6")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_tariffgroup.php?atmenu=tariffgroup&section=6">{"Call Plan"|gettext}</a></li>
				<li><a href="A2B_entity_tariffplan.php?atmenu=tariffplan&section=6">{"RateCards"|gettext}</a></li>
				<li><a href="CC_ratecard_import.php?atmenu=ratecard&section=6">»» {"Import"|gettext}</a></li>
				<li><a href="CC_ratecard_merging.php?atmenu=ratecard&section=6">»» {"Merge"|gettext}</a></li>
				<li><a href="CC_entity_sim_ratecard.php?atmenu=ratecard&section=6">»» {"Simulator"|gettext}</a></li>
				<li><a href="A2B_entity_def_ratecard.php?atmenu=ratecard&section=6">{"Rates"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXTRUNK > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img7"
	{if ($section == "7")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"PROVIDERS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="7")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_provider.php?section=7">{"Providers"|gettext}</a></li>
				<li><a href="A2B_entity_trunk.php?section=7">{"Trunks"|gettext}</a></li>
				<li><a href="A2B_entity_prefix.php?section=7">{"Prefixes"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXDID > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img8"
	{if ($section == "8")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"INBOUND DID"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="8")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_did.php?section=8">{"Add :: Search"|gettext}</a></li>
				<li><a href="A2B_entity_didgroup.php?section=8">{"Groups"|gettext}</a>
				<li><a href="A2B_entity_did_destination.php?section=8">{"Destination"|gettext}</a></li>
				<li><a href="A2B_entity_did_import.php?section=8">{"Import [CSV]"|gettext}</a></li>
				<li><a href="A2B_entity_didx.php?section=8">{"Import [DIDX]"|gettext}</a></li>
				<li><a href="A2B_entity_did_use.php?atmenu=did_use&section=8">{"Usage"|gettext}</a></li>
				<li><a href="A2B_entity_did_billing.php?atmenu=did_billing&section=8">{"Billing"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXOUTBOUNDCID > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img9"
	{if ($section == "9")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"OUTBOUND CID"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="9")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_outbound_cid.php?atmenu=cid&section=9">{"Add"|gettext}</a></li>
				<li><a href="A2B_entity_outbound_cidgroup.php?atmenu=cidgroup&section=9">{"Groups"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}



	{if ($ACXBILLING > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img10"
	{if ($section == "10")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"BILLING"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="10")}
		style="">
	{else}
	style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_voucher.php?section=10">{"Vouchers"|gettext}</a></li>
				<li><a href="A2B_entity_moneysituation.php?atmenu=moneysituation&section=10">{"Customers Balance"|gettext}</a></li>
                <li><a href="A2B_entity_transactions.php?atmenu=payment&section=10">»» {"Transactions"|gettext}</a></li>
				<li><a href="A2B_entity_billing_customer.php?atmenu=payment&section=10">»» {"Billings"|gettext}</a></li>
				<li><a href="A2B_entity_logrefill.php?atmenu=payment&section=10">»» {"Refills"|gettext}</a></li>
				<li><a href="A2B_entity_payment.php?atmenu=payment&section=10">»» {"Payments"|gettext}</a></li>
				<li><a href="A2B_entity_paymentlog.php?section=10">»» {"E-Payment Log"|gettext}</a></li>
				<li><a href="A2B_entity_charge.php?section=10">»» {"Charges"|gettext}</a></li>
				<li><a href="A2B_entity_agentsituation.php?atmenu=agentsituation&section=10">{"Agents Balance"|gettext}</a></li>
				<li><a href="A2B_entity_commission_agent.php?atmenu=payment&section=10">»» {"Commissions"|gettext}</a></li>
				<li><a href="A2B_entity_remittance_request.php?atmenu=payment&section=10">»» {"Remittance Request"|gettext}</a></li>
				<li><a href="A2B_entity_transactions_agent.php?atmenu=payment&section=10">»» {"Transactions"|gettext}</a></li>
				<li><a href="A2B_entity_logrefill_agent.php?atmenu=payment&section=10">»» {"Refills"|gettext}</a></li>
				<li><a href="A2B_entity_payment_agent.php?atmenu=payment&section=10">»» {"Payments"|gettext}</a></li>
				<li><a href="A2B_entity_paymentlog_agent.php?section=10">»» {"E-Payment Log"|gettext}</a></li>
				<li><a href="A2B_entity_payment_configuration.php?atmenu=payment&section=10">{"Payment Methods"|gettext}</a></li>
				<li><a href="A2B_currencies.php?section=10">{"Currency List"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXINVOICING > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img11"
	{if ($section == "11")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"INVOICES"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="11")}
		style="">
	{else}
	style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_receipt.php?atmenu=payment&section=11">{"Receipts"|gettext}</a></li>
				<li><a href="A2B_entity_invoice.php?atmenu=payment&section=11">{"Invoices"|gettext}</a></li>
				<li><a href="A2B_entity_invoice_conf.php?atmenu=payment&section=11">»» {"Configuration"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXPACKAGEOFFER > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img12"
	{if ($section == "12")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"PACKAGE OFFER"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="12")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_package.php?atmenu=package&section=12">{"Add"|gettext}</a></li>
				<li><a href="A2B_detail_package.php?section=12">{"Details"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXCRONTSERVICE  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img13"
	{if ($section == "13")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"RECUR SERVICE"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="13")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_service.php?section=13">{"Account Service"|gettext}</a></li>
				<li><a href="A2B_entity_subscription.php?section=13">{"Subscriptions Service"|gettext}</a></li>
				<li><a href="A2B_entity_subscriber_signup.php?section=13">{"Subscriptions SIGNUP"|gettext}</a></li>
				<li><a href="A2B_entity_subscriber.php?section=13">{"Subscribers"|gettext}</a></li>
				<li><a href="A2B_entity_autorefill.php?section=13">{"AutoRefill Report"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXCALLBACK  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img14"
	{if ($section == "14")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"CALLBACK"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="14")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_callback.php?section=14">{"Add"|gettext}</a></li>
				<li><a href="A2B_entity_server_group.php?section=14">{"Server Group"|gettext}</a></li>
				<li><a href="A2B_entity_server.php?section=14">{"Server"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}

	{if ($ACXPREDICTIVEDIALER  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img15"
	{if ($section == "15")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"CAMPAIGNS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="15")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_campaign.php?section=15">{"Autodialer"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXMAINTENANCE  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img16"
	{if ($section == "16")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"MAINTENANCE"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="16")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_alarm.php?section=16"> {"Alarms"|gettext}</a></li>
				<li><a href="A2B_entity_log_viewer.php?section=16">{"Users Activity"|gettext}</a></li>
				<li><a href="A2B_entity_backup.php?form_action=ask-add&section=16">{"Database Backup"|gettext}</a></li>
				<li><a href="A2B_entity_restore.php?section=16">{"Database Restore"|gettext}</a></li>
				<li><a href="CC_musiconhold.php?section=16">{"MusicOnHold"|gettext}</a></li>
				<li><a href="CC_upload.php?section=16">{"Upload File"|gettext}</a></li>
				<li><a href="A2B_logfile.php?section=16">{"Watch Log files"|gettext}</a></li>
				<li><a href="A2B_data_archiving.php?section=16">{"Archiving"|gettext}</a></li>
				<li><a href="A2B_asteriskinfo.php?section=16">Asterisk Info</a></li>
				<li><a href="A2B_phpsysinfo.php?section=16">phpSysInfo</a></li>
				<li><a href="A2B_phpinfo.php?section=16">phpInfo</a></li>
				<li><a href="A2B_entity_monitor.php?section=16"> {"Monitoring"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>

	{/if}

	{if ($ACXMAIL  > 0)}
	<!-- Disabled Mail feature -->
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img17"
	{if ($section == "17")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"MAIL"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="17")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_mailtemplate.php?atmenu=mailtemplate&section=17&languages=en">{"Mail templates"|gettext}</a></li>
				<li><a href="A2B_mass_mail.php?section=17">{"Mass Mail"|gettext}</a></li>
			</ul></li>
		</ul>
	</div>
	{/if}


	{if ($ACXSETTING  > 0)}
	<div class="toggle_menu"><li>
	<a href="javascript:;" class="toggle_menu" target="_self"> <div> <div id="menutitlebutton"> <img id="img18"
	{if ($section == "18")}
	src="templates/{$SKIN_NAME}/images/minus.gif"
	{else}
	src="templates/{$SKIN_NAME}/images/plus.gif"
	{/if} onmouseover="this.style.cursor='hand';" ></div> <div id="menutitlesection"><strong>{"SYSTEM SETTINGS"|gettext}</strong></div></div></a></li></div>
		<div class="tohide"
	{if ($section =="18")}
		style="">
	{else}
		style="display:none;">
	{/if}
		<ul>
			<li><ul>
				<li><a href="A2B_entity_config.php?form_action=list&atmenu=config&section=18">{"Global List"|gettext}</a></li>
				<li><a href="A2B_entity_config_group.php?form_action=list&atmenu=configgroup&section=18">{"Group List"|gettext}</a></li>
				<li><a href="A2B_entity_config_generate_confirm.php?section=18">{"Add agi-conf"|gettext}</a></li>
				<li><a href="A2B_provider_setup.php?section=18">{"Provider Setup"|gettext}</a></li>
				<li><a href="phpconfig.php?dir=/etc/asterisk&section=18">{"* Config Editor"|gettext}</a></li>
				{if ($ASTERISK_GUI_LINK)}
					<li><a href="http://{$HTTP_HOST}:8088/asterisk/static/config/index.html" target="_blank">{"Asterisk GUI"|gettext}</a></li>
				{/if}
			</ul></li>
		</ul>
	</div>

	{/if}

</ul>

<br/>
<ul id="nav"><li>
	<ul><li><a href="A2B_entity_password.php?atmenu=password&form_action=ask-edit"><strong>{"Change Password"|gettext}</strong> <img style="vertical-align:bottom;" src="templates/{$SKIN_NAME}/images/key.png"> </a></li></ul>
</li></ul>

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

