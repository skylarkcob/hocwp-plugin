window.wcCI = window.wcCI || {};

jQuery(document).ready(function ($) {
    const body = $("body");

    (function () {
        body.find("." + wcCI.textDomain + " input[type='file']").on("change", function () {
            if (this.files && this.files.length) {
                var inputFile = $(this),
                    maxImage = wcCI.max_image_count,
                    countImage = parseInt(inputFile.attr("data-count-image")),
                    fileIds = inputFile.parent().find(".file-ids"),
                    preview = inputFile.parent().find(".preview"),
                    messages = inputFile.parent().find(".messages");

                if (isNaN(countImage)) {
                    countImage = 0;
                }

                if (this.files.length <= maxImage && (countImage + this.files.length) <= maxImage) {
                    var files = this.files,
                        i = 0,
                        count = files.length,
                        maxSize = wcCI.max_image_size;

                    for (i; i < count; i++) {
                        var file = files[i];

                        if (file) {
                            if (file.size > (maxSize * 1024 * 1024)) {
                                var sizeText = maxSize;

                                if (maxSize < 1) {
                                    sizeText = maxSize * 1024;
                                    sizeText.toString();
                                    sizeText += "KB";
                                } else {
                                    sizeText.toString();
                                    sizeText += "MB";
                                }

                                inputFile.val(null);
                                inputFile.trigger("change");
                                alert(wcCI.text.max_image_size);
                                return;
                            }

                            (function (file) {
                                var Upload = function (file) {
                                    this.file = file;
                                };

                                Upload.prototype.getType = function () {
                                    return this.file.type;
                                };

                                Upload.prototype.getSize = function () {
                                    return this.file.size;
                                };

                                Upload.prototype.getName = function () {
                                    return this.file.name;
                                };

                                Upload.prototype.doUpload = function () {
                                    var that = this;
                                    var formData = new FormData();

                                    // add assoc key values, this will be posts values
                                    formData.append("file", this.file);
                                    formData.append("upload_file", true);
                                    formData.append("file_ids", fileIds.val());
                                    formData.append("accept", inputFile.attr("accept"));

                                    $.ajax({
                                        type: "POST",
                                        url: wcCI.ajaxUrl + "?do_action=upload_images&action=" + wcCI.optionName,
                                        xhr: function () {
                                            var myXhr = $.ajaxSettings.xhr();

                                            if (myXhr.upload) {
                                                myXhr.upload.addEventListener("progress", that.progressHandling, false);
                                            }

                                            return myXhr;
                                        },
                                        success: function (response) {
                                            if (response.success) {
                                                preview.append("<img data-id='" + response.data.image.id + "' src='" + response.data.image.url + "'>");

                                                var ids = fileIds.val();

                                                if ($.trim(ids)) {
                                                    ids += ",";
                                                }

                                                ids += response.data.image.id;

                                                fileIds.val(ids);

                                                countImage++;
                                                inputFile.attr("data-count-image", countImage);

                                                inputFile.val(null);

                                                preview.on("click", "img", function () {
                                                    removeImageFile($(this));
                                                });
                                            } else {
                                                if (response.data.messages) {
                                                    messages.html(response.data.messages);
                                                }
                                            }
                                        },
                                        error: function (error) {
                                            // handle error
                                        },
                                        async: true,
                                        data: formData,
                                        cache: false,
                                        contentType: false,
                                        processData: false,
                                        timeout: 60000
                                    });
                                };

                                Upload.prototype.progressHandling = function (event) {
                                    var percent = 0;
                                    var position = event.loaded || event.position;
                                    var total = event.total;

                                    if (event.lengthComputable) {
                                        percent = Math.ceil(position / total * 100);
                                    }

                                    console.log(position);
                                    console.log(percent);
                                    console.log(total);
                                };

                                var upload = new Upload(file);

                                // execute upload
                                upload.doUpload();
                            })(file);
                        }
                    }
                } else {
                    alert(wcCI.text.max_image_count);
                    inputFile.val(null);
                    inputFile.trigger("change");
                }
            }
        });

        function removeImageFile(element) {
            element.fadeOut();

            $.ajax({
                type: "GET",
                dataType: "json",
                url: wcCI.ajaxUrl,
                data: {
                    action: wcCI.optionName,
                    do_action: "delete_image",
                    id: element.attr("data-id")
                },
                success: function (response) {
                    var preview = element.parent();

                    element.remove();

                    var ids = "";

                    preview.children("img").each(function () {
                        if ($.trim(ids)) {
                            ids += ",";
                        }

                        ids += $(this).attr("data-id");
                    });

                    preview.parent().find(".file-ids").val(ids);

                    var input = preview.parent().find("input[type='file']"),
                        count = parseInt(input.attr("data-count-image"));

                    if (!isNaN(count)) {
                        count--;

                        if (0 > count) {
                            count = 0;
                        }

                        input.attr("data-count-image", count);
                    }
                }
            });
        }
    })();
});