<?php

require_once 'vendor/autoload.php';

$createrepos = 0;
$manageteams = 0;

$userorg = false;
$token = false;
$csvfile = false;
$public = false;
$watch = true;

$reposcol = -1;
$descscol = -1;
$userscol = -1;
$teamscol = -1;

checkParams();

// create client and authenticate
$client = new \Github\Client(
    new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
);
$client->authenticate($token,Github\Client::AUTH_HTTP_TOKEN);

// take the appropriate action
if ( $createrepos )
  createRepos($client);
if ( $manageteams )
  manageTeams($client);


function checkParams() {
  global $userorg, $token, $csvfile, $reposcol, $descscol, $argv, $argc, $createrepos, $manageteams, $public, $userscol, $teamscol, $watch;
  for ( $i = 1; $i < $argc; $i++ ) {
    switch($argv[$i]) {
    case "-org":
      $userorg = $argv[++$i];
      break;
    case "-token":
      $token = $argv[++$i];
      break;
    case "-create-repos":
      $createrepos = 1;
      break;
    case "-manage-teams":
      $manageteams = 1;
      break;
    case "-reposcol":
      $reposcol = $argv[++$i];
      if ( !is_numeric($reposcol) )
	die ("The -reposcol value must be numeric\n");
      $reposcol--;
      break;
    case "-descscol":
      $descscol = $argv[++$i];
      if ( !is_numeric($descscol) )
	die ("The -descscol value must be numeric\n");
      $descscol--;
      break;
    case "-teamscol":
      $teamscol = $argv[++$i];
      if ( !is_numeric($teamscol) )
	die ("The -teamscol value must be numeric\n");
      $teamscol--;
      break;
    case "-userscol":
      $userscol = $argv[++$i];
      if ( !is_numeric($userscol) )
	die ("The -userscol value must be numeric\n");
      $userscol--;
      break;
    case "-csvfile":
    case "-csv":
      $csvfile = $argv[++$i];
      if ( !file_exists($csvfile) )
	die ("CSV file '$csvfile' does not exist\n");
      break;
    case "-public":
      $public = true;
      break;
    case "-private":
      $public = false;
      break;
    case "-unwatch":
    case "-nowatch":
      $watch = false;
      break;
    default:
      die ("Unknown parameter: " . $argv[$i] . "\n");
      break;
    }
  }

  if ( !$createrepos && !$manageteams )
    die ("Nothing to do!  You must specify either -create-repos or -manage-teams\n");
  if ( $createrepos && $manageteams )
    die ("You can only specify one mode (-create-repos or -manage-teams), not both\n");
  if ( !$userorg )
    die ("You must specify the github user or organization via -userorg\n");
  if ( !$csvfile )
    die ("You must specify a CSV file to use via -csv\n");
  if ( !$token ) {
    echo "Enter the API token: ";
    $token = readline();
  }
}

function readCSVHeader($fp) {
  global $reposcol, $descscol, $userscol, $teamscol, $createrepos, $manageteams;

  $row = fgetcsv($fp);
  if ( $reposcol != -1 )
    echo "Was supplied the repositories column of " . ($reposcol+1) . "\n";
  if ( $descscol != -1 )
    echo "Was supplied the descriptions column of " . ($descscol+1) . "\n";
  if ( $teamscol != -1 )
    echo "Was supplied the teams column of " . ($teamscol+1) . "\n";
  if ( $userscol != -1 )
    echo "Was supplied the users column of " . ($userscol+1) . "\n";
  for ( $i = 0; $i < count($row); $i++ ) {
    if ( ($reposcol == -1) &&
	 ((strtolower($row[$i]) == "repo") || (strtolower($row[$i]) == "repos") ||
	  (strtolower($row[$i]) == "repository") || (strtolower($row[$i]) == "repositories")) )
      $reposcol = $i;
    if ( ($descscol == -1) &&
	 ((strtolower($row[$i]) == "desc") || (strtolower($row[$i]) == "description") ||
	  (strtolower($row[$i]) == "descs") || (strtolower($row[$i]) == "descriptions")) )
      $descscol = $i;
    if ( ($userscol == -1) &&
	 ((strtolower($row[$i]) == "user") || (strtolower($row[$i]) == "users") ||
	  (strtolower($row[$i]) == "username") || (strtolower($row[$i]) == "usernames")) )
      $userscol = $i;
    if ( ($teamscol == -1) &&
	 ((strtolower($row[$i]) == "team") || (strtolower($row[$i]) == "teams")) )
      $teamscol = $i;
  }
  if ( $reposcol == -1 )
    die ("No repos column found (and none specified via -reposcol)\n");
  else
    echo "Using column " . ($reposcol+1) . " with header '" . $row[$reposcol] . "' as the repository list\n";
  if ( $createrepos )
    if ( $descscol == -1 )
      echo "No description column found (and none specified via -descscol); continuing without descriptions...\n";
    else
      echo "Using column " . ($descscol+1) . " with header '" . $row[$descscol] . "' as the descriptions list\n";
  if ( $manageteams ) {
    if ( $userscol == -1 )
      die ("No users column found (and none specified via -userscol)\n");
    else
      echo "Using column " . ($userscol+1) . " with header '" . $row[$userscol] . "' as the users list\n";
    if ( $teamscol == -1 )
      die ("No teams column found (and none specified via -teamscol)\n");
    else
      echo "Using column " . ($teamscol+1) . " with header '" . $row[$teamscol] . "' as the teams list\n";
  }
}

