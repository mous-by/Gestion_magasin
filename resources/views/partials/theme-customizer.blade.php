<div class="switcher-wrapper">
    <div class="switcher-btn"><i class='bx bx-cog bx-spin'></i></div>

    <div class="switcher-body">
        <div class="d-flex align-items-center">
            <h5 class="mb-0 text-uppercase">Personnalisation</h5>
            <button type="button" class="btn-close ms-auto close-switcher" aria-label="Fermer"></button>
        </div>
        <hr />
        <h6 class="mb-0">Style du thème</h6>
        <hr />
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="themeStyle" id="lightmode">
                <label class="form-check-label" for="lightmode">Clair</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="themeStyle" id="darkmode">
                <label class="form-check-label" for="darkmode">Sombre</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="themeStyle" id="semidark">
                <label class="form-check-label" for="semidark">Semi-sombre</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="themeStyle" id="semibleu" checked>
                <label class="form-check-label" for="semibleu">Semi-bleu</label>
            </div>
        </div>
        <hr />
        <div class="form-check">
            <input class="form-check-input" type="radio" id="minimaltheme" name="themeStyle">
            <label class="form-check-label" for="minimaltheme">Thème minimal</label>
        </div>
        <hr />
        <h6 class="mb-0">Couleurs de l'en-tête</h6>
        <hr />
        <div class="header-colors-indigators">
            <div class="row row-cols-auto g-3">
                @for ($i = 1; $i <= 8; $i++)
                    <div class="col">
                        <div class="indigator headercolor{{ $i }}" id="headercolor{{ $i }}"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</div>
