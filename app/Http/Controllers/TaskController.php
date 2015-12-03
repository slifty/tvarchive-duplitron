<?php

namespace Duplitron\Http\Controllers;

use Illuminate\Http\Request;

use Duplitron\Http\Requests;
use Duplitron\Http\Controllers\Controller;

use Duplitron\Task;
use Duplitron\Jobs\PerformMediaTask;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        // Project ID is required
        if($request->has('project_id'))
        {
            $results = \DB::table('media')
                ->join('tasks', 'media.id', '=', 'tasks.media_id')
                ->where('project_id', $request->input('project_id'));
        }
        else
        {
            // TODO: Real errors.
            return ['ERR: Project ID Required'];
        }

        if($request->has('status'))
        {
            switch($request->input('status'))
            {
                case Task::STATUS_NEW:
                    $results = $results->where('status_code', Task::STATUS_NEW);
                    break;
                case Task::STATUS_STARTING:
                    $results = $results->where('status_code', Task::STATUS_STARTING);
                    break;
                case Task::STATUS_PROCESSING:
                    $results = $results->where('status_code', Task::STATUS_PROCESSING);
                    break;
                case Task::STATUS_FINISHED:
                    $results = $results->where('status_code', Task::STATUS_FINISHED);
                    break;
                case Task::STATUS_FAILED:
                    $results = $results->where('status_code', Task::STATUS_FAILED);
                    break;
            }
        }
        return $results->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // Create a new task object
        $task = new Task();
        $task->status_code = Task::STATUS_NEW;
        $task->attempts = 0;
        $task->media_id = $request->input('media_id');
        $task->type = $request->input('type');
        $task->save();

        // Dispatch a job for this task
        $this->dispatch(new PerformMediaTask($task));

        return $task;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $task = Task::find($id);
        return $task;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
