<?php
  if(!isset($_SESSION)) 
      { 
          session_start(); 
      } 
  if (!isset($_SESSION['signed_in']) || !$_SESSION['signed_in']) {
    $_SESSION['flash_error'] = "Please sign in";
    header("Location: login.php");
    exit; // IMPORTANT: Be sure to exit here!
  }
?>