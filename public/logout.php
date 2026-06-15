<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

logout_resident();
header('Location: /login.php');
exit;
