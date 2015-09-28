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
    protected $hidden = ['audf_corpus', 'audf_candidates', 'audf_targets', 'audf_distractors'];

    /**
     * Specify relationship with media
     */
    public function media() {
        return $this->hasMany('Duplitron\Media');
    }

    // Audfprint can't create databases until there is something in it, so we need to know if each database has been set up yet
    // TODO: modify Audfprint to allow empty databases to avoid this.
    public function has_corpus() {
        return $this->audf_corpus != null && file_exists(env('FPRINT_STORE').$this->audf_corpus);
    }
    public function has_candidates() {
        return $this->audf_candidates != null && file_exists(env('FPRINT_STORE').$this->audf_candidates);
    }
    public function has_targets() {
        return $this->audf_targets   != null && file_exists(env('FPRINT_STORE').$this->audf_targets);
    }
    public function has_distractors() {
        return $this->audf_distractors != null && file_exists(env('FPRINT_STORE').$this->audf_distractors);
    }
}
