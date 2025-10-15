<?php
session_start();
unset($_SESSION['reset_email'], $_SESSION['reset_link'], $_SESSION['full_name']);
http_response_code(200);
?>
