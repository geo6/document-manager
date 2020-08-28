"use strict";

export default function () {
  $("#modal-view-html").on("show.bs.modal", (event) => {
    const link = event.relatedTarget;
    const href = $(link).attr("href");

    fetch(href)
      .then((response) => response.text())
      .then((text) => {
        const parser = new DOMParser();
        const html = parser.parseFromString(text, "text/html");

        const body = $(html).find("body").html();

        $("#modal-view-html .modal-body").html(body);
        $("#modal-view-html").modal("handleUpdate");
      });
  });
}
