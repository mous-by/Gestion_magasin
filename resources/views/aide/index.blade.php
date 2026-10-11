@extends('layouts.admin')

@section('title', 'Aide')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Aide</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Guide d'utilisation</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        // Chapitres : thèmes de la formation du magasinier (cahier §31). Chaque étape est une phrase courte.
        $chapters = [
            ['bx bx-log-in', 'Se connecter et se déplacer', [
                'Connectez-vous avec votre numéro de téléphone et votre mot de passe. À la première connexion, choisissez un nouveau mot de passe (8 caractères au moins, lettres et chiffres).',
                'Le menu de gauche regroupe le magasin : Articles, Fournisseurs, Entrées, Sorties, Stock, Rapports. Une pastille orange signale ce qui attend votre action (brouillons à valider, articles à réapprovisionner).',
                'Le tableau de bord montre la situation du jour et donne accès direct à « Nouvelle entrée » et « Nouvelle sortie ».',
                'Votre profil (en haut à droite) permet de changer votre nom, votre numéro et votre mot de passe.',
            ]],
            ['bx bx-package', 'Créer un article', [
                'Articles → « Ajouter ». La référence (unique), la désignation, la catégorie et l\'unité sont obligatoires.',
                'Le seuil minimum déclenche l\'alerte « sous le seuil » ; l\'emplacement aide à retrouver l\'article au magasin.',
                'Les catégories, les unités et les services bénéficiaires se gèrent dans les onglets de l\'écran Articles.',
                'Un article qui n\'est plus utilisé se désactive (case « Article actif ») : il reste dans l\'historique mais n\'est plus proposé.',
                'Pour saisir beaucoup d\'articles d\'un coup : Articles → « Importer (Excel) » → « Télécharger le modèle Excel », remplissez la feuille « Articles », puis envoyez le fichier. S\'il contient une erreur, rien n\'est importé et chaque erreur est indiquée (ligne, colonne, motif).',
            ]],
            ['bx bx-log-in-circle', 'Enregistrer une entrée', [
                'Entrées → « Nouvelle entrée ». Choisissez le fournisseur, la date de réception et saisissez le numéro du bordereau.',
                'Ajoutez une ligne par article (Alt + N) : tapez quelques lettres de la référence ou de la désignation pour le trouver.',
                'Saisissez la quantité livrée ; la quantité acceptée se remplit toute seule et se corrige si une partie est refusée.',
                '« Enregistrer le brouillon » garde la saisie sans toucher au stock. « Enregistrer et valider » met le stock à jour et donne le numéro ENT-AAAA-NNNN.',
            ]],
            ['bx bx-error', 'Contrôler une livraison (écarts)', [
                'Si vous connaissez la quantité commandée, saisissez-la dans « Prévu » : l\'application signale tout de suite un manquant ou un excédent.',
                'Une quantité livrée mais refusée (cassée, non conforme) doit avoir un motif ; elle n\'entre jamais en stock.',
                'Tout écart, ou tout article non prévu au bon de commande, doit être expliqué dans l\'observation de la ligne : sans elle, l\'entrée n\'est pas enregistrée.',
                'Les écarts restent visibles dans la fiche de l\'entrée, sur le bon d\'entrée du magasin et dans le rapport des écarts.',
            ]],
            ['bx bx-log-out-circle', 'Enregistrer une sortie', [
                'Sorties → « Nouvelle sortie ». Indiquez le service bénéficiaire, le demandeur et le motif.',
                'Pour chaque article, le stock disponible et le stock après sortie s\'affichent pendant la saisie.',
                'Une quantité supérieure au stock passe la ligne en rouge et bloque la validation : réduisez la quantité ou attendez la prochaine livraison.',
                '« Valider la sortie » diminue le stock et donne le numéro SOR-AAAA-NNNN.',
            ]],
            ['bx bx-printer', 'Imprimer un bon', [
                'Après chaque validation, l\'application propose d\'imprimer le bon de sortie ou le bon d\'entrée du magasin.',
                'Ils restent imprimables à tout moment depuis la liste (bouton imprimante) ou depuis la fiche du document.',
                'Faites signer les zones prévues (demandeur, magasinier, responsable) et classez l\'exemplaire papier comme d\'habitude.',
            ]],
            ['bx bx-search', 'Rechercher un mouvement', [
                'Dans chaque liste, la zone « Rechercher » filtre instantanément par numéro, article, fournisseur, service ou demandeur.',
                'Les pastilles (brouillons, validées, avec écart, annulées…) et les filtres « Du / Au » limitent la liste à une période.',
                'Stock → bouton historique d\'un article : la fiche de mouvement liste toutes ses entrées, sorties et régularisations avec le solde.',
            ]],
            ['bx bx-transfer-alt', 'Consulter le stock', [
                'Le stock n\'est jamais saisi : il est calculé à partir des entrées, sorties et régularisations validées.',
                'Stock → les pastilles « Sous le seuil » et « En rupture » montrent ce qu\'il faut réapprovisionner.',
                'La fiche de mouvement d\'un article s\'imprime en PDF, pour toute la vie de l\'article ou pour une période.',
            ]],
            ['bx bx-bar-chart-alt-2', 'Générer les rapports', [
                'Rapports → choisissez le rapport (état de stock, entrées, sorties, écarts), la période et un filtre si besoin.',
                '« PDF » ouvre le rapport prêt à imprimer ; « Excel (CSV) » télécharge un fichier qui s\'ouvre dans Excel.',
                'L\'état de stock peut être demandé à une date passée : il est recalculé à partir des mouvements.',
            ]],
            ['bx bx-adjust', 'Corriger une erreur ou régulariser', [
                'Un brouillon se modifie ou se supprime librement.',
                'Une entrée ou une sortie validée ne se modifie plus : annulez-la (bouton flèche retour) en donnant le motif, puis ressaisissez-la correctement. L\'annulation laisse une trace.',
                'Si le stock compté diffère du stock calculé (casse, perte, erreur de comptage), utilisez Stock → « Régulariser » : saisissez la quantité réellement constatée et le motif. La correction reçoit un numéro REG-AAAA-NNNN.',
                'Une entrée dont une partie est déjà sortie ne peut pas être annulée : passez par une régularisation.',
            ]],
        ];
    @endphp

    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="mb-0 text-white"><i class='bx bx-help-circle me-2'></i>GUIDE D'UTILISATION DU MAGASIN</h6>
        </div>
        <div class="card-body">
            <p class="text-muted">Ce guide reprend les opérations de la formation : il suit l'ordre du travail quotidien, de la livraison du fournisseur jusqu'au bon de sortie.</p>
            <div class="accordion" id="aide">
                @foreach ($chapters as $n => [$icon, $title, $steps])
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="aide-h{{ $n }}">
                            <button class="accordion-button {{ $n ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#aide-c{{ $n }}" aria-expanded="{{ $n ? 'false' : 'true' }}" aria-controls="aide-c{{ $n }}">
                                <i class='{{ $icon }} me-2'></i>{{ $n + 1 }}. {{ $title }}
                            </button>
                        </h2>
                        <div id="aide-c{{ $n }}" class="accordion-collapse collapse {{ $n ? '' : 'show' }}" aria-labelledby="aide-h{{ $n }}" data-bs-parent="#aide">
                            <div class="accordion-body">
                                <ol class="mb-0">
                                    @foreach ($steps as $step)
                                        <li class="mb-1">{{ $step }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
