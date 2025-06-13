<?php
require_once($module->getModulePath()."Classes/Api.php");

$output = ['status' => 0, 'message'   => ''];

$projectId = $module->getProjectId();

if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    $folderId = $module->getProjectSetting('folder-id');
    if (!empty($folderId)) {
        $api_key = $module->getProjectSetting('api-key');
        $endpoint = rtrim($module->getProjectSetting('endpoint'), "/") . "/";
        $api_version = $module->getProjectSetting('api-version');

        $vsId = $module->vectorStoreIdforfolder($folderId, $projectId);
        if (is_null($vsId)) {
            /*************** STEP 1: Upload a Files from folder *****************************/
            $docIds = $module->docsForFolder($folderId);
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
            $sql = "INSERT INTO redcap_folders_vector_stores_items (project_id, folder_id, vs_id)
			            VALUES ('".$projectId."', '".$folderId."', '".$vsId."')";
            db_query($sql);
        }

        /*************** STEP 4: Responses API *****************************/

        $suffix = ($module->getProjectSetting('request-suffix') != '')
                        ? $module->getProjectSetting('request-suffix')
                        : 'Answer the question based on the uploaded files only';
        //If not able to get answer from uploaded files, print "there is no information available regarding this question."
        $prompt = $_POST['prompt_text'].' '.$suffix;
        $prompt .= " Limit your response to what is asked. Do not add any additional content, such as introductory remarks, explanations, etc.!";
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
            'input' => $prompt
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
                        }
                    }
                }
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
        /*************** STEP 1: Upload a Files from folder *****************************/
        $docIds = $module->docsForFolder($folder_id);

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
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'OpenAI-Beta: assistants=v1',
            ];
            //$resFile = API::http_post_em($endpoint . "files?api-version=" . $api_version, $data, null, 'application/json', "", $headers);
            //echo "after call http post"; die;
            $resFileUpload = Api::curlAPIPost($api_key, $endpoint . "files?api-version=" . $api_version, $data, $headers);
            //$resFileUpload = json_decode($resFile, true);
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
        $sql = "INSERT INTO redcap_folders_vector_stores_items (project_id, folder_id, vs_id)
			            VALUES ('".$projectId."', '".$folder_id."', '".$vsId."')";
        db_query($sql);
    }
    if (is_null($vsId))  $vsId = "";
    $output = ['status' => 1, 'message'  => $vsId];
} else if (isset($_GET['action']) && $_GET['action'] == 'get_files_info') {
    list($folder_name, $docsList) = $module->listAllFilesInfo($module->getProjectSetting('folder-id'), $projectId);
    if (!empty($docsList)) {
        $data = '<ul>';
        $data .= '<li style="font-size: 10px; color: #666">Please specify the question to get answer based on the below files.</li>';
        if ($folder_name != '') {
            $data .= '<li><b>'.$folder_name.'</b></li>';
        }
        foreach ($docsList as $doc) {
            $data .= '<li class="submenu">'.$doc.'</li>';
        }
        $data .= '</ul>';
    }

    print $data; exit;
} else if (isset($_GET['action']) && $_GET['action'] == 'sync_to_vs') {
    $docsList = $module->listAllFilesDetails($module->getProjectSetting('folder-id'), $projectId);
    if (!empty($docsList)) {
        foreach ($docsList as $docId => $docList) {
            print "doc id".$docId."--Stored date".$docList['stored_date'];
        }
    }
    exit;
}

print json_encode(($output));