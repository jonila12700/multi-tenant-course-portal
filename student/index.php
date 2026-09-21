<?php
require_once "../includes/session.php";

require_role('student');

header("Location: dashboard.php");
exit;
