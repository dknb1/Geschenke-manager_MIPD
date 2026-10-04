<?php
// Interessen-Haekchen fuer Person anlegen/bearbeiten. Erwartet $interessen (gewaehlte Schluessel).
?>
<fieldset class="interessen-auswahl">
    <legend>Interessen (optional)</legend>
    <p class="hinweis-klein">
        Grundlage für die Ideengenerierung. Es werden nur diese festen Kategorien übertragen,
        keine weiteren Angaben zur Person.
    </p>

    <div class="interessen-raster">
        <?php foreach (Interesse::KATEGORIEN as $schluessel => $bezeichnung): ?>
            <label class="anlass-checkbox">
                <input type="checkbox"
                       name="interessen[]"
                       value="<?= htmlspecialchars($schluessel) ?>"
                       <?= in_array($schluessel, $interessen, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($bezeichnung) ?>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
