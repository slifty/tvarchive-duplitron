<?php

namespace Duplitron\Helpers\Contracts;

interface LoaderContract
{


    /**
     * Takes a media URL, checks the local cache, and if it hasn't been downloaded
     * creates a local copy inside of the env('FPRINT_STORE')/media_cache/ directory
     * @param  string $media the media object being loaded
     * @return string        the name of the local media file that was created.
     */
    public function loadMedia($media);

}
