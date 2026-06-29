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

// Load the .env file from your project root
$module->loadEnv(__DIR__ . '/api-variables.env');

$api_key = $_ENV['API_KEY'];
$endpoint = $_ENV['API_ENDPOINT'];
$api_version = $_ENV['API_VERSION'];
$api_model = $_ENV['API_MODEL'];

if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    $debug = true;
    if (!empty($folderId)) {
        $start_time = microtime(true);
        $vsId = $module->vectorStoreIdforfolder($folderId, $projectId);
        if (is_null($vsId)) {
            $vsId = $module->uploadFilesToVectorStore($folderId, $projectId, $endpoint, $api_key, $api_version);
        }

        /*************** STEP 4: Responses API *****************************/
        //$systemPrompt = "You are an AI assistant that answers questions strictly based on the provided documents in the vector store. Do not use any external or general knowledge. If the answer is not in the documents, state you cannot find the information in the provided context. You don't mention any reference of files in response.";
        //$strictText = $module->getProjectSetting('use-files-data')[$num] == true ? "Do not use any external or general knowledge." : "";$strictText = $module->getProjectSetting('use-files-data')[$num] == true ? "Do not use any external or general knowledge." : "";
        //$systemPrompt = "You are an assistant which answers questions strictly based on knowledge which is provided documents in the vector store. You provide accurate and concise answers. ".$strictText." You don't mention any reference of files in response.";

        if ($module->getProjectSetting('custom-message')[$num] != '') {
            $messageText = $module->getProjectSetting('custom-message')[$num];
        } else {
            $messageText = "Sorry, We are unable to provide any information based on this question.";
        }

        $prependText = $module->getProjectSetting('request-prepend-text')[$num];
        if ($module->getProjectSetting('request-prepend-system-text')[$num] != '') {
            $systemPrompt = $module->getProjectSetting('request-prepend-system-text')[$num];
        } else {
            $systemPrompt = "You are an assistant which answers questions based on knowledge which is provided to you. You provide accurate and concise answers. While answering, you don't use your internal knowledge, but solely the information in the uploaded files. You don't mention any reference of files in response.";
        }

        $temperature = (float)$module->getProjectSetting("temperature")[$num] ?: 0.5;
        $max_num_results = (float)$module->getProjectSetting("max_num_results")[$num] ?: 4;
        $score_threshold = (float)$module->getProjectSetting("score_threshold")[$num] ?: 0.8;
        $max_output_tokens = (float)$module->getProjectSetting("max_output_tokens")[$num] ?: 4000;
        $resText = "";
        if (!empty($prependText))  $prependText = '<br>'.$prependText;
        $prompt = $systemPrompt.$prependText
            ."<br>Answer the question below:<br>"
            .$_POST['prompt_text'];
        //echo $prompt; die;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ];
        // Example conversation
        $data = [
            'model' => $api_model,
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

        $userResText = $resText;
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time);
        $executionInfoText = '';
        if ($debug == true) {
            $executionInfoText = "<i style='font-size: 11px; color: #666;'>Execution time: ".number_format($execution_time, 2, '.', '')." sec</i>";
        }
        $setup_name = $settingTitles[$num];
        if (defined('USERID')) {
            $username = USERID;
            $from_survey = 0;
        } else {
            $username = '';
            $field = $module->getProjectSetting("survey_identifier")[$num];
            $hash = (!is_null($_POST['survey_hash'])) ? $_POST['survey_hash'] : "";
            if ($hash != '') {
                // Ensure that hash exists. Retrieve ALL survey-related info and make all table fields into global variables
                $sql = "select r.record from redcap_surveys_response r, redcap_surveys_participants p 
				where p.hash = '".db_escape($hash)."' and p.participant_id = r.participant_id 
				and p.participant_email is not null limit 1";
                $q = db_query($sql);
                $record = db_num_rows($q) ? db_result($q, 0) : false;

                $data = \REDCap::getData([
                    "project_id" => $projectId,
                    "records" => [$record],
                    "fields" => [$field],
                    "return_format" => "json-array"
                ]);
                foreach($data as $recordDetails) {
                    if ($recordDetails[$field] != '') {
                        $username = $recordDetails[$field];
                    }
                }
            }
            $from_survey = 1;
        }

        $sql = "INSERT INTO redcap_ai_chatbot_log (project_id, username, folder_id, setup_name, vs_id, question, user_question, response, user_response, execution_time, session_id, created_at, from_survey)
			            VALUES ('".$projectId."', '".$username."', '".$folderId."', '".db_escape($setup_name)."', '".$vsId."', '".db_escape($prompt)."', '".db_escape($_POST['prompt_text'])."', '".db_escape(json_encode($response))."', '".db_escape($userResText)."', '".$execution_time."', '".session_id()."', '".NOW."', '".$from_survey."')";
        db_query($sql);

        $resultText = "<div class='table-container'>
                        <div class='table-row' style='float: right; font-size: 11px;'>
                            <button type='button' class='btn btn-xs btn-copy-clipboard' title='Copy to clipboard' style='padding:3px 8px 3px 6px; font-size: 11px; color: #666;'>
                                <i class='fas fa-copy'></i> Copy</button>
                        </div>
                        <div class='table-row result-text'>".nl2br($resText)."</div>
                        <div class='table-row'>".$executionInfoText."</div>
                    </div>";
        $output = ['status' => 1, 'message'  => $resultText];
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
    if (is_null($folderIds))  return 0;
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
} else if (isset($_GET['action']) && $_GET['action'] == 'unset_response_id') {
    unset($_SESSION['prev_response_id']);
    print "1"; exit;
}

print json_encode(($output));