<?php


 /**
 * transcribe_watson class
 *
 * @method null download
 */
if (!class_exists('transcribe_watson')) {
	class transcribe_watson implements transcribe_interface {

		/**
		 * declare private variables
		 */
		private $api_key;
		private $api_url;
		private $language;
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

			//build the setting object and get the recording path
			$this->api_key = $settings->get('transcribe', 'api_key');
			$this->api_url = $settings->get('transcribe', 'api_url');
			$this->language = $settings->get('transcribe', 'language');

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
		 */
		public function transcribe() : string {

			//get the content type
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

			//start output buffer
			//ob_start();

			//open standard output
			$out = fopen('php://output', 'w');

			// initialize a curl handle
			$ch = curl_init();

			// set the URL for the request
			curl_setopt($ch, CURLOPT_URL, $this->api_url);

			// set the request method to POST
			curl_setopt($ch, CURLOPT_POST, 1);

			//return the response as a string instead of outputting it directly
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			//send the autentication
			curl_setopt($ch, CURLOPT_USERPWD, 'apikey' . ':' . $this->api_key);

			//set the authentication type
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

			//send the content type
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: '.$content_type]);

			//send the file as binary
			curl_setopt($ch, CURLOPT_BINARYTRANSFER,TRUE);

			//prepare to send the file or audio
			if (file_exists($this->path.'/'.$this->filename)) {
				//send the audio from the file system
				curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($this->path.'/'.$this->filename));
			}
			elseif (!empty($this->audio_string)) {
				//send the audio from as a string
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->audio_string);
			}
			else {
				//audio file or string not found
				return false;
			}

			//set the connection timeout and the overall maximum curl run time
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
			curl_setopt($ch, CURLOPT_TIMEOUT, 300);

			//To follow any "Location: " header that the server sends as part of the HTTP header.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

			//To automatically set the Referer: field in requests where it follows a Location: redirect.
			curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);

			//Set whether to verify SSL peer
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

			//hide the headers when set to 0
			curl_setopt($ch, CURLOPT_HEADER, 0);

			//add verbose for debugging
			curl_setopt($ch, CURLOPT_VERBOSE, true);
			curl_setopt($ch, CURLOPT_STDERR, $out);

			//show the debug information
			fclose($out);

			//save the buffer
			//$this->debug = ob_get_clean();

			// run the curl request and transcription message
			$json_response = curl_exec($ch);

			// check for errors
			if (curl_errno($ch)) {
				echo 'Error: ' . curl_error($ch);
				exit;
			}

			// close the handle
			curl_close($ch);

			//get the content of the message
			$json = json_decode($json_response, true);

			//validate the json
			if ($json === null) {
				return 'invalid json';
			}

			//find the data
			foreach($json['results'] as $row) {
				$this->message .= $row['alternatives'][0]['transcript'];
			}

			//clean the data
			$this->message = str_replace("%HESITATION", " ", trim($this->message));
			$this->message = ucfirst($this->message);

			// return the transcription
			if (empty($this->message)) {
				return '';
			}
			else {
				return $this->message;
			}

		}

	}
}

?>
