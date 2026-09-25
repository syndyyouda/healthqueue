<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('SECRETAIRE');

$user = getUser();
$db   = getDB();

// ── Traitement des actions POST ───────────────────────────
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── SUPPRIMER UNE NOTIFICATION ────────────────────────
    if ($action === 'supprimer_notif') {
        $notif_id = intval($_POST['notif_id'] ?? 0);
        if ($notif_id) {
            $db->prepare("DELETE FROM notifications WHERE id=? AND destinataire_role='SECRETAIRE'")->execute([$notif_id]);
        }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    // ── TOUT SUPPRIMER ────────────────────────────────────
    if ($action === 'supprimer_tout_notif') {
        $db->query("DELETE FROM notifications WHERE destinataire_role='SECRETAIRE'");
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    // ── AJOUTER un patient ────────────────────────────────
    if ($action === 'ajouter') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $dob    = $_POST['date_naissance'] ?? '';
        $age    = intval($_POST['age'] ?? 0);
        $sexe   = $_POST['sexe'] ?? 'M';
        $tel    = trim($_POST['telephone'] ?? '');
        $adr    = trim($_POST['adresse'] ?? '');
        $motif  = trim($_POST['motif_consultation'] ?? '');
        $accomp = trim($_POST['accompagnant'] ?? '');

        if ($nom && $prenom && $dob && $motif) {
            $stmt = $db->prepare("
                INSERT INTO patients
                  (nom, prenom, date_naissance, age, sexe, telephone, adresse,
                   motif_consultation, accompagnant, priorite, score_priorite, statut)
                VALUES (?,?,?,?,?,?,?,?,?,'VERT',0,'EN_ATTENTE')
            ");
            $stmt->execute([$nom,$prenom,$dob,$age,$sexe,$tel,$adr,$motif,$accomp]);
            $message = "Patient $prenom $nom enregistré avec succès.";
        } else {
            $erreur = "Veuillez remplir les champs obligatoires.";
        }
    }

    // ── MODIFIER un patient ───────────────────────────────
    if ($action === 'modifier') {
        $id     = intval($_POST['id'] ?? 0);
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $dob    = $_POST['date_naissance'] ?? '';
        $age    = intval($_POST['age'] ?? 0);
        $sexe   = $_POST['sexe'] ?? 'M';
        $tel    = trim($_POST['telephone'] ?? '');
        $adr    = trim($_POST['adresse'] ?? '');
        $motif  = trim($_POST['motif_consultation'] ?? '');
        $accomp = trim($_POST['accompagnant'] ?? '');

        if ($id && $nom && $prenom) {
            $stmt = $db->prepare("
                UPDATE patients SET
                  nom=?, prenom=?, date_naissance=?, age=?, sexe=?,
                  telephone=?, adresse=?, motif_consultation=?, accompagnant=?
                WHERE id=?
            ");
            $stmt->execute([$nom,$prenom,$dob,$age,$sexe,$tel,$adr,$motif,$accomp,$id]);
            $message = "Patient modifié avec succès.";
        }
    }

    // ── SUPPRIMER un patient ──────────────────────────────
    if ($action === 'supprimer') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM patients WHERE id=?")->execute([$id]);
            $message = "Patient supprimé.";
        }
    }
}

