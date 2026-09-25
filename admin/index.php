<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('ADMIN');

$user = getUser();
$db   = getDB();
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CRÉER UTILISATEUR ─────────────────────────────────
    if ($action === 'creer') {
        $role  = strtoupper(trim($_POST['role']  ?? ''));
        $salle = trim($_POST['salle'] ?? '');
        $mdp   = trim($_POST['mot_de_passe'] ?? '');
        $nom   = trim($_POST['nom_affichage'] ?? '');
        if (!$role || !$mdp) {
            $erreur = "Le rôle et le mot de passe sont obligatoires.";
        } else {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            try {
                $db->prepare("INSERT INTO utilisateurs (role, mot_de_passe, salle) VALUES (?,?,?)")
                   ->execute([$role, $hash, $salle]);
                $message = "✅ Utilisateur $role créé avec succès.";
            } catch(Exception $e) {
                $erreur = "Ce rôle existe déjà.";
            }
        }
    }

    // ── MODIFIER UTILISATEUR ──────────────────────────────
    if ($action === 'modifier') {
        $id    = intval($_POST['id'] ?? 0);
        $salle = trim($_POST['salle'] ?? '');
        $db->prepare("UPDATE utilisateurs SET salle=? WHERE id=?")->execute([$salle, $id]);
        $message = "✅ Utilisateur mis à jour.";
    }

    // ── RÉINITIALISER MOT DE PASSE ────────────────────────
    if ($action === 'reset_mdp') {
        $id  = intval($_POST['id'] ?? 0);
        $mdp = trim($_POST['nouveau_mdp'] ?? '');
        if ($mdp) {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            $db->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")->execute([$hash, $id]);
            $message = "✅ Mot de passe réinitialisé.";
        } else {
            $erreur = "Le nouveau mot de passe ne peut pas être vide.";
        }
    }

    // ── SUPPRIMER UTILISATEUR ─────────────────────────────
    if ($action === 'supprimer') {
        $id = intval($_POST['id'] ?? 0);
        $u  = $db->query("SELECT role FROM utilisateurs WHERE id=$id")->fetch();
        if ($u && $u['role'] === $_SESSION['user_role']) {
            $erreur = "Vous ne pouvez pas supprimer votre propre compte.";
        } elseif ($id) {
            $db->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$id]);
            $message = "✅ Utilisateur supprimé.";
        }
    }
}

// ── DONNÉES ───────────────────────────────────────────────
$utilisateurs = $db->query("SELECT id, role, salle FROM utilisateurs ORDER BY role")->fetchAll();

// Statistiques globales
$stats = [];
$stats['total']       = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$stats['attente']     = $db->query("SELECT COUNT(*) FROM patients WHERE statut='EN_ATTENTE'")->fetchColumn();
$stats['consultation']= $db->query("SELECT COUNT(*) FROM patients WHERE statut='EN_CONSULTATION'")->fetchColumn();
$stats['oriente']     = $db->query("SELECT COUNT(*) FROM patients WHERE statut='ORIENTE'")->fetchColumn();
$stats['rouge']       = $db->query("SELECT COUNT(*) FROM patients WHERE priorite='ROUGE'")->fetchColumn();
$stats['orange']      = $db->query("SELECT COUNT(*) FROM patients WHERE priorite='ORANGE'")->fetchColumn();
$stats['jaune']       = $db->query("SELECT COUNT(*) FROM patients WHERE priorite='JAUNE'")->fetchColumn();
$stats['vert']        = $db->query("SELECT COUNT(*) FROM patients WHERE priorite='VERT'")->fetchColumn();
$stats['critiques']   = $db->query("SELECT COUNT(*) FROM patients WHERE cas_critique=1")->fetchColumn();
$stats['aujourd_hui'] = $db->query("SELECT COUNT(*) FROM patients WHERE DATE(date_arrivee)=CURDATE()")->fetchColumn();

