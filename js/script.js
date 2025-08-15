const chatbotToggler = $(".chatbot-toggler");
const closeBtn = $(".close-btn");
const chatbox = $(".chatbox");
const chatInput = $(".chat-input textarea");
const sendChatBtn = $(".chat-input span");

const menuIcon = $('.menu-icon');
const dropdownMenu = $('.dropdown-menu');

let userMessage = null; // Variable to store user's message
const inputInitHeight = chatInput.prop('scrollHeight');

$( document ).ready(function() {
    // If clicked anywhere on page, close files listing box
    $(document).mouseup(function(e) {
        if (!dropdownMenu.is(e.target) && dropdownMenu.has(e.target).length === 0) {
            dropdownMenu.css("display", "none");
        }
    });

    // Clicked on files listing icon on header of chat window
    menuIcon.click(function (){
        if (dropdownMenu.css("display") == 'block') {
            dropdownMenu.css("display", "none");
        } else {
            dropdownMenu.css("display", "block");
            var fetchText = '<div style="margin: 10px;"><img alt="Fetching from Vector Store..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Fetching, Please wait...</div>';
            $(".chatbot .dropdown-menu").html(fetchText);
            var url_param = getSettingNumParam();
            // Get list of filenames from Vector Store
            $.ajax({
                cache: false,
                url: get_response_url+'&action=get_files_info'+url_param,
                success: function (data) {
                    $(".chatbot .dropdown-menu").html(data);
                },
                error:function (xhr, ajaxOptions, thrownError){

                }
            });
        }

    });

    // Clicked on "Send" icon on bottom of chat window near question
    sendChatBtn.click(function (){
        var start = new Date();
        handleChat();
        console.log("Total time is "+(new Date() - start)+" sec");
    });

    closeBtn.click(function () {
        document.body.classList.remove("show-chatbot");
    });

    chatbotToggler.click(function () {
        $.ajax({
            cache: false,
            url: get_response_url+'&action=validate_em_setup',
            success: function (data) {
                var arr = data.split("###");
                if (arr[0] == 1) {
                    document.body.classList.toggle("show-chatbot");
                    var count = arr[1];
                    if (count > 1) {
                        if ($(".div-settings").is(":hidden")) {
                            for (let i = 0; i < count; i++) {
                                // Append a new option with value (i+1) and text "Setting (i+1)"
                                $('#setting-sel').append('<option value="'+(i+1)+'">Setting '+ (i+1) +'</option>');
                                $('.div-settings').show();
                            }
                        }
                    }
                } else {
                    alert("Error: Module is not configured. Please complete set up.");
                }
            },
            error:function (xhr, ajaxOptions, thrownError){

            }
        });
    });

    $('#setting-sel').change(function () {
        var loadingText = '<div id="loading-div" style="margin-left: 10px; float: left;"><img alt="Loading Setting..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Loading Setting...</div>';
        $(this).parent().after(loadingText);
        setTimeout(function() { $("#loading-div").remove(); }, 4000);
    });
    chatInput.on( "keydown", function(e) {
        var start = new Date();
        // If Enter key is pressed without Shift key and the window
        // width is greater than 800px, handle the chat
        if (e.key === "Enter" && !e.shiftKey && window.innerWidth > 800) {
            e.preventDefault();
            handleChat();
        }
        console.log("--Total time is "+(new Date() - start)+" sec");
    });

    chatInput.on( "input", function(e) {
        if(chatInput.val().trim() != "") {
            $("#send-btn").css("color", "#DB5E69");
        } else {
            $("#send-btn").css("color", "#888");
        }
        // Adjust the height of the input textarea based on its content
        var element = chatInput[0]; // or $('#myElement').get(0);
        chatInput.height("${inputInitHeight}px");
        chatInput.height("${element.scrollHeight}px");
    });

    $(".chatbot span.sync-icon").click(function() {
        $(".status-msg").html('<img alt="Processing..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Syncing, Please wait...');
        var url_param = getSettingNumParam();
        $.ajax({
            cache: false,
            url: get_response_url+'&action=sync_to_vs'+url_param,
            success: function (data) {
                showProgress(0,0);
                if (data == 1) {
                    $(".status-msg").html('<i class="fas fa-check"></i> Completed!');
                    $(".status-msg").show().delay( 2000 ).hide(0);
                }
            },
            error:function (xhr, ajaxOptions, thrownError){

            }
        });
    });
    $("button.save").click(function() {
        var moduleDirectoryPrefix = $('#external-modules-configure-modal').data('module');

        if (moduleDirectoryPrefix == 'redcap_ai_chatbot') {
            var formData = $(this).parent().prev('div').find('input, textarea, select').serialize();

            setTimeout(function() {
                $.ajax({
                    method: 'POST',
                    url: get_response_url,
                    data: {
                        action: "upload_to_vs",
                        formData: formData
                    },
                    dataType: 'json'
                })
                .done(function(data) {
                    if (data.status != 1) {
                        //alert(data.error.message);
                    } else {
                        //alert(data.message);
                    }
                })
                .fail(function(data) {
                    //alert("fail"+JSON.stringify(data));
                })
                .always(function(data) {

                });
            }, 0);
        }
    });

    $(document).on("input", ".rc-question-area" , function(){
        if($(this).val().trim() != "") {
            $(this).next('span').css("color", "#DB5E69");
        } else {
            $(this).next('span').css("color", "#888");
        }
    });
});

