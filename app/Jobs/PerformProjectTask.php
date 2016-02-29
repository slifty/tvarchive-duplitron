<?php

namespace Duplitron\Jobs;

use Duplitron\ProjectTask;
use Duplitron\Media;
use Duplitron\Jobs\Job;
use Duplitron\Project;

use Duplitron\Helpers\Contracts\FingerprinterContract;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class PerformProjectTask extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    // Models
    protected $task; // The publicly facing Duplitron task object associated with this job

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ProjectTask $task)
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

        // Mark this as processing
        $this->task->status_code = ProjectTask::STATUS_PROCESSING;
        $this->task->save();

        // TODO: switch on task type
        $results = $fingerprinter->cleanProject($this->task->project);
        $this->task->result_data = json_encode($results['results']);
        $this->task->result_output = json_encode($results['output']);


        $this->task->status_code = ProjectTask::STATUS_FINISHED;
        $this->task->save();

        return;
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed()
    {
    }

}
