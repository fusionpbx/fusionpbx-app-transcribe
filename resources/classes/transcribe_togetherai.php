<?php

/**
* transcribe_togetherai class
*
*/
class transcribe_togetherai implements transcribe_interface {

	/**
	 * declare public variables
	 */
	public $api_model;
	public $audio_channels;

	/**
	 * declare private variables
	 */
	private $api_key;
	private $api_url;
	private $path;
	private $filename;
	private $audio_string;
	private $audio_mime_type;
	private $audio_duration;
	private $diarize;
	private $format;
	private $message;
	private $language;
	private $translate;
	private $temp_dir;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {
		$this->api_key = $settings->get('transcribe', 'api_key', '');
		$this->api_url = $settings->get('transcribe', 'api_url', 'https://api.together.xyz/v1/audio/transcriptions');
		$this->api_model = 'openai/whisper-large-v3';
		$this->diarize = false;

		if (file_exists('/dev/shm')) {
			$this->temp_dir = '/dev/shm';
		}
		else {
			$this->temp_dir = sys_get_temp_dir();
		}

		$this->audio_channels = 1;
		$this->audio_duration = 0;
	}

	public function set_path(string $audio_path) {
		$this->path = $audio_path;
	}

	public function set_filename(string $audio_filename) {
		$this->filename = $audio_filename;
	}

	public function set_audio_string(string $audio_string) {
		$this->audio_string = $audio_string;
	}

	public function set_audio_mime_type(string $audio_mime_type) {
		$this->audio_mime_type = $audio_mime_type;
	}

	public function set_format(string $audio_format) {
		$this->format = $audio_format;
	}

	public function set_language(string $audio_language) {
		$this->language = $audio_language;
	}

	public function set_translate(string $audio_translate) {
		$this->translate = $audio_translate;
	}

	public function set_message(string $audio_message) {
		$this->message = $audio_message;
	}

	public function is_language_enabled() : bool {
		return true;
	}

	public function is_translate_enabled() : bool {
		return false;
	}

	public function get_languages() : array {
		return array(
			"af" => "Afrikaans",
			"ar" => "Arabic",
			"hy" => "Armenian",
			"az" => "Azerbaijani",
			"be" => "Belarusian",
			"bs" => "Bosnian",
			"bg" => "Bulgarian",
			"ca" => "Catalan",
			"zh" => "Chinese",
			"hr" => "Croatian",
			"cs" => "Czech",
			"da" => "Danish",
			"nl" => "Dutch",
			"en" => "English",
			"et" => "Estonian",
			"fi" => "Finnish",
			"fr" => "French",
			"gl" => "Galician",
			"de" => "German",
			"el" => "Greek",
			"he" => "Hebrew",
			"hi" => "Hindi",
			"hu" => "Hungarian",
			"is" => "Icelandic",
			"id" => "Indonesian",
			"it" => "Italian",
			"ja" => "Japanese",
			"kn" => "Kannada",
			"kk" => "Kazakh",
			"ko" => "Korean",
			"lv" => "Latvian",
			"lt" => "Lithuanian",
			"mk" => "Macedonian",
			"ms" => "Malay",
			"mr" => "Marathi",
			"mi" => "Maori",
			"ne" => "Nepali",
			"no" => "Norwegian",
			"fa" => "Persian",
			"pl" => "Polish",
			"pt" => "Portuguese",
			"ro" => "Romanian",
			"ru" => "Russian",
			"sr" => "Serbian",
			"sk" => "Slovak",
			"sl" => "Slovenian",
			"es" => "Spanish",
			"sw" => "Swahili",
			"sv" => "Swedish",
			"tl" => "Tagalog",
			"ta" => "Tamil",
			"th" => "Thai",
			"tr" => "Turkish",
			"uk" => "Ukrainian",
			"ur" => "Urdu",
			"vi" => "Vietnamese",
			"cy" => "Welsh"
		);
	}

	/**
	 * get_audio_channels - get the number of audio channels in the file
	 */
	private function get_audio_channels($path, $filename) : int {
		if (empty($path) || empty($filename)) {
			return 0;
		}

		if (!file_exists($path . '/' . $filename)) {
			return 0;
		}

		$command = "ffprobe -v error -select_streams a:0 -show_entries stream=channels -of default=noprint_wrappers=1:nokey=1 ".escapeshellarg($path).'/'.escapeshellarg($filename);
		$output = shell_exec($command);
		if (empty($output)) {
			return 1;
		}
		else {
			return (int)trim($output);
		}
	}

