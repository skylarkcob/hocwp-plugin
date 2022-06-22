window.wcCI = window.wcCI || {};

jQuery(document).ready(function ($) {
    const body = $("body");

    function removeImageFile(element, post_id, images) {
        element.fadeOut();

        post_id = post_id || false;
        images = images || images;

        $.ajax({
            type: "GET",
            dataType: "json",
            url: wcCI.ajaxUrl,
            data: {
                action: wcCI.optionName,
                do_action: "delete_image",
                id: element.attr("data-id"),
                post_id: post_id,
                images: images
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

                var input = preview.parent().find("input[name='_billing_images']"),
                    count = parseInt(input.attr("data-count-image"));

                if (!isNaN(count)) {
                    count--;

                    if (0 > count) {
                        count = 0;
                    }

                    input.attr("data-count-image", count);
                }

                if (response.success) {
                    input.val(response.data.images);
                }
            }
        });
    }

    if (!body.hasClass("wp-admin")) {
        body.on("click", ".preview img", function (e) {
            e.preventDefault();
            removeImageFile($(this), $("#post_ID").val(), body.find("input[name='_billing_images']").val());
        });
    }

    (function () {
        $("body").on("click", "button.remove-all-images", function (e) {
            e.preventDefault();

            var that = this,
                element = $(that),
                postId = element.attr("data-id");

            if (confirm(wcCI.text.confirm_delete)) {
                element.addClass("disabled");

                $.ajax({
                    type: "GET",
                    dataType: "json",
                    url: wcCI.ajaxUrl,
                    data: {
                        action: wcCI.optionName,
                        do_action: "delete_image",
                        post_id: postId
                    },
                    success: function (response) {
                        element.prev("a, .preview, .billing-images.woo-checkout-images").fadeOut();
                        alert(wcCI.text.all_images_deleted);
                        element.remove();
                    }
                });
            }
        });
    })();
});