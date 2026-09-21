<?php
require_once "../includes/session.php";

require_role('tenant_admin');

header("Location: dashboard.php");
exit;
