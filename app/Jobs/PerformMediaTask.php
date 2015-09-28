<?php

namespace Duplitron\Jobs;

use Duplitron\Task;
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
    const MATCH_CANDIDATE = 'candidatess';
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
        $media_file = $this->loadFile($this->task->media);

        // Run the Docker commands based on the task type
        switch($this->task->type)
        {
            case Task::TYPE_MATCH:

                $this->runMatch($media_file);
                break;

            case Task::TYPE_CORPUS_ADD:
                $this->addCorpusItem($media_file);
                break;

            case Task::TYPE_CANDIDATE_ADD:
                $this->addCandidatesItem($media_file);
                break;

            case Task::TYPE_DISTRACTOR_ADD:
                $this->addDistractorsItem($media_file);
                break;

            case Task::TYPE_TARGET_ADD:
                $this->addTargetsItem($media_file);
                break;
        }

    }

    /**
     * Copy a file from a remote location to our temporary storage directory
     * TODO: Support precomputed fingerprint files, not just media files
     * @param  string $path an ssh:// protocol path
     * @return string The new file name
     */
    private function loadFile($media)
    {
        $path = $media->media_path;

        // Make a name for the temporary media file
        $parsed_url = parse_url($path);
        $media_host = $parsed_url['host'];
        $media_user = $parsed_url['user'];
        $media_path = $parsed_url['path'];

        $parsed_path = pathinfo($media_path);
        $file_type = $parsed_path['extension'];

        $temp_media_file = "task_media-".$this->task->id.".".$file_type;

        // Run an rsync to get a local copy
        // NOTE: This feels dirty, but so it goes.
        // TODO: support HTTP, not just rsync
        $ssh_command = 'ssh -i '.env('RSYNC_IDENTITY_FILE');
        shell_exec('/usr/bin/rsync -az -e \''.$ssh_command.'\' '.$media_user.'@'.$media_host.':'.$media_path.' '.env('FPRINT_STORE').$temp_media_file);

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

        return $logs;
    }

    /**
     * Run a media file through the matching algorithm, comparing with all four corpuses
     * @param  string $media_file A path to the locally available media file
     */
    private function runMatch($media_file) {
        $media = $this->task->media;
        $project = $media->project;

        $task_logs = [];
        $corpus_results = [];
        $candidates_results = [];
        $distractors_results = [];
        $targets_results = [];

        /////
        // Find all matches with stored items

        // Find matches with corpus items
        if($project->has_corpus())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_corpus, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
            $corpus_logs = $this->runDocker($cmd);
            $task_logs = array_merge($task_logs, $corpus_logs);
            $corpus_results = $this->processAudfMatchLog($corpus_logs);
            array_walk($corpus_results, function(&$result)
            {
                $result['type'] = PerformMediaTask::MATCH_CORPUS;
            });
        }

        // Find matches with candidate items
        if($project->has_candidates())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_candidates, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
            $candidates_logs = $this->runDocker($cmd);
            $task_logs = array_merge($task_logs, $candidates_logs);
            $candidates_results = $this->processAudfMatchLog($candidates_logs);
            array_walk($candidates_results, function(&$result)
            {
                $result['type'] = PerformMediaTask::MATCH_CANDIDATE;
            });
        }

        // Find matches with distractor items
        if($project->has_distractors())
        {
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_distractors, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
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
            $cmd = ['match', '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_targets, '--find-time-range', '-x', '1000', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
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
        $matches = array_merge($corpus_results, $candidates_results, $distractors_results, $targets_results);

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
        usort($candidates_results, $start_sort);
        usort($distractors_results, $start_sort);
        usort($targets_results, $start_sort);

        // Consolidate matches involving the same file



        // Map the input file to segments based on the matches
        // Distractor -> Target -> Candidate -> Corpus
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
                $matched_files = [$match['matched_file']];
                $is_new_match = false;
            }
            else
            {
                // Merge overlap
                $end = max($end, $match['start'] + $match['duration']);

                // Store the file
                array_push($matched_files, $match['matched_file']);

                // Resolve types (combine and pick the dominant type)
                if($match['type'] == PerformMediaTask::MATCH_DISTRACTOR ||
                    $type == PerformMediaTask::MATCH_DISTRACTOR)
                    $type = PerformMediaTask::MATCH_DISTRACTOR;
                else if($match['type'] == PerformMediaTask::MATCH_TARGET ||
                    $type == PerformMediaTask::MATCH_TARGET)
                    $type = PerformMediaTask::MATCH_TARGET;
                else if($match['type'] == PerformMediaTask::MATCH_CANDIDATE ||
                    $type == PerformMediaTask::MATCH_CANDIDATE)
                    $type = PerformMediaTask::MATCH_CANDIDATE;
                else if($match['type'] == PerformMediaTask::MATCH_CORPUS ||
                    $type == PerformMediaTask::MATCH_CORPUS)
                    $type = PerformMediaTask::MATCH_CORPUS;
            }

            if($next_match == null || $next_match['start'] > $end) {
                // We're done with this match, SHIP IT
                $final_match = [
                    'start' => $start,
                    'end' => $end,
                    'type' => $type,
                    'matched_files' => array_unique($matched_files)
                ];

                array_push($segments, $final_match);
                $is_new_match = true;
            }

            // Add the next back to the list
            array_unshift($matches, $next_match);
        }

        $results = [
            "matches" => [
                "candidates" => $candidates_results,
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
                $match = [
                    "matched_file" => $match_data[5],
                    "duration" => floatval($match_data[1]),
                    "start" => floatval($match_data[2]),
                    "target_start" => floatval($match_data[4]),
                ];

                array_push($matches, $match);
            }
        }
        return $matches;
    }

    private function addCorpusItem($media_file) {
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

        $cmd = [($isNew?'new':'add'), '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_corpus, PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
        $this->runDocker($cmd);
    }

    private function addCandidatesItem($media_file) {
        $media = $this->task->media;
        $project = $media->project;

        if($project->has_candidates())
        {
            // Add to an existing corpus
            $audf_command = 'add';
        }
        else
        {
            // Create a corpus from this item
            $audf_command = 'new';
            $project->audf_candidates = "project_".$project->id."-candidates.pklz";
            $project->save();
        }

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_candidates, PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
        $this->runDocker($cmd);
    }

    private function addDistractorsItem($media_file) {
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

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_distractors, PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
        $this->runDocker($cmd);
    }

    private function addTargetsItem($media_file) {
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

        $cmd = [$audf_command, '-d', PerformMediaTask::AUDFPRINT_DOCKER_PATH.$project->audf_targets, PerformMediaTask::AUDFPRINT_DOCKER_PATH.$media_file];
        $this->runDocker($cmd);
    }
}
