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
    public function __construct()
    {
        $this->task = null;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // TODO: Make a "Docker" provider
        $docker = new \Docker\Docker(\Docker\Http\DockerClient::createWithEnv());

        $container = new \Docker\Container(
            [
                'Image' => env('DOCKER_FPRINT_IMAGE'),
                'Cmd' => ['precompute', '/var/audfprint/music.mp3'],
                'HostConfig' => [
                    'Binds' => [env('FPRINT_STORE').':/var/audfprint']
                ]
            ]
        );

        $manager = $docker->getContainerManager();
        $manager->create($container);
        $manager->run($container, function($output, $type) {
        });
    }
}
