<?php

namespace Duplitron\Helpers;

use Duplitron\Helpers\Contracts\LoaderContract;

class BasicLoader implements LoaderContract
{

    /**
     * See contract for documentation
     */
    public function loadFingerprints($media)
    {
        // TODO: do a better job of checking if the fingerprints exist already

        // Check to be sure there is even a path to load
        if(!$media->afpt_path)
            return null;

        // Set up the basics
        $return_files = array(
            'full' => '',
            'chunks' => array()
        );
        $source_afpt_path = $media->afpt_path;

        // Extract relevant pieces of the specified media path
        $parsed_url = parse_url($source_afpt_path);
        $afpt_scheme = $parsed_url['scheme'];

        $afpt_host = $parsed_url['host'];
        $afpt_user = (array_key_exists('user', $parsed_url))?$parsed_url['user']:"";
        $afpt_path = $parsed_url['path'];

        $parsed_path = pathinfo($afpt_path);
        $file_type = (array_key_exists('extension', $parsed_path))?$parsed_path['extension']:"zip";

        // TODO: possibly support non-zip... maybe.
        if($file_type != "zip")
            return null;

        // Download the zip file
        $destination_file_base = $this->getFileBase($media);
        $destination_directory = env('FPRINT_STORE').'afpt_cache/';
        $temp_afpt_archive_file = $this->loadFile($source_afpt_path, $destination_directory, $destination_file_base, $file_type);

        // Were we able to copy the archive file?
        if($temp_afpt_archive_file == "")
            return null;

        $detination_afpt_archive_path = $destination_directory.$temp_afpt_archive_file;
        $detination_afpt_extraction_path = $destination_directory.$destination_file_base.'/';

        // TODO: Extract the zip file, route parts to the right place, populate return array
        $zip = new \ZipArchive();
        if ($zip->open($detination_afpt_archive_path) === true) {
            $zip->extractTo($detination_afpt_extraction_path);
            $zip->close();

            // Get a list of files in the new archive
            $archive_files = scandir($detination_afpt_extraction_path);

            // TODO: relying on file name structure feels bad.

            // Separate the files
            // 1) One file will not start with an underscore, that's the main file
            // 2) Some files will start with an underscore, those are the parts

            foreach($archive_files as $archive_file)
            {
                // Skip over '.' and '..'
                // And also skip over files that aren't density 20
                // TODO: we may want a safer way to pick apart the 20 density from 100 density
                // TODO: in general we want to handle both 20 and 100


                if(substr($archive_file, -11) != '_tva20.afpt')
                    continue;

                if($archive_file[0] == '_')
                {
                    // This is a chunk file, figure out the index
                    $chunk_index = (int)substr($archive_file, 6, 2);
                    $chunk_file = $destination_file_base."_".$chunk_index.".afpt";
                    $chunk_file_path = $destination_directory.$chunk_file;
                    $archive_file_path = $detination_afpt_extraction_path.$archive_file;

                    // Move the file
                    copy($archive_file_path, $chunk_file_path);

                    // Delete the file
                    unlink($archive_file_path);

                    // Register it
                    $return_files['chunks'][] = $chunk_file;
                }
                else
                {
                    // This is the core afpt file
                    $fingerprint_file = $destination_file_base.'.afpt';
                    $fingerprint_file_path = $destination_directory.$fingerprint_file;
                    $archive_file_path = $detination_afpt_extraction_path.$archive_file;

                    // Move the file
                    copy($archive_file_path, $fingerprint_file_path);

                    // Delete the file
                    unlink($archive_file_path);

                    // Register it
                    $return_files['full'] = $fingerprint_file;
                }
            }

            // Were there any sliced parts?
            if(sizeof($return_files['chunks']) == 0)
            {
                    $chunk_file = $destination_file_base."_0.afpt";
                    $chunk_file_path = $destination_directory.$chunk_file;
                    copy($destination_directory.$return_files['full'], $chunk_file_path);
                    $return_files['chunks'][] = $chunk_file;
            }


            // Delete the zip file and the empty unzipped folder
            unlink($detination_afpt_archive_path);

            // Delete the empty unzipped folder
            rmdir($detination_afpt_extraction_path);

        } else {
            // Failed to open the zip file
            throw new \Exception("Unable to extract the fingerprint archive.");
        }

        return $return_files;
    }

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
        $source_media_path = $media->media_path;

        // Extract relevant pieces of the specified media path
        $parsed_url = parse_url($source_media_path);
        $media_scheme = $parsed_url['scheme'];

        $media_host = $parsed_url['host'];
        $media_user = (array_key_exists('user', $parsed_url))?$parsed_url['user']:"";
        $media_path = $parsed_url['path'];

        $parsed_path = pathinfo($media_path);
        $file_type = (array_key_exists('extension', $parsed_path))?$parsed_path['extension']:"mp3";

        // Set up the pieces of the destination file names
        $destination_file_base = $this->getFileBase($media);
        $destination_directory = env('FPRINT_STORE').'media_cache/';
        $temp_media_file = $destination_file_base.".".$file_type;
        $temp_media_path = $destination_directory.$temp_media_file;

