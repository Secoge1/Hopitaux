<?php
/**
 * Page tarifs — trois formules Efficasante SaaS.
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';

public_init();

public_head('Tarifs — ' . platform_name(), 'pub-tarifs', [], [
    'description' => 'Tarifs logiciel médical : Essentiel, Pro ou licence à vie. Paiement Mobile Money Orange/Wave. Activation sous 24h.',
    'keywords' => 'prix logiciel médical, tarifs gestion clinique, Mobile Money paiement, Orange Money Wave, licence médicale Mali',
]);
public_nav('tarifs');
public_hero('Choisissez votre licence', 'Trois formules pour équiper votre établissement de santé');
?>

<section class="pub-main">
    <div class="container">
        <?php public_plan_cards(); ?>

        <div class="mt-5 pt-2">
            <?php public_how_it_works(); ?>
        </div>
    </div>
</section>

<?php
public_footer();
public_scripts();
