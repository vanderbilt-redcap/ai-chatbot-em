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
            // Get list of filenames from Vector Store
            $.ajax({
                cache: false,
                url: get_response_url+'&action=get_files_info',
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
                if (data == 1) {
                    document.body.classList.toggle("show-chatbot");
                } else {
                    alert("Error: Module is not configured. Please complete set up.");
                }
            },
            error:function (xhr, ajaxOptions, thrownError){

            }
        });
    });

    chatInput.on( "keydown", function(e) {
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
        // Adjust the height of the input textarea based on its content
        var element = chatInput[0]; // or $('#myElement').get(0);
        chatInput.height("${inputInitHeight}px");
        chatInput.height("${element.scrollHeight}px");
    });

    $(".chatbot span.sync-icon").click(function() {
        $(".status-msg").html('<img alt="Processing..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Syncing, Please wait...');
        $.ajax({
            cache: false,
            url: get_response_url+'&action=sync_to_vs',
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
            setTimeout(function() {
                $.ajax({
                    method: 'POST',
                    url: get_response_url,
                    data: {
                        action: "upload_to_vs",
                        folder_id: $('select[name="folder-id"]').val(),
                        api_key: $('input[name="api-key"]').val(),
                        endpoint: $('input[name="endpoint"]').val(),
                        api_version: $('input[name="api-version"]').val()
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

function generateResponse(chatElement) {
    $.ajax({
        method: 'POST',
        url: get_response_url,
        data: { prompt_text: userMessage, action: "generate"},
        dataType: 'json'
    })
    .done(function(data) {
        if (data.status != 1) {
            alert(data.error.message);
        } else {
            chatElement.querySelector("p").textContent = data.message;
        }
    })
    .fail(function(data) {

    })
    .always(function(data) {

    });
}

function handleChat() {
    userMessage = chatInput.val().trim(); // Get user entered message and remove extra whitespace
    if (!userMessage) return;

    // Clear the input textarea and set its height to default
    chatInput.val("");
    $("#send-btn").css("color", "#888");
    chatInput.height("${inputInitHeight}px");

    // Append the user's message to the chatbox
    chatbox.append(createChatLi(userMessage, "outgoing"));
    chatbox.scrollTop(chatbox[0].scrollHeight);

    setTimeout(() => {
        // Display "Thinking..." message while waiting for the response
        var generateText = '<img alt="Generating..." src="' + app_path_images + 'progress_circle.gif">&nbsp; Generating, Please wait...';
        const incomingChatLi = createChatLi(generateText, "incoming");
        chatbox.append(incomingChatLi);
        chatbox.scrollTop(chatbox[0].scrollHeight);
        generateResponse(incomingChatLi);
    }, 600);
}
