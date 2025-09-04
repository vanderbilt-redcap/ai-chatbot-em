<?php

namespace Vanderbilt\REDCapAIChatbotModule;

use ExternalModules\AbstractExternalModule;
use Api;

/**
 * ExternalModule class for REDCap RAG-AI Chatbot.
 * 
 */
class REDCapAIChatbotModule extends AbstractExternalModule {
    public function __construct()
    {
        parent::__construct();
        define("MODULE_DOCROOT", $this->getModulePath());

        $this->configPID = $this->getSystemSetting('config-pid');
        $this->currentPID = isset($_GET['pid']) ? $_GET['pid'] : $this->configPID;

    }
    function redcap_every_page_top($project_id) {
        unset($_SESSION['prev_response_id']);
        if (!is_null($project_id)) {
            ?>
            <link rel="stylesheet" href="<?php echo $this->getUrl('ai_chat/style.css'); ?>">
            <script>
                var get_response_url = "<?php echo $this->getUrl('generate_response.php'); ?>";
                var rc_chatbot_css_url = "<?php echo $this->getUrl('ai_chat/rc_chatbot.css'); ?>"
            </script>
            <script src="<?php echo $this->getUrl('js/script.js'); ?>" defer></script>

            <?php
            include "ai_chat/index.html";
        }
        // Insert chatbot on data entry forms or survey page
        if (PAGE == 'DataEntry/index.php' || PAGE == 'surveys/index.php') {
            $this->appendChatBotToFields();
        }
    }


    public function appendChatBotToFields() {
        $settings = $this->getProjectSetting('settings');
        $fields = $this->getProjectSetting('redcap-field');
        foreach ($settings as $num => $setting) {
            if ($setting == true) {
                if (is_array($fields[$num]) && !empty($fields[$num])) {
                    foreach ($fields[$num] as $field) {
                        ?>
                        <script type="text/javascript">
                            var settingTitles = <?php echo json_encode($this->getProjectSetting('setting-name')); ?>;
                            $(function(){ setTimeout(function(){ insertChatBot("<?=$field?>", "<?=($num+1)?>") },500); });</script>
                        <?php
                    }
                }
            }
        }
    }

    /**
     * List all project folders.
     *
     * @param $project_id
     * @return array|int
     * @see /redcap_vX.X.X/Design/online_designer.php
     */
    public function foldersForProject($project_id) {
        $sql = "SELECT folder_id, name
                FROM redcap_docs_folders WHERE project_id = '" . db_escape($project_id) . "'
                AND parent_folder_id IS NULL
			    ORDER BY folder_id";
        return $this->query($sql);
    }

    /**
     * Return Vector Store ID is already have been created for a folder
     *
     * @param $folder_id
     * @param $project_id
     * @param $returnCreatedTime
     * @return int
     *
     */
    public function vectorStoreIdforfolder($folder_id, $project_id, $returnCreatedTime = false)
    {
        $field = ($returnCreatedTime == true) ? 'created_at' : 'vs_id';
        $sql = "SELECT ".$field."
                FROM redcap_folders_vector_stores_items WHERE project_id = '" . db_escape($project_id) . "'
                AND folder_id = '" . db_escape($folder_id) . "'
			    ORDER BY folder_id";
        $result = $this->query($sql);
        $return_val = $result->fetch_assoc()[$field];

        return $return_val;
    }

