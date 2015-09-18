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
        // TODO: Make a "Docker" provider
        $docker = new Docker\Docker(Docker\Http\DockerClient::createWithEnv());

        $container = new Docker\Container(
            [
                'Image' => env('DOCKER_FPRINT_IMAGE'),
                'Entrypoint' => ['cowsay', 'boo'],
                'Mounts' => [
                    {
                        'Source':env('FPRINT_STORE'),
                        'Destination': '/var/audfprint',
                        'Mode':'',
                        'RW':false
                    }
                ]
            ]
        );

        $manager = $docker->getContainerManager();
        $manager->create($container);
        $manager->run($container, function($output, $type) {
            echo($output);
        });

        printf('Container\'s id is %s', $container->getId());
        printf('Container\'s name is %s', $container->getName());
        printf('Container\'s exit code is %d', $container->getExitCode());

    }
}
