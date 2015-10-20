<?php

namespace Duplitron\Jobs;

use Duplitron\Task;
use Duplitron\Media;
use Duplitron\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class PerformMediaTask extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $task;
    private $temp_media_file;

    const AUDFPRINT_DOCKER_PATH = '/var/audfprint/';


    // TODO: We probably want to create a "matches" model
    const MATCH_POTENTIAL_TARGET = 'potential_targets';
    const MATCH_CORPUS = 'corpus';
    const MATCH_DISTRACTOR = 'distractors';
    const MATCH_TARGET = 'targets';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        // Load the media file locally
        $afpt_file = $this->prepareMedia($this->task->media);

        // Run the Docker commands based on the task type
        switch($this->task->type)
        {
            case Task::TYPE_MATCH:

                $this->runMatch($afpt_file);
                break;

            case Task::TYPE_CORPUS_ADD:
                $this->addCorpusItem($afpt_file);
                break;

            case Task::TYPE_POTENTIAL_TARGET_ADD:
                $this->addPotentialTargetsItem($afpt_file);
                break;

            case Task::TYPE_DISTRACTOR_ADD:
                $this->addDistractorsItem($afpt_file);
                break;

            case Task::TYPE_TARGET_ADD:
                $this->addTargetsItem($afpt_file);
                break;
        }

    }

    /**
     * Copy a file from a remote location to our temporary storage directory
     * TODO: Support precomputed fingerprint files, not just media files
     * TODO: this belongs in a provider or some other kind of utility
     * @param  string $path an ssh:// protocol path
     * @return string The new file name
     */
    private function loadMedia($media)
    {
        $path = $media->media_path;

        // Make a name for the temporary media file
        $parsed_url = parse_url($path);
        $media_host = $parsed_url['host'];
        $media_user = $parsed_url['user'];
        $media_path = $parsed_url['path'];

        $parsed_path = pathinfo($media_path);
        $file_type = $parsed_path['extension'];

        $temp_media_file = "media-".$this->task->media->id.".".$file_type;
        $temp_media_path = env('FPRINT_STORE').'media_cache/'.$temp_media_file;

        // Do we have a copy of this media cached?
        if(file_exists($temp_media_path))
            return $temp_media_path;

        // Run an rsync to get a local copy
        // NOTE: This feels dirty, but so it goes.
        // TODO: support HTTP, not just rsync
        $ssh_command = 'ssh -i '.env('RSYNC_IDENTITY_FILE');
        shell_exec('/usr/bin/rsync -az -e \''.$ssh_command.'\' '.$media_user.'@'.$media_host.':'.$media_path.' '.$temp_media_path);

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


    /**
     * Create and run a docker image
     * @param  string[] $cmd The list of commands to invoke in the docker image
     */
    private function runDocker($cmd) {

        // Create a connection with docker
        $docker = new \Docker\Docker(\Docker\Http\DockerClient::createWithEnv());

        // Create the docker container
        $container = new \Docker\Container(
            [
                'Image' => env('DOCKER_FPRINT_IMAGE'),
                'Cmd' => $cmd,
                'Volumes' => [
                    '/var/audfprint' => []
                ],
                'HostConfig' => [
                    'Binds' => [env('FPRINT_STORE').':'.PerformMediaTask::AUDFPRINT_DOCKER_PATH]
                ]
            ]
        );

        $manager = $docker->getContainerManager();
        $manager->create($container);

        $this->task->status_code = Task::STATUS_PROCESSING;
        $this->task->save();

        // Gather the logs and return them to the caller
        $logs = [];
        $manager->run($container, function($output, $type) use (&$logs) {
            // TODO: Process output more intelligently...
            $logs = array_merge($logs,explode("\n", $output));
        });

        $this->task->status_code = Task::STATUS_FINISHED;
        $this->task->save();

        return $logs;
    }

    /**
     * Make sure the media has been loaded and preprocessed
     * @param  object $media the media file we're going to be working with
     * @return boolean whether or not the media is properly loaded
     */
    private function prepareMedia($media)
    {
        // Nail down the name of the fingerprint file
        // TODO: fprint_file should probably be stored in the DB.  Right now we're relying on the filename matching a pattern from the loadMedia() step.  Bad form.
        $fprint_file = 'media-'.$this->task->media->id.'.afpt';
        $fprint_path = env('FPRINT_STORE').'afpt_cache/'.$fprint_file;

        // Do we have a fingerprint cached?
        if(file_exists($fprint_path))
            return $fprint_file;

        // Are we using a subset of the media / do we need to generate our own fprint?
        // TODO: for now we will ALWAYS generate an fprint if we don't have a cached one.  In future we will want to see if the fingerprint path is set and attempt to load it.

        // Load the media
        $media_file = $this->loadMedia($media);

        // Precompute the media
        $cmd = ['precompute', PerformMediaTask::AUDFPRINT_DOCKER_PATH.'media_cache/'.$media_file];
        $corpus_logs = $this->runDocker($cmd);

        // Move the precompute file
        $media_path = env('FPRINT_STORE').'media_cache/'.$fprint_file;
        rename($media_path, $fprint_path);

        return $fprint_file;
    }

    /**
     * Run a media file through the matching algorithm, comparing with all four corpuses
     * @param  string $media_file A path to the locally available media file
     */
    private function runMatch($afpt_file) {
        $media = $this->task->media;
        $project = $media->project;

        $task_logs = [];
        $corpus_results = [];
        $potential_targets_results = [];
        $distractors_results = [];
        $targets_results = [];

        /////
        // Find all matches with stored items
        // Find matches with corpus items
        if($project->has_corpus())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_corpus, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
            $corpus_logs = $this->runDocker($cmd);
            $task_logs = array_merge($task_logs, $corpus_logs);
            $corpus_results = $this->processAudfMatchLog($corpus_logs);
            array_walk($corpus_results, function(&$result)
            {
                $result['type'] = PerformMediaTask::MATCH_CORPUS;
            });
        }

        // Find matches with potential target items
        if($project->has_potential_targets())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_potential_targets, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
            $potential_targets_logs = $this->runDocker($cmd);
            $task_logs = array_merge($task_logs, $potential_targets_logs);
            $potential_targets_results = $this->processAudfMatchLog($potential_targets_logs);
            array_walk($potential_targets_results, function(&$result)
            {
                $result['type'] = PerformMediaTask::MATCH_POTENTIAL_TARGET;
            });
        }

        // Find matches with distractor items
        if($project->has_distractors())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_distractors, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
            $distractors_logs = $this->runDocker($cmd);
            $task_logs = array_merge($task_logs, $distractors_logs);
            $distractors_results = $this->processAudfMatchLog($distractors_logs);
            array_walk($distractors_results, function(&$result)
            {
                $result['type'] = PerformMediaTask::MATCH_DISTRACTOR;
            });
        }

        // Find matches with target items
        if($project->has_targets())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_targets, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
            $targets_logs = $this->runDocker($cmd);
            $task_logs = array_merge($task_logs, $targets_logs);
            $targets_results = $this->processAudfMatchLog($targets_logs);
            array_walk($targets_results, function(&$result)
            {
                $result['type'] = PerformMediaTask::MATCH_TARGET;
            });
        }


        /////
        // Resolve the matches
        //

        // Create a master list of matches
        $matches = array_merge($corpus_results, $potential_targets_results, $distractors_results, $targets_results);

        // Sort all matches list by start time
        $start_sort = function($a, $b)
        {
            if($a['start'] < $b['start'])
                return -1;
            if($a['start'] > $b['start'])
                return 1;
            return 0;
        };
        usort($matches, $start_sort);
        usort($corpus_results, $start_sort);
        usort($potential_targets_results, $start_sort);
        usort($distractors_results, $start_sort);
        usort($targets_results, $start_sort);

        // Consolidate matches involving the same file

        // Map the input file to segments based on the matches
        // Distractor -> Target -> Potential Target -> Corpus
        $segments = [];
        $start = -1;
        $end = -1;
        $type = '';
        $matched_files = [];
        $is_new_match = true;

        while($match = array_shift($matches))
        {
            $next_match = array_shift($matches);

            if($is_new_match)
            {
                // Brand new match
                $start = $match['start'];
                $end = $match['start'] + $match['duration'];
                $type = $match['type'];
                $is_new_match = false;
            }
            else
            {
                // Merge overlap
                $end = max($end, $match['start'] + $match['duration']);

                // Resolve types (combine and pick the dominant type)
                if($match['type'] == PerformMediaTask::MATCH_DISTRACTOR ||
                    $type == PerformMediaTask::MATCH_DISTRACTOR)
                    $type = PerformMediaTask::MATCH_DISTRACTOR;
                else if($match['type'] == PerformMediaTask::MATCH_TARGET ||
                    $type == PerformMediaTask::MATCH_TARGET)
                    $type = PerformMediaTask::MATCH_TARGET;
                else if($match['type'] == PerformMediaTask::MATCH_POTENTIAL_TARGET ||
                    $type == PerformMediaTask::MATCH_POTENTIAL_TARGET)
                    $type = PerformMediaTask::MATCH_POTENTIAL_TARGET;
                else if($match['type'] == PerformMediaTask::MATCH_CORPUS ||
                    $type == PerformMediaTask::MATCH_CORPUS)
                    $type = PerformMediaTask::MATCH_CORPUS;
            }

            if($next_match == null || $next_match['start'] > $end) {
                // We're done with this match, SHIP IT
                $final_match = [
                    'start' => $start,
                    'end' => $end,
                    'type' => $type
                ];

                array_push($segments, $final_match);
                $is_new_match = true;
            }

            // Add the next back to the list
            array_unshift($matches, $next_match);
        }

        $results = [
            "matches" => [
                "potential_targets" => $potential_targets_results,
                "corpus" => $corpus_results,
                "distractors" => $distractors_results,
                "targets" => $targets_results
            ],
            "segments" => $segments
        ];
        $this->task->result_data = json_encode($results);
        $this->task->result_output = json_encode($task_logs);
        $this->task->save();
    }


    /**
     * TODO: This method will hopefully be deleted some day, and audfprint will just return structured data
     * For now it takes the output from an audfprint match operation and returns a structured list of matches
     * @param  string[] $logs The log output from an audf process
     * @return object[]       The list of matches
     */
    private function processAudfMatchLog($logs) {
        $matches = [];
        foreach($logs as $line) {
            $match_pattern = '/Matched\s+(\S+)\s+s\s+starting\s+at\s+(\S+)\s+s\s+in\s+(\S+)\s+to\s+time\s+(\S+)\s+s\s+in\s+(\S+)\s+with\s+(\S+)\s+of\s+(\S+)\s+common\s+hashes\s+at\s+rank\s+(\S+)/';
            if(preg_match($match_pattern, $line, $match_data))
            {
                // TODO: This object should be defined somewhere... not randomly in the middle of code
                // matched_file -> the filename of the match
                // duration -> the duration of the match
                // start -> where in the INPUT file the match starts
                // target_start -> where in the MATCHED file the match starts

                // The file name has a distinct pattern that contains the media ID in the system
                $file_pattern = '/.*media\-(\d*)\..*/';
                preg_match($file_pattern, $match_data[5], $file_data);

                $destination_media = Media::find($file_data[1]);

                $match = [
                    "matched_file" => $match_data[5],
                    "duration" => floatval($match_data[1]),
                    "start" => floatval($match_data[2]),
                    "target_start" => floatval($match_data[4]),
                    "destination_media" => $destination_media
                ];

                array_push($matches, $match);
            }
        }
        return $matches;
    }

    private function addCorpusItem($afpt_file) {
        $media = $this->task->media;
        $project = $media->project;

        if($project->has_corpus())
        {
            // Add to an existing corpus
            $audf_command = 'add';
        }
        else
        {
            // Create a corpus from this item
            $audf_command = 'new';
            $project->audf_corpus = "project_".$project->id."-corpus.pklz";
            $project->save();
        }

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_corpus, PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
        $this->runDocker($cmd);
        $media->is_corpus = true;
        $media->save();
    }

    private function addPotentialTargetsItem($afpt_file) {
        $media = $this->task->media;
        $project = $media->project;

        if($project->has_potential_targets())
        {
            // Add to an existing corpus
            $audf_command = 'add';
        }
        else
        {
            // Create a corpus from this item
            $audf_command = 'new';
            $project->audf_potential_targets = "project_".$project->id."-potential_targets.pklz";
            $project->save();
        }

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_potential_targets, PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
        $this->runDocker($cmd);
        $media->is_potential_target = true;
        $media->save();
    }

    private function addDistractorsItem($afpt_file) {
        $media = $this->task->media;
        $project = $media->project;

        if($project->has_distractors())
        {
            // Add to an existing corpus
            $audf_command = 'add';
        }
        else
        {
            // Create a corpus from this item
            $audf_command = 'new';
            $project->audf_distractors = "project_".$project->id."-distractors.pklz";
            $project->save();
        }

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_distractors, PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
        $this->runDocker($cmd);
        $media->is_distractor = true;
        $media->save();
    }

    private function addTargetsItem($afpt_file) {
        $media = $this->task->media;
        $project = $media->project;

        if($project->has_targets())
        {
            // Add to an existing corpus
            $audf_command = 'add';
        }
        else
        {
            // Create a corpus from this item
            $audf_command = 'new';
            $project->audf_targets = "project_".$project->id."-targets.pklz";
            $project->save();
        }

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_targets, PerformMediaTask::AUDFPRINT_DOCKER_PATH.'afpt_cache/'.$afpt_file];
        $this->runDocker($cmd);
        $media->is_target = true;
        $media->save();
    }
}
