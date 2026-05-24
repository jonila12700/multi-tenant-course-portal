<?php
require_once 'includes/session.php';

require_login();

header('Location: ' . redirect_for_role($_SESSION['role']));
exit;
