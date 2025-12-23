<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2025
	the Initial Developer. All Rights Reserved.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//includes files
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('transcribe_queue_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//connect to the database
	$database = database::new();

//add the settings object
	$settings = new settings(["domain_uuid" => $_SESSION['domain_uuid'], "user_uuid" => $_SESSION['user_uuid']]);

//set from session variables
	$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', 'false');

//get the http post data
	if (!empty($_POST['transcribe_queue']) && is_array($_POST['transcribe_queue'])) {
		$action = $_POST['action'];
		$search = $_POST['search'];
		$transcribe_queue = $_POST['transcribe_queue'];
	}

//process the http post data by action
	if (!empty($action) && !empty($transcribe_queue) && is_array($transcribe_queue) && @sizeof($transcribe_queue) != 0) {

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: transcribe_queue.php');
			exit;
		}

		//prepare the array
		if (!empty($transcribe_queue)) {
			foreach ($transcribe_queue as $row) {
				$array['transcribe_queue'][$x]['checked'] = $row['checked'];
				$array['transcribe_queue'][$x]['transcribe_queue_uuid'] = $row['transcribe_queue_uuid'];
				$x++;
			}
		}

		//prepare the database object
		$database->app_name = 'transcribe_queue';
		$database->app_uuid = '8da245ba-e559-4094-9862-4bfaf5cec713';

		//send the array to the database class
		switch ($action) {
			case 'copy':
				if (permission_exists('transcribe_queue_add')) {
					$database->copy($array);
					//$obj = new transcribe_queue;
					//$obj->copy($transcribe_queue);
				}
				break;
			case 'toggle':
				if (permission_exists('transcribe_queue_edit')) {
					$database->toggle($array);
					//$obj = new transcribe_queue;
					//$obj->toggle($transcribe_queue);
				}
				break;
			case 'delete':
				if (permission_exists('transcribe_queue_delete')) {
					$database->delete($array);
					//$obj = new transcribe_queue;
					//$obj->delete($transcribe_queue);
				}
				break;
		}

		//redirect the user
		header('Location: transcribe_queue.php'.($search != '' ? '?search='.urlencode($search) : null));
		exit;
	}

//get order and order by
	$order_by = $_GET["order_by"] ?? 'u.insert_date';
	$order = $_GET["order"] ?? 'desc';

//define the variables
	$search = '';
	$show = '';
	$list_row_url = '';

//add the search variable
	if (!empty($_GET["search"])) {
		$search = strtolower($_GET["search"]);
	}

//add the show variable
	if (!empty($_GET["show"])) {
		$show = $_GET["show"];
	}

//set the time zone
	$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());

