{{-- Bouton « œil » sur tous les champs de mot de passe (mises en page admin et invité) : affiche ou masque la saisie.
     Ajouté automatiquement à chaque input[type=password], y compris ceux ajoutés plus tard dans la page ; un champ qui
     a déjà son bouton (page de connexion) ou porte data-no-toggle est laissé tel quel. --}}
<style>
    /* Champ et bouton forment un seul bloc : pas d'arrondi ni de bordure entre les deux. */
    .input-group > .pw-input { border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; border-right: 0; }
    .input-group > .pw-toggle { border: 1px solid #dfe5ee; border-left: 0; border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; background: #fff; color: #64748b; }
    .pw-toggle:hover, .pw-toggle:focus-visible { color: var(--nv-hover-fg, #1d4e89); background: var(--nv-hover-bg, #eef4fb); }
    .input-group > .pw-input.is-invalid + .pw-toggle { border-color: #dc3545; }
    .input-group > .pw-input:focus + .pw-toggle { border-color: #86b7fe; }
    html.dark-theme .pw-toggle { background: transparent; border-color: rgba(255, 255, 255, .15); color: #c3c2b7; }
</style>
<script>
    (function () {
        function enhance(input) {
            if (input.dataset.pwToggle || input.hasAttribute('data-no-toggle')) return;
            const group = input.closest('.input-group');
            if (group && group.querySelector('.password-toggle, .pw-toggle')) return;
            input.dataset.pwToggle = '1';
            input.classList.add('pw-input');

            let wrap = group;
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'input-group has-validation';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
                // Le message d'erreur Bootstrap doit être dans le groupe pour rester visible.
                const feedback = wrap.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) wrap.appendChild(feedback);
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn pw-toggle';
            button.setAttribute('aria-label', 'Afficher le mot de passe');
            button.setAttribute('aria-pressed', 'false');
            button.innerHTML = "<i class='bx bx-show'></i>";
            input.insertAdjacentElement('afterend', button);

            button.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(show));
                button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                button.querySelector('i').className = show ? 'bx bx-hide' : 'bx bx-show';
                input.focus();
            });
        }

        function scan(container) {
            (container.querySelectorAll ? container : document).querySelectorAll('input[type="password"]').forEach(enhance);
        }

        document.addEventListener('DOMContentLoaded', function () {
            scan(document);
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches('input[type="password"]')) enhance(n); else scan(n); } }); });
            }).observe(document.body, { childList: true, subtree: true });
        });
    })();
</script>
