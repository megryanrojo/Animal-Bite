<?php
session_start();
session_unset();
session_destroy();
header("Location: ../login/staff_login.html");
exit;
