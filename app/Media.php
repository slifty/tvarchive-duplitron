<?php

namespace Duplitron;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
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
    protected $table = 'media';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['media_path', 'afpt_path'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['is_candidate', 'is_corpus', 'is_distractor', 'is_target'];

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
     * Create the match categorization information block
     */
    public function getMatchCategorizationAttribute() {
        return $this->attributes['match_categorization'] = [
            'is_candidate' => $this->is_candidate,
            'is_corpus' => $this->is_corpus,
            'is_distractor' => $this->is_distractor,
            'is_target' => $this->is_target
        ];
    }


}
