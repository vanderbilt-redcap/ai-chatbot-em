<?php

$module = new \Vanderbilt\REDCapAIChatbotModule\REDCapAIChatbotModule();

if(SUPER_USER == "1") {
    call_user_func(array($module, 'getLoggingData'), $_POST);
} else {
    echo "Something went wrong";
}



