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

//check permissions
	if (!(permission_exists('transcribe_queue_add') || permission_exists('transcribe_queue_edit'))) {
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
	$button_icon_back = $settings->get('theme', 'button_icon_back', '');
	$button_icon_copy = $settings->get('theme', 'button_icon_copy', '');
	$button_icon_delete = $settings->get('theme', 'button_icon_delete', '');
	$button_icon_save = $settings->get('theme', 'button_icon_save', '');
	$input_toggle_style = $settings->get('theme', 'input_toggle_style', 'switch round');

//action add or update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$transcribe_queue_uuid = $_REQUEST["id"];
		$id = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		$hostname = $_POST["hostname"];
		$transcribe_status = $_POST["transcribe_status"];
		$transcribe_app_class = $_POST["transcribe_app_class"];
		$transcribe_app_method = $_POST["transcribe_app_method"];
		$transcribe_audio_path = $_POST["transcribe_audio_path"];
		$transcribe_audio_name = $_POST["transcribe_audio_name"];
		$transcribe_message = $_POST["transcribe_message"];
	}

//process the data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: transcribe_queue.php');
				exit;
			}

		//process the http post data by submitted action
			if ($_POST['action'] != '' && strlen($_POST['action']) > 0) {

				//prepare the array(s)
				//send the array to the database class
				switch ($_POST['action']) {
					case 'copy':
						if (permission_exists('transcribe_queue_add')) {
							$obj = new transcribe_queue;
							$obj->copy($array);
						}
						break;
					case 'delete':
						if (permission_exists('transcribe_queue_delete')) {
							$obj = new transcribe_queue;
							$obj->delete($array);
						}
						break;
					case 'toggle':
						if (permission_exists('transcribe_queue_update')) {
							$obj = new transcribe_queue;
							$obj->toggle($array);
						}
						break;
				}

				//redirect the user
				if (in_array($_POST['action'], array('copy', 'delete', 'toggle'))) {
					header('Location: transcribe_queue_edit.php?id='.$id);
					exit;
				}
			}

		//check for all required data
			$msg = '';
			if (strlen($hostname) == 0) { $msg .= $text['message-required']." ".$text['label-hostname']."<br>\n"; }
			if (strlen($transcribe_status) == 0) { $msg .= $text['message-required']." ".$text['label-transcribe_status']."<br>\n"; }
			if (strlen($transcribe_app_class) == 0) { $msg .= $text['message-required']." ".$text['label-transcribe_app_class']."<br>\n"; }
			if (strlen($transcribe_app_method) == 0) { $msg .= $text['message-required']." ".$text['label-transcribe_app_method']."<br>\n"; }
			if (strlen($transcribe_audio_path) == 0) { $msg .= $text['message-required']." ".$text['label-transcribe_audio_path']."<br>\n"; }
			if (strlen($transcribe_audio_name) == 0) { $msg .= $text['message-required']." ".$text['label-transcribe_audio_name']."<br>\n"; }
			//if (strlen($transcribe_message) == 0) { $msg .= $text['message-required']." ".$text['label-transcribe_message']."<br>\n"; }
			if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add the transcribe_queue_uuid
			if (!is_uuid($_POST["transcribe_queue_uuid"])) {
				$transcribe_queue_uuid = uuid();
			}

		//prepare the array
			$array['transcribe_queue'][0]['transcribe_queue_uuid'] = $transcribe_queue_uuid;
			$array['transcribe_queue'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['transcribe_queue'][0]['hostname'] = $hostname;
			$array['transcribe_queue'][0]['transcribe_status'] = $transcribe_status;
			$array['transcribe_queue'][0]['transcribe_app_name'] = $transcribe_app_name;
			$array['transcribe_queue'][0]['transcribe_app_method'] = $transcribe_app_method;
			$array['transcribe_queue'][0]['transcribe_audio_path'] = $transcribe_audio_path;
			$array['transcribe_queue'][0]['transcribe_audio_name'] = $transcribe_audio_name;
			$array['transcribe_queue'][0]['transcribe_message'] = $transcribe_message;

		//save the data
			$database->app_name = 'transcribe queue';
			$database->app_uuid = '8da245ba-e559-4094-9862-4bfaf5cec713';
			$database->save($array);

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					$_SESSION["message"] = $text['message-add'];
				}
				if ($action == "update") {
					$_SESSION["message"] = $text['message-update'];
				}
				//header('Location: transcribe_queue.php');
				header('Location: transcribe_queue_edit.php?id='.urlencode($transcribe_queue_uuid));
				return;
			}
	}