	/**
	 * get_audio_duration - get the audio duration in seconds
	 */
	private function get_audio_duration($path, $filename) : int {
		if (empty($path) || empty($filename)) {
			return 0;
		}

		if (!file_exists($path . '/' . $filename)) {
			return 0;
		}

		$command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ".escapeshellarg($path).'/'.escapeshellarg($filename);
		$output = shell_exec($command);
		if (empty($output)) {
			return 0;
		}
		else {
			return (int)trim($output);
		}
	}

	/**
	 * get_audio_start - get the audio start time in seconds
	 */
	private function get_audio_start_time($path, $filename) : float {
		if (empty($path) || empty($filename)) {
			return 0;
		}

		if (!file_exists($path . '/' . $filename)) {
			return 0;
		}

		$command = "ffmpeg -i \"" . escapeshellarg($path) . "/" . escapeshellarg($filename) . "\" -af \"silencedetect=n=-50dB:d=0.5\" -f null - 2>&1 | grep \"silence_start\"";
		exec($command, $output);

		foreach ($output as $line) {
			if (strpos($line, 'silence_start') !== false) {
				preg_match('/silence_start:\s*(\d+\.?\d*)/', $line, $matches);
				if (isset($matches[1])) {
					return (float)$matches[1];
				}
			}
		}

		return 0;
	}

	/**
	 * extract segments from a Together transcription response
	 */
	private function get_transcript_segments(array $transcript) : array {
		if (!empty($transcript['segments']) && is_array($transcript['segments'])) {
			return $transcript['segments'];
		}

		if (!empty($transcript['speaker_segments']) && is_array($transcript['speaker_segments'])) {
			$segments = [];
			foreach ($transcript['speaker_segments'] as $segment) {
				$segments[] = [
					'start' => $segment['start'] ?? 0,
					'end' => $segment['end'] ?? 0,
					'text' => $segment['text'] ?? '',
					'speaker' => $segment['speaker'] ?? '0',
					'channel' => $segment['speaker'] ?? '0',
				];
			}
			return $segments;
		}

		if (isset($transcript['text'])) {
			return [[
				'start' => 0,
				'end' => 0,
				'text' => $transcript['text'],
				'speaker' => '0',
				'channel' => '0',
			]];
		}

		return [];
	}

