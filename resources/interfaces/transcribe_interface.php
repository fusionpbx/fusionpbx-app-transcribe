<?php

/**
* transcribe_interface template class
*
*/
interface transcribe_interface {
	public function set_path(string $audio_path);
	public function set_filename(string $audio_filename);
	public function transcribe() : string;
	public function set_language(string $audio_language);
	public function get_languages() : array;
	public function is_language_enabled(): bool;
	public function set_audio_string(string $audio_string);
	public function set_audio_mime_type(string $audio_mime_type);
}
