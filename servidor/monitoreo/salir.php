<?php
require_once __DIR__ . '/lib.php';
iniciar_sesion();
$_SESSION = [];
session_destroy();
header('Location: index.php');
