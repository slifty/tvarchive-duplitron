<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    // The following fields are provided out of the box by Eloquent
    // - id
    // - created_at
    // - updated_at

    const DURATION_UNKNOWN = -1;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'media';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'media_path',
        'afpt_path',
        'external_id',
        'start',
        'duration'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'is_potential_target',
        'is_corpus',
        'is_distractor',
        'is_target',
        'potential_target_database',
        'corpus_database',
        'distractor_database',
        'target_database'
    ];



    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['match_categorization'];

    /**
     * Specify relationship with Tasks
     */
    public function tasks() {
        return $this->hasMany('Duplitron\Task');
    }

    /**
     * Specify relationship with Project
     */
    public function project() {
        return $this->belongsTo('Duplitron\Project');
    }

    /**
     * Specify relationship with Project
     */
    public function baseMedia() {
        return $this->belongsTo('Duplitron\Media', 'base_media_id');
    }

    /**
     * Specify relationship with Project
     */
    public function childMedia() {
        return $this->belongsTo('Duplitron\Media', 'base_media_id');
    }

    /**
     * Specify relationship with Matches
     */
    public function sourceMatches() {
        return $this->hasMany('Duplitron\Match', 'source_id');
    }

    /**
     * Specify relationship with Matches
     */
    public function destinationMatches() {
        return $this->hasMany('Duplitron\Match', 'destination_id');
    }

    /**
     * Create the match categorization information block
     */
    public function getMatchCategorizationAttribute() {
        return $this->attributes['match_categorization'] = [
            'is_potential_target' => $this->is_potential_target,
            'is_corpus' => $this->is_corpus,
            'is_distractor' => $this->is_distractor,
            'is_target' => $this->is_target
        ];
    }


}
