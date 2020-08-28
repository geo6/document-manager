"use strict";

import Resumable from "resumablejs/resumable";

export default function initUpload() {
  window.app.upload = {
    count: 0,
    success: 0,
  };

  const resumable = initResumableJS();

  $("#btn-upload-upload").on("click", () => {
    resumable.upload();
  });
}

/**
 *
 */
function initResumableJS() {
  const resumable = new Resumable({
    fileType: window.app.types,
    target: window.app.api.upload,
    testChunks: true,
    query: {
      directory: window.app.directory,
    },
    permanentErrors: [400, 401, 403, 404, 409, 415, 500, 501],
  });

  resumable.assignBrowse($("#btn-upload-browse"));

  resumable.on("fileAdded", (file, event) => {
    const { fileName, uniqueIdentifier } = file;
    const li = document.createElement("li");

    window.app.upload.count++;

    $(li).text(fileName).data("id", uniqueIdentifier);

    $("#upload-list").append(li);

    $("#btn-upload-upload")
      .prop("disabled", false)
      .addClass("btn-primary")
      .removeClass("btn-outline-primary");
  });

  resumable.on("progress", () => {
    const pct = Math.round(resumable.progress(true) * 100);

    $("#upload-progress").attr("aria-valuenow", pct).css("width", `${pct}%`);
  });

  resumable.on("fileSuccess", (file, message) => {
    const { uniqueIdentifier } = file;

    $("#upload-list > li").each((index, element) => {
      if ($(element).data("id") === uniqueIdentifier) {
        $(element).addClass("text-success");
      }
    });

    window.app.upload.success++;

    if (window.app.upload.success === window.app.upload.count) {
      window.location.reload();
    }
  });

  resumable.on("fileError", (file, message) => {
    const { uniqueIdentifier } = file;

    $("#upload-list > li").each((index, element) => {
      if ($(element).data("id") === uniqueIdentifier) {
        $(element).addClass("text-danger");
      }
    });
  });

  resumable.on("error", (message, file) => {
    console.error(message, file);
  });

  return resumable;
}
