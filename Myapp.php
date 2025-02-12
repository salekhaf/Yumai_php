<?php
session_start();
require 'config.php'; // Contiendra la connexion à la base de données

// Connexion à la base de données
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Page d'accueil
template('accueil.php');

// Inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscription'])) {
    $username = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirmPassword'];

    if ($password !== $confirm_password) {
        die("Les mots de passe ne correspondent pas.");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (username, email, password) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$username, $email, $hashed_password]);
        $_SESSION['user'] = $email;
        header('Location: questionnaire.php');
    } catch (PDOException $e) {
        die("Erreur lors de l'inscription : " . $e->getMessage());
    }
}

// Connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connexion'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $email;
        header('Location: questionnaire.php');
    } else {
        die("Identifiants incorrects.");
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
}

// Questionnaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['questionnaire'])) {
    if (!isset($_SESSION['user'])) {
        header('Location: connexion.php');
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO questionnaire (user_id, cooking_level, prep_time, cooking_type, number_of_people) VALUES ((SELECT id FROM utilisateurs WHERE email = ?), ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user'], $_POST['cookingLevel'], $_POST['prepTime'], $_POST['cookingType'], $_POST['numberOfPeople']]);
    
    header('Location: ingredients.php');
}

// Générer recettes avec Spoonacular
if (isset($_GET['ingredients'])) {
    $ingredients = urlencode($_GET['ingredients']);
    $apiKey = getenv('SPOONACULAR_API_KEY');
    $url = "https://api.spoonacular.com/recipes/findByIngredients?apiKey=$apiKey&ingredients=$ingredients&number=5&ranking=1";
    
    $response = file_get_contents($url);
    $recettes = json_decode($response, true);
    
    require 'recettes.php';
}

function template($file, $data = []) {
    extract($data);
    include "templates/$file";
}
?>