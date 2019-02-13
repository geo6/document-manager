import api from './api';
import initUpload from './upload';
import initViewImage from './view/image';
import initViewGeoJSON from './view/geojson';

window.app = window.app || {};

$(document).ready(() => {
    initUpload();
    initViewImage();
    initViewGeoJSON();

    $('#btn-directory-create').on('click', event => {
        let directory = prompt('Directory name ?');

        if (directory !== null) {
            directory = directory.trim();

            if (directory.length > 0) {
                api(
                    window.app.api.directory,
                    'POST', {
                        directory: window.app.directory,
                        new: directory
                    },
                    json => {
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

    $('.btn-delete').on('click', event => {
        const {
            path
        } = $(event.target).closest('tr').data();

        event.preventDefault();

        if (confirm('Are you sure you want to delete this file ?') === true) {
            api(
                window.app.api.file,
                'DELETE', {
                    path
                },
                json => {
                    if (json.deleted === true) {
                        $(event.target).closest('tr').remove();
                    } else {
                        $(event.target).closest('tr').addClass('table-danger');
                    }
                }
            );
        }
    });

    $('.btn-rename').on('click', event => {
        const {
            path
        } = $(event.target).closest('tr').data();

        event.preventDefault();

        $('#modal-rename').data({
            path
        });
        $('#rename-name, #rename-name-new').val(path.substring(path.lastIndexOf('/') + 1));
    });
    $('#modal-rename .modal-footer > .btn-primary').on('click', event => {
        event.preventDefault();

        const path = $('#modal-rename').data('path');
        const name = $('#rename-name').val();
        const newName = $('#rename-name-new').val().trim();

        if (name !== newName) {
            api(
                window.app.api.file,
                'PUT', {
                    path,
                    name: newName
                },
                json => {
                    if (json.renamed === true) {
                        location.reload();
                    } else {
                        throw new Error(`Unable to rename file "${path}"!`);
                    }
                }
            );
        }

        $('#modal-rename').modal('hide');
    });
});
