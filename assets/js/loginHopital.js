function togglePwd() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa-solid fa-eye';
    }
}

function showAlert(msg) {
    document.getElementById('alertMsg').textContent = msg;
    document.getElementById('loginAlert').style.display = 'block';
}

function hideAlert() {
    document.getElementById('loginAlert').style.display = 'none';
}

function setLoading(state) {
    const btn = document.getElementById('loginBtn');
    const spinner = document.getElementById('loginSpinner');
    const icon = document.getElementById('loginIcon');
    const text = document.getElementById('loginText');
    if (state) {
        spinner.style.display = 'block';
        icon.style.display = 'none';
        text.textContent = 'Connexion en cours...';
        btn.disabled = true;
    } else {
        spinner.style.display = 'none';
        icon.style.display = 'inline';
        text.textContent = 'Se connecter';
        btn.disabled = false;
    }
}

function handleLogin(e) {
    e.preventDefault();
    hideAlert();
    setLoading(true);

    const login = document.getElementById('login').value.trim();
    const password = document.getElementById('password').value;

    fetch('../api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'login',
            login: login, 
            password: password, 
            role: 'GESTIONNAIRE_HOPITAL'
        })
    })
    .then(r => r.json())
    .then(data => {
        setLoading(false);
        if (data.success) {
            sessionStorage.setItem('pigu_user', JSON.stringify(data.user));
            window.location.href = 'hopital_dashboard.html';
        } else {
            showAlert(data.message || 'Identifiants incorrects. Veuillez réessayer.');
        }
    })
    .catch(error => {
        setLoading(false);
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur. Vérifiez que XAMPP est démarré.');
    });
}