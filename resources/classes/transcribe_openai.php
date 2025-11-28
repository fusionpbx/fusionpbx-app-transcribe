<?php

/**
* transcribe_openai class
*
*/
class transcribe_openai implements transcribe_interface {

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
	private $temp_dir;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {
		// build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key', '');
		$this->api_url = $settings->get('transcribe', 'api_url', 'https://api.openai.com/v1/audio/transcriptions');
		$this->api_model = $settings->get('transcribe', 'api_model', 'whisper-1');
		$this->diarize = $settings->get('transcribe', 'diarize', false);

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
	 * get_audio_channels - get the number of audio channels in the file
	 */
	private function get_audio_channels($path, $filename) : int {
		// use ffprobe to get the number of audio channels
 		$command = "ffprobe -v error -select_streams a:0 -show_entries stream=channels -of default=noprint_wrappers=1:nokey=1 ".$path.'/'.$filename;
 		$output = shell_exec($command);
		// echo "command:\n".$command."\n";
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
		// use ffprobe to get the number of audio duration
		$command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ".$path.'/'.$filename;
		// echo "command:\n".$command."\n";
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
	function get_audio_start_time($path, $filename) : float {

		// Use ffmpeg to find the  leading silence
		$command = "ffmpeg -i \"" . $path . "/" . $filename . "\" -af \"silencedetect=n=-50dB:d=0.5\" -f null - 2>&1 | grep \"silence_start\"";
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
	 * @return string transcibed messages returned or empty for failure
	 */
	public function transcribe(?string $output_type = 'text') : string {

		// get the number of audio channels
		$this->audio_channels = $this->get_audio_channels($this->path, $this->filename);

		// get the duration of the audio file
		$this->audio_duration = $this->get_audio_duration($this->path, $this->filename);

		// define the array
		$transcribe_array = [];

		// segment the audio if needed and then send a request for each segment
		$segment_length = 900; // 1200 = 20 minutes in seconds
		$total_segments = ceil($this->audio_duration / $segment_length);

		// process each segment and return the result in the message array
		for ($i = 0; $i < $total_segments; $i++) {
			// set the start time
			$start_time = $i * $segment_length;

			// get the path i the filename
			$path_parts = pathinfo($this->filename);

			// get the file extension
			$file_extension = strtolower($path_parts['extension'] ?? '');

			// get the base file name without the extension
			$file_base_name = $path_parts['filename'];

			// set the segement filename
			$output_filename = $file_base_name . ".segment." . ($i + 1) . "." . $file_extension;

			// set the codec parameter for mp3 to copy audio codec stream
			if ($file_extension == 'mp3') {
				$codec_parameter = '-c copy';
			}

			// set the codec parameter for wav when copy is not supported
			if ($file_extension == 'wav') {
				$codec_parameter = '-c:a pcm_s16le';
			}

			// save audio into segments
			$command = "ffmpeg -y -ss {$start_time} -t {$segment_length} -i {$this->path}/{$this->filename} {$codec_parameter} {$this->temp_dir}/{$output_filename}";
			shell_exec($command);

			// single channel process once or if diarize is enabled
			if ($this->audio_channels == 1 || $this->diarize) {
				// call the send_request function with the filename of each segment
				$transcribe_array[] = array('channel' => $channel, 'segment_id' => $i, 'segment_length' => $segment_length, 'text' => $this->send_request($this->temp_dir, $output_filename));
			}

			// multiple channels process each one in a loop
			if ($this->audio_channels > 1 && !$this->diarize) {
				for ($channel = 0; $channel < $this->audio_channels; $channel++) {
					// set the channel filename
					$output_channel_filename = $file_base_name . ".segment." . ($i + 1) . ".channel." . $channel . "." . $file_extension;

					// seperate the channels from the segment
					$command = "ffmpeg -y -threads 4 -i " . $this->temp_dir . "/" . $output_filename . " -map_channel 0.0." . $channel . " " . $this->temp_dir . "/" . $output_channel_filename;
					shell_exec($command);

					// get the audio start time
					$audio_start_time = $this->get_audio_start_time($this->temp_dir, $output_channel_filename);

					// call the send_request function with the filename of each segment
					$transcribe_array[] = array('channel' => (string)$channel, 'segment_id' => $i, 'segment_length' => $segment_length, 'audio_start_time' => $audio_start_time, 'json' => $this->send_request($this->temp_dir, $output_channel_filename));

					// remove the segmented file name
					unlink($this->temp_dir.'/'.$output_channel_filename);
				}
			}

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

			// determine the key to get the content from the transcribe results
			if (isset($transcribe_array[0]['text'])) {
				$content_key = 'text';
			} else {
				$content_key = 'json';
			}

			// process the transcribe results
			foreach ($transcribe_array as $row) {
				// decode the json to the transcript array
				$transcript = json_decode($row[$content_key], true);

				// check for errors in the transcript array
				if (isset($transcript['error'])) {
					print_r($transcript);
					return false;
				}

				// merge segments
				foreach ($transcript['segments'] as $segment) {

					//calculate the start and stop time
					if (isset($row['segment_id']) && isset($row['segment_length'])) {
						$segment['start'] = $segment['start'] + $row['audio_start_time'] + ($row['segment_id'] * $row['segment_length']);
						$segment['end'] = $segment['end'] + $row['audio_start_time'] + ($row['segment_id'] * $row['segment_length']);
					}

					//set the speaker for diarization to a number
					if ($this->diarize) {
						if ($segment['speaker'] == 'A') {
							$segment['channel'] = '0';
						} else if ($segment['speaker'] == 'B') {
							$segment['channel'] = '1';
						}
					}

					//set the speaker using the channel id
					if (!$this->diarize) {
						if (isset($row['channel'])) {
							$segment['speaker'] = $row['channel'];
							$segment['channel'] = $row['channel'];
						}
					}

					// add keys values to the array
					$array = [];
					$array['channel'] = $segment['channel'];
					$array['speaker'] = $segment['channel'];
					$array['start'] = $segment['start'];
					$array['end'] = $segment['end'];
					$array['text'] = $segment['text'];

					//combine all text
					$all_text .= $segment['text'];

					// prepare the array
					$all_segments['segments'][] =  $array;
				}
			}

			// generate the combined text from segments
			// $combined_text = implode(' ', array_map(function($segment) {
			//  	return trim($segment['text']);
			// }, $all_segments));

			// sort the segments in ascending order
			usort($all_segments['segments'], function ($a, $b) {
				return $a['start'] <=> $b['start'];
			});

			// set the message to return all text
			if ($output_type == 'text') {
				$this->message = $all_text;
			}

			// set the message to return a json string
			if ($output_type == 'json') {
				$this->message = json_encode(['segments' => $all_segments['segments']], JSON_PRETTY_PRINT);
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

	public function send_request(string $path, string $filename) {

		// Use the curl command line for debuging
		// echo "/usr/bin/curl --request POST \n";
		// echo " --url 'https://api.openai.com/v1/audio/transcriptions' \n";
		// echo " --header 'Authorization: Bearer ".$this->api_key."' \n";
		// echo " --header 'Content-Type: multipart/form-data' \n";
		// echo " --form 'file=@".$path.'/'.$filename."' \n";
		// echo " --form 'model=whisper-1' \n";
		// echo " --form 'response_format=text' \n";
		// echo "\n";

		// start output buffer
		ob_start();
		$out = fopen('php://output', 'w');

		// initialize a curl handle
		$ch = curl_init();

		// set the URL for the request
		curl_setopt($ch, CURLOPT_URL, $this->api_url);

		// set the request method to POST
		curl_setopt($ch, CURLOPT_POST, true);

		// set the request headers
		$headers = [];
		$headers[] = "Authorization: Bearer " . $this->api_key;
		$headers[] = "Content-Type: multipart/form-data";

		// add the request headers
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// prepare the HTTP POST data
		if (file_exists($path.'/'.$filename)) {
			// send the audio from the file system
			$post_data['file'] = new CURLFile($path.'/'.$filename);
		}
		elseif (!empty($this->audio_string) && version_compare(PHP_VERSION, '8.1.0', '<')) {
			// get the temp directory
			$temp_dir = sys_get_temp_dir();

			// save the tremporary file to the temp directory
			file_put_contents($temp_dir.'/'.$filename, $this->audio_string);

			// send the audio from the file system
			$post_data['file'] = new CURLFile($temp_dir.'/'.$filename);

			// remove the temporary file
			unlink($temp_dir.'/'.$filename);
		}
		elseif (!empty($this->audio_string)) {
			// send the audio from as a string requires PHP 8.1 or higher
			$post_data['file'] = new CURLStringFile($this->audio_string, $filename, $this->audio_mime_type);
		}
		else {
			// audio file or string not found
			echo "audio file or string not found";
			return false;
		}

		// prepare and send the http post data
		$post_data['model'] = $this->api_model;
		if ($this->audio_channels == 1) {
			$post_data['response_format'] = 'text';
		}
		if ($this->audio_channels > 1 && $this->diarize) {
			$post_data['response_format'] = 'diarized_json';
			$post_data['chunking_strategy'] = 'auto';
		}
		else {
			$post_data['response_format'] = 'verbose_json';
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

		// return the response as a string instead of outputting it directly
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// set the connection timeout and the overall maximum curl run time
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 4500);

		// follow any "Location: " header the server sends as part of the HTTP header.
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		// automatically set the Referer: field in requests where it follows a Location: redirect.
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);

		// set whether to verify SSL peer
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

		// add verbose for debugging
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $out);

		// run the curl request and transcription message
		$message = curl_exec($ch);

		// show the debug information
		fclose($out);

		// check for errors
		if (curl_errno($ch)) {
			return 'Error: ' . curl_error($ch);
		}

		// close the handle
		unset($ch);

		// return the result from the request
		return $message;
	}

	public function set_model(string $model): void {
		if (array_key_exists($model, $this->get_models())) {
			$this->api_model = $model;
		}
	}

	public function get_models(): array {
		return [
			'tts-1-hd' => 'tts-1-hd'
		];
	}

}
