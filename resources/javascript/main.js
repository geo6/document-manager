import initUpload from './upload';
import initViewImage from './view/image';
import initViewGeoJSON from './view/geojson';

window.app = window.app || {};

$(document).ready(() => {
    initUpload();
    initViewImage();
    initViewGeoJSON();

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
