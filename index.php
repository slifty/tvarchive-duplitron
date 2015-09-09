<?PHP 
	require __DIR__ . '/vendor/autoload.php'; #Autoload composer files
	require __DIR__ . '/config/phpVideoToolkit.php'; # Configure phpVideoToolkit

	require __DIR__ . '/app/controllers/AudioController.php'; # Configure phpVideoToolkit

	// $ffmpeg = new PHPVideoToolkit\FfmpegParser($config);
	// $is_available = $ffmpeg->isAvailable(); // returns boolean
	// $ffmpeg_version = $ffmpeg->getVersion(); // outputs something like - array('version'=>1.0, 'build'=>null)

	// $parser = new PHPVideoToolkit\MediaParser();
	// $data = $parser->getFileInformation('example.mp3');
	// $video  = new PHPVideoToolkit\Audio('example.mp3', null, null, false);
	// $start = new PHPVideoToolkit\Timecode('00:01:22.0', PHPVideoToolkit\Timecode::INPUT_FORMAT_TIMECODE);
	// $duration = new PHPVideoToolkit\Timecode('00:01:32.0', PHPVideoToolkit\Timecode::INPUT_FORMAT_TIMECODE);
	// $command = $video->extractSegment($start, $duration);

	// $process = $command->save('./output/example2.mp3', null, true);

	// print_r($process->getOutput());

	$audioController = new AudioController();
	$audioController->addMedia('example.mp3');

?>