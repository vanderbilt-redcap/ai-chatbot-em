## REDCap RAG AI ChatBot

### Description
Prototype module for REDCap-RAG (Retrieval Augmented Generation) AI Integration. Currently working towards using OpenAI to allow users to integrate external knowledge bases (i.e. Files inside REDCap folders) during the response generation process.

### Project-Level Settings
* **REDCap Folder:** This must be set for the purpose of external source to utilize to generate response. OpenAI API will generate response based on uploaded file(s) inside selected REDCap folder.
* **OpenAI Crediential:** A valid Credientials from your Azure OpenAI instance. 
* * **OpenAI API Key**
* * **OpenAI Endpoint URL**
* * **API Model Version**
* **Enter Suffix:** Add Prompt text to append at the end of question asked in chat window to get response from uploaded files in REDCap folder. E.g. "Answer the question based on the uploaded files only."

### Usage
After downloading and enabling this module on your REDCap instance. User can enable this module for any project and configure settings at project-level. An chatbot icon will appear at the right bottom of each page inside a project. Clicking this icon, user can interact with AI by entering question and will get response based uploaded files inside REDCap folder selected at configuration.