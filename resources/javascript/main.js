window.app = window.app || {};

$(document).ready(() => {
    $('#modal-view').on('show.bs.modal', (event) => {
        const link = event.relatedTarget;
        const index = $('a[data-target="#modal-view"]').index(link);

        $('#carousel').carousel(index);
    });

    $('.btn-delete').on('click', (event) => {
        const href = $(event.target).attr('href');
        const {
            path
        } = $(event.target).closest('tr').data();

        event.preventDefault();

        if (confirm('Are you sure you want to delete this file ?') === true) {
            fetch(href, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    path
                })
            }).then((response) => {
                if (response.ok !== true) {
                    throw response.statusText;
                }

                return response.json();
            }).then((data) => {
                console.log(data);

                if (data.deleted === true) {
                    $(event.target).closest('tr').remove();
                } else {
                    $(event.target).closest('tr').addClass('table-danger');
                }
            }).catch((error) => {
                console.error(error);
            });
        }
    });
});
