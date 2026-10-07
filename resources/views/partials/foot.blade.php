<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

<script src="{{ asset('assets/plugins/simplebar/js/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/plugins/metismenu/js/metisMenu.min.js') }}"></script>
<script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>

<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>

<script>
    // Jeton CSRF pour les requêtes AJAX (recherche des permissions, etc.).
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Rendre TOUS les tableaux DataTables lisibles sur mobile : défilement horizontal + libellés FR.
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            scrollX: true,
            autoWidth: false,
            language: {
                search: 'Rechercher :',
                lengthMenu: 'Afficher _MENU_ éléments',
                info: '_START_ à _END_ sur _TOTAL_',
                infoEmpty: '0 élément',
                infoFiltered: '(filtré sur _MAX_)',
                zeroRecords: 'Aucun résultat',
                emptyTable: 'Aucune donnée disponible',
                paginate: { first: '«', previous: '‹', next: '›', last: '»' },
            },
        });
    }

    // Tableaux « simples » (non DataTables) : défilement horizontal sur mobile.
    window.addEventListener('load', function () {
        document.querySelectorAll('table.table').forEach(function (t) {
            if (t.closest('.table-responsive') || t.closest('.dataTables_wrapper')) return;
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    });

    $(function () {
        $('.single-select').each(function () {
            const modal = $(this).closest('.modal');
            $(this).select2({
                theme: 'bootstrap4',
                allowClear: false,
                dropdownParent: modal.length ? modal : $(document.body),
            });
        });

        // Select2 en recherche côté serveur (AJAX) : pour un champ dont la liste complète serait trop lourde à
        // précharger (article, fournisseur…). `data-search-url` répond en JSON `{"results": [{"id", "text"}]}`.
        $('.ajax-select').each(function () {
            const modal = $(this).closest('.modal');
            $(this).select2({
                theme: 'bootstrap4',
                allowClear: false,
                dropdownParent: modal.length ? modal : $(document.body),
                placeholder: $(this).data('placeholder') || 'Rechercher…',
                minimumInputLength: 0,
                ajax: {
                    url: $(this).data('search-url'),
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({ q: params.term || '' }),
                    processResults: (data) => ({ results: data.results }),
                    cache: true,
                },
            });
        });
    });
</script>
