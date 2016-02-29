<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    const STATUS_NEW = 0;
    const STATUS_STARTING = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_FINISHED = 3;
    const STATUS_FAILED = -1;

    const TYPE_CLEAN = 'clean';

    // The following fields are provided out of the box by Eloquent
    // - id
    // - created_at
    // - updated_at

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'project_tasks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['type', 'attempts'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['status_code', 'result_code', 'result_data', 'result_output'];


    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status', 'result'];


    /**
     * Specify relationship with Media
     */
    public function project() {
        return $this->belongsTo('Duplitron\Project');
    }

    public function getStatusAttribute() {
        return $this->attributes['status'] = [
            "code" => $this->attributes['status_code'],
            "description" => ""
        ];
    }

    public function getResultAttribute() {
        return $this->attributes['result'] = [
            "code" => $this->result_code,
            "data" => json_decode($this->result_data),
            "output" => json_decode($this->result_output)
        ];
    }

}
