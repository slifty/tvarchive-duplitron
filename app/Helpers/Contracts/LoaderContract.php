<?php

namespace Duplitron\Helpers\Contracts;

interface LoaderContract
{


    /**
     * Takes a media URL, checks the local cache, and if it hasn't been downloaded
     * creates a local copy inside of the env('FPRINT_STORE')/media_cache/ directory
     * @param  string $media the media object being loaded
     * @return array         the name of the ['full'] local media file that was created as well as the names of the ['chunks'].
     */
    public function loadMedia($media);

}
