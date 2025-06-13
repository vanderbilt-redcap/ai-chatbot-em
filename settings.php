<?php
require_once($module->getModulePath()."Classes/Api.php");

$folders = $module->foldersForProject($module->getProjectId());

?>
<h1>Settings [AI Chatbot]</h1>
<p>Set below values to proceed with AI Chatbot:</p>


<form method="post" action="<?= $module->getUrl('settings.php') ?>">
    <table style="width:750px;margin-top:20px;">
        <tr>
            <td style="padding: 6px; border:1px solid #A7C3F1; color: #000066; background-color: #E2EAFA;">
                <div style="margin:0 0 10px;">Select a folder from the drop-down list below, after which using model entered we will upload all files in folder to AI so that you can utilize them in prompts afterward.</div>
                <table cellpadding="5px;">
                    <tr>
                        <td style="text-align:right;font-weight:bold;padding-top:2px;" valign="top">Enter Model (exa., gpt-turbo-4): </td>
                        <td style="" valign="top">
                            <input type="text" name="model_name">
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:right;font-weight:bold;padding-top:2px;">Select REDCap Folder: </td>
                        <td>
                            <select id="folder_id" name="folder_id">
                                <option value="">-- Select --</option>
                                <?php
                                foreach ($folders as $folder) {
                                    echo '<option ';
                                    if ($_POST['folder_id'] == $folder['folder_id']) {
                                        echo 'selected = "selected" ';
                                    }
                                    echo 'value="'.$folder['folder_id'].'">'.$folder['name'].'</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <input type="submit" name="upload-files" value="Upload Files">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</form>

<?php

if (!empty($_POST['upload-files'])) {
    echo '<p>Uploading Files...</p>';
    $api_key = $module->getProjectSetting('api-key');
    $endpoint = rtrim($module->getProjectSetting('endpoint'), "/") . "/";
    $api_version = $module->getProjectSetting('api-version');
    $response = API::getCurlCall($api_key, "https://vumc-openai-16.openai.azure.com/openai/vector_stores/vs_dfdfh/files?api-version=2025-03-01-preview");
    print_array(json_decode($response)); die;
    // VERIFY :: Get list of all vector stores
    /*$response = Api::getCurlCall($api_key, $endpoint . "vector_stores?api-version=" . $api_version);
    $res = json_decode($response);
    print_array($res); die;
    foreach ($res->data as $obj) {
        // Delete a vector store
        $result = curlAPIDelete('https://vumc-openai-16.openai.azure.com/openai/vector_stores/'.$obj->id.'?api-version=2025-03-01-preview');
        echo $result;
    }*/
    //$response = getCurlCall($api_key, "https://vumc-openai-16.openai.azure.com/openai/files?api-version=2025-03-01-preview");
    //$response = getCurlCall($api_key, "https://vumc-openai-16.openai.azure.com/openai/vector_stores?api-version=2025-03-01-preview");
    //print_array(json_decode($response));

    $folderId = $_POST['folder_id'];
    $projectId = $module->getProjectId();

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
        var_dump($resVS); die;
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

    /*************** Verify Vector Store contains file or not *****************************/
//https://vumc-openai-16.openai.azure.com/openai/vector_stores/{vector_store_id}/files?api-version=2025-03-01-preview
    //$response = getCurlCall($api_key, "https://vumc-openai-16.openai.azure.com/openai/vector_stores/".$vsId."/files?api-version=2025-03-01-preview");
    //$response = getCurlCall($api_key, "https://vumc-openai-16.openai.azure.com/openai/vector_stores?api-version=2025-03-01-preview");

    //print_array(json_decode($response)); die;
    /*************** STEP 4: Responses API *****************************/

    $suffix = ($module->getProjectSetting('request-prepend-text') != '') ? $module->getProjectSetting('request-suffix') : 'Answer the question based on the uploaded files only. If not able to get answer from uploaded files, print "there is no information available regarding this question."';
    $suffix = ' '.$suffix;
echo 'Who gets access to my data? Answer the question based on the uploaded files only.';
    $api_url = 'https://vumc-openai-16.openai.azure.com/openai/responses?api-version=2025-03-01-preview';  // Construct the correct endpoint
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
        //'input' => 'What is qualification of John? Find answer inside uploaded files only.'
        //'input' => 'Tell me about Shop in 30 words? Find answer inside uploaded files only.'
        //'input' => 'What are operating hours of shop? Find answer inside uploaded files only.'
        //'input' => 'What is qualification of Karl? Find answer inside uploaded files only.'
        //'input' => 'Answer 2+3. Find answer inside uploaded files only.'
        //'input' => 'Which data will be gathered from me at the time of joining? Find answer inside uploaded files only.'
        'input' => 'Who gets access to my data? Answer the question based on the uploaded files only.'
    ];

    $data_json = json_encode($data, JSON_UNESCAPED_SLASHES);
    $response = Api::curlAPIPost($api_key, $endpoint . "responses?api-version=" . $api_version, json_encode($data), $headers);
    print_array($response);
    die;
}
