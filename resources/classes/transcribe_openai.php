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
	private $format;
	private $voice;
	private $message;
	private $model;
	private $language;
	private $translate;
	private $temp_dir;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {

		//build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key', '');
		$this->api_url = $settings->get('transcribe', 'api_url', '');
		$this->api_model = $settings->get('transcribe', 'api_model', 'whisper-1');

		//get the temp directory
		$this->temp_dir = sys_get_temp_dir();

		//set the audio defaults
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

	public function set_voice(string $audio_voice) {
		$this->voice = $audio_voice;
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
		//return the whether engine is handles languages
		return false;
	}

	public function is_translate_enabled() : bool {
		//return the whether engine is able to translate
		return false;
	}

	public function get_voices() : array {
		$voices = array(
			"alloy" => "alloy",
			"echo" => "echo",
			"fable" => "fable",
			"nova" => "nova",
			"onyx" => "onyx",
			"shimmer" => "shimmer"
		);

		//return the languages array
		return $voices;
	}

	public function get_languages() : array {
		//create the languages array
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

		//return the languages array
		return $languages;
	}

	/**
	 * transcribe - speech to text
	 * @return string transcibed messages returned or empty for failure
	 */
	public function transcribe() : string {

		// set the api_url if not already set
		if (empty($this->api_url)) {
			$this->api_url = 'https://api.openai.com/v1/audio/transcriptions';
		}

		//get the number of audio channels
		$command = "ffprobe -v error -select_streams a:0 -show_entries stream=channels -of default=noprint_wrappers=1:nokey=1 ".$this->path.'/'.$this->filename;
		$output = shell_exec($command);
		if (!empty($output)) {
			$this->audio_channels = trim($output);
		}

		//get the duration of the audio file
		$command = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ".$this->path.'/'.$this->filename;
		$output = shell_exec($command);
		if (!empty($output)) {
			$this->audio_duration = trim($output);
		}

		//when there is more than one audio channel change the api model to diarize the recording
		if ($this->audio_channels > 1) {
			$this->api_model = 'gpt-4o-transcribe-diarize';
		}

		//define the array
		$message = [];

		//segment the audio if needed and then send a request for each segment
		$segment_length = 180; // 1200 = 20 minutes in seconds
		$total_segments = ceil($this->audio_duration / $segment_length);

		//process each segment and return the result in the message array
		for ($i = 0; $i < $total_segments; $i++) {
			//set the start time
			$start_time = $i * $segment_length;

			//get the path i the filename
			$path_parts = pathinfo($this->filename);

			//get the file extension
			$file_extension = $path_parts['extension'];

			//get the base file name without the extension
			$file_base_name = $path_parts['filename'];

			//set the segement filename
			$output_filename = $file_base_name . ".segment." . ($i + 1) . "." . $file_extension;

			//save audio into segments
			$command = "ffmpeg -y -ss {$start_time} -t {$segment_length} -i {$this->path}/{$this->filename} -c copy {$this->temp_dir}/{$output_filename}";
			shell_exec($command);

			//call the send_request function with the filename of each segment
			$message[] = $this->send_request($this->temp_dir, $output_filename);

			//remove the segmented file name
			unlink($this->temp_dir.'/'.$output_filename);
		}

		//process the results if there are multiple results then combine them
		if (empty($message)) {
			$this->message = '';
		}
		elseif (empty($transcript['segments'])) {
			$this->message = $message[0];
		}
		else {
			//set default values
			$all_segments = [];
			$total_tokens = 0;
			$input_tokens = 0;
			$input_text_tokens = 0;
			$input_audio_tokens = 0;
			$output_tokens = 0;

			foreach ($message as $json) {
				//decode the json to the transcript array
				$transcript = json_decode($json, true);

				//merge segments
				foreach ($transcript['segments'] as $segment) {
					array_push($all_segments, $segment);
				}

				//sum up the usage data
				$total_tokens += $transcript['usage']['total_tokens'];
				$input_tokens += $transcript['usage']['input_tokens'];
				$input_text_tokens += $transcript['usage']['input_token_details']['text_tokens'];
				$input_audio_tokens += $transcript['usage']['input_token_details']['audio_tokens'];
				$output_tokens += $transcript['usage']['output_tokens'];
			}

			//re-encode into json
			$combined_transcript = [
				'segments' => $all_segments,
				'usage' => [
					'type' => 'tokens',
					'total_tokens' => $total_tokens,
					'input_tokens' => $input_tokens,
					'input_token_details' => [
						'text_tokens' => $input_text_tokens,
						'audio_tokens' => $input_audio_tokens
					],
					'output_tokens' => $output_tokens
				]
			];

			//generate the combined text from segments
			$combined_text = implode(' ', array_map(function($segment) {
				return trim($segment['text']);
			}, $all_segments));

			$final_output = [
				'text' => $combined_text,
				'segments' => $combined_transcript['segments'],
				'usage' => $combined_transcript['usage']
			];

			//encode the array into a json string
			$this->message = json_encode($final_output, JSON_PRETTY_PRINT);
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

		//start output buffer
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

		//add the request headers
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// prepare the HTTP POST data
		if (file_exists($path.'/'.$filename)) {
			//send the audio from the file system
			$post_data['file'] = new CURLFile($path.'/'.$filename);
		}
		elseif (!empty($this->audio_string) && version_compare(PHP_VERSION, '8.1.0', '<')) {
			//save the tremporary file to the temp directory
			file_put_contents($this->temp_dir.'/'.$filename, $this->audio_string);

			//send the audio from the file system
			$post_data['file'] = new CURLFile($this->temp_dir.'/'.$filename);

			//remove the temporary file
			unlink($this->temp_dir.'/'.$filename);
		}
		elseif (!empty($this->audio_string)) {
			//send the audio from as a string requires PHP 8.1 or higher
			$post_data['file'] = new CURLStringFile($this->audio_string, $filename, $this->audio_mime_type);
		}
		else {
			//audio file or string not found
			echo "audio file or string not found";
			return false;
		}

		// add additional post data
		$post_data['model'] = $this->api_model;
		if ($this->audio_channels == 1) {
			$post_data['response_format'] = 'text';
		}
		else {
			$post_data['response_format'] = 'diarized_json';
			$post_data['chunking_strategy'] = 'auto';
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

		// return the response as a string instead of outputting it directly
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// set the connection timeout and the overall maximum curl run time
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);

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
			echo 'Error: ' . curl_error($ch);
			exit;
		}

		// close the handle
		curl_close($ch);

		//return the result from the request
		return $message;
	}

	public function get_models(): array {
		return [
			'tts-1-hd' => 'tts-1-hd'
		];
	}

}
