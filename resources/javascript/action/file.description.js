'use strict';

import api from '../api';

export default function () {
    $('#modal-description').on('show.bs.modal', event => {
        const {
            path
        } = $(event.relatedTarget).closest('tr').data();

        $('#modal-description').data({
            path
        });

        api(
            window.app.api.file,
            'GET', {
                path
            },
            json => {
                $('#textarea-description').val(json.description);
            }
        );
    });

    $('#modal-description .modal-footer > .btn-primary').on('click', event => {
        event.preventDefault();

        const path = $('#modal-description').data('path');
        const description = $('#textarea-description').val().trim();

        api(
            window.app.api.file,
            'PUT', {
                path,
                description
            },
            json => {
                if (json.description === true) {
                    location.reload();
                } else {
                    throw new Error(`Unable to edit file "${path}" description!`);
                }
            }
        );

        $('#modal-description').modal('hide');
    });
}
