<?php
session_start();
require_once '../config/db.php';

$role = $_GET['role'] ?? '';
if (!$role) { echo json_encode([]); exit; }

$db = getDB();
$stmt = $db->prepare("
    SELECT message, DATE_FORMAT(created_at,'%H:%i') as heure
    FROM notifications
    WHERE destinataire_role = ? AND lue = 0
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$role]);
echo json_encode($stmt->fetchAll());
