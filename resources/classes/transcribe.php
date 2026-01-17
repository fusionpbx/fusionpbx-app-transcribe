<?php

/**
* transcribe class
*
*/
class transcribe {

	/**
	 * declare private variables
	 */
	private $api_key;

	/** @var string $engine */
	private $engine;

	/** @var template_engine $object */
	private $transcribe_object;

	private $settings;

	public $audio_path;
	public $audio_filename;
	public $audio_string;
	public $audio_mime_type;
	public $audio_format;
	public $audio_model;
	public $audio_voice;
	public $audio_language;
	public $audio_message;

	/**
	 * called when the object is created
	 */
	public function __construct(?settings $settings = null) {
		//make the setting object
		if ($settings === null) {
			$settings = new settings();
		}

		//add the settings object to the class
		$this->settings = $settings;

		//build the setting object and get the recording path
		$this->api_key = $settings->get('transcribe', 'api_key');
		$this->engine = $settings->get('transcribe', 'engine');
	}

	/**
	 * transcribe - speech to text
	 */
	public function transcribe(?string $output_type = 'text') {

		if (!empty($this->engine)) {
			//set the class interface to use the _template suffix
			$classname = 'transcribe_'.$this->engine;

			if (empty($this->audio_path)) {
				$this->audio_path = null;
			}

			//create the object
			$object = new $classname($this->settings);

			//ensure the class has implemented the audio_interface interface
			if ($object instanceof transcribe_interface) {
				if ($object->is_language_enabled() && !empty($this->audio_language)) {
					$object->set_language($this->audio_language);
				}
				if (!empty($this->audio_string)) {
					$object->set_audio_string($this->audio_string);
				}
				if (!empty($this->audio_mime_type)) {
					$object->set_audio_mime_type($this->audio_mime_type);
				}
				$object->set_path($this->audio_path);
				$object->set_filename($this->audio_filename);
				return $object->transcribe($output_type);
			}
			else {
				return '';
			}
		}

	}

	/**
	 * transcribe - convert the transcription into a conversation
	 */
	static function conversation_format($transcription = '', $type = 'html') {
		global $text;

		$text['label-speaker'] ?? 'Speaker';

		//if the transcription is empty return an empty string
		if (empty($transcription)) {
			return '';
		}

		//decode transcription json text
		$transcribe_array = json_decode($transcription, true);

		if ($type == 'html') {
			$html = '';
			$previous_speaker = '';
			$i = 0;
			foreach ($transcribe_array['segments'] as $row) {
				if ($previous_speaker != $row['speaker']) {
					if ($i > 0) { $html .= "</div>\n"; }
					$speaker_class = $row['speaker'] === '0' ? 'message-bubble-em' : 'message-bubble-me';
					$html .= "<div class='message-bubble {$speaker_class}'>";
					$html .= "<div ><strong>" . $text['label-speaker'] . " " . $row['speaker'] . "</strong></div>\n";
				}
				//$html .= "	<span class='time'>".round($row['start'])."</span>";
				$html .= "".escape(trim($row['text']))." ";
				if ($previous_speaker != $row['speaker']) {
					$previous_speaker = $row['speaker'];
				}
				$i++;
			}

			$html .= "</div>\n";
			return $html;
		}

		if ($type == 'text') {
			$text = '';
			$previous_speaker = '';
			$i = 0;
			foreach ($transcription['segments'] as $row) {
				if ($previous_speaker != $row['speaker']) {
					$text .= "\n".$text['label-speaker'] . " " . $row['speaker']."\n";
				}
				$text .= " ".escape(trim($row['text']))." ";
				if ($previous_speaker != $row['speaker']) {
					$previous_speaker = $row['speaker'];
				}
				$i++;
			}
			return $text;
		}
	}
}
