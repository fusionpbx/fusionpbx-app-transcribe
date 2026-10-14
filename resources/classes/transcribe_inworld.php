<?php

/**
 * transcribe_inworld class
 *
 * Uses the Inworld AI Speech-to-Text api to transcribe audio.
 * - single channel recordings are transcribed directly
 * - multi channel recordings are transcribed with speaker diarization
 */
class transcribe_inworld implements transcribe_interface {

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
	private $diarize;
	private $format;
	private $message;
	private $language;
	private $translate;
	private $sample_rate;
	private $temp_dir;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {
		// build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key', '');
		$this->api_url = $settings->get('transcribe', 'api_url', 'https://api.inworld.ai/stt/v1/transcribe');
		$this->api_model = $settings->get('transcribe', 'api_model', 'inworld/inworld-stt-1');
		$this->diarize = $settings->get('transcribe', 'diarize', false);
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
		$this->language = 'en-US';
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
		// return the whether engine is handles languages
		return false;
	}

	public function is_translate_enabled() : bool {
		// return the whether engine is able to translate
		return false;
	}

	public function get_languages() : array {
		// create the languages array
		$languages = array(
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

		// return the languages array
		return $languages;
	}

	/**
	 * get_models - get the available inworld stt models
	 */
	public function get_models(): array {
		return [
			'inworld/inworld-stt-1' => 'Inworld STT 1'
		];
	}

	/**
	 * set_model - set the inworld stt model
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
	 * transcribe - speech to text
	 *
	 * @param string $output_type options: json, text
	 *
	 * @return string transcibed messages returned or empty for failure
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

		// multiple channel recordings are transcribed with speaker diarization
		$diarize = ($this->audio_channels > 1) ? true : (bool)$this->diarize;

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

			// set the segment filename (converted to a 16kHz 16-bit PCM mono WAV for Inworld)
			$output_filename = $file_base_name . ".segment." . ($i + 1) . ".wav";

			// convert the segment to a 16kHz 16-bit PCM mono WAV
			// Inworld requires LINEAR16 audio, the original telephony codec is decoded and resampled
			$command = "ffmpeg -y -ss " . $start_time . " -t " . $segment_length . " -i " . escapeshellarg($this->path . "/" . $this->filename) . " -ac 1 -ar " . $this->sample_rate . " -acodec pcm_s16le " . escapeshellarg($this->temp_dir . "/" . $output_filename);
			shell_exec($command);

			// call the send_request function with the filename of each segment
			$transcribe_array[] = array(
				'channel' => '0',
				'segment_id' => $i,
				'segment_length' => $segment_length,
				'diarize' => $diarize,
				'response' => $this->send_request($this->temp_dir, $output_filename, $diarize)
			);

			// remove the segmented file name
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
					// calculate the start and stop time relative to the full recording
					$offset = ($row['segment_id'] * $row['segment_length']);
					$segment['start'] = ($segment['start'] ?? 0) + $offset;
					$segment['end'] = ($segment['end'] ?? 0) + $offset;

					// add keys values to the array
					$array = [];
					$array['channel'] = $segment['channel'] ?? '0';
					$array['speaker'] = $segment['speaker'] ?? $array['channel'];
					$array['start'] = $segment['start'];
					$array['end'] = $segment['end'];
					$array['text'] = $segment['text'];

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
	 * build_segments - convert an Inworld transcription response into standard segments
	 *
	 * @param array $row segment metadata (segment_id, segment_length, diarize)
	 * @param array $transcript decoded Inworld response
	 *
	 * @return array list of segments (start/end in seconds, text, speaker, channel)
	 */
	private function build_segments(array $row, $transcript) : array {

		$segments = [];

		// guard against a non-array response
		if (!is_array($transcript) || !isset($transcript['transcription']) || !is_array($transcript['transcription'])) {
			return $segments;
		}

		$transcription = $transcript['transcription'];
		$diarize = !empty($row['diarize']);

		// the pause (in ms) between words that forces a new segment
		$pause_threshold = 500;

		// if word timestamps are available then group the words into segments
		if (isset($transcription['wordTimestamps']) && is_array($transcription['wordTimestamps']) && !empty($transcription['wordTimestamps'])) {
			$current = null;
			foreach ($transcription['wordTimestamps'] as $word) {
				// skip words without timing data
				if (!isset($word['startTimeMs'], $word['endTimeMs'])) {
					continue;
				}

				// get the speaker for this word (diarization assigns an integer speaker per word)
				$speaker = (isset($word['speaker']) && $word['speaker'] !== null) ? (string)$word['speaker'] : '0';

				// decide when to start a new segment
				$new_segment = false;
				if ($current === null) {
					$new_segment = true;
				}
				elseif ($diarize && $speaker !== $current['speaker']) {
					// speaker changed, start a new segment
					$new_segment = true;
				}
				elseif (($word['startTimeMs'] - $current['end_ms']) > $pause_threshold) {
					// long pause, start a new segment
					$new_segment = true;
				}

				if ($new_segment) {
					// add the previous segment
					if ($current !== null) {
						$segments[] = $current;
					}
					// start a new segment
					$current = [
						'speaker' => $speaker,
						'channel' => $diarize ? $speaker : '0',
						'start_ms' => $word['startTimeMs'],
						'end_ms' => $word['endTimeMs'],
						'text' => trim($word['word'] ?? '')
					];
				}
				else {
					// extend the current segment
					$current['end_ms'] = $word['endTimeMs'];
					$current['text'] .= ' ' . trim($word['word'] ?? '');
				}
			}

			// add the final segment
			if ($current !== null) {
				$segments[] = $current;
			}
		}
		// fall back to a single segment using the full transcript
		elseif (isset($transcription['transcript']) && !empty($transcription['transcript'])) {
			$segments[] = [
				'speaker' => '0',
				'channel' => '0',
				'start_ms' => 0,
				'end_ms' => 0,
				'text' => trim($transcription['transcript'])
			];
		}

		// convert the millisecond timing to seconds
		$converted = [];
		foreach ($segments as $segment) {
			$converted[] = [
				'channel' => $segment['channel'],
				'speaker' => $segment['speaker'],
				'start' => round(($segment['start_ms'] ?? 0) / 1000, 3),
				'end' => round(($segment['end_ms'] ?? 0) / 1000, 3),
				'text' => trim($segment['text'])
			];
		}

		return $converted;
	}

	/**
	 * send_request - make the transcribe request to the Inworld api
	 *
	 * @param string $path path of the audio file
	 * @param string $filename name of the audio file
	 * @param bool $diarize enable speaker diarization for the request
	 *
	 * @return string|false the raw response from the api or false on failure
	 */
	public function send_request(string $path, string $filename, bool $diarize = false) {

		// read the audio file or use the provided audio string
		if (file_exists($path . '/' . $filename)) {
			// send the audio from the file system
			$content = base64_encode(file_get_contents($path . '/' . $filename));
		}
		elseif (!empty($this->audio_string)) {
			// send the audio from as a string
			$content = base64_encode($this->audio_string);
		}
		else {
			// audio file or string not found
			return false;
		}

		// build the transcribe config (Inworld requires LINEAR16 audio)
		$transcribe_config = [
			'modelId' => $this->api_model,
			'audioEncoding' => 'LINEAR16',
			'sampleRateHertz' => $this->sample_rate,
			'numberOfChannels' => 1,
			'includeWordTimestamps' => true
		];

		// set the language when it is available
		if (!empty($this->language)) {
			$transcribe_config['language'] = $this->language;
		}

		// enable speaker diarization for the request
		if ($diarize) {
			$transcribe_config['enableSpeakerDiarization'] = true;
		}

		// build the request body
		$post_data = [
			'transcribeConfig' => $transcribe_config,
			'audioData' => [
				'content' => $content
			]
		];

		// initialize a curl handle
		$ch = curl_init();

		// set the URL for the request
		curl_setopt($ch, CURLOPT_URL, $this->api_url);

		// set the request method to POST
		curl_setopt($ch, CURLOPT_POST, true);

		// set the request headers (Inworld uses basic authentication)
		$headers = [];
		$headers[] = "Authorization: Basic " . $this->api_key;
		$headers[] = "Content-Type: application/json";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// prepare and send the http post data
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

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

		// close the handle
		unset($ch);

		// return the result from the request
		return $message;
	}

}