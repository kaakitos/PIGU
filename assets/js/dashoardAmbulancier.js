
let currentUser = null;
let currentMissionId = null;
let missions = [];

document.addEventListener('DOMContentLoaded', () => {
    const userData = sessionStorage.getItem('pigu_user');
    if (!userData) {
        window.location.href = 'ambulancier_login.html';
        return;
    }
    currentUser = JSON.parse(userData);
    document.getElementById('userName').textContent = `${currentUser.prenom} ${currentUser.nom}`;
    document.getElementById('userAvatar').textContent = (currentUser.prenom?.[0] || '') + (currentUser.nom?.[0] || '');
    document.getElementById('sidebarName').textContent = `${currentUser.prenom} ${currentUser.nom}`;
    
    loadAmbulance();
    loadMissions();
});

function showSection(section) {
    document.getElementById('section-dashboard').style.display = 'none';
    document.getElementById('section-missions').style.display = 'none';
    document.getElementById('section-ambulance').style.display = 'none';
    
    if (section === 'dashboard') {
        document.getElementById('section-dashboard').style.display = 'block';
        document.getElementById('topbarTitle').textContent = 'Tableau de bord';
        document.getElementById('breadcrumbCurrent').textContent = 'Dashboard';
    }
    if (section === 'missions') {
        document.getElementById('section-missions').style.display = 'block';
        document.getElementById('topbarTitle').textContent = 'Mes missions';
        document.getElementById('breadcrumbCurrent').textContent = 'Missions';
        loadMissions();
    }
    if (section === 'ambulance') {
        document.getElementById('section-ambulance').style.display = 'block';
        document.getElementById('topbarTitle').textContent = 'Mon ambulance';
        document.getElementById('breadcrumbCurrent').textContent = 'Ambulance';
        loadAmbulance();
    }
    
    document.querySelectorAll('.nav-item').forEach(a => a.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

function loadAmbulance() {
    fetch(`../api/ambulance.php?utilisateur_id=${currentUser.id}`)
    .then(r => r.json())
    .then(data => {
        if (data && data.id) {
            currentUser.ambulance_id = data.id;
            currentUser.immatriculation = data.immatriculation;
            document.getElementById('ambulanceImmat').innerHTML = `Immatriculation: ${data.immatriculation}`;
            document.getElementById('statAmbulance').innerHTML = data.statut === 'DISPONIBLE' ? 'Disponible' : (data.statut === 'EN_MISSION' ? 'En mission' : 'Hors service');
            document.getElementById('ambulanceDetails').innerHTML = `
                <p><strong>Immatriculation:</strong> ${data.immatriculation}</p>
                <p><strong>Statut:</strong> <span class="badge ${data.statut === 'DISPONIBLE' ? 'bg-success' : (data.statut === 'EN_MISSION' ? 'bg-warning' : 'bg-secondary')}">${data.statut}</span></p>
                <p><strong>Dernière mise à jour:</strong> ${new Date(data.updated_at).toLocaleString()}</p>
            `;
        } else {
            document.getElementById('ambulanceImmat').innerHTML = 'Immatriculation: Non définie';
            document.getElementById('statAmbulance').innerHTML = 'Non créée';
            document.getElementById('ambulanceDetails').innerHTML = '<p class="text-muted">Vous n\'avez pas encore enregistré votre ambulance.</p>';
        }
    });
}

function openAmbulanceModal() {
    document.getElementById('ambulanceImmatInput').value = currentUser.immatriculation || '';
    new bootstrap.Modal(document.getElementById('ambulanceModal')).show();
}

function saveAmbulance() {
    const immatriculation = document.getElementById('ambulanceImmatInput').value.trim();
    if (!immatriculation) {
        showToast('Veuillez saisir une immatriculation', true);
        return;
    }
    
    fetch('../api/ambulance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            utilisateur_id: currentUser.id,
            immatriculation: immatriculation
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('ambulanceModal')).hide();
            loadAmbulance();
            showToast('Ambulance créée avec succès');
        } else {
            showToast(data.message, true);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur lors de la création', true);
    });
}

