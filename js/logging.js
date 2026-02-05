$(document).ready(function () {
    let currentRowsChecked = [];

    const url = new URL(window.location.href);
    $("#aichatSubmitCustom").css({ visibility: "hidden" });

    const getQueryData = new URLSearchParams();

    getQueryData.append("redcap_csrf_token", UIOWA_AICHAT.redcap_csrf_token);

    fetchDataAndLoadTable();

    function fetchDataAndLoadTable() {
        fetch(UIOWA_AICHAT.urlLookup.post, {
            method: "POST",
            body: getQueryData,
        })
            .then((response) => response.text())
            .then((data) => {
                const data2 = data
                    .replaceAll("&quot;", '"')
                    .replaceAll("<", "&lt;")
                    .replaceAll(">", "&gt;");
                let newData = JSON.parse(data2);

                const projectIdLink = {
                    data: "Project ID",
                    title: "Project ID",
                    render: function (data, type, row, meta) {
                        return `<a href="${UIOWA_AICHAT.urlLookup.redcapBase}index.php?pid=${row.project_id}" target="_blank">${data}</a>`;
                    },
                };

                newData.columns.splice(0, 1, projectIdLink);

                let table = $("#aichatTable").DataTable({
                    data: newData.data,

                    scrollXInner: true,
                    scrollY: true,
                    colReorder: true,
                    fixedHeader: {
                        header: true,
                        headerOffset: $("#redcap-home-navbar-collapse").height(),
                    },
                    columnDefs: [
                        {
                            targets: 0,
                            data: null,
                            defaultContent: "",
                            orderable: false,
                            className: "",
                        },
                    ],
                    select: {
                        style: "multi",
                        selector: "td:first-child",
                    },
                    columns: [...newData.columns],
                    orderCellsTop: true,
                    fixedHeader: true,

                    initComplete: function () {
                        let $filterRow = $('<tr class="filter-row"></tr>');

                        // add column filters
                        this.api()
                            .columns()
                            .every(function () {
                                let column = this;
                                let $filterTd = $(
                                    '<th data-column-index="' + column.index() + '"></th>'
                                );
                                $filterTd.append('<input style="width: 100%"/>');

                                $("input", $filterTd).on("keyup change clear", function () {
                                    if (column.search() !== this.value) {
                                        column.search(this.value).draw();
                                    }
                                });
                                $filterRow.append($filterTd);
                            });
                        $("div .dataTables_scrollHeadInner thead").append($filterRow);
                    },
                });

                // sync filter visibility with column
                table.on("column-visibility.dt", function (e, settings, column, state) {
                    let $filterTd = $(".filter-row > td").eq(column);

                    state ? $filterTd.show() : $filterTd.hide();
                });



                table.on("select", function (e, dt, type, indexes) {
                    currentRowsChecked = $.map(
                        table.rows(".selected").data(),
                        function (item) {
                            return item.project_id;
                        }
                    );

                    if (currentRowsChecked.length >= 1) {
                        $("#aichatReviewSubmit")
                            .css({ visibility: "visible" })
                            .text(`Review and Submit (${currentRowsChecked.length})`);
                    } else {
                        $("#aichatReviewSubmit").css({ visibility: "hidden" });
                    }
                });

                table.on("deselect", function (e, dt, type, indexes) {
                    currentRowsChecked = $.map(
                        table.rows(".selected").data(),
                        function (item) {
                            return item.project_id;
                        }
                    );

                    if (currentRowsChecked.length >= 1) {
                        $("#aichatReviewSubmit")
                            .css({ visibility: "visible" })
                            .text(`Review and Submit (${currentRowsChecked.length})`);
                    } else {
                        $("#aichatReviewSubmit").css({ visibility: "hidden" });
                    }
                });

                $("#aichatSubmit").click(function () {
                    let ids = $.map(table.rows(".selected").data(), function (item) {
                        return item.project_id;
                    });

                    fetch(UIOWA_AICHAT.urlLookup.post, {
                        method: "POST",
                        body: getQueryData,
                    })
                        .then((response) => response.text())
                        .then((data) => {
                            window.location.reload();
                        });
                });



                $(".modal-close").click(function () {
                    $(".modal").css({ display: "none" });
                });

                $("body th")
                    .on("click", "#aichatCheckAll", function () {
                        if ($("#aichatCheckAll").hasClass("selected")) {
                            table.rows().deselect();
                            $("#aichatCheckAll").removeClass("selected");
                        } else {
                            table.rows().select();
                            $("#aichatCheckAll").addClass("selected");
                        }
                    })
                    .on("select deselect", function () {
                        ("Some selection or deselection going on");
                        if (
                            table
                                .rows({
                                    selected: true,
                                })
                                .count() !== table.rows().count()
                        ) {
                            $("#aichatCheckAll").removeClass("selected");
                        } else {
                            $("#aichatCheckAll").addClass("selected");
                        }
                    });
            });
    }
});
