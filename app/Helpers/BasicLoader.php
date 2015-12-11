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
        // Set up the basics
        $return_files = array(
            'full' => '',
            'chunks' => array()
        );
        $path = $media->media_path;

        // Extract relevant pieces of the specified media path
        $parsed_url = parse_url($path);
        $media_scheme = $parsed_url['scheme'];

        $media_host = $parsed_url['host'];
        $media_user = (array_key_exists('user', $parsed_url))?$parsed_url['user']:"";
        $media_path = $parsed_url['path'];

        $parsed_path = pathinfo($media_path);
        $file_type = (array_key_exists('extension', $parsed_path))?$parsed_path['extension']:"mp3";

        // Set up the pieces of the destination file names
        $temp_media_base_file = "media-".$media->id;
        $temp_media_file = $temp_media_base_file.".".$file_type;
        $temp_media_base_path = env('FPRINT_STORE').'media_cache/';
        $temp_media_path = $temp_media_base_path.$temp_media_file;

        // Save the media path
        $return_files['full'] = $temp_media_file;

        // Do we have a copy of this media cached?
        if(file_exists($temp_media_path))
        {
            // Load the cached chunks as well
            $chunk_glob_path = $temp_media_base_path.$temp_media_base_file."_*.".$file_type;
            // Return the cached values
            $chunk_paths = glob($chunk_glob_path);

            foreach($chunk_paths as $chunk_path)
                $return_files['chunks'][] = basename($chunk_path);

            return $return_files;
        }

        // Load the file (based on the scheme)
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

        // Set up PHPVideoToolkit (for use in the next steps)
        $config = new \PHPVideoToolkit\Config(array(
            'temp_directory' => '/tmp',
            'ffmpeg' => env('FFMPEG_BINARY_PATH'),
            'ffprobe' => env('FFPROBE_BINARY_PATH'),
            'yamdi' => '',
            'qtfaststart' => '',
        ), true);

        // Slice down the media if it has a listed start / duration
        if($media->duration > 0)
        {
            // Extract the section we care about
            $start = new \PHPVideoToolkit\Timecode($media->start);
            $end = new \PHPVideoToolkit\Timecode($media->start + $media->duration);

            // Process differently based on the file type
            switch($file_type)
            {
                case 'mp3':
                    $audio  = new \PHPVideoToolkit\Audio($temp_media_path, null, null, false);
                    $command = $audio->extractSegment($start, $end);
                    break;
                case 'mp4':
                    $video  = new \PHPVideoToolkit\Video($temp_media_path, null, null, false);
                    $command = $video->extractSegment($start, $end);
                    break;
            }
            // We need to save as a separate file then overwrite
            $trimmed_media_path = $temp_media_path."trimmed.".$file_type;
            $process = $command->save($trimmed_media_path, null, true);
            rename($trimmed_media_path, $temp_media_path);
        }

        // Chunk the media file into smaller pieces based on env settings

        // Calculate the number of chunks needed
        $parser = new \PHPVideoToolkit\MediaParser();
        $media_data = $parser->getFileInformation($temp_media_path);
        $slice_duration = env('FPRINT_CHUNK_LENGTH');
        $duration = $media_data['duration']->total_seconds;

        // Perform the actual slicing
        if($duration > env('FPRINT_CHUNK_LENGTH'))
        {
            switch($file_type)
            {
                case 'mp3':
                    $audio  = new \PHPVideoToolkit\Audio($temp_media_path, null, null, false);
                    $slices = $audio->split(env('FPRINT_CHUNK_LENGTH'));
                    break;
                case 'mp4':
                    $video  = new \PHPVideoToolkit\Video($temp_media_path, null, null, false);
                    $slices = $video->split(env('FPRINT_CHUNK_LENGTH'));
                    break;
            }
            $process = $slices->save($temp_media_base_path.$temp_media_base_file."_%index.".$file_type);
            $output = $process->getOutput();

            // Get the filenames
            foreach($output as $chunk)
            {
                $return_files['chunks'][] = basename($chunk->getMediaPath());
            }
        }
        else
        {
            $copy_path = str_replace(".".$file_type, "_0.".$file_type, $temp_media_path);
            copy($temp_media_path, $copy_path);
            $return_files['chunks'][] = basename($copy_path);
        }

        return $return_files;
    }
}
?>

