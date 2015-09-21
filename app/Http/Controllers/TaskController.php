<?php

namespace Duplitron\Http\Controllers;

use Illuminate\Http\Request;

use Duplitron\Http\Requests;
use Duplitron\Http\Controllers\Controller;

use Duplitron\Task;
use Duplitron\Jobs\PerformVideoMatch;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return "";
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
        $task->status = Task::STATUS_NEW;
        $task->project_id = $request->input('project_id');
        $task->media_url = $request->input('media_url');
        $task->save();

        // Dispatch a job for this task
        $this->dispatch(new PerformVideoMatch($task));

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
