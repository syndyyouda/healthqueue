<?php
session_start();
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('INFIRMIERE');

$user = getUser();
$db   = getDB();
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'cas_critique') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $pat = $db->query("SELECT nom, prenom FROM patients WHERE id=$id")->fetch();
            $db->prepare("UPDATE patients SET cas_critique=1, priorite='ROUGE', score_priorite=100 WHERE id=?")->execute([$id]);
            $db->prepare("INSERT INTO notifications (type, message, patient_id, destinataire_role) VALUES ('CAS_CRITIQUE',?,?,'SECRETAIRE')")
               ->execute(["🚨 CAS CRITIQUE : ".$pat['prenom']." ".$pat['nom']." — Prise en charge immédiate !", $id]);
            $message = "🚨 Cas critique déclaré pour ".$pat['prenom']." ".$pat['nom'];
        }
    }

    if ($action === 'signes_vitaux') {
        $id     = intval($_POST['id'] ?? 0);
        $poids  = $_POST['poids']  !== '' ? floatval($_POST['poids'])  : null;
        $taille = $_POST['taille'] !== '' ? intval($_POST['taille'])   : null;
        $imc    = ($poids && $taille) ? round($poids / pow($taille/100, 2), 1) : null;
        $ras    = isset($_POST['ras_symptomes']);

        $data = [
            'poids'                   => $poids,
            'taille'                  => $taille,
            'imc'                     => $imc,
            'tension_arterielle'      => $_POST['tension_arterielle']     ?? null,
            'frequence_cardiaque'     => $_POST['frequence_cardiaque']    !== '' ? intval($_POST['frequence_cardiaque'])    : null,
            'frequence_respiratoire'  => $_POST['frequence_respiratoire'] !== '' ? intval($_POST['frequence_respiratoire']) : null,
            'temperature'             => $_POST['temperature']            !== '' ? floatval($_POST['temperature'])          : null,
            'saturation_oxygene'      => $_POST['saturation_oxygene']     !== '' ? intval($_POST['saturation_oxygene'])     : null,
            'difficulte_respiratoire' => !$ras && isset($_POST['difficulte_respiratoire']) ? 1 : 0,
            'douleur_thoracique'      => !$ras && isset($_POST['douleur_thoracique'])      ? 1 : 0,
            'douleur_intense_brutale' => !$ras && isset($_POST['douleur_intense_brutale']) ? 1 : 0,
            'perte_connaissance'      => !$ras && isset($_POST['perte_connaissance'])      ? 1 : 0,
            'saignement_abondant'     => !$ras && isset($_POST['saignement_abondant'])     ? 1 : 0,
            'fievre_elevee'           => !$ras && isset($_POST['fievre_elevee'])           ? 1 : 0,
            'troubles_neurologiques'  => !$ras && isset($_POST['troubles_neurologiques'])  ? 1 : 0,
            'grossesse'               => isset($_POST['grossesse'])        ? 1 : 0,
            'hta'                     => isset($_POST['hta'])              ? 1 : 0,
            'diabete'                 => isset($_POST['diabete'])          ? 1 : 0,
            'asthme'                  => isset($_POST['asthme'])           ? 1 : 0,
            'immunodepression'        => isset($_POST['immunodepression']) ? 1 : 0,
            'maladies_chroniques'     => trim($_POST['maladies_chroniques'] ?? ''),
            'age'                     => intval($_POST['age'] ?? 0),
        ];

        $res      = calculerPriorite($data);
        $priorite = $res['priorite'];
        $score    = $res['score'];

        $db->prepare("UPDATE patients SET
            poids=?, taille=?, imc=?,
            tension_arterielle=?, frequence_cardiaque=?, frequence_respiratoire=?,
            temperature=?, saturation_oxygene=?,
            difficulte_respiratoire=?, douleur_thoracique=?, douleur_intense_brutale=?,
            perte_connaissance=?, saignement_abondant=?, fievre_elevee=?, troubles_neurologiques=?,
            grossesse=?, hta=?, diabete=?, asthme=?, immunodepression=?, maladies_chroniques=?,
            priorite=?, score_priorite=?
            WHERE id=?
        ")->execute([
            $data['poids'], $data['taille'], $data['imc'],
            $data['tension_arterielle'], $data['frequence_cardiaque'], $data['frequence_respiratoire'],
            $data['temperature'], $data['saturation_oxygene'],
            $data['difficulte_respiratoire'], $data['douleur_thoracique'], $data['douleur_intense_brutale'],
            $data['perte_connaissance'], $data['saignement_abondant'], $data['fievre_elevee'], $data['troubles_neurologiques'],
            $data['grossesse'], $data['hta'], $data['diabete'], $data['asthme'], $data['immunodepression'], $data['maladies_chroniques'],
            $priorite, $score, $id
        ]);

        $icones = ['ROUGE'=>'🔴','ORANGE'=>'🟠','JAUNE'=>'🟡','VERT'=>'🟢'];
        $message = "Signes vitaux enregistrés — Priorité : ".($icones[$priorite]??'')." $priorite (score $score/100)";
    }
}

