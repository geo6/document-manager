"use strict";

import api from "../api";

export default function () {
  $("#modal-rename").on("show.bs.modal", (event) => {
    const { path } = $(event.relatedTarget).closest("tr").data();

    $("#modal-rename").data({
      path,
    });

    $("#rename-name, #rename-name-new").val(
      path.substring(path.lastIndexOf("/") + 1)
    );
  });

  $("#modal-rename .modal-footer > .btn-primary").on("click", (event) => {
    event.preventDefault();

    const path = $("#modal-rename").data("path");
    const name = $("#rename-name").val();
    const newName = $("#rename-name-new").val().trim();

    if (name !== newName) {
      api(
        window.app.api.file,
        "PUT",
        {
          path,
          name: newName,
        },
        (json) => {
          if (json.renamed === true) {
            location.reload();
          } else {
            throw new Error(`Unable to rename file "${path}"!`);
          }
        }
      );
    }

    $("#modal-rename").modal("hide");
  });
}