//pre-populate the form
	if (is_array($_GET) && $_POST["persistformvar"] != "true") {
		$sql = "select ";
		$sql .= " transcribe_queue_uuid, ";
		$sql .= " hostname, ";
		$sql .= " transcribe_status, ";
		$sql .= " transcribe_app_class, ";
		$sql .= " transcribe_app_method, ";
		$sql .= " transcribe_audio_path, ";
		$sql .= " transcribe_audio_name, ";
		$sql .= " transcribe_message ";
		$sql .= "from v_transcribe_queue ";
		$sql .= "where transcribe_queue_uuid = :transcribe_queue_uuid ";
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['transcribe_queue_uuid'] = $transcribe_queue_uuid;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$hostname = $row["hostname"];
			$transcribe_status = $row["transcribe_status"];
			$transcribe_app_class = $row["transcribe_app_class"];
			$transcribe_app_method = $row["transcribe_app_method"];
			$transcribe_audio_path = $row["transcribe_audio_path"];
			$transcribe_audio_name = $row["transcribe_audio_name"];
			$transcribe_message = $row["transcribe_message"];
		}
		unset($sql, $parameters, $row);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $text['title-transcribe_queue'];
	require_once "resources/header.php";

//show the content
	echo "<form name='frm' id='frm' method='post' action=''>\n";
	echo "<input class='formfld' type='hidden' name='transcribe_queue_uuid' value='".escape($transcribe_queue_uuid)."'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-transcribe_queue']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$button_icon_back,'id'=>'btn_back','collapse'=>'hide-xs','style'=>'margin-right: 15px;','link'=>'transcribe_queue.php']);
	if ($action == 'update') {
		if (permission_exists('_add')) {
			echo button::create(['type'=>'button','label'=>$text['button-copy'],'icon'=>$button_icon_copy,'id'=>'btn_copy','name'=>'btn_copy','style'=>'display: none;','onclick'=>"modal_open('modal-copy','btn_copy');"]);
		}
		if (permission_exists('_delete')) {
			echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$button_icon_delete,'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none; margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
		}
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$button_icon_save,'id'=>'btn_save','collapse'=>'hide-xs']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	//echo $text['title_description-transcribe_queue']."\n";
	//echo "<br /><br />\n";

	if ($action == 'update') {
		if (permission_exists('transcribe_queue_add')) {
			echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'copy','onclick'=>"modal_close();"])]);
		}
		if (permission_exists('transcribe_queue_delete')) {
			echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
		}
	}

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='20%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-hostname']."\n";
	echo "</td>\n";
	echo "<td width='80%' class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='hostname' maxlength='255' value='".escape($hostname)."'>\n";
	echo "<br />\n";
	echo $text['description-hostname']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-transcribe_status']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
		echo "	<select class='formfld' name='transcribe_status'>\n";
		echo "		<option value=''></option>\n";
		if ($transcribe_status == "pending") {
			echo "		<option value='pending' selected='selected'>".$text['label-pending']."</option>\n";
		}
		else {
			echo "		<option value='pending'>".$text['label-pending']."</option>\n";
		}
		if ($transcribe_status == "processing") {
			echo "		<option value='processing' selected='selected'>".$text['label-processing']."</option>\n";
		}
		else {
			echo "		<option value='processing'>".$text['label-processing']."</option>\n";
		}
		if ($transcribe_status == "completed") {
			echo "		<option value='completed' selected='selected'>".$text['label-completed']."</option>\n";
		}
		else {
			echo "		<option value='completed'>".$text['label-completed']."</option>\n";
		}
		if ($transcribe_status == "failed") {
			echo "		<option value='failed' selected='selected'>".$text['label-failed']."</option>\n";
		}
		else {
			echo "		<option value='failed'>".$text['label-failed']."</option>\n";
		}
		echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-transcribe_status']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-transcribe_app_class']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<input class='formfld' type='text' name='transcribe_app_class' maxlength='255' value='".escape($transcribe_app_class)."'>\n";
	echo "<br />\n";
	echo $text['description-transcribe_app_class']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-transcribe_app_method']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "  <input class='formfld' type='text' name='transcribe_app_method' maxlength='255' value='".escape($transcribe_app_method)."'>\n";
	echo "<br />\n";
	echo $text['description-transcribe_app_method']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-transcribe_audio_path']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "  <input class='formfld' type='text' name='transcribe_audio_path' maxlength='255' value='".escape($transcribe_audio_path)."'>\n";
	echo "<br />\n";
	echo $text['description-transcribe_audio_path']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-transcribe_audio_name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "  <input class='formfld' type='text' name='transcribe_audio_name' maxlength='255' value='".escape($transcribe_audio_name)."'>\n";
	echo "<br />\n";
	echo $text['description-transcribe_audio_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-transcribe_message']."\n";
	echo "</td>\n";
	echo "<td class='vtable' style='position: relative;' align='left'>\n";
	echo "	<textarea class='formfld' style='width: 450px; height: 100px;' name='transcribe_message'>";
	echo escape($transcribe_message)."\n";
	echo "	</textarea>";
	echo "<br />\n";
	echo $text['description-transcribe_message']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br /><br />";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
