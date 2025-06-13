<?php

class Api
{
    /**
     * CURL - Call GET method
     *
     * @param $api_key string
     * @param $url string
     * @return array|boolean
     */
    public static function getCurlCall($api_key, $url)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
                                CURLOPT_URL => $url,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 60,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'GET',
                                CURLOPT_HTTPHEADER => array(
                                    'OpenAI-Beta: assistants=v2',
                                    'Authorization: Bearer '.$api_key,
                                ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if($err)
        {
            echo("cURL Error #:" . $err);
            return false;
        }
        else
        {
            return $response;
        }
    }

    public static function curlAPIPost($api_key, $url, $data, $headers = [])
    {
        if (empty($headers)) {
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'OpenAI-Beta: assistants=v1',
            ];
        }
        $response = http_post($url, $data, null, 'application/json', "", $headers);
            // self::sendRequest($url, 'POST', $data, $headers);
        return json_decode($response, true);
    }
    public static function sendRequest($url, $method, $post_fields = [], $headers)
    {
        $curl_info = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($post_fields == []) {
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }

        $curl = curl_init();

        curl_setopt_array($curl, $curl_info);
        /*echo "before curl exec";
        echo json_encode($curl_info); */

        $response = curl_exec($curl);
        $info = curl_getinfo($curl);

        curl_close($curl);

        return $response;
    }
}
