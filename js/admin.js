document.addEventListener('DOMContentLoaded', function() {

    // Editor Heading
    const textInput = document.getElementById('title');
    const submitButton = document.getElementById('editor-header-button');
    const initialValue = textInput.value;

    textInput.addEventListener('input', () => {
        if (textInput.value !== "" && textInput.value.trim() !== initialValue) {
            submitButton.disabled = false;
        } else {
            submitButton.disabled = true;
        }
    });

});