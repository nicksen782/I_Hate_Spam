CREATE TABLE 'deletions'
(
-- Primary key.
'id'                          INTEGER PRIMARY KEY AUTOINCREMENT ,

-- Base topic info.
'topic_title'                 VARCHAR  ,

-- Original author.
'topicOriginalAuthorUsername' VARCHAR  ,

-- Latest author and post info.
'topicLastAuthorUsername'     VARCHAR  ,
'topicLastpostNumber'         VARCHAR  ,
'topicLastPostDate'           DATETIME ,

-- Forum used.
'forumName'                   VARCHAR   ,

-- Technical.
'forumNumber'                 VARCHAR    ,
'topicNumber'                 VARCHAR    ,
'postIpAddress'               VARCHAR    ,

-- Deletion logging.
'reasonForDeletion'           VARCHAR    ,
'deletedByUsername'           VARCHAR    ,
'deletionDate'                DATETIME   ,
'moderatorIP'                 VARCHAR
)
;

CREATE TABLE 'errorlog' (
	'id'          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL ,
	'tstamp'      DATETIME ,
	'type'        VARCHAR  ,
	'sessioninfo' VARCHAR  ,
	'misc'        VARCHAR  ,
	'ip'          VARCHAR
)
;

CREATE TABLE 'trustedAccounts' (
	'id'          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL ,
	'tstamp'      DATETIME ,
	'username'    VARCHAR
)
;

CREATE TABLE 'knownSpamAccounts' (
	'id'          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL ,
	'tstamp'      DATETIME ,
	'username'    VARCHAR
)
;

CREATE TABLE 'spammyWords' (
	'id'          INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL ,
	'tstamp'      DATETIME ,
	'word'        VARCHAR  ,
	'category'    VARCHAR
)
;
