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
        //$response = self::http_post_em($url, $data, null, 'application/json', "", $headers);
        $response = self::sendRequest($url, 'POST', $data, $headers);
        return json_decode($response, true);
    }
    public static function http_post_em($url="", $params=array(), $timeout=null, $content_type='application/x-www-form-urlencoded', $basic_auth_user_pass="", $headers=array())
    {
        // If params are given as an array, then convert to query string format, else leave as is
        if ($content_type == 'application/json') {
            // Send as JSON data
            $param_string = (is_array($params)) ? json_encode($params) : $params;
        } elseif ($content_type == 'application/x-www-form-urlencoded') {
            // Send as Form encoded data
            $param_string = (is_array($params)) ? http_build_query($params, '', '&') : $params;
        } else {
            // Send params as is (e.g., Soap XML string)
            $param_string = $params;
        }

        // Check if cURL is installed first. If so, then use cURL instead of file_get_contents.
        if (function_exists('curl_init')) {
            // Use cURL
            $curlpost = curl_init();
            curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
            curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
            curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curlpost, CURLOPT_URL, $url);
            curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
            if (!sameHostUrl($url)) {
                curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
                curl_setopt($curlpost, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
            }
            curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
            if (is_numeric($timeout)) {
                curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
            }
            // If using basic authentication = base64_encode(username:password)
            if ($basic_auth_user_pass != "") {
                curl_setopt($curlpost, CURLOPT_USERPWD, $basic_auth_user_pass);
                // curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
            }
            // If not sending as x-www-form-urlencoded, then set special header
            if ($content_type != 'application/x-www-form-urlencoded') {
                curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: " . strlen($param_string)));
            }
            // If passing headers manually, then add then
            if (!empty($headers) && is_array($headers)) {
                curl_setopt($curlpost, CURLOPT_HTTPHEADER, $headers);
            }
            // Make the call
            $response = curl_exec($curlpost);
            $info = curl_getinfo($curlpost);
            curl_close($curlpost);
            // If returns certain HTTP 400 or 500 errors, return false
            if (isset($info['http_code']) && ($info['http_code'] == 404 || $info['http_code'] == 407 || $info['http_code'] >= 500)) return false;
            if ($info['http_code'] != '0') return $response;
        }
        // Try using file_get_contents if allow_url_open is enabled .
        // If curl somehow returned http status=0, then try this method.
        if (ini_get('allow_url_fopen')) {
            // Set http array for file_get_contents
            $http_array = array('method' => 'POST',
                'header' => "Content-type: $content_type",
                'content' => $param_string
            );
            if (is_numeric($timeout)) {
                $http_array['timeout'] = $timeout; // Set timeout time in seconds
            }
            // If using basic authentication (username:password)
            if ($basic_auth_user_pass != "") {
                $http_array['header'] .= PHP_EOL . "Authorization: Basic " . base64_encode($basic_auth_user_pass);
            }
            // If using a proxy
            if (!sameHostUrl($url) && PROXY_HOSTNAME != '') {
                $http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
                $http_array['request_fulluri'] = true;
                if (PROXY_USERNAME_PASSWORD != '') {
                    $http_array['header'] .= PHP_EOL . "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD);
                }
            }

            // Use file_get_contents
            $content = @file_get_contents($url, false, stream_context_create(array('http' => $http_array)));

            // Return the content
            if ($content !== false) {
                return $content;
            } // If no content, check the headers to see if it's hiding there (why? not sure, but it happens)
            else {
                if (empty($http_response_header)) return false;
                // If header is a true header, then return false, else return the content found in the header
                return (substr($content, 0, 5) == 'HTTP/') ? false : $content;
            }
        }
        // Return false
        return false;
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
