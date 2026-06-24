<?php
/** Champs formulaire budget — inclus par budgets.php */
$formPrefix = $formPrefix ?? 'create';
$p = $formPrefix === 'edit' ? 'edit_' : '';
$anneeVal = $formPrefix === 'edit' ? '' : (int) ($annee ?? date('Y'));
?>
<div class="mb-3">
    <label for="<?= $p ?>annee" class="form-label">Année *</label>
    <input type="number" class="form-control" id="<?= $p ?>annee" name="annee"
           value="<?= $formPrefix === 'edit' ? '' : $anneeVal ?>" required min="2020" max="2100">
</div>
<div class="mb-3">
    <label for="<?= $p ?>departement" class="form-label">Département</label>
    <input type="text" class="form-control" id="<?= $p ?>departement" name="departement"
           placeholder="Ex. Urgences, Consultation…">
</div>
<div class="mb-3">
    <label for="<?= $p ?>categorie" class="form-label">Catégorie *</label>
    <select class="form-select" id="<?= $p ?>categorie" name="categorie" required>
        <option value="">Choisir…</option>
        <?php foreach ($categories as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-3">
    <label for="<?= $p ?>montant_alloue" class="form-label">Montant alloué (FCFA) *</label>
    <input type="number" class="form-control" id="<?= $p ?>montant_alloue" name="montant_alloue" step="0.01" required min="0">
</div>
<div class="mb-3">
    <label for="<?= $p ?>statut" class="form-label">Statut</label>
    <select class="form-select" id="<?= $p ?>statut" name="statut">
        <?php foreach ($statuts as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-0">
    <label for="<?= $p ?>notes" class="form-label">Notes</label>
    <textarea class="form-control" id="<?= $p ?>notes" name="notes" rows="3" placeholder="Notes sur le budget…"></textarea>
</div>
