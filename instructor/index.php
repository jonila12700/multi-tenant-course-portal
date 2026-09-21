<?php
require_once "../includes/session.php";

require_role('instructor');

header("Location: dashboard.php");
exit;
