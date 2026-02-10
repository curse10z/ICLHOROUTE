<?php
/**
 * Password Utility Functions
 * Provides secure password hashing and verification using bcrypt
 */

/**
 * Hash a password using bcrypt algorithm
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    // Use PASSWORD_BCRYPT with cost factor of 10 (default)
    // This produces a 60-character hash in format: $2y$10$...
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify a password against a hash
 * @param string $password Plain text password to verify
 * @param string $hash Hashed password from database
 * @return bool True if password matches, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if a password hash needs to be rehashed
 * Useful for upgrading to stronger algorithms in the future
 * @param string $hash Current password hash
 * @return bool True if hash needs updating
 */
function needsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT);
}

/**
 * Check if a string is already a bcrypt hash
 * @param string $password String to check
 * @return bool True if string is a bcrypt hash
 */
function isBcryptHash($password) {
    // Bcrypt hashes start with $2y$, $2a$, or $2b$ and are 60 characters long
    return preg_match('/^\$2[ayb]\$.{56}$/', $password) === 1;
}
?>
