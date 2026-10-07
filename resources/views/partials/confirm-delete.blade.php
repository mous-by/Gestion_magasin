{{-- Confirmation SweetAlert2 de toute suppression : <form class="js-confirm-delete" data-title="…" data-name="…">.
     À inclure dans @push('scripts') d'un écran de module. --}}
<script>
    $(document).on('submit', '.js-confirm-delete', function (event) {
        event.preventDefault();
        const form = this;
        Swal.fire({
            icon: 'warning',
            title: $(form).data('title') || 'Supprimer ?',
            text: $(form).data('name') + ' sera supprimé(e) (récupérable par un administrateur).',
            showCancelButton: true,
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler',
        }).then((result) => { if (result.isConfirmed) form.submit(); });
    });
</script>
