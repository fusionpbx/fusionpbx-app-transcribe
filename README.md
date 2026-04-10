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