        // If the file doesn't exist, download it
        if(file_exists($temp_media_path))
        {
            $return_files['full'] = $temp_media_file;
        }
        else
        {
            $temp_media_file = $this->loadFile($source_media_path, $destination_directory, $destination_file_base, $file_type);

            if($temp_media_file == "")
                throw new \Exception("Could not download the media: ".$source_media_path);

            // Save the media path
            $return_files['full'] = $temp_media_file;

            // Set up PHPVideoToolkit (for use in the next steps)
            $config = new \PHPVideoToolkit\Config(array(
                'temp_directory' => '/tmp',
                'ffmpeg' => env('FFMPEG_BINARY_PATH'),
                'ffprobe' => env('FFPROBE_BINARY_PATH'),
                'yamdi' => '',
                'qtfaststart' => '',
            ), true);

            // Slice down the new media file if it has a listed start / duration
            if($media->duration > 0)
            {
                // Extract the section we care about
                $start = new \PHPVideoToolkit\Timecode($media->start);
                $end = new \PHPVideoToolkit\Timecode($media->start + $media->duration);

                // Process differently based on the file type
                switch($file_type)
                {
                    case 'mp3':
                    case 'wav':
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
        }

        // Check if the file parts already exist
        $chunk_glob_path = $destination_directory.$destination_file_base."_*.".$file_type;
        $chunk_paths = glob($chunk_glob_path);

        // If the chunks already exist use them, otherwise make them.
        if(sizeof($chunk_paths) > 0)
        {
            foreach($chunk_paths as $chunk_path)
                $return_files['chunks'][] = basename($chunk_path);
        }
        else
        {

            // Calculate the number of chunks needed
            $parser = new \PHPVideoToolkit\MediaParser();
            $media_data = $parser->getFileInformation($temp_media_path);
            $slice_duration = env('FPRINT_CHUNK_LENGTH');
            $duration = $media_data['duration']->total_seconds;

            // Perform the actual slicing
            // TODO: there is an issue where slices that are too small might cause errors.
            // We will need to add logic to detect those and merge them into the previous slice
            if($duration > env('FPRINT_CHUNK_LENGTH') + 60) // The + 60 is to try to prevent slices less than 1 minute...
            {
                switch($file_type)
                {
                    case 'mp3':
                    case 'wav':
                        $audio  = new \PHPVideoToolkit\Audio($temp_media_path, null, null, false);
                        $slices = $audio->split(env('FPRINT_CHUNK_LENGTH'));
                        break;
                    case 'mp4':
                        $video  = new \PHPVideoToolkit\Video($temp_media_path, null, null, false);
                        $slices = $video->split(env('FPRINT_CHUNK_LENGTH'));
                        break;
                }
                $process = $slices->save($destination_directory.$destination_file_base."_%index.".$file_type);
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
        }
        return $return_files;
    }

    /**
     * See contract for documentation
     */
    // TODO: make this thread safe, as well as all the other loader methods.
    public function removeCachedFiles($media) {

        // Set up the cache directories
        $media_cache = env('FPRINT_STORE').'media_cache/';
        $afpt_cache = env('FPRINT_STORE').'afpt_cache/';
        $file_base = $this->getFileBase($media);
        $remove_paths = [];

        // Find media files
        $media_glob_path = $media_cache.$file_base.".*";
        $remove_paths = array_merge($remove_paths, glob($media_glob_path));
        $media_chunks_glob_path = $media_cache.$file_base."_*.*";
        $remove_paths = array_merge($remove_paths, glob($media_chunks_glob_path));

        // Find fingerprint files
        $media_glob_path = $afpt_cache.$file_base.".afpt";
        $remove_paths = array_merge($remove_paths, glob($media_glob_path));
        $media_chunks_glob_path = $afpt_cache.$file_base."_*.afpt";
        $remove_paths = array_merge($remove_paths, glob($media_chunks_glob_path));

        // Go through the paths and remove them
        foreach($remove_paths as $remove_path)
        {
            unlink($remove_path);
        }
        return $remove_paths;
    }

    /**
     * Given a media file, returns the basename of the file being used for it's various cached pieces
     * @param  Object $media the media being saved
     * @return String        the base filename to be used
     */
    private function getFileBase($media) {
        return "media-".$media->id;
    }

    /**
     * Loads a file into a specified directory, returns the file name that was created
     * @param  string $source_path           The full path to the file being loaded
     * @param  string $destination_directory The directory the file should be stored in
     * @param  string $destination_file_base The base name (not including extension) of the destination
     * @return string                        The name of the final file
     */
    private function loadFile($source_url, $destination_directory, $destination_file_base, $file_type)
    {
        // Extract relevant pieces of the specified file path
        $parsed_url = parse_url($source_url);
        $source_scheme = $parsed_url['scheme'];

        $source_host = $parsed_url['host'];
        $source_user = (array_key_exists('user', $parsed_url))?$parsed_url['user']:"";
        $source_path = $parsed_url['path'];

        // Set up the pieces of the destination file names
        $temp_file = $destination_file_base.".".$file_type;
        $temp_file_path = $destination_directory.$temp_file;

        // Do we have a copy of this file already?
        if(file_exists($temp_file_path))
        {
            // Good job! You loaded the file! PARTY!
            return $temp_file;
        }

        // Load the file (based on the scheme)
        switch($source_scheme)
        {
            case 'ssh':
                // Run an rsync to get a local copy
                // NOTE: This feels dirty, but so it goes.
                $ssh_command = 'ssh -i '.env('RSYNC_IDENTITY_FILE');
                shell_exec('/usr/bin/rsync -az -e \''.$ssh_command.'\' '.$source_user.'@'.$source_host.':'.$source_path.' '.$temp_file_path);
                break;

            case 'http':
            case 'https':

                // If this is the archive, use their credentials
                // TODO: this should be made somehow more elegant or generalized
                if($source_host == "archive.org")
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

                    try
                    {
                        copy($source_url, $temp_file_path, $context);
                    }
                    catch (\Exception $e)
                    {
                        // Something went wrong with the copy
                        return "";
                    }
                }
                else
                {
                    copy($source_url, $temp_file_path);
                }
                break;
        }

        // Done
        return $temp_file;

    }
}
?>