	/**
	 * transcribe - speech to text
	 *
	 * @param string $output_type options: json, text
	 *
	 * @return string transcibed messages returned or empty for failure
	 */
	public function transcribe(?string $output_type = 'text') : string {
		if (empty($this->path) || empty($this->filename)) {
			return '';
		}

		if (!file_exists($this->path . '/' . $this->filename)) {
			return '';
		}

		$this->audio_channels = $this->get_audio_channels($this->path, $this->filename);
		$this->audio_duration = $this->get_audio_duration($this->path, $this->filename);

		$transcribe_array = [];
		$segment_length = 900;
		$total_segments = max(1, ceil($this->audio_duration / $segment_length));

		for ($i = 0; $i < $total_segments; $i++) {
			$start_time = $i * $segment_length;
			$path_parts = pathinfo($this->filename);
			$file_extension = strtolower($path_parts['extension'] ?? '');
			$file_base_name = $path_parts['filename'];
			$output_filename = $file_base_name . ".segment." . ($i + 1) . "." . $file_extension;
			$codec_parameter = '';

			if ($file_extension == 'mp3') {
				$codec_parameter = '-c copy';
			}

			if ($file_extension == 'wav') {
				$codec_parameter = '-c:a pcm_s16le';
			}

			$command = "ffmpeg -y -ss ".$start_time." -t ".$segment_length." -i ".escapeshellarg($this->path)."/".escapeshellarg($this->filename)." ".$codec_parameter." ".escapeshellarg($this->temp_dir)."/".escapeshellarg($output_filename);
			shell_exec($command);

			if ($this->audio_channels == 1 || $this->diarize) {
				$transcribe_array[] = [
					'channel' => '0',
					'segment_id' => $i,
					'segment_length' => $segment_length,
					'audio_start_time' => 0,
					'json' => $this->send_request($this->temp_dir, $output_filename),
				];
			}

			if ($this->audio_channels > 1 && !$this->diarize) {
				for ($channel = 0; $channel < $this->audio_channels; $channel++) {
					$output_channel_filename = $file_base_name . ".segment." . ($i + 1) . ".channel." . $channel . "." . $file_extension;
					$command = "ffmpeg -y -threads 4 -i " . escapeshellarg($this->temp_dir) . "/" . escapeshellarg($output_filename) . " -af \"pan=mono|c0=c" . $channel . "\" " . escapeshellarg($this->temp_dir) . "/" . escapeshellarg($output_channel_filename);
					shell_exec($command);

					$audio_start_time = $this->get_audio_start_time($this->temp_dir, $output_channel_filename);
					$transcribe_array[] = [
						'channel' => (string)$channel,
						'segment_id' => $i,
						'segment_length' => $segment_length,
						'audio_start_time' => $audio_start_time,
						'json' => $this->send_request($this->temp_dir, $output_channel_filename),
					];

					unlink($this->temp_dir.'/'.$output_channel_filename);
				}
			}

			unlink($this->temp_dir . '/' . $output_filename);
		}

		if (empty($transcribe_array)) {
			$this->message = '';
		}
		else {
			$all_text = '';
			$all_segments = [];

			foreach ($transcribe_array as $row) {
				$transcript = json_decode($row['json'], true);

				if (!is_array($transcript)) {
					return '';
				}

				if (isset($transcript['error'])) {
					print_r($transcript);
					return '';
				}

				$segments = $this->get_transcript_segments($transcript);
				foreach ($segments as $segment) {
					$segment['start'] = ($segment['start'] ?? 0) + ($row['audio_start_time'] ?? 0) + (($row['segment_id'] ?? 0) * ($row['segment_length'] ?? 0));
					$segment['end'] = ($segment['end'] ?? 0) + ($row['audio_start_time'] ?? 0) + (($row['segment_id'] ?? 0) * ($row['segment_length'] ?? 0));

					if ($this->diarize && isset($segment['speaker'])) {
						if ($segment['speaker'] == 'A') {
							$segment['channel'] = '0';
						}
						else if ($segment['speaker'] == 'B') {
							$segment['channel'] = '1';
						}
						else {
							$segment['channel'] = (string)($segment['speaker']);
						}
					}

					if (!$this->diarize && isset($row['channel'])) {
						$segment['speaker'] = $row['channel'];
						$segment['channel'] = $row['channel'];
					}

					$channel = (string)($segment['channel'] ?? $segment['speaker'] ?? '0');
					$text = $segment['text'] ?? '';

					$all_text .= trim($text) . ' ';
					$all_segments['segments'][] = [
						'channel' => $channel,
						'speaker' => $channel,
						'start' => $segment['start'] ?? 0,
						'end' => $segment['end'] ?? 0,
						'text' => $text,
					];
				}
			}

			if (!empty($all_segments['segments'])) {
				usort($all_segments['segments'], function ($a, $b) {
					return $a['start'] <=> $b['start'];
				});
			}

			if ($output_type == 'text') {
				$this->message = trim($all_text);
			}

			if ($output_type == 'json') {
				$this->message = json_encode(['segments' => $all_segments['segments'] ?? []], JSON_PRETTY_PRINT);
			}
		}

		if (empty($this->message)) {
			return '';
		}
		else {
			return trim($this->message);
		}
	}

	public function send_request(string $path, string $filename) : string {
		ob_start();
		$out = fopen('php://output', 'w');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->api_url);
		curl_setopt($ch, CURLOPT_POST, true);

		$headers = [];
		$headers[] = "Authorization: Bearer " . $this->api_key;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if (file_exists($path.'/'.$filename)) {
			$post_data['file'] = new CURLFile($path.'/'.$filename);
		}
		elseif (!empty($this->audio_string) && version_compare(PHP_VERSION, '8.1.0', '<')) {
			$temp_dir = sys_get_temp_dir();
			file_put_contents($temp_dir.'/'.$filename, $this->audio_string);
			$post_data['file'] = new CURLFile($temp_dir.'/'.$filename);
			unlink($temp_dir.'/'.$filename);
		}
		elseif (!empty($this->audio_string)) {
			$post_data['file'] = new CURLStringFile($this->audio_string, $filename, $this->audio_mime_type);
		}
		else {
			fclose($out);
			return '';
		}

		$post_data['model'] = $this->api_model;
		$post_data['response_format'] = 'verbose_json';
		$post_data['timestamp_granularities[]'] = 'segment';

		if (!empty($this->language)) {
			$post_data['language'] = $this->language;
		}

		if (!empty($this->message)) {
			$post_data['prompt'] = $this->message;
		}

		if ($this->diarize) {
			$post_data['diarize'] = 'true';
		}

		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 4500);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $out);

		$message = curl_exec($ch);
		fclose($out);

		if (curl_errno($ch)) {
			$error = 'Error: ' . curl_error($ch);
			curl_close($ch);
			return $error;
		}

		curl_close($ch);
		return is_string($message) ? $message : '';
	}

	public function set_model(string $model): void {
		if (array_key_exists($model, $this->get_models())) {
			$this->api_model = $model;
		}
	}

	public function get_models(): array {
		return [
			'openai/whisper-large-v3' => 'openai/whisper-large-v3',
		];
	}

}
