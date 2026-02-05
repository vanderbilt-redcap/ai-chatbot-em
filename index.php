<?php

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
    </div>

    <style>
        /* make the display the full width */
        div#outer
        {
            width: 50%;
        }

        #pagecontainer
        {
            max-width: 80%;
            /*cursor: progress;*/
        }

        table thead tr th {
            background-color: #aed8ff;

        }

        table thead tr td  {
            background-color: #aed8ff;

        }

        table.dataTable tbody tr:hover td  {
            background-color: #b7b7b7 !important;
        }

        .deleteTableHeader {
            background-color: #fd3e3e !important;
        }

        .deleteTableRow {
            background-color: #ff9e9e !important;
        }

        .restoreTableHeader {
            background-color: #02c139 !important;
        }

        .restoreTableRow {
            background-color: #8cd58c !important;
        }

        thead input {
            width: 100%;
        }

        /* The Modal (background) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: scroll; /* Enable scroll if needed */
            background-color: rgb(0,0,0); /* Fallback color */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Modal Content/Box */
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto; /* 15% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 40%; /* Could be more or less, depending on screen size */
        }

        /* The Close Button */
        button .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;

        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .tableFormatting {
            border: 1px solid black;
            padding: 3px;
        }

        .buttonFormatting {
            border: 0;
            border-radius: 10px;

            background-color: #aed8ff;
        }

        .bigButtonFormatting {
            font-size: 20px;
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