/*global $*/

window.app = window.app || {};

$(document).ready(function() {
    $('#modal').on('show.bs.modal', (event) => {
        const link = event.relatedTarget;
        const index = $('a[data-target="#modal"]').index(link);

        $('#carousel').carousel(index);
    });
});