//get the count
	$sql = "select count(transcribe_queue_uuid) ";
	$sql .= "from v_transcribe_queue ";
	if (permission_exists('transcribe_queue_all') && $show == 'all') {
		$sql .= "where true ";
	}
	else {
		$sql .= "where domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "	lower(hostname) like :search ";
		$sql .= "	or lower(transcribe_status) like :search ";
		$sql .= "	or lower(transcribe_app_class) like :search ";
		$sql .= "	or lower(transcribe_app_method) like :search ";
		$sql .= "	or lower(transcribe_message) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$num_rows = $database->select($sql, $parameters ?? null, 'column');
	unset($sql, $parameters);

//prepare to page the results
	$rows_per_page = $settings->get('domain', 'paging', 50);
	$param = !empty($search) ? "&search=".$search : null;
	$param .= (!empty($_GET['page']) && $show == 'all' && permission_exists('transcribe_queue_all')) ? "&show=all" : null;
	$page = !empty($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;
	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get the list
	$sql = "select ";
	$sql .= "transcribe_queue_uuid, ";
	$sql .= "u.domain_uuid, ";
	$sql .= "d.domain_name, ";
	$sql .= "hostname, ";
	$sql .= "to_char(timezone(:time_zone, u.insert_date), 'DD Mon YYYY') as date_formatted, \n";
	$sql .= "to_char(timezone(:time_zone, u.insert_date), 'HH12:MI:SS am') as time_formatted, \n";
	$sql .= "transcribe_status, ";
	$sql .= "transcribe_duration, ";
	$sql .= "transcribe_app_class, ";
	$sql .= "transcribe_app_method ";
	$sql .= "from v_transcribe_queue as u, v_domains as d ";
	if (permission_exists('transcribe_queue_all') && $show == 'all') {
		$sql .= "where true ";
	}
	else {
		$sql .= "where u.domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	}
	if (!empty($search)) {
		$sql .= "and ( ";
		$sql .= "	lower(hostname) like :search ";
		$sql .= "	or lower(transcribe_status) like :search ";
		$sql .= "	or lower(transcribe_app_class) like :search ";
		$sql .= "	or lower(transcribe_app_method) like :search ";
		$sql .= "	or lower(transcribe_message) like :search ";
		$sql .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= "and u.domain_uuid = d.domain_uuid ";
	$sql .= order_by($order_by, $order, '', '');
	$sql .= limit_offset($rows_per_page, $offset);
	$parameters['time_zone'] = $time_zone;
	$transcribe_queue = $database->select($sql, $parameters ?? null, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//additional includes
	$document['title'] = $text['title-transcribe_queue'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-transcribe_queue']." (".$num_rows.")</b></div>\n";
	echo "	<div class='actions'>\n";
	if (permission_exists('transcribe_queue_add') && $transcribe_queue) {
		echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$settings->get('theme', 'button_icon_copy'),'id'=>'btn_copy','name'=>'btn_copy','style'=>'display:none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
	}
	if (permission_exists('transcribe_queue_edit') && $transcribe_queue) {
		echo button::create(['type'=>'button','label'=>$text['button-toggle'],'icon'=>$settings->get('theme', 'button_icon_toggle'),'id'=>'btn_toggle','name'=>'btn_toggle','style'=>'display:none;','onclick'=>"modal_open('modal-toggle','btn_toggle');"]);
	}
	if (permission_exists('transcribe_queue_delete') && $transcribe_queue) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'id'=>'btn_delete','name'=>'btn_delete','style'=>'display:none;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo 		"<form id='form_search' class='inline' method='get'>\n";
	if (permission_exists('transcribe_queue_all')) {
		if ($show == 'all') {
			echo "		<input type='hidden' name='show' value='all'>\n";
		}
		else {
			echo button::create(['type'=>'button','label'=>$text['button-show_all'],'icon'=>$settings->get('theme', 'button_icon_all'),'link'=>'?show=all&search='.$search]);
		}
	}
	echo 		"<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	if ($paging_controls_mini != '') {
		echo 	"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if (permission_exists('transcribe_queue_add') && $transcribe_queue) {
		echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('transcribe_queue_edit') && $transcribe_queue) {
		echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);
	}
	if (permission_exists('transcribe_queue_delete') && $transcribe_queue) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	}

	echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search ?? '')."\">\n";

	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (permission_exists('transcribe_queue_add') || permission_exists('transcribe_queue_edit') || permission_exists('transcribe_queue_delete')) {
		echo "	<th class='checkbox'>\n";
		echo "		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);' ".empty($transcribe_queue ? "style='visibility: hidden;'" : null).">\n";
		echo "	</th>\n";
	}
	if ($show == 'all' && permission_exists('transcribe_queue_all')) {
		echo th_order_by('domain_name', $text['label-domain'], $order_by, $order);
	}
	echo "<th class=''>".$text['label-date']."</th>\n";
	echo "<th class='hide-md-dn'>".$text['label-time']."</th>\n";
	echo "<th class='hide-md-dn'>".$text['label-hostname']."</th>\n";
	echo "<th class='hide-md-dn'>".$text['label-transcribe_application_name']."</th>\n";
	//echo th_order_by('transcribe_application_name', $text['label-transcribe_application_name'], $order_by, $order);
	echo "<th class=''>".$text['label-transcribe_duration']."</th>\n";
	echo th_order_by('transcribe_status', $text['label-transcribe_status'], $order_by, $order);
	//echo th_order_by('transcribe_target_table', $text['label-transcribe_target_table'], $order_by, $order);
	//echo th_order_by('transcribe_target_key_name', $text['label-transcribe_target_key_name'], $order_by, $order);
	//echo th_order_by('transcribe_target_column_name', $text['label-transcribe_target_column_name'], $order_by, $order);
	if (permission_exists('transcribe_queue_edit') && $list_row_edit_button == 'true') {
		echo "	<td class='action-button'>&nbsp;</td>\n";
	}
	echo "</tr>\n";

	if (!empty($transcribe_queue) && is_array($transcribe_queue) && @sizeof($transcribe_queue) != 0) {
		$x = 0;
		foreach ($transcribe_queue as $row) {
			if (permission_exists('transcribe_queue_edit')) {
				$list_row_url = "transcribe_queue_edit.php?id=".urlencode($row['transcribe_queue_uuid']);
			}
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			if (permission_exists('transcribe_queue_add') || permission_exists('transcribe_queue_edit') || permission_exists('transcribe_queue_delete')) {
				echo "	<td class='checkbox'>\n";
				echo "		<input type='checkbox' name='transcribe_queue[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
				echo "		<input type='hidden' name='transcribe_queue[$x][transcribe_queue_uuid]' value='".escape($row['transcribe_queue_uuid'])."' />\n";
				echo "	</td>\n";
			}
			if ($show == 'all' && permission_exists('transcribe_queue_all')) {
				echo "	<td>".escape($row['domain_name'])."</td>\n";
			}
			echo "	<td nowrap='nowrap'>".escape($row['date_formatted'])."	</td>\n";
			echo "	<td nowrap='nowrap' class='shrink hide-md-dn'>".escape($row['time_formatted'])."	</td>\n";
			echo "	<td class='hide-md-dn'>\n";
			if (permission_exists('transcribe_queue_edit')) {
				echo "	<a href='".$list_row_url."' title=\"".$text['button-edit']."\">".escape($row['hostname'])."</a>\n";
			}
			else {
				echo "	".escape($row['hostname']);
			}
			echo "	</td>\n";
			echo "	<td>".escape($row['transcribe_app_model'])."</td>\n";
			echo "	<td class='hide-md-dn'>".escape($row['transcribe_duration'])."</td>\n";
			echo "	<td>".escape($row['transcribe_status'])."</td>\n";
			//echo "	<td>".escape($row['transcribe_target_table'])."</td>\n";
			//echo "	<td>".escape($row['transcribe_target_key_name'])."</td>\n";
			//echo "	<td>".escape($row['transcribe_target_column_name'])."</td>\n";
			if (permission_exists('transcribe_queue_edit') && $list_row_edit_button == 'true') {
				echo "	<td class='action-button'>\n";
				echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$settings->get('theme', 'button_icon_edit'),'link'=>$list_row_url]);
				echo "	</td>\n";
			}
			echo "</tr>\n";
			$x++;
		}
		unset($transcribe_queue);
	}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
