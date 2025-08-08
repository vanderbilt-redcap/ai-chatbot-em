## REDCap RAG AI ChatBot

### Description
Prototype module for REDCap-RAG (Retrieval Augmented Generation) AI Integration. Currently working towards using OpenAI to allow users to integrate external knowledge bases (i.e. Files inside REDCap folders) during the response generation process.

### Project-Level Settings
* **REDCap Folder:** This must be set for the purpose of external source to utilize to generate response. OpenAI API will generate response based on uploaded file(s) inside selected REDCap folder.
* **OpenAI Crediential:** A valid Credientials from your Azure OpenAI instance. 
  * **OpenAI API Key**
  * **OpenAI Endpoint URL**
  * **API Model Version**
* **Text to prepend to a question (Optional):** Example: "Reformulate the response as a single paragraph."
* **Refer strictly to the uploaded file to provide response::** When checked, response will be fetched from uploaded files only. If information regarding question not present inside files, It will print custom message if set or default message. If not checked, response will be fetched from other external sources and not restricted to uploaded files.
* **Custom message to display in response if answer is not a part of any files:** To utilize this text, it is recommended to keep above checkbox checked. If empty, it will default to "Sorry, We are unable to provide any information based on this question."
* **Additional Settings:** Optional. Additional settings for testing purpose only.
  * **Temperature** Default: 0.5, Value should be between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic.
  * **Max Num Results** Default: 0.8, Value should be a number between 0 and 1. Numbers closer to 1 will attempt to return only the most relevant results, but may return fewer results.
  * **Score Threshold** Default: 0.8, Value should be a number between 0 and 1. Numbers closer to 1 will attempt to return only the most relevant results, but may return fewer results.
  * **Max Output Tokens** Default: 4000, An upper bound for the number of tokens that can be generated for a response, including visible output tokens and reasoning tokens.

### Usage
After downloading and enabling this module on your REDCap instance. User can enable this module for any project and configure settings at project-level. An chatbot icon will appear at the right bottom of each page inside a project. Clicking this icon, user can interact with AI by entering question and will get response based uploaded files inside REDCap folder selected at configuration.