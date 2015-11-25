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
    public function index(Request $request)
    {
        // Project ID is required
        if($request->has('project_id'))
        {
            $results = Media::where('project_id', $request->input('project_id'));
        }
        else
        {
            // TODO: Real errors.
            return ['ERR: Project ID Required'];
        }

        if($request->has('matchType'))
        {
            switch($request->input('matchType'))
            {
                case 'corpus':
                    $results = $results->where('is_corpus', 1);
                    break;
                case 'distractor':
                    $results = $results->where('is_distractor', 1);
                    break;
                case 'potential_target':
                    $results = $results->where('is_potential_target', 1);
                    break;
                case 'target':
                    $results = $results->where('is_target', 1);
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
            $media->base_media_id = $base_media->id;
            $media->media_path = $base_media->media_path;
            $media->afpt_path = $base_media->afpt_path;
            $media->external_id = $base_media->external_id;
        }
        else
        {
            $media->media_path = $request->input('media_path');
            $media->afpt_path = '';

            // Store the external ID if it exists
            if($request->has('external_id'))
            {
                $media->external_id = $request->input('external_id');
            }
        }


        // Sometimes the media we want to track is a subset of the full media
        $media->start = $request->has('start')?$request->start:0;
        $media->duration = $request->has('duration')?$request->duration:MEDIA::DURATION_UNKNOWN;

        // Make sure this media hasn't already been saved for this project
        $query = Media::where('project_id', $media->project_id)
            ->where('start', $media->start)
            ->where('duration', $media->duration);

        if($media->external_id)
        {
            // If an external ID is set, use that as the differentiating factor
            $query = $query->where('external_id', $media->external_id);
        }
        else
        {
            // Otherwise, use the media path
            $query = $query->where('media_path', $media->media_path);
        }

        $existing_media = $query->first();
        if($existing_media)
        {
            return $existing_media;
        }

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


    public function getMediaMatches($id)
    {
        $media = Media::find($id);
        if(!$media)
            return [];

        return $media->destinationMatches()->get();
    }
}
