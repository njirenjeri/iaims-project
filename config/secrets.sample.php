<?php

// Copy this file to config/secrets.php and keep that copy OUT of version control.
//
// Generate a key with:  php -r "echo bin2hex(random_bytes(32));"
//
// This key signs every audit log entry. Back it up somewhere safe and separate from the
// database: without it the log cannot be verified, and anyone who can read it together
// with write access to the database could forge entries.
define('AUDIT_HMAC_KEY', 'bcf40aef07a4fb33dd43683f404011b83dc69587042c3ca6fab716fc2a08bfee');
