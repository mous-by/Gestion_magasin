# MagaStock — guide du projet (dépôt `Gestion_magasin`)

Ce fichier est la **seule documentation** du dépôt. Il se lit en entier avant de commencer, par le développeur comme par son assistant IA (Claude Code le charge automatiquement ; tout autre assistant doit l'ouvrir en premier). Langue de travail : français. Source officielle des exigences : `docs/cahier_des_charges.pdf` (cahier des charges V1.0 du 02/10/2026, Université de Ségou).

## 0. Règles pour l'assistant IA

1. Le cahier des charges est la source principale. Ce qui n'y est pas est « À VALIDER AVEC LE CLIENT » ; toute idée non prévue est « PROPOSITION TECHNIQUE — À VALIDER » et n'est jamais une exigence.
2. Ne crée ni table ni fonctionnalité « parce qu'elle semble logique » : vérifie d'abord le cahier, les décisions (§5) et le modèle de données (§4.2).
3. Le **socle** (authentification, rôles et permissions, audit, thème) vient du gabarit VigiMoto (`Numerise_vignette`) ; il ne se modifie qu'après revue. Un module s'ajoute **sans** modifier les fichiers du socle (§6).
4. Aucun mot de passe, secret ni numéro réel dans Git. Aucune ressource externe (CDN) : assets locaux (D15).
5. Toute action sensible est journalisée dans `audit_logs` (RG12). Tout modèle métier utilise `LogsActivity`. Les permissions ne se créent pas « à la main » dans la base (§4.4).
6. **Le stock ne se saisit jamais** : il se calcule à partir des mouvements validés (cahier §15, RG09). Aucun écran ne modifie un solde directement.
7. Avant de conclure une tâche : `composer test` vert, `vendor/bin/pint` propre, tâche vérifiée dans le navigateur.
8. Un fichier `.md` de plus n'est pas souhaité : mets à jour **ce fichier**.
9. **Le gabarit du thème est impératif** (§7) : en-têtes de carte bleus, tableaux DataTables au style du thème, **modales** pour créer / modifier / consulter / supprimer. Exception prévue : les formulaires à lignes multiples (entrée, sortie) ont une page dédiée (§7, règle 3).
10. L'assistant ne commite ni ne pousse que si son développeur le demande.

### 0.1 Début de session

1. Lis ce fichier en entier (§4 architecture, §5 décisions, §6 ajout d'un module, §10 plan), puis la section du cahier du module à construire.
2. Vérifie l'environnement (§3) : `composer install`, `.env`, les deux bases, `php artisan migrate --seed`, puis `composer test` **vert avant ta première modification**.
3. **Imite l'existant, ne réinvente pas.** Fichiers de référence :

| Besoin | Fichier de référence |
|---|---|
| Manifeste de module (permissions, défauts par rôle, menu) | `config/modules/articles.php`, `config/modules/users.php` |
| Écran complet (DataTables, filtres, fiche, formulaire partagé en modale) | `app/Http/Controllers/Web/Referentiels/ArticleController.php`, `resources/views/articles/{index,_form,_fiche}.blade.php` |
| Écran de référentiel simple (une ligne de config par champ) | `resources/views/referentiels/_simple.blade.php`, `resources/views/categories/index.blade.php` |
| FormRequest création + modification | `app/Http/Requests/Web/Referentiels/{ReferentielRequest,ArticleRequest}.php` |
| Recherche Select2 (JSON) | `ArticleController::search()` (`/articles/search?q=` : id, libellé, unité, décimales, stock) |
| Document à lignes (page dédiée, brouillon → validation → annulation) | `app/Services/Entrees/EntreeService.php`, `app/Http/Requests/Web/EntreeRequest.php`, `resources/views/entrees/{form,_line,index,_fiche}.blade.php` |
| Écriture du stock / numérotation | `app/Services/Stock/StockMovementService.php`, `app/Services/Documents/DocumentNumberService.php` |
| Document PDF | `resources/views/pdf/_layout.blade.php`, `resources/views/pdf/fiche-entree.blade.php` |
| Logique métier dans un service | `app/Services/Users/UserProvisioningService.php` |
| FormRequest (messages en français) | `app/Http/Requests/Web/UpdateProfileRequest.php` |
| Modèle audité | `app/Models/User.php`, `app/Models/Concerns/LogsActivity.php` |
| Policy | `app/Policies/UserPolicy.php` |
| Tests | `tests/Concerns/BuildsFoundation.php`, `tests/Feature/Users/UserScreenTest.php`, `database/factories/UserFactory.php` |

4. **Pièges connus** :
   - `composer test` tourne sur `db_gestion_magasin_test`, jamais sur la base de développement.
   - MariaDB 10.4 (XAMPP) : pas de requête JSON, pas d'`enum` SQL (chaîne + enum PHP), `created_at` de l'audit en DATETIME.
   - `User::roleName()` et non `role()` ; un seul rôle par utilisateur. Les numéros passent par `App\Support\PhoneNumber::normalize()` (E.164).
   - Pas d'`update()` de masse sans audit (test d'architecture). Pas de permission créée à la main (manifeste + `php artisan permissions:sync`).
   - Quantités en `decimal(12,3)` (mètres, rouleaux entamés…), jamais en `float`.
   - Un serveur déjà lancé sur le port 8000 est peut-être celui d'un autre projet : utiliser `php artisan serve --port=8001`.
   - Blade : ne jamais écrire `@php(...)` en ligne avant un bloc `@php … @endphp` dans la même vue (compilation cassée : variables « indéfinies »). Toujours des blocs (test d'architecture).
   - Quantités affichées par `App\Support\Quantity::format()` (« 2,5 », « 1 250 »), saisies avec virgule ou point.
   - Le stock ne s'écrit que par `StockMovementService::record()`, dans une transaction (verrou sur l'article, refus d'un solde négatif). `articles.stock_quantity` n'est pas remplissable ; `MouvementStock` refuse `update` / `delete` (comme l'audit).
   - Tests : `RefreshDatabase` enveloppe chaque test dans une transaction ; les gardes « doit être appelé dans une transaction » ne sont donc pas testables.
   - Vérifier une page JavaScript : `node --check` sur les scripts rendus, et Chrome headless (`puppeteer-core` installé dans un dossier temporaire, jamais dans le projet).

## 1. Le projet

**MagaStock** (nom proposé, À VALIDER, cahier §33) est l'application web de gestion informatisée du **magasin de l'Université de Ségou** et des mouvements de matériels. La V1 résout d'abord le problème du **magasinier / comptable matière** : enregistrer vite les entrées et les sorties, connaître le stock à tout moment, retrouver l'historique, documenter les écarts de livraison, générer les bons et les états — en remplaçant les bons, bordereaux et fiches papier sans changer leur logique (cahier §1 à §4).

| Acteur (cahier §6) | Rôle technique | Rôle V1 |
|---|---|---|
| Magasinier / Comptable matière | `magasinier` | Utilisateur principal : articles, fournisseurs, entrées, sorties, stock, documents, rapports |
| Administrateur technique | `administrateur` | Comptes, droits, paramètres, sauvegardes, maintenance |
| Développeurs | `superadmin` | Maintenance de la plateforme, rôles, permissions, audit, système |
| Autres services | — | Hors V1 (demandes, validations : évolutions §27) |

**Hors périmètre V1** (cahier §5.2) : workflow de validation administrative, visas (Patrimoine, Finances), comptabilité financière, achats et marchés, patrimoine immobilier, affectations, application mobile native, signature électronique qualifiée. L'architecture doit permettre de les ajouter plus tard (§27) sans les imposer.

**Critère central de réussite** (cahier §34) : le magasinier réalise ses opérations quotidiennes plus vite, avec moins d'erreurs, et une information de stock fiable à tout moment.

## 2. Où en est-on

**Fait — phase 0, socle (repris du gabarit VigiMoto, adapté)** :
- Laravel 13, PHP ≥ 8.3, MariaDB, Blade + Bootstrap 5 + thème « Semi Bleu » local, Spatie Permission, DomPDF (installé pour les bons et états).
- Authentification par **numéro de téléphone** + mot de passe (D26 du gabarit, À VALIDER), mot de passe temporaire à changer, 5 tentatives, comptes désactivables à chaud.
- 3 rôles (`superadmin`, `administrateur`, `magasinier`), plafond de rôle, permissions à deux voies (manifestes + interface), écrans Utilisateurs, Rôles, Permissions, Attribution, Audit, Système (menu **Paramètres**).
- Journal d'audit synchrone et non modifiable (`audit_logs`), superadmins créés depuis `.env`.
- Tableau de bord : chiffres du socle (la maquette fictive du gabarit a été supprimée le 2026-10-07 : aucune donnée fictive dans l'application).
- Sidebar : modules à venir (articles, fournisseurs, entrées, sorties, stock, rapports), chacun avec sa fiche (résumé, articles du cahier, décisions à valider).
- 217 tests automatiques verts (`composer test`), Pint propre.

**Fait — phase 3, référentiels** (2026-10-07) :
- **Articles** (menu Magasin) : fiche du cahier §9, référence unique en majuscules y compris parmi les supprimés (RG01), catégorie et unité actives obligatoires (une catégorie désactivée reste valable pour ses articles existants), seuil décimal seulement si l'unité l'admet, actif / inactif (RG07), filtres (statut, sans seuil, catégorie), fiche en modale, recherche JSON des articles actifs pour les futures entrées et sorties.
- **Catégories**, **Unités** (onglets de l'écran Articles, permission `articles.manage_referentiels`) : un référentiel utilisé par un article (même supprimé) ne se supprime pas, il se désactive. Unités du cahier (U, paquet, rouleau, mètre) créées par `UniteSeeder` (inclus dans `db:seed`).
- **Fournisseurs** (menu Magasin) : fiche du cahier §10, fiche en modale (l'historique des livraisons viendra avec les entrées).
- **Services bénéficiaires** (onglet) : préparés pour les sorties.
- Jeu de démonstration : `php artisan db:seed --class=DemoSeeder` (articles des exemples du cahier §13.1, 2 fournisseurs, 3 services ; jamais en production).
- 239 tests verts.

**Fait — phase 4, entrées** (2026-10-07) :
- **Entrées** (menu Magasin) : page de saisie dédiée (en-tête de livraison + lignes : prévu facultatif, livré, accepté, rejeté calculé, motif, observation), recherche d'article Select2 avec le stock affiché, acceptée = livrée par défaut, écarts calculés en direct (manquant, excédent, rejet, article non prévu), Alt + N pour ajouter une ligne.
- Règles : tout écart exige une observation (RG08), un rejet exige un motif (endommagé, non conforme, autre), acceptée ≤ livrée, décimales selon l'unité, article actif (RG07), date non future, un article une seule fois par entrée.
- Cycle : **brouillon** (sans effet sur le stock) → **validée** (numéro `ENT-AAAA-NNNN` sous verrou, + quantité acceptée en stock, mouvement tracé) → **annulée** (contre-mouvement, motif obligatoire ; refusée si le stock reçu est déjà sorti). On peut enregistrer et valider en une fois, avec confirmation qui rappelle les écarts.
- Liste : filtre par période, pastilles (brouillons, validées, avec écart, annulées), fiche en modale, bon d'entrée du magasin en PDF (25 Ko, brouillon et annulée marqués) proposée à l'impression après validation.
- Articles : colonne et pastilles de stock (sous le seuil, rupture). Fournisseurs : historique des livraisons. Un article ou un fournisseur qui a un historique ne se supprime plus (il se désactive).
- 257 tests verts ; formulaire vérifié dans Chrome headless (aucune erreur JavaScript).

**Fait — phases 5 à 7, sorties, stock, rapports** (2026-10-07) :
- **Sorties** (menu Magasin) : page de saisie rapide (service, demandeur, motif, réf. papier ; par ligne : stock disponible, quantité, stock après en direct), ligne en rouge et bouton « Valider » désactivé au-delà du stock ; contrôle refait sous verrou à la validation (RG04) — la validation refusée ne consomme pas de numéro. `SOR-AAAA-NNNN`, `stock_before` figé par ligne, annulation par contre-mouvement, **bon de sortie PDF** proposé à l'impression.
- **Stock** : état du stock (normal, sous le seuil, rupture, filtre par catégorie, dernier mouvement), **fiche de mouvement** d'un article à l'écran et en PDF (tableau du cahier §15, période avec solde d'ouverture), **régularisation** (stock constaté + motif → `REG-AAAA-NNNN`, ancienne et nouvelle valeur, RG11), `php artisan stock:verify` (RG09).
- **Tableau de bord réel** (cahier §8) : à traiter (ruptures, sous le seuil, brouillons d'entrées et de sorties), articles, entrées et sorties du jour / du mois, dernières entrées, sorties et écarts, accès direct « Nouvelle entrée » / « Nouvelle sortie ». Pastilles du menu (brouillons, articles à réapprovisionner).
- **Rapports** : état de stock (à une date, recalculé depuis les mouvements), rapports des entrées, des sorties et des écarts, filtres période / catégorie / fournisseur / service, PDF ou **CSV pour Excel** (UTF-8, « ; », export audité).

**Fait — phases 8 et 9, ce qui ne dépend pas du client** :
- Jeu de démonstration complet (`DemoSeeder` : référentiels + scénario de l'Annexe A passé par les services).
- Sauvegarde quotidienne planifiée (`backup:run`, `backup:verify`), restauration testée (§9).
- **Guide d'utilisation en ligne** (menu Aide) : un chapitre par thème de la formation du cahier §31.
- « Modules à venir » : les évolutions du cahier §27 (inventaires, demandes, validations, affectations, patrimoine, QR codes).
- **276 tests verts** ; formulaires vérifiés dans Chrome headless.

**Fait — documents réels de l'Université** (reçus le 2026-10-07 : carnet « RECTORAT — BON DE SORTIE », bordereau de livraison de Mamadou A DIALLO, bon des sorties N°2026-029/US-SP du Service Patrimoine) :
- **Bon de sortie au modèle officiel** : en-tête ministère / République du Mali / devise, logo, encadré « RECTORAT », Demandeur, Date, colonnes Désignations / Quantités / Observations (+ unité et numéro, exigés par le cahier §14), quadrillage du carnet, signatures soulignées Le Demandeur / Le Comptable Matière / Visa du Chef de Service des Finances. Même en-tête sur la fiche de réception, la fiche de mouvement et les rapports.
- **Paramètres → Documents** (permission `documents.manage`, administrateur par défaut) : **rien n'est figé dans le code** — en-tête officiel, logo (PNG/JPEG, stocké hors `public/`), signataires de chaque document (titre + nom fixe ou jeton : `{demandeur}`, `{service}`, `{fournisseur}`, `{valide_par}`, `{saisi_par}`, `{utilisateur}`), numérotation (format avec `{prefix}`, `{year}`, `{number}`, nombre de chiffres, préfixes). Aperçu PDF ; chaque modification auditée (table `app_settings`, `App\Support\DocumentSettings` ; valeur absente = défaut de `config/brand.php` et `config/documents.php`).
- **Catalogue réel** (`CatalogueInitialSeeder`, utilisable en production, idempotent) : 40 articles des deux documents, 7 catégories, unités (dont Carton), le fournisseur Mamadou A DIALLO et les services (Rectorat, Service Patrimoine, Division Équipement, Service des Finances). Classement, références et unités : PROPOSITION À VALIDER.
- **Démonstration réelle** (`DemoSeeder`) : l'entrée du bordereau (35 articles ; piles cubiques « 20 sale + 27 » lues comme 20 refusées — à confirmer), la sortie du bon 2026-029/US-SP (18 articles livrés) et un brouillon pour les 5 articles jamais livrés (Fil TH 1,5 et 2,5 mm², boîte ronde, chevilles, vis) que le contrôle du stock bloque.

**Fait — finitions du 2026-10-07** :
- **Plus aucune donnée fictive** : maquette du tableau de bord (`MockDashboardData`, `config/dashboard.php`, `DASHBOARD_MOCK`) supprimée.
- **Écarts de réception à traiter** (cahier §15.1, troisième alerte) : une entrée validée avec écart reste « à traiter » jusqu'à ce que la suite donnée soit enregistrée (complément livré, avoir, retour… — permission `entrees.resolve`, bouton dans la liste des entrées, audité ; visible dans la fiche et le bon d'entrée PDF). Colonnes `entrees.has_discrepancy`, `discrepancy_resolution`, `discrepancy_resolved_at`, `discrepancy_resolved_by`.
- **Tableau de bord par rôle** (`RealDashboardData`, une section par domaine, chacune derrière une permission) : **magasinier** → Magasin (tâches : ruptures, sous le seuil, écarts à traiter, brouillons ; chiffres du jour et du mois ; graphique d'activité des 14 derniers jours en SVG local, palette validée, vue tableau ; articles à réapprovisionner ; dernières entrées, sorties, écarts) ; **administrateur technique** → Comptes et accès (sauf superadmins) + Exploitation (dernière sauvegarde et sa vérification, cohérence du stock, logo et signataires des documents ; permission `exploitation.view`), sans aucune donnée du magasin ; **superadmin** → tout, plus la Supervision (activité par agent sur 30 jours, journal, comptes superadmin). Plus de bande d'accueil : « Nouvelle entrée » / « Nouvelle sortie » à droite du fil d'Ariane. La **cloche** suit la même règle (ruptures, écarts, brouillons, sauvegarde).
- **Comptes superadmin invisibles** pour les autres rôles : absents des listes et des chiffres ; leur nom d'auteur (saisie, validation, mouvements, PDF, signatures) s'affiche « Administration » (`User::displayName()`, `brand.system_actor`) ; une adresse `/users/{id}` qui désigne un superadmin répond 404 comme un compte inexistant.
- **Images du diaporama de connexion** livrées (générées par IA d'après les prompts du §7, vérifiées sans texte ni logo) : `public/assets/images/login/slide-1` à `slide-5.jpg`, 1659 × 948, 200 à 340 Ko ; slide-4 retournée pour garder le sujet hors du formulaire.
- **Base de développement remise à zéro le 2026-10-07** à la demande : plus aucune opération ni aucun nom de personne ; restent le compte superadmin, le catalogue réel (40 articles, 7 catégories, 5 unités), le fournisseur et les services des documents. Pour retrouver une démonstration : `php artisan db:seed --class=DemoSeeder`.
- **Import Excel des articles** (permission `articles.import`, magasinier) : bouton « Importer (Excel) » de l'écran Articles ; **modèle .xlsx à télécharger** (feuilles « Articles » avec listes déroulantes catégorie / unité / statut alimentées par la base, « Listes », « Mode d'emploi ») ; import .xlsx, .xls, .ods ou .csv en **tout ou rien** (rapport ligne / colonne / motif), colonnes repérées par leur titre, catégorie inconnue créée, unité inconnue refusée, référence existante ignorée ou mise à jour (case à cocher), 2 000 lignes au plus, audité (`articles.imported`). `App\Services\Articles\ArticleImportService`, bibliothèque `phpoffice/phpspreadsheet`.
- **Logo de l'Université** installé par défaut (`public/assets/images/brand/logo-universite.png`, recadré) ; remplaçable dans Paramètres → Documents.

**Modifié le 2026-10-11** (demande de l'Université) : la « fiche de réception » s'appelle **« Bon d'entrée du magasin »** (titre du PDF, boutons, aide, fichier `bon-entree-<numéro>.pdf` ; noms techniques internes `fiche_entree` et `pdf/fiche-entree` inchangés pour ne pas casser les réglages enregistrés) ; la signature « Visa du Chef de Service » devient **« Le Magasinier »** sur le bon d'entrée, la fiche de mouvement et les rapports (le « Visa du Chef de Service des Finances » du bon de sortie, tiré du carnet officiel, est conservé). Un réglage déjà enregistré dans Paramètres → Documents garde sa valeur.

**Reste** (dépend du client) : phases 1 et 2 (validation des points ouverts du §5, modèles officiels du bon de sortie et du bon d'entrée du magasin) ; R5 (import de la liste réelle des articles) ; phase 8 (recette sur le terrain avec le magasinier) ; phase 9 (hébergement, HTTPS, formation). La table `documents` (cahier §24) n'est pas créée : les PDF sont générés à la demande, toujours identiques au document validé ; à ajouter seulement si le service veut archiver chaque exemplaire imprimé.

## 3. Démarrer

Prérequis : PHP ≥ 8.3 (extensions `gd` et `mbstring` pour DomPDF), Composer 2, MariaDB 10.4+ ou MySQL 8. Aucun Node (D19).

```sh
composer install
cp .env.example .env
php artisan key:generate
# créer deux bases vides (utf8mb4, utf8mb4_unicode_ci) : db_gestion_magasin et db_gestion_magasin_test
php artisan migrate --seed      # rôles, permissions, superadmins déclarés dans .env
php artisan serve --port=8001   # crée aussi les superadmins manquants au lancement
composer test                   # base db_gestion_magasin_test, jamais la base de développement
```

Dans `.env` (jamais dans Git) : `SUPERADMIN_PASSWORD`, `SUPERADMIN_1_NAME` / `_PHONE` (et `SUPERADMIN_2_*` si un second développeur) ; `BRAND_STRUCTURE` et `BRAND_SERVICE` pour l'en-tête des bons. **Connexion : numéro de téléphone + mot de passe.**

## 4. Architecture

```
Navigateur (Blade + Bootstrap) → routes/web/*.php → session (guard web) → Middleware (auth, active, password.changed, permission:…)
   → Contrôleurs minces → Services (logique métier, transactions) → Modèles → MariaDB
                                       ↑ ActivityLogger → audit_logs (même transaction)
```

1. La logique vit dans des **Services** (une entrée validée, une sortie validée, une régularisation = une méthode de service dans une transaction), jamais dans les contrôleurs.
2. Autorisation en trois couches : permission (`module.action`) → Policy (plafond de rôle, état du document) → règles du service.
3. Le **superadmin** contourne l'autorisation (`Gate::before`), pas la logique métier ni l'audit.
4. L'audit est écrit dans la même transaction que l'action.
5. Modules par manifeste : un module s'ajoute sans modifier le socle.

### 4.1 Tables du socle (13)

| Table | Contenu |
|---|---|
| `users` | `id`, `name` (150), `phone` (20, unique, E.164, identifiant de connexion), `password`, `is_active`, `must_change_password`, `password_changed_at`, `last_login_at`, `remember_token`, timestamps, `deleted_at`. Ni e-mail ni photo (À VALIDER) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie Permission. `permissions.source` = `manifest` ou `custom`. Index **UNIQUE** sur `model_has_roles` (`model_id`, `model_type`) : un seul rôle par utilisateur |
| `audit_logs` | `user_id`, `user_name`, `user_role` (copies figées), `action` (`entrees.validated`…), `module`, `description`, `subject_type`, `subject_id`, `subject_label`, `old_values`, `new_values` (champs modifiés seulement, jamais de secret), `channel` (`web`/`console`), `ip_address`, `user_agent`, `created_at` (DATETIME). Modèle `ActivityLog`, non modifiable |
| `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel (sessions, cache et file d'attente en base) |

### 4.2 Modèle de données métier (✔ = créée)

Tables du cahier §24, colonnes proposées (PROPOSITION TECHNIQUE — À VALIDER en phase 2). Noms de tables en français comme dans le cahier, colonnes en anglais (D3). Tout en `softDeletes` sauf les mouvements, qui ne se suppriment jamais (RG05).

| Table | Colonnes principales | Règles |
|---|---|---|
| `categories` ✔ | `name` (100, unique), `description`, `is_active` | Liste initiale à fournir (§33) |
| `unites` ✔ | `name` (50, unique), `symbol` (15, unique), `allows_decimal` | U, pqt, rlx, m semées |
| `articles` ✔ | `reference` (50, unique, RG01), `designation` (200, index), `category_id`, `unite_id`, `brand_model`, `min_threshold` (decimal 12,3, nullable), `location`, `observation`, `is_active` (RG07) ; `stock_quantity` (decimal, cache, D-M3, ajouté en phase 4) | Inactif = plus utilisable dans une nouvelle opération |
| `fournisseurs` ✔ | `name` (150, unique), `address`, `phone` (libre), `email`, `contact_person`, `admin_references`, `is_active` | Historique des livraisons = ses entrées |
| `services` ✔ | `name` (150, unique), `code` (30, unique, nullable), `is_active` | Services bénéficiaires des sorties (cahier §13, §17) |
| `entrees` ✔ | `number` (unique, `ENT-AAAA-NNNN`), `received_on`, `fournisseur_id`, `delivery_note_number` (bordereau), `order_reference`, `observation`, `status` (`brouillon`, `validee`, `annulee`), `validated_at`, `validated_by`, `created_by` | Numéro attribué à la validation |
| `entree_details` ✔ | `entree_id`, `article_id`, `expected_qty` (nullable), `delivered_qty`, `accepted_qty`, `rejected_qty` (livrée − acceptée), `rejection_reason` (endommagé, non conforme, autre), `observation` | `accepted_qty` seule entre en stock (RG02, §12) |
| `sorties` ✔ | `number` (unique, `SOR-AAAA-NNNN`), `issued_on`, `requester_name`, `service_id`, `reason`, `observation`, `paper_reference`, `status`, `validated_at`, `validated_by`, `created_by` | |
| `sortie_details` ✔ | `sortie_id`, `article_id`, `quantity`, `stock_before`, `observation` | `stock_before` figé pour le bon et la preuve du contrôle |
| `mouvements_stock` ✔ | `article_id`, `type` (`entree`, `sortie`, `regularisation`, `annulation`), `quantity` (signée), `balance_after`, `source_type`, `source_id` (morph vers l'entrée, la sortie, la régularisation), `reference` (numéro du document), `moved_on` (date de l'opération), `user_id`, `reason`, `created_at` | **Jamais modifiée ni supprimée** (comme l'audit) ; stock = somme des `quantity` (Annexe B, RG09) |
| `regularisations` ✔ | `number` (`REG-AAAA-NNNN`), `regularized_on`, `article_id`, `quantity` (signée), `reason` (obligatoire, RG11), `old_balance`, `new_balance`, `user_id` — non modifiable | Ajoutée au §24 du cahier (numérotation REG, §19) — À VALIDER |
| `documents` (non créée) | `type` (`bon_sortie`, `fiche_entree`, `etat_stock`…), `number`, `documentable_type/id`, `path`, `generated_by`, `generated_at` | Trace des PDF générés (cahier §24) |
| `document_sequences` ✔ | `prefix` (ENT, SOR, REG, INV), `year`, `last_number` | Numérotation unique sous verrou (`lockForUpdate`), RG10 — PROPOSITION TECHNIQUE |

### 4.3 Rôles

| Rôle | Niveau | Périmètre | Créé par |
|---|---|---|---|
| `superadmin` | 100 | Tout, audité | Amorçage automatique (`.env`) / `php artisan app:create-superadmin` |
| `administrateur` | 80 | Comptes (sauf superadmins), paramètres, sauvegardes ; aucun privilège technique réservé | Superadmin |
| `magasinier` | 50 | Toutes les opérations du magasin (permissions données par les manifestes des modules) | Administrateur, superadmin |

Plafond de rôle (`config/role_hierarchy.php`) : superadmin → tous ; administrateur → magasinier ; magasinier → aucun. Droits exacts de l'administrateur et du magasinier : À VALIDER (cahier §33) ; l'administrateur pourra recevoir la consultation des données métier en lecture si le client le souhaite.

### 4.4 Permissions (deux voies)

Convention `module.action`. **Voie A** : manifeste `config/modules/<module>.php` (`label`, `permissions`, `reserved`, par rôle `defaults` / `optional`, `navigation`) + `php artisan permissions:sync` (à chaque déploiement, après `migrate`). **Voie B** : le superadmin crée des permissions `custom` depuis **Paramètres → Permissions**. Toutes les routes portent `permission:module.action` (jamais `role:`). Réservées au superadmin : `roles.*`, `permissions.*`, `audit.*`, `system.*`. Défauts actuels : `administrateur` = `users.*` sauf `delete` (en exception).

Permissions des modules livrés (magasinier par défaut ; `delete` en exception ; l'administrateur peut recevoir la consultation en exception) : `articles.view/create/update/delete/manage_referentiels` (catégories et unités), `fournisseurs.view/create/update/delete`, `services.view/create/update/delete`. `entrees.view/create/update/validate/cancel` (toutes au magasinier, y compris l'annulation — À VALIDER ; `view` en exception pour l'administrateur). Prévues (phases 5 à 7) : `sorties.view/create/update/validate/cancel/force` (dépassement de stock, si la régularisation est autorisée, cahier §13.1), `stock.view/regularize`, `rapports.view/export`.

### 4.5 Audit

`ActivityLogger::log()` pour les actions métier (`entrees.validated`, `sorties.validated`, `stock.regularized`, `documents.generated`…), trait `LogsActivity` pour création / modification / suppression / restauration (anciennes et nouvelles valeurs, cahier §16). Les lectures ne sont pas journalisées. L'écran Audit est réservé au superadmin ; l'ouvrir à l'administrateur : À VALIDER.

## 5. Décisions

| # | Décision |
|---|---|
| D-G | **Gabarit** : socle, thème, conventions et tests repris de `Numerise_vignette` (VigiMoto), sans ses modules métier, son API mobile, ses institutions (commissariats, mairies) ni Sanctum. Les décisions D1 à D27 du gabarit restent valables quand elles concernent le socle (MariaDB, Laravel 13, audit, permissions à deux voies, assets locaux D15, pas de Vite D19, thème D20/D24, maquette D25, connexion par téléphone D26, superadmins automatiques D27) |
| D-1 | Nom du dépôt et du dossier : `Gestion_magasin`. Nom affiché : **MagaStock** (proposé, `config/brand.php` + `APP_NAME`) — nom officiel À VALIDER (cahier §33) |
| D-2 | Rôles : `superadmin`, `administrateur`, `magasinier` (cahier §6). Pas de canal API ni de compte pour les autres services en V1 |
| D-3 | Table d'audit nommée `audit_logs` (cahier §24) ; le modèle garde le nom `ActivityLog` du gabarit |
| D-4 | Pas d'institution : un seul magasin en V1. Plusieurs magasins / sites : évolution, À VALIDER |
| D-5 | PDF avec `barryvdh/laravel-dompdf` (police embarquée en sous-ensemble : PDF de ~25 Ko) ; « Excel éventuel » (cahier §23) = export **CSV** UTF-8 « ; » qui s'ouvre dans Excel, sans bibliothèque. Un vrai .xlsx : À VALIDER |
| D-M1 | **Validation en deux temps** : une entrée ou une sortie est saisie en `brouillon` (modifiable, sans effet sur le stock), puis **validée** (numéro attribué, mouvements écrits, stock mis à jour, document généré). Une opération validée n'est plus modifiable : elle s'**annule** par un contre-mouvement tracé (RG05) — PROPOSITION TECHNIQUE |
| D-M2 | **Contrôle du stock à la validation** dans une transaction avec verrou sur les articles (`lockForUpdate`), pas seulement à l'affichage : deux sorties simultanées ne peuvent pas rendre le stock négatif (RG04) |
| D-M3 | `articles.stock_quantity` est un **cache** mis à jour dans la même transaction que le mouvement ; la vérité est `SUM(mouvements_stock.quantity)`. Commande `php artisan stock:verify` (et test) qui signale tout écart (RG09) — PROPOSITION TECHNIQUE |
| D-M4 | Quantité **endommagée / rejetée** : jamais en stock (seule l'acceptée entre, cahier §12) ; elle reste visible dans l'entrée et le rapport des écarts. Un stock « en quarantaine » séparé : À VALIDER |
| D-M6 | Une sortie au-delà du stock est **toujours** refusée. La « procédure de régularisation explicitement autorisée » du cahier §13.1 est la régularisation de stock (permission `stock.regularize`, motif obligatoire), pas un forçage de la sortie — À VALIDER |
| D-M7 | Une entrée dont une partie est déjà sortie ne peut pas être annulée (le stock deviendrait négatif) : on passe par une régularisation |
| D-M8 | Le magasinier reçoit par défaut toutes les permissions de son métier, y compris l'annulation et la régularisation (cahier §31) ; la suppression d'un article, d'un fournisseur ou d'un service est une exception à accorder. L'administrateur technique ne voit pas les données du magasin par défaut (consultation en exception) — droits exacts À VALIDER (cahier §33) |
| D-M9 | Tout ce qui figure sur les documents imprimés (en-tête, logo, noms et titres des signataires, numérotation) se règle dans l'interface (Paramètres → Documents), jamais dans le code ; un nom de signataire peut venir du document (jetons) |
| D-M5 | Numérotation `ENT-2026-0001`, `SOR-2026-0001`, `REG-2026-0001` (cahier §19), remise à 1 chaque année, attribuée à la validation (pas de trou pour les brouillons abandonnés) — nomenclature officielle À VALIDER |

### Points ouverts — À VALIDER AVEC LE CLIENT (cahier §33)

Toutes les décisions marquées « PROPOSITION TECHNIQUE » ou « À VALIDER » ci-dessus ont une implémentation par défaut : rien ne bloque la démonstration, mais chaque point se tranche avant la mise en production.

Avant la mise en production : images du diaporama de connexion (prompts §7) ; noms des signataires (Chef de Service des Finances…) ; validation du catalogue réel (catégories, références, unité du « Compact 250 DC », lecture « 20 sale + 27 ») ; nom officiel de l'application et nom exact du service (phase 2) ; liste initiale des articles, des catégories et des unités, seuils de stock (phase 3) ; modèle définitif du bordereau / de la fiche d'entrée (phase 4) ; modèle définitif du bon de sortie et liste des services bénéficiaires (phase 5) ; règles de régularisation : qui, quels motifs, sortie au-delà du stock autorisée ou non (phase 6) ; format des rapports, Excel ou non (phase 7) ; hébergement et sauvegarde (phase 9) ; droits exacts de l'administrateur et du magasinier.

Non bloquants : connexion par téléphone (gabarit) ou par e-mail / identifiant ; images du diaporama de connexion (`public/assets/images/login/slide-1` à `slide-5`, JPEG ≤ 400 Ko, paysage ≥ 1280 px : photos réelles du magasin conseillées) ; logo de l'Université sur les bons (autorisation d'usage).

## 6. Comment ajouter un module

Principe : **un module ne modifie jamais les fichiers du socle**. Les modules prévus sont listés dans `config/planned_modules.php` (ne pas modifier : l'entrée disparaît quand le manifeste existe).

0. Une suppression ne casse jamais l'historique : soft delete, et un objet utilisé (article par une entrée, fournisseur par une entrée, catégorie par un article…) se **désactive** au lieu de se supprimer (règle dans le contrôleur, jamais dans la policy que `Gate::before` contourne).
1. Manifeste `config/modules/<module>.php` (permissions, défauts du rôle `magasinier`, `navigation` avec `route`, `permission`, `label`, `icon`, `active`, `order`) puis `php artisan permissions:sync`.
2. Migrations : `softDeletes` (sauf mouvements), index, clés étrangères `restrictOnDelete`, quantités `decimal(12,3)`. Ajouter les tables au test `tests/Feature/Database/SchemaTest.php`.
3. Modèle avec `LogsActivity` ; alias morph dans `AppServiceProvider` (`Relation::morphMap`).
4. Policy, FormRequests (messages en français), **Service**, contrôleur `Web/Admin`, `routes/web/<module>.php` (`auth`, `active`, `password.changed`, `permission:…`).
5. Vues : `@extends('layouts.admin')`, gabarit du §7.
6. Audit explicite des actions métier (`ActivityLogger::log('entrees.validated', …)`).
7. Tests obligatoires : permission, règles de gestion du module, audit ; tests d'architecture verts.

## 7. Interface Web

- **Thème** « Dashkote Admin » + `public/assets/css/numerise.css` (palette : bleu `#1d4e89`, bleu foncé `#123a63`, orange `#f97316` ; police Inter ; icônes Boxicons). Structure : `layouts/admin.blade.php` et `partials/`. Le fichier CSS garde son nom du gabarit.
- **Sidebar** : recherche, Tableau de bord, entrées des manifestes groupées (**Magasin** : articles, fournisseurs, entrées, sorties, stock, rapports ; **Administration**), modules à venir repliés, **Paramètres** en bas (Utilisateurs, Rôles, Permissions, Attribution, Audit, Système).
- **Onglets des référentiels** (`config/referentiels.php`, `partials/referentiels-tabs`) : l'entrée « Articles » mène aux onglets Articles, Catégories, Unités, Services bénéficiaires (chaque onglet selon sa permission).

### Règles impératives (gabarit du thème)

1. **En-tête de carte** : `<div class="card-header card-header-brand …">`, titre `<h6 class="mb-0 text-white"><i class='bx bx-…'></i>TITRE EN MAJUSCULES</h6>`, boutons `btn btn-light btn-sm` à droite.
2. **Tableaux** : `<table class="table">`, `<thead>` sans classe de couleur, DataTables (libellés français), boutons d'action `btn btn-primary btn-sm` à icône (`bx-show` consulter, `bx-edit` modifier, `bx-trash` supprimer, `bx-check-shield` valider, `bx-printer` imprimer). Pastilles de filtre `nv-chips` et fiche de consultation en modale (`_fiche.blade.php`) comme l'écran Utilisateurs.
3. **Modales** pour les fiches simples (catégorie, unité, article, fournisseur, service). **Exception validée par ce plan** : la saisie d'une entrée et d'une sortie (lignes multiples) se fait sur une **page dédiée** (`entrees/create`, `sorties/create`), optimisée pour la saisie rapide (cahier §13) : recherche d'article Select2 en AJAX (`ajax-select`, `data-search-url`), stock disponible affiché sur chaque ligne et recalculé en direct, ligne en rouge et bouton « Valider » désactivé si une quantité dépasse le stock, raccourci clavier pour ajouter une ligne.
4. **Erreurs de validation** : la modale se rouvre avec les erreurs ; suppression confirmée par SweetAlert2 ; messages `session('status')` / `session('error')`.
5. **Fil d'ariane** en tête de page, puis `<hr />`.
6. **Documents PDF** : vues Blade dédiées `resources/views/pdf/*.blade.php` (en-tête : `brand.structure`, `brand.service`, numéro, date ; tableau désignation / unité / quantité / observations ; zones de signature), sans CSS externe.
7. Interface adaptée à l'ordinateur, la tablette et le téléphone (cahier §20) : tableaux en défilement horizontal, formulaires en une colonne sous 768 px.

### Images du diaporama de connexion (prompts pour un générateur d'images)

Cinq images, une par diapositive de `config/brand.php` (`login_slides`) : à déposer dans `public/assets/images/login/` sous les noms `slide-1.jpg` à `slide-5.jpg`, **JPEG ≤ 400 Ko, 1280 px de large au moins, format paysage (16:9)** (test `LoginCarouselTest`). Le formulaire occupe le tiers droit et les légendes le bas gauche : sujet au centre gauche. Générer 2 ou 3 variantes par image, garder la plus réaliste, faire relire décors et tenues par une personne de l'Université, vérifier les droits d'usage de l'outil, convertir en JPEG qualité ≈ 80.

*Style commun (à placer au début de chaque prompt)* : « Photorealistic documentary photograph, natural soft daylight, Ségou (Mali), West African university setting, warm sahelian tones with clean blue accents, 35mm lens, shallow depth of field, high detail, wide 16:9 landscape frame (1792x1024), main subject in the left-center of the frame, calm uncluttered space on the right third and along the bottom edge. Absolutely no writing anywhere: no signs, no labels with letters, no posters, no logos or brand names on boxes, products, clothing or screens, no flags, no coat of arms, no QR codes. »

1. `slide-1` (Entrées) : « Inside a tidy university storeroom, a Malian storekeeper in a plain shirt receives a delivery: he checks sealed cardboard boxes of electrical supplies (LED tube lights, cable reels) against a blank delivery sheet on a clipboard, while a delivery man stands beside a hand trolley; metal shelving in the background, soft light from a high window. »
2. `slide-2` (Sorties) : « At the counter of a university storeroom, a Malian storekeeper hands a small box of light bulbs and a coil of electric cable to a smiling technician in plain blue work clothes; a tablet on the counter shows a simple blank interface with soft colored blocks; orderly shelves behind them. »
3. `slide-3` (Stock) : « Wide view of a well-organized storeroom with long metal shelves holding neatly stacked unbranded boxes, cable reels, fans and paper reams, each shelf section tidy; a Malian woman storekeeper counts items with a tablet in hand, warm daylight, sense of order and reliability. »
4. `slide-4` (Documents) : « Close view of an office desk in a university administration: a printer outputs a sheet with a blank table layout, a pen and a rubber stamp beside it, a laptop with a blurred screen, a Malian administrative officer's hands signing a blank form; calm blue and cream tones. »
5. `slide-5` (Traçabilité) : « A Malian storekeeper and a university administrator review together, on a laptop with a softly blurred dashboard of abstract charts, the history of the storeroom; bright modern office with a window overlooking a sunny campus courtyard with neem trees, collaborative and trustworthy mood. »

*Prompt négatif* : « text, letters, numbers, words, signs, labels, posters, banners, watermark, logo, brand name, signature, QR code, national flag, coat of arms, emblem, uniform insignia, weapons, deformed hands, extra fingers, blurry, low resolution, cartoon, illustration, oversaturated, distorted faces, cluttered composition. »

## 8. Règles de gestion → où elles vivent

| Règle (cahier §26) | Implémentation prévue | Test |
|---|---|---|
| RG01 référence article unique | index UNIQUE + `ArticleRequest` | `ArticleScreenTest` |
| RG02 entrée validée = + quantité acceptée | `EntreeService::validate()` | `EntreeWorkflowTest` |
| RG03 sortie validée = − quantité | `SortieService::validate()` | `SortieWorkflowTest` |
| RG04 sortie > stock refusée | `SortieRequest` (message par ligne) + `StockMovementService::record()` sous verrou (D-M2) + formulaire (ligne rouge, validation désactivée) | `SortieWorkflowTest` |
| RG05 mouvement validé jamais supprimé sans trace | `MouvementStock` et `Regularisation` refusent `update`/`delete` ; annulation par contre-mouvement ; test d'architecture | `EntreeWorkflowTest`, `FoundationRulesTest` |
| RG06 date, référence, utilisateur sur tout mouvement | colonnes NOT NULL de `mouvements_stock` | `EntreeWorkflowTest` |
| RG07 article inactif interdit dans une nouvelle opération | validation des lignes, revérifié à la validation, recherche AJAX filtrée | `EntreeWorkflowTest`, `SortieWorkflowTest` |
| RG08 écarts documentés | `EntreeRequest` : observation obligatoire pour manquant, excédent, rejet, article non prévu ; motif du rejet | `EntreeWorkflowTest` |
| RG09 stock = mouvements validés | D-M3, `stock:verify` (planifié chaque semaine) | `StockAndRapportsTest` |
| RG10 références de documents uniques | `DocumentNumberService` (`document_sequences` sous verrou) + index UNIQUE | `EntreeWorkflowTest` |
| RG11 correction de stock justifiée | `RegularisationService` : motif obligatoire, ancienne et nouvelle valeur | `StockAndRapportsTest` |
| RG12 actions sensibles journalisées | `ActivityLogger` dans chaque service (validations, annulations, régularisations, exports, sauvegardes) | tous les tests de module |

## 9. Exploitation : installation, sauvegarde, restauration, déploiement

### Installation sur un serveur (Linux)

1. PHP ≥ 8.3 (extensions `pdo_mysql`, `mbstring`, `gd`, `zip`), Composer 2, MariaDB 10.4+ ou MySQL 8, serveur web (Apache ou Nginx) pointant sur `public/`, **HTTPS obligatoire** (cahier §21).
2. `composer install --no-dev --optimize-autoloader`, `cp .env.example .env`, `php artisan key:generate`.
3. `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, base, `SUPERADMIN_*` (mot de passe **fort** : il est temporaire hors `local`), `BRAND_STRUCTURE`, `BRAND_SERVICE`, `BACKUP_MYSQLDUMP`, `BACKUP_COPY_PATH`.
4. `php artisan migrate --force && php artisan db:seed --force` (rôles, permissions, superadmins, unités) — **jamais** `DemoSeeder` en production (il s'y refuse).
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Cron : `* * * * * cd /chemin/Gestion_magasin && php artisan schedule:run >> /dev/null 2>&1` (sauvegarde quotidienne à `BACKUP_TIME`, vérification de la sauvegarde, `stock:verify` chaque lundi).
7. Compte de base de l'application sans `UPDATE` ni `DELETE` sur `audit_logs`, `mouvements_stock` et `regularisations` (journaux non modifiables), un compte distinct pour les migrations.
8. À chaque mise à jour : `git pull`, `composer install --no-dev`, `php artisan migrate --force`, `php artisan permissions:sync`, `php artisan optimize`.

### Sauvegarde (cahier §22)

- `php artisan backup:run` : `mysqldump --single-transaction` compressé dans `storage/app/backups/` (`BACKUP_PATH`), `BACKUP_KEEP` versions conservées (30 par défaut), copie séparée dans `BACKUP_COPY_PATH` (autre disque, partage réseau). Mot de passe passé par `MYSQL_PWD`, jamais en ligne de commande. Chaque sauvegarde (ou échec) est journalisée dans l'audit.
- `php artisan backup:verify [fichier]` : archive lisible, dump complet, tables du magasin présentes. Lancée automatiquement après chaque sauvegarde.
- XAMPP local : `BACKUP_MYSQLDUMP=/opt/lampp/bin/mysqldump`. Les procédures stockées ne sont pas sauvegardées (l'application n'en a pas) ; sur ce XAMPP, `mysql.proc` est d'une ancienne version (`mysql_upgrade` la corrigerait) et faisait échouer `--routines`.

### Restauration (procédure testée le 2026-10-07)

1. Choisir l'archive : `ls storage/app/backups/` (ou la copie séparée), puis `php artisan backup:verify <fichier>`.
2. **Test de restauration périodique** (au moins une fois par mois, cahier §22), sans toucher à la production :
   ```sh
   mysql -u root -p -e "CREATE DATABASE db_gestion_magasin_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   gunzip -c storage/app/backups/<fichier>.sql.gz | mysql -u root -p db_gestion_magasin_restore_test
   mysql -u root -p -e "SELECT COUNT(*) FROM db_gestion_magasin_restore_test.mouvements_stock"
   mysql -u root -p -e "DROP DATABASE db_gestion_magasin_restore_test"
   ```
3. **Restauration réelle** : `php artisan down`, sauvegarder l'état actuel (`php artisan backup:run`), recréer la base vide, `gunzip -c <fichier> | mysql … db_gestion_magasin`, `php artisan migrate --force` (si la sauvegarde précède une mise à jour), `php artisan stock:verify`, `php artisan up`. Prévenir le magasinier : les opérations saisies après l'heure de la sauvegarde sont à ressaisir.

### Documentation utilisateur et formation

Guide en ligne dans l'application (menu **Aide**, `resources/views/aide/index.blade.php`), un chapitre par thème de la formation (cahier §31). Pour la formation : `php artisan db:seed --class=DemoSeeder` sur une base de démonstration, puis dérouler l'Annexe A avec le magasinier (livraison avec écart, sortie, bon, rapport, régularisation).

## 10. Plan de développement

Phases du cahier (§28), découpées en tâches. Chaque tâche se termine par ses tests et une vérification dans le navigateur. Estimation indicative pour un développeur avec assistant IA.

| Phase | Contenu | Livrable | Statut |
|---|---|---|---|
| 0 | Socle du gabarit adapté (authentification, rôles, permissions, audit, thème, maquette) | Application qui démarre, 217 tests verts | **Fait** |
| 1 | Analyse et validation | Cahier des charges final : réponses aux points §5 « À VALIDER » | À faire (client) |
| 2 | Conception | Maquettes des écrans entrée / sortie / stock, modèle de données §4.2 validé, modèles du bon de sortie et de la fiche d'entrée récupérés | À faire |
| 3 | Référentiels | Articles, catégories, unités, fournisseurs, services | **Fait** (R5, import de la liste réelle, attend la liste du client) |
| 4 | Entrées | Module de réception fonctionnel (écarts compris) + fiche d'entrée PDF | **Fait** |
| 5 | Sorties | Module de sortie + bon de sortie PDF | **Fait** |
| 6 | Stock | Stock, mouvements, alertes, régularisations, tableau de bord réel | **Fait** |
| 7 | Rapports | État de stock, fiche de mouvement, rapports entrées / sorties / écarts (PDF, Excel éventuel) | **Fait** (P4 : la recherche se fait dans chaque liste, cahier §17 « recherche rapide dans les tableaux ») |
| 8 | Tests terrain | Corrections validées avec le magasinier, données réelles | Démonstration prête (T1) ; recette T2, T3 avec le client |
| 9 | Déploiement | Production HTTPS, sauvegardes, documentation, formation | Sauvegarde, restauration, procédure et guide en ligne prêts (§9) ; hébergement et formation avec le client |

### Détail des tâches

**Phase 2 — Conception**
- C1. Faire valider le modèle de données §4.2 et les décisions D-M1 à D-M5.
- C2. Récupérer les modèles papier (bon de sortie, bordereau, fiche de stock) et les reproduire en maquette PDF.
- C3. Maquettes HTML statiques des pages « Nouvelle entrée » et « Nouvelle sortie » à faire essayer au magasinier.

**Phase 3 — Référentiels** (cahier §9, §10 ; recette 9, 10)
- R1. `categories` + `unites` : manifeste `articles` (un seul module, onglets Catégories / Unités dans l'écran Articles), migrations, modèles, écrans en modales.
- R2. `articles` : fiche du §9 (champs obligatoires et facultatifs), référence unique (RG01), statut actif / inactif (RG07), recherche par référence / désignation, filtre par catégorie, `search()` JSON pour Select2.
- R3. `fournisseurs` : fiche du §10, recherche ; l'historique des livraisons s'affiche dans la fiche dès la phase 4.
- R4. `services` : liste des services bénéficiaires (écran simple, dans Paramètres ou Référentiels).
- R5. Import initial des articles (CSV ou seeder depuis la liste du client, §33) — selon le format fourni.

**Phase 4 — Entrées** (cahier §11, §12, §25 ; recette 11 à 14)
- E1. Migrations `entrees`, `entree_details`, `mouvements_stock`, `document_sequences`, `documents` ; modèle `MouvementStock` non modifiable.
- E2. `DocumentNumberService` : numéro `ENT-AAAA-NNNN` sous verrou (RG10).
- E3. Page « Nouvelle entrée » : fournisseur, bordereau, commande, lignes (prévu, livré, accepté, rejeté calculé), observation par ligne.
- E4. Écarts (§12) : écart négatif / excédent affichés, observation exigée, alerte « article non prévu », quantité endommagée isolée, seule l'acceptée en stock.
- E5. `EntreeService::validate()` : transaction, numéro, mouvements, stock, audit `entrees.validated` ; annulation par contre-mouvement (`entrees.cancelled`).
- E6. Liste des entrées (filtres période, fournisseur, statut, « avec écart »), fiche en modale, fiche d'entrée / réception PDF.

**Phase 5 — Sorties** (cahier §13, §14 ; recette 15 à 18, 20)
- S1. Migrations `sorties`, `sortie_details`.
- S2. Page « Nouvelle sortie » optimisée : demandeur, service, motif, référence papier, lignes avec stock disponible en direct, blocage au-delà du stock (§13.1).
- S3. `SortieService::validate()` : verrou, contrôle RG04, numéro `SOR-AAAA-NNNN`, mouvements, audit ; procédure de dépassement seulement si le client l'autorise (permission `sorties.force`, motif obligatoire).
- S4. Bon de sortie PDF (structure, numéro, date, désignation, unité, quantité, observations, signatures) généré à la validation et réimprimable ; trace dans `documents`.
- S5. Liste des sorties (filtres période, service, demandeur), fiche, annulation tracée.

**Phase 6 — Stock** (cahier §8, §15, §16, Annexe B ; recette 14, 18, 19)
- K1. État du stock : article, catégorie, unité, stock, seuil, statut (normal, sous seuil, rupture), filtres.
- K2. Fiche de mouvement d'un article (date, référence, opération, entrée, sortie, solde — tableau du §15), historique complet (§16).
- K3. Alertes : sous seuil, rupture, écart de réception non traité ; pastilles dans la sidebar (`sidebarBadges`).
- K4. Régularisations `REG-AAAA-NNNN` avec motif obligatoire et anciennes / nouvelles valeurs (RG11).
- K5. `php artisan stock:verify` + test : stock en cache = somme des mouvements (RG09).
- K6. Tableau de bord réel du §8 dans `RealDashboardData` (articles, entrées et sorties du jour / du mois, sous seuil, rupture, dernières entrées / sorties / écarts, boutons « Nouvelle entrée » et « Nouvelle sortie »).

**Phase 7 — Rapports** (cahier §17, §18 ; recette 21, 22)
- P1. État de stock PDF (à une date donnée : solde recalculé à partir des mouvements).
- P2. Rapports des entrées, des sorties et des écarts par période (filtres catégorie, fournisseur, service, type de mouvement) en PDF.
- P3. Export Excel si validé (D-5).
- P4. Recherche globale (référence, désignation, fournisseur, service — cahier §17).

**Phase 8 — Tests terrain** (recette 9 à 23 complète)
- T1. Jeu de données de démonstration (`DemoSeeder` : articles, fournisseurs, entrées avec écart, sorties) — livrable « environnement de démonstration » (§30).
- T2. Séance de recette avec le magasinier sur le scénario de l'Annexe A ; corrections.
- T3. Mesure de la saisie d'une sortie (objectif : quelques secondes par ligne).

**Phase 9 — Déploiement** (cahier §21, §22, §30, §31)
- D1. Serveur Linux, HTTPS, `APP_ENV=production`, compte de base sans UPDATE/DELETE sur `audit_logs` et `mouvements_stock`.
- D2. Sauvegarde quotidienne de la base (cron `mysqldump` compressé, rotation de plusieurs versions, copie séparée), procédure de restauration écrite **ici** et testée.
- D3. Documentation d'installation et utilisateur (section de ce fichier ou PDF utilisateur selon le client), modèles PDF.
- D4. Formation pratique du magasinier (cahier §31).

### Critères de recette (cahier §29) → phase

Tous couverts par un test automatique ; la recette sur le terrain (phase 8) reste à faire avec le magasinier.

| Critère | Où | | Critère | Où |
|---|---|---|---|---|
| Créer et rechercher un article | Articles ✔ | | Voir le stock avant la sortie | Nouvelle sortie ✔ |
| Créer et rechercher un fournisseur | Fournisseurs ✔ | | Empêcher une sortie supérieure au stock | Nouvelle sortie ✔ |
| Entrée à plusieurs articles | Nouvelle entrée ✔ | | Valider une sortie, vérifier le solde | Sorties, Stock ✔ |
| Saisir les quantités réellement reçues | Nouvelle entrée ✔ | | Historique d'un article | Stock → fiche de mouvement ✔ |
| Signaler un écart de livraison | Nouvelle entrée ✔ | | Bon de sortie PDF | Sorties ✔ |
| Valider une entrée, vérifier le stock | Entrées, Stock ✔ | | État de stock | Rapports ✔ |
| Sortie à plusieurs articles | Nouvelle sortie ✔ | | Rapports entrées et sorties | Rapports ✔ |
| | | | Journal des opérations | Paramètres → Audit ✔ |

### Ordre de travail

Phase 1 et 2 en parallèle des référentiels (phase 3 ne dépend que de la liste des articles) ; puis 4 → 5 → 6 → 7 strictement dans cet ordre (chaque phase s'appuie sur les mouvements de la précédente) ; 8 et 9 à la fin. Une branche `feature/<phase><n>-<nom>` par tâche (ex. `feature/R2-articles`), messages de commit en français avec préfixe (`feat(R2) : …`, `fix : …`, `test : …`, `docs : …`).
