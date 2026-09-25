<?php
require_once 'config/db.php';
$db = getDB();

// Stats live
$nb_attente      = $db->query("SELECT COUNT(*) FROM patients WHERE statut='EN_ATTENTE' AND cas_critique=0")->fetchColumn();
$nb_consultation = $db->query("SELECT COUNT(*) FROM patients WHERE statut IN ('EN_CONSULTATION','APPELE')")->fetchColumn();
$nb_oriente      = $db->query("SELECT COUNT(*) FROM patients WHERE statut='ORIENTE' AND DATE(date_arrivee)=CURDATE()")->fetchColumn();
$nb_total        = $db->query("SELECT COUNT(*) FROM patients WHERE DATE(date_arrivee)=CURDATE()")->fetchColumn();

// Patient(s) appelé(s) — mis en avant
$appeles = $db->query("
    SELECT id, nom, prenom, date_arrivee
    FROM patients WHERE statut='APPELE'
    ORDER BY heure_appel DESC
")->fetchAll();

// File d'attente normale (hors critiques, hors terminés)
$file = $db->query("
    SELECT id, nom, prenom, statut, priorite, date_arrivee, motif_consultation, age, sexe
    FROM patients
    WHERE statut NOT IN ('TERMINE','ORIENTE')
    AND cas_critique = 0
    ORDER BY FIELD(statut,'EN_CONSULTATION','APPELE','EN_ATTENTE'),
             FIELD(priorite,'ROUGE','ORANGE','JAUNE','VERT'),
             date_arrivee ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HealthQueue — Salle d'attente</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --rouge:#dc2626;--rouge-bg:rgba(220,38,38,.1);
      --orange:#ea580c;--orange-bg:rgba(234,88,12,.1);
      --jaune:#ca8a04;--jaune-bg:rgba(202,138,4,.1);
      --vert:#16a34a;--vert-bg:rgba(22,163,74,.1);
      --bleu:#0f4c81;--bleu2:#1a6bc4;
      --bg:#0b1a2e;--card:rgba(255,255,255,.06);
      --bord:rgba(255,255,255,.1);--texte:#fff;--gris:rgba(255,255,255,.5);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    body{
      font-family:'DM Sans',sans-serif;
      background:var(--bg);color:var(--texte);
      min-height:100vh;
      background-image:
        radial-gradient(ellipse 70% 50% at 10% 10%, rgba(26,107,196,.2) 0%,transparent 60%),
        radial-gradient(ellipse 60% 60% at 90% 90%, rgba(220,38,38,.12) 0%,transparent 60%);
    }

    /* TOPBAR */
    .topbar{
      background:rgba(15,76,129,.6);
      backdrop-filter:blur(20px);
      border-bottom:1px solid var(--bord);
      padding:0 32px;height:70px;
      display:flex;align-items:center;justify-content:space-between;
      position:sticky;top:0;z-index:50;
    }
    .t-left{display:flex;align-items:center;gap:14px;}
    .t-logo{font-size:2rem;}
    .t-title{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:#fff;}
    .t-sub{font-size:.78rem;color:var(--gris);margin-top:1px;}
    .t-right{display:flex;align-items:center;gap:16px;}
    .t-clock{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;letter-spacing:2px;}
    .t-date{font-size:.78rem;color:var(--gris);text-align:right;margin-top:1px;}
    .refresh-btn{
      background:rgba(255,255,255,.1);border:1px solid var(--bord);
      border-radius:9px;padding:8px 16px;color:#fff;font-size:.82rem;
      cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;
    }
    .refresh-btn:hover{background:rgba(255,255,255,.18);}
    .refresh-indicator{width:8px;height:8px;border-radius:50%;background:#16a34a;animation:pulse 2s infinite;}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}

    /* LAYOUT */
    .layout{display:grid;grid-template-columns:1fr 380px;gap:24px;padding:24px 32px;max-width:1600px;margin:0 auto;}

    /* STATS */
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
    .stat{
      background:var(--card);backdrop-filter:blur(10px);
      border:1px solid var(--bord);border-radius:16px;
      padding:18px 20px;text-align:center;
    }
    .stat .s-ico{font-size:1.8rem;margin-bottom:6px;}
    .stat .s-num{font-family:'Syne',sans-serif;font-size:2.2rem;font-weight:800;line-height:1;}
    .stat .s-lbl{font-size:.72rem;color:var(--gris);text-transform:uppercase;letter-spacing:.5px;margin-top:5px;}
    .stat.total .s-num{color:#93c5fd;}
    .stat.attente .s-num{color:#fcd34d;}
    .stat.consult .s-num{color:#6ee7b7;}
    .stat.done .s-num{color:#a5b4fc;}

    /* APPEL EN COURS */
    .appel-section{margin-bottom:20px;}
    .appel-titre{font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--gris);margin-bottom:10px;}
    .appel-vide{
      background:var(--card);border:1px solid var(--bord);border-radius:14px;
      padding:20px;text-align:center;color:var(--gris);font-size:.88rem;
    }
    .appel-card{
      background:linear-gradient(135deg,rgba(22,163,74,.25),rgba(22,163,74,.1));
      border:2px solid rgba(22,163,74,.5);border-radius:16px;
      padding:20px 24px;
      display:flex;align-items:center;justify-content:space-between;gap:16px;
      animation:appelAnim .6s ease;
      margin-bottom:8px;
    }
    @keyframes appelAnim{from{opacity:0;transform:scale(.97)}to{opacity:1;transform:scale(1)}}
    .appel-ticket{
      background:rgba(22,163,74,.3);border:2px solid #16a34a;
      border-radius:12px;padding:10px 16px;text-align:center;flex-shrink:0;
    }
    .appel-ticket .tk-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.5px;color:#6ee7b7;margin-bottom:2px;}
    .appel-ticket .tk-num{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;color:#fff;line-height:1;}
    .appel-nom{flex:1;}
    .appel-nom .an-lbl{font-size:.7rem;color:#6ee7b7;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
    .appel-nom .an-nom{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:#fff;}
    .appel-nom .an-heure{font-size:.78rem;color:var(--gris);margin-top:3px;}
    .appel-icone{font-size:2.5rem;animation:cloche 1s ease-in-out infinite alternate;}
    @keyframes cloche{from{transform:rotate(-15deg)}to{transform:rotate(15deg)}}

    /* FILE D'ATTENTE */
    .file-titre{font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--gris);margin-bottom:10px;display:flex;justify-content:space-between;}
    .file-liste{display:flex;flex-direction:column;gap:8px;max-height:calc(100vh - 340px);overflow-y:auto;padding-right:4px;}
    .file-liste::-webkit-scrollbar{width:4px;}
    .file-liste::-webkit-scrollbar-track{background:transparent;}
    .file-liste::-webkit-scrollbar-thumb{background:rgba(255,255,255,.15);border-radius:99px;}

    .p-item{
      background:var(--card);backdrop-filter:blur(10px);
      border:1px solid var(--bord);border-radius:13px;
      padding:13px 16px;
      display:flex;align-items:center;gap:14px;
      position:relative;overflow:hidden;
      transition:all .2s;
    }
    .p-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;}
    .p-item.ROUGE::before{background:var(--rouge);}
    .p-item.ORANGE::before{background:var(--orange);}
    .p-item.JAUNE::before{background:var(--jaune);}
    .p-item.VERT::before{background:var(--vert);}
    .p-item.appele{background:rgba(22,163,74,.1);border-color:rgba(22,163,74,.3);}
    .p-item.en-consult{background:rgba(124,58,237,.1);border-color:rgba(124,58,237,.3);}

    .p-ticket{
      background:rgba(255,255,255,.08);border-radius:8px;
      padding:6px 10px;text-align:center;flex-shrink:0;min-width:52px;
    }
    .p-ticket .pt-lbl{font-size:.55rem;text-transform:uppercase;letter-spacing:.4px;color:var(--gris);margin-bottom:1px;}
    .p-ticket .pt-num{font-family:'Syne',sans-serif;font-size:1rem;font-weight:800;color:#fff;}

    .p-info{flex:1;min-width:0;}
    .p-nom{font-family:'Syne',sans-serif;font-size:.95rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .p-meta{font-size:.74rem;color:var(--gris);margin-top:2px;}

    .p-badges{display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0;}
    .badge-st{font-size:.62rem;font-weight:700;padding:3px 9px;border-radius:99px;text-transform:uppercase;white-space:nowrap;}
    .badge-st.EN_ATTENTE   {background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);}
    .badge-st.APPELE       {background:rgba(22,163,74,.25);color:#6ee7b7;}
    .badge-st.EN_CONSULTATION{background:rgba(124,58,237,.25);color:#c4b5fd;}
    .p-pos{font-size:.65rem;color:var(--gris);}

    /* COLONNE DROITE — infos centre */
    .right-col{display:flex;flex-direction:column;gap:16px;}
    .info-card{
      background:var(--card);backdrop-filter:blur(10px);
      border:1px solid var(--bord);border-radius:16px;padding:20px;
    }
    .info-card h3{font-family:'Syne',sans-serif;font-size:.85rem;font-weight:700;color:var(--gris);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;}

    /* Légende priorités */
    .legend-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--bord);}
    .legend-item:last-child{border-bottom:none;}
    .legend-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
    .legend-txt{flex:1;}
    .legend-txt .lt{font-size:.82rem;font-weight:600;color:#fff;}
    .legend-txt .ls{font-size:.72rem;color:var(--gris);margin-top:1px;}

    /* Statut footer */
    .footer-strip{
      background:rgba(255,255,255,.04);border-top:1px solid var(--bord);
      padding:10px 32px;
      display:flex;align-items:center;justify-content:space-between;
      font-size:.74rem;color:var(--gris);
    }
    .footer-strip .refresh-info{display:flex;align-items:center;gap:6px;}
    .countdown{font-weight:700;color:#93c5fd;}

    @media(max-width:1100px){
      .layout{grid-template-columns:1fr;}
      .stats-row{grid-template-columns:repeat(2,1fr);}
    }
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="t-left">
    <div class="t-logo">🏥</div>
    <div>
      <div class="t-title">HealthQueue</div>
      <div class="t-sub">Salle d'attente — Affichage en direct</div>
    </div>
  </div>
  <div class="t-right">
    <div>
      <div class="t-clock" id="clock">—</div>
      <div class="t-date" id="dateAff">—</div>
    </div>
    <button class="refresh-btn" onclick="rafraichir()">
      <span class="refresh-indicator"></span>
      Rafraîchir
    </button>
  </div>
</div>

<div class="layout">

  <!-- COLONNE GAUCHE -->
  <div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat total">
        <div class="s-ico">📋</div>
        <div class="s-num" id="st_total"><?= $nb_total ?></div>
        <div class="s-lbl">Patients aujourd'hui</div>
      </div>
      <div class="stat attente">
        <div class="s-ico">⏳</div>
        <div class="s-num" id="st_attente"><?= $nb_attente ?></div>
        <div class="s-lbl">En attente</div>
      </div>
      <div class="stat consult">
        <div class="s-ico">🩺</div>
        <div class="s-num" id="st_consult"><?= $nb_consultation ?></div>
        <div class="s-lbl">En consultation</div>
      </div>
      <div class="stat done">
        <div class="s-ico">✅</div>
        <div class="s-num" id="st_done"><?= $nb_oriente ?></div>
        <div class="s-lbl">Orientés ce jour</div>
      </div>
    </div>

    <!-- PATIENT(S) APPELÉ(S) -->
    <div class="appel-section">
      <div class="appel-titre">📢 Patient(s) appelé(s) — Veuillez vous présenter</div>
      <div id="appelZone">
        <?php if(empty($appeles)): ?>
          <div class="appel-vide">Aucun patient appelé pour le moment.</div>
        <?php else: ?>
          <?php foreach($appeles as $a): ?>
            <div class="appel-card">
              <div class="appel-ticket">
                <div class="tk-lbl">Ticket</div>
                <div class="tk-num">#<?= str_pad($a['id'],3,'0',STR_PAD_LEFT) ?></div>
              </div>
              <div class="appel-nom">
                <div class="an-lbl">Veuillez vous présenter au médecin</div>
                <div class="an-nom"><?= htmlspecialchars($a['prenom'].' '.$a['nom']) ?></div>
                <div class="an-heure">🕐 Appelé à <?= date('H:i', strtotime($a['date_arrivee'])) ?></div>
              </div>
              <div class="appel-icone">🔔</div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- FILE D'ATTENTE -->
    <div class="file-titre">
      <span>👥 File d'attente</span>
      <span id="nbFile"><?= count($file) ?> patient(s)</span>
    </div>
    <div class="file-liste" id="fileListe">
      <?php if(empty($file)): ?>
        <div style="text-align:center;color:var(--gris);padding:30px;font-size:.88rem;">File d'attente vide.</div>
      <?php else: ?>
        <?php $pos=1; foreach($file as $p): ?>
          <div class="p-item <?= $p['priorite'] ?> <?= $p['statut']==='APPELE'?'appele':($p['statut']==='EN_CONSULTATION'?'en-consult':'') ?>">
            <div class="p-ticket">
              <div class="pt-lbl">N°</div>
              <div class="pt-num"><?= str_pad($p['id'],3,'0',STR_PAD_LEFT) ?></div>
            </div>
            <div class="p-info">
              <div class="p-nom"><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></div>
              <div class="p-meta">
                <?= $p['age'] ?> ans •
                <?php $a=intval($p['age']); echo $a<18?'Enfant':($a<65?'Adulte':'Senior'); ?> •
                Arrivé à <?= date('H:i',strtotime($p['date_arrivee'])) ?>
              </div>
            </div>
            <div class="p-badges">
              <span class="badge-st <?= $p['statut'] ?>">
                <?php
                  $labels=['EN_ATTENTE'=>'⏳ En attente','APPELE'=>'📢 Appelé','EN_CONSULTATION'=>'🩺 En consultation'];
                  echo $labels[$p['statut']] ?? $p['statut'];
                ?>
              </span>
              <?php if($p['statut']==='EN_ATTENTE'): ?>
                <span class="p-pos">Position <?= $pos ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php if($p['statut']==='EN_ATTENTE') $pos++; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <!-- COLONNE DROITE -->
  <div class="right-col">

    <!-- Légende priorités -->
    <div class="info-card">
      <h3>📊 Niveaux de priorité</h3>
      <div class="legend-item">
        <div class="legend-dot" style="background:#dc2626;box-shadow:0 0 8px #dc2626;"></div>
        <div class="legend-txt">
          <div class="lt">🔴 Critique / Urgence</div>
          <div class="ls">Prise en charge immédiate</div>
        </div>
      </div>
      <div class="legend-item">
        <div class="legend-dot" style="background:#ea580c;box-shadow:0 0 8px #ea580c;"></div>
        <div class="legend-txt">
          <div class="lt">🟠 Urgent</div>
          <div class="ls">Délai d'attente court</div>
        </div>
      </div>
      <div class="legend-item">
        <div class="legend-dot" style="background:#ca8a04;box-shadow:0 0 8px #ca8a04;"></div>
        <div class="legend-txt">
          <div class="lt">🟡 Semi-urgent</div>
          <div class="ls">Délai d'attente modéré</div>
        </div>
      </div>
      <div class="legend-item">
        <div class="legend-dot" style="background:#16a34a;box-shadow:0 0 8px #16a34a;"></div>
        <div class="legend-txt">
          <div class="lt">🟢 Non urgent</div>
          <div class="ls">Délai d'attente standard</div>
        </div>
      </div>
    </div>

    <!-- Infos pratiques -->
    <div class="info-card">
      <h3>ℹ️ Informations</h3>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div style="background:rgba(255,255,255,.06);border-radius:10px;padding:12px 14px;">
          <div style="font-size:.7rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;">Gardez votre ticket</div>
          <div style="font-size:.84rem;color:#fff;">Conservez votre numéro de ticket et restez dans la salle d'attente.</div>
        </div>
        <div style="background:rgba(255,255,255,.06);border-radius:10px;padding:12px 14px;">
          <div style="font-size:.7rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;">Votre nom est affiché</div>
          <div style="font-size:.84rem;color:#fff;">Lorsque le médecin vous appelle, votre nom apparaîtra en vert sur cet écran.</div>
        </div>
        <div style="background:rgba(255,255,255,.06);border-radius:10px;padding:12px 14px;">
          <div style="font-size:.7rem;color:var(--gris);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;">L'ordre peut changer</div>
          <div style="font-size:.84rem;color:#fff;">Les patients sont pris en charge par ordre de priorité médicale, pas uniquement d'arrivée.</div>
        </div>
      </div>
    </div>

    <!-- Connexion personnel -->
    <div class="info-card" style="text-align:center;">
      <h3>👤 Personnel médical</h3>
      <a href="login.php" style="
        display:inline-block;background:linear-gradient(135deg,var(--bleu2),var(--bleu));
        color:#fff;text-decoration:none;border-radius:10px;padding:11px 24px;
        font-family:'Syne',sans-serif;font-weight:700;font-size:.88rem;
        box-shadow:0 4px 14px rgba(26,107,196,.35);transition:all .2s;
      ">🔐 Accès personnel</a>
    </div>

  </div>
</div>

<!-- FOOTER -->
<div class="footer-strip">
  <div>HealthQueue — Système de gestion de file d'attente médicale</div>
  <div class="refresh-info">
    <span class="refresh-indicator"></span>
    Rafraîchissement automatique dans <span class="countdown" id="countdown">15</span>s
  </div>
</div>

<script>
// ── HORLOGE ───────────────────────────────────────────────
function updateClock() {
  var now = new Date();
  var h = String(now.getHours()).padStart(2,'0');
  var m = String(now.getMinutes()).padStart(2,'0');
  var s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('clock').textContent = h+':'+m+':'+s;
  var jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
  var mois  = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
  document.getElementById('dateAff').textContent =
    jours[now.getDay()]+' '+now.getDate()+' '+mois[now.getMonth()]+' '+now.getFullYear();
}
setInterval(updateClock, 1000);
updateClock();

// ── COMPTE À REBOURS + AUTO-REFRESH ──────────────────────
var secondes = 15;
function updateCountdown() {
  secondes--;
  document.getElementById('countdown').textContent = secondes;
  if (secondes <= 0) location.reload();
}
var timerAuto = setInterval(updateCountdown, 1000);

function rafraichir() {
  clearInterval(timerAuto);
  location.reload();
}
</script>
</body>
</html>
