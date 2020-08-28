"use strict";

import "../sass/style.scss";

import initDirectoryCreate from "./action/directory.create";
import initFileDelete from "./action/file.delete";
import initFileDescription from "./action/file.description";
import initFileRename from "./action/file.rename";
import initUpload from "./upload";
import initViewHTML from "./view/html";
import initViewImage from "./view/image";
import initViewGeoJSON from "./view/geojson";
import initViewText from "./view/text";

window.app = window.app || {};

$(document).ready(() => {
  initUpload();

  initDirectoryCreate();

  initViewHTML();
  initViewImage();
  initViewGeoJSON();
  initViewText();

  initFileDelete();
  initFileDescription();
  initFileRename();
});
