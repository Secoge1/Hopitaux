<?php
/**
 * Page tarifs — trois formules Efficasante SaaS.
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';

public_init();

public_head('Tarifs — ' . platform_name(), 'pub-tarifs');
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
