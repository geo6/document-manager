export default function () {
    $('#modal-view-image').on('show.bs.modal', (event) => {
        const link = event.relatedTarget;
        const index = $('a[data-target="#modal-view-image"]').index(link);

        $('#carousel').carousel(index);
    });
}
