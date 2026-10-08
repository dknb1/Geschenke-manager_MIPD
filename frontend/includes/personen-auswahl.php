<?php
// Personen-Haekchen fuer Anlass erstellen/bearbeiten. Erwartet $personIds (gewaehlte IDs).
// Haekchen statt Mehrfachauswahl, damit ein Klick an- oder abwaehlt, ohne Strg.
$auswahlPersonen = Person::alle();

// Gewaehlte zuerst, damit sie im scrollbaren Kasten sofort sichtbar sind (usort ist stabil).
usort(
    $auswahlPersonen,
    fn (array $a, array $b) => in_array((int) $b['id'], $personIds, true) <=> in_array((int) $a['id'], $personIds, true)
);
?>
<fieldset class="personen-auswahl">
    <legend>Personen (optional)</legend>
    <?php if (empty($auswahlPersonen)): ?>
        <p class="hinweis-klein">Es wurden noch keine Personen angelegt.</p>
    <?php else: ?>
        <div class="personen-raster">
            <?php foreach ($auswahlPersonen as $p): ?>
                <label class="anlass-checkbox">
                    <input type="checkbox" name="person_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $personIds, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</fieldset>
