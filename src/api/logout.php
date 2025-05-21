<?php
session_start();
session_destroy();
header('Location: /shokudouMenu2/public/index.php');
exit();
