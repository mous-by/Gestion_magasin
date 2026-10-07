@extends('layouts.admin')

@section('title', 'Audit')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Paramètres</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Audit</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="row">
        <div class="col-12 col-lg-3">
            @include('configuration._menu')
        </div>

        <div class="col-12 col-lg-9">
    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="mb-0 text-white"><i class='bx bx-filter-alt me-2'></i>FILTRES</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('audit.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="author">Auteur</label>
                    <input type="text" class="form-control @error('author') is-invalid @enderror" id="author" name="author" value="{{ $filters['author'] ?? '' }}" placeholder="Nom de l'auteur">
                    @error('author')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="module">Module</label>
                    <select class="form-select @error('module') is-invalid @enderror" id="module" name="module">
                        <option value="">Tous</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                    @error('module')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="action">Action</label>
                    <input type="text" class="form-control @error('action') is-invalid @enderror" id="action" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Ex : auth.login">
                    @error('action')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="channel">Canal</label>
                    <select class="form-select @error('channel') is-invalid @enderror" id="channel" name="channel">
                        <option value="">Tous</option>
                        @foreach ($channels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['channel'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="from">Du</label>
                    <input type="date" class="form-control @error('from') is-invalid @enderror" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                    @error('from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="to">Au</label>
                    <input type="date" class="form-control @error('to') is-invalid @enderror" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                    @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="superadmin" name="superadmin" value="1" @checked($filters['superadmin'])>
                        <label class="form-check-label" for="superadmin">Actions du superadmin seulement</label>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                    <button type="submit" class="btn btn-primary d-flex align-items-center gap-1"><i class='bx bx-search'></i>Filtrer</button>
                    <a href="{{ route('audit.index') }}" class="btn btn-light">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-list-check me-2'></i>JOURNAL D'AUDIT</h6>
            <span class="badge bg-light text-dark">{{ $logs->total() }} entrée(s)</span>
        </div>
        <div class="card-body">
            @if ($logs->isEmpty())
                <p class="text-muted text-center mb-0 py-4">Aucune entrée ne correspond à ces filtres.</p>
            @else
                <div class="table-responsive">
                    <table class="table" id="audit-table">
                        <thead>
                            <tr><th>DATE</th><th>AUTEUR</th><th>ACTION</th><th>DESCRIPTION</th><th width="10%">DÉTAIL</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                @php($detail = $details[$log->id])
                                <tr>
                                    <td class="text-nowrap">
                                        {{ $detail['date'] }}
                                        <div class="text-muted small">{{ $detail['channel'] }}</div>
                                    </td>
                                    <td>
                                        {{ $detail['author'] }}
                                        <div>
                                            @if ($detail['superadmin'])
                                                <span class="badge bg-danger">Superadmin</span>
                                            @elseif ($detail['role'])
                                                <span class="text-muted small">{{ $detail['role'] }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-break"><code class="text-break">{{ $log->action }}</code></td>
                                    <td class="cell-wrap text-break">{{ $log->description }}</td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#auditDetailModal" data-log="{{ json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}" title="Détail">
                                            <i class='bx bx-show'></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
        </div>
    </div>

    <div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-list-check me-2'></i>Détail de l'action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm mb-4"><tbody id="audit-summary"></tbody></table>
                    <p class="fw-semibold mb-2">Valeurs modifiées</p>
                    <div id="audit-values"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $('#audit-table').DataTable({ paging: false, info: false, searching: false, ordering: false, scrollX: false });

        // Les valeurs du journal peuvent contenir du texte saisi par un tiers : tout passe par textContent, jamais innerHTML.
        const format = (value) => value === null || value === undefined ? '—' : (typeof value === 'object' ? JSON.stringify(value) : String(value));

        function cell(tag, text, className) {
            const element = document.createElement(tag);
            element.textContent = text;
            if (className) element.className = className;
            return element;
        }

        document.getElementById('auditDetailModal').addEventListener('show.bs.modal', (event) => {
            const log = JSON.parse(event.relatedTarget.dataset.log);
            const summary = document.getElementById('audit-summary');
            const values = document.getElementById('audit-values');
            summary.replaceChildren();
            values.replaceChildren();

            [
                ['Date', log.date],
                ['Auteur', log.author + (log.role ? ' (' + log.role + ')' : '')],
                ['Module', log.module],
                ['Action', log.action],
                ['Description', log.description],
                ['Élément concerné', log.subject],
                ['Canal', log.channel],
                ['Adresse IP', log.ip],
                ['Navigateur', log.agent],
            ].forEach(([label, value]) => {
                const row = document.createElement('tr');
                row.append(cell('th', label, 'w-25 text-muted fw-normal'), cell('td', format(value), 'text-break'));
                summary.append(row);
            });

            const keys = [...new Set([...Object.keys(log.old), ...Object.keys(log.new)])];
            if (keys.length === 0) {
                values.append(cell('p', 'Aucune valeur enregistrée pour cette action.', 'text-muted mb-0'));
                return;
            }

            const table = document.createElement('table');
            table.className = 'table table-sm mb-0';
            const head = document.createElement('tr');
            head.append(cell('th', 'CHAMP'), cell('th', 'AVANT'), cell('th', 'APRÈS'));
            const thead = document.createElement('thead');
            thead.append(head);
            const tbody = document.createElement('tbody');
            keys.forEach((key) => {
                const row = document.createElement('tr');
                row.append(cell('td', key, 'fw-semibold'), cell('td', format(log.old[key]), 'text-break'), cell('td', format(log.new[key]), 'text-break'));
                tbody.append(row);
            });
            table.append(thead, tbody);
            values.append(table);
        });
    </script>
@endpush
