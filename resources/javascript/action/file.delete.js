"use strict";

import api from "../api";

export default function () {
  $(".btn-delete").on("click", (event) => {
    const { path } = $(event.target).closest("tr").data();

    event.preventDefault();

    if (confirm("Are you sure you want to delete this file ?") === true) {
      api(
        window.app.api.file,
        "DELETE",
        {
          path,
        },
        (json) => {
          if (json.deleted === true) {
            $(event.target).closest("tr").remove();
          } else {
            $(event.target).closest("tr").addClass("table-danger");
          }
        }
      );
    }
  });
}