$patients = $db->query("
    SELECT * FROM patients
    WHERE statut IN ('EN_ATTENTE','APPELE')
    ORDER BY FIELD(priorite,'ROUGE','ORANGE','JAUNE','VERT'), date_arrivee ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealthQueue — Infirmière</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{--rouge:#dc2626;--rouge-bg:rgba(220,38,38,.1);--orange:#ea580c;--orange-bg:rgba(234,88,12,.1);--jaune:#ca8a04;--jaune-bg:rgba(202,138,4,.1);--vert:#16a34a;--vert-bg:rgba(22,163,74,.1);--teal:#0d9488;--teal-bg:#f0fdfa;--bg:#f0f4f8;--blanc:#fff;--texte:#0f172a;--gris:#64748b;--bord:#e2e8f0;}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--texte);min-height:100vh;}
    .topbar{background:var(--teal);padding:0 24px;height:58px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 12px rgba(13,148,136,.3);position:sticky;top:0;z-index:100;}
    .t-left{display:flex;align-items:center;gap:10px;}
    .t-title{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:#fff;}
    .t-role{background:rgba(255,255,255,.2);color:#fff;font-size:.7rem;padding:3px 10px;border-radius:99px;font-weight:600;text-transform:uppercase;}
    .t-user{color:rgba(255,255,255,.85);font-size:.86rem;}
    .btn-out{background:rgba(255,255,255,.15);border:none;border-radius:8px;padding:6px 13px;color:#fff;font-size:.82rem;cursor:pointer;text-decoration:none;}
    .alert{margin:14px 24px 0;padding:11px 16px;border-radius:10px;font-size:.87rem;font-weight:500;display:flex;align-items:center;gap:9px;}
    .alert.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
    .layout{display:grid;grid-template-columns:390px 1fr;gap:18px;padding:18px 24px;max-width:1500px;margin:0 auto;}
    .card{background:var(--blanc);border-radius:14px;padding:18px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid var(--bord);}
    .card-title{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--bord);display:flex;align-items:center;justify-content:space-between;}
    .p-list{display:flex;flex-direction:column;gap:10px;max-height:calc(100vh - 180px);overflow-y:auto;}

    /* Patient card */
    .p-item{border:1.5px solid var(--bord);border-radius:12px;background:#fff;transition:all .15s;position:relative;overflow:hidden;}
    .p-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:5px;}
    .p-item.ROUGE::before{background:var(--rouge);}
    .p-item.ORANGE::before{background:var(--orange);}
    .p-item.JAUNE::before{background:var(--jaune);}
    .p-item.VERT::before{background:var(--vert);}
    .p-item.actif{border-color:var(--teal);background:var(--teal-bg);}
    .p-infos{padding:11px 13px 8px 18px;cursor:pointer;}
    .p-infos:hover{opacity:.85;}
    .p-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;gap:8px;}
    .p-nom{font-weight:700;font-size:.9rem;}
    .p-meta{font-size:.76rem;color:var(--gris);margin-bottom:3px;}
    .p-motif{font-size:.75rem;color:#475569;font-style:italic;}
    .badges{display:flex;gap:4px;align-items:center;flex-wrap:wrap;flex-shrink:0;}
    .badge{font-size:.61rem;font-weight:700;padding:2px 7px;border-radius:99px;text-transform:uppercase;}
    .badge.ROUGE{background:var(--rouge-bg);color:var(--rouge);}
    .badge.ORANGE{background:var(--orange-bg);color:var(--orange);}
    .badge.JAUNE{background:var(--jaune-bg);color:var(--jaune);}
    .badge.VERT{background:var(--vert-bg);color:var(--vert);}
    .badge-score{background:#f1f5f9;color:var(--gris);font-size:.6rem;padding:2px 7px;border-radius:99px;}

    /* Bouton cas critique SUR le patient */
    .btn-p-crit{width:calc(100% - 20px);margin:0 10px 10px;padding:9px 14px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;border-radius:9px;font-family:'Syne',sans-serif;font-weight:800;font-size:.81rem;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 3px 10px rgba(220,38,38,.3);}
    .btn-p-crit:hover{background:#b91c1c;transform:translateY(-1px);box-shadow:0 5px 16px rgba(220,38,38,.45);}
    .btn-p-crit.done{background:linear-gradient(135deg,#7f1d1d,#991b1b);opacity:.65;cursor:default;transform:none;box-shadow:none;}

    /* Formulaire */
    .form-scroll{max-height:calc(100vh - 160px);overflow-y:auto;padding-right:4px;}
    .sec{font-family:'Syne',sans-serif;font-size:.77rem;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:.6px;margin:16px 0 9px;padding-bottom:5px;border-bottom:2px solid #ccfbf1;}
    .g2{display:grid;grid-template-columns:1fr 1fr;gap:9px;}
    .full{grid-column:1/-1;}
    .field label{display:block;font-size:.67rem;font-weight:600;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
    .field input,.field select,.field textarea{width:100%;padding:8px 10px;border:1.5px solid var(--bord);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.87rem;color:var(--texte);background:#fff;outline:none;transition:border-color .2s;}
    .field input:focus,.field textarea:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,148,136,.1);}
    .field textarea{resize:vertical;min-height:68px;}
    .unite{font-size:.67rem;color:var(--gris);margin-top:2px;}
    .imc-box{background:var(--teal-bg);border:1.5px solid #99f6e4;border-radius:9px;padding:9px 13px;display:flex;justify-content:space-between;align-items:center;}
    .imc-val{font-family:'Syne',sans-serif;font-size:1.25rem;font-weight:800;color:var(--teal);}
    .imc-cat{font-size:.76rem;color:var(--gris);text-align:right;}
    .ck-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
    .ck{display:flex;align-items:center;gap:7px;background:#f8fafc;border:1.5px solid var(--bord);border-radius:8px;padding:8px 10px;cursor:pointer;font-size:.81rem;font-weight:500;transition:all .14s;user-select:none;}
    .ck input[type=checkbox]{width:15px;height:15px;accent-color:var(--teal);cursor:pointer;flex-shrink:0;}
    .ck.danger{border-color:rgba(220,38,38,.22);}
    .ck.danger:has(input:checked){background:var(--rouge-bg);border-color:var(--rouge);color:var(--rouge);}
    .ck:not(.danger):has(input:checked){background:var(--teal-bg);border-color:var(--teal);}
    .ras{display:flex;align-items:center;gap:9px;background:#f0fdf4;border:2px solid #86efac;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600;font-size:.87rem;margin-top:8px;user-select:none;}
    .ras:has(input:checked){background:#dcfce7;border-color:var(--vert);}
    .ras input{width:16px;height:16px;accent-color:var(--vert);cursor:pointer;}
    .age-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px;}
    .age-btn{border:2px solid var(--bord);border-radius:10px;padding:10px 6px;text-align:center;cursor:pointer;transition:all .16s;background:#f8fafc;}
    .age-btn .ico{font-size:1.3rem;display:block;margin-bottom:3px;}
    .age-btn .lbl{font-size:.79rem;font-weight:600;}
    .age-btn .sub{font-size:.65rem;opacity:.6;}
    .age-btn:hover{border-color:#94a3b8;}
    .age-btn.selected{border-color:var(--teal);background:var(--teal-bg);color:var(--teal);}
    .btn-save{width:100%;background:var(--teal);color:#fff;border:none;border-radius:10px;padding:12px;font-family:'Syne',sans-serif;font-weight:700;font-size:.93rem;cursor:pointer;transition:all .2s;margin-top:14px;box-shadow:0 4px 14px rgba(13,148,136,.3);}
    .btn-save:hover{background:#0f766e;transform:translateY(-1px);}
    .placeholder{text-align:center;padding:60px 20px;color:var(--gris);}
    .placeholder .ico{font-size:3rem;margin-bottom:12px;}
    .placeholder p{font-size:.88rem;line-height:1.7;}
    @media(max-width:960px){.layout{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<div class="topbar">
  <div class="t-left">
    <span style="font-size:1.3rem;">🏥</span>
    <span class="t-title">HealthQueue</span>
    <span class="t-role">💉 Infirmière</span>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <span class="t-user">👤 <?= htmlspecialchars($user['nom'] ?: 'Infirmière') ?></span>
    <a href="../logout.php" class="btn-out">Déconnexion</a>
  </div>
</div>

<?php if($message): ?><div class="alert ok">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>

<div class="layout">

  <!-- COLONNE GAUCHE -->
  <div>
    <div class="card">
      <div class="card-title">
        👥 Patients en attente
        <span style="font-size:.78rem;color:var(--gris);font-weight:400;"><?= count($patients) ?> patient(s)</span>
      </div>
      <div class="p-list">
        <?php if(empty($patients)): ?>
          <div style="text-align:center;color:var(--gris);padding:24px;">Aucun patient en attente.</div>
        <?php else: ?>
          <?php foreach($patients as $p): ?>
            <div class="p-item <?= $p['priorite'] ?>" id="item-<?= $p['id'] ?>">

              <!-- Zone cliquable pour ouvrir le formulaire -->
              <div class="p-infos" onclick="selPatient(<?= htmlspecialchars(json_encode($p)) ?>)">
                <div class="p-top">
                  <span class="p-nom"><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></span>
                  <div class="badges">
                    <?php if($p['cas_critique']): ?>
                      <span class="badge" style="background:#fee2e2;color:var(--rouge);">🚨 Critique</span>
                    <?php endif; ?>
                    <span class="badge <?= $p['priorite'] ?>"><?= $p['priorite'] ?></span>
                    <?php if($p['score_priorite'] > 0): ?>
                      <span class="badge-score"><?= $p['score_priorite'] ?>/100</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="p-meta">
                  <?= $p['age'] ?> ans •
                  <?= $p['sexe']==='M'?'Homme':'Femme' ?> •
                  <?php $a=intval($p['age']); echo $a<18?'👶 Enfant':($a<65?'🧑 Adulte':'👴 Senior'); ?> •
                  <?= date('H:i',strtotime($p['date_arrivee'])) ?>
                </div>
                <div class="p-motif">« <?= htmlspecialchars(mb_substr($p['motif_consultation'],0,60)) ?>… »</div>
              </div>

              <!-- BOUTON CAS CRITIQUE sur chaque patient -->
              <form method="POST" onsubmit="return confirm('Déclarer CAS CRITIQUE pour <?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?> ?\n\nPriorité ROUGE immédiate + notification secrétaire.')">
                <input type="hidden" name="action" value="cas_critique"/>
                <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
                <button type="submit"
                        class="btn-p-crit <?= $p['cas_critique'] ? 'done' : '' ?>"
                        <?= $p['cas_critique'] ? 'disabled' : '' ?>>
                  <?= $p['cas_critique'] ? '✅ Déjà en Cas Critique' : '🚨 Déclarer CAS CRITIQUE' ?>
                </button>
              </form>

            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- COLONNE DROITE -->
  <div>
    <div class="card">

      <div class="placeholder" id="placeholder">
        <div class="ico">💉</div>
        <p>Cliquez sur un patient à gauche<br>pour saisir ses signes vitaux.</p>
        <p style="margin-top:8px;font-size:.77rem;color:#94a3b8;">
          Priorité calculée sur : signes vitaux,<br>IMC, symptômes, terrain et tranche d'âge.
        </p>
      </div>

      <div id="formVitaux" style="display:none;">
        <div class="card-title"><span id="formTitre">📋 Évaluation</span></div>
        <div class="form-scroll">
        <form method="POST" action="">
          <input type="hidden" name="action" value="signes_vitaux"/>
          <input type="hidden" name="id"  id="sv_id"/>
          <input type="hidden" name="age" id="sv_age"/>

          <div class="sec">⚖️ Anthropométrie</div>
          <div class="g2">
            <div class="field">
              <label>Poids</label>
              <input type="number" name="poids" id="sv_poids" step="0.1" min="0" max="300" placeholder="70"/>
              <div class="unite">kg</div>
            </div>
            <div class="field">
              <label>Taille</label>
              <input type="number" name="taille" id="sv_taille" min="0" max="250" placeholder="170"/>
              <div class="unite">cm</div>
            </div>
            <div class="full" id="imcBox" style="display:none;">
              <div class="imc-box">
                <div>
                  <div style="font-size:.66rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;">IMC calculé</div>
                  <div class="imc-val" id="imcVal">—</div>
                </div>
                <div class="imc-cat" id="imcCat">—</div>
              </div>
            </div>
          </div>

          <div class="sec">❤️ Signes vitaux <span style="font-size:.67rem;font-weight:400;opacity:.6;text-transform:none;letter-spacing:0;">(influent sur la priorité)</span></div>
          <div class="g2">
            <div class="field"><label>Tension artérielle</label><input type="text" name="tension_arterielle" id="sv_ta" placeholder="120/80"/><div class="unite">mmHg</div></div>
            <div class="field"><label>Fréquence cardiaque</label><input type="number" name="frequence_cardiaque" id="sv_fc" min="0" max="300" placeholder="75"/><div class="unite">bpm — normal 60–100</div></div>
            <div class="field"><label>Fréquence respiratoire</label><input type="number" name="frequence_respiratoire" id="sv_fr" min="0" max="60" placeholder="16"/><div class="unite">cycles/min — normal 12–20</div></div>
            <div class="field"><label>Température</label><input type="number" name="temperature" id="sv_temp" step="0.1" min="30" max="45" placeholder="37.0"/><div class="unite">°C — normal 36.5–37.5</div></div>
            <div class="field full"><label>Saturation O₂ (SpO2)</label><input type="number" name="saturation_oxygene" id="sv_spo2" min="0" max="100" placeholder="98"/><div class="unite">% — normal 95–100</div></div>
          </div>

          <div class="sec">👁️ Symptômes observés par l'infirmière</div>
          <div class="field">
            <label>Description libre</label>
            <textarea name="symptomes_observes" id="sv_obs" placeholder="Ex : patient agité, pâleur, sudation, confusion, difficultés à parler…"></textarea>
          </div>

          <div class="sec">⚠️ Symptômes clés <span style="font-size:.67rem;font-weight:400;opacity:.6;text-transform:none;letter-spacing:0;">(pour la priorisation)</span></div>
          <div class="ck-grid">
            <label class="ck danger"><input type="checkbox" name="difficulte_respiratoire" id="ck_dr"/> 😮‍💨 Difficulté respiratoire</label>
            <label class="ck danger"><input type="checkbox" name="douleur_thoracique"      id="ck_dt"/> 💔 Douleur thoracique</label>
            <label class="ck danger"><input type="checkbox" name="perte_connaissance"      id="ck_pc"/> 😵 Perte de connaissance</label>
            <label class="ck danger"><input type="checkbox" name="saignement_abondant"     id="ck_sa"/> 🩸 Saignement abondant</label>
            <label class="ck danger"><input type="checkbox" name="troubles_neurologiques"  id="ck_tn"/> 🧠 Troubles neurologiques</label>
            <label class="ck">       <input type="checkbox" name="douleur_intense_brutale" id="ck_dib"/> 😣 Douleur intense/brutale</label>
            <label class="ck">       <input type="checkbox" name="fievre_elevee"           id="ck_fe"/> 🌡️ Fièvre élevée</label>
          </div>
          <label class="ras">
            <input type="checkbox" name="ras_symptomes" id="ck_ras" onchange="toggleRAS(this)"/>
            ✅ RAS — Aucun symptôme clé observé
          </label>

          <div class="sec">🏥 Terrain <span style="font-size:.67rem;font-weight:400;opacity:.6;text-transform:none;letter-spacing:0;">(influe sur la priorité)</span></div>
          <div style="font-size:.67rem;font-weight:600;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">Tranche d'âge</div>
          <div class="age-grid">
            <div class="age-btn" id="age-enfant" onclick="selAge('enfant')"><span class="ico">👶</span><div class="lbl">Enfant</div><div class="sub">0–17 ans</div></div>
            <div class="age-btn" id="age-adulte" onclick="selAge('adulte')"><span class="ico">🧑</span><div class="lbl">Adulte</div><div class="sub">18–64 ans</div></div>
            <div class="age-btn" id="age-senior" onclick="selAge('senior')"><span class="ico">👴</span><div class="lbl">Personne âgée</div><div class="sub">65 ans et +</div></div>
          </div>
          <div class="ck-grid">
            <label class="ck"><input type="checkbox" name="hta"            id="ck_hta"/> 🫀 HTA</label>
            <label class="ck"><input type="checkbox" name="diabete"        id="ck_db"/>  🩸 Diabète</label>
            <label class="ck"><input type="checkbox" name="asthme"         id="ck_as"/>  🫁 Asthme</label>
            <label class="ck"><input type="checkbox" name="grossesse"      id="ck_gr"/>  🤰 Grossesse</label>
            <label class="ck full"><input type="checkbox" name="immunodepression" id="ck_im"/> 🛡️ Immunodépression</label>
          </div>
          <div class="field" style="margin-top:9px;">
            <label>Autres maladies chroniques</label>
            <input type="text" name="maladies_chroniques" id="sv_mc" placeholder="Ex : insuffisance rénale, cancer, épilepsie…"/>
          </div>

          <button type="submit" class="btn-save">💾 Enregistrer et calculer la priorité</button>
        </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function selPatient(p) {
  document.querySelectorAll('.p-item').forEach(function(i){i.classList.remove('actif');});
  document.getElementById('item-'+p.id).classList.add('actif');
  document.getElementById('sv_id').value=p.id;
  document.getElementById('sv_age').value=p.age;
  document.getElementById('formTitre').innerHTML='📋 <strong>'+p.prenom+' '+p.nom+'</strong>';

  function sv(id,v){var e=document.getElementById(id);if(e&&v!=null&&v!='')e.value=v;}
  function ck(id,v){var e=document.getElementById(id);if(e)e.checked=(v==1);}

  sv('sv_poids',p.poids); sv('sv_taille',p.taille);
  sv('sv_ta',p.tension_arterielle); sv('sv_fc',p.frequence_cardiaque);
  sv('sv_fr',p.frequence_respiratoire); sv('sv_temp',p.temperature);
  sv('sv_spo2',p.saturation_oxygene); sv('sv_mc',p.maladies_chroniques);

  ck('ck_dr',p.difficulte_respiratoire); ck('ck_dt',p.douleur_thoracique);
  ck('ck_pc',p.perte_connaissance); ck('ck_sa',p.saignement_abondant);
  ck('ck_tn',p.troubles_neurologiques); ck('ck_dib',p.douleur_intense_brutale);
  ck('ck_fe',p.fievre_elevee); ck('ck_hta',p.hta); ck('ck_db',p.diabete);
  ck('ck_as',p.asthme); ck('ck_gr',p.grossesse); ck('ck_im',p.immunodepression);

  var age=parseInt(p.age);
  selAge(age<18?'enfant':age<65?'adulte':'senior');

  document.getElementById('placeholder').style.display='none';
  document.getElementById('formVitaux').style.display='';
  calculerIMC();
}

function selAge(t){
  ['enfant','adulte','senior'].forEach(function(x){document.getElementById('age-'+x).classList.remove('selected');});
  document.getElementById('age-'+t).classList.add('selected');
}

function toggleRAS(cb){
  ['ck_dr','ck_dt','ck_pc','ck_sa','ck_tn','ck_dib','ck_fe'].forEach(function(id){
    var e=document.getElementById(id);
    if(e){e.disabled=cb.checked;if(cb.checked)e.checked=false;}
  });
}

function calculerIMC(){
  var p=parseFloat(document.getElementById('sv_poids').value);
  var t=parseFloat(document.getElementById('sv_taille').value);
  if(p>0&&t>0){
    var imc=Math.round(p/Math.pow(t/100,2)*10)/10;
    document.getElementById('imcVal').textContent=imc;
    var cat=imc<14?'🚨 Dénutrition sévère':imc<16?'⚠️ Dénutrition':imc<18.5?'Insuffisance pondérale':imc<25?'✅ Poids normal':imc<30?'Surpoids':imc<35?'Obésité modérée':imc<40?'⚠️ Obésité sévère':'🚨 Obésité morbide';
    document.getElementById('imcCat').textContent=cat;
    document.getElementById('imcBox').style.display='';
  }
}
document.getElementById('sv_poids').addEventListener('input',calculerIMC);
document.getElementById('sv_taille').addEventListener('input',calculerIMC);
</script>
</body>
</html>
