<?php

namespace Duplitron\Jobs;

use Duplitron\Task;
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
    public function __construct(Task $task)
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

        // Load the media file locally
        // Mark this as processing
        $this->task->status_code = Task::STATUS_PROCESSING;
        $this->task->save();

        // TODO: Errors should be caught in a better way
        try
        {
            // Run the Docker commands based on the task type
            switch($this->task->type)
            {
                case Task::TYPE_MATCH:
                    $results = $fingerprinter->runMatch($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case Task::TYPE_CORPUS_ADD:
                    $results = $fingerprinter->addCorpusItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case Task::TYPE_POTENTIAL_TARGET_ADD:
                    $results = $fingerprinter->addPotentialTargetsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case Task::TYPE_DISTRACTOR_ADD:
                    $results = $fingerprinter->addDistractorsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case Task::TYPE_TARGET_ADD:
                    $results = $fingerprinter->addTargetsItem($this->task->media);
                    $this->task->result_data = json_encode($results['results']);
                    $this->task->result_output = json_encode($results['output']);
                    $this->task->save();
                    break;

                case Task::TYPE_POTENTIAL_TARGET_REMOVE:
                    $results = $fingerprinter->removePotentialTargetsItem($this->task->media);
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
            $this->task->status_code = Task::STATUS_FAILED;
            $this->task->save();
            return;
        }

        // TODO: this is honestly the worst thing ever.
        if($this->checkForErrors(json_encode($this->task->result_output)))
            $this->task->status_code = Task::STATUS_FAILED;
        else
            $this->task->status_code = Task::STATUS_FINISHED;

        // Mark this as finished
        $this->task->status_code = Task::STATUS_FINISHED;
        $this->task->save();
    }

    // TODO: lol
    private function checkForErrors($line)
    {
        $match_pattern = '/Traceback/';
        // Did we find a log line that talks about drop count?
        if(preg_match($match_pattern, $line))
            return true;

        return false;
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed()
    {
        // Called when the job is failing...
        $this->task->status_code = Task::STATUS_FAILED;
        $this->task->save();
    }

}
