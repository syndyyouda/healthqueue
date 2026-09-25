<?php
// ============================================================
//  reset_passwords.php
//  Exécuter UNE SEULE FOIS puis supprimer le fichier !
//  URL : http://localhost/healthqueue/reset_passwords.php
// ============================================================
require_once 'config/db.php';
$db = getDB();

$comptes = [
    'SECRETAIRE' => 'sec123',
    'INFIRMIERE' => 'inf123',
    'MEDECIN'    => 'med123',
    'ADMIN'      => 'adm123',
];

echo "<h2>🔑 Réinitialisation des mots de passe</h2><ul>";
foreach ($comptes as $role => $mdp) {
    $hash = password_hash($mdp, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE role = ?");
    $stmt->execute([$hash, $role]);
    $nb = $stmt->rowCount();
    echo "<li>✅ <strong>$role</strong> → mot de passe : <code>$mdp</code> ($nb ligne mise à jour)</li>";
}
echo "</ul>";
echo "<p style='color:green;font-weight:bold;'>✅ Terminé ! Vous pouvez maintenant vous connecter.</p>";
echo "<p><a href='login.php'>→ Aller à la page de connexion</a></p>";
echo "<p style='color:red;'>⚠️ Supprimez ce fichier après utilisation !</p>";
?>
