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
                    var settingTitles = JSON.parse(arr[2]);
                    if (count > 1) {
                        if ($(".div-settings").is(":hidden")) {
                            for (let i = 0; i < count; i++) {
                                // Append a new option with value (i+1) and text "Setting (i+1)"
                                var name = 'Setting ' + (i+1);
                                if (settingTitles[i] != '' && settingTitles[i] != null) {
                                    name = settingTitles[i];
                                }

                                $('#setting-sel').append('<option value="'+(i+1)+'"> '+ name +'</option>');
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
        $.ajax({
            cache: false,
            url: get_response_url+'&action=unset_response_id',
            success: function (data) {
                showProgress(0,0);
                if (data == 1) {
                    var settingNum = $('#setting-sel').find(":selected").val();
                    var greetText = defaultGreetText;
                    if (greetingTexts[settingNum-1]) {
                        greetText = greetingTexts[settingNum-1];
                    }
                    console.log(greetText+"###");
                    $("#greeting-text").html(greetText);

                    $("#loading-div").remove();
                    $('ul.chatbox li:not(:first-child)').remove();
                }
            },
            error:function (xhr, ajaxOptions, thrownError){

            }
        });
    });

    $("span.download-icon").click(function () {
        // Get the content of the chat box div
        var chatContent = "";
        $('.chatbox li').each(function() {
            if ($(this).hasClass("incoming")) {
                var botText = '';
                console.log($(this).find("div > div.result-text").length);
                if ($(this).find("div > div.result-text").length > 0) {
                    botText = $(this).find("div > div.result-text").clone();
                } else {
                    botText = $(this).find("div").clone();
                }
                chatContent += "Bot: " + botText // Clone the element to avoid modifying the original DOM
                    .find('i') // Find all <i> tags within the clone to remove execution time part
                    .remove() // Remove them
                    .end() // Go back to the cloned <div> element
                    .text() + "\n\n";
            } else {
                chatContent += user_name+": " + $(this).find("div").text() + "\n\n";
            }
        });

        // Create a Blob from the content with the text/plain MIME type
        const blob = new Blob([chatContent], { type: 'text/plain' });

        // Create a temporary anchor element
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'chat_log.txt'; // Set the default filename

        // Append the anchor to the body, click it, and then remove it
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

    });

    chatInput.on( "keydown", function(e) {
        var start = new Date();
        // If Enter key is pressed without Shift key and the window
        // width is greater than 800px, handle the chat
        if (e.key === "Enter" && !e.shiftKey && window.innerWidth > 800) {
            e.preventDefault();
            handleChat();
        }
    });

    chatInput.on( "input", function(e) {
        if(chatInput.val().trim() != "") {
            $("#send-btn").css("color", "#DB5E69");
        } else {
            $("#send-btn").css("color", "#888");
        }
        autoResizeInputBox();
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

    // Copy-to-clipboard action
    $(document).on("click", ".btn-copy-clipboard" , function(){
        copyResponseToClipboard($(this));
    });
});

function copyResponseToClipboard(ob) {
    var element = ob.parent().next('div.result-text');
    var $temp = $("<textarea>");
    $("body").append($temp);
    $temp.val(element.text().trim()).select();
    document.execCommand("copy");
    $temp.remove();

    element.animate({
        backgroundColor: '#F6EABA'
    }, 1000, function() { // Callback after highlight animation completes
        setTimeout(() => {
            element.animate({
                backgroundColor: 'transparent'
            }, 1000);
        }, 1000);
    });

    // Create progress element that says "Copied!" when clicked
    var rndm = Math.random()+"";
    var copyid = 'clip'+rndm.replace('.','');
    $('.clipboardSaveProgress').remove();
    var clipSaveHtml = '<span class="clipboardSaveProgress" style="font-size: 11px;" id="'+copyid+'"><i class="fas fa-check"></i></span>';
    ob.before(clipSaveHtml);
    $('#'+copyid).toggle('fade','fast');
    setTimeout(function(){
        $('#'+copyid).toggle('fade','fast',function(){
            $('#'+copyid).remove();
        });
    },2000);
}

function autoResizeInputBox() {
    // Reset the height to 'auto' to correctly calculate the new scrollHeight
    chatInput.height('auto');

    // Set the height to the new scrollHeight, but clamp it at the max-height
    // We get the max-height from the element's computed style
    const maxHeight = parseFloat(window.getComputedStyle(chatInput[0]).maxHeight) - 15;
    var scrollHeight = chatInput[0].scrollHeight - 15;
    if (scrollHeight > maxHeight) {
        chatInput.height(`${maxHeight}px`);
        chatInput.css({
            overflowY: "auto"
        });
    } else {
        chatInput.height(`${scrollHeight}px`);
        chatInput.css({
            overflowY: "scroll"
        });
    }

}
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
    let chatContent = className === "outgoing" ? `<div></div><span><i class="fas fa-user"></i></span>` : `<span><i class="fas fa-robot"></i></span><div></div>`;
    chatLi.innerHTML = chatContent;
    chatLi.querySelector("div").innerHTML = message;
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
            chatElement.querySelector("div").innerHTML = data.message;
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

    chatInput.height('auto');

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
    if ($('tr#'+name+'-tr').length > 0) { // Execute this script only if form contain that field
        // Utilize first setup by default
        if (setupNum == undefined || setupNum == '') setupNum = 1;
        var settingTitle = 'REDCap Chatbot';
        if (settingTitles[setupNum-1] != '' && settingTitles[setupNum-1] != null) {
            settingTitle = settingTitles[setupNum-1];
        }
        var greetingText = 'Hi there <br>How can I help you today?';
        if (greetingTexts[setupNum-1] != '' && greetingTexts[setupNum-1] != null) {
            greetingText = greetingTexts[setupNum-1];
        }
        $('head').append('<link rel="stylesheet" type="text/css" href="'+rc_chatbot_css_url+'">');

        var html = "<div style='margin-top: 10px;' class=\"rc-chatbot-container\">\n" +
            "  <div class=\"rc-chatbot-header\">\n" +
            settingTitle+"\n" +
            "  </div>\n" +
            "  <ul class=\"rc-chatbox\">\n" +
            "        <li class=\"chat incoming\">\n" +
            "            <span><i class=\"fas fa-robot\"></i></span>\n" +
            "            <p>"+greetingText+"</p>\n" +
            "        </li>\n" +
            "    </ul>\n" +
            "  <div class=\"rc-chatbot-input\">\n" +
            "    <textarea id=\"rc-user-input\" class=\"rc-question-area\" placeholder=\"Enter a question...\"></textarea>\n" +
            "    <span style='vertical-align: middle; padding:15px 15px 15px 0; color: #888;' onclick=\"askQuestion('"+name+"', "+setupNum+")\"><i class=\"fas fa-arrow-alt-circle-up fa-2xl\"></i></span>\n" +
            "  </div>\n" +
            "</div>";

        if ($('tr#'+name+'-tr').find('td:first-child div:first').length > 0) {
            $('tr#'+name+'-tr').find('td:first-child div:first').append(html);
        } else {
            $('tr#'+name+'-tr').find('td:nth-child(2) div:first').append(html);
        }
    }
}
function askQuestion(name, setupNum) {
    handleChat($('tr#'+name+'-tr').find("#rc-user-input"), $('tr#'+name+'-tr').find(".rc-chatbox"), setupNum);
}
