<?php

/**
* transcribe_openai class
*
*/
class transcribe_openai implements transcribe_interface {

	/**
	 * declare private variables
	 */
	private $api_key;
	private $api_url;
	public  $api_model;
	private $path;
	private $filename;
	private $audio_string;
	private $audio_mime_type;
	private $format;
	private $voice;
	private $message;
	private $model;
	private $language;
	private $translate;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {

		//build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key', '');
		$this->api_url = $settings->get('transcribe', 'api_url', '');
		$this->api_model = $settings->get('transcribe', 'api_model', 'whisper-1');

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

		// Use the curl command line for debuging
		//echo "/usr/bin/curl --request POST ";
		//echo " --url 'https://api.openai.com/v1/audio/transcriptions' ";
		//echo " --header 'Authorization: Bearer ".$this->api_key."' ";
		//echo " --header 'Content-Type: multipart/form-data' ";
		//echo " --form 'file=@".$this->path.'/'.$this->filename."' ";
		//echo " --form 'model=whisper-1' ";
		//echo " --form 'response_format=text' ";
		//echo "\n";

		//start output buffer
		ob_start();
		$out = fopen('php://output', 'w');

		// initialize a curl handle
		$ch = curl_init();

		// set the api_url if not already set
		if (empty($this->api_url)) {
			$this->api_url = 'https://api.openai.com/v1/audio/transcriptions';
		}

		// set the URL for the request
		curl_setopt($ch, CURLOPT_URL, $this->api_url);

		// set the request method to POST
		curl_setopt($ch, CURLOPT_POST, true);

		// set the request headers
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer '.$this->api_key,
			'Content-Type: multipart/form-data'
		));

		// prepare the HTTP POST data
		if (file_exists($this->path.'/'.$this->filename)) {
			//send the audio from the file system
			$post_data['file'] = new CURLFile($this->path.'/'.$this->filename);
		}
		elseif (!empty($this->audio_string)) {
			//send the audio from as a string
			$post_data['file'] = new CURLStringFile($this->audio_string, $this->filename, $this->audio_mime_type);
		}
		else {
			//audio file or string not found
			return false;
		}

		$post_data['model'] = $this->api_model;
		$post_data['response_format'] = 'text';
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
		$this->message = curl_exec($ch);

		// show the debug information
		fclose($out);

		// check for errors
		if (curl_errno($ch)) {
			echo 'Error: ' . curl_error($ch);
			exit;
		}

		// close the handle
		curl_close($ch);

		// return the transcription
		if (empty($this->message)) {
			return '';
		}
		else {
			return trim($this->message);
		}
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
