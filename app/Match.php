<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class Match extends Model
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
    protected $table = 'matches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['duration', 'destination_start', 'source_start'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $with = ['source', 'destination'];

    /**
     * Specify relationship with Media (Target)
     * The destination is the file that has been matched against (from one of the audfprint databases).
     */
    public function destination() {
        return $this->belongsTo('Duplitron\Media', 'destination_id');
    }

    /**
     * Specify relationship with Media (Source)
     * The source is the input file that we searched for matches within.
     */
    public function source() {
        return $this->belongsTo('Duplitron\Media', 'source_id');
    }



}
