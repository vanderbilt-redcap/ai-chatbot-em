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
        return self::sendRequest($url, 'POST', $data, $headers);



        /*$ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        echo "before exec"; die;
        $res = json_decode($response, true);
        $err = curl_error($ch);
        curl_close($ch);
        if($err) {
            echo("cURL Error #:" . $err); die;
            return false;
        } else {
            echo("success"); die;
            return $res;
        }*/

    }
    public static function sendRequest($url, $method, $options = [], $headers)
    {
echo $url; die;
        //$post_fields = json_encode($options);
        $post_fields = $options;

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

        if ($options == []) {
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }

        $curl = curl_init();

        curl_setopt_array($curl, $curl_info);
        echo "before curl exec";
        echo json_encode($curl_info); die;

        $response = curl_exec($curl);

        $info           = curl_getinfo($curl);

        curl_close($curl);

        return $response;
    }
}
