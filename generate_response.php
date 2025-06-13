<?php
require_once($module->getModulePath()."Classes/Api.php");

$output = ['status' => 0, 'message'   => ''];

$projectId = $module->getProjectId();

$api_key = $module->getProjectSetting('api-key');
$endpoint = rtrim($module->getProjectSetting('endpoint'), "/") . "/";
$api_version = $module->getProjectSetting('api-version');
$folderId = $module->getProjectSetting('folder-id');

if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    if (!empty($folderId)) {
        $vsId = $module->vectorStoreIdforfolder($folderId, $projectId);
        if (is_null($vsId)) {
            /*************** STEP 1: Upload a Files from folder *****************************/
            $docIds = $module->docsForFolder($folderId, $projectId);
            if (empty($docIds)) {
                print "<b>No files available in this folder.</b>";
                exit;
            }
            foreach ($docIds as $docId) {
                $fileAttr = \Files::getEdocContentsAttributes($docId);
                $curlFile = new \CURLStringFile($fileAttr[2], $fileAttr[1], $fileAttr[0]);
                $headers = [
                    'Content-Type: multipart/form-data',
                    'Authorization: Bearer ' . $api_key,
                ];

                $data = [
                    'purpose' => 'assistants',
                    'file' => $curlFile,
                ];
                $resFileUpload = Api::curlAPIPost($api_key, $endpoint . "files?api-version=" . $api_version, $data, $headers);
                $fileIds[] = $resFileUpload['id'];
            }
            /*************** STEP 2: Create New Vector Store *****************************/
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'OpenAI-Beta: assistants=v2',
            ];
            $data = [
                'name' => "Shop FAQ"
            ];

            $resVS = Api::curlAPIPost($api_key, $endpoint . "vector_stores?api-version=" . $api_version, json_encode($data), $headers);
            $vsId = $resVS['id'];

            /*************** STEP 3: Add File to Vector Store *****************************/

            $data = [
                'file_ids' => $fileIds
            ];
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'OpenAI-Beta: assistants=v2'
            ];
            $resVF = Api::curlAPIPost($api_key, $endpoint . "vector_stores/" . $vsId . "/file_batches?api-version=" . $api_version, json_encode($data), $headers);
            $vsfbId = $resVF['id'];

            // Insert vector store ID and folder ID in mapping DB table
            $sql = "INSERT INTO redcap_folders_vector_stores_items (project_id, folder_id, vs_id, created_at)
			            VALUES ('".$projectId."', '".$folderId."', '".$vsId."', '".NOW."')";
            db_query($sql);
        }

        /*************** STEP 4: Responses API *****************************/

        $prependText = $module->getProjectSetting('request-prepend-text') ?: "Refer to the uploaded files and provide a response that strictly adheres to its content.";

        /*$prompt = $prependText
            ."<br>Limit your response to what is asked. Do not add any additional content, such as introductory remarks, explanations, etc.!"
            ."<br>Answer the question below:<br>"
            .$_POST['prompt_text'];*/

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
            'model' => 'gpt-4.5-preview',
            'tools' => [
                [
                    "type" => "file_search",
                    'vector_store_ids' => [$vsId],
                    "max_num_results" => 20
                ]
            ],
            'input' => $prompt,
            'temperature' => 1.5
        ];

        $data_json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $response = Api::curlAPIPost($api_key, $endpoint . "responses?api-version=" . $api_version, json_encode($data), $headers);
        //print_array($response); die;
        if (is_array($response) && isset($response['output'])) {
            foreach ($response['output'] as $output) {
                if (isset($output['content'])) {
                    foreach ($output['content'] as $content) {
                        if (isset($content['text'])) {
                            $resText = $content['text'];
                            $annotation_arr = $content['annotations'];
                        }
                    }
                }
            }
        }

        if ($module->getProjectSetting('use-files-data') == true && empty($annotation_arr)) {
            if ($module->getProjectSetting('custom-message') != '') {
                $resText = $module->getProjectSetting('custom-message');
            } else {
                $resText = "Sorry, We are unable to provide any information based on this question.";
            }
        }
        $output = ['status' => 1, 'message'  => $resText];
    }
} else if (isset($_POST['action']) && $_POST['action'] == 'upload_to_vs') {
    $folder_id = $_POST['folder_id'];
    $vsId = $module->vectorStoreIdforfolder($folder_id, $projectId);

    if (is_null($vsId) || $vsId == '') {
        $endpoint = $_POST['endpoint'];
        $api_key = $_POST['api_key'];
        $api_version = $_POST['api_version'];
        $vsId = uploadFilesToVectorStore($module, $folder_id, $projectId, $endpoint, $api_key, $api_version);
    }
    if (is_null($vsId))  $vsId = "";
    $output = ['status' => 1, 'message'  => $vsId];
} else if (isset($_GET['action']) && $_GET['action'] == 'get_files_info') {
    $folder_name = $module->getFolderName($folderId, $projectId);
    // Get files list from Vector store
    $storedVSId = $module->vectorStoreIdforfolder($folderId, $projectId, false);
    $response = \Api::getCurlCall($api_key, $endpoint. "vector_stores/".$storedVSId."/files?api-version=".$api_version);
    $allFiles = json_decode($response);

    if (count($allFiles->data) > 0) {
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

    $vsFilesCount = count($allFiles->data);

    if ($vsFilesCount != $storedFilesCount
        || $anyDateLater == true) { // At least one date in the array of docs created dates is later than the vector store created date.

        // Delete existing entry of vector store ID and folder ID in mapping DB table
        $sql = "DELETE FROM redcap_folders_vector_stores_items 
                WHERE project_id = '".$projectId."' AND folder_id = '".$folderId."' AND vs_id = '".$storedVSId."'";
        db_query($sql);
        $vsId = uploadFilesToVectorStore($module, $folderId, $projectId, $endpoint, $api_key, $api_version);
    }
    print "1"; exit;
} else if (isset($_GET['action']) && $_GET['action'] == 'validate_em_setup') {
    $response = 1;
    if (trim($folderId) == ''
        || trim($api_key) == ''
        || trim($endpoint) == ''
        || trim($api_version) == '') {
        $response = 0;
    }
    print $response; exit;
}
function uploadFilesToVectorStore($module, $folder_id, $projectId, $endpoint, $api_key, $api_version) {

    /*************** STEP 1: Upload a Files from folder *****************************/
    $docIds = $module->docsForFolder($folder_id, $projectId);

    if (empty($docIds)) {
        print "<b>No files available in this folder.</b>";
        exit;
    }
    foreach ($docIds as $docId) {
        $fileAttr = \Files::getEdocContentsAttributes($docId);
        $curlFile = new \CURLStringFile($fileAttr[2], $fileAttr[1], $fileAttr[0]);
        $headers = [
            'Content-Type: multipart/form-data',
            'Authorization: Bearer ' . $api_key,
        ];

        $data = [
            'purpose' => 'assistants',
            'file' => $curlFile,
        ];

        $headers = [
            'Content-Type: multipart/form-data',
            'Authorization: Bearer ' . $api_key,
        ];

        $resFileUpload = Api::curlAPIPost($api_key, $endpoint . "files?api-version=" . $api_version, $data, $headers);
        $fileIds[] = $resFileUpload['id'];
    }
    /*************** STEP 2: Create New Vector Store *****************************/
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        'OpenAI-Beta: assistants=v2',
    ];
    $data = [
        'name' => "Shop FAQ"
    ];

    $resVS = Api::curlAPIPost($api_key, $endpoint . "vector_stores?api-version=" . $api_version, json_encode($data), $headers);
    $vsId = $resVS['id'];

    /*************** STEP 3: Add File to Vector Store *****************************/

    $data = [
        'file_ids' => $fileIds
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        'OpenAI-Beta: assistants=v2'
    ];
    $resVF = Api::curlAPIPost($api_key, $endpoint . "vector_stores/" . $vsId . "/file_batches?api-version=" . $api_version, json_encode($data), $headers);
    $vsfbId = $resVF['id'];

    // Insert vector store ID and folder ID in mapping DB table
    $sql = "INSERT INTO redcap_folders_vector_stores_items (project_id, folder_id, vs_id, created_at)
			            VALUES ('".$projectId."', '".$folder_id."', '".$vsId."', '".NOW."')";
    db_query($sql);

    return $vsId;
}
print json_encode(($output));