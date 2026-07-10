<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Connexion à la BDD
    $dsn = "pgsql:host=127.0.0.1;port=5433;dbname=cv_analysis";
    $pdo = new PDO($dsn, 'cv', 'cv');

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Requête : on récupère tout l'utilisateur
    $stmt = $pdo->prepare("SELECT id, mot_de_passe FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérification SANS HASH
    if ($user && ($password === $user['mot_de_passe'])) {
        $_SESSION['utilisateur_id'] = $user['id'];
        header('Location: /'); // Redirection vers l'accueil
        exit;
    } else {
        $error = "Identifiants incorrects.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<body>
    <h2>Accès Utilisateur</h2>
    <?php if($error) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Connexion</button>
    </form>
</body>
</html>