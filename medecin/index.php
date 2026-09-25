<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('MEDECIN');

$user = getUser();
$db   = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = intval($_POST['id'] ?? 0);

    if ($action === 'appeler' && $id) {
        $db->prepare("UPDATE patients SET statut='APPELE', heure_appel=NOW() WHERE id=?")->execute([$id]);
        $pat = $db->query("SELECT nom, prenom FROM patients WHERE id=$id")->fetch();
        $db->prepare("INSERT INTO notifications (type,message,patient_id,destinataire_role) VALUES ('APPEL',?,?,'SECRETAIRE')")
           ->execute(["📢 Le médecin appelle : ".$pat['prenom']." ".$pat['nom'], $id]);
        $message = "📢 ".$pat['prenom']." ".$pat['nom']." appelé(e).";
    }
    if ($action === 'consulter' && $id) {
        $db->prepare("UPDATE patients SET statut='EN_CONSULTATION', heure_consultation=NOW() WHERE id=?")->execute([$id]);
        $pat = $db->query("SELECT nom, prenom FROM patients WHERE id=$id")->fetch();
        $message = "🩺 Consultation démarrée pour ".$pat['prenom']." ".$pat['nom'].".";
    }
    if ($action === 'terminer' && $id) {
        $notes      = trim($_POST['notes_medecin']          ?? '');
        $orientation= trim($_POST['orientation']            ?? '');
        $specialite = trim($_POST['specialite_orientation'] ?? '');
        $db->prepare("UPDATE patients SET statut='ORIENTE',heure_fin=NOW(),notes_medecin=?,orientation=?,specialite_orientation=? WHERE id=?")
           ->execute([$notes,$orientation,$specialite,$id]);
        $pat = $db->query("SELECT nom,prenom FROM patients WHERE id=$id")->fetch();
        $n = "✅ Consultation terminée : ".$pat['prenom']." ".$pat['nom'].($orientation?" → $orientation":'');
        $db->prepare("INSERT INTO notifications (type,message,patient_id,destinataire_role) VALUES ('FIN_CONSULTATION',?,?,'SECRETAIRE')")
           ->execute([$n,$id]);
        $message = "✅ Consultation terminée pour ".$pat['prenom']." ".$pat['nom'].".";
    }
    if ($action === 'notifier' && $id) {
        $msg = trim($_POST['message_notif'] ?? '');
        if ($msg) {
            $db->prepare("INSERT INTO notifications (type,message,patient_id,destinataire_role) VALUES ('MESSAGE',?,?,'SECRETAIRE')")
               ->execute([$msg,$id]);
            $message = "📨 Message envoyé à la secrétaire.";
        }
    }
}

