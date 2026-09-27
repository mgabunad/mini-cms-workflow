<?php
require 'app.php';

$_SESSION = [];
session_destroy();

redirect('login.php');
