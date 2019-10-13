<?php
// ----------------------------------------------------------
// PROGRAM: I Hate Spam
// VERSION: 4 (2019)
// DATE: 2017 - 2019
// AUTHOR: Nickolas Andersen
// GITHUB: https://github.com/nicksen782/IHateSpam
//
// The DOM parser used uses code from Ivo Vopetkov at: https://github.com/ivopetkov/html5-dom-document-php .
//
// INTENT:
//  Specifically intended for the uzebox.org/forums .
//  Expected phpBB version: 3.2.0
//
//  Program uses phpBB moderator access to scan for the latest spam posts on a phpBB forum.
//
//  Based on some logic, posts that are considered "untrusted" can be deleted based on certain characteristics.
//  "trusted" posts are defined by the last poster of a topic being a in the trusted member list.
//
//  Filters available for identifying spam:
//   KNOWN SPAM ACCOUNT: If the last poster is a known spammer the post will be deleted.
//   SPAMMY TEXT       : If the post contains too many "spammy words" the post will be deleted.
//   MASS POST         : If the user has posted too many posts per minute the post will be deleted.
//   IP BAN            : If the last poster used an IP in the IP BAN list the post will be deleted.
//   IP RANGE BAN      : If the last poster used an IP within the IP RANGE BAN list the post will be deleted.
//   CYRILLIC SCRIPT   : If the topic text contains cyrillic characters the post will be deleted.
//
//  If the untrusted post does not match any of these characteristics then it will NOT be deleted.
//
// ARGUMENTS:
// $argv[1]; // The "o" value.
// $argv[2]; // The allow flag for post deletion.
// $argv[3]; // The count of how many recent deleted posts to show.
//
// EXAMPLE USAGE:
// VIA SCRIPT     : /home/nicksen782/SERVER/SCRIPTS/runIHS.sh
// VIA PHP COMMAND: cd /home/nicksen782/workspace/web/ACTIVE/IHateSpamV4/api/ && php -d register_argc_argv=1 ihs_p.php ajax_runScan 1 0
// VIA WEB (POST) : { o=ajax_runScan , deleteFlaggedPosts=1 }
// VIA CRON       : */5 * * * * /home/nicksen782/SERVER/SCRIPTS/runIHS.sh
// VIA CRON       : */5 * * * * cd /home/nicksen782/workspace/web/ACTIVE/IHateSpamV4/api/ && php -d register_argc_argv=1 ihs_p.php ajax_runScan 1 0
// ----------------------------------------------------------

// CONFIGURE TIMEZONE.
define('TIMEZONE', 'America/Detroit');
date_default_timezone_set(TIMEZONE);

// CONFIGURE WARNING AND ERROR HANDLING.
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT );
$_appdir                 = getcwd().'/../' ;
chdir($_appdir);
ini_set('error_log'         , $_appdir . 'IHSv4_php-error.txt');
ini_set('display_errors'    , 1);
ini_set("log_errors"        , 1);
ini_set('register_argc_argv', 1);

function exitWithErrorText2() {
	// Get the last error.
	$err = error_get_last();

	// If there was an error...
	if( is_null($e = $err ) === false) {
		$type = $err["type"];

		switch($type) {
			case E_ERROR             : { $type = 'E_ERROR';             break; } // 1 //
			case E_WARNING           : { $type = 'E_WARNING';           break; } // 2 //
			case E_PARSE             : { $type = 'E_PARSE';             break; } // 4 //
			// case E_NOTICE            : { $type = 'E_NOTICE';            break; } // 8 //
			case E_CORE_ERROR        : { $type = 'E_CORE_ERROR';        break; } // 16 //
			case E_CORE_WARNING      : { $type = 'E_CORE_WARNING';      break; } // 32 //
			case E_COMPILE_ERROR     : { $type = 'E_COMPILE_ERROR';     break; } // 64 //
			case E_COMPILE_WARNING   : { $type = 'E_COMPILE_WARNING';   break; } // 128 //
			case E_USER_ERROR        : { $type = 'E_USER_ERROR';        break; } // 256 //
			case E_USER_WARNING      : { $type = 'E_USER_WARNING';      break; } // 512 //
			case E_USER_NOTICE       : { $type = 'E_USER_NOTICE';       break; } // 1024 //
			case E_STRICT            : { $type = 'E_STRICT';            break; } // 2048 //
			case E_RECOVERABLE_ERROR : { $type = 'E_RECOVERABLE_ERROR'; break; } // 4096 //
			case E_DEPRECATED        : { $type = 'E_DEPRECATED';        break; } // 8192 //
			case E_USER_DEPRECATED   : { $type = 'E_USER_DEPRECATED';   break; } // 16384 //
			default                  : { $type = ""; break; }
		}

		// Is this an error we are looking for?
		if($type != ""){
			// Clear the output buffer. Send a JSON response.
			$ob = ob_get_contents() ;
			ob_end_clean ();
			// global $errorFlags;
			$output = [
				"__error" => [
					"file"       => basename($err["file"]) ,
					"line"       => $err["line"]           ,
					"message"    => "\n".$err["message"]   ,
					"type_num"   => $err["type"]           ,
					"type"       => $type                  ,
					"ob"         => $ob                    ,
					'$argv'      => $argv                  ,
					// "errorFlags" => $errorFlags            ,
				]
			]
			;
			echo json_encode($output, JSON_PRETTY_PRINT);
			exit();
		}

		// Ignore this error. Just return.
		else{
			return;
		}
	}
}

register_shutdown_function('exitWithErrorText2');
ob_start(); // Start output buffering (used for the error handler.)

// INCLUDE LIBRARIES.
require_once 'vendor/html5-dom-document-php/autoload.php';
require_once 'api/arrays.php';

// GLOBALS
$cookieData              = [] ;
$isLoggedIn              = 0  ;
$loggedOnUsername        = "" ;
$_originationFlag_indexp = true ; // Origination flag.
$_db_file                = $_appdir."api/ihs_v4.db" ;
$viacommandline          = false;

if( ! file_exists( $_db_file )){ createInitialDatabase(); }

// Was a request received? Process it.
if     ( isset($_POST['o']) ){               API_REQUEST( $_POST['o'] ); }
// else if( isset($_GET ['o']) ){ $_POST=$_GET; API_REQUEST( $_GET ['o'] ); } // DEBUG
else{
	// Was the program called via the command line and with arguments?
	if(
		( isset( $argv[1] ) && $argv[1] == "ajax_runScan" ) &&
		isset  ( $argv[2] ) &&
		isset  ( $argv[3] )
	){
		global $viacommandline;

		$viacommandline=true;
		// Set the $_POST to match the correct argv values.
		// NOTE: This specifically supports "ajax_runScan" as the "o" value.
		$_POST['o']                  = $argv[1]; // The "o" value.
		$_POST['deleteFlaggedPosts'] = $argv[2]; // The allow flag for post deletion.
		$_POST['numDeletedPosts']    = $argv[3]; // The count of how many recent deleted posts to show.

		// Do a loop until all flagged posts have been cleaned.
		if($_POST['deleteFlaggedPosts']){
			// while(0){
				// Clear the output buffer.
				ob_end_clean ();

				$numPostsDeleted  = 0;
				$deletionOccurred = 0;

				do{
					// Restart the output buffer.
					ob_start();

					// Perform the request.
					API_REQUEST( $_POST ['o'] );

					// Get the results from the output buffer.
					$output1           = ob_get_contents();
					$decoded_output1   = json_decode( $output1, true);

					if( is_array($decoded_output1['res']['deleted']) ){
						$deletionOccurred  = sizeof($decoded_output1['res']['deleted']) ? 1 : 0;
						$numPostsDeleted  += sizeof($decoded_output1['res']['deleted']) ;
					}

					// Clear the output buffer. (It will not be used here.
					ob_end_clean ();
				}
				while($deletionOccurred);

				// Update the cmdline_output.txt file with the current datetime text.
				file_put_contents(
					'cmdline_output.txt',
					"(".$numPostsDeleted.") "."Last cron run: " . date("Y-m-d H:i:s") . "\n\n"
				);
			// }
		}

	}
	// No? Then this is an error.
	else{
		$stats['error_text']="*** No 'o' POST value was provided.";
		$stats['$_POST'] = $_POST;
		$stats['$_GET']  = $_GET;

		// Return the error data.
		echo json_encode( [
			'stats'  => $stats ,
			'$argv'  => $argv  ,
			'$_POST' => $_POST ,
			'$_GET'  => $_GET  ,
		]);
	}

	exit();
}

