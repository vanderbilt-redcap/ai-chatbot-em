## REDCap RAG AI ChatBot

### Description
Prototype module for REDCap-RAG (Retrieval Augmented Generation) AI Integration. Currently working towards using OpenAI to allow users to integrate external knowledge bases (i.e. Files inside REDCap folders) during the response generation process.

### Project-Level Settings
* **REDCap Folder:** This must be set for the purpose of external source to utilize to generate response. OpenAI API will generate response based on uploaded file(s) inside selected REDCap folder.
* **OpenAI Crediential:** A valid Credientials from your Azure OpenAI instance. 
* * **OpenAI API Key**
* * **OpenAI Endpoint URL**
* * **API Model Version**
* **Text to prepend to a question (Optional):** Example: "Reformulate the response as a single paragraph."
* **Refer strictly to the uploaded file to provide response::** When checked, response will be fetched from uploaded files only. If information regarding question not present inside files, It will print custom message if set or default message. If not checked, response will be fetched from other external sources and not restricted to uploaded files.
* **Custom message to display in response if answer is not a part of any files:** To utilize this text, it is recommended to keep above checkbox checked. If empty, it will default to "Sorry, We are unable to provide any information based on this question."

### Usage
After downloading and enabling this module on your REDCap instance. User can enable this module for any project and configure settings at project-level. An chatbot icon will appear at the right bottom of each page inside a project. Clicking this icon, user can interact with AI by entering question and will get response based uploaded files inside REDCap folder selected at configuration.