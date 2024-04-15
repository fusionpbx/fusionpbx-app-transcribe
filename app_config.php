<?php

	//application details
		$apps[$x]['name'] = 'Transcribe';
		$apps[$x]['uuid'] = '8da245ba-e559-4094-9862-4bfaf5cec713';
		$apps[$x]['category'] = 'API';
		$apps[$x]['subcategory'] = '';
		$apps[$x]['version'] = '1.0';
		$apps[$x]['license'] = 'Mozilla Public License 1.1';
		$apps[$x]['url'] = 'http://www.fusionpbx.com';
		$apps[$x]['description']['en-us'] = 'Speech to Text';

	//default settings
		$y=0;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "bc054920-5877-4695-9885-9c9009a7713c";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "transcribe";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Speech to Text API enabled.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "72b9feeb-b21c-4dad-ad26-dde86955d87b";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "transcribe";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "engine";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "openai";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Speech to Text API engine.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "7883f9fc-9259-4f9b-a73d-532f44db2a28";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "transcribe";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "api_key";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Speech to Text API key.";
		$y++;

?>