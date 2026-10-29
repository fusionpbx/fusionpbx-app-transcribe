<?php

/**
 * transcribe_parakeet class
 *
 * Uses a self-hosted Parakeet (NVIDIA NeMo) transcription service to transcribe audio.
 * - the multilingual model (parakeet-tdt-0.6b-v3) transcribes 25 European languages
 *   and automatically detects the language of the audio, the English only model
 *   (parakeet-unified-en-0.6b) can also be selected in the settings
 * - single channel recordings (voicemails) are transcribed directly
 * - multi channel recordings (call recordings) have each channel split out and
 *   transcribed separately, then merged and interleaved by start time
 * - the Parakeet service only accepts 16 kHz single channel WAV audio, so the audio
 *   is converted with ffmpeg before it is uploaded
 * - with the timestamps setting enabled the service returns word-level timestamps
 *   ({"text": "...", "words": [{"word", "start", "end"}]}) which are grouped into
 *   the standard segment structure used by all of the transcribe engines
 *
 * The service is expected to accept a POST multipart/form-data request with a `file`
 * field plus optional `model`, `language`, `response_format`, and `timestamps`
 * form fields.
 */
class transcribe_parakeet implements transcribe_interface {

	/**
	 * declare public variables
	 */
	public  $api_model;
	public  $audio_channels;

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
	private $timestamps;
	private $sample_rate;
	private $format;
	private $message;
	private $language;
	private $translate;
	private $temp_dir;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {
		// build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key', '');
		$this->api_url = $settings->get('transcribe', 'api_url', 'http://127.0.0.1:8000/transcribe');
		$this->api_model = $settings->get('transcribe', 'api_model', 'parakeet-tdt-0.6b-v3');
		$this->timestamps = (bool)$settings->get('transcribe', 'timestamps', true);
		$this->sample_rate = (int)$settings->get('transcribe', 'sample_rate', 16000);

		// get the temp directory
		if (file_exists('/dev/shm')) {
			$this->temp_dir = '/dev/shm';
		}
		else {
			$this->temp_dir = sys_get_temp_dir();
		}

		// set the audio defaults
		$this->audio_channels = 1;
		$this->audio_duration = 0;

		// the multilingual model auto-detects the language of the audio,
		// a specific language can be set as a hint (empty = auto detect)
		$this->language = $settings->get('transcribe', 'language', '');
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
		// the multilingual model supports 25 European languages,
		// the English only model does not
		return $this->is_model_multilingual();
	}

	public function is_translate_enabled() : bool {
		// return the whether engine is able to translate
		return false;
	}

	public function get_languages() : array {
		// create the languages array
		// the multilingual model supports 25 European languages (auto-detected)
		$languages = array(
			"bg" => "Bulgarian",
			"cs" => "Czech",
			"da" => "Danish",
			"de" => "German",
			"el" => "Greek",
			"en" => "English",
			"es" => "Spanish",
			"et" => "Estonian",
			"fi" => "Finnish",
			"fr" => "French",
			"hr" => "Croatian",
			"hu" => "Hungarian",
			"it" => "Italian",
			"lt" => "Lithuanian",
			"lv" => "Latvian",
			"mt" => "Maltese",
			"nl" => "Dutch",
			"pl" => "Polish",
			"pt" => "Portuguese",
			"ro" => "Romanian",
			"ru" => "Russian",
			"sk" => "Slovak",
			"sl" => "Slovenian",
			"sv" => "Swedish",
			"uk" => "Ukrainian"
		);

		// the English only model has a single language
		if (!$this->is_model_multilingual()) {
			$languages = array(
				"en" => "English"
			);
		}

		// return the languages array
		return $languages;
	}

	/**
	 * is_model_multilingual - check if the selected model supports multiple languages
	 */
	private function is_model_multilingual(): bool {
		// only the v3 model is multilingual (25 European languages, auto-detected)
		return $this->api_model === 'parakeet-tdt-0.6b-v3';
	}

	/**
	 * get_models - get the available parakeet stt models
	 */
	public function get_models(): array {
		return [
			'parakeet-tdt-0.6b-v3' => 'Parakeet TDT 0.6B v3 (Multilingual, 25 languages)',
			'parakeet-unified-en-0.6b' => 'Parakeet Unified 0.6B (English)'
		];
	}

	/**
	 * set_model - set the parakeet stt model
	 */
	public function set_model(string $model): void {
		if (array_key_exists($model, $this->get_models())) {
			$this->api_model = $model;
		}
	}

