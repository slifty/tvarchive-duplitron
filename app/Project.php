<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    // The following fields are provided out of the box by Eloquent
    // - id
    // - created_at
    // - updated_at

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
    protected $fillable = ['name'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Specify relationship with media
     */
    public function media() {
        return $this->hasMany('Duplitron\Media');
    }

    /**
     * Specify relationship with Project Tasks
     */
    public function tasks() {
        return $this->hasMany('Duplitron\ProjectTask');
    }

}
