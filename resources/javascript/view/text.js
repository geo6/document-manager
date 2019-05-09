'use strict';

export default function () {
    $('#modal-view-text').on('show.bs.modal', event => {
        const link = event.relatedTarget;
        const href = $(link).attr('href');

        $('#modal-view-text .modal-body > pre > code').empty();

        fetch(href)
            .then(response => response.text())
            .then(text => {
                $('#modal-view-text .modal-body > pre > code').text(text);
                $('#modal-view-text').modal('handleUpdate');
            });
    });
}
