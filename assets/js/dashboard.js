// ══════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════
let allPlaces = [];
let filteredPlaces = [];
let currentFilter = 'TOUS';
let currentSearch = '';
let deleteTargetId = null;
let currentPage = 1;
const PER_PAGE = 10;

// Récupérer l'utilisateur connecté
const user = JSON.parse(sessionStorage.getItem('pigu_user'));

// Si pas d'utilisateur connecté, rediriger vers login
if (!user) {
    window.location.href = 'hopital_login.html';
}

// ══════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    // Set user info
    document.getElementById('userName').textContent = user.prenom + ' ' + user.nom;
    document.getElementById('userAvatar').textContent =
        (user.prenom?.[0] || '') + (user.nom?.[0] || '');
    document.getElementById('sidebarHospitalName').textContent = 'Mon Hôpital';

    loadPlaces();
});

// ══════════════════════════════════════════════
// NAVIGATION SECTIONS
// ══════════════════════════════════════════════
function showSection(name) {
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    const titles = {
        dashboard: 'Tableau de bord',
        places: 'Gestion des places',
        hopital: 'Mon hôpital',
        missions: 'Missions reçues',
        profil: 'Mon profil'
    };
    document.getElementById('topbarTitle').textContent = titles[name] || name;
    document.getElementById('breadcrumbCurrent').textContent = name;
}

// ══════════════════════════════════════════════
// LOAD PLACES (API unique) - CHEMIN CORRIGÉ
// ══════════════════════════════════════════════
function loadPlaces() {
    // Chemin absolu depuis la racine du projet
    const apiUrl = '/pigu/api/places.php?hopital_id=' + user.hopital_id;
    console.log('Chargement des places depuis:', apiUrl);
    
    fetch(apiUrl)
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (Array.isArray(data)) {
            allPlaces = data;
            applyFilter();
            updateStats();
            updateDonut();
        } else {
            console.error('Erreur chargement places', data);
            showToast('Erreur de chargement des données', true);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur de connexion à la base de données: ' + error.message, true);
    });
}

// ══════════════════════════════════════════════
// FILTER & SEARCH
// ══════════════════════════════════════════════
function applyFilter() {
    filteredPlaces = allPlaces.filter(p => {
        const matchFilter = currentFilter === 'TOUS' || p.statut === currentFilter;
        const matchSearch = !currentSearch ||
            p.numero.toLowerCase().includes(currentSearch) ||
            (p.specialite || '').toLowerCase().includes(currentSearch);
        return matchFilter && matchSearch;
    });
    currentPage = 1;
    renderTable();
}

function filterPlaces(status, btn) {
    currentFilter = status;
    document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    applyFilter();
}

function searchPlaces(val) {
    currentSearch = val.toLowerCase();
    applyFilter();
}

