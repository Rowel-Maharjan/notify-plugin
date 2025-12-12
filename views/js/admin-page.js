jQuery(document).ready(function ($) {
  function showToast(message, type = "success", duration = 4000) {
    const toast = $("#wc-notif-toast");
    toast.removeClass("success error").addClass(type);
    toast.text(message);
    toast.fadeIn(300);

    setTimeout(function () {
      toast.fadeOut(300);
    }, duration);
  }

  function showLoading(element, originalText = null) {
    if (originalText) {
      element.data("original-text", originalText);
    }
    element.prop("disabled", true);
    if (element.is("button")) {
      element.text("Loading...");
    }
    element.addClass("loading");
  }

  function hideLoading(element) {
    element.prop("disabled", false);
    element.removeClass("loading");
    if (element.is("button") && element.data("original-text")) {
      element.text(element.data("original-text"));
    }
  }

  $("#enable_forwarding").on("change", function () {
    const $checkbox = $(this);
    const enabled = $checkbox.is(":checked");
    const $topicCheckboxes = $(".topic-toggle");

    showLoading($checkbox);

    $.ajax({
      url: WCNotif.ajax_url,
      type: "POST",
      data: {
        action: "wc_notification_toggle_forwarding",
        enabled: enabled,
        nonce: WCNotif.nonce,
      },
      success: function (response) {
        if (response.success) {
          showToast(response.data.message, "success");

          $topicCheckboxes.prop("disabled", !enabled);

          if (!enabled) {
            $topicCheckboxes.each(function () {
              $(this).prop("checked", false).data("current-status", 0);
            });
          }
        } else {
          showToast(
            response.data.message || "Failed to update forwarding setting",
            "error"
          );
          $checkbox.prop("checked", !enabled);
        }
      },
      error: function (xhr, status, error) {
        showToast("Network error: " + error, "error");
        $checkbox.prop("checked", !enabled);
      },
      complete: function () {
        hideLoading($checkbox);
      },
    });
  });

  $(".topic-toggle").on("change", function () {
    const $checkbox = $(this);
    const topic = $checkbox.data("topic");
    const enabled = $checkbox.is(":checked");
    const currentStatus = parseInt($checkbox.data("current-status")) || 0;

    if ((enabled && currentStatus === 1) || (!enabled && currentStatus === 0)) {
      return;
    }

    if (!$("#enable_forwarding").is(":checked")) {
      showToast("Please enable global forwarding first", "error");
      $checkbox.prop("checked", false);
      return;
    }

    showLoading($checkbox);

    $.ajax({
      url: WCNotif.ajax_url,
      type: "POST",
      data: {
        action: "wc_notification_toggle_topic",
        topic: topic,
        enabled: enabled,
        current_status: currentStatus,
        nonce: WCNotif.nonce,
      },
      success: function (response) {
        if (response.success) {
          showToast(response.data.message, "success");
          $checkbox.data("current-status", enabled ? 1 : 0);
        } else {
          showToast(response.data.message || "Failed to update topic", "error");
          $checkbox.prop("checked", currentStatus === 1);
        }
      },
      error: function (xhr, status, error) {
        showToast("Network error: " + error, "error");
        $checkbox.prop("checked", currentStatus === 1);
      },
      complete: function () {
        hideLoading($checkbox);
      },
    });
  });

  let isEditing = false;
  const $apiUrl = $("#api_url");
  const $apiToken = $("#api_token");
  const $editButton = $("#toggle-api-edit");
  const $saveButton = $("#save-api");
  const $cancelButton = $("#cancel-api-edit");

  let originalUrl = $apiUrl.val();
  let originalToken = $apiToken.val();

  $editButton.on("click", function () {
    if (!isEditing) {
      $apiUrl.prop("disabled", false);
      $apiToken.prop("disabled", false);
      $saveButton.prop("disabled", false);
      $cancelButton.prop("disabled", false).show();
      $editButton.text("Editing...");
      isEditing = true;

      originalUrl = $apiUrl.val();
      originalToken = $apiToken.val();

      $apiUrl.focus();
    }
  });

  $cancelButton.on("click", function () {
    $apiUrl.val(originalUrl);
    $apiToken.val(originalToken);
    exitEditMode();
  });

  function exitEditMode() {
    $apiUrl.prop("disabled", true);
    $apiToken.prop("disabled", true);
    $saveButton.prop("disabled", true);
    $cancelButton.prop("disabled", true).hide();
    $editButton.text("Edit");
    isEditing = false;
  }

  $saveButton.on("click", function () {
    const apiUrl = $apiUrl.val().trim();
    const apiToken = $apiToken.val().trim();

    if (!apiUrl || !apiToken) {
      showToast("Please fill in both API URL and Token", "error");
      return;
    }

    try {
      new URL(apiUrl);
    } catch (e) {
      showToast("Please enter a valid URL", "error");
      return;
    }

    if (apiToken.length < 3) {
      showToast("API token must be at least 3 characters long", "error");
      return;
    }

    showLoading($saveButton, "Save");

    $.ajax({
      url: WCNotif.ajax_url,
      type: "POST",
      data: {
        action: "wc_notification_save_api_config",
        api_url: apiUrl,
        api_token: apiToken,
        nonce: WCNotif.nonce,
      },
      success: function (response) {
        if (response.success) {
          showToast(response.data.message, "success");

          originalUrl = apiUrl;
          originalToken = apiToken;

          exitEditMode();
        } else {
          showToast(
            response.data.message || "Failed to save configuration",
            "error"
          );
        }
      },
      error: function (xhr, status, error) {
        showToast("Network error: " + error, "error");
      },
      complete: function () {
        hideLoading($saveButton);
      },
    });
  });

  $("#api-config-form").on("submit", function (e) {
    e.preventDefault();
    if (isEditing && !$saveButton.prop("disabled")) {
      $saveButton.click();
    }
  });

  $apiUrl.add($apiToken).on("keypress", function (e) {
    if (e.which === 13 && isEditing && !$saveButton.prop("disabled")) {
      e.preventDefault();
      $saveButton.click();
    }
  });

  $(document).on("keydown", function (e) {
    if (e.which === 27 && isEditing) {
      $cancelButton.click();
    }
  });

  $(window).on("beforeunload", function (e) {
    if (isEditing) {
      const message =
        "You have unsaved changes. Are you sure you want to leave?";
      e.returnValue = message;
      return message;
    }
  });

  $("[data-tooltip]").each(function () {
    $(this).attr("title", $(this).data("tooltip"));
  });
});
