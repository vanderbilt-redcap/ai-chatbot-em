<?php
// Available ONLY for super users / admins
if(SUPER_USER == "1") {

    $page = new HtmlPage();
    $page->PrintHeaderExt();
    include APP_PATH_VIEWS . 'HomeTabs.php';

    $jsObject = $module->generateJavascriptObject();

    ?>

    <script>

        let UIOWA_AICHAT = JSON.parse(<?= json_encode($jsObject) ?>.replaceAll("&lt;", "<")
            .replaceAll("&gt;", ">")
            .replaceAll("&quot;", '"').replaceAll("&amp;", "&"));

    </script>

    <div id='aichatContainer' style='padding-top: 55px; padding-bottom: 10px;'>
        <div id="aichatTopContainer" style='text-align:center'>
            <h2>Chatbot Logging</h2>
            <hr/>
            <div>
                Logging Details
            </div>

        </div>
    </div>
    
    <div id="aichatTopContainer" style='text-align:left'>
        <table id="aichatTable"></table>
    </div>

    <style>
        /* make the display the full width */
        div#outer
        {
            width: 50%;
        }

        #pagecontainer
        {
            max-width: 90%;
            /*cursor: progress;*/
        }
        table thead tr th {
            background-color: #DB707E;
            color: #ffffff;
        }

        table thead tr td  {
            background-color: #dcf8c6;
        }

        table.dataTable tbody tr:hover td  {
            background-color: #e0e0e0 !important;
        }

        thead input {
            width: 100%;
        }

    </style>

    <script src="<?= $module->getUrl("/js/logging.js") ?>"></script>

    <script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/select/1.6.2/js/dataTables.select.min.js"></script>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/select/1.6.2/css/select.dataTables.min.css"/>

    <?php

} else {
    echo "Something went wrong";
}