// ══════════════════════════════════════════════
// RENDER TABLE
// ══════════════════════════════════════════════
function renderTable() {
    const tbody = document.getElementById('placesTableBody');
    const total = filteredPlaces.length;
    document.getElementById('tableCount').textContent = `${total} place(s)`;
    document.getElementById('navBadge').textContent = allPlaces.filter(p=>p.statut==='DISPONIBLE').length;

    if (total === 0) {
        tbody.innerHTML = `
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-bed"></i></div>
                    <div class="empty-title">Aucun résultat</div>
                    <div class="empty-desc">Aucune place ne correspond à votre recherche.</div>
                </div>
            </td></tr>
        `;
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    const start = (currentPage - 1) * PER_PAGE;
    const paginated = filteredPlaces.slice(start, start + PER_PAGE);

    tbody.innerHTML = paginated.map(p => `
        <tr>
            <td><span class="place-num">${p.numero}</span></td>
            <td><span class="type-badge ${p.type}">${p.type}</span></td>
            <td>${p.specialite || '<span style="color:#A0AEC0">—</span>'}</td>
            <td>
                <span class="status-badge ${p.statut}">
                    <span class="dot"></span>
                    ${labelStatut(p.statut)}
                </span>
            </td>
            <td style="color:var(--samu-muted);font-size:0.78rem">${formatDate(p.updated_at)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon toggle" onclick="cycleStatus(${p.id})" title="Changer statut">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                    <button class="btn-icon edit" onclick="openEditModal(${p.id})" title="Modifier">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn-icon delete" onclick="openDeleteModal(${p.id},'${p.numero}')" title="Supprimer">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    renderPagination(total);
}

function renderPagination(total) {
    const pages = Math.ceil(total / PER_PAGE);
    const pg = document.getElementById('pagination');
    if (pages <= 1) { pg.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= pages; i++) {
        html += `<button class="page-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
    }
    pg.innerHTML = html;
}

function goPage(n) { currentPage = n; renderTable(); }

// ══════════════════════════════════════════════
// STATS & DONUT
// ══════════════════════════════════════════════
function updateStats() {
    const total  = allPlaces.length;
    const dispo  = allPlaces.filter(p=>p.statut==='DISPONIBLE').length;
    const occupe = allPlaces.filter(p=>p.statut==='OCCUPE').length;
    const hors   = allPlaces.filter(p=>p.statut==='HORS_SERVICE').length;

    document.getElementById('statTotal').textContent  = total;
    document.getElementById('statDispo').textContent  = dispo;
    document.getElementById('statOccupe').textContent = occupe;
    document.getElementById('statHors').textContent   = hors;

    const pctDispo  = total ? Math.round(dispo/total*100) : 0;
    const pctOccupe = total ? Math.round(occupe/total*100) : 0;

    document.getElementById('dispoPct').textContent  = pctDispo + '%';
    document.getElementById('occupePct').textContent = pctOccupe + '%';
    document.getElementById('barDispo').style.width  = pctDispo + '%';
    document.getElementById('barOccupe').style.width = pctOccupe + '%';
    document.getElementById('barHors').style.width   = total ? Math.round(hors/total*100)+'%' : '0%';
}

function updateDonut() {
    const total  = allPlaces.length;
    const dispo  = allPlaces.filter(p=>p.statut==='DISPONIBLE').length;
    const occupe = allPlaces.filter(p=>p.statut==='OCCUPE').length;
    const hors   = allPlaces.filter(p=>p.statut==='HORS_SERVICE').length;
    const C = 339.3;

    document.getElementById('lgDispo').textContent  = dispo;
    document.getElementById('lgOccupe').textContent = occupe;
    document.getElementById('lgHors').textContent   = hors;
    document.getElementById('donutPct').textContent = total ? Math.round(dispo/total*100)+'%' : '0%';

    if (total === 0) return;
    const dOff = C - (dispo/total)*C;
    const oOff = C - (occupe/total)*C;
    const hOff = C - (hors/total)*C;

    setTimeout(() => {
        const donutGreen = document.getElementById('donutGreen');
        const donutRed = document.getElementById('donutRed');
        const donutOrange = document.getElementById('donutOrange');
        if (donutGreen) donutGreen.style.strokeDashoffset = dOff;
        if (donutRed) donutRed.style.strokeDashoffset = oOff;
        if (donutOrange) donutOrange.style.strokeDashoffset = hOff;
    }, 200);
}

// ══════════════════════════════════════════════
// CRUD MODALS
// ══════════════════════════════════════════════
function openAddModal() {
    document.getElementById('editId').value = '';
    document.getElementById('mNumero').value = '';
    document.getElementById('mType').value = 'LIT';
    document.getElementById('mSpecialite').value = '';
    document.getElementById('mStatut').value = 'DISPONIBLE';
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-plus me-2"></i>Ajouter une place';
    document.getElementById('saveText').textContent = 'Enregistrer';
    document.getElementById('placeModal').classList.add('show');
}

function openEditModal(id) {
    const p = allPlaces.find(x => x.id === id);
    if (!p) return;
    document.getElementById('editId').value = id;
    document.getElementById('mNumero').value = p.numero;
    document.getElementById('mType').value = p.type;
    document.getElementById('mSpecialite').value = p.specialite || '';
    document.getElementById('mStatut').value = p.statut;
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen me-2"></i>Modifier la place';
    document.getElementById('saveText').textContent = 'Mettre à jour';
    document.getElementById('placeModal').classList.add('show');
}

function openDeleteModal(id, numero) {
    deleteTargetId = id;
    document.getElementById('deleteNum').textContent = numero;
    document.getElementById('deleteModal').classList.add('show');
}

function closeModal() { document.getElementById('placeModal').classList.remove('show'); }
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('show'); }

