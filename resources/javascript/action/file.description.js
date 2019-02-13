'use strict';

import api from '../api';

export default function () {
    $('.btn-description').on('click', event => {
        const {
            path
        } = $(event.target).closest('tr').data();

        event.preventDefault();

        $('#modal-description').data({
            path
        });
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
