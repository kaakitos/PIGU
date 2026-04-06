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

function showAlert(msg, isError = true) {
    const el = document.getElementById('globalAlert');
    el.textContent = msg;
    el.className = isError ? 'alert-pigu error' : 'alert-pigu success';
    // Auto-hide après 3 secondes
    setTimeout(() => {
        el.style.display = 'none';
    }, 3000);
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

document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const password = document.getElementById('password').value;
    const confirmPwd = document.getElementById('confirmPwd').value;

    if (password !== confirmPwd) {
        document.getElementById('pwdError').style.display = 'block';
        return;
    } else {
        document.getElementById('pwdError').style.display = 'none';
    }

    if (password.length < 6) {
        showAlert('Le mot de passe doit contenir au moins 6 caractères.', true);
        return;
    }

    hideAlert();
    setLoading(true);

    const payload = {
        action: 'register',
        role: 'AMBULANCIER',
        nom: document.getElementById('nom').value.trim(),
        prenom: document.getElementById('prenom').value.trim(),
        login: document.getElementById('login').value.trim(),
        telephone: document.getElementById('telephone').value.trim(),
        mot_de_passe: password
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
            // Message de succès sans alert()
            showAlert('Inscription réussie ! Redirection vers la connexion...', false);
            setTimeout(() => {
                window.location.href = 'ambulancier_login.html';
            }, 2000);
        } else {
            showAlert(data.message || 'Une erreur est survenue.', true);
        }
    })
    .catch(error => {
        setLoading(false);
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur.', true);
    });
});