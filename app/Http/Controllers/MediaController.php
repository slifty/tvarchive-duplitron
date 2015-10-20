<?php

namespace Duplitron\Http\Controllers;

use Illuminate\Http\Request;

use Duplitron\Http\Requests;
use Duplitron\Http\Controllers\Controller;

use Duplitron\Media;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
        $media = new Media();


        $media->project_id = $request->input('project_id');

        // Media can be created in two ways:
        // 1) A media path or an audf path (TODO: implement this)
        // 2) As a subsection of an existing media file
        if($request->has('base_media_id'))
        {
            $base_media = Media::find($request->input('base_media_id'));
            $media->media_path = $base_media->media_path;
            $media->afpt_path = $base_media->afpt_path;
        }
        else
        {
            $media->media_path = $request->input('media_path');
            $media->afpt_path = '';
        }

        // Sometimes the media we want to track is a subset of the full media
        $media->start = $request->has('start')?$request->start:0;
        $media->duration = $request->has('duration')?$request->duration:MEDIA::DURATION_UNKNOWN;

        // Make sure this media hasn't already been saved for this project
        $existing_media = Media::where('media_path', $media->media_path)
            ->where('project_id', $media->project_id)
            ->where('start', $media->start)
            ->where('duration', $media->duration)
            ->first();

        if($existing_media)
            return $existing_media;

        $media->save();
        return $media;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $media = Media::find($id);
        return $media;
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