function fileRCRepoDownload(doc_id, param_name)
{
    if (!isinteger(doc_id)) return;
    if (typeof param_name == 'undefined') param_name = 'id';
    window.location.href = app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:download&'+param_name+'='+doc_id;
}

function createChatLi(message, className) {
    // Create a chat <li> element with passed message and className
    const chatLi = document.createElement("li");
    chatLi.classList.add("chat", `${className}`);
    let chatContent = className === "outgoing" ? `<p></p><span><i class="fas fa-user"></i></span>` : `<span><i class="fas fa-robot"></i></span><p></p>`;
    chatLi.innerHTML = chatContent;
    chatLi.querySelector("p").innerHTML = message;
    return chatLi; // return chat <li> element
}

function generateResponse(chatElement, setupNum) {
    var url_param = '';
    if (setupNum != '') {
        url_param = '&setup_num='+setupNum;
    } else {
        url_param = getSettingNumParam();
    }

    $.ajax({
        method: 'POST',
        url: get_response_url+url_param,
        data: { prompt_text: userMessage, action: "generate"},
        dataType: 'json'
    })
    .done(function(data) {
        if (data.status != 1) {
            alert(data.error.message);
        } else {
            //typeWriterEffect(chatElement.querySelector("p"), data.message, 5); // Type into 'myDiv' with 50ms delay per character
            chatElement.querySelector("p").innerHTML = data.message;
        }
    })
    .fail(function(data) {

    })
    .always(function(data) {

    });
}

function handleChat(chatInput = '', chatbox = '', setupNum = '') {
    if (chatInput == '') {
        chatInput = $(".chat-input textarea");
        $("#send-btn").css("color", "#888");
    } else {
        chatInput.next('span').css("color", "#888");
    }
    if (chatbox == '') {
        chatbox = $(".chatbox");
    }
    userMessage = chatInput.val().trim(); // Get user entered message and remove extra whitespace
    if (!userMessage) return;

    // Clear the input textarea and set its height to default
    chatInput.val("");

    chatInput.height("${inputInitHeight}px");

    // Append the user's message to the chatbox
    chatbox.append(createChatLi(userMessage, "outgoing"));
    chatbox.scrollTop(chatbox[0].scrollHeight);

    // Display "Thinking..." message while waiting for the response
    var generateText = '<img alt="Generating..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Generating, Please wait...';
    const incomingChatLi = createChatLi(generateText, "incoming");
    chatbox.append(incomingChatLi);
    chatbox.scrollTop(chatbox[0].scrollHeight);
    generateResponse(incomingChatLi, setupNum);
}

function typeWriterEffect(elementId, newText, speed) {
    const $element = elementId;
    $element.textContent = ''; // Clear existing content
    let i = 0;

    function typeChar() {
        if (i < newText.length) {
            $element.append(newText.charAt(i));
            i++;
            setTimeout(typeChar, speed);
        }
    }

    typeChar(); // Start the typing animation
}
function getSettingNumParam() {
    var url_param = '';
    if ($(".div-settings").is(":visible")) {
        var settingNum = $('#setting-sel').find(":selected").val();
        url_param = '&setup_num='+settingNum;
    }
    return url_param;
}

function insertChatBot(name, setupNum) {
    // Utilize first setup by default
    if (setupNum == undefined || setupNum == '') setupNum = 1;

    $('head').append('<link rel="stylesheet" type="text/css" href="'+rc_chatbot_css_url+'">');

    var html = "<div style='margin-top: 10px;' class=\"rc-chatbot-container\">\n" +
        "  <div class=\"rc-chatbot-header\">\n" +
        "    REDCap Chatbot\n" +
        "  </div>\n" +
        "  <ul class=\"rc-chatbox\">\n" +
        "        <li class=\"chat incoming\">\n" +
        "            <span><i class=\"fas fa-robot\"></i></span>\n" +
        "            <p>Hi there <br>How can I help you today?</p>\n" +
        "        </li>\n" +
        "    </ul>\n" +
        "  <div class=\"rc-chatbot-input\">\n" +
        "    <textarea id=\"rc-user-input\" class=\"rc-question-area\" placeholder=\"Enter a question...\"></textarea>\n" +
        "    <span style='vertical-align: middle; padding:15px 15px 15px 0; color: #888;' onclick=\"askQuestion('"+name+"', "+setupNum+")\"><i class=\"fas fa-arrow-alt-circle-up fa-2xl\"></i></span>\n" +
        "  </div>\n" +
        "</div>";

    $('tr#'+name+'-tr').find('td:first-child div:first').append(html);
}
function askQuestion(name, setupNum) {
    handleChat($('tr#'+name+'-tr').find("#rc-user-input"), $('tr#'+name+'-tr').find(".rc-chatbox"), setupNum);
}