    /**
     * List all documents inside a folder
     *
     * @param $folder_id
     * @param $project_id
     * @return array
     */
    public function docsForFolder($folder_id, $project_id)
    {
        $docIds = [];

        $sql = "select de.doc_id
                from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
                left join redcap_docs_attachments a on a.docs_id = d.docs_id
                left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
                left join redcap_docs_folders f on ff.folder_id = f.folder_id
                where d.project_id = $project_id and f.folder_id = $folder_id and d.export_file = 0 and a.docs_id is null
                and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.delete_date is null and e.date_deleted_server is null";
        $result = $this->query($sql);

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $docIds[] = $row['doc_id'];
        }
        return $docIds;
    }

    /**
     * Get folder name and all documents links inside a folder
     *
     * @param $folder_id
     * @param $project_id
     * @return array
     */
    public function listAllFilesInfo($folder_id, $project_id)
    {
        $sql = "select d.docs_id, d.docs_name, f.name
                            from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
                            left join redcap_docs_attachments a on a.docs_id = d.docs_id
                            left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
                            left join redcap_docs_folders f on ff.folder_id = f.folder_id
                            where d.project_id = $project_id and f.folder_id = $folder_id and d.export_file = 0 and a.docs_id is null
                            and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.delete_date is null and e.date_deleted_server is null";

        $result = $this->query($sql);

        $docsList = [];
        foreach ($result as $i => $arr) {
            $doc_id = $arr['docs_id'];
            $filename = $arr['docs_name'];
            $folder_name = $arr['name'];
            $docsList[] = "<a id='file-download-$doc_id' href='javascript:;' class='fs14' title='Click to download' onclick=\"fileRCRepoDownload($doc_id);\">".htmlentities($filename)."</a>";
        }
        return [$folder_name, $docsList];
    }

    /**
     * Return list of all documents details inside a folder
     *
     * @param $folder_id
     * @param $project_id
     * @return array
     */
    public function listAllFilesDetails($folder_id, $project_id)
    {
        $sql = "select d.docs_id, d.docs_name, d.docs_size, e.stored_date, d.docs_comment, ff.folder_id, e.delete_date, e.doc_id
                from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
                left join redcap_docs_attachments a on a.docs_id = d.docs_id
                left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
                left join redcap_docs_folders f on ff.folder_id = f.folder_id
                where d.project_id = $project_id and f.folder_id = $folder_id and d.export_file = 0 and a.docs_id is null
                and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.delete_date is null and e.date_deleted_server is null";

        $result = $this->query($sql);

        $docsList = [];
        foreach ($result as $i => $arr) {
            $doc_id = $arr['docs_id'];
            $stored_date = $arr['stored_date'];
            $docsList[$doc_id]['stored_date'] = $stored_date;
        }
        return $docsList;
    }

    /**
     * Get list of all files stored at vector store at azure portal
     *
     * @param $api_key
     * @param $endpoint
     * @return array
     */
    public function getFilesListStoredAtVectorStore($api_key, $endpoint) {
        $response = \Api::getCurlCall($api_key, $endpoint);
        $allFiles = json_decode($response);
        return $allFiles;
    }

    /**
     * Get folder name by folder id
     *
     * @param $folder_id
     * @param $project_id
     * @return string
     */
    public function getFolderName($folder_id, $project_id)
    {
        if (!isinteger($folder_id)) return null;
        // Get the name of this folder and return HTML link and div
        $sql = "select name from redcap_docs_folders where folder_id = $folder_id and project_id = ".$project_id;
        $q = db_query($sql);
        return (db_num_rows($q) ? db_result($q, 0, "name") : null);
    }

    /**
     * Upload Files to Vector store via API upon selecting folder at configuration and return vs ID stored at DB
     *
     * @param $folder_id
     * @param $projectId
     * @param $endpoint
     * @param $api_key
     * @param $api_version
     *
     * @return array
     */
    function uploadFilesToVectorStore($folder_id, $projectId, $endpoint, $api_key, $api_version) {
        /*************** STEP 1: Upload a Files from folder *****************************/
        $docIds = $this->docsForFolder($folder_id, $projectId);

        if (empty($docIds)) {
            print "<b>No files available in this folder.</b>";
            exit;
        }
        foreach ($docIds as $docId) {
            $fileAttr = \Files::getEdocContentsAttributes($docId);
            $curlFile = new \CURLStringFile($fileAttr[2], $fileAttr[1], $fileAttr[0]);
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
        $folder_name = $this->getFolderName($folder_id, $projectId);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'name' => $folder_name
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

    /**
     * Delete Vector store and files attached to a vector store via API upon selecting another folder at configuration or clicked sync button
     *
     * @param $folderId
     * @param $projectId
     * @param $vsId
     * @param $endpoint
     * @param $api_key
     * @param $api_version
     *
     * @return void
     */
    function deleteVectorStore($folderId, $projectId, $vsId, $endpoint, $api_key, $api_version) {
        /************ Step 1: Delete files attached to Vector Store via API call ************************************/
        $response = API::getCurlCall($api_key, $endpoint . "vector_stores/" . $vsId . "/files?api-version=". $api_version);
        $result = json_decode($response);
        if (is_array($result->data) && count($result->data) > 0) {
            foreach ($result->data as $res) {
                $fileId = $res->id;
                $res = API::deleteCurlCall($api_key, $endpoint . "files/" . $fileId . "?api-version=".$api_version);
                $result = json_decode($res);
                $resArr[] = $result->deleted; // For debug purpose
            }
        }
        /************ Step 2: Delete a Vector Store via API call ************************************/
        $result = API::deleteCurlCall($api_key, $endpoint . "vector_stores/" . $vsId . "?api-version=".$api_version);
        $res = json_decode($result); // For debug purpose

        /************ Step 3: Delete existing entry of vector store ID and folder ID in mapping DB table ************************************/
        $sql = "DELETE FROM redcap_folders_vector_stores_items 
                WHERE project_id = '".$projectId."' AND folder_id = '".$folderId."' AND vs_id = '".$vsId."'";
        db_query($sql);
    }
}