function savePlace() {
    const id = document.getElementById('editId').value;
    const numero = document.getElementById('mNumero').value.trim();
    const type = document.getElementById('mType').value;
    const specialite = document.getElementById('mSpecialite').value;
    const statut = document.getElementById('mStatut').value;

    if (!numero) { showToast('Le numéro de place est obligatoire.', true); return; }

    const payload = { hopital_id: user.hopital_id, numero, type, specialite, statut };
    let method = 'POST';
    
    if (id) { 
        method = 'PUT'; 
        payload.id = parseInt(id); 
    }

    fetch('/pigu/api/places.php', {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            loadPlaces();
            showToast(id ? 'Place mise à jour avec succès.' : 'Place ajoutée avec succès.');
        } else {
            showToast(data.message || 'Erreur lors de l\'opération', true);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur de connexion au serveur', true);
    });
}

function confirmDelete() {
    fetch('/pigu/api/places.php?id=' + deleteTargetId, { 
        method: 'DELETE', 
        headers: { 'Content-Type': 'application/json' } 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            loadPlaces();
            showToast('Place supprimée avec succès.');
        } else {
            showToast(data.message || 'Erreur lors de la suppression', true);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur de connexion au serveur', true);
    });
}

function cycleStatus(id) {
    const p = allPlaces.find(x => x.id === id);
    if (!p) return;
    const cycle = { DISPONIBLE:'OCCUPE', OCCUPE:'HORS_SERVICE', HORS_SERVICE:'DISPONIBLE' };
    const newStatut = cycle[p.statut];
    
    fetch('/pigu/api/places.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, statut: newStatut })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPlaces();
            showToast(`Statut changé → ${labelStatut(newStatut)}`);
        } else {
            showToast('Erreur lors du changement de statut', true);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur de connexion au serveur', true);
    });
}

// ══════════════════════════════════════════════
// QUICK ACTIONS
// ══════════════════════════════════════════════
function markAllAvailable() {
    if (!confirm('Marquer TOUTES les places comme disponibles ?')) return;
    
    const updates = allPlaces.map(place => {
        return fetch('/pigu/api/places.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: place.id, statut: 'DISPONIBLE' })
        });
    });
    
    Promise.all(updates)
        .then(() => loadPlaces())
        .catch(() => showToast('Erreur lors de la mise à jour', true));
    showToast('Mise à jour en cours...');
}

function exportData() {
    const headers = ['N° Place', 'Type', 'Spécialité', 'Statut', 'Dernière MAJ'];
    const rows = allPlaces.map(p => [p.numero, p.type, p.specialite || '', labelStatut(p.statut), formatDate(p.updated_at)]);
    const csv = [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', `places_hopital_${Date.now()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showToast('Export CSV téléchargé');
}

// ══════════════════════════════════════════════
// TOAST & HELPERS
// ══════════════════════════════════════════════
function showToast(msg, isError = false) {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = 'toast-msg' + (isError ? ' error' : '');
    t.innerHTML = `<i class="fa-solid ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(100%)'; setTimeout(() => t.remove(), 300); }, 3000);
}

function labelStatut(s) {
    return { DISPONIBLE:'Disponible', OCCUPE:'Occupé', HORS_SERVICE:'Hors service' }[s] || s;
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('fr-FR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
}

function logout() {
    fetch('/pigu/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' })
    })
    .then(() => {
        sessionStorage.clear();
        window.location.href = 'hopital_login.html';
    })
    .catch(() => {
        sessionStorage.clear();
        window.location.href = 'hopital_login.html';
    });
}

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('show'); });
});