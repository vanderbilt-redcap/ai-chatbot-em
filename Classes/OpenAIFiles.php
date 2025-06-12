<?php

class OpenAIFiles
{
    public function __construct($endpoint, $api_key, $api_version, $module)
    {
        $this->endpoint = rtrim($endpoint, "/") . "/";
        $this->api_key = $api_key;
        $this->api_version = $api_version;
        $this->module = $module;
    }
    public function uploadToVectorStore($folder_id, $project_id)
    {
        $vsId = $this->module->vectorStoreIdforfolder($folder_id, $project_id);

        if (is_null($vsId) || $vsId == '') {
            $endpoint = $this->endpoint;
            $api_key = $this->api_key;
            $api_version = $this->api_version;
            /*************** STEP 1: Upload a Files from folder *****************************/
            $docIds = $this->module->docsForFolder($folder_id);
            print json_encode($docIds); die;
            if (empty($docIds)) {
                print "<b>No files available in this folder.</b>";
                exit;
            }
            foreach ($docIds as $docId) {
                echo $docId."--";
                $fileAttr = \Files::getEdocContentsAttributes($docId);
                echo json_encode($fileAttr);
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
                echo json_encode($resFileUpload);
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
			            VALUES ('".$project_id."', '".$folder_id."', '".$vsId."')";
            db_query($sql);
        } else {
            print "outside if".$vsId;
        }
        die;
        return $vsId;
    }
}
