<?php
$originalDir = getcwd();
chdir(__DIR__ . '/views');

require_once __DIR__ . '/views/index.php';

chdir($originalDir);

