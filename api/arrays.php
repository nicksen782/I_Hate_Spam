<?php

/**
 * LEGACY: This used to be the primary source of filter values.
 *       : Now that source is the database.
 *       : However, this still remains the source to the program.
 * Can be used to quickly add values for testing before entering
 * them into the database.
 *
**/

// https://whoisip.ovh/77.247.181.165
// https://stopforumspam.com/ipcheck/77.247.181.165

/**
 * If the last post is made by one of these users
 * then the post is NOT subject to possible deletion.
**/
$trustedMembers = [
];
$trustedMembers = array_map('strtolower', $trustedMembers);

/**
 * List of known spam accounts.
 * Posts made by these accounts WILL be deleted.
**/
$knownSpamAccounts = [
];
$knownSpamAccounts = array_map('strtolower', $knownSpamAccounts);

/**
 * Individual IP bans.
 * Untrusted posts made by these WILL be deleted.
**/
$spammyIPs_individualBans = [
];

/**
 * Bans of IP address ranges.
 * Untrusted posts made by these WILL be deleted.
**/
$spammyIPs_subnetsCIDR = [
];

/**
 * Spammy words.
 * Untrusted posts made by these WILL be deleted.
**/
$spammyWords = [
	// DEBUG: Used to create false positives for testing.
	// "Big Ass Photos - Free Huge Butt Porn, Big Booty Pics" ,
	// "Looking", "to", "buy", "a", "Uzebox", "kit" ,
	// "виагру" ,  "в" ,  "Москве" ,  "сейчас" , "легко" , "и" ,  "просто" ,
];
$spammyWords = array_map('strtolower', $spammyWords);

?>