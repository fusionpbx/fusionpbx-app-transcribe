<?php

//check the permission
	if (defined('STDIN')) {
		//includes files
		require_once dirname(__DIR__, 4) . "/resources/require.php";
	}
	else {
		exit;
	}

//define the global settings
	global $settings, $database;

//increase limits
	set_time_limit(7200);
	ini_set('max_execution_time',7200); //2 hours
	ini_set('memory_limit', '512M');

//save the arguments to variables
	$script_name = $argv[0];
	if (!empty($argv[1])) {
		parse_str($argv[1], $_GET);
	}
	//print_r($_GET);

//get the primary key
	if (is_uuid($_GET['transcribe_queue_uuid'])) {
		$transcribe_queue_uuid = $_GET['transcribe_queue_uuid'];
		$hostname = urldecode($_GET['hostname']);
		$debug = $_GET['debug'] ?? null;
		$sleep_seconds = $_GET['sleep'] ?? null;
	}
	else {
		//invalid uuid
		exit;
	}

//define the process id file
	$pid_file = '/var/run/fusionpbx/transcribe_process.'.$transcribe_queue_uuid.'.pid';
	echo "pid_file: ".$pid_file."\n";

//function to check if the process exists
	function process_exists($file = '') {
		//check if the file exists return false if not found
		if (!file_exists($file)) {
			return false;
		}

		//check to see if the process id is valid
		$pid = file_get_contents($file);
		if (filter_var($pid, FILTER_VALIDATE_INT) === false) {
			return false;
		}

		//check if the process is running
		exec('ps -p '.$pid, $output);
		if (count($output) > 1) {
			return true;
		}
		else {
			return false;
		}
	}

//check to see if the process exists
	$pid_exists = process_exists($pid_file);

//prevent the process running more than once
	if ($pid_exists) {
		echo "Cannot lock pid file {$pid_file}\n";
		exit;
	}

//make sure the /var/run/fusionpbx directory exists
	if (!file_exists('/var/run/fusionpbx')) {
		$result = mkdir('/var/run/fusionpbx', 0777, true);
		if (!$result) {
			die('Failed to create /var/run/fusionpbx');
		}
	}

//create the process id file if the process doesn't exist
	if (!$pid_exists) {
		//remove the old pid file
		if (!empty($pid_file) && file_exists($pid_file)) {
			unlink($pid_file);
		}

		//show the details to the user
		//echo "The process id is ".getmypid()."\n";
		//echo "pid_file: ".$pid_file."\n";

		//save the pid file
		file_put_contents($pid_file, getmypid());
	}

//set the transcribe status to processing
	$sql = "update v_transcribe_queue ";
	$sql .= "set transcribe_status = 'processing' ";
	$sql .= "where transcribe_queue_uuid = :transcribe_queue_uuid; ";
	$parameters['transcribe_queue_uuid'] = $transcribe_queue_uuid;
	$database->execute($sql, $parameters);
	unset($parameters);

//sleep used for debugging
	if (isset($sleep_seconds)) {
		sleep($sleep_seconds);
	}

//get the email settings
	$save_response = $settings->get('transcribe', 'save_response', false);

//get the transcribe job details
	$sql = "select ";
	$sql .= " transcribe_queue_uuid, ";
	$sql .= " hostname, ";
	$sql .= " transcribe_status, ";
	$sql .= " transcribe_app_class, ";
	$sql .= " transcribe_app_method, ";
	$sql .= " transcribe_app_params, ";
	$sql .= " transcribe_audio_path, ";
	$sql .= " transcribe_audio_name, ";
	$sql .= " transcribe_message ";
	$sql .= "from v_transcribe_queue ";
	$sql .= "where transcribe_queue_uuid = :transcribe_queue_uuid ";
	$parameters['transcribe_queue_uuid'] = $transcribe_queue_uuid;
	$row = $database->select($sql, $parameters, 'row');
	if (is_array($row) && @sizeof($row) != 0) {
		$hostname = $row["hostname"];
		$transcribe_status = $row["transcribe_status"];
		$transcribe_app_class = $row["transcribe_app_class"];
		$transcribe_app_method = $row["transcribe_app_method"];
		$transcribe_app_params = $row["transcribe_app_params"];
		$transcribe_audio_path = $row["transcribe_audio_path"];
		$transcribe_audio_name = $row["transcribe_audio_name"];
	}
	unset($sql, $parameters, $row);

//transcribe the audio file
	if (!empty($transcribe_audio_path) && !empty($transcribe_audio_name)) {
		//set the start time
		$start_time = microtime(true);

		//initialize the transcribe object
		$transcribe = new transcribe($settings);

		//audio to text - get the transcription from the audio file
		$transcribe->audio_path = $transcribe_audio_path;
		$transcribe->audio_filename = $transcribe_audio_name;
		$transcribe_message = $transcribe->transcribe();

		//set the end time
		$end_time = microtime(true);

		//calculate the duration
		$transcribe_duration = round($end_time - $start_time, 1);
	}
	else {
		echo "audio path ".$transcribe_audio_path."\n";
		echo "audio name ".$transcribe_audio_name."\n";
	}

//update the transcribe status to completed
	$sql = "update v_transcribe_queue \n";
	$sql .= "set transcribe_status = 'completed', ";
	$sql .= "transcribe_duration = :transcribe_duration ";
	if ($save_response) {
		$sql .= ",\n transcribe_message = :transcribe_message \n";
	}
	$sql .= "where transcribe_queue_uuid = :transcribe_queue_uuid; \n";
	$parameters['transcribe_queue_uuid'] = $transcribe_queue_uuid;
	$parameters['transcribe_duration'] = $transcribe_duration;
	if ($save_response) {
		$parameters['transcribe_message'] = $transcribe_message;
	}
	$database->execute($sql, $parameters);
	unset($parameters);

//update the target table with the transcription
	if (!empty($transcribe_message)) {
		//convert the json to an array
		$params = json_decode($transcribe_app_params, true);
print_r($params);
		//add the transcription to the params array
		$params['transcribe_message'] = $transcribe_message;

		//add the transcription to the summary array
		if (!empty($transcribe_summary)) {
			$params['transcribe_summary'] = $transcribe_summary;
		}

		//check to see if the class exists
		if (!class_exists($transcribe_app_class)) {
echo __line__."\n";
			return false;
		}

		//create an instance dynamically
		$object = new $transcribe_app_class;

//echo __line__."\n";

		$object->$transcribe_app_method($params);
echo __line__."\n";
		//$object->transcribe_queue($params);
		//call the method dynamically
		//call_user_func_array([$object, $transcribe_app_method], $params);
echo __line__."\n";
	}

//remove the old pid file
	if (file_exists($pid_file)) {
		unlink($pid_file);
	}