function loadMissions() {
    if (!currentUser.ambulance_id) {
        // Message plus élégant
        const emptyMessage = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
                <div class="empty-title">Aucune ambulance enregistrée</div>
                <div class="empty-desc">Pour commencer à recevoir des missions, vous devez d'abord créer votre ambulance.</div>
                <button class="btn-pigu-primary" onclick="openAmbulanceModal()">
                    <i class="fa-solid fa-plus"></i> Créer mon ambulance
                </button>
            </div>
        `;
        document.getElementById('recentMissions').innerHTML = emptyMessage;
        document.getElementById('allMissions').innerHTML = emptyMessage;
        document.getElementById('navBadge').textContent = '0';
        return;
    }
    
    fetch(`../api/mission.php?ambulance_id=${currentUser.ambulance_id}`)
    .then(r => r.json())
    .then(data => {
        missions = data;
        updateStats();
        renderRecentMissions();
        renderAllMissions();
        document.getElementById('navBadge').textContent = missions.filter(m => m.statut !== 'TERMINEE').length;
    });
}

function updateStats() {
    const total = missions.length;
    const enCours = missions.filter(m => m.statut === 'EN_COURS').length;
    const terminees = missions.filter(m => m.statut === 'TERMINEE').length;
    
    document.getElementById('statTotal').textContent = total;
    document.getElementById('statEnCours').textContent = enCours;
    document.getElementById('statTerminees').textContent = terminees;
}

function getPrioriteClass(priorite) {
    if (priorite === 'TRES_URGENT') return 'tres-urgent';
    if (priorite === 'URGENT') return 'urgent';
    return 'non-urgent';
}

function getPrioriteIcon(priorite) {
    if (priorite === 'TRES_URGENT') return '<i class="fa-solid fa-triangle-exclamation"></i>';
    if (priorite === 'URGENT') return '<i class="fa-solid fa-exclamation"></i>';
    return '<i class="fa-solid fa-info-circle"></i>';
}

function renderRecentMissions() {
    const recent = missions.slice(0, 5);
    const container = document.getElementById('recentMissions');
    
    if (recent.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-tasks"></i>
                </div>
                <div class="empty-title">Aucune mission</div>
                <div class="empty-desc">Vous n'avez pas encore de missions assignées.</div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = recent.map(m => `
        <div class="mission-card" style="border-left-color: ${getStatusColor(m.statut)}">
            <div class="d-flex justify-content-between align-items-start">
                <div style="flex: 1;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="mission-priorite ${getPrioriteClass(m.priorite)}">
                            ${getPrioriteIcon(m.priorite)} ${m.priorite}
                        </span>
                        <span class="status-badge status-${m.statut}">${getStatusLabel(m.statut)}</span>
                    </div>
                    <h6 class="mb-1">${m.description || 'Mission d\'urgence'}</h6>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fa-solid fa-hospital"></i> ${m.hopital_nom}
                        </small><br>
                        <small class="text-muted">
                            <i class="fa-solid fa-location-dot"></i> ${m.adresse_texte || 'Adresse non spécifiée'}
                        </small><br>
                        <small class="text-muted">
                            <i class="fa-regular fa-calendar"></i> ${new Date(m.date_debut).toLocaleString()}
                        </small>
                    </div>
                </div>
                <div class="text-end ms-3">
                    ${m.statut !== 'TERMINEE' ? `
                        <button class="btn-pigu-outline" onclick="openStatusModal(${m.id}, '${m.description.replace(/'/g, "\\'")}')">
                            <i class="fa-solid fa-arrows-rotate"></i> Changer statut
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

function renderAllMissions() {
    const container = document.getElementById('allMissions');
    
    if (missions.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-tasks"></i>
                </div>
                <div class="empty-title">Aucune mission</div>
                <div class="empty-desc">Vous n'avez pas encore de missions assignées.</div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = missions.map(m => `
        <div class="mission-card" style="border-left-color: ${getStatusColor(m.statut)}">
            <div class="d-flex justify-content-between align-items-start">
                <div style="flex: 1;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="mission-priorite ${getPrioriteClass(m.priorite)}">
                            ${getPrioriteIcon(m.priorite)} ${m.priorite}
                        </span>
                        <span class="status-badge status-${m.statut}">${getStatusLabel(m.statut)}</span>
                    </div>
                    <h6 class="mb-1">${m.description || 'Mission d\'urgence'}</h6>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fa-solid fa-hospital"></i> ${m.hopital_nom}
                        </small><br>
                        <small class="text-muted">
                            <i class="fa-solid fa-location-dot"></i> ${m.adresse_texte || 'Adresse non spécifiée'}
                        </small><br>
                        <small class="text-muted">
                            <i class="fa-regular fa-calendar"></i> ${new Date(m.date_debut).toLocaleString()}
                        </small>
                        ${m.date_fin ? `<small class="text-muted d-block mt-1"><i class="fa-regular fa-clock"></i> Terminée le: ${new Date(m.date_fin).toLocaleString()}</small>` : ''}
                    </div>
                </div>
                <div class="text-end ms-3">
                    ${m.statut !== 'TERMINEE' ? `
                        <button class="btn-pigu-outline" onclick="openStatusModal(${m.id}, '${m.description.replace(/'/g, "\\'")}')">
                            <i class="fa-solid fa-arrows-rotate"></i> Changer statut
                        </button>
                    ` : ''}
                </div>
            </div>
        </div>
    `).join('');
}

function openStatusModal(missionId, description) {
    currentMissionId = missionId;
    document.getElementById('missionDesc').textContent = description || 'Mission d\'urgence';
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function updateMissionStatus() {
    const newStatut = document.getElementById('newStatut').value;
    
    fetch('../api/mission.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentMissionId, statut: newStatut })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            loadMissions();
            loadAmbulance();
            showToast('Statut mis à jour avec succès');
        } else {
            showToast(data.message, true);
        }
    });
}

function getStatusColor(statut) {
    const colors = { 'EN_ATTENTE': '#F59E0B', 'EN_COURS': '#3B82F6', 'TERMINEE': '#10B981' };
    return colors[statut] || '#6B7280';
}

function getStatusLabel(statut) {
    const labels = { 'EN_ATTENTE': 'En attente', 'EN_COURS': 'En cours', 'TERMINEE': 'Terminée' };
    return labels[statut] || statut;
}

function showToast(message, isError = false) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-msg ${isError ? 'error' : 'success'}`;
    toast.innerHTML = `<i class="fa-solid ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function logout() {
    sessionStorage.clear();
    window.location.href = 'ambulancier_login.html';
}
