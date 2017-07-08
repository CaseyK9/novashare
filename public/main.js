var dialog = document.querySelector('dialog');
var showModalButton = document.querySelector('.show-modal');
if (!dialog.showModal) {
    dialogPolyfill.registerDialog(dialog);
}
showModalButton.addEventListener('click', function() {
    $.ajax({url: "/user/apikey", success: function(result){
        $("#apikey").text(result);
    }});
    dialog.showModal();
});
dialog.querySelector('.close').addEventListener('click', function() {
    dialog.close();
});
