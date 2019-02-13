import initDirectoryCreate from './action/directory.create';
import initFileDelete from './action/file.delete';
import initFileDescription from './action/file.description';
import initFileRename from './action/file.rename';
import initUpload from './upload';
import initViewImage from './view/image';
import initViewGeoJSON from './view/geojson';

window.app = window.app || {};

$(document).ready(() => {
    initUpload();

    initDirectoryCreate();

    initViewImage();
    initViewGeoJSON();

    initFileDelete();
    initFileDescription();
    initFileRename();
});
