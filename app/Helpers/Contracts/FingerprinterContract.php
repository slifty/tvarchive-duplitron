<?php

namespace Duplitron\Helpers\Contracts;

interface FingerprinterContract
{

    /**
     * Run a media file through the matching algorithm, comparing with all four corpuses
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function runMatch($media);


    /**
     * Add a media file to the corpus database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function addCorpusItem($media);


    /**
     * Add a media file to the distractors database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function addDistractorsItem($media);


    /**
     * Add a media file to the potential targets database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function addPotentialTargetsItem($media);


    /**
     * Add a media file to the targets database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function addTargetsItem($afpt_file);


    /**
     * Remove a media file from the corpus database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function removeCorpusItem($media);


    /**
     * Remove a media file from the distractors database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function removeDistractorsItem($media);


    /**
     * Remove a media file from the potential targets database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function removePotentialTargetsItem($media);


    /**
     * Remove a media file from the targets database
     * @param  object $media the media object being matched against the various corpuses
     * @return object        the result object, containing output logs and data
     */
    public function removeTargetsItem($afpt_file);


}
