<?php

namespace Duplitron\Helpers\Contracts;

interface LoaderContract
{

    /**
     * Takes a media object, checks the local cache for fingerprints, and if it hasn't been downloaded
     * creates a local copy inside of the env('FPRINT_STORE')/afpt_cache/ directory
     * @param  object $media the media object whose fingerprints are being loaded
     * @return array         the name of the ['full'] local afpt file that was created as well as the names of the ['chunks'].
     */
    public function loadFingerprints($media);

    /**
     * Takes a media object, checks the local cache, and if it hasn't been downloaded
     * creates a local copy inside of the env('FPRINT_STORE')/media_cache/ directory
     * @param  object $media the media object being loaded
     * @return array         the name of the ['full'] local media file that was created as well as the names of the ['chunks'].
     */
    public function loadMedia($media);

}
