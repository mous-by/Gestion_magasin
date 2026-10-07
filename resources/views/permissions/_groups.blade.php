@forelse ($grouped as $module => $permissions)
    <div class="mb-4">
        <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2">
            <i class='bx bx-folder me-1'></i>{{ $module }}
            <span class="badge bg-light text-dark">{{ $permissions->count() }}</span>
        </h6>
        <table class="table table-sm mb-0">
            <tbody>
                @foreach ($permissions as $permission)
                    <tr>
                        <td class="fw-bold">
                            {{ $permission->name }}
                            @if ($labels->has($permission->name))
                                <div class="text-muted small fw-normal">{{ $labels[$permission->name]['label'] }}</div>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if ($permission->isCustom())
                                <span class="badge bg-info">Interface</span>
                            @else
                                <span class="badge bg-primary">Manifeste</span>
                            @endif
                            @if ($registry->isReserved($permission->name))
                                <span class="badge bg-danger">Réservée</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <span class="badge bg-light text-dark border">{{ $permission->roles_count }} rôle(s)</span>
                            <span class="badge bg-light text-dark border">{{ $permission->users_count }} utilisateur(s)</span>
                            @if ($permission->isCustom())
                                <form method="POST" action="{{ route('permissions.destroy', $permission) }}" class="d-inline js-delete-permission" data-name="{{ $permission->name }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer"><i class='bx bx-trash'></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p class="text-muted text-center py-4 mb-0">Aucune permission trouvée.</p>
@endforelse
