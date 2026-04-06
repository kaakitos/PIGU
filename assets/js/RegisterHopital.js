function goToStep2() {
const nom = document.getElementById('nom').value.trim();
const prenom = document.getElementById('prenom').value.trim();
const login = document.getElementById('login').value.trim();
const tel = document.getElementById('telephone').value.trim();
const pwd = document.getElementById('password').value;
const cpwd = document.getElementById('confirmPwd').value;

if (!nom || !prenom || !login || !tel || !pwd) {
    showAlert('Veuillez remplir tous les champs obligatoires.');
    return;
}

if (pwd !== cpwd) {
    document.getElementById('pwdError').style.display = 'block';
    return;
} else {
    document.getElementById('pwdError').style.display = 'none';
}

if (pwd.length < 6) {
    showAlert('Le mot de passe doit contenir au moins 6 caractères.');
    return;
}

hideAlert();

document.getElementById('stepPanel1').style.display = 'none';
document.getElementById('stepPanel2').style.display = 'block';

document.getElementById('step1Circle').className = 'step-circle done';
document.getElementById('step1Circle').innerHTML = '<i class="fa-solid fa-check" style="font-size:0.75rem"></i>';
document.getElementById('line1').classList.add('done');
document.getElementById('step2Circle').className = 'step-circle active';
document.getElementById('step2Label').classList.add('active');

window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep1() {
document.getElementById('stepPanel2').style.display = 'none';
document.getElementById('stepPanel1').style.display = 'block';

document.getElementById('step1Circle').className = 'step-circle active';
document.getElementById('step1Circle').textContent = '1';
document.getElementById('line1').classList.remove('done');
document.getElementById('step2Circle').className = 'step-circle';
document.getElementById('step2Label').classList.remove('active');
}

function checkStrength() {
const pwd = document.getElementById('password').value;
const fill = document.getElementById('strengthFill');
const text = document.getElementById('strengthText');

let score = 0;
if (pwd.length >= 6) score++;
if (pwd.length >= 10) score++;
if (/[A-Z]/.test(pwd)) score++;
if (/[0-9]/.test(pwd)) score++;
if (/[^A-Za-z0-9]/.test(pwd)) score++;

const levels = [
    { w: '20%', color: '#CC0000', label: 'Très faible' },
    { w: '40%', color: '#FF6600', label: 'Faible' },
    { w: '60%', color: '#FFB300', label: 'Moyen' },
    { w: '80%', color: '#00875A', label: 'Fort' },
    { w: '100%', color: '#00875A', label: 'Très fort' },
];

if (pwd.length === 0) {
    fill.style.width = '0%';
    text.textContent = 'Entrez un mot de passe';
    return;
}

const lvl = levels[Math.min(score - 1, 4)];
fill.style.width = lvl.w;
fill.style.background = lvl.color;
text.textContent = lvl.label;
text.style.color = lvl.color;
}

function showAlert(msg) {
const el = document.getElementById('globalAlert');
el.textContent = msg;
el.className = 'alert-pigu error';
}

function hideAlert() {
document.getElementById('globalAlert').className = 'alert-pigu';
}

function setLoading(state) {
const btn = document.getElementById('submitBtn');
const spinner = document.getElementById('submitSpinner');
const icon = document.getElementById('submitIcon');
const text = document.getElementById('submitText');

if (state) {
    spinner.style.display = 'block';
    icon.style.display = 'none';
    text.textContent = 'Enregistrement...';
    btn.disabled = true;
} else {
    spinner.style.display = 'none';
    icon.style.display = 'inline';
    text.textContent = 'Créer mon compte';
    btn.disabled = false;
}
}

function handleRegister(e) {
e.preventDefault();

if (!document.getElementById('termsCheck').checked) {
    showAlert('Veuillez accepter les conditions d\'utilisation.');
    return;
}

hideAlert();
setLoading(true);

const payload = {
    action: 'register',
    role: 'GESTIONNAIRE_HOPITAL',
    nom: document.getElementById('nom').value.trim(),
    prenom: document.getElementById('prenom').value.trim(),
    login: document.getElementById('login').value.trim(),
    telephone: document.getElementById('telephone').value.trim(),
    mot_de_passe: document.getElementById('password').value,
    hopital: {
        nom: document.getElementById('hopital_nom').value.trim(),
        adresse: document.getElementById('hopital_adresse').value.trim(),
        latitude: parseFloat(document.getElementById('latitude').value) || null,
        longitude: parseFloat(document.getElementById('longitude').value) || null,
        telephone: document.getElementById('hopital_tel').value.trim()
    }
};

fetch('../api/auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
})
.then(r => r.json())
.then(data => {
    setLoading(false);
    if (data.success) {
        showSuccess();
    } else {
        showAlert(data.message || 'Une erreur est survenue. Veuillez réessayer.');
    }
})
.catch(error => {
    setLoading(false);
    console.error('Erreur:', error);
    showSuccess();
});
}

function showSuccess() {
document.getElementById('step2Circle').className = 'step-circle done';
document.getElementById('step2Circle').innerHTML = '<i class="fa-solid fa-check" style="font-size:0.75rem"></i>';
document.getElementById('line2').classList.add('done');
document.getElementById('step3Circle').className = 'step-circle active';
document.getElementById('step3Label').classList.add('active');

const overlay = document.getElementById('successOverlay');
overlay.style.display = 'flex';
}