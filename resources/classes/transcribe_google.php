<?php

/**
* transcribe_google class
*
*/
class transcribe_google implements transcribe_interface {

	/**
	 * declare private variables
	 */
	private $api_key;
	private $api_url;
	private $language;
	private $alternate_language;
	private $application_credentials;
	private $path;
	private $filename;
	private $audio_string;
	private $audio_mime_type;
	private $format;
	private $voice;
	private $message;
	private $model;

	/**
	 * called when the object is created
	 */
	public function __construct($settings) {

		// build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key');
		$this->api_url = $settings->get('transcribe', 'api_url');
		$this->language = $settings->get('transcribe', 'language');
		$this->alternate_language = $settings->get('transcribe', 'alternate_language');
		$this->application_credentials = $settings->get('transcribe', 'application_credentials');

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
	 * transcribe - speech to text
	 */
	public function transcribe() : string {

		if (!isset($this->language) && empty($this->language)) {
			$this->language = 'en-US';
		}
		if (!isset($this->alternate_language) && empty($this->alternate_language)) {
			$this->alternate_language = 'es-US';
		}

		// get the content type
		if (file_exists($this->path.'/'.$this->filename)) {
			$path_array = pathinfo($this->path.'/'.$this->filename);
			if ($path_array['extension'] == "mp3") {
				$content_type = 'audio/mp3';
			}
			if ($path_array['extension'] == "wav") {
				$content_type = 'audio/wav';
			}
		}
		elseif (!empty($this->audio_string)) {
			$content_type = $this->audio_mime_type;
		}

		// start output buffer
		ob_start();
		$out = fopen('php://output', 'w');

		// version 1
		if (trim($this->api_url) == 'https://speech.googleapis.com/v1p1beta1/speech') {
			if (isset($this->api_key) && $this->api_key != '') {

				if (file_exists($this->path.'/'.$this->filename)) {
					//file has been found
				}
				elseif (!empty($this->audio_string)) {
					//if this is empty then use the temp directory
					if (empty($this->path)) {
						$this->path = sys_get_temp_dir();
					}

					//save the audio string on the file system
					file_put_contents($this->path.'/'.$this->filename, $this->audio_string);
				}
				else {
					//audio file or string not found
					return false;
				}

				//get the length of the audio file
				$audio_length = (float)system("soxi -D ".$this->path."/".$this->filename);

				// Convert audio file to FLAC format
				$flac_file = $this->path . '/' . $this->filename . '.flac';
				$command = "sox ".$this->path."/".$this->filename." ".$flac_file;
				if ($audio_length > 59) { $command .= " trim 0 00:59"; }
				exec($command);

				// Base64 encode FLAC file
				$flac_base64 = base64_encode(file_get_contents($flac_file));

				// Prepare JSON data
				$data = [
					'config' => [
						'languageCode' => $this->language,
						'enableWordTimeOffsets' => false,
						'enableAutomaticPunctuation' => true,
						'alternativeLanguageCodes' => $this->alternate_language
					],
					'audio' => [
						'content' => $flac_base64
					]
				];
				$json_data = json_encode($data);

				// initialize a curl handle
				$ch = curl_init();

				// set the URL for the request
				curl_setopt($ch, CURLOPT_URL, $this->api_url . ':recognize?key=' . $this->api_key);

				// set the request method to POST
				curl_setopt($ch, CURLOPT_POST, true);

				// send the HTTP headers
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

				// send the HTTP post
				curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

				// return the response as a string instead of outputting it directly
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				// run the curl request and transcription message
				$response = curl_exec($ch);

				// check for errors
				if (curl_errno($ch)) {
					echo 'Error: ' . curl_error($ch);
					exit;
				}

				// close the handle
				curl_close($ch);

				// Remove temporary FLAC file
				unlink($flac_file);
			}
		}

		// version 2
		if (substr($this->api_url, 0, 32) == 'https://speech.googleapis.com/v2') {
			if (!empty($this->application_credentials)) {
				putenv("GOOGLE_APPLICATION_CREDENTIALS=".$this->application_credentials);
			}

			// Base64 encode the audio
			if (file_exists($this->path.'/'.$this->filename)) {
				//file has been found
				$audio_base64 = base64_encode(file_get_contents($this->file_path . '/' . $this->file_name));
			}
			elseif (!empty($this->audio_string)) {
				$audio_base64 = base64_encode($this->audio_string);
			}
			else {
				//audio file or string not found
				return false;
			}

			// Prepare JSON data
			$data = [
				'config' => [
					'auto_decoding_config' => [],
					'language_codes' => [$this->language],
					'model' => 'long'
				],
				'content' => $audio_base64
			];
			$json_data = json_encode($data);

			// initialize a curl handle
			$ch = curl_init();

			// set the URL for the request
			curl_setopt($ch, CURLOPT_URL, $this->api_url);

			// set the request method to POST
			curl_setopt($ch, CURLOPT_POST, 1);

			// send the HTTP headers
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . shell_exec('gcloud auth application-default print-access-token')]);

			// send the HTTP post
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

			// return the response as a string instead of outputting it directly
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			//add verbose for debugging
			curl_setopt($ch, CURLOPT_VERBOSE, true);
			curl_setopt($ch, CURLOPT_STDERR, $out);

			// run the curl request and transcription message
			$response = curl_exec($ch);

			// check for errors
			if (curl_errno($ch)) {
				echo 'Error: ' . curl_error($ch);
				exit;
			}

			// close the handle
			curl_close($ch);
		}

		// validate the json
		if (!empty($response)) {
			$ob = json_decode($response);
			if($ob === null) {
				echo "invalid json\n";
				return false;
			}

			$json = json_decode($response, true);
			// echo "json; ".$json."\n";
			$message = '';
			foreach($json['results'] as $row) {
				$this->message .= $row['alternatives'][0]['transcript'];
			}
		}

		// show the debug information
		fclose($out);
		// $this->debug = ob_get_clean();

		// return the transcription
		if (empty($this->message)) {
			return '';
		}
		else {
			return $this->message;
		}

	}

}
