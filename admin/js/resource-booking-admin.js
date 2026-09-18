(function ($) {
  "use strict";

  $(document).ready(function () {
    // Media library (resource images).
    $("#resource_image_button").on("click", function (event) {
      event.preventDefault();

      var image_frame = wp.media({
        title: "Select Resource Image",
        button: {
          text: "Use This Image",
        },
        multiple: true,
      });

      image_frame.on("select", function () {
        var attachments = image_frame.state().get("selection").toJSON();
        var image_ids = [];
        var preview_html = "";

        $.each(attachments, function (index, attachment) {
          image_ids.push(attachment.id);

          preview_html +=
            '<img src="' +
            attachment.url +
            '" style="max-width: 150px; height: auto; margin: 5px;">';
        });

        $("#resource_image_ids").val(image_ids.join(","));

        $("#resource_image_preview").html(preview_html);
      });

      image_frame.open();
    });

    // Remove image.
    $("#resource_image_remove_button").on("click", function (event) {
      event.preventDefault();

      $("#resource_image_ids").val("");

      $("#resource_image_preview").html("");
    });

    // Bookings list: AJAX filters, pagination, confirm / reject.

    var $tableWrapper = $("#resource-booking-bookings");

    if ($tableWrapper.length) {
      function rbLoadBookings(paged) {
        var data = {
          action: "resource_booking_get_bookings",
          nonce: resourceBookingAdmin.nonce,
          status: $("#rb_status_filter").val(),
          resource_id: $("#rb_resource_filter").val(),
          paged: paged,
        };

        $tableWrapper.addClass("resource-booking-loading");

        $.post(resourceBookingAdmin.ajaxUrl, data, function (response) {
          $tableWrapper.removeClass("resource-booking-loading");

          if (response.success) {
            $tableWrapper
              .data("current-page", response.data.current_page)
              .attr("data-current-page", response.data.current_page)
              .html(response.data.html);
          } else {
            $tableWrapper.html(
              "<p>" +
                (response.data.message || "Error loading bookings.") +
                "</p>",
            );
          }
        });
      }

      // Filter button.
      $("#rb_apply_filters").on("click", function () {
        rbLoadBookings(1);
      });

      // Filter selects also apply immediately.
      $("#rb_status_filter, #rb_resource_filter").on("change", function () {
        rbLoadBookings(1);
      });

      // Prevent native form submit (no-JS fallback is fine; JS intercepts).
      $("#resource-booking-filters").on("submit", function (event) {
        event.preventDefault();
        rbLoadBookings(1);
      });

      // Pagination links (delegated).
      $tableWrapper.on("click", "a.rb-pagination-link", function (event) {
        event.preventDefault();

        rbLoadBookings($(this).data("page"));
      });

      // Confirm / reject actions (delegated).
      $tableWrapper.on("click", ".rb-booking-action", function (event) {
        event.preventDefault();

        var $button = $(this);
        var actionLabel =
          $button.data("rb-action") === "confirm" ? "confirm" : "reject";

        if (
          !window.confirm(
            "Are you sure you want to " + actionLabel + " this booking?",
          )
        ) {
          return;
        }

        var data = {
          action: "resource_booking_update_booking_status",
          nonce: resourceBookingAdmin.nonce,
          booking_id: $button.data("booking-id"),
          rb_action: $button.data("rb-action"),
        };

        $button.prop("disabled", true);

        $.post(resourceBookingAdmin.ajaxUrl, data, function (response) {
          $button.prop("disabled", false);

          if (response.success) {
            // Show success notice inline, then re-load current page.
            var currentPage = $tableWrapper.data("current-page") || 1;

            rbLoadBookings(currentPage);
          } else {
            window.alert(response.data.message || "Action failed.");
          }
        });
      });

      // Sync payment status with Stripe (delegated).
      $tableWrapper.on("click", ".rb-sync-payment", function (event) {
        event.preventDefault();

        var $button = $(this);

        $button.prop("disabled", true).text("Syncing...");

        var data = {
          action: "resource_booking_sync_payment",
          nonce: resourceBookingAdmin.nonce,
          booking_id: $button.data("booking-id"),
        };

        $.post(resourceBookingAdmin.ajaxUrl, data, function (response) {
          if (response.success) {
            var currentPage = $tableWrapper.data("current-page") || 1;

            rbLoadBookings(currentPage);
          } else {
            $button.prop("disabled", false).text("Sync");

            window.alert(response.data.message || "Sync failed.");
          }
        });
      });
    }
  });
})(jQuery);
