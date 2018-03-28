jQuery(document).ready(function ($) {
    var body = $("body");

    (function () {
        if (body.hasClass("settings_page_hocwp_auto_approve_comment")) {
            var tagsPanel = $(".tags-panel"),
                index = 0;

            $(".add-new-tag").on("click", function (e) {
                e.preventDefault();
                var element = $(this),
                    emptyInput = tagsPanel.find("input").filter(function () {
                        return !this.value;
                    }).first(),
                    emptyTextarea = tagsPanel.find("textarea:empty").first();

                if (emptyInput.length && !$.trim(emptyInput.val())) {
                    emptyInput.focus();
                } else if (emptyTextarea.length && !$.trim(emptyTextarea.val())) {
                    emptyTextarea.focus();
                } else {
                    element.addClass("disabled");

                    $.ajax({
                        type: "POST",
                        dataType: "JSON",
                        url: hocwpAAC.ajaxUrl,
                        cache: true,
                        data: {
                            action: "hocwp_plugin_aac_generate_tag_row",
                            index: index
                        },
                        success: function (response) {
                            if (response.success) {
                                tagsPanel.append(response.data.tag_row);

                                emptyTextarea = tagsPanel.find("textarea:empty").first();

                                tagsPanel.find("input").filter(function () {
                                    return !this.value;
                                }).first().focus();

                                index = response.data.index;
                                index++;
                            }
                        },
                        complete: function () {
                            element.removeClass("disabled");

                            hocwp_plugin_acc_track_change();
                        }
                    });
                }
            });

            tagsPanel.on("click", ".delete-row", function () {
                var element = $(this),
                    row = element.closest("tr"),
                    index = row.attr("data-key");
                row.fadeOut();
                $.ajax({
                    type: "POST",
                    dataType: "JSON",
                    url: hocwpAAC.ajaxUrl,
                    cache: true,
                    data: {
                        action: "hocwp_plugin_aac_remove_tag_row",
                        index: index
                    },
                    success: function (response) {
                        if (response.success) {
                            row.remove();
                        } else {
                            row.show();
                        }
                    },
                    error: function () {
                        row.show();
                    }
                });
            });

            body.on("click", "button.update-tags", function () {
                var data = [],
                    rows = tagsPanel.find("tr").not(":first-child");

                rows.each(function () {
                    var element = $(this),
                        tag = element.find("input"),
                        reply = element.find("textarea");

                    data[element.attr("data-key")] = {
                        tag: tag.val(),
                        reply: reply.val()
                    };
                });

                var element = $(this);

                element.addClass("disabled");

                $.ajax({
                    type: "POST",
                    dataType: "JSON",
                    url: hocwpAAC.ajaxUrl,
                    cache: true,
                    data: {
                        action: "hocwp_plugin_aac_update_tag_row",
                        data: data,
                        all: 1
                    },
                    complete: function () {
                        element.removeClass("disabled");
                    }
                });
            });

            function hocwp_plugin_acc_track_change() {
                var timeout = null;
                tagsPanel.find("input, textarea").on("keyup", function () {
                    clearTimeout(timeout);

                    var element = $(this),
                        value = element.val();

                    timeout = setTimeout(function () {
                        var data = {
                            name: element.attr("name"),
                            value: value
                        };

                        $.ajax({
                            type: "POST",
                            dataType: "JSON",
                            url: hocwpAAC.ajaxUrl,
                            cache: true,
                            data: {
                                action: "hocwp_plugin_aac_update_tag_row",
                                data: data,
                                all: 0
                            }
                        });
                    }, 500);
                });
            }

            hocwp_plugin_acc_track_change();
        }
    })();
});