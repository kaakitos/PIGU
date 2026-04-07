<?php
$host = 'localhost';
$dbname = 'pigu';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test 1 : connexion OK
    echo "✅ Connexion réussie à la base '$dbname'<br>";

    // Test 2 : lister les tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Tables trouvées : " . implode(', ', $tables) . "<br>";

    // Test 3 : vérifier la table utilisateur
    $count = $pdo->query("SELECT COUNT(*) FROM utilisateur")->fetchColumn();
    echo "👤 Nombre d'utilisateurs : $count<br>";

} catch(PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>