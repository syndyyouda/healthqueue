<?php
// ============================================================
//  includes/auth.php
// ============================================================

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_role'])) {
        header('Location: /healthqueue/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'ADMIN') {
        header('Location: /healthqueue/login.php?error=acces');
        exit;
    }
}

function getUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return [
        'nom'   => $_SESSION['user_nom']   ?? '',
        'role'  => $_SESSION['user_role']  ?? '',
        'salle' => $_SESSION['user_salle'] ?? '',
    ];
}

// ══════════════════════════════════════════════════════════
//  ALGORITHME DE PRIORISATION COMPLET
//  Prend en compte : symptômes clés, signes vitaux,
//  IMC, terrain, tranche d'âge
// ══════════════════════════════════════════════════════════
function calculerPriorite($data) {
    $score = 0;

    // Cas critique manuel → ROUGE immédiat
    if (!empty($data['cas_critique'])) {
        return ['priorite' => 'ROUGE', 'score' => 100];
    }

    // ── 1. SYMPTÔMES CLÉS ─────────────────────────────────
    // (chaque symptôme contribue même sans les autres)
    if (!empty($data['perte_connaissance']))       $score += 35;
    if (!empty($data['difficulte_respiratoire']))  $score += 30;
    if (!empty($data['douleur_thoracique']))       $score += 30;
    if (!empty($data['saignement_abondant']))      $score += 30;
    if (!empty($data['troubles_neurologiques']))   $score += 28;
    if (!empty($data['douleur_intense_brutale']))  $score += 20;
    if (!empty($data['fievre_elevee']))            $score += 15;

    // ── 2. SIGNES VITAUX ──────────────────────────────────
    // SpO2
    $sat = isset($data['saturation_oxygene']) && $data['saturation_oxygene'] !== '' ? intval($data['saturation_oxygene']) : null;
    if ($sat !== null) {
        if      ($sat < 90)  $score += 35;
        elseif  ($sat < 94)  $score += 18;
        elseif  ($sat < 96)  $score += 8;
    }

    // Fréquence cardiaque
    $fc = isset($data['frequence_cardiaque']) && $data['frequence_cardiaque'] !== '' ? intval($data['frequence_cardiaque']) : null;
    if ($fc !== null) {
        if      ($fc > 130 || $fc < 40)   $score += 30;
        elseif  ($fc > 110 || $fc < 50)   $score += 15;
        elseif  ($fc > 100 || $fc < 60)   $score += 5;
    }

    // Fréquence respiratoire
    $fr = isset($data['frequence_respiratoire']) && $data['frequence_respiratoire'] !== '' ? intval($data['frequence_respiratoire']) : null;
    if ($fr !== null) {
        if      ($fr > 30 || $fr < 8)    $score += 25;
        elseif  ($fr > 24 || $fr < 10)   $score += 12;
        elseif  ($fr > 20)               $score += 5;
    }

    // Température
    $temp = isset($data['temperature']) && $data['temperature'] !== '' ? floatval($data['temperature']) : null;
    if ($temp !== null) {
        if      ($temp >= 40.5 || $temp <= 35.0)  $score += 25;
        elseif  ($temp >= 39.5 || $temp <= 35.5)  $score += 15;
        elseif  ($temp >= 38.5)                   $score += 8;
    }

    // ── 3. IMC ────────────────────────────────────────────
    $imc = isset($data['imc']) && $data['imc'] !== '' ? floatval($data['imc']) : null;
    if ($imc !== null) {
        if      ($imc >= 40)   $score += 12;  // Obésité morbide
        elseif  ($imc >= 35)   $score += 8;   // Obésité sévère
        elseif  ($imc < 14)    $score += 12;  // Dénutrition sévère
        elseif  ($imc < 16)    $score += 8;   // Dénutrition modérée
        elseif  ($imc < 18.5)  $score += 4;   // Insuffisance pondérale
    }

    // ── 4. TERRAIN ────────────────────────────────────────
    if (!empty($data['grossesse']))        $score += 12;
    if (!empty($data['immunodepression'])) $score += 12;
    if (!empty($data['hta']))              $score += 6;
    if (!empty($data['diabete']))          $score += 6;
    if (!empty($data['asthme']))           $score += 8;
    if (!empty($data['maladies_chroniques']) && strlen(trim($data['maladies_chroniques'])) > 2) {
        $score += 5; // autres maladies chroniques mentionnées
    }

    // ── 5. TRANCHE D'ÂGE ─────────────────────────────────
    $age = intval($data['age'] ?? 30);
    if      ($age < 1)   $score += 20;  // nourrisson
    elseif  ($age < 5)   $score += 15;  // enfant en bas âge
    elseif  ($age < 18)  $score += 8;   // enfant
    elseif  ($age >= 80) $score += 15;  // très âgé
    elseif  ($age >= 70) $score += 10;  // âgé
    elseif  ($age >= 65) $score += 6;   // senior

    // ── Score final ───────────────────────────────────────
    $score = min($score, 100);

    if      ($score >= 60) $priorite = 'ROUGE';
    elseif  ($score >= 35) $priorite = 'ORANGE';
    elseif  ($score >= 15) $priorite = 'JAUNE';
    else                   $priorite = 'VERT';

    return ['priorite' => $priorite, 'score' => $score];
}
