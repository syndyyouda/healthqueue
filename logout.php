<?php
session_start();
session_destroy();
header('Location: /healthqueue/login.php');
exit;
