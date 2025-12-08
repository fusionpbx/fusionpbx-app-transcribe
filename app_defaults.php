<?php

if ($domains_processed == 1) {
	$ffmpeg_path = shell_exec('which ffmpeg');
	if (empty($ffmpeg_path)) {
	    echo "Please install ffmpeg\n";
	    echo "On Debian / Ubuntu Linux install ffmpeg witht his command.\n";
	    echo "apt instal ffmpeg\n";
	}
}

?>