// File normale — cas critiques EXCLUS, mais ORIENTE inclus pour permettre l'impression
$patients = $db->query("
    SELECT * FROM patients
    WHERE statut NOT IN ('TERMINE')
    AND cas_critique = 0
    ORDER BY FIELD(statut,'EN_CONSULTATION','APPELE','EN_ATTENTE','ORIENTE'),
             FIELD(priorite,'ROUGE','ORANGE','JAUNE','VERT'),
             date_arrivee ASC
")->fetchAll();

// Cas critiques — section séparée, NON affichés dans la file normale
$cas_critiques = $db->query("
    SELECT * FROM patients
    WHERE cas_critique = 1
    AND statut NOT IN ('TERMINE')
    ORDER BY date_arrivee ASC
")->fetchAll();

// Passer tous les patients en JSON pour le JS (signes vitaux inclus)
$tous = array_merge($patients, $cas_critiques);
$patientsJson = json_encode($tous, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealthQueue — Médecin</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--rouge:#dc2626;--rouge-bg:rgba(220,38,38,.1);--orange:#ea580c;--orange-bg:rgba(234,88,12,.1);--jaune:#ca8a04;--jaune-bg:rgba(202,138,4,.1);--vert:#16a34a;--vert-bg:rgba(22,163,74,.1);--med:#7c3aed;--med-bg:#f5f3ff;--bg:#f0f4f8;--blanc:#fff;--texte:#0f172a;--gris:#64748b;--bord:#e2e8f0;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--texte);min-height:100vh;}
    .topbar{background:var(--med);padding:0 24px;height:58px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 12px rgba(124,58,237,.3);position:sticky;top:0;z-index:100;}
    .t-left{display:flex;align-items:center;gap:10px;}
    .t-title{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:#fff;}
    .t-role{background:rgba(255,255,255,.2);color:#fff;font-size:.7rem;padding:3px 10px;border-radius:99px;font-weight:600;text-transform:uppercase;}
    .t-user{color:rgba(255,255,255,.85);font-size:.86rem;}
    .btn-out{background:rgba(255,255,255,.15);border:none;border-radius:8px;padding:6px 13px;color:#fff;font-size:.82rem;cursor:pointer;text-decoration:none;}
    .alert{margin:14px 24px 0;padding:11px 16px;border-radius:10px;font-size:.87rem;font-weight:500;display:flex;align-items:center;gap:9px;background:#ede9fe;color:#5b21b6;border:1px solid #ddd6fe;}
    .layout{display:grid;grid-template-columns:390px 1fr;gap:18px;padding:18px 24px;max-width:1500px;margin:0 auto;}
    .card{background:var(--blanc);border-radius:14px;padding:18px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid var(--bord);margin-bottom:16px;}
    .card:last-child{margin-bottom:0;}
    .card-title{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--bord);display:flex;align-items:center;justify-content:space-between;}

    /* CAS CRITIQUES */
    .critique-section{background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:14px;padding:14px 18px;margin-bottom:14px;box-shadow:0 6px 20px rgba(220,38,38,.4);}
    .critique-section h3{font-family:'Syne',sans-serif;color:#fff;font-size:.9rem;font-weight:800;margin-bottom:10px;}
    .crit-item{background:rgba(255,255,255,.15);border-radius:10px;padding:10px 13px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;gap:10px;}
    .crit-item:last-child{margin-bottom:0;}
    .crit-nom{color:#fff;font-weight:700;font-size:.88rem;}
    .crit-info{color:rgba(255,255,255,.8);font-size:.74rem;margin-top:2px;}
    .btn-crit-ok{background:#fff;color:var(--rouge);border:none;border-radius:8px;padding:7px 13px;font-family:'Syne',sans-serif;font-weight:800;font-size:.78rem;cursor:pointer;white-space:nowrap;}
    .btn-crit-ok:hover{background:#fee2e2;}

    /* LISTE */
    .p-list{display:flex;flex-direction:column;gap:8px;max-height:calc(100vh - 290px);overflow-y:auto;}
    .p-item{border:1.5px solid var(--bord);border-radius:11px;background:#fff;transition:all .15s;position:relative;overflow:hidden;}
    .p-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:5px;}
    .p-item.ROUGE::before{background:var(--rouge);}
    .p-item.ORANGE::before{background:var(--orange);}
    .p-item.JAUNE::before{background:var(--jaune);}
    .p-item.VERT::before{background:var(--vert);}
    .p-item.actif{border-color:var(--med);background:var(--med-bg);}
    .p-item.en-cours{border-color:var(--vert);background:var(--vert-bg);}
    .p-body{padding:11px 13px 8px 18px;cursor:pointer;}
    .p-body:hover{opacity:.85;}
    .p-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;gap:6px;}
    .p-nom{font-weight:700;font-size:.9rem;}
    .p-meta{font-size:.76rem;color:var(--gris);margin-bottom:3px;}
    .p-motif{font-size:.75rem;color:#475569;font-style:italic;}
    .badges{display:flex;gap:4px;flex-wrap:wrap;flex-shrink:0;}
    .badge{font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:99px;text-transform:uppercase;}
    .badge.ROUGE{background:var(--rouge-bg);color:var(--rouge);}
    .badge.ORANGE{background:var(--orange-bg);color:var(--orange);}
    .badge.JAUNE{background:var(--jaune-bg);color:var(--jaune);}
    .badge.VERT{background:var(--vert-bg);color:var(--vert);}
    .badge-st{font-size:.6rem;font-weight:600;padding:2px 7px;border-radius:99px;}
    .badge-st.EN_ATTENTE{background:#eff6ff;color:#1d4ed8;}
    .badge-st.APPELE{background:#fefce8;color:#854d0e;}
    .badge-st.EN_CONSULTATION{background:var(--vert-bg);color:var(--vert);}
    .badge-st.ORIENTE{background:#f3f4f6;color:#374151;}
    .p-actions{display:flex;gap:6px;padding:0 12px 10px 18px;flex-wrap:wrap;}
    .btn-sm{border-radius:7px;padding:5px 12px;font-size:.78rem;font-weight:600;cursor:pointer;transition:all .15s;border:1.5px solid;}
    .btn-app{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
    .btn-con{background:var(--vert-bg);color:var(--vert);border-color:#bbf7d0;}
    .btn-dos{background:#f5f3ff;color:var(--med);border-color:#ddd6fe;}

    /* DOSSIER */
    .sec{font-family:'Syne',sans-serif;font-size:.77rem;font-weight:700;color:var(--med);text-transform:uppercase;letter-spacing:.6px;margin:16px 0 9px;padding-bottom:5px;border-bottom:2px solid #ede9fe;}
    .vitaux-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
    .vbox{background:#f8fafc;border:1.5px solid var(--bord);border-radius:10px;padding:10px 12px;text-align:center;}
    .vbox .vl{font-size:.64rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;}
    .vbox .vv{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:800;color:var(--texte);}
    .vbox .vr{font-size:.62rem;color:var(--gris);margin-top:3px;}
    .vbox.al{border-color:var(--rouge);background:var(--rouge-bg);}  .vbox.al .vv{color:var(--rouge);}
    .vbox.wn{border-color:var(--orange);background:var(--orange-bg);} .vbox.wn .vv{color:var(--orange);}
    .vbox.ok{border-color:var(--vert);background:var(--vert-bg);}    .vbox.ok .vv{color:var(--vert);}
    .tags{display:flex;flex-wrap:wrap;gap:6px;}
    .tag-d{background:var(--rouge-bg);color:var(--rouge);border:1px solid rgba(220,38,38,.25);font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:99px;}
    .tag-o{background:var(--vert-bg);color:var(--vert);border:1px solid rgba(22,163,74,.25);font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:99px;}
    .tag-i{background:#f1f5f9;color:var(--gris);border:1px solid var(--bord);font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:99px;}
    .g2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .full{grid-column:1/-1;}
    .field label{display:block;font-size:.67rem;font-weight:600;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
    .field input,.field select,.field textarea{width:100%;padding:9px 11px;border:1.5px solid var(--bord);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--texte);background:#fff;outline:none;transition:border-color .2s;}
    .field input:focus,.field select:focus,.field textarea:focus{border-color:var(--med);box-shadow:0 0 0 3px rgba(124,58,237,.1);}
    .field textarea{resize:vertical;min-height:80px;}
    .btn-terminer{width:100%;background:linear-gradient(135deg,var(--med),#6d28d9);color:#fff;border:none;border-radius:10px;padding:13px;font-family:'Syne',sans-serif;font-weight:800;font-size:.95rem;cursor:pointer;transition:all .2s;margin-top:14px;box-shadow:0 4px 16px rgba(124,58,237,.35);}
    .btn-terminer:hover{transform:translateY(-1px);}
    .btn-imprimer{width:100%;background:linear-gradient(135deg,#0f4c81,#1a6bc4);color:#fff;border:none;border-radius:10px;padding:12px;font-family:'Syne',sans-serif;font-weight:800;font-size:.93rem;cursor:pointer;transition:all .2s;margin-top:10px;box-shadow:0 4px 14px rgba(15,76,129,.3);display:flex;align-items:center;justify-content:center;gap:8px;}
    .btn-imprimer:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(15,76,129,.4);}

    /* OVERLAY FICHE */
    .print-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:300;align-items:center;justify-content:center;padding:20px;overflow-y:auto;}
    .print-overlay.open{display:flex;}
    .fiche-print-zone{background:#fff;border-radius:16px;width:700px;box-shadow:0 24px 60px rgba(0,0,0,.3);overflow:hidden;}
    .fiche-entete{background:linear-gradient(135deg,#0f4c81,#1a6bc4);padding:20px 28px;display:flex;justify-content:space-between;align-items:center;}
    .fiche-entete-left h2{font-family:'Syne',sans-serif;color:#fff;font-size:1.1rem;font-weight:800;margin:0;}
    .fiche-entete-left p{color:rgba(255,255,255,.7);font-size:.78rem;margin-top:3px;}
    .fiche-entete-right{text-align:right;}
    .fiche-entete-right .fiche-date{color:#fff;font-size:.82rem;font-weight:600;}
    .fiche-entete-right .fiche-id{color:rgba(255,255,255,.6);font-size:.72rem;margin-top:2px;}
    .fiche-body{padding:22px 28px;}
    .fiche-sec{font-family:'Syne',sans-serif;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#0f4c81;border-bottom:2px solid #dbeafe;padding-bottom:5px;margin:16px 0 10px;}
    .fiche-sec:first-child{margin-top:0;}
    .fiche-grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
    .fiche-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
    .fiche-cell{background:#f8fafc;border-radius:8px;padding:9px 12px;}
    .fiche-cell .fc-lbl{font-size:.63rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;}
    .fiche-cell .fc-val{font-size:.88rem;font-weight:600;color:var(--texte);}
    .fiche-cell.al{background:var(--rouge-bg);border:1px solid rgba(220,38,38,.2);}
    .fiche-cell.al .fc-val{color:var(--rouge);}
    .fiche-cell.wn{background:var(--orange-bg);border:1px solid rgba(234,88,12,.2);}
    .fiche-cell.wn .fc-val{color:var(--orange);}
    .fiche-cell.ok{background:var(--vert-bg);border:1px solid rgba(22,163,74,.2);}
    .fiche-cell.ok .fc-val{color:var(--vert);}
    .fiche-notes-box{background:#f8fafc;border-radius:8px;padding:12px;font-size:.87rem;color:var(--texte);min-height:60px;white-space:pre-wrap;line-height:1.6;}
    .fiche-orientation-badge{display:inline-flex;align-items:center;gap:7px;background:#ede9fe;color:#6d28d9;font-weight:700;font-size:.88rem;padding:8px 18px;border-radius:99px;}
    .fiche-priorite{display:inline-block;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:99px;text-transform:uppercase;margin-left:8px;}
    .fiche-priorite.ROUGE{background:var(--rouge-bg);color:var(--rouge);}
    .fiche-priorite.ORANGE{background:var(--orange-bg);color:var(--orange);}
    .fiche-priorite.JAUNE{background:var(--jaune-bg);color:var(--jaune);}
    .fiche-priorite.VERT{background:var(--vert-bg);color:var(--vert);}
    .fiche-footer-strip{background:#f8fafc;border-top:1px solid var(--bord);padding:12px 28px;display:flex;justify-content:space-between;align-items:center;}
    .fiche-footer-strip p{font-size:.72rem;color:var(--gris);}
    .fiche-signature{border-top:1px dashed #cbd5e1;margin-top:6px;padding-top:4px;width:160px;text-align:center;font-size:.68rem;color:var(--gris);}
    .print-actions{display:flex;gap:10px;padding:16px 28px;border-top:1px solid var(--bord);justify-content:flex-end;background:#fff;}
    .btn-close-p{background:#f1f5f9;color:var(--gris);border:none;border-radius:9px;padding:10px 20px;font-weight:600;font-size:.85rem;cursor:pointer;}
    .btn-do-print{background:#0f4c81;color:#fff;border:none;border-radius:9px;padding:10px 22px;font-family:'Syne',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;display:flex;align-items:center;gap:7px;}
    .btn-do-print:hover{background:#1a6bc4;}

    /* IMPRESSION */
    @media print{
      body>*{display:none!important;}
      .print-overlay{display:block!important;position:static!important;background:none!important;padding:0!important;}
      .fiche-print-zone{border-radius:0!important;box-shadow:none!important;width:100%!important;}
      .fiche-entete{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
      .print-actions{display:none!important;}
      .btn-close-p,.btn-do-print{display:none!important;}
    }
    .notif-row{display:flex;gap:8px;margin-top:10px;}
    .notif-row input{flex:1;padding:9px 12px;border:1.5px solid var(--bord);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.87rem;outline:none;}
    .notif-row input:focus{border-color:var(--med);}
    .btn-notif{background:var(--med);color:#fff;border:none;border-radius:9px;padding:9px 16px;font-weight:600;font-size:.83rem;cursor:pointer;white-space:nowrap;}
    .placeholder{text-align:center;padding:50px 20px;color:var(--gris);}
    .placeholder .ico{font-size:3rem;margin-bottom:12px;}
    @media(max-width:960px){.layout{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<div class="topbar">
  <div class="t-left">
    <span style="font-size:1.3rem;">🏥</span>
    <span class="t-title">HealthQueue</span>
    <span class="t-role">🩺 Médecin</span>
    <?php if($user['salle']): ?><span style="background:rgba(255,255,255,.15);color:#fff;font-size:.72rem;padding:3px 10px;border-radius:99px;"><?= htmlspecialchars($user['salle']) ?></span><?php endif; ?>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <span class="t-user">👤 <?= htmlspecialchars($user['nom'] ?: 'Médecin') ?></span>
    <a href="../logout.php" class="btn-out">Déconnexion</a>
  </div>
</div>

<?php if($message): ?><div class="alert">🏥 <?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="layout">

  <!-- COLONNE GAUCHE -->
  <div>

    <!-- CAS CRITIQUES : SÉPARÉS, pas dans la file normale -->
    <?php if(!empty($cas_critiques)): ?>
    <div class="critique-section">
      <h3>🚨 Cas Critiques — Prise en charge immédiate</h3>
      <?php foreach($cas_critiques as $c): ?>
        <div class="crit-item">
          <div>
            <div class="crit-nom"><?= htmlspecialchars($c['prenom'].' '.$c['nom']) ?></div>
            <div class="crit-info">
              <?= $c['age'] ?> ans • <?= date('H:i',strtotime($c['date_arrivee'])) ?>
              • <?= htmlspecialchars(mb_substr($c['motif_consultation'],0,45)) ?>…
            </div>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="terminer"/>
            <input type="hidden" name="id" value="<?= $c['id'] ?>"/>
            <input type="hidden" name="orientation" value="URGENCES"/>
            <input type="hidden" name="notes_medecin" value="Cas critique — pris en charge par le service d'urgence."/>
            <button type="submit" class="btn-crit-ok"
                    onclick="return confirm('Confirmer la prise en charge aux urgences ?')">
              ✅ Pris en charge
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FILE NORMALE (sans cas critiques) -->
    <div class="card">
      <div class="card-title">
        👥 File d'attente normale
        <span style="font-size:.78rem;color:var(--gris);font-weight:400;"><?= count($patients) ?> patient(s)</span>
      </div>
      <div class="p-list">
        <?php if(empty($patients)): ?>
          <div style="text-align:center;color:var(--gris);padding:24px;font-size:.87rem;">File vide.</div>
        <?php else: ?>
          <?php foreach($patients as $p): ?>
            <div class="p-item <?= $p['priorite'] ?> <?= $p['statut']==='EN_CONSULTATION'?'en-cours':'' ?>"
                 id="item-<?= $p['id'] ?>">
              <div class="p-body" onclick="ouvrirPatient(<?= $p['id'] ?>)">
                <div class="p-top">
                  <span class="p-nom"><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></span>
                  <div class="badges">
                    <span class="badge <?= $p['priorite'] ?>"><?= $p['priorite'] ?></span>
                    <span class="badge-st <?= $p['statut'] ?>"><?= str_replace('_',' ',$p['statut']) ?></span>
                  </div>
                </div>
                <div class="p-meta">
                  <?= $p['age'] ?> ans •
                  <?php $a=intval($p['age']); echo $a<18?'👶 Enfant':($a<65?'🧑 Adulte':'👴 Senior'); ?> •
                  <?= date('H:i',strtotime($p['date_arrivee'])) ?>
                  <?php if($p['score_priorite']>0): ?> • Score <?= $p['score_priorite'] ?>/100<?php endif; ?>
                </div>
                <div class="p-motif">« <?= htmlspecialchars(mb_substr($p['motif_consultation'],0,55)) ?>… »</div>
              </div>
              <div class="p-actions">
                <?php if($p['statut']==='EN_ATTENTE'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="appeler"/>
                    <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
                    <button type="submit" class="btn-sm btn-app">📢 Appeler</button>
                  </form>
                <?php endif; ?>
                <?php if(in_array($p['statut'],['EN_ATTENTE','APPELE'])): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="consulter"/>
                    <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
                    <button type="submit" class="btn-sm btn-con">🩺 Consulter</button>
                  </form>
                <?php endif; ?>
                <button class="btn-sm btn-dos" onclick="ouvrirPatient(<?= $p['id'] ?>)">👁️ Dossier</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- COLONNE DROITE -->
  <div>

    <div class="card placeholder" id="placeholder">
      <div class="ico">🩺</div>
      <p style="font-size:.88rem;line-height:1.7;">Sélectionnez un patient<br>pour voir son dossier complet<br>avec les signes vitaux de l'infirmière.</p>
    </div>

    <div id="dossierPanel" style="display:none;">

      <!-- En-tête -->
      <div class="card">
        <div class="card-title">
          <span id="d_titre">📋 Dossier</span>
          <div id="d_badges" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:.85rem;margin-bottom:12px;">
          <div><div style="color:var(--gris);font-size:.67rem;text-transform:uppercase;margin-bottom:3px;">Âge / Tranche</div><strong id="d_age">—</strong></div>
          <div><div style="color:var(--gris);font-size:.67rem;text-transform:uppercase;margin-bottom:3px;">Sexe</div><strong id="d_sexe">—</strong></div>
          <div><div style="color:var(--gris);font-size:.67rem;text-transform:uppercase;margin-bottom:3px;">Arrivée</div><strong id="d_arrivee">—</strong></div>
        </div>
        <div style="background:#f8fafc;border-radius:9px;padding:10px 14px;">
          <div style="color:var(--gris);font-size:.67rem;text-transform:uppercase;margin-bottom:4px;">Motif</div>
          <div id="d_motif" style="font-size:.87rem;font-style:italic;"></div>
        </div>
      </div>

      <!-- SIGNES VITAUX -->
      <div class="card">
        <div class="card-title">❤️ Bilan infirmière — Signes vitaux</div>
        <div class="vitaux-grid" id="d_vitaux"></div>
        <div class="sec">⚠️ Symptômes clés</div>
        <div class="tags" id="d_symptomes"></div>
        <div class="sec">🏥 Terrain</div>
        <div class="tags" id="d_terrain"></div>
      </div>

      <!-- FORMULAIRE CONSULTATION -->
      <div class="card" id="consultForm" style="display:none;">
        <div class="card-title" id="consultTitle">🩺 Consultation en cours</div>
        <form method="POST" id="formTerminer">
          <input type="hidden" name="action" value="terminer"/>
          <input type="hidden" name="id" id="c_id"/>
          <div class="sec">📝 Notes du médecin</div>
          <div class="field">
            <label>Diagnostic et observations</label>
            <textarea name="notes_medecin" id="c_notes" placeholder="Diagnostic, observations, prescriptions…"></textarea>
          </div>
          <div class="sec">🔀 Orientation</div>
          <div class="g2">
            <div class="field">
              <label>Orientation</label>
              <select name="orientation" id="c_orientation" onchange="toggleSpe(this.value)">
                <option value="">— Choisir —</option>
                <option value="PHARMACIE">💊 Pharmacie</option>
                <option value="LABORATOIRE">🧪 Laboratoire</option>
                <option value="RADIOLOGIE">🔬 Radiologie</option>
                <option value="SPECIALISTE">👨‍⚕️ Spécialiste</option>
                <option value="HOSPITALISATION">🏥 Hospitalisation</option>
                <option value="DOMICILE">🏠 Retour domicile</option>
                <option value="URGENCES">🚨 Urgences</option>
              </select>
            </div>
            <div class="field" id="speField" style="display:none;">
              <label>Spécialité</label>
              <input type="text" name="specialite_orientation" placeholder="Ex : Cardiologue…"/>
            </div>
          </div>
          <button type="submit" class="btn-terminer" id="btnTerminer" onclick="return confirm('Terminer la consultation ?')">
            ✅ Terminer et orienter le patient
          </button>
        </form>
        <div id="secMessage">
          <div class="sec">📨 Message à la secrétaire</div>
          <form method="POST">
            <input type="hidden" name="action" value="notifier"/>
            <input type="hidden" name="id" id="c_id_notif"/>
            <div class="notif-row">
              <input type="text" name="message_notif" placeholder="Ex : Préparer le dossier…"/>
              <button type="submit" class="btn-notif">📨 Envoyer</button>
            </div>
          </form>
        </div>
      </div>

      <!-- BOUTON IMPRIMER — card indépendante, visible si ORIENTE -->
      <div class="card" id="imprimerPanel" style="display:none;">
        <button class="btn-imprimer" onclick="ouvrirFiche()">
          🖨️ Imprimer la fiche de consultation
        </button>
      </div>

      <!-- ACTIONS si pas encore en consultation -->
      <div class="card" id="actionsPanel" style="display:none;">
        <div class="card-title">⚡ Actions</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <form method="POST" id="fAppeler" style="display:none;">
            <input type="hidden" name="action" value="appeler"/>
            <input type="hidden" name="id" id="a_app"/>
            <button type="submit" class="btn-sm btn-app" style="padding:10px 20px;font-size:.88rem;">📢 Appeler</button>
          </form>
          <form method="POST">
            <input type="hidden" name="action" value="consulter"/>
            <input type="hidden" name="id" id="a_con"/>
            <button type="submit" class="btn-sm btn-con" style="padding:10px 20px;font-size:.88rem;">🩺 Démarrer la consultation</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ══ FICHE D'IMPRESSION ══ -->
<div class="print-overlay" id="printOverlay">
  <div class="fiche-print-zone" id="fichePrintZone">

    <!-- EN-TÊTE -->
    <div class="fiche-entete">
      <div class="fiche-entete-left">
        <h2>🏥 HealthQueue — Fiche de Consultation</h2>
        <p id="f_medecin">Centre de Santé</p>
      </div>
      <div class="fiche-entete-right">
        <div class="fiche-date" id="f_date">—</div>
        <div class="fiche-id" id="f_id">—</div>
      </div>
    </div>

    <div class="fiche-body">

      <!-- PATIENT -->
      <div class="fiche-sec">👤 Informations du patient</div>
      <div class="fiche-grid2">
        <div class="fiche-cell">
          <div class="fc-lbl">Nom complet</div>
          <div class="fc-val" id="f_nom">—</div>
        </div>
        <div class="fiche-cell">
          <div class="fc-lbl">Âge / Sexe</div>
          <div class="fc-val" id="f_age_sexe">—</div>
        </div>
        <div class="fiche-cell">
          <div class="fc-lbl">Heure d'arrivée</div>
          <div class="fc-val" id="f_arrivee">—</div>
        </div>
      </div>
      <div class="fiche-cell" style="margin-top:8px;">
        <div class="fc-lbl">Motif de consultation</div>
        <div class="fc-val" id="f_motif">—</div>
      </div>

      <!-- SIGNES VITAUX -->
      <div class="fiche-sec">❤️ Signes vitaux (bilan infirmière)</div>
      <div class="fiche-grid3" id="f_vitaux"></div>

      <!-- DIAGNOSTIC -->
      <div class="fiche-sec">📝 Diagnostic et notes du médecin</div>
      <div class="fiche-notes-box" id="f_notes">—</div>

      <!-- ORIENTATION -->
      <div class="fiche-sec">🔀 Orientation</div>
      <div id="f_orientation_wrap">
        <span class="fiche-orientation-badge" id="f_orientation">—</span>
      </div>

    </div>

    <!-- FOOTER -->
    <div class="fiche-footer-strip">
      <div>
        <p>HealthQueue — Système de gestion de file d'attente médicale</p>
        <p style="margin-top:2px;">Document généré le <span id="f_now">—</span></p>
      </div>
      <div>
        <div class="fiche-signature">Signature du médecin</div>
      </div>
    </div>

    <!-- ACTIONS (masquées à l'impression) -->
    <div class="print-actions">
      <button class="btn-close-p" onclick="fermerFiche()">✖ Fermer</button>
      <button class="btn-do-print" onclick="window.print()">🖨️ Imprimer</button>
    </div>

  </div>
</div>

<script>
var PATIENTS = <?= $patientsJson ?>;
var patientActif = null;

function ouvrirPatient(id) {
  var p = null;
  for(var i=0; i<PATIENTS.length; i++) {
    if(parseInt(PATIENTS[i].id) === parseInt(id)) { p = PATIENTS[i]; break; }
  }
  if(!p) return;
  patientActif = p;

  document.querySelectorAll('.p-item').forEach(function(el){ el.classList.remove('actif'); });
  var item = document.getElementById('item-'+p.id);
  if(item) item.classList.add('actif');

  document.getElementById('d_titre').innerHTML = '📋 <strong>'+p.prenom+' '+p.nom+'</strong>';
  var age = parseInt(p.age);
  var tranche = age < 18 ? '👶 Enfant' : age < 65 ? '🧑 Adulte' : '👴 Senior';
  document.getElementById('d_age').textContent     = p.age+' ans — '+tranche;
  document.getElementById('d_sexe').textContent    = p.sexe === 'M' ? '👨 Homme' : '👩 Femme';
  document.getElementById('d_arrivee').textContent = p.date_arrivee ? p.date_arrivee.substring(11,16) : '—';
  document.getElementById('d_motif').textContent   = p.motif_consultation || '—';

  var bH = '<span class="badge '+p.priorite+'">'+p.priorite+'</span>';
  if(p.score_priorite > 0) bH += '<span style="background:#f1f5f9;color:var(--gris);font-size:.6rem;font-weight:600;padding:2px 8px;border-radius:99px;">Score '+p.score_priorite+'/100</span>';
  document.getElementById('d_badges').innerHTML = bH;

  var spo2 = p.saturation_oxygene    !== null && p.saturation_oxygene    !== '' ? parseInt(p.saturation_oxygene)    : null;
  var fc   = p.frequence_cardiaque   !== null && p.frequence_cardiaque   !== '' ? parseInt(p.frequence_cardiaque)   : null;
  var fr   = p.frequence_respiratoire!== null && p.frequence_respiratoire!== '' ? parseInt(p.frequence_respiratoire): null;
  var temp = p.temperature           !== null && p.temperature           !== '' ? parseFloat(p.temperature)         : null;
  var imc  = p.imc                   !== null && p.imc                   !== '' ? parseFloat(p.imc)                 : null;
  var ta   = p.tension_arterielle    || null;

  function nSpo2(v){ if(v===null)return''; return v<90?'al':v<94?'wn':v<96?'':'ok'; }
  function nFC(v)  { if(v===null)return''; return v>130||v<40?'al':v>110||v<50?'wn':'ok'; }
  function nFR(v)  { if(v===null)return''; return v>30||v<8?'al':v>24||v<10?'wn':'ok'; }
  function nTemp(v){ if(v===null)return''; return v>=40.5||v<=35?'al':v>=39.5?'wn':'ok'; }
  function nIMC(v) { if(v===null)return''; return v>=40||v<14?'al':v>=35||v<16?'wn':'ok'; }

  var vitaux = [
    {l:'SpO₂',            v:spo2!==null?spo2+'%':'N/R',        n:nSpo2(spo2)},
    {l:'F. Cardiaque',    v:fc!==null?fc+' bpm':'N/R',         n:nFC(fc)},
    {l:'F. Respiratoire', v:fr!==null?fr+' /min':'N/R',        n:nFR(fr)},
    {l:'Température',     v:temp!==null?temp+'°C':'N/R',        n:nTemp(temp)},
    {l:'Tension',         v:ta||'N/R',                          n:''},
    {l:'IMC',             v:imc!==null?imc+' kg/m²':'N/R',     n:nIMC(imc)},
  ];
  var vH = '';
  vitaux.forEach(function(v){
    vH += '<div class="vbox '+v.n+'">';
    vH += '<div class="vl">'+v.l+'</div>';
    vH += '<div class="vv">'+v.v+'</div>';
    vH += '</div>';
  });
  document.getElementById('d_vitaux').innerHTML = vH;

  var syms = [
    {k:'difficulte_respiratoire', l:'😮‍💨 Diff. respiratoire'},
    {k:'douleur_thoracique',      l:'💔 Douleur thoracique'},
    {k:'perte_connaissance',      l:'😵 Perte de connaissance'},
    {k:'saignement_abondant',     l:'🩸 Saignement abondant'},
    {k:'troubles_neurologiques',  l:'🧠 Troubles neurologiques'},
    {k:'douleur_intense_brutale', l:'😣 Douleur intense/brutale'},
    {k:'fievre_elevee',           l:'🌡️ Fièvre élevée'},
  ];
  var sH = ''; var hasS = false;
  syms.forEach(function(s){ if(parseInt(p[s.k])===1){ sH+='<span class="tag-d">'+s.l+'</span>'; hasS=true; } });
  if(!hasS) sH = '<span class="tag-o">✅ RAS — Aucun symptôme clé</span>';
  document.getElementById('d_symptomes').innerHTML = sH;

  var terr = [
    {k:'hta',l:'🫀 HTA'},{k:'diabete',l:'🩸 Diabète'},
    {k:'asthme',l:'🫁 Asthme'},{k:'grossesse',l:'🤰 Grossesse'},
    {k:'immunodepression',l:'🛡️ Immunodépression'}
  ];
  var tH = '';
  terr.forEach(function(t){ if(parseInt(p[t.k])===1) tH+='<span class="tag-i">'+t.l+'</span>'; });
  if(p.maladies_chroniques && p.maladies_chroniques.trim())
    tH += '<span class="tag-i">📋 '+p.maladies_chroniques+'</span>';
  if(!tH) tH = '<span style="font-size:.82rem;color:var(--gris);">Aucune pathologie chronique renseignée</span>';
  document.getElementById('d_terrain').innerHTML = tH;

  var enC = p.statut === 'EN_CONSULTATION';
  var ori = p.statut === 'ORIENTE';
  var enA = p.statut === 'EN_ATTENTE' || p.statut === 'APPELE';

  // Dossier toujours visible
  document.getElementById('placeholder').style.display  = 'none';
  document.getElementById('dossierPanel').style.display = '';

  // Panel consultation : EN_CONSULTATION ou ORIENTE (lecture seule)
  document.getElementById('consultForm').style.display   = (enC || ori) ? '' : 'none';

  // Panel actions : EN_ATTENTE ou APPELE
  document.getElementById('actionsPanel').style.display  = enA ? '' : 'none';

  // Panel imprimer : ORIENTE seulement — card indépendante
  document.getElementById('imprimerPanel').style.display = ori ? '' : 'none';

  if (enC || ori) {
    document.getElementById('c_id').value       = p.id;
    document.getElementById('c_id_notif').value = p.id;
    document.getElementById('c_notes').value    = p.notes_medecin || '';
    document.getElementById('c_notes').disabled = ori;

    var selOri = document.getElementById('c_orientation');
    if (selOri) { selOri.value = p.orientation || ''; selOri.disabled = ori; }

    // Titre et bouton selon statut
    document.getElementById('consultTitle').textContent = ori ? '✅ Consultation terminée' : '🩺 Consultation en cours';
    document.getElementById('btnTerminer').style.display = ori ? 'none' : '';
    document.getElementById('secMessage').style.display  = ori ? 'none' : '';
  }

  if (enA) {
    document.getElementById('a_app').value = p.id;
    document.getElementById('a_con').value = p.id;
    var fApp = document.getElementById('fAppeler');
    if (fApp) fApp.style.display = p.statut === 'EN_ATTENTE' ? '' : 'none';
  }
}

function ouvrirFiche() {
  var p = patientActif;
  if(!p) return;

  var now = new Date();
  var dateStr = now.toLocaleDateString('fr-FR',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  var heureStr = now.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});

  document.getElementById('f_date').textContent    = dateStr;
  document.getElementById('f_now').textContent     = dateStr+' à '+heureStr;
  document.getElementById('f_id').textContent      = 'Dossier #'+p.id;
  document.getElementById('f_medecin').textContent = '<?= htmlspecialchars($user['nom'] ?: 'Médecin') ?>'
    + (<?= json_encode($user['salle'] ?: '') ?> ? ' — Salle '+<?= json_encode($user['salle'] ?: '') ?> : '');

  document.getElementById('f_nom').textContent      = p.prenom+' '+p.nom;
  var sexeStr = p.sexe === 'M' ? 'Homme' : 'Femme';
  var age = parseInt(p.age);
  var tranche = age < 18 ? 'Enfant' : age < 65 ? 'Adulte' : 'Senior';
  document.getElementById('f_age_sexe').textContent  = p.age+' ans — '+sexeStr+' ('+tranche+')';
  document.getElementById('f_arrivee').textContent   = p.date_arrivee ? p.date_arrivee.substring(11,16) : '—';
  document.getElementById('f_motif').textContent     = p.motif_consultation || '—';

  // Signes vitaux dans la fiche
  function cls(n){ return n==='al'?'al':n==='wn'?'wn':n==='ok'?'ok':''; }
  function nSpo2(v){ if(v===null)return''; return v<90?'al':v<94?'wn':'ok'; }
  function nFC(v)  { if(v===null)return''; return v>130||v<40?'al':v>110||v<50?'wn':'ok'; }
  function nFR(v)  { if(v===null)return''; return v>30||v<8?'al':v>24||v<10?'wn':'ok'; }
  function nTemp(v){ if(v===null)return''; return v>=40.5||v<=35?'al':v>=39.5?'wn':'ok'; }
  function nIMC(v) { if(v===null)return''; return v>=40||v<14?'al':v>=35||v<16?'wn':'ok'; }

  var spo2 = p.saturation_oxygene    ? parseInt(p.saturation_oxygene)    : null;
  var fc   = p.frequence_cardiaque   ? parseInt(p.frequence_cardiaque)   : null;
  var fr   = p.frequence_respiratoire? parseInt(p.frequence_respiratoire): null;
  var temp = p.temperature           ? parseFloat(p.temperature)         : null;
  var imc  = p.imc                   ? parseFloat(p.imc)                 : null;
  var ta   = p.tension_arterielle    || null;

  var vx = [
    {l:'SpO₂',            v:spo2!==null?spo2+'%':'Non renseigné',    r:'Norme ≥ 95%',       n:nSpo2(spo2)},
    {l:'F. Cardiaque',    v:fc!==null?fc+' bpm':'Non renseigné',     r:'Norme 60–100 bpm',  n:nFC(fc)},
    {l:'F. Respiratoire', v:fr!==null?fr+' /min':'Non renseigné',    r:'Norme 12–20/min',   n:nFR(fr)},
    {l:'Température',     v:temp!==null?temp+'°C':'Non renseigné',    r:'Norme 36.5–37.5°C', n:nTemp(temp)},
    {l:'Tension',         v:ta||'Non renseigné',                      r:'mmHg',              n:''},
    {l:'IMC',             v:imc!==null?imc+' kg/m²':'Non renseigné', r:'Norme 18.5–25',     n:nIMC(imc)},
  ];
  var fvH = '';
  vx.forEach(function(v){
    fvH += '<div class="fiche-cell '+cls(v.n)+'">';
    fvH += '<div class="fc-lbl">'+v.l+' <span style="font-weight:400;font-size:.58rem;opacity:.7;">'+v.r+'</span></div>';
    fvH += '<div class="fc-val">'+v.v+'</div>';
    fvH += '</div>';
  });
  document.getElementById('f_vitaux').innerHTML = fvH;

  // Diagnostic
  document.getElementById('f_notes').textContent = p.notes_medecin || 'Aucune note renseignée.';

  // Orientation
  var oriMap = {
    'PHARMACIE':'💊 Pharmacie','LABORATOIRE':'🧪 Laboratoire',
    'RADIOLOGIE':'🔬 Radiologie','SPECIALISTE':'👨‍⚕️ Spécialiste',
    'HOSPITALISATION':'🏥 Hospitalisation','DOMICILE':'🏠 Retour domicile',
    'URGENCES':'🚨 Urgences'
  };
  var oriTxt = p.orientation ? (oriMap[p.orientation] || p.orientation) : 'Non renseignée';
  if(p.specialite_orientation) oriTxt += ' — '+p.specialite_orientation;
  document.getElementById('f_orientation').textContent = oriTxt;

  document.getElementById('printOverlay').classList.add('open');
}

function fermerFiche() {
  document.getElementById('printOverlay').classList.remove('open');
}

// Fermer en cliquant en dehors
document.getElementById('printOverlay').addEventListener('click', function(e){
  if(e.target === this) fermerFiche();
});

function toggleSpe(v) {
  document.getElementById('speField').style.display = v === 'SPECIALISTE' ? '' : 'none';
}
</script>
</body>
</html>
