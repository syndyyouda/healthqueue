<?php
session_start();
session_destroy();
session_start();

require_once 'config/db.php';

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = trim($_POST['role']          ?? '');
    $mdp  = trim($_POST['password']      ?? '');
    $nom  = trim($_POST['nom_affichage'] ?? '');

    if ($role && $mdp) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE role = ?");
        $stmt->execute([$role]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nom']   = $nom;
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_salle'] = $user['salle'];

            switch($user['role']) {
                case 'SECRETAIRE': header('Location: /healthqueue/secretaire/index.php'); exit;
                case 'INFIRMIERE': header('Location: /healthqueue/infirmiere/index.php'); exit;
                case 'MEDECIN':    header('Location: /healthqueue/medecin/index.php');    exit;
                case 'ADMIN':      header('Location: /healthqueue/admin/index.php');      exit;
                default:           header('Location: login.php'); exit;
            }
        } else {
            $erreur = 'Rôle ou mot de passe incorrect.';
        }
    } else {
        $erreur = 'Veuillez choisir un rôle et saisir le mot de passe.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealthQueue — Connexion</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --rouge:#dc2626; --rouge-bg:rgba(220,38,38,.15);
      --orange:#ea580c;
      --jaune:#ca8a04;
      --vert:#16a34a;
      --teal:#0d9488;
      --med:#7c3aed;
      --admin:#0f4c81; --admin2:#1a6bc4;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{
      font-family:'DM Sans',sans-serif;
      min-height:100vh;
      display:flex;align-items:center;justify-content:center;
      background:#0b1f3a;
      overflow:hidden;
    }

    /* Fond dégradé avec les 4 couleurs de priorité */
    body::before{
      content:'';position:fixed;inset:0;z-index:0;
      background:
        radial-gradient(ellipse 60% 50% at 10% 10%,  rgba(220,38,38,.2)   0%,transparent 60%),
        radial-gradient(ellipse 50% 60% at 90% 20%,  rgba(13,148,136,.2)  0%,transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 90%,  rgba(124,58,237,.2)  0%,transparent 60%),
        radial-gradient(ellipse 50% 50% at 15% 85%,  rgba(15,76,129,.35)  0%,transparent 60%);
    }
    body::after{
      content:'';position:fixed;inset:0;z-index:0;
      background-image:
        linear-gradient(rgba(255,255,255,.022) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.022) 1px,transparent 1px);
      background-size:40px 40px;
    }

    /* CARTE */
    .card{
      position:relative;z-index:10;width:440px;
      background:rgba(255,255,255,.08);
      backdrop-filter:blur(30px);
      border:1px solid rgba(255,255,255,.14);
      border-radius:24px;padding:44px 40px 36px;
      box-shadow:0 24px 64px rgba(0,0,0,.45),inset 0 1px 0 rgba(255,255,255,.15);
      animation:appear .55s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes appear{from{opacity:0;transform:translateY(28px) scale(.96)}to{opacity:1;transform:none}}

    /* HEADER */
    .header{text-align:center;margin-bottom:30px;}
    .logo{
      width:68px;height:68px;border-radius:50%;
      background:linear-gradient(135deg,var(--rouge),var(--admin2));
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 13px;font-size:28px;
      box-shadow:0 8px 28px rgba(220,38,38,.35);
    }
    .header h1{font-family:'Syne',sans-serif;font-size:1.75rem;font-weight:800;color:#fff;letter-spacing:-.5px;}
    .header p{color:rgba(255,255,255,.45);font-size:.83rem;margin-top:4px;}

    /* ALERTE */
    .alert-err{
      background:rgba(220,38,38,.15);border:1px solid rgba(220,38,38,.35);
      border-radius:10px;padding:10px 14px;color:#fca5a5;
      font-size:.84rem;margin-bottom:16px;
      display:flex;align-items:center;gap:8px;
      animation:shake .35s ease;
    }
    @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}

    /* CHAMPS */
    .field{margin-bottom:14px;}
    label{display:block;font-size:.7rem;font-weight:500;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;}
    .input-box{position:relative;}
    .input-box .ico{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:.95rem;opacity:.4;pointer-events:none;}
    input[type=text],input[type=password]{
      width:100%;background:rgba(255,255,255,.07);
      border:1.5px solid rgba(255,255,255,.12);
      border-radius:11px;padding:12px 14px 12px 38px;
      color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;
      outline:none;transition:all .2s;
    }
    input::placeholder{color:rgba(255,255,255,.28);}
    input:focus{border-color:rgba(26,107,196,.8);background:rgba(26,107,196,.1);box-shadow:0 0 0 3px rgba(26,107,196,.2);}

    /* GRILLE RÔLES — couleurs cohérentes avec chaque interface */
    .role-label{display:block;font-size:.7rem;font-weight:500;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;}
    .role-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;}
    .role-btn{
      background:rgba(255,255,255,.05);
      border:2px solid rgba(255,255,255,.1);
      border-radius:12px;padding:12px 8px;
      color:rgba(255,255,255,.55);cursor:pointer;
      text-align:center;transition:all .18s;
      font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:500;
    }
    .role-btn .ico{font-size:1.4rem;display:block;margin-bottom:4px;}
    .role-btn .sub{font-size:.68rem;opacity:.6;margin-top:2px;}
    .role-btn:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.22);color:#fff;}

    /* Couleur sélectionnée = couleur de l'interface correspondante */
    .sel-SECRETAIRE{background:rgba(15,76,129,.35)  !important;border-color:#93c5fd !important;color:#fff !important;}
    .sel-INFIRMIERE {background:rgba(13,148,136,.3)  !important;border-color:#5eead4 !important;color:#fff !important;}
    .sel-MEDECIN    {background:rgba(124,58,237,.3)  !important;border-color:#c4b5fd !important;color:#fff !important;}
    .sel-ADMIN      {background:rgba(15,76,129,.45)  !important;border-color:#bfdbfe !important;color:#fff !important;}

    /* Indicateur couleur sous le label du rôle */
    .role-btn .color-dot{
      width:8px;height:8px;border-radius:50%;
      display:inline-block;margin-right:4px;vertical-align:middle;
    }

    /* BOUTON CONNEXION */
    .btn{
      width:100%;margin-top:4px;
      background:linear-gradient(135deg,var(--admin2),var(--admin));
      color:#fff;font-family:'Syne',sans-serif;
      font-weight:700;font-size:1rem;
      padding:13px;border:none;border-radius:11px;
      cursor:pointer;transition:all .22s;
      box-shadow:0 4px 20px rgba(26,107,196,.4);
    }
    .btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(26,107,196,.55);}
    .btn:active{transform:translateY(0);}

    /* FOOTER */
    .footer{margin-top:22px;text-align:center;}
    .footer p{color:rgba(255,255,255,.28);font-size:.72rem;}

    /* Bande de couleurs priorités */
    .priority-strip{
      display:flex;gap:8px;justify-content:center;
      margin-top:12px;align-items:center;
    }
    .prio-dot{
      display:flex;align-items:center;gap:5px;
      font-size:.68rem;color:rgba(255,255,255,.35);
    }
    .prio-dot span{width:10px;height:10px;border-radius:50%;display:inline-block;}

    /* Séparateurs de rôle avec la couleur de l'interface */
    .role-btn.sel-SECRETAIRE .role-indicator{background:var(--admin2);}
    .role-btn.sel-INFIRMIERE  .role-indicator{background:var(--teal);}
    .role-btn.sel-MEDECIN     .role-indicator{background:var(--med);}
    .role-btn.sel-ADMIN       .role-indicator{background:var(--admin);}

    .role-indicator{
      height:3px;border-radius:99px;background:transparent;
      margin-top:8px;transition:background .2s;
    }
  </style>
</head>
<body>
<div class="card">

  <!-- HEADER -->
  <div class="header">
    <div class="logo">🏥</div>
    <h1>HealthQueue</h1>
    <p>Gestion intelligente de file d'attente médicale</p>
  </div>

  <?php if($erreur): ?>
    <div class="alert-err">⚠️ <?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">

    <!-- Nom -->
    <div class="field">
      <label>Votre nom</label>
      <div class="input-box">
        <span class="ico">👤</span>
        <input type="text" name="nom_affichage"
               placeholder="Ex : Dr. Nkou, Infirmière Marie…"
               value="<?= htmlspecialchars($_POST['nom_affichage'] ?? '') ?>"/>
      </div>
    </div>

    <!-- Rôle -->
    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? '') ?>"/>
    <span class="role-label">Votre rôle</span>
    <div class="role-grid">

      <!-- Secrétaire — bleu admin -->
      <div class="role-btn <?= ($_POST['role']??'')==='SECRETAIRE'?'sel-SECRETAIRE':'' ?>"
           onclick="selectRole('SECRETAIRE',this)">
        <span class="ico">🗂️</span>
        Secrétaire
        <div class="sub">Accueil & dossiers</div>
        <div class="role-indicator"></div>
      </div>

      <!-- Infirmière — teal -->
      <div class="role-btn <?= ($_POST['role']??'')==='INFIRMIERE'?'sel-INFIRMIERE':'' ?>"
           onclick="selectRole('INFIRMIERE',this)">
        <span class="ico">💉</span>
        Infirmière
        <div class="sub">Signes vitaux</div>
        <div class="role-indicator"></div>
      </div>

      <!-- Médecin — violet -->
      <div class="role-btn <?= ($_POST['role']??'')==='MEDECIN'?'sel-MEDECIN':'' ?>"
           onclick="selectRole('MEDECIN',this)">
        <span class="ico">🩺</span>
        Médecin
        <div class="sub">Consultation</div>
        <div class="role-indicator"></div>
      </div>

      <!-- Admin — bleu foncé -->
      <div class="role-btn <?= ($_POST['role']??'')==='ADMIN'?'sel-ADMIN':'' ?>"
           onclick="selectRole('ADMIN',this)">
        <span class="ico">⚙️</span>
        Admin
        <div class="sub">Gestion système</div>
        <div class="role-indicator"></div>
      </div>

    </div>

    <!-- Mot de passe -->
    <div class="field">
      <label>Mot de passe</label>
      <div class="input-box">
        <span class="ico">🔒</span>
        <input type="password" name="password" placeholder="••••••••" required/>
      </div>
    </div>

    <button type="submit" class="btn" id="btnLogin">Se connecter →</button>
  </form>

  <!-- Footer avec bande de priorités -->
  <div class="footer">
    <p>Système de priorisation médicale</p>
    <div class="priority-strip">
      <div class="prio-dot"><span style="background:#dc2626;"></span>Critique</div>
      <div class="prio-dot"><span style="background:#ea580c;"></span>Urgent</div>
      <div class="prio-dot"><span style="background:#ca8a04;"></span>Semi-urgent</div>
      <div class="prio-dot"><span style="background:#16a34a;"></span>Non urgent</div>
    </div>
  </div>

</div>

<script>
// Couleurs des indicateurs par rôle — cohérentes avec chaque interface
var ROLE_COLORS = {
  SECRETAIRE: '#1a6bc4',  // bleu admin
  INFIRMIERE:  '#0d9488',  // teal
  MEDECIN:     '#7c3aed',  // violet
  ADMIN:       '#0f4c81',  // bleu foncé
};

function selectRole(role, el) {
  // Réinitialiser tous les boutons
  document.querySelectorAll('.role-btn').forEach(function(b){
    b.className = 'role-btn';
    b.querySelector('.role-indicator').style.background = 'transparent';
  });
  // Activer le sélectionné
  el.classList.add('sel-' + role);
  el.querySelector('.role-indicator').style.background = ROLE_COLORS[role] || '#fff';
  document.getElementById('roleInput').value = role;

  // Changer la couleur du bouton connexion selon le rôle
  var btn = document.getElementById('btnLogin');
  var c = ROLE_COLORS[role] || '#1a6bc4';
  btn.style.background = 'linear-gradient(135deg, '+c+', '+darken(c)+')';
}

function darken(hex) {
  // Assombrit légèrement la couleur pour le gradient
  var map = {'#1a6bc4':'#0f4c81','#0d9488':'#0f766e','#7c3aed':'#6d28d9','#0f4c81':'#0a3260'};
  return map[hex] || '#0f4c81';
}

// Restaurer la sélection si erreur PHP (page rechargée)
var roleVal = document.getElementById('roleInput').value;
if(roleVal) {
  var btn = document.querySelector('[onclick="selectRole(\''+roleVal+'\',this)"]');
  if(btn) selectRole(roleVal, btn);
}
</script>
</body>
</html>
