"use strict";

import api from "../api";

export default function () {
  $("#btn-directory-create").on("click", (event) => {
    let directory = prompt("Directory name ?");

    if (directory !== null) {
      directory = directory.trim();

      if (directory.length > 0) {
        api(
          window.app.api.directory,
          "POST",
          {
            directory: window.app.directory,
            new: directory,
          },
          (json) => {
            if (json.created === true) {
              location.reload();
            } else {
              throw new Error(`Unable to create directory "${directory}"!`);
            }
          }
        );
      }
    }
  });
}
