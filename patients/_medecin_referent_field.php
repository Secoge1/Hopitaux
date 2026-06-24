<?php
/**
 * Champ médecin référent (inclus par ajouter.php / modifier.php).
 * Variables attendues : $medecins (array), $selectedMedecinId (int|null), $canAssignMedecin (bool)
 */
if (empty($canAssignMedecin)) {
    return;
}
$selectedMedecinId = isset($selectedMedecinId) ? (int) $selectedMedecinId : 0;
?>
<div class="col-12">
    <h6 class="text-primary mb-3"><i class="fas fa-user-md me-2"></i>Médecin référent</h6>
</div>
<div class="col-md-8">
    <label for="medecin_referent_id" class="form-label">Médecin dédié</label>
    <select class="form-select" id="medecin_referent_id" name="medecin_referent_id">
        <option value="">— Non assigné —</option>
        <?php foreach ($medecins as $m): ?>
        <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === $selectedMedecinId ? 'selected' : '' ?>>
            <?= htmlspecialchars(medecin_profil_format_name($m)) ?>
            <?= !empty($m['specialite']) ? ' — ' . htmlspecialchars($m['specialite']) : '' ?>
        </option>
        <?php endforeach; ?>
    </select>
    <div class="form-text">Le patient sera suivi par ce médecin (accueil, consultations, tickets).</div>
</div>
