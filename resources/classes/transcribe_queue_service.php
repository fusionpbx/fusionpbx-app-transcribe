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
	 * @var int
	 */
	private $limit;

	/**
	 * interval variable
	 * @var int
	 */
	private $interval;

	/**
	 * save_response variable
	 * @var bool
	 */
	private $save_response;

	/**
	 * Tracks currently running child PIDs
	 * @var array
	 */
	private $running_pids = [];

	/**
	 * Reloads settings from database, config file and websocket server.
	 *
	 * @return void
	 */
	public function reload_settings(): void {
		// Re-read the config file to get any possible changes
		parent::$config->read();

		// Connect to the database
		$this->database = new database(['config' => parent::$config]);

		// Get the settings using global defaults
		$this->settings = new settings(['database' => $this->database]);

		// Get the hostname
		$this->hostname = gethostname();

		// Get the limit, ensure strict integer type
		$this->limit = (int)($this->settings->get('transcribe', 'limit', 3));

		// Get the interval
		$this->interval = (int)($this->settings->get('transcribe', 'interval', 3));

		// Save the response
		$this->save_response = $this->settings->get('transcribe', 'save_response', false);
	}

	/**
	 * Checks which PIDs are still active and removes dead ones from the tracking array.
	 * Returns the current count of active processes.
	 */
	private function get_active_process_count(): int {
		foreach ($this->running_pids as $key => $pid) {
			// posix_kill with signal 0 checks if a process is alive
			if (!posix_kill($pid, 0)) {
				unset($this->running_pids[$key]);
			}
		}
		return count($this->running_pids);
	}

	/**
	 * Cleans up jobs that are stuck in 'processing' state for too long.
	 * Essential for production to prevent queue clogs after a crash.
	 */
	private function cleanup_stale_jobs(): void {
		$timeout = (int)$this->settings->get('transcribe', 'stale_timeout', 3600); // Default 1 hour
		$sql = "UPDATE v_transcribe_queue ";
		$sql .= "SET transcribe_status = 'pending' ";
		$sql .= "WHERE transcribe_status = 'processing' ";
		$sql .= "AND ( ";
		$sql .= " update_date < NOW() - INTERVAL '" . $timeout . " seconds' ";
		$sql .= " OR update_date IS NULL ";
		$sql .= ") ";
		$this->database->execute($sql);
	}

	public function run(): int {
		// Reload the settings
		$this->reload_settings();

		// Service work is handled here
		while ($this->running) {

			try {
				// Make sure the database connection is available
				while (!$this->database->is_connected()) {
					// Connect to the database
					$this->database->connect();

					// Reload settings after connection to the database
					$this->settings = new settings(['database' => $this->database]);

					// Sleep for a moment
					sleep(1);
				}

				// Cleanup stale jobs. This is done in every loop to ensure the queue stays healthy
				$this->cleanup_stale_jobs();

				// Calculate exactly how many slots are available
				$current_running = $this->get_active_process_count();
				$available_slots = $this->limit - $current_running;

				// Use a single atomic UPDATE with LIMIT + FOR UPDATE SKIP LOCKED.
				if ($available_slots > 0) {
					$sql = "WITH target_rows AS ( ";
					$sql .= " SELECT transcribe_queue_uuid  ";
					$sql .= " FROM v_transcribe_queue  ";
					$sql .= " WHERE hostname = :hostname ";
					$sql .= " AND transcribe_status = 'pending'  ";
					$sql .= " ORDER BY insert_date ASC  ";
					$sql .= " LIMIT :limit  ";
					$sql .= " FOR UPDATE SKIP LOCKED ";
					$sql .= ") ";
					$sql .= "UPDATE v_transcribe_queue  ";
					$sql .= "SET transcribe_status = 'processing',  ";
					$sql .= " update_date = NOW()  ";
					$sql .= "WHERE transcribe_queue_uuid IN (SELECT transcribe_queue_uuid FROM target_rows)  ";
					$sql .= "RETURNING transcribe_queue_uuid ";
					$parameters['hostname'] = $this->hostname;
					$parameters['limit'] = (int)$available_slots;

					// Returns exactly the number of rows claimed in this atomic batch
					$claimed_jobs = $this->database->select($sql, $parameters, 'all');

					if (!empty($claimed_jobs)) {
						foreach ($claimed_jobs as $row) {
							// Set the variables
							$uuid = $row["transcribe_queue_uuid"];
							$host = $this->hostname;

							// Build the process command
							$command = PHP_BINARY." ".dirname(__DIR__, 4)."/app/transcribe/resources/jobs/process.php ";
							$command .= "'action=send&transcribe_queue_uuid=".$uuid."&hostname=".$host."'";
							$this->notice("command: " . $command);
							if (parent::$log_level == 7) {
								// Run process inline to see debug info
								$result = system($command);
								$this->debug($result);
							} else {
								// Start background process. > /dev/null & forks it immediately.
								// IMPORTANT: To track the PID, we use 'echo $!' which returns the PID of the last background process
								$exec_command = $command . " > /dev/null 2>&1 & echo \$!";
								$pid = (int)shell_exec($exec_command);
								$this->notice("process pid: " . $pid.", uuid: $uuid");
								if ($pid > 0) {
									$this->running_pids[] = $pid;
								}
							}
						}
					}
				} else {
					// Optional: log that we are at capacity
					// $this->error("Max concurrency reached ({$this->limit}). Waiting...");
				}
			} catch (\Throwable $t) {
				// Catch all throwables to prevent the daemon from dying
				$this->error("Critical Error in Transcribe Queue Loop: " . $t->getMessage() . " | SQL State: " . ($t instanceof PDOException ? $t->errorInfo[0] : ''));
			}

			// Memory Management - PHP long-running processes suffer from memory fragmentation/leaks. Limit: 100 MB
			if (memory_get_usage() > 100 * 1024 * 1024) {
				$this->error("The transcribe_queue service memory limit reached (" . round(memory_get_usage()/1024/1024, 2) . "MB). Running garbage collect function.");
				gc_collect_cycles();
			}

			// Use the interval
			sleep($this->interval);
		}
		return 0;
	}

	protected static function display_version(): void {
		echo "1.00\n";
	}

	protected static function set_command_options(): void {
		// Placeholder
	}
}