// ── Charger la liste des patients ─────────────────────────
$patients = $db->query("
    SELECT * FROM patients
    WHERE statut NOT IN ('TERMINE')
    ORDER BY
      FIELD(priorite,'ROUGE','ORANGE','JAUNE','VERT'),
      date_arrivee ASC
")->fetchAll();

// ── Notifications non lues ─────────────────────────────────
$notifs = $db->query("
    SELECT * FROM notifications
    WHERE destinataire_role='SECRETAIRE' AND lue=0
    ORDER BY created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealthQueue — Secrétaire</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --rouge:#dc2626; --rouge-bg:rgba(220,38,38,.1);
      --orange:#ea580c; --orange-bg:rgba(234,88,12,.1);
      --jaune:#ca8a04; --jaune-bg:rgba(202,138,4,.1);
      --vert:#16a34a; --vert-bg:rgba(22,163,74,.1);
      --bleu:#0f4c81; --bleu2:#1a6bc4;
      --bg:#f0f4f8; --blanc:#fff;
      --texte:#0f172a; --gris:#64748b; --bord:#e2e8f0;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--texte); min-height:100vh; }

    /* ── TOPBAR ── */
    .topbar {
      background:var(--bleu);
      padding:0 28px;
      height:60px;
      display:flex; align-items:center; justify-content:space-between;
      box-shadow:0 2px 12px rgba(15,76,129,.3);
      position:sticky; top:0; z-index:100;
    }
    .topbar-left { display:flex; align-items:center; gap:12px; }
    .topbar-logo { font-size:1.4rem; }
    .topbar-title { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; color:#fff; }
    .topbar-role {
      background:rgba(255,255,255,.15); color:rgba(255,255,255,.9);
      font-size:.72rem; padding:3px 10px; border-radius:99px;
      font-weight:600; text-transform:uppercase; letter-spacing:.5px;
    }
    .topbar-right { display:flex; align-items:center; gap:14px; }
    .topbar-user { color:rgba(255,255,255,.8); font-size:.88rem; }

    .notif-bell {
      position:relative; background:rgba(255,255,255,.12);
      border:none; border-radius:8px; padding:7px 10px;
      cursor:pointer; color:#fff; font-size:1.1rem;
      transition:background .2s;
    }
    .notif-bell:hover { background:rgba(255,255,255,.22); }
    .notif-count {
      position:absolute; top:-4px; right:-4px;
      background:var(--rouge); color:#fff;
      font-size:.6rem; font-weight:700;
      width:17px; height:17px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
    }

    .btn-logout {
      background:rgba(255,255,255,.12); border:none; border-radius:8px;
      padding:7px 14px; color:#fff; font-size:.83rem;
      cursor:pointer; transition:background .2s; text-decoration:none;
    }
    .btn-logout:hover { background:rgba(220,38,38,.4); }

    /* ── LAYOUT ── */
    .layout { display:grid; grid-template-columns:1fr 380px; gap:20px; padding:24px 28px; max-width:1400px; margin:0 auto; }

    /* ── ALERTES ── */
    .alert {
      grid-column:1/-1;
      padding:12px 18px; border-radius:10px;
      font-size:.88rem; font-weight:500;
      display:flex; align-items:center; gap:10px;
    }
    .alert.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
    .alert.error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }

    /* ── CARTE ── */
    .card {
      background:var(--blanc); border-radius:16px;
      padding:24px; box-shadow:0 2px 12px rgba(0,0,0,.06);
      border:1px solid var(--bord);
    }
    .card-title {
      font-family:'Syne',sans-serif; font-size:1rem;
      font-weight:700; color:var(--texte);
      margin-bottom:18px; padding-bottom:12px;
      border-bottom:2px solid var(--bord);
      display:flex; align-items:center; gap:8px;
    }

    /* ── FORMULAIRE ── */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .form-grid .full { grid-column:1/-1; }
    .field label {
      display:block; font-size:.72rem; font-weight:600;
      color:var(--gris); text-transform:uppercase;
      letter-spacing:.5px; margin-bottom:5px;
    }
    .field input, .field select, .field textarea {
      width:100%; padding:9px 12px;
      border:1.5px solid var(--bord); border-radius:9px;
      font-family:'DM Sans',sans-serif; font-size:.9rem;
      color:var(--texte); background:#fff; outline:none;
      transition:border-color .2s;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
      border-color:var(--bleu2);
      box-shadow:0 0 0 3px rgba(26,107,196,.1);
    }
    .field textarea { resize:vertical; min-height:72px; }
    .required { color:var(--rouge); }

    .form-actions { display:flex; gap:10px; margin-top:16px; justify-content:flex-end; }
    .btn-primary {
      background:var(--bleu2); color:#fff;
      border:none; border-radius:9px; padding:10px 22px;
      font-family:'Syne',sans-serif; font-weight:700;
      font-size:.88rem; cursor:pointer; transition:all .2s;
    }
    .btn-primary:hover { background:var(--bleu); transform:translateY(-1px); }
    .btn-secondary {
      background:var(--bord); color:var(--gris);
      border:none; border-radius:9px; padding:10px 18px;
      font-size:.88rem; cursor:pointer; transition:all .2s;
    }
    .btn-secondary:hover { background:#cbd5e1; }

    /* ── LISTE PATIENTS ── */
    .search-bar {
      display:flex; gap:10px; margin-bottom:16px;
    }
    .search-bar input {
      flex:1; padding:9px 14px;
      border:1.5px solid var(--bord); border-radius:9px;
      font-family:'DM Sans',sans-serif; font-size:.9rem; outline:none;
    }
    .search-bar input:focus { border-color:var(--bleu2); }

    .patient-list { display:flex; flex-direction:column; gap:10px; }

    .patient-card {
      border:1.5px solid var(--bord); border-radius:12px;
      padding:14px 16px; background:#fff;
      transition:all .18s; cursor:pointer;
      position:relative; overflow:hidden;
    }
    .patient-card::before {
      content:''; position:absolute; left:0; top:0; bottom:0;
      width:4px; border-radius:4px 0 0 4px;
    }
    .patient-card.ROUGE::before  { background:var(--rouge); }
    .patient-card.ORANGE::before { background:var(--orange); }
    .patient-card.JAUNE::before  { background:var(--jaune); }
    .patient-card.VERT::before   { background:var(--vert); }
    .patient-card:hover { border-color:#94a3b8; box-shadow:0 4px 16px rgba(0,0,0,.08); transform:translateY(-1px); }

    .patient-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
    .patient-nom { font-weight:700; font-size:.95rem; }
    .patient-badges { display:flex; gap:6px; align-items:center; }

    .badge {
      font-size:.68rem; font-weight:700; padding:2px 9px;
      border-radius:99px; text-transform:uppercase; letter-spacing:.4px;
    }
    .badge.ROUGE  { background:var(--rouge-bg);  color:var(--rouge); }
    .badge.ORANGE { background:var(--orange-bg); color:var(--orange); }
    .badge.JAUNE  { background:var(--jaune-bg);  color:var(--jaune); }
    .badge.VERT   { background:var(--vert-bg);   color:var(--vert); }

    .badge-statut {
      font-size:.68rem; font-weight:600; padding:2px 9px;
      border-radius:99px; background:#f1f5f9; color:var(--gris);
    }
    .badge-statut.EN_ATTENTE     { background:#eff6ff; color:#1d4ed8; }
    .badge-statut.EN_CONSULTATION{ background:#f0fdf4; color:#15803d; }
    .badge-statut.APPELE         { background:#fefce8; color:#854d0e; }

    .patient-info { font-size:.82rem; color:var(--gris); margin-bottom:8px; }
    .patient-motif { font-size:.82rem; color:#475569; font-style:italic; }

    .patient-actions { display:flex; gap:8px; margin-top:10px; }
    .btn-edit {
      background:#eff6ff; color:#1d4ed8;
      border:none; border-radius:7px; padding:5px 12px;
      font-size:.78rem; font-weight:600; cursor:pointer; transition:all .15s;
    }
    .btn-edit:hover { background:#dbeafe; }
    .btn-del {
      background:#fef2f2; color:var(--rouge);
      border:none; border-radius:7px; padding:5px 12px;
      font-size:.78rem; font-weight:600; cursor:pointer; transition:all .15s;
    }
    .btn-del:hover { background:#fee2e2; }

    /* ── COMPTEURS ── */
    .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:18px; }
    .stat-box {
      border-radius:10px; padding:12px 14px; text-align:center;
    }
    .stat-box .num { font-family:'Syne',sans-serif; font-size:1.6rem; font-weight:800; }
    .stat-box .lbl { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.4px; opacity:.8; }
    .stat-box.rouge  { background:var(--rouge-bg);  color:var(--rouge); }
    .stat-box.orange { background:var(--orange-bg); color:var(--orange); }
    .stat-box.jaune  { background:var(--jaune-bg);  color:var(--jaune); }
    .stat-box.vert   { background:var(--vert-bg);   color:var(--vert); }

    /* ── NOTIFICATIONS ── */
    .notif-panel { display:flex; flex-direction:column; gap:10px; }
    .notif-item {
      background:#fff; border:1.5px solid var(--bord);
      border-radius:10px; padding:12px 14px;
      border-left:4px solid var(--rouge);
      animation:notifIn .3s ease;
    }
    @keyframes notifIn { from{opacity:0;transform:translateX(10px)} to{opacity:1;transform:translateX(0)} }
    .notif-msg { font-size:.88rem; font-weight:600; color:var(--texte); margin-bottom:4px; }
    .notif-time { font-size:.75rem; color:var(--gris); }
    .notif-empty { text-align:center; color:var(--gris); font-size:.88rem; padding:20px; }

    /* ── MODAL MODIFIER ── */
    .modal-overlay {
      display:none; position:fixed; inset:0; z-index:200;
      background:rgba(0,0,0,.45); backdrop-filter:blur(4px);
      align-items:center; justify-content:center;
    }
    .modal-overlay.open { display:flex; }
    .modal {
      background:#fff; border-radius:18px; padding:28px;
      width:620px; max-height:90vh; overflow-y:auto;
      box-shadow:0 24px 64px rgba(0,0,0,.2);
      animation:modalIn .3s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes modalIn { from{opacity:0;transform:scale(.93)} to{opacity:1;transform:scale(1)} }
    .modal-title {
      font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800;
      margin-bottom:20px; padding-bottom:12px; border-bottom:2px solid var(--bord);
      display:flex; justify-content:space-between; align-items:center;
    }
    .modal-close {
      background:none; border:none; font-size:1.3rem;
      cursor:pointer; color:var(--gris); transition:color .15s;
    }
    .modal-close:hover { color:var(--rouge); }

    /* ── RESPONSIVE ── */
    @media(max-width:900px) {
      .layout { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="topbar-left">
    <span class="topbar-logo">🏥</span>
    <span class="topbar-title">HealthQueue</span>
    <span class="topbar-role">Secrétaire</span>
  </div>
  <div class="topbar-right">
    <span class="topbar-user">👤 <?= htmlspecialchars($user['nom'] ?: 'Secrétaire') ?></span>
    <button class="notif-bell" onclick="toggleNotifs()" title="Notifications">
      🔔
      <?php if (count($notifs) > 0): ?>
        <span class="notif-count"><?= count($notifs) ?></span>
      <?php endif; ?>
    </button>
    <a href="../logout.php" class="btn-logout">Déconnexion</a>
  </div>
</div>

<div class="layout">

  <!-- ALERTES -->
  <?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($erreur): ?>
    <div class="alert error">⚠️ <?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <!-- COLONNE GAUCHE -->
  <div>

    <!-- Formulaire enregistrement -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">📋 Enregistrer un nouveau patient</div>

      <form method="POST" action="">
        <input type="hidden" name="action" value="ajouter"/>
        <div class="form-grid">

          <div class="field">
            <label>Nom <span class="required">*</span></label>
            <input type="text" name="nom" placeholder="MBARGA" required/>
          </div>
          <div class="field">
            <label>Prénom <span class="required">*</span></label>
            <input type="text" name="prenom" placeholder="Paul" required/>
          </div>

          <div class="field">
            <label>Date de naissance <span class="required">*</span></label>
            <input type="date" name="date_naissance" id="new_dob" required oninput="calcAge(this.value,'new_age')"/>
          </div>
          <div class="field">
            <label>Âge <span class="required">*</span></label>
            <input type="number" name="age" id="new_age" min="0" max="130" placeholder="Calculé automatiquement" readonly style="background:#f8fafc;color:#0f172a;cursor:default;"/>
          </div>

          <div class="field">
            <label>Sexe</label>
            <select name="sexe">
              <option value="M">Masculin</option>
              <option value="F">Féminin</option>
              <option value="Autre">Autre</option>
            </select>
          </div>
          <div class="field">
            <label>Téléphone</label>
            <input type="text" name="telephone" placeholder="6XX XXX XXX"/>
          </div>

          <div class="field full">
            <label>Adresse</label>
            <input type="text" name="adresse" placeholder="Quartier, Ville"/>
          </div>

          <div class="field full">
            <label>Motif de consultation <span class="required">*</span></label>
            <textarea name="motif_consultation" placeholder="Décrivez le motif de la visite…" required></textarea>
          </div>

          <div class="field full">
            <label>Accompagnant</label>
            <input type="text" name="accompagnant" placeholder="Nom de l'accompagnant (optionnel)"/>
          </div>

        </div>
        <div class="form-actions">
          <button type="reset" class="btn-secondary">Annuler</button>
          <button type="submit" class="btn-primary">✚ Enregistrer le patient</button>
        </div>
      </form>
    </div>

    <!-- Liste des patients -->
    <div class="card">
      <div class="card-title">
        👥 File d'attente
        <span style="font-size:.8rem;color:var(--gris);font-family:'DM Sans',sans-serif;font-weight:400;">
          <?= count($patients) ?> patient(s)
        </span>
      </div>

      <!-- Compteurs par priorité -->
      <?php
        $cR = count(array_filter($patients, fn($p)=>$p['priorite']==='ROUGE'));
        $cO = count(array_filter($patients, fn($p)=>$p['priorite']==='ORANGE'));
        $cJ = count(array_filter($patients, fn($p)=>$p['priorite']==='JAUNE'));
        $cV = count(array_filter($patients, fn($p)=>$p['priorite']==='VERT'));
      ?>
      <div class="stats">
        <div class="stat-box rouge"><div class="num"><?=$cR?></div><div class="lbl">🔴 Critique</div></div>
        <div class="stat-box orange"><div class="num"><?=$cO?></div><div class="lbl">🟠 Élevée</div></div>
        <div class="stat-box jaune"><div class="num"><?=$cJ?></div><div class="lbl">🟡 Moyenne</div></div>
        <div class="stat-box vert"><div class="num"><?=$cV?></div><div class="lbl">🟢 Normale</div></div>
      </div>

      <!-- Recherche -->
      <div class="search-bar">
        <input type="text" id="searchInput" placeholder="🔍 Rechercher un patient…" oninput="filtrerPatients()"/>
      </div>

      <!-- Liste -->
      <div class="patient-list" id="patientList">
        <?php if (empty($patients)): ?>
          <div style="text-align:center;color:var(--gris);padding:32px;font-size:.9rem;">
            Aucun patient en attente pour le moment.
          </div>
        <?php else: ?>
          <?php foreach ($patients as $p): ?>
            <div class="patient-card <?= $p['priorite'] ?>" data-nom="<?= strtolower($p['nom'].' '.$p['prenom']) ?>">
              <div class="patient-top">
                <span class="patient-nom"><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></span>
                <div class="patient-badges">
                  <span class="badge <?= $p['priorite'] ?>"><?= $p['priorite'] ?></span>
                  <span class="badge-statut <?= $p['statut'] ?>"><?= str_replace('_',' ',$p['statut']) ?></span>
                </div>
              </div>
              <div class="patient-info">
                <?= $p['age'] ?> ans • <?= $p['sexe']==='M'?'Homme':'Femme' ?> •
                Arrivée : <?= date('H:i', strtotime($p['date_arrivee'])) ?>
                <?php if($p['telephone']): ?> • 📞 <?= htmlspecialchars($p['telephone']) ?><?php endif; ?>
              </div>
              <div class="patient-motif">« <?= htmlspecialchars($p['motif_consultation']) ?> »</div>
              <div class="patient-actions">
                <button class="btn-edit" onclick="ouvrirModifier(<?= htmlspecialchars(json_encode($p)) ?>)">✏️ Modifier</button>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Supprimer ce patient ?')">
                  <input type="hidden" name="action" value="supprimer"/>
                  <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
                  <button type="submit" class="btn-del">🗑️ Supprimer</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- fin colonne gauche -->

  <!-- COLONNE DROITE : Notifications -->
  <div>
    <div class="card" id="notifsPanel">
      <div class="card-title">
        🔔 Notifications du médecin
        <?php if(!empty($notifs)): ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer toutes les notifications ?')">
            <input type="hidden" name="action" value="supprimer_tout_notif"/>
            <button type="submit" style="background:#fee2e2;color:#dc2626;border:none;border-radius:7px;padding:4px 11px;font-size:.72rem;font-weight:700;cursor:pointer;">🗑️ Tout effacer</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="notif-panel" id="notifList">
        <?php if (empty($notifs)): ?>
          <div class="notif-empty">Aucune notification pour le moment.</div>
        <?php else: ?>
          <?php foreach ($notifs as $n): ?>
            <div class="notif-item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
              <div style="flex:1;">
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-time">🕐 <?= date('H:i', strtotime($n['created_at'])) ?></div>
              </div>
              <form method="POST" style="flex-shrink:0;">
                <input type="hidden" name="action" value="supprimer_notif"/>
                <input type="hidden" name="notif_id" value="<?= $n['id'] ?>"/>
                <button type="submit" title="Supprimer"
                        style="background:none;border:none;cursor:pointer;font-size:1rem;opacity:.5;padding:2px 5px;border-radius:5px;transition:all .15s;"
                        onmouseover="this.style.opacity='1';this.style.background='#fee2e2';"
                        onmouseout="this.style.opacity='.5';this.style.background='none';">✕</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- fin layout -->

<!-- MODAL MODIFIER -->
<div class="modal-overlay" id="modalModifier">
  <div class="modal">
    <div class="modal-title">
      ✏️ Modifier le patient
      <button class="modal-close" onclick="fermerModifier()">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="modifier"/>
      <input type="hidden" name="id" id="edit_id"/>
      <div class="form-grid">
        <div class="field">
          <label>Nom <span class="required">*</span></label>
          <input type="text" name="nom" id="edit_nom" required/>
        </div>
        <div class="field">
          <label>Prénom <span class="required">*</span></label>
          <input type="text" name="prenom" id="edit_prenom" required/>
        </div>
        <div class="field">
          <label>Date de naissance</label>
          <input type="date" name="date_naissance" id="edit_dob" oninput="calcAge(this.value,'edit_age')"/>
        </div>
        <div class="field">
          <label>Âge</label>
          <input type="number" name="age" id="edit_age" min="0" max="130" readonly style="background:#f8fafc;color:#0f172a;cursor:default;"/>
        </div>
        <div class="field">
          <label>Sexe</label>
          <select name="sexe" id="edit_sexe">
            <option value="M">Masculin</option>
            <option value="F">Féminin</option>
            <option value="Autre">Autre</option>
          </select>
        </div>
        <div class="field">
          <label>Téléphone</label>
          <input type="text" name="telephone" id="edit_tel"/>
        </div>
        <div class="field full">
          <label>Adresse</label>
          <input type="text" name="adresse" id="edit_adr"/>
        </div>
        <div class="field full">
          <label>Motif de consultation</label>
          <textarea name="motif_consultation" id="edit_motif"></textarea>
        </div>
        <div class="field full">
          <label>Accompagnant</label>
          <input type="text" name="accompagnant" id="edit_accomp"/>
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="fermerModifier()">Annuler</button>
        <button type="submit" class="btn-primary">💾 Enregistrer les modifications</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Recherche ─────────────────────────────────────────────
function filtrerPatients() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('.patient-card').forEach(function(c) {
    c.style.display = c.dataset.nom.includes(q) ? '' : 'none';
  });
}

// ── Modal modifier ────────────────────────────────────────
function ouvrirModifier(p) {
  document.getElementById('edit_id').value     = p.id;
  document.getElementById('edit_nom').value    = p.nom;
  document.getElementById('edit_prenom').value = p.prenom;
  document.getElementById('edit_dob').value    = p.date_naissance;
  document.getElementById('edit_age').value    = p.age;
  document.getElementById('edit_sexe').value   = p.sexe;
  document.getElementById('edit_tel').value    = p.telephone || '';
  document.getElementById('edit_adr').value    = p.adresse || '';
  document.getElementById('edit_motif').value  = p.motif_consultation;
  document.getElementById('edit_accomp').value = p.accompagnant || '';
  document.getElementById('modalModifier').classList.add('open');
}
function fermerModifier() {
  document.getElementById('modalModifier').classList.remove('open');
}
document.getElementById('modalModifier').addEventListener('click', function(e) {
  if (e.target === this) fermerModifier();
});

// ── Rafraîchissement auto des notifications (toutes les 10s) ──
function refreshNotifs() {
  fetch('../api/notifs.php?role=SECRETAIRE')
    .then(r => r.json())
    .then(function(data) {
      var list = document.getElementById('notifList');
      if (data.length === 0) {
        list.innerHTML = '<div class="notif-empty">Aucune notification pour le moment.</div>';
        return;
      }
      list.innerHTML = data.map(function(n) {
        return '<div class="notif-item"><div class="notif-msg">' + n.message +
               '</div><div class="notif-time">🕐 ' + n.heure + '</div></div>';
      }).join('');
    }).catch(function(){});
}
setInterval(refreshNotifs, 10000);

// ── Calcul automatique de l'âge ──────────────────────────
function calcAge(dob, targetId) {
  if (!dob) return;
  var today = new Date();
  var birth = new Date(dob);
  if (isNaN(birth.getTime()) || birth > today) return;
  var age = today.getFullYear() - birth.getFullYear();
  var m   = today.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
  document.getElementById(targetId).value = age;
}

// Recalculer l'âge si le formulaire de modification est ouvert avec une date existante
document.addEventListener('DOMContentLoaded', function() {
  var editDob = document.getElementById('edit_dob');
  if (editDob && editDob.value) calcAge(editDob.value, 'edit_age');
});
</script>

</body>
</html>
