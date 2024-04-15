<?php

/**
 * transcribe class
 *
 * @method null download
 */
if (!class_exists('transcribe')) {
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
		public $audio_format;
		public $audio_model;
		public $audio_voice;
		public $audio_language;
		public $audio_message;

		/**
		 * called when the object is created
		 */
		public function __construct(settings $settings = null) {
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
		public function transcribe() : string {

			if (!empty($this->engine)) {
				//set the class interface to use the _template suffix
				$classname = 'transcribe_'.$this->engine;

				//create the object
				$object = new $classname($this->settings);

				//ensure the class has implemented the audio_interface interface
				if ($object instanceof transcribe_interface) {
					if ($object->is_language_enabled() && !empty($this->audio_language)) {
						$object->set_language($this->audio_language);
					}
					$object->set_path($this->audio_path);
					$object->set_filename($this->audio_filename);
					return $object->transcribe();
				}
				else {
					return '';
				}
			}

		}

	}
}

?>