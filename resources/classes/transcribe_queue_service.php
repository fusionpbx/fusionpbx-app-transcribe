<?php

/**
 * Description goes here for transcribe_queue service
 */
class transcribe_queue_service extends service {

	/**
	 * database object
	 * @var database
	 */
	private $database;

	/**
	 * settings object
	 * @var settings
	 */
	private $settings;

	/**
	 * hostname variable
	 * @var string
	 */
	private $hostname;

	/**
	 * limit variable
	 * @var string
	 */
	private $limit;

	/**
	 * interval variable
	 * @var string
	 */
	private $interval;

	/**
	 * save_response variable
	 * @var string
	 */
	private $save_response;

	/**
	 * Reloads settings from database, config file and websocket server.
	 *
	 * @return void
	 */
	protected function reload_settings(): void {
		// re-read the config file to get any possible changes
		parent::$config->read();

		// Connect to the database
		$this->database = new database(['config' => parent::$config]);

		// get the settings using global defaults
		$this->settings = new settings(['database' => $database]);

		// get the hostname
		$this->hostname = gethostname();

		// get the limit
		$this->limit = intval($this->settings->get('transcribe', 'limit', 3));

		// get the interval
		$this->interval = intval($this->settings->get('transcribe', 'interval', 3));

		// save the response
		$this->save_response = $this->settings->get('transcribe', 'save_response', false);
	}

	public function run(): int {

		// Reload the settings
		$this->reload_settings();

		// Service work is handled here
		while ($this->running) {
			// Get the processing count from the transcribe queue
			$sql = "select count(*) as count ";
			$sql .= "from v_transcribe_queue ";
			$sql .= "where hostname = :hostname ";
			$sql .= "and transcribe_status = 'processing' ";
			$parameters['hostname'] = $this->hostname;
			$processing_count = $this->database->select($sql, $parameters, 'column');
 			unset($parameters);

	        // Only proceed if we haven't reached the limit
	        if ($processing_count < $this->limit) {
	            // Get pending jobs from the transcribe queue
				$sql = "select transcribe_queue_uuid ";
				$sql .= "from v_transcribe_queue ";
				$sql .= "where hostname = :hostname ";
				$sql .= "and transcribe_status = 'pending' ";
				$sql .= "limit :limit ";
				$parameters['hostname'] = $this->hostname;
				$parameters['limit'] = $this->limit;
				$transcribe_queue = $this->database->select($sql, $parameters , 'all');
				if (!empty($transcribe_queue)) {
					//$this->debug(implode(', ', $transcribe_queue));
					foreach($transcribe_queue as $row) {
						// Build the process command
						$command = PHP_BINARY." ".dirname(__DIR__, 4)."/app/transcribe/resources/jobs/process.php ";
						$command .= "'action=send&transcribe_queue_uuid=".$row["transcribe_queue_uuid"]."&hostname=".$this->hostname."'";

						if (parent::$log_level == 7) {
							// Run process inline to see debug info
							$this->debug($command);
							$result = system($command);
							$this->debug($result);
						}
						else {
							// Starts process rapidly doesn't wait for previous process to finish (used for production)
							$handle = popen($command." > /dev/null &", 'r');
							//$this->debug("'$handle' " . gettype($handle));
							$read = fread($handle, 2096);
							//$this->debug($read);
							pclose($handle);
						}
					}
				}
			}

			// Use the interval
			sleep($this->interval);
		}
		return 0;
	}

	protected static function display_version(): void {
		echo "1.00\n";
	}

	protected static function set_command_options() {

	}

}
