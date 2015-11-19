<?php

namespace Duplitron\Helpers;

use Duplitron\Helpers\Contracts\LoaderContract;

class BasicLoader implements LoaderContract
{

    /**
     * See contract for documentation
     */
    public function loadMedia($media)
    {
        $path = $media->media_path;

        // Make a name for the temporary media file
        $parsed_url = parse_url($path);
        $media_scheme = $parsed_url['scheme'];


        $media_host = $parsed_url['host'];
        $media_user = (array_key_exists('user', $parsed_url))?$parsed_url['user']:"";
        $media_path = $parsed_url['path'];

        $parsed_path = pathinfo($media_path);
        $file_type = (array_key_exists('user', $parsed_path))?$parsed_path['extension']:"mp3";

        $temp_media_file = "media-".$media->id.".".$file_type;
        $temp_media_path = env('FPRINT_STORE').'media_cache/'.$temp_media_file;

        // Do we have a copy of this media cached?
        if(file_exists($temp_media_path))
            return $temp_media_file;


        // Check the scheme
        switch($media_scheme)
        {
            case 'ssh':
                // Run an rsync to get a local copy
                // NOTE: This feels dirty, but so it goes.
                $ssh_command = 'ssh -i '.env('RSYNC_IDENTITY_FILE');
                shell_exec('/usr/bin/rsync -az -e \''.$ssh_command.'\' '.$media_user.'@'.$media_host.':'.$media_path.' '.$temp_media_path);
                break;
            case 'http':
            case 'https':

                // If this is the archive, use their credentials
                // TODO: this should be made somehow more elegant or generalized
                if($media_host == "archive.org")
                {
                    $user = env("ARCHIVE_USER");
                    $sig = env("ARCHIVE_SIG");

                    // Set the cookie and load the file
                    $opts = array(
                        'http'=>array(
                            'header'=>"Cookie: logged-in-user=".$user.";logged-in-sig=".$sig
                        )
                    );
                    $context = stream_context_create($opts);
                    copy($path, $temp_media_path, $context);
                }
                else
                {
                    copy($path, $temp_media_path);
                }
                break;
        }
        // If the media has a listed start / duration, slice it down
        if($media->duration > 0)
        {

            // Specify the configuration for PHPVideoToolkit
            $config = new \PHPVideoToolkit\Config(array(
                'temp_directory' => '/tmp',
                'ffmpeg' => env('FFMPEG_BINARY_PATH'),
                'ffprobe' => env('FFPROBE_BINARY_PATH'),
                'yamdi' => '',
                'qtfaststart' => '',
            ), true);

            // Extract the section we care about
            $start = new \PHPVideoToolkit\Timecode($media->start);
            $end = new \PHPVideoToolkit\Timecode($media->start + $media->duration);
            $audio  = new \PHPVideoToolkit\Audio($temp_media_path, null, null, false);
            $command = $audio->extractSegment($start, $end);

            // We need to save as a separate file then overwrite
            $trimmed_media_path = $temp_media_path."trimmed.mp3";
            $process = $command->save($trimmed_media_path, null, true);
            rename($trimmed_media_path, $temp_media_path);
        }

        return $temp_media_file;
    }
}
?>

