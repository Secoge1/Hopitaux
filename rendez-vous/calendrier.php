<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('rdv'));

require_once __DIR__ . '/../models/RendezVous.php';

$rdvModel = new RendezVous();

// Récupérer les rendez-vous du mois
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$rdvs_month = $rdvModel->getByMonth($month, $year);

app_module_page_start([
    'active'    => 'rdv',
    'title'     => 'Calendrier des Rendez-vous',
    'subtitle'  => 'Vue calendrier avec récurrence et notifications',
    'icon'      => 'fa-calendar-alt',
    'extra_css' => ['https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css'],
]);
app_module_back_toolbar(app_url('rendez-vous/index.php'), 'Retour à la liste', [
    ['href' => app_url('rendez-vous/ajouter.php'), 'label' => 'Nouveau RDV', 'icon' => 'fa-plus', 'class' => 'btn-primary'],
]);
app_module_flash();
?>
<style>
    #calendar {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .fc-event { cursor: pointer; }
    .recurrence-badge {
        background: #17a2b8;
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.7rem;
        margin-left: 5px;
    }
</style>

        <div class="card app-mod-form-card">
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/locales/fr.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: function(fetchInfo, successCallback, failureCallback) {
                    fetch('api_rdv.php?start=' + fetchInfo.startStr + '&end=' + fetchInfo.endStr)
                        .then(response => response.json())
                        .then(data => {
                            var events = data.map(function(rdv) {
                                var color = '#3788d8';
                                if (rdv.statut === 'confirme') color = '#28a745';
                                else if (rdv.statut === 'annule') color = '#dc3545';
                                else if (rdv.statut === 'termine') color = '#6c757d';
                                
                                var title = rdv.patient_nom + ' ' + rdv.patient_prenom;
                                if (rdv.recurrence && rdv.recurrence !== 'aucune') {
                                    title += ' <span class="recurrence-badge">Répété</span>';
                                }
                                
                                return {
                                    id: rdv.id,
                                    title: title,
                                    start: rdv.date_rdv + 'T' + rdv.heure_rdv,
                                    color: color,
                                    extendedProps: {
                                        statut: rdv.statut,
                                        recurrence: rdv.recurrence,
                                        motif: rdv.motif
                                    }
                                };
                            });
                            successCallback(events);
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            failureCallback(error);
                        });
                },
                eventClick: function(info) {
                    var raw = info.event.id;
                    if (raw === undefined || raw === null || raw === '') {
                        return;
                    }
                    var id = String(raw).split('_')[0];
                    window.location.href = 'voir.php?id=' + encodeURIComponent(id);
                },
                dateClick: function(info) {
                    window.location.href = 'ajouter.php?date=' + info.dateStr;
                }
            });
    calendar.render();
});
</script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();

