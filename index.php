<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Facture Proforma</title>
    
</head>
<style>
/* Reset et styles de base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background-color: #f5f7fa;
    color: #333;
    line-height: 1.6;
}

/* Conteneur de connexion */
.login-container {
    max-width: 400px;
    margin: 5% auto;
    padding: 2rem;
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.login-container h2 {
    margin-bottom: 1.5rem;
    color: #2c3e50;
}

/* Formulaire */
.form-group {
    margin-bottom: 1.5rem;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #34495e;
}

.form-group input {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
    transition: border 0.3s;
}

.form-group input:focus {
    border-color: #3498db;
    outline: none;
}

/* Bouton */
.btn {
    width: 100%;
    padding: 0.8rem;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #2980b9;
}

/* Lien d'inscription */
.login-container p {
    margin-top: 1rem;
    color: #7f8c8d;
}

.login-container a {
    color: #3498db;
    text-decoration: none;
    font-weight: 600;
}

.login-container a:hover {
    text-decoration: underline;
}

/* Messages d'erreur/succès */
#loginMessage, #registerMessage {
    margin-top: 1rem;
    padding: 0.8rem;
    border-radius: 5px;
    font-weight: 500;
}

.error {
    background-color: #ffebee;
    color: #e74c3c;
    border: 1px solid #e74c3c;
}

.success {
    background-color: #e8f5e9;
    color: #27ae60;
    border: 1px solid #27ae60;
}

/* Sidebar (pour le dashboard) */
.dashboard-sidebar {
    position: fixed;
    left: -250px;
    top: 0;
    width: 250px;
    height: 100%;
    background: #2c3e50;
    transition: left 0.3s;
    z-index: 1000;
}

.dashboard-sidebar.active {
    left: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .login-container {
        margin: 10% auto;
        width: 90%;
    }

    body.sidebar-active {
        overflow: hidden;
    }

    body.sidebar-active::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }
}
</style>
<body>
    <div class="login-container">
        <h2>Connexion</h2>
        <form id="loginForm">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Se connecter</button>
        </form>
        <p>Pas encore de compte? <a href="register.php">S'inscrire</a></p>
        <div id="loginMessage"></div>
    </div>
    <script >
        document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la connexion
    if (document.getElementById('loginForm')) {
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            fetch('auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('loginMessage');
                messageDiv.textContent = data.message;
                messageDiv.className = data.success ? 'success' : 'error';
                
                if (data.success) {
                    window.location.href = 'dashboard.php';
                }
            });
        });
    }
    
  
});

// Vérifie si le toggle existe déjà pour éviter les doublons
if (!window.sidebarScriptLoaded) {
    window.sidebarScriptLoaded = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.querySelector('.dashboard-sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                document.body.classList.toggle('sidebar-active');
            });
            
            // Fermeture en cliquant à l'extérieur
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    event.target !== menuToggle && 
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-active');
                }
            });
        }
    });
}
    </script>
</body>
</html>