function createRepos($client) {
  global $reposcol, $descscol, $csvfile, $userorg, $public, $watch;

  try {

    // read in the CSV file header, and determine the row with the repository names
    $fp = fopen($csvfile,"r");
    readCSVHeader($fp);

    // read in the repo data
    $newrepos = array();
    $descriptions = array();
    while ( ($row = fgetcsv($fp)) ) {
      if ( !in_array($row[$reposcol],$newrepos) ) {
	$newrepos[] = $row[$reposcol];
	if ( $descscol != -1 )
	  $descriptions[$row[$reposcol]] = $row[$descscol];
	else
	  $descriptions[$row[$reposcol]] = "no description";
      }
    }
    echo "Repositories that were requested to create (" . count($newrepos) . "): " . implode(", ",$newrepos) . "\n";

    // get the repos in the user/org
    echo "Attempting to read repositories for '$userorg'...\n";
    $tmp = $client->api('organization')->repositories($userorg);
    $ghrepos = array();
    foreach ( $tmp as $repo )
      $ghrepos[] = $repo['name'];
    echo "Repositories on github.com/$userorg (" . count($ghrepos) . "): " . implode(", ",$ghrepos) . "\n";

    // which ones to create?
    $repos_to_create = array();
    foreach ( $newrepos as $repo )
      if ( in_array($repo,$ghrepos) )
	echo "\trepo '$repo' already exists on github; ignoring\n";
      else
	$repos_to_create[] = $repo;
    echo "Processed repositories to create (" . count($repos_to_create) . "):\n";
    foreach ( $repos_to_create as $repo )
      echo "\t$repo (\"" . $descriptions[$repo] . "\"): " . (($watch)?"":"NOT ") . "watching\n";

    // are there any left?
    if ( count($repos_to_create) == 0 )
      die ("There are no repos to create!\n");

    // get user prompt
    echo "Are you sure you want to continue? (yes/no)\n";
    $response = readline();
    if ( $response != "yes" )
      die ("Response of 'yes' not received.\n");

    // create the repo!
    foreach ( $repos_to_create as $repo ) {
      $client->api('repo')->create($repo, $descriptions[$repo], "",$public,$userorg);
      echo "Repository '$repo' created!\n";
      if ( $watch ) {
	$client->api('current_user')->watchers()->watch($userorg, $repo);
	echo "\twatched repo '$repo'\n";
      } else {
	$client->api('current_user')->watchers()->unwatch($userorg, $repo);
	echo "\tunwatched repo '$repo'\n";
      }
    }

    fclose($fp);

  } catch (Exception $e) {
    die ("Exception thrown: " . $e->getMessage() . "\n");
  }
}

