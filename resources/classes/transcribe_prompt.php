<?php

/**
 * transcribe_prompt class
 *
 * Passes a speech-to-text transcription through a language model using a
 * per-voicemail prompt template. The template may contain ${transcription}
 * as a placeholder; when absent the transcription is appended after the template.
 */
class transcribe_prompt {

	private $settings;

	public function __construct(?settings $settings = null) {
		if ($settings === null) {
			$settings = new settings();
		}
		$this->settings = $settings;
	}

	/**
	 * process
	 *
	 * Combines the prompt template with the transcription and sends it to the
	 * configured language model. Returns the model's response or an empty string
	 * when the language model is not configured.
	 *
	 * @param string $transcription  The raw speech-to-text output.
	 * @param string $prompt_template  The instruction prompt saved on the voicemail.
	 * @return string
	 */
	public function process(string $transcription, string $prompt_template): string {
		if (empty($transcription) || empty($prompt_template)) {
			return '';
		}

		// Allow the template to embed the transcription at a specific position;
		// fall back to appending it after the template.
		if (strpos($prompt_template, '${transcription}') !== false) {
			$combined = str_replace('${transcription}', $transcription, $prompt_template);
		} else {
			$combined = $prompt_template . "\n\n" . $transcription;
		}

		$lm = new language_model();
		$model = $this->settings->get('language_model', 'api_model', '');
		$result = $lm->request($model, ['prompt' => $combined]);

		return is_string($result) ? trim($result) : '';
	}
}
