<?php

namespace Duplitron\Helpers\Contracts;

interface HttpContract
{


    /**
     * An internal helper method created in the name of DRY
     * It just calls curl and processes the result
     * @param  string $url  the url to post to
     * @param  object $data the data to send in the post
     * @return object       an object parsed from the json returned
     */
    public function post($url, $data);

    /**
     * An internal helper method created in the name of DRY
     * It just calls curl and processes the result
     * @param  string $url  the url to post to
     * @return object       an object parsed from the json returned
     */
    public function get($url);

}
