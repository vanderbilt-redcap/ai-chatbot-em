<?php

namespace Vanderbilt\REDCapAIChatbotModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

/**
 * ExternalModule class for Instance-Type Indicator.
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
        if (!is_null($project_id)) {
            ?>
            <link rel="stylesheet" href="<?php echo $this->getUrl('ai_chat/style.css'); ?>">
            <script>
                var get_response_url = "<?php echo $this->getUrl('generate_response.php'); ?>";
            </script>
            <script src="<?php echo $this->getUrl('js/script.js'); ?>" defer></script>

            <?php
            include "ai_chat/index.html";
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
     * @return array|int
     * @see /redcap_vX.X.X/Design/online_designer.php
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
     * @return array|int
     * @see /redcap_vX.X.X/Design/online_designer.php
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
     * List all documents inside a folder
     *
     * @param $folder_id
     * @param $project_id
     * @return array|int
     * @see /redcap_vX.X.X/Design/online_designer.php
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
     * List all documents inside a folder
     *
     * @param $folder_id
     * @param $project_id
     * @return array|int
     * @see /redcap_vX.X.X/Design/online_designer.php
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

    public function getFilesListStoredAtVectorStore($api_key, $endpoint) {
        $response = \Api::getCurlCall($api_key, $endpoint);
        $allFiles = json_decode($response);
        return $allFiles;
    }

    public function getFolderName($folder_id, $project_id)
    {
        if (!isinteger($folder_id)) return null;
        // Get the name of this folder and return HTML link and div
        $sql = "select name from redcap_docs_folders where folder_id = $folder_id and project_id = ".$project_id;
        $q = db_query($sql);
        return (db_num_rows($q) ? db_result($q, 0, "name") : null);
    }
}