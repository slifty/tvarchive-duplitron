<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    const STATUS_NEW = 0;
    const STATUS_STARTED = 1;
    const STATUS_GENERATING_FPRINT = 2;
    const STATUS_MATCHING = 3;
    const STATUS_FINISHED = 4;
    const STATUS_FAILED = -1;

	/**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'projects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['project', 'fprintPath', 'mediaPath', 'status'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['imageId'];

}
