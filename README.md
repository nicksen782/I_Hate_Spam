[forumPost]: <http://uzebox.org/forums/viewtopic.php?f=1&t=2873>

# I Hate Spam

## Overview

- Deletes spam on the Uzebox forums in bulk by using filters to detect mass posts, spammy topics, and known spammers.

## Links

Further information is available here:

- [forumPost] - Uzebox forum post concerning the issue we had with spam.

## Method

- PHP script using CURL. The process for deleting a post is handled fully by the script.
- Additionally, there is a simple web interface for some tasks.
- Can be viewed and operated by a cell phone (which is often how I manage it.)

## How It Works

- Initially it will read the list of active topics.
- Then it will separate the topics into two associative arrays. The arrays are 'trusted' and 'untrusted'.
- A post is trusted if the last poster is a known member. The script will compare against an internal array of member names.
- If a post is not trusted then it is untrusted.
- The untrusted list then is walked through and the poster's IP address is gathered through the post details feature.
  - One record at a time:
    - The last poster's username is compared against a list of known spam accounts. If there is a match the post is marked for deletion.
    - The IP is then checked against a list of blocked IP networks (CIDR). If true then the post is marked for deletion.
    - The post is checked for a count of spammy words. A post will be deleted after a certain number and the user auto-added to the known spammers list if another higher number of spammy words is found.
      - If a user posts too many posts within a short time (not normally humanly possible) then the user is auto-added to the known spammers list.
  - If the record has been marked for deletion it is deleted.
        - Additionally, a record of the deletion and some of the post details are recorded to a local database.
- The web interface will display list of recent deleted records and provides an interface to easily add a username to the trusted or untrusted list.

## Installation / Usage

I Hate Spam requires PHP 5.6 or higher to run as well as cURL, SQLite, and the PDO PHP driver for SQLite.
Tested on Ubuntu Linux 18. It may also work on a Windows server but that has not been tested.

Example of how to run the scanner from the command prompt:

```sh
# Change into the directory of the application and run the tool (one line.)
cd /home/user/IHateSpamV4/api && php -d register_argc_argv=1 ihs_p.php ajax_runScan 1 0
```

The application tries to protect the api/db_data_backup.sql and forumCredentials.json files. It uses .htaccess to do this.
If .htaccess files are not allowed (default) you will need to add the following example code to either your apache.conf or your virtual hosts file in /etc/apache2/sites-enabled. This is important because you do not want anything other than the scanner itself to see those files.
```sh
<Directory /var/www/PATH_TO_APPLICATION/IHateSpamV4/>
  AllowOverride All
</Directory>
<Directory /var/www/PATH_TO_APPLICATION/IHateSpamV4/api>
  AllowOverride All
</Directory>
```

There is a simple web interface available which offers some management features.

Additionally, since the PHP script can run independent of the web interface you can schedule the run to be run periodically via cron or another task scheduler.

## NOTE

If you use this software please note that the database structure will be automatically created for you but it will be blank. I do not include the data at this time but it is easy to add.
