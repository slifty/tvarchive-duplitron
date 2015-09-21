<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    const STATUS_NEW = 0;
    const STATUS_STARTING = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_FINISHED = 3;
    const STATUS_FAILED = -1;

	/**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'tasks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['project_id', 'fprint_path', 'media_path', 'status', 'result'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['image_id'];

    /**
     * Specify 1:* relationship with Project
     */
    public function project() {
        return $this->hasMany('Duplitron\Project');
    }


}
