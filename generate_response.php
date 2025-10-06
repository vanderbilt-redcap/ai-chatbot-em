<?php
require_once($module->getModulePath()."Classes/Api.php");

$output = ['status' => 0, 'message'   => ''];

$projectId = $module->getProjectId();

$settings = $module->getProjectSetting('settings');
$settingTitles = $module->getProjectSetting('setting-name');
/*$api_keys = $module->getProjectSetting('api-key');
$endpoints = $module->getProjectSetting('endpoint');
$api_versions = $module->getProjectSetting('api-version');*/
$folderIds = $module->getProjectSetting('folder-id');

$num = 0;
if (isset($_GET['setup_num'])) {
    $num = ($_GET['setup_num'] - 1);
}

/*$api_key = $api_keys[$num];
$endpoint = rtrim($endpoints[$num], "/") . "/";
$api_version = $api_versions[$num];*/
$folderId = $folderIds[$num];

$api_key = '81ce3b70e4f94439aaaf57c4682ba5f8';
$endpoint = 'https://vumc-openai-16.openai.azure.com/openai/';
$api_version = '2025-03-01-preview';


if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    $debug = true;
    if (!empty($folderId)) {
        $start_time = microtime(true);
        $vsId = $module->vectorStoreIdforfolder($folderId, $projectId);
        if (is_null($vsId)) {
            $vsId = $module->uploadFilesToVectorStore($folderId, $projectId, $endpoint, $api_key, $api_version);
        }

        /*************** STEP 4: Responses API *****************************/

        $prependText = $module->getProjectSetting('request-prepend-text')[$num] ?: "You are an assistant which answers questions based on knowledge which is provided to you. You provide accurate and concise answers. While answering, you don't use your internal knowledge, but solely the information in the uploaded files. You don't mention any reference of files in response.";
        $temperature = (float)$module->getProjectSetting("temperature")[$num] ?: 0.5;
        $max_num_results = (float)$module->getProjectSetting("max_num_results")[$num] ?: 4;
        $score_threshold = (float)$module->getProjectSetting("score_threshold")[$num] ?: 0.8;
        $max_output_tokens = (float)$module->getProjectSetting("max_output_tokens")[$num] ?: 4000;
        $prompt = $prependText
            ."<br>Answer the question below:<br>"
            .$_POST['prompt_text'];
        //echo $prompt; die;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ];
        // Example conversation
        $data = [
            'model' => 'gpt-4o-mini',
            'tools' => [
                [
                    "type" => "file_search",
                    'vector_store_ids' => [$vsId],
                    "max_num_results" => $max_num_results,
                    "ranking_options" => [
                        "score_threshold" => $score_threshold
                    ]
                ]
            ],
            'include' => ["file_search_call.results"],
            'max_output_tokens' => $max_output_tokens,
            'input' => $prompt,
            'temperature' => $temperature
        ];
        if (isset($_SESSION['prev_response_id']) && $_SESSION['prev_response_id'] != '') {
            $data['previous_response_id'] = $_SESSION['prev_response_id'];
        }

        $data_json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $response = Api::curlAPIPost($api_key, $endpoint . "responses?api-version=" . $api_version, json_encode($data), $headers);
        //print_array($response); die;
        if (is_array($response) && isset($response['output'])) {
            $_SESSION['prev_response_id'] = $response['id'];
            // Insert into log table to log question and response received
            $sql = "INSERT INTO redcap_ai_chatbot_log (project_id, folder_id, vs_id, question, response, created_at)
			            VALUES ('".$projectId."', '".$folderId."', '".$vsId."', '".db_escape($prompt)."', '".json_encode($response)."', '".NOW."')";
            db_query($sql);
            foreach ($response['output'] as $output) {
                if (isset($output['content'])) {
                    foreach ($output['content'] as $content) {
                        if (isset($content['text'])) {
                            $resText = $content['text'];
                            if (!empty($content['annotations'])) {
                                $annotation_arr[] = $content['annotations'];
                            }
                        }
                    }
                }
            }
        }

        if ($module->getProjectSetting('use-files-data')[$num] == true && empty($annotation_arr)) {
            if ($module->getProjectSetting('custom-message')[$num] != '') {
                $resText = $module->getProjectSetting('custom-message')[$num];
            } else {
                $resText = "Sorry, We are unable to provide any information based on this question.";
            }
        }
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time);
        if ($debug == true) {
            $resText .= "<br><i style='font-size: 11px; color: #666;'>Execution time: ".number_format($execution_time, 2, '.', '')." sec</i>";
        }
        $output = ['status' => 1, 'message'  => $resText];
    }
} else if (isset($_POST['action']) && $_POST['action'] == 'upload_to_vs') {
    $formData = explode("&", $_POST['formData']);
    foreach ($formData as $data) {
        list($data1, $value) = explode("=", $data);
        list($key, $index) = explode("____", $data1);
        $dataArr[$key][$index] = $value;
    }

    $total = count($dataArr['settings']);
    for ($i = 0; $i < $total; $i++) {
        if ($dataArr['settings'][$i] == true) {
            $folder_id = $dataArr['folder-id'][$i];
            $vsId = $module->vectorStoreIdforfolder($folder_id, $projectId);

            if (is_null($vsId) || $vsId == '') {
                /*$endpoint = urldecode($dataArr['endpoint'][$i]);
                $api_key = $dataArr['api-key'][$i];
                $api_version = $dataArr['api-version'][$i];*/

                $vsId = $module->uploadFilesToVectorStore($folder_id, $projectId, $endpoint, $api_key, $api_version);
            } else {
                /*$storedVSId = $module->vectorStoreIdforfolder($folderId, $projectId, false);
                if ($storedVSId != $vsId) {

                }*/
            }
            if (is_null($vsId))  $vsId = "";
        }
    }

    $output = ['status' => 1, 'message'  => $vsId];
} else if (isset($_GET['action']) && $_GET['action'] == 'get_files_info') {
    $folder_name = $module->getFolderName($folderId, $projectId);
    // Get files list from Vector store
    $storedVSId = $module->vectorStoreIdforfolder($folderId, $projectId, false);
    $response = \Api::getCurlCall($api_key, $endpoint. "vector_stores/".$storedVSId."/files?api-version=".$api_version);
    $allFiles = json_decode($response);

    if (is_array($allFiles->data) && count($allFiles->data) > 0) {
        $data = '<div>';
        $data = '<ul>';
        $data .= '<li style="font-size: 10px; color: #666">Below files (<b>fetched from vector store</b>) will be<br> utilized to answer questions.</li>';
        if ($folder_name != '') {
            $data .= '<li><b>'.$folder_name.'</b></li>';
        }
        foreach ($allFiles->data as $fileObj) {
            $resFile = \Api::getCurlCall($api_key, $endpoint. "files/".$fileObj->id."?api-version=".$api_version);
            $fileInfo = json_decode($resFile);
            $data .= '<li class="submenu">'.$fileInfo->filename.'</li>';
        }
        $data .= '</ul></div>';

        $onclickJs = "$('.chatbot .dropdown-menu').hide();";
        $data .= '<div style="float: right; font-size: 12px; padding-right: 5px;"><a href="javascript:;" onclick="'.$onclickJs.'">[X]</a></div>';
    }

    print $data; exit;
} else if (isset($_GET['action']) && $_GET['action'] == 'sync_to_vs') {
    $vsCreatedAt = $module->vectorStoreIdforfolder($folderId, $projectId, true);

    $docsList = $module->listAllFilesDetails($folderId, $projectId);
    if (!empty($docsList)) {
        foreach ($docsList as $docId => $docList) {
            $docsStoredAt[] = $docList['stored_date'];
        }
    }
    $storedFilesCount = count($docsStoredAt);

    $anyDateLater = false;

    foreach ($docsStoredAt as $date) {
        if ($date > $vsCreatedAt) { // Use comparison operators to compare DateTime objects
            $anyDateLater = true;
            break; // Stop checking once a later date is found
        }
    }

    // Get files list from Vector store
    $storedVSId = $module->vectorStoreIdforfolder($folderId, $projectId, false);
    $response = \Api::getCurlCall($api_key, $endpoint. "vector_stores/".$storedVSId."/files?api-version=".$api_version);
    $allFiles = json_decode($response);

    $vsFilesCount = (is_array($allFiles->data)) ? count($allFiles->data) : 0;

    if ($vsFilesCount != $storedFilesCount
        || $anyDateLater == true) { // At least one date in the array of docs created dates is later than the vector store created date.

        // Delete - 1. all files attached to old Vector store, 2. Vector Store, 3. existing entry of vector store ID and folder from DB
        $module->deleteVectorStore($folderId, $projectId, $storedVSId, $endpoint, $api_key, $api_version);

        // Upload files from selected folder to new vector store and add entry in mapping DB table
        $vsId = $module->uploadFilesToVectorStore($folderId, $projectId, $endpoint, $api_key, $api_version);
    }
    print "1"; exit;
} else if (isset($_GET['action']) && $_GET['action'] == 'validate_em_setup') {

    $response = 1;
    $count = count($folderIds);
    $setting_titles = [];
    for ($i = 0; $i < $count; $i++) {
        /*if (trim($folderIds[$i]) == ''
            || trim($api_keys[$i]) == ''
            || trim($endpoints[$i]) == ''
            || trim($api_versions[$i]) == '') {
            $response = 0;
            break;
        }*/
        if (trim($folderIds[$i]) == '') {
            $response = 0;
            break;
        }
        $setting_titles[] = $settingTitles[$i];
    }
    print $response."###".$count."###".json_encode($setting_titles); exit;
}

print json_encode(($output));