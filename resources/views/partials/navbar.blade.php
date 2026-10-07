@php
    $authUser = auth()->user();
    // Liste ouverte : chaque alerte disponible ajoute une entrée, le badge affiche le VRAI nombre d'entrées.
    $notifications = $notifications ?? [];
    $notificationCount = count($notifications);
@endphp
<header>
    <div class="topbar d-flex align-items-center">
        <nav class="navbar navbar-expand">
            <div class="mobile-toggle-menu"><i class='bx bx-menu'></i></div>

            <div class="flex-grow-1"></div>

            <div class="top-menu ms-auto">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item dropdown dropdown-large">
                        <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="position-relative d-inline-block">
                                <i class='bx bx-bell'></i>
                                @if ($notificationCount > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: .6rem; padding: .3em .5em;">
                                        {{ $notificationCount }}
                                        <span class="visually-hidden">notification(s)</span>
                                    </span>
                                @endif
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="javascript:;">
                                <div class="msg-header">
                                    <p class="msg-header-title">Notifications @if ($notificationCount > 0)({{ $notificationCount }})@endif</p>
                                </div>
                            </a>
                            <div class="header-notifications-list">
                                @forelse ($notifications as $notification)
                                    <a href="{{ $notification['link'] ?? 'javascript:;' }}" class="dropdown-item">
                                        <div class="d-flex align-items-start gap-2 py-1">
                                            <i class='bx {{ $notification['icon'] }} {{ $notification['class'] }} fs-4'></i>
                                            <div class="text-wrap small">{{ $notification['text'] }}</div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="dropdown-item text-center text-muted py-4">
                                        Aucune notification pour le moment
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="user-box dropdown">
                <a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-img d-flex align-items-center justify-content-center bg-primary text-white fw-bold">
                        {{ mb_strtoupper(mb_substr($authUser->name, 0, 1)) }}
                    </div>
                    <div class="user-info ps-3">
                        <p class="user-name mb-0">{{ $authUser->name }}</p>
                        <p class="designattion mb-0">{{ $authUser->roleName()?->label() }}</p>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="bx bx-user"></i><span>Profil</span></a></li>
                    <li><div class="dropdown-divider mb-0"></div></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class='bx bx-log-out-circle'></i><span>Déconnexion</span>
                        </a>
                    </li>
                </ul>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </nav>
    </div>
</header>