// Historique patients (tous)
$onglet   = $_GET['tab'] ?? 'utilisateurs';
$patients = [];
if ($onglet === 'patients') {
    $patients = $db->query("
        SELECT * FROM patients
        ORDER BY date_arrivee DESC
        LIMIT 200
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealthQueue — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --admin:#0f4c81;--admin2:#1a6bc4;--admin-bg:#eff6ff;
      --rouge:#dc2626;--rouge-bg:rgba(220,38,38,.1);
      --orange:#ea580c;--orange-bg:rgba(234,88,12,.1);
      --jaune:#ca8a04;--jaune-bg:rgba(202,138,4,.1);
      --vert:#16a34a;--vert-bg:rgba(22,163,74,.1);
      --bg:#f0f4f8;--blanc:#fff;
      --texte:#0f172a;--gris:#64748b;--bord:#e2e8f0;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--texte);min-height:100vh;}

    /* TOPBAR */
    .topbar{background:linear-gradient(135deg,var(--admin),var(--admin2));padding:0 24px;height:58px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 12px rgba(15,76,129,.3);position:sticky;top:0;z-index:100;}
    .t-left{display:flex;align-items:center;gap:10px;}
    .t-title{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:#fff;}
    .t-role{background:rgba(255,255,255,.2);color:#fff;font-size:.7rem;padding:3px 10px;border-radius:99px;font-weight:600;text-transform:uppercase;}
    .t-user{color:rgba(255,255,255,.85);font-size:.86rem;}
    .btn-out{background:rgba(255,255,255,.15);border:none;border-radius:8px;padding:6px 13px;color:#fff;font-size:.82rem;cursor:pointer;text-decoration:none;}

    /* ALERTS */
    .alert{margin:14px 24px 0;padding:11px 16px;border-radius:10px;font-size:.87rem;font-weight:500;display:flex;align-items:center;gap:9px;}
    .alert.ok {background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
    .alert.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}

    /* ONGLETS */
    .tabs{display:flex;gap:4px;padding:18px 24px 0;max-width:1400px;margin:0 auto;}
    .tab{padding:9px 20px;border-radius:10px 10px 0 0;font-family:'Syne',sans-serif;font-size:.83rem;font-weight:700;cursor:pointer;text-decoration:none;color:var(--gris);background:#e2e8f0;transition:all .15s;}
    .tab.actif{background:var(--blanc);color:var(--admin);box-shadow:0 -2px 8px rgba(0,0,0,.06);}

    /* CONTENU */
    .content{max-width:1400px;margin:0 auto;padding:0 24px 24px;}

    /* STATS */
    .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;padding-top:18px;}
    .stat-box{background:var(--blanc);border-radius:13px;padding:16px 18px;box-shadow:0 2px 10px rgba(0,0,0,.06);border:1px solid var(--bord);text-align:center;}
    .stat-box .ico{font-size:1.6rem;margin-bottom:6px;}
    .stat-box .num{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;line-height:1;}
    .stat-box .lbl{font-size:.72rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-top:4px;}
    .stat-box.rouge{border-color:var(--rouge);background:var(--rouge-bg);} .stat-box.rouge .num{color:var(--rouge);}
    .stat-box.orange{border-color:var(--orange);background:var(--orange-bg);} .stat-box.orange .num{color:var(--orange);}
    .stat-box.jaune{border-color:var(--jaune);background:var(--jaune-bg);} .stat-box.jaune .num{color:var(--jaune);}
    .stat-box.vert{border-color:var(--vert);background:var(--vert-bg);} .stat-box.vert .num{color:var(--vert);}
    .stat-box.bleu{border-color:var(--admin2);background:var(--admin-bg);} .stat-box.bleu .num{color:var(--admin);}

    /* LAYOUT 2 colonnes */
    .cols{display:grid;grid-template-columns:1fr 380px;gap:20px;padding-top:18px;}

    /* CARD */
    .card{background:var(--blanc);border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid var(--bord);}
    .card-title{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid var(--bord);display:flex;align-items:center;justify-content:space-between;}

    /* TABLEAU UTILISATEURS */
    table{width:100%;border-collapse:collapse;}
    thead th{font-size:.72rem;font-weight:700;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;padding:8px 12px;text-align:left;background:#f8fafc;border-bottom:2px solid var(--bord);}
    tbody tr{border-bottom:1px solid var(--bord);transition:background .12s;}
    tbody tr:hover{background:#f8fafc;}
    tbody td{padding:11px 12px;font-size:.87rem;vertical-align:middle;}
    .role-badge{font-size:.68rem;font-weight:700;padding:3px 10px;border-radius:99px;text-transform:uppercase;}
    .role-badge.ADMIN     {background:#ede9fe;color:#6d28d9;}
    .role-badge.MEDECIN   {background:#ede9fe;color:#7c3aed;}
    .role-badge.INFIRMIERE{background:#f0fdfa;color:#0d9488;}
    .role-badge.SECRETAIRE{background:#eff6ff;color:#1d4ed8;}
    .btn-action{border:none;border-radius:7px;padding:5px 11px;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;}
    .btn-edit{background:#eff6ff;color:#1d4ed8;}
    .btn-edit:hover{background:#dbeafe;}
    .btn-del{background:#fee2e2;color:var(--rouge);}
    .btn-del:hover{background:#fecaca;}
    .btn-mdp{background:#f0fdfa;color:#0d9488;}
    .btn-mdp:hover{background:#ccfbf1;}

    /* FORMULAIRE */
    .sec{font-family:'Syne',sans-serif;font-size:.77rem;font-weight:700;color:var(--admin);text-transform:uppercase;letter-spacing:.6px;margin:16px 0 9px;padding-bottom:5px;border-bottom:2px solid #dbeafe;}
    .field{margin-bottom:12px;}
    .field label{display:block;font-size:.67rem;font-weight:600;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
    .field input,.field select{width:100%;padding:9px 11px;border:1.5px solid var(--bord);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--texte);background:#fff;outline:none;transition:border-color .2s;}
    .field input:focus,.field select:focus{border-color:var(--admin2);box-shadow:0 0 0 3px rgba(26,107,196,.1);}
    .btn-submit{width:100%;background:linear-gradient(135deg,var(--admin),var(--admin2));color:#fff;border:none;border-radius:10px;padding:11px;font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;cursor:pointer;transition:all .2s;box-shadow:0 4px 14px rgba(15,76,129,.3);}
    .btn-submit:hover{transform:translateY(-1px);}

    /* MODAL */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;}
    .modal-overlay.open{display:flex;}
    .modal{background:#fff;border-radius:16px;padding:24px;width:380px;box-shadow:0 20px 60px rgba(0,0,0,.2);}
    .modal h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:16px;}
    .modal-btns{display:flex;gap:8px;margin-top:16px;}
    .btn-cancel{flex:1;background:#f1f5f9;color:var(--gris);border:none;border-radius:9px;padding:10px;font-weight:600;cursor:pointer;}
    .btn-confirm{flex:1;background:var(--admin);color:#fff;border:none;border-radius:9px;padding:10px;font-weight:700;cursor:pointer;}
    .btn-confirm.danger{background:var(--rouge);}

    /* TABLEAU PATIENTS */
    .badge{font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;text-transform:uppercase;}
    .badge.ROUGE{background:var(--rouge-bg);color:var(--rouge);}
    .badge.ORANGE{background:var(--orange-bg);color:var(--orange);}
    .badge.JAUNE{background:var(--jaune-bg);color:var(--jaune);}
    .badge.VERT{background:var(--vert-bg);color:var(--vert);}
    .badge-st{font-size:.6rem;font-weight:600;padding:2px 7px;border-radius:99px;background:#f1f5f9;color:var(--gris);}

    @media(max-width:1100px){.stats-grid{grid-template-columns:repeat(3,1fr);}.cols{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="t-left">
    <span style="font-size:1.3rem;">🏥</span>
    <span class="t-title">HealthQueue</span>
    <span class="t-role">⚙️ Admin</span>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <span class="t-user">👤 <?= htmlspecialchars($user['nom'] ?: 'Administrateur') ?></span>
    <a href="../logout.php" class="btn-out">Déconnexion</a>
  </div>
</div>

<?php if($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if($erreur):  ?><div class="alert err">⚠️ <?= htmlspecialchars($erreur) ?></div><?php endif; ?>

<!-- ONGLETS -->
<div class="tabs">
  <a href="?tab=utilisateurs" class="tab <?= $onglet==='utilisateurs'?'actif':'' ?>">👥 Utilisateurs</a>
  <a href="?tab=statistiques"  class="tab <?= $onglet==='statistiques' ?'actif':'' ?>">📊 Statistiques</a>
  <a href="?tab=patients"      class="tab <?= $onglet==='patients'     ?'actif':'' ?>">🗂️ Historique patients</a>
</div>

<div class="content">

  <!-- ══ ONGLET UTILISATEURS ══ -->
  <?php if($onglet === 'utilisateurs'): ?>
  <div class="cols">

    <!-- Liste utilisateurs -->
    <div class="card">
      <div class="card-title">
        👥 Comptes utilisateurs
        <span style="font-size:.78rem;color:var(--gris);font-weight:400;"><?= count($utilisateurs) ?> compte(s)</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Rôle</th>
            <th>Salle / Bureau</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($utilisateurs as $u): ?>
          <tr>
            <td><span class="role-badge <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td><?= htmlspecialchars($u['salle'] ?: '—') ?></td>
            <td style="text-align:right;">
              <div style="display:flex;gap:5px;justify-content:flex-end;">
                <button class="btn-action btn-mdp"
                        onclick="openModalMdp(<?= $u['id'] ?>, '<?= htmlspecialchars($u['role']) ?>')">
                  🔑 MDP
                </button>
                <button class="btn-action btn-edit"
                        onclick="openModalEdit(<?= $u['id'] ?>, '<?= htmlspecialchars($u['role']) ?>', '<?= htmlspecialchars($u['salle'] ?? '') ?>')">
                  ✏️ Modifier
                </button>
                <button class="btn-action btn-del"
                        onclick="openModalDel(<?= $u['id'] ?>, '<?= htmlspecialchars($u['role']) ?>')">
                  🗑️ Supprimer
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Formulaire création -->
    <div class="card">
      <div class="card-title">➕ Créer un compte</div>
      <form method="POST">
        <input type="hidden" name="action" value="creer"/>
        <div class="sec">Informations du compte</div>
        <div class="field">
          <label>Rôle *</label>
          <select name="role" required>
            <option value="">— Choisir —</option>
            <option value="SECRETAIRE">👩‍💼 Secrétaire</option>
            <option value="INFIRMIERE">💉 Infirmière</option>
            <option value="MEDECIN">🩺 Médecin</option>
            <option value="ADMIN">⚙️ Admin</option>
          </select>
        </div>
        <div class="field">
          <label>Salle / Bureau (optionnel)</label>
          <input type="text" name="salle" placeholder="Ex : Salle 1, Bureau 3…"/>
        </div>
        <div class="sec">Sécurité</div>
        <div class="field">
          <label>Mot de passe *</label>
          <input type="password" name="mot_de_passe" required placeholder="Minimum 6 caractères"/>
        </div>
        <button type="submit" class="btn-submit">➕ Créer le compte</button>
      </form>
    </div>

  </div>
  <?php endif; ?>

  <!-- ══ ONGLET STATISTIQUES ══ -->
  <?php if($onglet === 'statistiques'): ?>
  <div class="stats-grid">
    <div class="stat-box bleu">
      <div class="ico">🏥</div>
      <div class="num"><?= $stats['total'] ?></div>
      <div class="lbl">Total patients</div>
    </div>
    <div class="stat-box bleu">
      <div class="ico">📅</div>
      <div class="num"><?= $stats['aujourd_hui'] ?></div>
      <div class="lbl">Aujourd'hui</div>
    </div>
    <div class="stat-box bleu">
      <div class="ico">⏳</div>
      <div class="num"><?= $stats['attente'] ?></div>
      <div class="lbl">En attente</div>
    </div>
    <div class="stat-box vert">
      <div class="ico">🩺</div>
      <div class="num"><?= $stats['consultation'] ?></div>
      <div class="lbl">En consultation</div>
    </div>
    <div class="stat-box bleu">
      <div class="ico">✅</div>
      <div class="num"><?= $stats['oriente'] ?></div>
      <div class="lbl">Orientés</div>
    </div>
    <div class="stat-box rouge">
      <div class="ico">🔴</div>
      <div class="num"><?= $stats['rouge'] ?></div>
      <div class="lbl">Priorité Rouge</div>
    </div>
    <div class="stat-box orange">
      <div class="ico">🟠</div>
      <div class="num"><?= $stats['orange'] ?></div>
      <div class="lbl">Priorité Orange</div>
    </div>
    <div class="stat-box jaune">
      <div class="ico">🟡</div>
      <div class="num"><?= $stats['jaune'] ?></div>
      <div class="lbl">Priorité Jaune</div>
    </div>
    <div class="stat-box vert">
      <div class="ico">🟢</div>
      <div class="num"><?= $stats['vert'] ?></div>
      <div class="lbl">Priorité Verte</div>
    </div>
    <div class="stat-box rouge">
      <div class="ico">🚨</div>
      <div class="num"><?= $stats['critiques'] ?></div>
      <div class="lbl">Cas critiques</div>
    </div>
  </div>

  <!-- Répartition par priorité -->
  <?php
    $total_p = max(1, $stats['rouge'] + $stats['orange'] + $stats['jaune'] + $stats['vert']);
    $pct = [
      'rouge'  => round($stats['rouge']  / $total_p * 100),
      'orange' => round($stats['orange'] / $total_p * 100),
      'jaune'  => round($stats['jaune']  / $total_p * 100),
      'vert'   => round($stats['vert']   / $total_p * 100),
    ];
  ?>
  <div class="card" style="margin-top:0;">
    <div class="card-title">📊 Répartition par priorité</div>
    <div style="display:flex;height:32px;border-radius:10px;overflow:hidden;gap:2px;margin-bottom:14px;">
      <?php if($pct['rouge']): ?><div style="width:<?=$pct['rouge']?>%;background:#dc2626;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;"><?=$pct['rouge']?>%</div><?php endif; ?>
      <?php if($pct['orange']):?><div style="width:<?=$pct['orange']?>%;background:#ea580c;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;"><?=$pct['orange']?>%</div><?php endif; ?>
      <?php if($pct['jaune']): ?><div style="width:<?=$pct['jaune']?>%;background:#ca8a04;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;"><?=$pct['jaune']?>%</div><?php endif; ?>
      <?php if($pct['vert']):  ?><div style="width:<?=$pct['vert']?>%;background:#16a34a;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;"><?=$pct['vert']?>%</div><?php endif; ?>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
      <span style="font-size:.8rem;display:flex;align-items:center;gap:6px;"><span style="width:12px;height:12px;background:#dc2626;border-radius:3px;display:inline-block;"></span>🔴 Rouge : <?=$stats['rouge']?></span>
      <span style="font-size:.8rem;display:flex;align-items:center;gap:6px;"><span style="width:12px;height:12px;background:#ea580c;border-radius:3px;display:inline-block;"></span>🟠 Orange : <?=$stats['orange']?></span>
      <span style="font-size:.8rem;display:flex;align-items:center;gap:6px;"><span style="width:12px;height:12px;background:#ca8a04;border-radius:3px;display:inline-block;"></span>🟡 Jaune : <?=$stats['jaune']?></span>
      <span style="font-size:.8rem;display:flex;align-items:center;gap:6px;"><span style="width:12px;height:12px;background:#16a34a;border-radius:3px;display:inline-block;"></span>🟢 Vert : <?=$stats['vert']?></span>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ ONGLET PATIENTS ══ -->
  <?php if($onglet === 'patients'): ?>
  <div class="card" style="padding-top:18px;margin-top:0;">
    <div class="card-title">
      🗂️ Historique patients
      <span style="font-size:.78rem;color:var(--gris);font-weight:400;">200 derniers</span>
    </div>
    <div style="overflow-x:auto;">
    <table>
      <thead>
        <tr>
          <th>Nom / Prénom</th>
          <th>Âge</th>
          <th>Arrivée</th>
          <th>Priorité</th>
          <th>Score</th>
          <th>Statut</th>
          <th>Orientation</th>
          <th>Critique</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($patients)): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--gris);padding:24px;">Aucun patient enregistré.</td></tr>
        <?php else: ?>
          <?php foreach($patients as $p): ?>
          <tr>
            <td>
              <div style="font-weight:700;font-size:.88rem;"><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></div>
              <div style="font-size:.74rem;color:var(--gris);font-style:italic;"><?= htmlspecialchars(mb_substr($p['motif_consultation'],0,40)) ?>…</div>
            </td>
            <td><?= $p['age'] ?> ans</td>
            <td style="font-size:.8rem;color:var(--gris);"><?= date('d/m H:i',strtotime($p['date_arrivee'])) ?></td>
            <td><span class="badge <?= $p['priorite'] ?>"><?= $p['priorite'] ?></span></td>
            <td style="font-size:.82rem;"><?= $p['score_priorite'] ?>/100</td>
            <td><span class="badge-st"><?= str_replace('_',' ',$p['statut']) ?></span></td>
            <td style="font-size:.8rem;"><?= htmlspecialchars($p['orientation'] ?: '—') ?></td>
            <td style="text-align:center;"><?= $p['cas_critique'] ? '🚨' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- MODAL — Modifier salle -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <h3>✏️ Modifier le compte</h3>
    <form method="POST">
      <input type="hidden" name="action" value="modifier"/>
      <input type="hidden" name="id" id="edit_id"/>
      <div class="field">
        <label>Rôle</label>
        <input type="text" id="edit_role" disabled style="background:#f8fafc;"/>
      </div>
      <div class="field">
        <label>Salle / Bureau</label>
        <input type="text" name="salle" id="edit_salle" placeholder="Ex : Salle 2, Bureau 1…"/>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn-cancel" onclick="closeModal('modalEdit')">Annuler</button>
        <button type="submit" class="btn-confirm">✅ Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL — Réinitialiser mot de passe -->
<div class="modal-overlay" id="modalMdp">
  <div class="modal">
    <h3>🔑 Réinitialiser le mot de passe</h3>
    <p style="font-size:.84rem;color:var(--gris);margin-bottom:14px;">Compte : <strong id="mdp_role"></strong></p>
    <form method="POST">
      <input type="hidden" name="action" value="reset_mdp"/>
      <input type="hidden" name="id" id="mdp_id"/>
      <div class="field">
        <label>Nouveau mot de passe</label>
        <input type="password" name="nouveau_mdp" required placeholder="Minimum 6 caractères"/>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn-cancel" onclick="closeModal('modalMdp')">Annuler</button>
        <button type="submit" class="btn-confirm">🔑 Réinitialiser</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL — Supprimer -->
<div class="modal-overlay" id="modalDel">
  <div class="modal">
    <h3>🗑️ Supprimer le compte</h3>
    <p style="font-size:.88rem;color:var(--gris);">Supprimer le compte <strong id="del_role"></strong> ?<br>Cette action est irréversible.</p>
    <form method="POST">
      <input type="hidden" name="action" value="supprimer"/>
      <input type="hidden" name="id" id="del_id"/>
      <div class="modal-btns">
        <button type="button" class="btn-cancel" onclick="closeModal('modalDel')">Annuler</button>
        <button type="submit" class="btn-confirm danger">🗑️ Supprimer</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModalEdit(id, role, salle) {
  document.getElementById('edit_id').value    = id;
  document.getElementById('edit_role').value  = role;
  document.getElementById('edit_salle').value = salle;
  document.getElementById('modalEdit').classList.add('open');
}
function openModalMdp(id, role) {
  document.getElementById('mdp_id').value   = id;
  document.getElementById('mdp_role').textContent = role;
  document.getElementById('modalMdp').classList.add('open');
}
function openModalDel(id, role) {
  document.getElementById('del_id').value          = id;
  document.getElementById('del_role').textContent  = role;
  document.getElementById('modalDel').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
// Fermer en cliquant en dehors
document.querySelectorAll('.modal-overlay').forEach(function(m){
  m.addEventListener('click', function(e){ if(e.target===m) m.classList.remove('open'); });
});
</script>
</body>
</html>
