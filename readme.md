Github API Tools
================

This repository is a set of tools aimed at letting course instructors configure their github organization accounts based on information in CSV files.  It allows the creation of repositories and management of teams.  This is based on, and this repo is forked from, the [KnpLabs/php-github-api](https://github.com/KnpLabs/php-github-api) repository.  This script will never delete repositories or teams.  However, it intentionally **MAY** remove users from teams.


Installation
------------

First, the github-api-tools must be configured.  The quick version is below; the full description can be found in the [original readme](readme-original.md) ([original version from the upstream repo](https://github.com/KnpLabs/php-github-api/blob/master/README.markdown)).

The first step to use `php-github-api` is to download composer:

```bash
$ curl -s http://getcomposer.org/installer | php
```

Then we have to install our dependencies using:
```bash
$ php composer.phar install
```

Next, the user will need to create a personal access token from [https://github.com/settings/applications](https://github.com/settings/applications).  When creating that token, it ***MUST*** be given the `admin:org` scope, as the purpose of this repo's main script is to allow the modifications of the teams and repositories in an organization.  The API token will need to be remembered, as it is entered each time the program is run.

This script requires being able to execute a PHP script from the command line.  Under recent Ubuntu installations, this is provided via installing the `php5-cli` package.

At that point, PHP can be run from the command-line via:

```bash
$ php main.php <options>
```

Usage
-----

There are two exclusive modes: create repositories and manage teams.  All the other options provide parameters to one of these two modes.

**Modes**

* `-create-repos`: Create the repositories specified in the provided CSV file.
* `-manage-teams`: Manage teams as per the specifications in the provided CSV file.

**General options**

* `-org <name>`: specify the organization under which the repositories will be created or where the teams will be managed.  The user must have administration access to that organization (and the token must have that scope, as described above).
* `-token <token>`: specify the Github personal access token to be used for authentication; see above for how to create it.  If this is not specified, the script will prompt for the token.
* `-public`: whether to make the created repositories public; this is *not* the default.
* `-private`: whether to make the created repositories private.  This requires that the organization have that many private repositories available.  This is the default.
* `-nowatch` or `-unwatch`: whether to have the user (who is identified by the provided token) specifically unwatch the repositories.  The default, if nothing is specified, is that a user watches a repo s/he creates; this option allows one to unwatch a repo.

**CSV file options**

* `-csv <file>`: the name of the CSV file that provides the information for the script to run.  See below for the format.  Note that `-csvfile <file>` is another name for this option.
* `-reposcol <num>`: the number of the column (indexed from 1) that provides the name of the repositories to create; if not specfied, the script will search for columns with the names: 'repos', 'repo', 'repository', and 'repositories'.
* `-descscol <num>`: the number of the column (indexed from 1) that provides the descriptions of the repositories to create; if not specfied, the script will search for columns with the names: 'desc', 'descs', 'description', 'descriptions'.
* `-userscol <num>`: the number of the column (indexed from 1) that provides the github userids for managing the teams; if not specfied, the script will search for columns with the names: 'user', 'users', 'username', 'usernames'.
* `-teamscol <num>`: the number of the column (indexed from 1) that provides the names of the teams to manage; if not specfied, the script will search for columns with the names: 'team', 'teams'.

CSV file format
---------------

The CSV file must have the top row be the column header titles.  Which column to find the information in can either be provided via the command line options (the "CSV file options", above), or by naming the columns per the convention also mentioned above.  CSV parsing is provided via the `fgetcsv()` function in PHP, so it should be general enough to handle most CSV formats.

For all of the columns, the user can specify which column holds the data (via `-reposcol`, `-desccol`, etc.).  If not provided, the script will attempt to determine which column based on the column header name; if there are multiple columns, then the first encountered (the left-most) will be used.

The create repository mode requires only one column (the repository name), and can optionally have a description column.  If no description column is provided, then "no description" is provided as the description for each repo.  Additional rows with the same repository name will be ignored.

The manage teams mode requires three columns: repository, github username, team name.  Additional rows will have no effect.


Example
-------

Consider the following data (which is not a CSV file, obviously):


id | user  | repo  | desc        | group
---|-------|-------|-------------|-------
1  | user1 | repo1 | Lorem ipsum | group1
2  | user2 | repo1 | Lorem ipsum | group1
3  | user2 | repo2 | Lorem ipsum | group2
4  | user3 | repo2 | Lorem ipsum | group2
5  | user4 | repo3 | Lorem ipsum | group3
6  | user5 | repo3 | Lorem ipsum | group3
7  | user6 | repo1 | Lorem ipsum | group2
8  | user7 | repo2 | Lorem ipsum | group2
9  | user8 | repo3 | Lorem ipsum | group3

The actual CSV file would be:

```
id,user,repo,desc,group
1,user1,repo1,"Lorem ipsum",group1
2,user2,repo1,"Lorem ipsum",group1
3,user2,repo2,"Lorem ipsum",group2
4,user3,repo2,"Lorem ipsum",group2
5,user4,repo3,"Lorem ipsum",group3
6,user5,repo3,"Lorem ipsum",group3
7,user6,repo1,"Lorem ipsum",group2
8,user6,repo2,"Lorem ipsum",group2
9,user6,repo3,"Lorem ipsum",group3
```

Note that the last column header is 'group', not 'team'; the former is not recognized automatically, while the latter is.

First, one would run this to create the repositories:

```bash
$ php main.php -create-repos -org superorg -token 1234567890 -csv data.csv -nowatch
```

The script will find the 'repo' column, and create all the repos listed there; there are three such repos to be created (repo1, repo2, and repo3).  If they already exist, then it does nothing (but it does indicate that they already exist).  The user specifically does NOT want to watch the repositories that were created, hence the `-nowatch` flag at the end.  Note that this assumes that the organization name is 'superorg', and that the token provided has already been created properly (see above for details).

Next, one could run the script in the following manner to manage the groups:

```bash
$ php main.php -manage-teams -org superorg -teamscol 5 -csv data.csv
```

This execution does not specify a token on the command line, so the script will prompt for it.  And because the 'group' column header is not known to the script, it needs to be specified via the `-teamscol` option.

This second execution will perform three different funtions:

* If the teams do not exist, then they will be created.  There are three teams to be created (group1, group2, and group3).  The teams have push access by default (Github defaults to pull).
* Looking at just the user and team (group) columns, it ensures that the **ONLY** members of the team are the ones listed.  In the data from above, group1 will contain only user1 and user2; group 2 will contain user2, user3, user6, and user7; group3 will contain user4, user5, and user8.
* Looking just at the repository and team (group) columns, it will ensure that the appropriate teams can access those repositories, **AND NO OTHER REPOS**.  This means that it will **REMOVE** access to a repo if a team can access a repo not listed.  The intent here was probably to have group1 able to access repo1, group2 access repo2, and group3 access repo3.  However, a likely typo in line ID 7 gives group2 access to repo1.  This means *anybody* in group2 can access both repo2 (from lines 3, 4, and 8), as well as repo1 (from line 7).

From the data provided above, once modified to have real values for the token, organization, and usernames, it worked as desired.

Notes
-----

* If the repositories are not created when setting up the teams, the script will crash.
* If you just want to create the teams, but not set up the repos, you can leave the repo name blank (but the repo column must still be present); however, this will REMOVE any repos that the team currently has access to

