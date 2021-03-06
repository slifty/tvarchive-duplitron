<?php

namespace Duplitron\Jobs;

use Duplitron\MediaTask;
use Duplitron\Media;
use Duplitron\Jobs\Job;

use Duplitron\Helpers\Contracts\FingerprinterContract;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class PerformMediaTask extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    // Models
    protected $task; // The publicly facing Duplitron task object associated with this job

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(MediaTask $task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(FingerprinterContract $fingerprinter)
    {
        if($this->attempts() > 1)
            return;

        // Load the media file locally
        // Mark this as processing
        $this->task->status_code = MediaTask::STATUS_PROCESSING;
        $this->task->save();

        // TODO: Errors should be caught in a better way
        try
        {
            // Run the Docker commands based on the task type
            switch($this->task->type)
            {
                case MediaTask::TYPE_MATCH:
                    $results = $fingerprinter->runMatch($this->task->media, true);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_MATCH_TARGETS:
                    $results = $fingerprinter->runMatch($this->task->media, true, FingerprinterContract::MATCH_TARGET);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_FULL_MATCH: // All files, not just recent ones

                    // Set date limits if specified
                    if(array_key_exists('start_date', $this->task->parameters))
                        $fingerprinter->start_date = $this->task->parameters['start_date'];
                    if(array_key_exists('end_date', $this->task->parameters))
                        $fingerprinter->end_date = $this->task->parameters['end_date'];

                    $results = $fingerprinter->runMatch($this->task->media, false);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_CORPUS_ADD:
                    $results = $fingerprinter->addCorpusItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_POTENTIAL_TARGET_ADD:
                    $results = $fingerprinter->addPotentialTargetsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_DISTRACTOR_ADD:
                    $results = $fingerprinter->addDistractorsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_TARGET_ADD:
                    $results = $fingerprinter->addTargetsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_CORPUS_REMOVE:
                    $results = $fingerprinter->removeCorpusItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_POTENTIAL_TARGET_REMOVE:
                    $results = $fingerprinter->removePotentialTargetsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_TARGET_REMOVE:
                    $results = $fingerprinter->removeTargetsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_DISTRACTOR_REMOVE:
                    $results = $fingerprinter->removeDistractorsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case MediaTask::TYPE_CLEAN:
                    $results = $fingerprinter->cleanUp($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;
            }
        }
        catch (\Exception $e)
        {
            $result = array(
                "type" => "error",
                "message" => $e->getMessage()
            );

            $output = array(
                "message" => $e->getMessage(),
                "trace" => $e->getTrace(),
                "line" => $e->getLine()
            );
            $this->task->result_data = json_encode($result);
            $this->task->result_output = json_encode($output);
            $this->task->status_code = MediaTask::STATUS_FAILED;
            $this->task->save();
            return;
        }

        // TODO: this is honestly the worst thing ever.
        if($this->hasErrors($this->task->result_output))
            $this->task->status_code = MediaTask::STATUS_FAILED;
        else
            $this->task->status_code = MediaTask::STATUS_FINISHED;

        // Mark this as finished
        $this->task->save();
    }

    // TODO: lol
    private function hasErrors($line)
    {
        // Did we find a log line that talks about a Traceback?
        if(strpos($line, 'Traceback') === false)
            return false;

        return true;
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed()
    {
        // Called when the job is failing...
        $this->task->status_code = MediaTask::STATUS_FAILED;
        $this->task->save();
    }

}
