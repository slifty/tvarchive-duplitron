<?php

namespace Duplitron\Helpers;

use Duplitron\Helpers\Contracts\HttpContract;

class CurlHttp implements HttpContract
{

    /**
     * See contract for documentation
     */
    public function post($url, $data)
    {
        // Create the POST
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        // Take in the server's response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Run the CURL
        $curl_result = curl_exec($ch);
        curl_close ($ch);

        // Parse the result
        $result = json_decode($curl_result);

        if(!$result)
            echo($curl_result);

        return $result;
    }

    /**
     * See contract for documentation
     */
    public function get($url)
    {
        // Create the GET
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Take in the server's response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Run the CURL
        $curl_result = curl_exec($ch);
        curl_close ($ch);

        // Parse the result
        $result = json_decode($curl_result);

        if(!$result)
            echo($curl_result);

        return $result;
    }
}
?>