function manageTeams($client) {
  global $reposcol, $teamscol, $userscol, $csvfile, $userorg;

  try {

    // read in the CSV file header, and determine the row with the repository names
    $fp = fopen($csvfile,"r");
    readCSVHeader($fp);

    // read in the team data from CSV
    $teamdata = array();
    $teams = array();
    while ( ($row = fgetcsv($fp)) ) {
      $teamdata[] = array($row[$reposcol], $row[$userscol], $row[$teamscol]);
      if ( !in_array($row[$teamscol],$teams) )
	$teams[] = $row[$teamscol];
    }

    echo "\nHandling of team creation...\n";

    // get team data from github
    echo "Attempting to read teams for '$userorg'...\n";
    $tmp = $client->api('organization')->teams()->all($userorg);
    //print_r($tmp);
    $ghteams = array();
    foreach ( $tmp as $team )
      $ghteams[$team['name']] = $team['id'];
    echo "Teams on github.com/$userorg (" . count($ghteams) . "): " . implode(", ",array_keys($ghteams)) . "\n";

    // which teams to create?
    $teams_to_create = array();
    foreach ( $teams as $team )
      if ( !in_array($team,array_keys($ghteams)) )
	$teams_to_create[] = $team;
    echo "Teams to create on github.com/$userorg (" . count($teams_to_create) . "): " . implode(", ",$teams_to_create) . "\n";

    // create those teams
    foreach ( $teams_to_create as $team ) {
      $client->api('organization')->teams()->create($userorg,
	           array('name'=>$team,'permission'=>'push'));
      echo "\tteam '$team' on $userorg created!\n";
    }

    // get team data (again) from github
    echo "Attempting to read teams (again) for '$userorg'...\n";
    $tmp = $client->api('organization')->teams()->all($userorg);
    //print_r($tmp);
    $ghteams = array();
    foreach ( $tmp as $team )
      $ghteams[$team['name']] = $team['id'];
    echo "Teams on github.com/$userorg (" . count($ghteams) . "): " . implode(", ",array_keys($ghteams)) . "\n";
    //echo "Teams IDs on github.com/$userorg (" . count($ghteams) . "): " . implode(", ",$ghteams) . "\n";

    // for each team, set the members
    echo "\nHandling of member assignments to teams...\n";
    foreach ( $teams as $team ) {
      echo "Setting members for team '$team'...\n";

      // get desired members from CSV file data
      $desired_members = array();
      foreach ( $teamdata as $datum ) {
	if ( $datum[2] != $team )
	  continue;
	if ( !in_array($datum[1],$desired_members) )
	  $desired_members[] = $datum[1];
      }
      echo "\tdesired members in team '$team' (" . count($desired_members) . "): " . implode(", ",$desired_members) . "\n";

      // get actual members from github
      $tmp = $client->api('organization')->teams()->members($ghteams[$team]);
      $actual_members = array();
      //print_r($tmp);
      foreach ( $tmp as $user )
	$actual_members[$user['login']] = $user['id'];
      echo "\tactual members in team '$team' (" . count($actual_members) . "): " . implode(", ",array_keys($actual_members)) . "\n";
      //echo "\tactual members IDs in team '$team' (" . count($actual_members) . "): " . implode(", ",$actual_members) . "\n";

      // add members
      foreach ( $desired_members as $member )
	if ( !in_array($member,array_keys($actual_members)) ) {
	  $client->api('organization')->teams()->addMember($ghteams[$team],$member);
	  echo "\tadded $member to team '$team'\n";
	}

      // remove members
      foreach ( array_keys($actual_members) as $member )
	if ( !in_array($member,$desired_members) ) {
	  $client->api('organization')->teams()->removeMember($ghteams[$team],$member);
	  echo "\tremoved $member from team '$team'\n";
	}
    }

    // for each team, set the repos
    echo "\nHandling of repo assignments to teams...\n";
    foreach ( $teams as $team ) {
      echo "Setting repos for team '$team'...\n";

      // get desired repos from CSV file data
      $desired_repos = array();
      foreach ( $teamdata as $datum ) {
	if ( $datum[2] != $team )
	  continue;
	if ( (trim($datum[0]) != "") && (!in_array($datum[0],$desired_repos)) )
	  $desired_repos[] = $datum[0];
      }
      echo "\tdesired repos for team '$team' (" . count($desired_repos) . "): " . implode(", ",$desired_repos) . "\n";

      // get actual repos from github
      $tmp = $client->api('organization')->teams()->repositories($ghteams[$team]);
      //print_r($tmp);
      $actual_repos = array();
      foreach ( $tmp as $repo )
	$actual_repos[$repo['name']] = $repo['id'];
      echo "\tactual repos in team '$team' (" . count($actual_repos) . "): " . implode(", ",array_keys($actual_repos)) . "\n";

      // add repos
      foreach ( $desired_repos as $repo )
	if ( !in_array($repo,array_keys($actual_repos)) ) {
	  $client->api('organization')->teams()->addRepository($ghteams[$team],$userorg,$repo);
	  echo "\tadded $repo to team '$team'\n";
	}

      // remove repos
      foreach ( array_keys($actual_repos) as $repo )
	if ( !in_array($repo,$desired_repos) ) {
	  $client->api('organization')->teams()->removeRepository($ghteams[$team],$userorg,$repo);
	  echo "\tremoved $repo from team '$team'\n";
	}
    }

    fclose($fp);

  } catch (Exception $e) {
    die ("Exception thrown: " . $e->getMessage() . "\n");
  }
}

?>
