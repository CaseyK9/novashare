// Wait for page to load
$(document).ready(function() {
    $(function() {
        $('body').bootstrapMaterialDesign();
    });
    $('.booksearch').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: '/search/books',
                dataType: "json",
                data: {
                    query: request.term
                },
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.title + " by " + item.author + " (pages: " + item.pages + ")",
                            value: item.title + " by " + item.author + " (pages: " + item.pages + ")"
                        };
                    }));
                }
            });
        },
        autoFocus: true,
        minLength: 1
    });
    $('.studentsearch').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: '/search/students',
                dataType: "json",
                data: {
                    query: request.term
                },
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.studentid + " - " + item.firstname + " " + item.lastname +
                                " (graduation year: " + item.graduation + ", grade: " + item.grade + ")",
                            value: item.studentid
                        };
                    }));
                }
            });
        },
        autoFocus: true,
        minLength: 1,
        select: function(event, ui) {
            if (!$(".studentsearch").val()) {
                console.log("Empty student.");
                $.snackbar({
                    content: "Use the search box to select a student before trying to edit a student.",
                    style: "toast",
                    timeout: 7000
                });
                return;
            }

            var opts = {
                lines: 17, // The number of lines to draw
                length: 28, // The length of each line
                width: 14, // The line thickness
                radius: 42, // The radius of the inner circle
                scale: 0.3, // Scales overall size of the spinner
                corners: 1, // Corner roundness (0..1)
                color: '#37474f', // #rgb or #rrggbb or array of colors
                opacity: 0.25, // Opacity of the lines
                rotate: 0, // The rotation offset
                direction: 1, // 1: clockwise, -1: counterclockwise
                speed: 1.6, // Rounds per second
                trail: 83, // Afterglow percentage
                fps: 30, // Frames per second when using setTimeout() as a fallback for CSS
                zIndex: 2e9, // The z-index (defaults to 2000000000)
                className: 'spinner', // The CSS class to assign to the spinner
                top: '50%', // Top position relative to parent
                left: '50%', // Left position relative to parent
                shadow: true, // Whether to render a shadow
                hwaccel: false, // Whether to use hardware acceleration
                position: 'absolute' // Element positioning
            };

            var spinner = new Spinner(opts).spin();
            $('.data-loading').each(function(i, obj) {
                obj.appendChild(spinner.el);
            });

            $(".data-loading").show();
            $("#edit-student-title").hide();
            $(".data-loaded").hide();
            $("#editstudent-editbook").modal("toggle");
            $('#edit-student-booksread tbody').empty();
            console.log("Loading data...");

            $.getJSON("/students/get", {query: ui.item.value}, function(data) {
                var sdata = data[0];
                if (data.error === "student_not_found") {
                    $.snackbar({
                        content: "You entered an invalid student. Make sure that you clicked a search result.",
                        style: "toast",
                        timeout: 7000
                    });
                    return;
                }
                $.getJSON("/students/booksread", {query: ui.item.value}, function(json) {
                    $('.booksread').append($('<option>').text("Select"));
                    $("#edit-student-booksread-new").empty();
                    $.each(json, function(i, obj){
                        console.log("Books read data #" + i, obj);
                        $("#edit-student-booksread-new")
                        .append($('<div class="card edit-book-card">' +
                            '<div class="card-height-indicator"></div>' +
                            '<div class="card-content">' +
                                '<div class="card-image">' +
                                    '<h4 class="card-image-headline">' + obj.title + '</h4>' +
                                    '<h5 class="card-image-headline">By ' + obj.author + '</h5>' +
                                '</div>' +
                                '<div class="card-body">' +
                                    '<p style="padding-top: 10px; margin-bottom: 0.5em;">Approved by <b>' + obj.lastname + '</b> on <b>' + obj.approvaldate + '</b></p>' +
                                    '<p style="padding-top: 5px;">Conferenced with <b>' + obj.lastname + '</b> on <b>' + obj.conferencedate + '</b></p>' +
                                '</div>' +
                                '<footer class="card-footer">' +
                                    '<button class="btn btn-outline-secondary edit-book" data-bookid="' + obj.bookid + '">Edit</button>' +
                                '</footer>' +
                            '</div>'));
                        $(".edit-book").click(function() {
                            console.log("Editing book with id: " + $(".edit-book").data('bookid'));
                            $(".bookconference-notes").val(obj.conference_notes);
                            $("#teacherlist").empty();
                            console.log("Loading teachers...");
                            $.getJSON("/teachers/list", {}, function(json) {
                                $.each(json, function(i, teacher) {
                                    if (obj.teacherid === teacher.id) {
                                        $("#teacherlist").append($("<option selected value='" + teacher.id + "'>" + teacher.firstname + " " + teacher.lastname + "</option>"));
                                    } else {
                                        $("#teacherlist").append($("<option value='" + teacher.id + "'>" + teacher.firstname + " " + teacher.lastname + "</option>"));
                                    }
                                });
                                console.log("Done loading teachers.");
                            });
                            $.getJSON("/books/get", {query: $(".edit-book").data('bookid')}, function(json) {
                                var obj = json[0];
                                $(".bookname").text(obj.title);
                                $(".bookpages").val(obj.pages);
                                $('#editbook-modal').modal('toggle');
                            });
                        });
                    });

                    $("#edit-student-title").text('Editing ' + sdata.firstname + " " + sdata.lastname);
                    $("#edit-student-title").show();
                    $(".data-loaded").show();
                    $(".data-loading").hide();

                    console.log("Done loading.");
                });
            });
        }
    });
    $('.teachersearch').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: '/search/students',
                dataType: "json",
                data: {
                    query: request.term
                },
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.studentid + " - " + item.firstname + " " + item.lastname +
                                " (graduation year: " + item.graduation + ", grade: " + item.grade + ")",
                            value: item.studentid + " - " + item.firstname + " " + item.lastname
                        };
                    }));
                }
            });
        },
        autoFocus: true,
        minLength: 1
    });
});
