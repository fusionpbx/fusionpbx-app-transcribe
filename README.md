# fusionpbx-app-transcribe
Speech to text

## Install
```
apt install ffmpeg
cd /var/www/fusionpbx/app
git clone https://github.com/fusionpbx/fusionpbx-app-transcribe.git transcribe
chown -R www-data:www-data /var/www/fusionpbx
php /var/www/fusionpbx/core/upgrade/upgrade.php
```

## Define the required Settings
- Menu -> Default Settings 
- Category: transcribe

## Together AI
- Set `transcribe -> engine -> text` to `togetherai`
- Set `transcribe -> api_key -> text` to your Together AI API key
- Optional: set `transcribe -> api_url -> text` to `https://api.together.xyz/v1/audio/transcriptions`
- `openai/whisper-large-v3` is selected in the provider class
- diarization is controlled in the provider class
