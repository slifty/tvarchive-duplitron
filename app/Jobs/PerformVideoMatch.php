<?php

namespace Duplitron\Jobs;

use Duplitron\Task;
use Duplitron\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

class PerformVideoMatch extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $task;

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
        //////////////
        // STEP 1: Copy the media file to our storage directory

        // Pick a name for the temporary copy
        $parsed_url = parse_url($this->task->media_url);
        $media_host = $parsed_url['host'];
        $media_user = $parsed_url['user'];
        $media_path = $parsed_url['path'];

        $parsed_path = pathinfo($media_path);
        $file_type = $parsed_path['extension'];

        $target_filename = "task_media-".$this->task->id.".".$file_type;

        // Run an rsync to get a local copy
        // NOTE: This feels dirty, but so it goes.
        // TODO: support HTTP, not just rsync
        $ssh_command = 'ssh -i '.env('RSYNC_IDENTITY_FILE');
        shell_exec('/usr/bin/rsync -az -e \''.$ssh_command.'\' '.$media_user.'@'.$media_host.':'.$media_path.' '.env('FPRINT_STORE').'/'.$target_filename);


        //////////////
        // STEP 2: Run the task in docker

        // TODO: Make a "Docker" provider

        // Create a connection with docker
        $docker = new \Docker\Docker(\Docker\Http\DockerClient::createWithEnv());


        // Create the docker container
        $container = new \Docker\Container(
            [
                'Image' => env('DOCKER_FPRINT_IMAGE'),
                'Cmd' => ['precompute', '/var/audfprint/'.$target_filename],
                'HostConfig' => [
                    'Binds' => [env('FPRINT_STORE').':/var/audfprint']
                ]
            ]
        );

        $manager = $docker->getContainerManager();
        $manager->create($container);

        $this->task->status = Task::STATUS_STARTED;
        $this->task->image_id = $container->getId();
        $this->task->save();

        $manager->run($container, function($output, $type) {

        });
    }
}
