<?php
// employee/overview.php
// Ce fichier contient le contenu principal du tableau de bord de l'employé.

// Assurez-vous que les variables nécessaires ($pdo, $currentUser, BASE_URL, etc.) sont disponibles
// via l'inclusion de dashboard.php ou d'une autre méthode.
// Elles le seront si ce fichier est inclus depuis dashboard.php
?>

<h2 class="mb-4">
    Tableau de Bord Personnel
    </h2>

<div class="row">
    <div class="col-12 col-md-12 col-lg-8 mb-4"> <div class="dashboard-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Calendrier des Événements</h5>
            </div>
            <div class="card-body p-0">
                <div id='calendar'></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-12 col-lg-4 mb-4"> <div class="dashboard-card clock-digital-widget d-flex flex-column justify-content-center align-items-center">
            <div class="card-header w-100 text-center">
                <h5 class="card-title mb-0">Horloge Numérique</h5>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center flex-grow-1 w-100">
                <div class="digital-time" id="digitalClockTime">00:00:00</div>
                <div class="digital-date" id="digitalClockDate">Date</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Actualités du Monde</h5>
                <a href="https://news.google.com/" target="_blank" class="btn btn-sm btn-outline-secondary">Voir plus</a>
            </div>
            <div class="card-body">
                <div id="newsContainer" class="row">
                    <div class="col-12 text-center text-muted py-5" id="loadingNews">Chargement des actualités...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Dashboard specific card styles */
    .dashboard-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        padding: 20px;
        margin-bottom: 20px;
        border: none;
        height: 100%; /* Make cards fill height of column */
    }
    .dashboard-card .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: #343a40;
    }
    .dashboard-card .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .dashboard-card .card-header .btn-sm {
        padding: 5px 10px;
        font-size: 0.8rem;
    }

    /* Calendar styles */
    #calendar {
        max-width: none; /* Allow calendar to take full width of its container */
        margin: 0 auto;
        font-size: 0.9em; /* Adjust font size within calendar */
    }
    .fc .fc-toolbar-title {
        font-size: 1.5em; /* Make title bigger */
    }
    .fc .fc-button-primary {
        background-color: #007bff;
        border-color: #007bff;
    }
    .fc .fc-button-primary:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }

    /* Styles pour la nouvelle horloge numérique */
    .clock-digital-widget .card-header {
        margin-bottom: 0; /* Remove bottom margin from header */
        padding-bottom: 10px; /* Adjust padding */
        border-bottom: 1px solid #eee; /* Keep border */
    }

    .clock-digital-widget .card-body {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 150px; /* Ensure enough height for content */
    }

    .digital-time {
        font-size: 3.5rem; /* Large font for time */
        font-weight: 700;
        color: #343a40;
        margin-bottom: 5px;
        line-height: 1; /* Adjust line height */
    }

    .digital-date {
        font-size: 1.2rem;
        color: #6c757d;
        text-align: center;
    }

    /* News card specific styles */
    .news-card .card-img-top {
        height: 180px; /* Fixed height for consistent image size */
        object-fit: cover; /* Ensures image covers area without distortion */
    }
    .news-card .card-body {
        display: flex;
        flex-direction: column;
        justify-content: space-between; /* Push button to bottom */
    }
    .news-card .card-title {
        font-size: 1rem;
        font-weight: 600;
        line-height: 1.3;
        margin-bottom: 10px;
    }
    .news-card .card-text {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    .news-card .card-text small {
        display: block; /* Make date appear on its own line */
        margin-bottom: 5px;
    }

    /* Responsive adjustments */
    @media (max-width: 576px) { /* For very small screens */
        .dashboard-card {
            padding: 15px;
        }
        .dashboard-card .card-title {
            font-size: 1rem;
        }
        .digital-time {
            font-size: 2.5rem;
        }
        .digital-date {
            font-size: 1rem;
        }
        .fc .fc-toolbar-title {
            font-size: 1.2em;
        }
        .news-card .card-title {
            font-size: 0.9rem;
        }
        .news-card .card-text {
            font-size: 0.8rem;
        }
    }

    /* Styles pour les notifications non lues */
    .notification-item.unread {
        background-color: #f8f9fa; /* Un léger fond pour les notifications non lues */
        font-weight: bold;
    }
    .notification-item.unread:hover {
        background-color: #e9ecef;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Logic for Digital Clock Widget ---
        function updateDigitalClock() {
            const now = new Date();
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

            document.getElementById('digitalClockTime').textContent = now.toLocaleTimeString('fr-FR', timeOptions);
            document.getElementById('digitalClockDate').textContent = now.toLocaleDateString('fr-FR', dateOptions);
        }

        // Update digital clock every second
        setInterval(updateDigitalClock, 1000);
        updateDigitalClock(); // Initial call to set time immediately

        // --- FullCalendar Initialization ---
        var calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                initialView: 'dayGridMonth',
                editable: true,
                dayMaxEvents: true,
                events: [
                    // Exemple d'événements (à remplacer par des données dynamiques via une API ou PHP)
                    {
                        title: 'Réunion d\'équipe',
                        start: '2025-06-29T10:00:00',
                        end: '2025-06-29T11:00:00',
                        color: '#007bff'
                    },
                    {
                        title: 'Formation RH',
                        start: '2025-07-03T14:00:00',
                        color: '#28a745'
                    },
                    {
                        title: 'Rendez-vous client',
                        start: '2025-07-07T09:30:00',
                        end: '2025-07-07T10:30:00',
                        color: '#ffc107'
                    }
                ],
                eventClick: function(info) {
                    // Gère le clic sur un événement existant
                    alert('Événement: ' + info.event.title + '\nDate: ' + info.event.start.toLocaleDateString('fr-FR'));
                },
                dateClick: function(info) {
                    // Gère le clic sur une date vide du calendrier
                    const clickedDate = info.dateStr; // Date au format YYYY-MM-DD

                    const eventTitle = prompt('Entrez le titre du rendez-vous pour le ' + new Date(clickedDate).toLocaleDateString('fr-FR') + ' :');
                    if (!eventTitle) {
                        return; // L'utilisateur a annulé ou n'a rien entré
                    }

                    const eventTime = prompt('Entrez l\'heure du rendez-vous (ex: 14:30) pour "' + eventTitle + '" :');
                    // Validation simple de l'heure (peut être améliorée)
                    const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)$/;
                    if (eventTime && !timeRegex.test(eventTime)) {
                        alert('Format d\'heure invalide. Veuillez utiliser HH:MM (ex: 14:30).');
                        return;
                    }

                    // Déterminer AM/PM ou UNKNOWN
                    let amPmOrUnknown = 'UNKNOWN';
                    if (eventTime) {
                        const [hours, minutes] = eventTime.split(':').map(Number);
                        if (hours >= 0 && hours < 12) {
                            amPmOrUnknown = 'AM';
                        } else if (hours >= 12 && hours < 24) {
                            amPmOrUnknown = 'PM';
                        }
                    }

                    // Simulation de l'appel à l'outil generic_calendar.create_calendar_event
                    // En environnement réel, ceci serait une requête AJAX vers un script PHP
                    // qui appellerait l'outil côté serveur.
                    console.log("Simulating tool call: generic_calendar.create_calendar_event(");
                    console.log(`    start_date: '${clickedDate}',`);
                    console.log(`    start_time_of_day: '${eventTime || ''}',`);
                    console.log(`    start_am_pm_or_unknown: '${amPmOrUnknown}',`);
                    console.log(`    title: '${eventTitle}'`);
                    console.log(")");

                    // Ici, vous intégreriez une requête AJAX (fetch ou XMLHttpRequest)
                    // vers un script PHP sur votre serveur (par exemple, `create_event.php`)
                    // qui, à son tour, appellerait l'API du calendrier.
                    // Pour l'instant, nous allons juste simuler l'ajout visuel.

                    // Exemple de requête AJAX (vous devrez créer un fichier PHP `employee/create_calendar_event.php`
                    // qui recevra ces données et appellera l'outil `Calendar`)
                    fetch('<?= BASE_URL ?>employee/create_calendar_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            start_date: clickedDate,
                            start_time_of_day: eventTime,
                            start_am_pm_or_unknown: amPmOrUnknown,
                            title: eventTitle
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Rendez-vous "' + eventTitle + '" ajouté avec succès !');
                            // Si l'ajout est réussi, rafraîchir le calendrier ou ajouter l'événement visuellement
                            calendar.addEvent({
                                title: eventTitle,
                                start: clickedDate + (eventTime ? 'T' + eventTime + ':00' : ''), // Combine date and time
                                allDay: !eventTime, // If no time, it's an all-day event
                                color: '#17a2b8' // Couleur pour les nouveaux événements
                            });
                        } else {
                            alert('Erreur lors de l\'ajout du rendez-vous : ' + (data.message || 'Erreur inconnue.'));
                            console.error('Erreur API calendrier:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur de réseau ou de serveur lors de l\'ajout du rendez-vous:', error);
                        alert('Impossible de communiquer avec le serveur pour ajouter le rendez-vous.');
                    });
                }
            });
            calendar.render();
        }

        // --- Fetch News Logic ---
        const newsContainer = document.getElementById('newsContainer');
        const loadingNews = document.getElementById('loadingNews');

        function fetchNews() {
            if (loadingNews) {
                loadingNews.textContent = 'Chargement des actualités...';
            }

            fetch('<?= BASE_URL ?>employee/fetch_news.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('API Error:', data.error);
                        newsContainer.innerHTML = '<div class="col-12 text-center text-danger py-5">Erreur lors du chargement des actualités : ' + data.error + '</div>';
                        return;
                    }
                    if (data.status === 'ok' && data.articles.length > 0) {
                        newsContainer.innerHTML = ''; // Clear loading message
                        data.articles.forEach(article => {
                            const publishedDate = new Date(article.publishedAt).toLocaleDateString('fr-FR', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric'
                            });
                            const imageUrl = article.urlToImage || 'https://via.placeholder.com/400x200?text=No+Image'; // Fallback image

                            const newsCard = `
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 news-card">
                                        <img src="${imageUrl}" class="card-img-top" alt="${article.title}">
                                        <div class="card-body">
                                            <h6 class="card-title">${article.title}</h6>
                                            <p class="card-text"><small class="text-muted">${publishedDate}</small></p>
                                            <p class="card-text">${article.description ? article.description.substring(0, 100) + '...' : 'Pas de description.'}</p>
                                            <a href="${article.url}" class="btn btn-primary btn-sm mt-auto" target="_blank">Lire plus</a>
                                        </div>
                                    </div>
                                </div>
                            `;
                            newsContainer.innerHTML += newsCard;
                        });
                    } else {
                        newsContainer.innerHTML = '<div class="col-12 text-center text-muted py-5">Aucune actualité disponible pour le moment.</div>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    newsContainer.innerHTML = '<div class="col-12 text-center text-danger py-5">Impossible de charger les actualités. Vérifiez votre connexion ou l\'API.</div>';
                });
        }

        // Fetch news when the page loads
        fetchNews();

        // --- Notification Bell Logic ---
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        // Note: notificationDropdown est le lien qui ouvre le dropdown, son ID est défini dans l'HTML de la cloche
        const notificationDropdownLink = document.getElementById('notificationDropdown');

        function fetchNotifications() {
            // Assurez-vous que BASE_URL est correctement défini en PHP (par exemple, dans config.php)
            // et que le chemin vers fetch_notifications.php est correct.
            fetch('<?= BASE_URL ?>includes/fetch_notifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Mettre à jour le badge de notification
                        if (data.unread_count > 0) {
                            notificationBadge.textContent = data.unread_count;
                            notificationBadge.style.display = 'block';
                        } else {
                            notificationBadge.style.display = 'none';
                        }

                        // Mettre à jour la liste déroulante des notifications
                        let notificationsHtml = '<li><h6 class="dropdown-header">Notifications</h6></li>';
                        if (data.notifications.length > 0) {
                            data.notifications.forEach(notif => {
                                let link = notif.link ? `href="${notif.link}"` : 'href="#"';
                                notificationsHtml += `
                                    <li>
                                        <a class="dropdown-item notification-item ${notif.is_read ? 'read' : 'unread'}" data-id="${notif.id}" ${link}>
                                            <div class="fw-bold">${notif.message}</div>
                                            <small class="text-muted">${new Date(notif.created_at).toLocaleString('fr-FR')}</small>
                                        </a>
                                    </li>
                                `;
                            });
                        } else {
                            notificationsHtml += '<li><a class="dropdown-item text-center text-muted" href="#">Aucune nouvelle notification</a></li>';
                        }
                        notificationsHtml += '<li><hr class="dropdown-divider"></li>';
                        notificationsHtml += '<li><a class="dropdown-item text-center" href="<?= BASE_URL ?>notifications.php">Voir toutes les notifications</a></li>';

                        notificationList.innerHTML = notificationsHtml;

                        // Attacher les écouteurs d'événements aux nouvelles notifications
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.addEventListener('click', function(event) {
                                // Empêcher la navigation immédiate si on veut marquer comme lu avant
                                // event.preventDefault(); // Décommentez si vous voulez empêcher la redirection par défaut

                                const notificationId = this.dataset.id;
                                markNotificationAsRead(notificationId);
                                
                                // Si le lien est défini, rediriger après le marquage
                                if (this.getAttribute('href') !== '#' && this.getAttribute('href') !== '') {
                                    window.location.href = this.getAttribute('href');
                                }
                            });
                        });

                    } else {
                        console.error('Erreur lors de la récupération des notifications :', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur réseau lors de la récupération des notifications :', error);
                    // Optionnel: afficher un message d'erreur dans l'UI des notifications
                    notificationList.innerHTML = '<li><h6 class="dropdown-header">Notifications</h6></li><li><a class="dropdown-item text-center text-danger" href="#">Erreur de chargement des notifications.</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center" href="<?= BASE_URL ?>notifications.php">Voir toutes les notifications</a></li>';
                });
        }

        // Fonction pour marquer une notification comme lue
        function markNotificationAsRead(notificationId) {
            fetch('<?= BASE_URL ?>includes/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Notification ' + notificationId + ' marquée comme lue.');
                    fetchNotifications(); // Rafraîchir les notifications après avoir marqué comme lu
                } else {
                    console.error('Échec de marquer la notification comme lue :', data.message);
                }
            })
            .catch(error => {
                console.error('Erreur réseau lors du marquage comme lu :', error);
            });
        }

        // Actualiser les notifications toutes les 30 secondes (ou plus souvent si nécessaire)
        setInterval(fetchNotifications, 30000);
        // Appeler la fonction une fois au chargement de la page
        fetchNotifications();

        // Ajout d'un écouteur pour rafraîchir les notifications quand le dropdown s'ouvre
        if (notificationDropdownLink) {
            notificationDropdownLink.addEventListener('click', function() {
                // Fetch les notifications chaque fois que l'utilisateur clique sur la cloche pour voir le menu déroulant
                fetchNotifications();
            });
        }
    });
</script>