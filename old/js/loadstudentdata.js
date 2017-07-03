function loadStudentData() {
    if (!$("#studentsearch").val()) {
        $.snackbar({
            content: "Use the search box to select a student before trying to edit a student.",
            style: "toast",
            timeout: 7000
        });
        return;
    }
    $.getJSON("/students/get", {query: $("#studentsearch").val().split(" ")[0]}, function(data) {
        var name = $("#studentsearch").val().split(" ");
        var sdata = data[0];
        if (data.error === "student_not_found" || !(sdata.firstname === name[2] && sdata.lastname === name[3])) {
            $.snackbar({
                content: "You entered an invalid student. Make sure to click on a search result and then edit the student.",
                style: "toast",
                timeout: 7000
            });
            return;
        }
        $.getJSON("/students/booksread", {query: $("#studentsearch").val().split(" ")[0]}, function(json) {
            $('#booksread').empty();
            $('#booksread').append($('<option>').text("Select"));
            $.each(json, function(i, obj){
                $('#booksread').append($('<option>').text(obj.title + " by " + obj.author + " (approved by " + obj.lastname + ")").attr('value', obj.bookid));
            });
        });
        $("#edit-student-title").text('Editing ' + sdata.firstname + " " + sdata.lastname);
        $("#editstudent").modal("toggle");
    });
}