// **********************************
// EXTERNAL (AJAX) CALLABLE FUNCTIONS
// **********************************
function API_REQUEST( $api ){
	$stats = array(
		'error'      => false ,
		'error_text' => ""    ,
		'thisUser'   => []    ,
	);

	// Rights.
	$public = 1        ; // No rights required.

	$o_values=array();

	// --- POST CALLABLE FUNCTIONS ---
	$o_values["ajax_runScan"]         = [ "p"=>( ($public) ? 1 : 0 ), "args"=>[] ] ;
	$o_values["ajax_deletePost"]      = [ "p"=>( ($public) ? 1 : 0 ), "args"=>[] ] ;
	$o_values["ajax_addKnownSpammer"] = [ "p"=>( ($public) ? 1 : 0 ), "args"=>[] ] ;
	$o_values["ajax_addTrustedUser"]  = [ "p"=>( ($public) ? 1 : 0 ), "args"=>[] ] ;

	$o_values["ajax_sql_data_backups"]= [ "p"=>( ($public) ? 1 : 0 ), "args"=>[] ] ;

	$o_values["ajax_ipUserCounts"]    = [ "p"=>( ($public) ? 1 : 0 ), "args"=>[] ] ;
	// --- POST CALLABLE FUNCTIONS ---

	// DETERMINE IF THE API IS AVAILABLE TO THE USER.

	// Is this a known API?
	if( ! isset( $o_values[ $api] ) ){
		$stats['error']=true;
		$stats['error_text']="Unhandled API" . "-" . $api . "-" ;
		$stats['o']=$_POST["o"] ? $_POST["o"] : $_GET["o"];
	}

	// Does the user have sufficient permissions?
	else if( ! $o_values[ $api ]['p'] ){
		$stats['error']=true;
		$stats['error_text']="API auth error";
		$stats['o']=$_POST["o"] ? $_POST["o"] : $_GET["o"];
	}

	// Can the function be run?
	if(! $stats['error']){
		// GOOD! Allow the API call.
		call_user_func_array( $api, array( $o_values[ $api ]["args"]) );
	}

	// Was there an error?
	else{
		echo json_encode( $stats );
		exit("ERROR! SEE STATS FOR DETAILS.");
	}

}
function ajax_runScan(){
	global $cookieData        ;
	global $isLoggedIn        ;
	global $loggedOnUsername  ;
	global $knownSpamAccounts ;
	global $trustedMembers    ;
	global $spammyWords       ;

	// Is the delete flag set?
	$deleteFlaggedPosts = intval($_POST['deleteFlaggedPosts'])
		? 1
		: 0
	;

	// Set a value for the number of recent deletions to display.
	$numDeletedPosts    = is_int($_POST['numDeletedPosts'])
		? intval($_POST['numDeletedPosts'])
		: 20
	;

	// Run the command. Get some data back.
	$res = runScan($deleteFlaggedPosts);
	$runCount=1;
	$postsDeletedCount = $res['postsDeletedCount'] ;

	$res['recentDeletions']=[];

	// Were some posts deleted? Run the scan again up to 10 times or until no more deleted posts.
	if($res['postsDeletedCount'] && $deleteFlaggedPosts ){
		$postsDeletedCount=0;

		while($res['postsDeletedCount'] && $runCount < 10){
			$res = runScan($deleteFlaggedPosts);
			$runCount += 1;
			$postsDeletedCount += $res['postsDeletedCount'] ;
		}
	}

	global $viacommandline;
	if($viacommandline==false){
		// Get the recent deletions.
		$res['recentDeletions'] = getrecentDeletions( $numDeletedPosts );

		// Get the deleted post counts for the last 5 days.
		$LatestDeletionCounts = getLatestDeletionsInfo();
	}

	$namespaces=array();
	foreach(get_declared_classes() as $name) {
		if(preg_match_all("@[^\\\]+(?=\\\)@iU", $name, $matches)) {
			$matches = $matches[0];
			$parent =&$namespaces;
			while(count($matches)) {
				$match = array_shift($matches);
				if(!isset($parent[$match]) && count($matches))
					$parent[$match] = array();
				$parent =&$parent[$match];

			}
		}
	}

	// print_r($namespaces);

	// Return this data to the client.
	$output = [
		'res'                  => $res               ,
			'postsDeletedCount'    => $postsDeletedCount ,
			'runCount'             => $runCount          ,
			'namespaces'           => $namespaces        ,

		'knownSpamAccounts'    => $knownSpamAccounts ,
		'trustedusers'         => $trustedMembers    ,
		'spammyWords'          => $spammyWords       ,
		'isLoggedIn'           => $isLoggedIn        ,
		'loggedOnUsername'     => $loggedOnUsername  ,
		'cmdline_output'       => file_get_contents('cmdline_output.txt') ,
		'LatestDeletionCounts' => $LatestDeletionCounts ,
		//
		'DEBUG_timing'             => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] ,
		'DEBUG_deleteFlaggedPosts' => $deleteFlaggedPosts ,
		'DEBUG_cookieData'         => $cookieData         ,
		'DEBUG_$_POST'             => $_POST              ,
		'DEBUG_o'                  => $_POST['o']         ,
	];

	// DEBUG
	// echo "<pre>";
	// print_r($output);
	// echo "</pre>";

	echo json_encode( $output );
}
function ajax_deletePost(){
	// Login test!
	pre_login();

	$thisRecord     = json_decode($_POST['thisRecord'], true) ;
	$forumNum = $thisRecord['f']      ;
	$postNum  = $thisRecord['p']      ;
	$thisRecord['deletionReason'] = $_POST['deletionReason'] ;
	$reason = $thisRecord['deletionReason'] ;

	if($forumNum && $postNum && $reason){
		// Delete the post.
		deletePost( $thisRecord );
	}
	else{
	}

	ajax_runScan();

	// echo json_encode([
	// 	'$_POST'          => $_POST          ,
	// 	// 'o'          => $_POST['o']          ,
	// 	// 'CHECK'          => ($forumNum && $postNum && $reason),
	// 	'A_forumNum'=>$forumNum,
	// 	'A_postNum'=>$postNum,
	// 	'A_reason'=>$reason,
	// 	'$thisRecord'     => $thisRecord     ,
	// ]);
}
function ajax_addKnownSpammer(){
	// Login test!
	// pre_login();

	// Add the spammer to the database.
	$thisRecord = json_decode($_POST['thisRecord'], true) ;
	$username = $thisRecord['lastPostAuthor'] ;
	addKnownSpammer($username);

	// Run a new scan to refresh the data.
	// ajax_runScan();
}
function ajax_addTrustedUser(){
	// Add the trusted user to the database.

	// Login test!
	pre_login();

	global $_db_file ;
	$thisRecord = json_decode($_POST['thisRecord'], true) ;
	$username = $thisRecord['lastPostAuthor'] ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	// Check if the username has already been added.
	$s_SQL1='
		SELECT username
		FROM trustedAccounts
		WHERE username = :username
	;';
	$prp1     = $dbhandle->prepare($s_SQL1);
	$dbhandle->bind(':username' , $username ) ;
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	if( !sizeof($results1) ){
		// Add the username.
		$s_SQL2='
		INSERT INTO trustedAccounts
		     ( id  , tstamp           ,  username )
		VALUES
		     ( null, CURRENT_TIMESTAMP, :username )
		;';
		$prp2     = $dbhandle->prepare($s_SQL2);
		$dbhandle->bind(':username' , $username ) ;
		$retval2  = $dbhandle->execute();
	}

	// Run a new scan to refresh the data.
	ajax_runScan();
}

