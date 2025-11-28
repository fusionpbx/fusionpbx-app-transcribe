<?php

if ($domains_processed == 1) {
	$ffmpeg_path = shell_exec('which ffmpeg');
	if (empty($ffmpeg_path)) {
	    echo "Please install ffmpeg\n";
	    echo "On Debian / Ubuntu Linux install ffmpeg with this command.\n";
	    echo "apt install ffmpeg\n";
	}
}

?>