	/**
	 * get_audio_channels - get the number of audio channels in the file
	 */
	private function get_audio_channels($path, $filename) : int {
		// ensure the path and file name are not empty
		if (empty($path) || empty($filename)) {
			return 0;
		}

		// check to make sure the file exists
		if (!file_exists($path . '/' . $filename)) {
			return 0;
		}

		// use ffprobe to get the number of audio channels
		$command = "ffprobe -v error -select_streams a:0 -show_entries stream=channels -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path . '/' . $filename);
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
		// ensure the path and file name are not empty
		if (empty($path) || empty($filename)) {
			return 0;
		}

		// check to make sure the file exists
		if (!file_exists($path . '/' . $filename)) {
			return 0;
		}

		// use ffprobe to get the number of audio duration
		$command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path . '/' . $filename);
		$output = shell_exec($command);
		if (empty($output)) {
			return 0;
		}
		else {
			return (int)trim($output);
		}
	}

	/**
	 * get_audio_start_time - get the audio start time in seconds
	 *
	 * Uses ffmpeg silencedetect to find the leading silence so the segment
	 * word timestamps can be aligned to the full recording timeline.
	 */
	function get_audio_start_time($path, $filename) : float {

		// ensure the path and file name are not empty
		if (empty($path) || empty($filename)) {
			return 0;
		}

		// check to make sure the file exists
		if (!file_exists($path . '/' . $filename)) {
			return 0;
		}

		// use ffmpeg to find the leading silence
		$command = "ffmpeg -i " . escapeshellarg($path . '/' . $filename) . " -af \"silencedetect=n=-50dB:d=0.5\" -f null - 2>&1 | grep \"silence_start\"";
		exec($command, $output);

		// detect the silence to find the audio start time
		foreach ($output as $line) {
			if (strpos($line, 'silence_start') !== false) {
				preg_match('/silence_start:\s*(\d+\.?\d*)/', $line, $matches);
				if (isset($matches[1])) {
					return (float)$matches[1];
				}
			}
		}

		// if not found then send return 0
		return 0;
	}


	/**
	 * transcribe - speech to text
	 *
	 * @param string $output_type options: json, text
	 *
	 * @return string transcribed messages returned or empty for failure
	 */
	public function transcribe(?string $output_type = 'text') : string {

		// ensure the path and file name are not empty
		if (empty($this->path) || empty($this->filename)) {
			return '';
		}

		// check to make sure the file exists
		if (!file_exists($this->path . '/' . $this->filename)) {
			return '';
		}

		// get the number of audio channels
		$this->audio_channels = $this->get_audio_channels($this->path, $this->filename);

		// get the duration of the audio file
		$this->audio_duration = $this->get_audio_duration($this->path, $this->filename);

		// audio duration is too short
		if ($this->audio_duration < 2) {
			return '';
		}

		// define the array
		$transcribe_array = [];

		// segment the audio if needed and then send a request for each segment
		$segment_length = 900; // 900 = 15 minutes in seconds
		$total_segments = ceil($this->audio_duration / $segment_length);

		// get the base file name without the extension
		$file_base_name = pathinfo($this->filename, PATHINFO_FILENAME);

		// process each segment and return the result in the message array
		for ($i = 0; $i < $total_segments; $i++) {
			// set the start time
			$start_time = $i * $segment_length;

			// set the segment filename (a 16 kHz PCM WAV; the Parakeet service
			// only accepts 16 kHz single channel WAV audio, so the channel is
			// separated out to a mono WAV below before upload)
			$output_filename = $file_base_name . ".segment." . ($i + 1) . ".wav";

			// convert the segment to a 16 kHz PCM WAV, the original codec (mp3,
			// ulaw, alaw, ...) is decoded and resampled in one step.
			// only downmix to mono when the source is already mono, a multi-channel
			// recording must keep its channels so each one can be separated below
			if ($this->audio_channels == 1) {
				$channel_parameter = "-ac 1";
			}
			else {
				$channel_parameter = "";
			}
			$command = "ffmpeg -y -ss " . $start_time . " -t " . $segment_length . " -i " . escapeshellarg($this->path . '/' . $this->filename) . " " . $channel_parameter . " -ar " . $this->sample_rate . " -acodec pcm_s16le " . escapeshellarg($this->temp_dir . '/' . $output_filename);
			shell_exec($command);

			// single channel audio (voicemail) process once
			if ($this->audio_channels == 1) {
				// call the send_request function with the filename of the segment
				$transcribe_array[] = array(
					'channel' => '0',
					'segment_id' => $i,
					'segment_length' => $segment_length,
					'offset' => $start_time,
					'response' => $this->send_request($this->temp_dir, $output_filename)
				);
			}

			// multi channel audio (call recording) process each channel
			if ($this->audio_channels > 1) {
				for ($channel = 0; $channel < $this->audio_channels; $channel++) {
					// set the channel filename
					$output_channel_filename = $file_base_name . ".segment." . ($i + 1) . ".channel." . $channel . ".wav";

					// separate the channel from the segment (keep the sample rate, it is already a PCM WAV)
					$command = "ffmpeg -y -i " . escapeshellarg($this->temp_dir . '/' . $output_filename) . " -af \"pan=mono|c0=c" . $channel . "\" -acodec pcm_s16le " . escapeshellarg($this->temp_dir . '/' . $output_channel_filename);
					shell_exec($command);

					// get the audio start time (leading silence) to align the word timestamps
					$audio_start_time = $this->get_audio_start_time($this->temp_dir, $output_channel_filename);

					// call the send_request function with the filename of the channel
					$transcribe_array[] = array(
						'channel' => (string)$channel,
						'segment_id' => $i,
						'segment_length' => $segment_length,
						'offset' => $start_time,
						'audio_start_time' => $audio_start_time,
						'response' => $this->send_request($this->temp_dir, $output_channel_filename)
					);

					// remove the channel file
					unlink($this->temp_dir . '/' . $output_channel_filename);
				}
			}

			// remove the segment file
			unlink($this->temp_dir . '/' . $output_filename);
		}

		// process the message array to combine the results
		if (empty($transcribe_array)) {
			$this->message = '';
		}
		else {
			// set default values
			$all_text = '';
			$all_segments = [];

			// process the transcribe results
			foreach ($transcribe_array as $row) {
				// decode the response to an array
				$transcript = is_array($row['response']) ? $row['response'] : json_decode((string)$row['response'], true);

				// build the segments for this response
				$segments = $this->build_segments($row, $transcript);

				// merge the segments
				foreach ($segments as $segment) {
					// add keys values to the array
					$array = [];
					$array['channel'] = $segment['channel'] ?? '0';
					$array['speaker'] = $segment['speaker'] ?? $array['channel'];
					$array['start'] = $segment['start'];
					$array['end'] = $segment['end'];
					$array['text'] = $segment['text'];
					if (!empty($segment['words'])) {
						// keep the word-level timestamps in the segment
						$array['words'] = $segment['words'];
					}

					// combine all text
					$all_text .= $segment['text'] . ' ';

					// prepare the array
					$all_segments['segments'][] = $array;
				}
			}

			// sort the segments in ascending order
			if (!empty($all_segments['segments']) && is_array($all_segments['segments'])) {
				usort($all_segments['segments'], function ($a, $b) {
					return $a['start'] <=> $b['start'];
				});
			}

			// set the message based on the requested output type
			if ($output_type == 'json') {
				$this->message = json_encode(['segments' => $all_segments['segments']], JSON_PRETTY_PRINT);
			}
			else {
				$this->message = trim($all_text);
			}
		}

		// return the transcription
		if (empty($this->message)) {
			return '';
		}
		else {
			return trim($this->message);
		}
	}

	/**
	 * build_segments - convert a Parakeet transcription response into standard segments
	 *
	 * Word timestamps (when the timestamps setting is enabled) are grouped into
	 * segments on a pause longer than the threshold or at the end of a sentence.
	 * A plain text response (timestamps disabled) becomes a single segment.
	 *
	 * @param array $row segment metadata (channel, segment_id, segment_length, offset, audio_start_time)
	 * @param mixed $transcript decoded Parakeet response
	 *
	 * @return array list of segments (start/end in seconds, text, speaker, channel, words)
	 */
	private function build_segments(array $row, $transcript) : array {

		$segments = [];

		// guard against a non-array or error response
		if (!is_array($transcript) || isset($transcript['error'])) {
			return $segments;
		}

		// calculate the offset to align this segment to the full recording timeline
		$offset = ($row['segment_id'] * $row['segment_length']) + ($row['audio_start_time'] ?? 0);
		$speaker = $row['channel'] ?? '0';

		// the pause (in seconds) between words that forces a new segment
		$pause_threshold = 1.0;

		// if word timestamps are available then group the words into segments
		if (isset($transcript['words']) && is_array($transcript['words']) && !empty($transcript['words'])) {
			$current = null;
			foreach ($transcript['words'] as $word) {
				// skip words without timing data
				if (!isset($word['start'], $word['end']) || trim($word['word'] ?? '') === '') {
					continue;
				}

				// decide when to start a new segment
				$new_segment = false;
				if ($current === null) {
					$new_segment = true;
				}
				elseif ((float)$word['start'] - (float)$current['end'] > $pause_threshold) {
					// long pause, start a new segment
					$new_segment = true;
				}
				elseif (preg_match('/[.!?]$/', trim($current['last_word']))) {
					// previous word ended a sentence, start a new segment
					$new_segment = true;
				}

				if ($new_segment) {
					// add the previous segment
					if ($current !== null) {
						unset($current['last_word']);
						$segments[] = $current;
					}
					// start a new segment
					$current = [
						'channel' => $speaker,
						'speaker' => $speaker,
						'start' => (float)$word['start'] + $offset,
						'end' => (float)$word['end'] + $offset,
						'text' => trim($word['word'] ?? ''),
						'last_word' => trim($word['word'] ?? ''),
						'words' => [[
							'word' => trim($word['word'] ?? ''),
							'start' => (float)$word['start'] + $offset,
							'end' => (float)$word['end'] + $offset
						]]
					];
				}
				else {
					// extend the current segment
					$current['end'] = (float)$word['end'] + $offset;
					$current['text'] .= ' ' . trim($word['word'] ?? '');
					$current['last_word'] = trim($word['word'] ?? '');
					$current['words'][] = [
						'word' => trim($word['word'] ?? ''),
						'start' => (float)$word['start'] + $offset,
						'end' => (float)$word['end'] + $offset
					];
				}
			}
			// add the final segment
			if ($current !== null) {
				unset($current['last_word']);
				$segments[] = $current;
			}

			// round the segment and word timing
			foreach ($segments as &$segment) {
				$segment['start'] = round($segment['start'], 3);
				$segment['end'] = round($segment['end'], 3);
				foreach ($segment['words'] as &$word) {
					$word['start'] = round($word['start'], 3);
					$word['end'] = round($word['end'], 3);
				}
				unset($word);
			}
			unset($segment);
		}
		// if only the plain text was returned (timestamps disabled) build a single segment
		elseif (isset($transcript['text']) && trim($transcript['text']) !== '') {
			$segments[] = [
				'channel' => $speaker,
				'speaker' => $speaker,
				'start' => round($offset, 3),
				'end' => round($offset + ($row['segment_length'] ?? 0), 3),
				'text' => trim($transcript['text'])
			];
		}

		return $segments;
	}


	/**
	 * send_request - make the transcribe request to the parakeet service
	 *
	 * @param string $path path of the audio file
	 * @param string $filename name of the audio file
	 *
	 * @return string|false the raw response from the service or false on failure
	 */
	public function send_request(string $path, string $filename) {

		// prepare the http post data
		if (file_exists($path . '/' . $filename)) {
			// send the audio from the file system
			$post_data['file'] = new CURLFile($path . '/' . $filename);
		}
		elseif (!empty($this->audio_string)) {
			// send the audio from as a string requires PHP 8.1 or higher
			$post_data['file'] = new CURLStringFile($this->audio_string, $filename, $this->audio_mime_type);
		}
		else {
			// audio file or string not found
			return false;
		}

		// prepare the request fields
		$post_data['model'] = $this->api_model;
		// send the language when it is set (the multilingual model auto-detects
		// the language of the audio, the value is a hint to the decoder)
		if (!empty($this->language)) {
			$post_data['language'] = $this->language;
		}
		$post_data['response_format'] = 'json';

		// request the word-level timestamps
		if ($this->timestamps) {
			$post_data['timestamps'] = 'true';
		}

		// initialize a curl handle
		$ch = curl_init();

		// set the URL for the request
		curl_setopt($ch, CURLOPT_URL, $this->api_url);

		// set the request method to POST
		curl_setopt($ch, CURLOPT_POST, true);

		// set the request headers (only set the authorization header when a key is configured)
		$headers = [];
		if (!empty($this->api_key)) {
			$headers[] = "Authorization: Bearer " . $this->api_key;
		}
		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		// prepare and send the http post data
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

		// return the response as a string instead of outputting it directly
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// set the connection timeout and the overall maximum curl run time
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 4500);

		// set whether to verify SSL peer
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		// run the curl request and transcription message
		$message = curl_exec($ch);

		// check for errors
		if (curl_errno($ch)) {
			// return false on a curl error
			unset($ch);
			return false;
		}

		// check for an http error (the service returns a json error object)
		$http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

		// close the handle
		unset($ch);

		// return the result from the request
		return ($http_code >= 200 && $http_code < 300) ? $message : false;
	}

}