function ajax_ipUserCounts(){
	// This function is likely very inefficient.

	$numPosts = intval($numPosts);

	global $_db_file ;
	$thisRecord = json_decode($_POST['thisRecord'], true) ;
	$username = $thisRecord['lastPostAuthor'] ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");
	$dbhandle2 = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	// Get list of unique ips from the deletion table.
	$s_SQL1='
		SELECT
			DISTINCT postIpAddress AS postIpAddress
		FROM deletions
		ORDER BY topicLastPostDate DESC
	;';
	$prp1     = $dbhandle->prepare($s_SQL1);
	// $dbhandle->bind(':numPosts' , $numPosts ) ;
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	// Get all users associated with each ip in the previous list.
	$s_SQL2='
		SELECT
			DISTINCT topicLastAuthorUsername AS user
		FROM deletions
		WHERE
			postIpAddress = :postIpAddress
	;';
	$prp2     = $dbhandle->prepare($s_SQL2);

	$results2=[];
	for($i=0; $i<sizeof($results1); $i+=1){
		$addr = $results1[$i]['postIpAddress'];
		$newrecord=[];

		if($addr !=0 && $addr != ''){
			$dbhandle->bind(':postIpAddress' , $addr ) ;
			$retval2  = $dbhandle->execute();
			$res = $dbhandle->statement->fetchAll(PDO::FETCH_COLUMN) ;

			// Get the latest post date for this IP address.
			$s_SQL3='
				SELECT
					MAX(topicLastPostDate) AS lastpost
				FROM deletions
				WHERE
					postIpAddress = :postIpAddress
			;';
			$prp3     = $dbhandle2->prepare($s_SQL3);
			$dbhandle2->bind(':postIpAddress' , $addr ) ;
			$retval3  = $dbhandle2->execute();
			$results3 = $dbhandle2->statement->fetchAll(PDO::FETCH_ASSOC) ;
			$lastdate = $results3[0]['lastpost'];

			$newrecord = [
				'ip'        => $addr ,
				'lastdate'  => $lastdate ,
				'usernames' => $res  ,
			] ;
			$results2[] = $newrecord ;
		}

	}

	// Sort the results: Latest dates first.
	usort($results2, function($a, $b){
		return strcmp($b['lastdate'], $a['lastdate']);
	});

	// Sort the results: Count of username associations with ip address first.
	// usort($results2, function($a, $b){
	// 	return sizeof($a['usernames']) <=> sizeof($b['usernames']);
	// });

	echo json_encode([
		// '$_POST'     => $_POST      ,
		// 'o'          => $_POST['o'] ,
		// '$results1'  => $results1   ,
		'$results2'  => $results2   ,
	]);
}
// ***************************
// INTERNAL CALLABLE FUNCTIONS
// ***************************
function pre_login(){
	global $cookieData;
	global $isLoggedIn;
	global $loggedOnUsername;

	// Login if needed.
	// Test the login twice. Fail after two times.
	for($i=0; $i<2; $i+=1){
		// Get the HTML for the active topics page.
		$html = getLatestTopicsInfo(30);
		$dom  = new IvoPetkov\HTML5DOMDocument();
		$dom->loadHTML($html );

		// Is the logged in link there?
		$link = $dom ->querySelector('#username_logged_in') ;
		if(!$link){
			// echo "!!! TRYING TO LOGIN !!!<br><br>";
			login();
			$cookieData = returnCookieData('cookie.txt');
			continue;
		}
		else{
			// Set the username.
			$loggedOnUsername = $dom ->querySelector('#username_logged_in')
									 ->querySelector('.username')->innerHTML;

			// echo "LOGGED IN AS: ".$loggedOnUsername."<br><br>";
			$isLoggedIn = true;

			$cookieData = returnCookieData('cookie.txt');
			break;
		}
	}
	if(!$isLoggedIn){
		echo "ERROR: Could not login!";
		exit("");
	}

	// Retrieve the filter lists from the database.
	// Sets global variables.
	getFilterLists();

	// Return the dom gathered here.
	return $dom;

}
function returnCookieData($filename){
	// [httponly]         => 1
	// [domain]           => .uzebox.org
	// [flag]             => TRUE
	// [path]             => /
	// [secure]           => FALSE
	// [expiration-epoch] => 1596111294
	// [name]             => phpbb3_5rn2n_k
	// [value]            => fe39EXAMPLE9cf7730
	// [expiration]       => 2020-07-30 08:14:54

	$cookieString = file_get_contents("api/".$filename);
	$lines        = explode(PHP_EOL, $cookieString);
	$cookies      = [];

	foreach ($lines as $line) {
		$cookie = array();

		// detect httponly cookies and remove #HttpOnly prefix
		if (substr($line, 0, 10) == '#HttpOnly_') {
			$line = substr($line, 10);
			$cookie['httponly'] = true;
		} else {
			$cookie['httponly'] = false;
		}

		// we only care for valid cookie def lines
		if( strlen( $line ) > 0 && $line[0] != '#' && substr_count($line, "\t") == 6) {

			// get tokens in an array
			$tokens = explode("\t", $line);

			// trim the tokens
			$tokens = array_map('trim', $tokens);

			// Extract the data
			$cookie['domain'] = $tokens[0]; // The domain that created AND can read the variable.
			$cookie['flag'] = $tokens[1];   // A TRUE/FALSE value indicating if all machines within a given domain can access the variable.
			$cookie['path'] = $tokens[2];   // The path within the domain that the variable is valid for.
			$cookie['secure'] = $tokens[3]; // A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.

			$cookie['expiration-epoch'] = $tokens[4];  // The UNIX time that the variable will expire on.
			$cookie['name'] = urldecode($tokens[5]);   // The name of the variable.
			$cookie['value'] = urldecode($tokens[6]);  // The value of the variable.

			// Convert date to a readable format
			$cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);

			// Record the cookie.
			$cookies[] = $cookie;
		}
	}

	// Convert the cookies into individual variables.
	foreach ($cookies as $key => $val) {
		$name = $cookies[ $key ]['name'] ;
		$exploded_name = explode("_", $name);
		if(sizeof($exploded_name)==3){
			$cookies_assoc[$exploded_name[2]] = $cookies[ $key ]['value'];
		}
		// else { echo "BAD!"; }
	}

	if(sizeof($cookies)){
		$cookies_assoc['expires-epoch'] = $cookies[0]['expiration-epoch'];
		$cookies_assoc['expires']       = $cookies[0]['expiration'];
	}

	return $cookies_assoc;
}
function login(){
	global $cookieData;
	$cookieData = returnCookieData('cookie.txt');

	// https://stackoverflow.com/questions/12099136/posting-to-phpbb2-forum-with-php-and-curl

	if(!file_exists("forumCredentials.json")){
		$newFile = [
			"username" => "USERNAME",
			"password" => "PASSWORD"
		];
		file_put_contents("forumCredentials.json", json_encode($newFile));

		exit("ALERTTHIS forumCredentials.json does not exist. It has been created. You will need to edit it.");
	}
	else{
		$credentials = file_get_contents("forumCredentials.json");
		$credentials = json_decode($credentials, true);
		$username    = $credentials["username"];
		$password    = $credentials["password"];
	}

	$cookie_file_path = dirname(__FILE__).'/cookie.txt';

	$loginURL         = 'http://uzebox.org/forums/ucp.php?mode=login'; // Login url.

	$ch = curl_init();
	$options = [
		CURLOPT_URL        => $loginURL ,
		CURLOPT_HTTPGET    => 0 ,
		CURLOPT_POST       => 1 ,
		CURLOPT_POSTFIELDS => [
			'username'  => $username ,
			'password'  => $password ,
			'autologin' => 'on'      ,
			'login'     => 'Log in'
		] ,
		CURLOPT_COOKIEJAR      => $cookie_file_path ,
		CURLOPT_COOKIEFILE     => $cookie_file_path ,
		CURLOPT_COOKIESESSION  => 1 ,
		CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ,
		CURLOPT_RETURNTRANSFER => 1 ,
		CURLOPT_REFERER        => $_SERVER['REQUEST_URI'] ,
		CURLOPT_FOLLOWLOCATION => 1 ,
	];

	// echo "<pre>"; print_r($options); echo "</pre>";
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch); $ch=null;
}
function getLatestTopicsInfo($days){
	if(!$days){ $days = 30; }

	$activeTopicsURL  = "http://uzebox.org/forums/search.php?st=$days&sk=t&sd=d&sr=topics&search_id=active_topics"; // Active topics url.
	$cookie_file_path = dirname(__FILE__).'/cookie.txt';

	$ch = curl_init();
	$options = array(
		CURLOPT_URL            => $activeTopicsURL   ,
		CURLOPT_HEADER         => false              ,
		CURLOPT_RETURNTRANSFER => true               ,

		CURLOPT_COOKIEJAR      => $cookie_file_path  ,
		CURLOPT_COOKIESESSION  => 1                  ,
		CURLOPT_COOKIEFILE     => $cookie_file_path  ,

		CURLOPT_REFERER        => $activeTopicsURL            ,
		CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ,
	);
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch);
	$ch=null;
	return $result;
}
function getFilterLists(){
	global $trustedMembers           ;
	global $knownSpamAccounts        ;
	global $spammyWords              ;
	// global $spammyIPs_individualBans ;
	// global $spammyIPs_subnetsCIDR    ;

	global $_db_file ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	$s_SQL1=' SELECT username FROM trustedAccounts WHERE 1=1 ; ';
	$prp1     = $dbhandle->prepare($s_SQL1);
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	$s_SQL2=' SELECT username FROM knownSpamAccounts WHERE 1=1 ; ';
	$prp2     = $dbhandle->prepare($s_SQL2);
	$retval2  = $dbhandle->execute();
	$results2 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	$s_SQL3=' SELECT word FROM spammyWords WHERE 1=1 ; ';
	$prp3     = $dbhandle->prepare($s_SQL3);
	$retval3  = $dbhandle->execute();
	$results3 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	// $s_SQL4=' SELECT username FROM trustedAccounts WHERE 1=1 ; ';
	// $prp4     = $dbhandle->prepare($s_SQL4);
	// $retval4  = $dbhandle->execute();
	// $results4 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	// $s_SQL5=' SELECT username FROM trustedAccounts WHERE 1=1 ; ';
	// $prp5     = $dbhandle->prepare($s_SQL5);
	// $retval5  = $dbhandle->execute();
	// $results5 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	$trustedMembers            = array_map(function($v){ return $v['username']; }, $results1);
	$knownSpamAccounts         = array_map(function($v){ return $v['username']; }, $results2);
	$spammyWords               = array_map(function($v){ return $v['word'];     }, $results3);
	// $spammyIPs_individualBans;
	// $spammyIPs_subnetsCIDR;

}
function createTopicsArrays($dom){
	// global $trustedMembers;
	global $trustedMembers;
	global $knownSpamAccounts;
	$untrustedTopics=[];
	$trustedTopics  =[];

	// Get the first topics.
	$topicTitlesROWS = $dom->querySelectorAll('.topics')->item(0);

	// If a topic was NOT found then stop.
	if( !$topicTitlesROWS ) { return [];} ;

	// Get the rows of the topic.
	$topicTitlesROWS=$topicTitlesROWS->querySelectorAll('li.row');

// document.querySelectorAll(".topics")[0].querySelectorAll('li.row').forEach(function(d,i,a){ console.log(d.querySelector('a.topictitle').innerHTML, d.querySelectorAll('.username')); });

	// Go through the rows.
	foreach($topicTitlesROWS as $key => $val){
		// Get a handle onto this DOM element.
		$thisRow_elem=$topicTitlesROWS->item($key);

		// Get the data fields for this record.
		$thisRow_arr = [
			'topictitle'     => $thisRow_elem->querySelector('a.topictitle')->innerHTML ,
			'topictitleurl'  => $thisRow_elem->querySelector('a.topictitle')->getAttributes()['href'] ,

			'orgpostername'  => $thisRow_elem
									->querySelector('.responsive-hide')
									->querySelectorAll('a')->item(0)
									->innerHTML ,
			'orgposterurl'   => $thisRow_elem
									->querySelector('.responsive-hide')
									->querySelectorAll('a')->item(0)
									->getAttributes()['href'] ,
			'forumurl'       => $thisRow_elem
									->querySelector('.responsive-hide')
									->querySelectorAll('a')->item(1)
									->getAttributes()['href'] ,
			'forumname'      => $thisRow_elem
									->querySelector('.responsive-hide')
									->querySelectorAll('a')->item(1)
									->innerHTML ,
			'lastpostername' => $thisRow_elem
									->querySelector('.lastpost')
									->querySelectorAll('a')->item(0)
									->innerHTML
									,
			'lastposterurl'  => $thisRow_elem
									->querySelector('.lastpost')
									->querySelectorAll('a')->item(0)
									->getAttributes()['href'] ,
			'lastposturl'    => $thisRow_elem
									->querySelector('.lastpost')
									->querySelector('span')
									->querySelectorAll('a')->item(1)
									->getAttributes()['href'] ,
			'lastpostdate'   => explode("<br>", $thisRow_elem
									->querySelector('.lastpost')
									->querySelectorAll('span')->item(0)
									->innerHTML)[1] ,
		];
		// Trim each data field.
		foreach($thisRow_arr as $k => $v){ $thisRow_arr[$k] = trim($v); }
		$k = null; $v = null;

		// Get some additional data.
		$explodedQueryString = array();

		parse_str( parse_url( $thisRow_arr['lastposturl'], PHP_URL_QUERY), $explodedQueryString );
		$f    = $explodedQueryString['f'];
		$t    = $explodedQueryString['t'];
		// $sid  = $sid;//$explodedQueryString['sid'];
		// $sid  = $explodedQueryString['sid'];
		$p    = $explodedQueryString['p'];
		// $u    = $explodedQueryString['u'];

		// Create an entry in the untrustedTopics array.
		$tempRecord =
			array(
				'forumname'      => $thisRow_arr['forumname']      , // $forumnameTXT      ,
				'topic'          => $thisRow_arr['topictitle']     , // $topictitle        ,
				'author'         => $thisRow_arr['orgpostername']  , // $originalPosterTXT ,
				'authorURL'      => $thisRow_arr['orgposterurl']   , // $originalPosterTXT ,
				'lastPostDate'   => $thisRow_arr['lastpostdate']   , // $lastPostDateTXT   ,
				'lastPostAuthor' => $thisRow_arr['lastpostername'] , // $lastPosterTXT     ,
				'lastPosterURL'  => $thisRow_arr['lastposterurl']  , // $lastPostDateHREF  ,
				'lastPostURL'    => $thisRow_arr['lastposturl']    , // $lastPostDateHREF  ,
				'f'              => $f             ,
				't'              => $t             ,
				'p'              => $p             ,
				'deleteThis'     => 0              ,
				'postip'         => ''             ,
				'deletionReason' => ''             ,
				'postCount'      => 0              ,
				// 'sid'            => $sid           ,
				// 'u'              => $u             ,
				// 'ipbanned'       => 0              ,
				// 'userbanned'     => 0              ,
			);

		$lowercase_trustedMembers    = array_map('strtolower', $trustedMembers);
		$lowercase_knownSpamAccounts = array_map('strtolower', $knownSpamAccounts);

		// Is the last poster a trusted user?
		if(
			in_array(
				strtolower($tempRecord['lastPostAuthor']),
				$lowercase_trustedMembers
			)
		){
			array_push( $trustedTopics, $tempRecord);
		}
		// The last poster is NOT a trusted user?
		else{
			array_push( $untrustedTopics, $tempRecord);
		}

	}

	// Return both arrays.
	return array(
		'untrustedTopics' => $untrustedTopics ,
		'trustedTopics'   => $trustedTopics   ,
	);

/*
	if(!$days){ $days = 30; }

	$activeTopicsURL  = "http://uzebox.org/forums/search.php?st=$days&sk=t&sd=d&sr=topics&search_id=active_topics"; // Active topics url.
	$cookie_file_path = dirname(__FILE__).'/cookie.txt';

	$ch = curl_init();
	$options = array(
		CURLOPT_URL            => $activeTopicsURL ,
		CURLOPT_HEADER         => false            ,
		CURLOPT_RETURNTRANSFER => true             ,
	);
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch);
	$ch=null;
	return $result;
*/
}
function runScan($deleteFlaggedPosts){
	// Bring in the globals.
	global $cookieData;
	global $isLoggedIn;
	global $loggedOnUsername;
	global $spammyWords;
	global $knownSpamAccounts;

	// Login? Will log on the bot if it is not already logged on.
	// Also will send back the DOM for the active topics forum page.
	$dom = pre_login();

	// Parse the retrieved HTML/DOM and create data arrays.
	$topicData = createTopicsArrays($dom);

	// Ignore the trusted entries.
	//

	// Check each untrusted entry against the spam filters.
	$usersWithSpammyPosts=[];
	for($i=0; $i<sizeof($topicData['untrustedTopics']); $i++){
	// !!!!!!!!!!!!!!!!!!!
	// BASE INFO GATHERING
	// !!!!!!!!!!!!!!!!!!!
		$deletionReason    = "" ;
		$deleteThisPost    = 0  ;
		$lastPoster        = "" ;
		$min_unix          = 0  ;
		$max_unix          = 0  ;
		$numPosts          = 0  ;
		$timeRange         = 0  ;
		$timeRange_minutes = 0  ;
		$ipinfo            = [] ;
		$spammy            = [] ;
		$spammy_total      = "" ;
		$spammy_words      = [] ;
		$NEWKNOWNSPAMMER=false;
		$topicText = "";
		$topicData['untrustedTopics'][$i]['NEWKNOWNSPAMMER']  = 0  ;
		$topicData['untrustedTopics'][$i]['deleteThis']       = 0  ;
		$topicData['untrustedTopics'][$i]['postip']           = "" ;
		$topicData['untrustedTopics'][$i]['postips']          = [] ;
		$topicData['untrustedTopics'][$i]['deletionReason']   = "" ;
		$topicData['untrustedTopics'][$i]['spammywords']      = "" ;
		$topicData['untrustedTopics'][$i]['spammywordsCNT']   = 0  ;
		$topicData['untrustedTopics'][$i]['whoisip']          = "" ;
		$topicData['untrustedTopics'][$i]['stopforumspamURL'] = "" ;
		// $topicData['untrustedTopics'][$i]['D_numPosts']         = 0  ;

		$topicText = $topicData['untrustedTopics'][$i]['topic'];
		$lastPoster = $topicData['untrustedTopics'][$i]['lastPostAuthor'] ;

		// Get a list of all the untrusted posts made by this untrusted user.
		$postsFromThisUser = [];
		for($p=0; $p<sizeof($topicData['untrustedTopics']); $p+=1){
			if( $topicData['untrustedTopics'][$p]['lastPostAuthor']==$lastPoster ){
				array_push($postsFromThisUser, $topicData['untrustedTopics'][$p]);
			}
		}

		$numPosts = sizeof($postsFromThisUser);
		if( sizeof($postsFromThisUser) > 1 ){
			$min_unix = strtotime( min(array_column($postsFromThisUser, 'lastPostDate')) );
			$max_unix = strtotime( max(array_column($postsFromThisUser, 'lastPostDate')) );
			// $topicData['untrustedTopics'][$i]['D_min_unix']         = $min_unix;
			// $topicData['untrustedTopics'][$i]['D_max_unix']         = $max_unix;
			// $topicData['untrustedTopics'][$i]['D_timeRange']        = $timeRange_minutes;
			$timeRange=(abs($max_unix - $min_unix));
			$timeRange_minutes = $timeRange / 60;
			$topicData['untrustedTopics'][$i]['D_timeRange_minutes']        = $timeRange_minutes;
			$postRate=$timeRange_minutes/$numPosts;
		}
		else{
			$postRate=1;
		}

		// Get the IP address.
		$ipinfo=getPostIPaddress( $topicData['untrustedTopics'][$i] ) ;
		$topicData['untrustedTopics'][$i]['postip']  = $ipinfo['ip'];
		$topicData['untrustedTopics'][$i]['postips'] = $ipinfo['ips'];

		// Count spammy words.
		$spammy = spammyRating( $topicText ) ;
		$spammy_total = $spammy['total'];
		$spammy_words = implode(',', $spammy['words']);

	// !!!!!!!!!!!!!!!!
	// START SPAM CHECK
	// !!!!!!!!!!!!!!!!

		// ********************************
		// Post from a known spam account??
		// ********************************
		if( in_array( $lastPoster , $knownSpamAccounts) ){
			$deleteThisPost = 1;
			$deletionReason = 'KNOWN SPAMMER';
		}

		// **************
		// Mass posting??
		// **************
		// At least 3, post rate less than 1 (meaning more than one post per minute.)
		else if( $numPosts >= 3 && $postRate < 1 ){
			$deleteThisPost = 1;
			$deletionReason = 'MASS POST';

			// Also add the user to the known spammers list (ARRAY ENTRY).
			$NEWKNOWNSPAMMER=1;
			if(!in_array($lastPoster, $knownSpamAccounts)){
				array_push($knownSpamAccounts, $lastPoster);
			}
		}

		// *******************
		// Spammy word count??
		// *******************
		else if($spammy_total >= 2){
			$deleteThisPost = 1;
			$deletionReason = 'SPAMMY TEXT';
			array_push($usersWithSpammyPosts, $lastPoster);

			// Very spammy? Almost certainly a spammer.
			if($spammy_total >= 4){
				// Also add the user to the known spammers list (ARRAY ENTRY).
				$NEWKNOWNSPAMMER=1;
				if(!in_array($lastPoster, $knownSpamAccounts)){
					array_push($knownSpamAccounts, $lastPoster);
				}
			}
		}
		// In Cyrillic / Russian?
		// https://www.key-shortcut.com/en/writing-systems/abv-cyrillic-alphabet
		// https://stackoverflow.com/a/16130169
		// https://stackoverflow.com/a/3212339
		else{
			// if( preg_match('/[А-Яа-яЁё]/u', $text) ) {
			if( preg_match( '/[\p{Cyrillic}]/u', $topicText) ) {
				$deleteThisPost = 1;
				$deletionReason = 'CYRILLIC SCRIPT';
			}
		}

		// NOT YET WORKING
		// ***********
		// Banned IP??
		// ***********
		// Check if the last post IP address is from a banned IP.
		// else if(1==0){
			// $deleteThisPost = 1;
			// $deletionReason = 'IP BAN';
		// }

		// NOT YET WORKING
		// *****************
		// Banned IP range??
		// *****************
		// Check if the last post IP address is from a banned IP range.
		// else if(1==0){
			// $deleteThisPost = 1;
			// $deletionReason = 'IP RANGE BAN';
		// }

	// !!!!!!!!!!!!!!!!!!!!!!!!!!!
	// UPDATE THE IN-MEMORY RECORD
	// !!!!!!!!!!!!!!!!!!!!!!!!!!!
		$topicData['untrustedTopics'][$i]['deletionReason']   = $deletionReason;
		$topicData['untrustedTopics'][$i]['NEWKNOWNSPAMMER']  = $NEWKNOWNSPAMMER;
		$topicData['untrustedTopics'][$i]['spammywords'   ]   = $spammy_words;
		$topicData['untrustedTopics'][$i]['spammywordsCNT']   = $spammy_total;
		$topicData['untrustedTopics'][$i]['whoisip']          = 'https://whoisip.ovh/'.$topicData['untrustedTopics'][$i]['postip']               ;
		$topicData['untrustedTopics'][$i]['stopforumspamURL'] = 'https://stopforumspam.com/ipcheck/'.$topicData['untrustedTopics'][$i]['postip'] ;
		// $topicData['untrustedTopics'][$i]['D_numPosts']       = $numPosts  ;
		// $topicData['untrustedTopics'][$i]['D_rate']           = $postRate  ;

	// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	// SET DELETION FLAG IF APPLICABLE
	// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		// If applicable set the deleteThis flag. (Do not delete yet.)
		if($deleteFlaggedPosts){
			if( $deleteThisPost == 1 ){
				// Set the deleteThis flag.
				$topicData['untrustedTopics'][$i]['deleteThis'    ]   = $deleteThisPost;
			}
		}
	}

	// Separate the non-deleted.
	$topicData['untrustedTopics2'] = array_filter(
		$topicData['untrustedTopics'],
		function ($key, $val) { return $key['deleteThis']==0; }
		,ARRAY_FILTER_USE_BOTH
	);

	// Separate the deleted.
	$topicData['deleted'] = array_filter(
		$topicData['untrustedTopics'],
		function ($key, $val) { return $key['deleteThis']==1; }
		,ARRAY_FILTER_USE_BOTH
	);

	// Reindex deleted.
	$topicData['deleted'] = array_values($topicData['deleted']);

	$postsDeletedCount=0;

	// Now, actually DELETE the posts that were marked as deleteThis.
	for($i=0; $i<sizeof($topicData['deleted']); $i++){
		if($deleteFlaggedPosts){
			// deletePost($topicData['untrustedTopics'][$i]);
			if( $topicData['deleted'][$i]['deleteThis'] == 1 ){
				// Delete the post.
				deletePost($topicData['deleted'][$i]);
				$postsDeletedCount+=1;

				// Was this a mass post or otherwise set as a known spammer??
				// if( $topicData['deleted'][$i]['deletionReason'] == 'MASS POST' ){
				if( $topicData['deleted'][$i]['NEWKNOWNSPAMMER'] == 1 ){
					// Also add the user to the known spammers list (DATABASE ENTRY)
					addKnownSpammer( $topicData['deleted'][$i]['lastPostAuthor'] );
				}

				// Too many spammy posts for this user in the scan?
				if(
					isset( array_count_values( $usersWithSpammyPosts )
						[
							$topicData['deleted'][$i]['lastPostAuthor']
						]
					)
				){
					// Also add the user to the known spammers list (DATABASE ENTRY)
					// print_r(array_count_values($array)['1']);
					addKnownSpammer( $topicData['deleted'][$i]['lastPostAuthor'] );
				}
			}

		}
	}

	// Set the non-deleted as the untrusted.
	$topicData['untrustedTopics'] = array_values($topicData['untrustedTopics2']);
	// Unset the old non-deleted.
	unset($topicData['untrustedTopics2']);

	$topicData['postsDeletedCount'] = $postsDeletedCount;

	// Finally, return the data.
	return $topicData ;
}
function deletePost($record){
	global $cookieData;
	$reason = $record['deletionReason'];
	$cookie_file_path = dirname(__FILE__).'/cookie.txt';

	$mode = 'delete' ; // $topicRecord['mode'] ;
	$f    = $record['f']    ;
	$p    = $record['p']    ;

	$postDeleteURL  = "http://uzebox.org/forums/posting.php?f=$f&mode=$mode&p=$p";

	// Get to the post delete confirmation screen.
	$ch = curl_init();
	$options = [
		CURLOPT_URL            => $postDeleteURL ,
		CURLOPT_HTTPGET        => 1 ,
		CURLOPT_POST           => 0 ,
		CURLOPT_POSTFIELDS     => null ,
		CURLOPT_COOKIEJAR      => $cookie_file_path ,
		CURLOPT_COOKIEFILE     => $cookie_file_path ,
		CURLOPT_HEADER         => 1 ,
		CURLOPT_NOBODY         => 0 ,
		CURLOPT_COOKIESESSION  => 1 ,
		CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ,
		CURLOPT_RETURNTRANSFER => 1 ,
		CURLOPT_REFERER        => $postDeleteURL ,
		CURLOPT_FOLLOWLOCATION => 1 ,
	];
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);

	$dom = new IvoPetkov\HTML5DOMDocument();
	$dom->loadHTML($result);

	$formAction = $dom
		->querySelectorAll('#confirm')->item(0)
		->getAttributes()['action'];
	;
	// Get the values provided in the href.
	$qs = array();
	parse_str( parse_url( $formAction, PHP_URL_QUERY), $qs );

	$postFields = array(
		'delete_reason'    => $dom->querySelector('#delete_reason')->getAttributes()['value'] ,
		'p'                => $dom->querySelector('#confirm')      ->querySelector('input[name="p"]')               ->getAttributes()['value'] ,
		'f'                => $dom->querySelector('#confirm')      ->querySelector('input[name="f"]')               ->getAttributes()['value'] ,
		'mode'             => $dom->querySelector('#confirm')      ->querySelector('input[name="mode"]')            ->getAttributes()['value'] ,
		'delete_permanent' => $dom->querySelector('#confirm')      ->querySelector('input[name="delete_permanent"]')->getAttributes()['value'] ,
		'confirm_uid'      => $dom->querySelector('#confirm')      ->querySelector('input[name="confirm_uid"]')     ->getAttributes()['value'] ,
		'sess'             => $dom->querySelector('#confirm')      ->querySelector('input[name="sess"]')            ->getAttributes()['value'] ,
		'sid'              => $dom->querySelector('#confirm')      ->querySelector('input[name="sid"]')             ->getAttributes()['value'] ,
		'confirm'          => $dom->querySelector('#confirm')      ->querySelector('input[name="confirm"]')         ->getAttributes()['value'] ,
		// 'cancel'           => $dom->querySelector('#confirm')->querySelector('input[name="cancel"]')          ->getAttributes()['value'] ,
	);
	$postFields['delete_reason'] = "Forum anti-spam bot: " . $reason;
	$postConfirmDeleteURL        = "http://uzebox.org/forums/" . substr($formAction, 2);

	// Now submit the form with all the required details.
	$options = [
		CURLOPT_URL            => $postConfirmDeleteURL ,
		CURLOPT_HTTPGET        => 0 ,
		CURLOPT_POST           => 1 ,
		CURLOPT_POSTFIELDS     => $postFields ,
		CURLOPT_COOKIEJAR      => $cookie_file_path ,
		CURLOPT_COOKIEFILE     => $cookie_file_path ,
		CURLOPT_HEADER         => 0 ,
		CURLOPT_NOBODY         => 0 ,
		CURLOPT_COOKIESESSION  => 1 ,
		CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ,
		CURLOPT_RETURNTRANSFER => 1 ,
		CURLOPT_REFERER        => $postDeleteURL ,
		CURLOPT_FOLLOWLOCATION => 1 ,
	];
	curl_setopt_array($ch, $options);

	// $result2 = curl_exec($ch);
	curl_exec($ch);

	curl_close($ch); $ch=null;

	// Create a record of the post deletion.
	addDeletionRecord( $record );
}
function addDeletionRecord( $record ){
	global $_db_file ;
	global $loggedOnUsername ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	$s_SQL1='
		INSERT INTO "deletions" (
			"id"                          ,

			"topic_title"                 ,

			"topicoriginalauthorusername" ,

			"topiclastauthorusername"     ,
			"topiclastpostnumber"         ,
			"topiclastpostdate"           ,

			"forumname"                   ,

			"forumnumber"                 ,
			"topicnumber"                 ,
			"postipaddress"               ,

			"reasonfordeletion"           ,
			"deletedbyusername"           ,
			"deletiondate"                ,
			"moderatorIP"
		)
		VALUES      (
			:id                          ,

			:topic_title                 ,

			:topicoriginalauthorusername ,

			:topiclastauthorusername     ,
			:topiclastpostnumber         ,
			:topiclastpostdate           ,

			:forumname                   ,

			:forumnumber                 ,
			:topicnumber                 ,
			:postipaddress               ,

			:reasonfordeletion           ,
			:deletedbyusername           ,
			 datetime(\'now\', \'localtime\'),
			 -- datetime(\'now\'),
			 :moderatorIP
		)
	;';

	$prp1     = $dbhandle->prepare($s_SQL1);
	$dbhandle->bind(':id'                          , null                                                    ) ;
	$dbhandle->bind(':topic_title'                 , $record['topic']                                        ) ;
	$dbhandle->bind(':topicoriginalauthorusername' , $record['author']                                       ) ;
	$dbhandle->bind(':topiclastauthorusername'     , $record['lastPostAuthor']                               ) ;
	$dbhandle->bind(':topiclastpostnumber'         , $record['p']                                            ) ;
	$dbhandle->bind(':topiclastpostdate'           , date("Y-m-d H:i:s", strtotime($record['lastPostDate'])) ) ;
	$dbhandle->bind(':forumname'                   , $record['forumname']                                    ) ;
	$dbhandle->bind(':forumnumber'                 , $record['f']                                            ) ;
	$dbhandle->bind(':topicnumber'                 , $record['t']                                            ) ;
	$dbhandle->bind(':postipaddress'               , $record['postip']                                       ) ;
	$dbhandle->bind(':reasonfordeletion'           , $record['deletionReason']                               ) ;
	$dbhandle->bind(':deletedbyusername'           , $loggedOnUsername                                       ) ;
	$dbhandle->bind(':moderatorIP'                 , $_SERVER['REMOTE_ADDR']                                 ) ;
	$retval1  = $dbhandle->execute();
}
function getrecentDeletions($numPosts){
	$numPosts = intval($numPosts);

	global $_db_file ;
	$thisRecord = json_decode($_POST['thisRecord'], true) ;
	$username = $thisRecord['lastPostAuthor'] ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	// Check if the username has already been added.
	$s_SQL1='
		SELECT
		  topicLastPostDate           AS lastPostDate
		, topicLastAuthorUsername     AS lastPostAuthor
		, postIpAddress               AS postip
		, reasonForDeletion           AS deletionReason
		, forumName                   AS forumname
		, topic_title                 AS topic
		, topicOriginalAuthorUsername AS author
		, deletionDate                AS deletionDate
		FROM deletions
		WHERE 1=1
		ORDER BY deletionDate DESC
		LIMIT '.$numPosts.'
	;';
	$prp1     = $dbhandle->prepare($s_SQL1);
	// $dbhandle->bind(':numPosts' , $numPosts ) ;
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	return $results1;
}
function getLatestDeletionsInfo(){
	// $numPosts = intval($numPosts);

	global $_db_file ;
	// $thisRecord = json_decode($_POST['thisRecord'], true) ;
	// $username = $thisRecord['lastPostAuthor'] ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	// Check if the username has already been added.
	$s_SQL1="
		SELECT
			tmp.topiclastauthorusername AS topiclastauthorusername ,
			tmp.deletionCount           AS deletionCount           ,
			tmp.topicLastPostDate       AS LASTPOST

		FROM (
			SELECT
				topiclastauthorusername        AS topiclastauthorusername ,
				Count(topiclastauthorusername) AS deletionCount           ,
				MAX  (topicLastPostDate)       AS topicLastPostDate
				-- (topicLastPostDate)       AS topicLastPostDate
			FROM deletions
			GROUP BY topiclastauthorusername
		) tmp

		WHERE
			tmp.deletionCount > 0
			AND (topicLastPostDate) >= DATETIME('now', '-5 days')

		--ORDER  BY tmp.deletionCount DESC
		ORDER  BY tmp.topicLastPostDate DESC , tmp.deletionCount DESC

		LIMIT  50
	;";
	$prp1     = $dbhandle->prepare($s_SQL1);
	// $dbhandle->bind(':numPosts' , $numPosts ) ;
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	return $results1;
}
function spammyRating($text){
	global $spammyWords;

	$text = strtolower($text);

	$total = 0  ;
	$words = [] ;

	for($i=0; $i<sizeof($spammyWords); $i+=1){
		if( substr_count($text, $spammyWords[$i]) )    {
			$total+=1;
			array_push($words, $spammyWords[$i]);
		}
	}

	return [
		'total' => $total ,
		'words' => $words ,
	];
}
function getPostIPaddress($topicRecord){
	global $cookieData;

	$i    = 'main'               ;
	$mode = 'post_details'       ;
	$f    = $topicRecord['f']    ;
	$p    = $topicRecord['p']    ;
	$sid  = $cookieData['sid']   ;

	$postDetailsUrl    = "http://uzebox.org/forums/mcp.php?i=$i&mode=$mode&f=$f&p=$p&sid=$sid";
	$cookie_file_path = dirname(__FILE__).'/cookie.txt';

	$ch = curl_init();
	$options = array(
		CURLOPT_URL            => $postDetailsUrl   ,
		CURLOPT_HEADER         => false              ,
		CURLOPT_RETURNTRANSFER => true               ,

		CURLOPT_COOKIEJAR      => $cookie_file_path ,
		CURLOPT_COOKIESESSION  => 1                  ,
		CURLOPT_COOKIEFILE     => $cookie_file_path  ,

		CURLOPT_REFERER        => $postDetailsUrl            ,
		CURLOPT_USERAGENT      => $_SERVER['HTTP_USER_AGENT'] ,
	);
	curl_setopt_array($ch, $options);
	$result = curl_exec($ch);
	curl_close($ch);
	$ch=null;

	$dom = new IvoPetkov\HTML5DOMDocument();
	$dom->loadHTML($result);
	$ip = $dom
		->querySelectorAll('#ip')->item(0)
		->querySelectorAll('div')->item(0)
		->querySelectorAll('a')->item(0)
		->innerHTML;

	$ips=[];
	$ips_elems = $dom
		->querySelectorAll('#ip')->item(0)
		->querySelectorAll('div')->item(0)
		->querySelectorAll('table')->item(1)
		->querySelectorAll('tbody')->item(0)
		->querySelectorAll('tr')
		;
	foreach($ips_elems as $key => $val){
		// The ip.
		$thisIp = $ips_elems[$key]
			->querySelectorAll('td')->item(0)
			->querySelectorAll('a')->item(0)
			->innerHTML;
		// The number of times the user posted from the ip.
		$postsFromIp = $ips_elems[$key]
			->querySelectorAll('td')->item(1)
			->innerHTML;

		array_push($ips, $thisIp);
	}

	$info = [
		'ip'  => $ip  ,
		'ips' => $ips ,
	];

	return $info;
}
function addKnownSpammer($username){
	// Add the spammer to the database.
	global $_db_file ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	// Check if the username has already been added.
	$s_SQL1='
		SELECT username
		FROM knownSpamAccounts
		WHERE username = :username
	;';
	$prp1     = $dbhandle->prepare($s_SQL1);
	$dbhandle->bind(':username' , $username ) ;
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	// Add the name if it does not already exist.
	if( !sizeof($results1) ){
		// Add the username.
		$s_SQL2='
		INSERT INTO knownSpamAccounts
		     ( id  , tstamp           ,  username )
		VALUES
		     ( null, CURRENT_TIMESTAMP, :username )
		;';
		$prp2     = $dbhandle->prepare($s_SQL2);
		$dbhandle->bind(':username' , $username ) ;
		$retval2  = $dbhandle->execute();
	}
}

function createInitialDatabase(){
	// Pull in some globals.
	global $_appdir;
	global $_db_file;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO($_db_file) or exit("cannot open the database");

	// Add individual queries from one file.
	$populateQuerys = array();
	$queryFile      = file_get_contents($_appdir."/db_init/createInitialDatabase.sql") ;
	$queries        = explode(";", $queryFile);

	// Now do the exploded queries.
	for($i=0; $i<sizeof($queries); $i++){
		$queries[$i] .= ";";
		$s_SQL1     = $queries[$i]                ;
		$prp1       = $dbhandle->prepare($s_SQL1) ;
		$retval1    = $dbhandle->execute()        ;
	}
}
function ajax_sql_data_backups(){
	global $_db_file ;

	// Create the file. By trying to open the file it will be created!
	$dbhandle = new sqlite3_DB_PDO( $_db_file ) or exit("cannot open the database");

	$s_SQL1='SELECT * FROM deletions;';
	$prp1     = $dbhandle->prepare($s_SQL1);
	$retval1  = $dbhandle->execute();
	$results1 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	$s_SQL2='SELECT * FROM trustedAccounts;';
	$prp2     = $dbhandle->prepare($s_SQL2);
	$retval2  = $dbhandle->execute();
	$results2 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	$s_SQL3='SELECT * FROM knownSpamAccounts;';
	$prp3     = $dbhandle->prepare($s_SQL3);
	$retval3  = $dbhandle->execute();
	$results3 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	$s_SQL4='SELECT * FROM spammyWords;';
	$prp4     = $dbhandle->prepare($s_SQL4);
	$retval4  = $dbhandle->execute();
	$results4 = $dbhandle->statement->fetchAll(PDO::FETCH_ASSOC) ;

	// Create the deletions text.
	$text1='INSERT INTO deletions (id, topic_title, topicOriginalAuthorUsername, topicLastAuthorUsername, topicLastpostNumber, topicLastPostDate, forumName, forumNumber, topicNumber, postIpAddress, reasonForDeletion, deletedByUsername, deletionDate, moderatorIP)';
	$text1.="\nVALUES \n";
	for($i=0; $i<sizeof($results1); $i+=1){
		$rec=$results1[$i];

		$text1.='(';

		$text1.=$rec["topic_title"]                !=NULL ? ('"'.$rec["topic_title"]                .'"') : "null"; $text1.=', ';
		$text1.=$rec["topicOriginalAuthorUsername"]!=NULL ? ('"'.$rec["topicOriginalAuthorUsername"].'"') : "null"; $text1.=', ';
		$text1.=$rec["topicLastAuthorUsername"]    !=NULL ? ('"'.$rec["topicLastAuthorUsername"]    .'"') : "null"; $text1.=', ';
		$text1.=$rec["topicLastpostNumber"]        !=NULL ? ('"'.$rec["topicLastpostNumber"]        .'"') : "null"; $text1.=', ';
		$text1.=$rec["topicLastPostDate"]          !=NULL ? ('"'.$rec["topicLastPostDate"]          .'"') : "null"; $text1.=', ';
		$text1.=$rec["forumName"]                  !=NULL ? ('"'.$rec["forumName"]                  .'"') : "null"; $text1.=', ';
		$text1.=$rec["forumNumber"]                !=NULL ? ('"'.$rec["forumNumber"]                .'"') : "null"; $text1.=', ';
		$text1.=$rec["topicNumber"]                !=NULL ? ('"'.$rec["topicNumber"]                .'"') : "null"; $text1.=', ';
		$text1.=$rec["postIpAddress"]              !=NULL ? ('"'.$rec["postIpAddress"]              .'"') : "null"; $text1.=', ';
		$text1.=$rec["reasonForDeletion"]          !=NULL ? ('"'.$rec["reasonForDeletion"]          .'"') : "null"; $text1.=', ';
		$text1.=$rec["deletedByUsername"]          !=NULL ? ('"'.$rec["deletedByUsername"]          .'"') : "null"; $text1.=', ';
		$text1.=$rec["deletionDate"]               !=NULL ? ('"'.$rec["deletionDate"]               .'"') : "null"; $text1.=', ';
		$text1.=$rec["moderatorIP"]                !=NULL ? ('"'.$rec["moderatorIP"]                .'"') : "null"; $text1.=' ';

		$text1.=')';

		// If this is the last line then use a semi-colon.
		if($i+1 >= sizeof($results1)){ $text1.="\n;\n"; }
		// If this is NOT the last line then use a comma.
		else{ $text1.=",\n"; }
	}

	// Create the trustedAccounts text.
	$text2='INSERT INTO trustedAccounts (id, tstamp, username)';
	$text2.="\nVALUES \n";
	for($i=0; $i<sizeof($results2); $i+=1){
		$rec=$results2[$i];

		$text2.='(';

		$text2.=$rec["tstamp"]   !=NULL ? ('"'.$rec["tstamp"]   .'"') : "null"; $text2.=', ';
		$text2.=$rec["username"] !=NULL ? ('"'.$rec["username"] .'"') : "null"; $text2.=' ' ;

		$text2.=')';

		// If this is the last line then use a semi-colon.
		if($i+1 >= sizeof($results2)){ $text2.="\n;\n"; }

		// If this is NOT the last line then use a comma.
		else{ $text2.=",\n"; }
	}

	// Create the knownSpamAccounts text.
	$text3='INSERT INTO knownSpamAccounts (id, tstamp, username)';
	$text3.="\nVALUES \n";
	for($i=0; $i<sizeof($results3); $i+=1){
		$rec=$results3[$i];

		$text3.='(';

		$text3.=$rec["tstamp"]   !=NULL ? ('"'.$rec["tstamp"]   .'"') : "null"; $text3.=', ';
		$text3.=$rec["username"] !=NULL ? ('"'.$rec["username"] .'"') : "null"; $text3.=' ' ;

		$text3.=')';

		// If this is the last line then use a semi-colon.
		if($i+1 >= sizeof($results3)){ $text3.="\n;\n"; }

		// If this is NOT the last line then use a comma.
		else{ $text3.=",\n"; }
	}

	// Create the spammyWords text.
	$text4="INSERT INTO spammyWords (tstamp, word, category)";
	$text4.="\nVALUES \n";
	for($i=0; $i<sizeof($results4); $i+=1){
		$rec=$results4[$i];

		$text4.='(';

		$text4.=$rec["tstamp"]   !=NULL ? ('"'.$rec["tstamp"]   .'"') : "null"; $text4.=', ';
		$text4.=$rec["word"]     !=NULL ? ('"'.$rec["word"]     .'"') : "null"; $text4.=',' ;
		$text4.=$rec["category"] !=NULL ? ('"'.$rec["category"] .'"') : "null"; $text4.=' ' ;

		$text4.=')';

		// If this is the last line then use a semi-colon.
		if($i+1 >= sizeof($results4)){ $text4.="\n;\n"; }
		// If this is NOT the last line then use a comma.
		else{ $text4.=", \n"; }
	}

	file_put_contents("api/db_data_backup.sql", $text1 . $text2 . $text3 . $text4);
}

// **************
// DATABASE CLASS
// **************
class sqlite3_DB_PDO{
	public $dbh;              // The DB handle.
	public $statement;        // The prepared statement handle.

	public function __construct( $file_db_loc ){
		// Set timezone.
		// date_default_timezone_set('America/Detroit');

		try{
			// Connect to the database.
			$this->dbh = new PDO("sqlite:".$file_db_loc);
			// $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
			$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
			// $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch(PDOException $e){
			echo "ERROR ON DB FILE OPEN:"; print_r( $e );
		}
	}

	public function prepare($query){
		try                   {
			$this->statement = $this->dbh->prepare($query);

    		// echo "errorInfo: "; print_r($this->dbh->errorInfo()); echo "<br>";

			return $this->statement;
		}
		catch(PDOException $e){
			echo "ERROR ON PREPARE:"; print_r( $e );
			return ($e);
		}
	}

	public function bind($param, $value, $type = null){
		if(!$this->statement){ return "FAILURE TO BIND"; }

		//Example: $db_pdo->bind(':fname', 'Jenny');
		if (is_null($type)) {
			switch (true) {
				case is_int($value) : { $type = PDO::PARAM_INT ; break; }
				case is_bool($value): { $type = PDO::PARAM_BOOL; break; }
				case is_null($value): { $type = PDO::PARAM_NULL; break; }
				default             : { $type = PDO::PARAM_STR ;        }
			}
		}

		try                   { $this->statement->bindValue($param, $value, $type); }
		catch(PDOException $e){
			echo "ERROR ON BIND:"; print_r( $e );
			return $e;
		}
	}

	public function execute()			{
		try                   { return $this->statement->execute(); }
		catch(PDOException $e){
			echo "ERROR ON EXECUTE:"; print_r( $e );
			/* print_r( debug_backtrace()[1] ); */
		}
	}

	public function getErrors($e)			{
		// #define SQLITE_OK           0   /* Successful result */
		// #define SQLITE_ERROR        1   /* SQL error or missing database */
		// #define SQLITE_INTERNAL     2   /* An internal logic error in SQLite */
		// #define SQLITE_PERM         3   /* Access permission denied */
		// #define SQLITE_ABORT        4   /* Callback routine requested an abort */
		// #define SQLITE_BUSY         5   /* The database file is locked */
		// #define SQLITE_LOCKED       6   /* A table in the database is locked */
		// #define SQLITE_NOMEM        7   /* A malloc() failed */
		// #define SQLITE_READONLY     8   /* Attempt to write a readonly database */
		// #define SQLITE_INTERRUPT    9   /* Operation terminated by sqlite_interrupt() */
		// #define SQLITE_IOERR       10   /* Some kind of disk I/O error occurred */
		// #define SQLITE_CORRUPT     11   /* The database disk image is malformed */
		// #define SQLITE_NOTFOUND    12   /* (Internal Only) Table or record not found */
		// #define SQLITE_FULL        13   /* Insertion failed because database is full */
		// #define SQLITE_CANTOPEN    14   /* Unable to open the database file */
		// #define SQLITE_PROTOCOL    15   /* Database lock protocol error */
		// #define SQLITE_EMPTY       16   /* (Internal Only) Database table is empty */
		// #define SQLITE_SCHEMA      17   /* The database schema changed */
		// #define SQLITE_TOOBIG      18   /* Too much data for one row of a table */
		// #define SQLITE_CONSTRAINT  19   /* Abort due to contraint violation */
		// #define SQLITE_MISMATCH    20   /* Data type mismatch */
		// #define SQLITE_MISUSE      21   /* Library used incorrectly */
		// #define SQLITE_NOLFS       22   /* Uses OS features not supported on host */
		// #define SQLITE_AUTH        23   /* Authorization denied */
		// #define SQLITE_ROW         100  /* sqlite_step() has another row ready */
		// #define SQLITE_DONE        101  /* sqlite_step() has finished executing */
	}

}

?>