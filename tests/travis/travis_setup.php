#!/usr/bin/env php
<?php
/**
 * Initialises a test project that can be built by travis.
 *
 * Travis downloads the module, but in order to run unit tests it needs
 * to be part of a SilverStripe "installer" project.
 * This script generates a custom composer.json with the required dependencies
 * and installs it into a separate webroot. The originally downloaded module
 * code is re-installed via composer.
 */

if (php_sapi_name() != 'cli') {
	header('HTTP/1.0 404 Not Found');
	exit;
}

$opts = getopt('', array(
	'target:',
));

if (!$opts) {
	echo "Invalid arguments specified\n";
	exit(1);
}

extract($opts);

$dir = __DIR__;
$modulePath = dirname(dirname($dir));
$moduleName = basename($modulePath);
$parent = dirname($modulePath);

// Get exact version of downloaded module so we can re-download via composer
$moduleRevision = getenv('TRAVIS_COMMIT');
$moduleBranch = getenv('TRAVIS_BRANCH');
$moduleBranchComposer = (preg_match('/^\d\.\d/', $moduleBranch)) ? $moduleBranch . '.x-dev' : 'dev-master';
$coreBranch = getenv('CORE_RELEASE');
$coreBranchComposer = (preg_match('/^\d\.\d/', $coreBranch)) ? $coreBranch . '.x-dev' : 'dev-master';

// Print out some environment information.
printf("Environment:\n");
printf("  * MySQL:      %s\n", trim(`mysql --version`));
printf("  * PostgreSQL: %s\n", trim(`pg_config --version`));
printf("  * SQLite:     %s\n\n", trim(`sqlite3 -version`));

// Extract the package info from the module composer file, and build a
// custom project composer file with the local package explicitly defined.
echo "Reading composer information...\n";

$package = json_decode(file_get_contents("$modulePath/composer.json"), true);

// Generate a custom composer file.
$packageNew = array(
	'require' => array_merge(
		isset($package['require']) ? $package['require'] : array(),
		array($package['name'] => $moduleBranchComposer . '#' . $moduleRevision,)
	),
	// Always include DBs, allow module specific version dependencies though
	'require-dev' => array_merge(
		array('silverstripe/postgresql' => '*','silverstripe/sqlite3' => '*'),
		isset($package['require-dev']) ? $package['require-dev'] : array()
	),
	'minimum-stability' => 'dev'
);
// Override module dependencies in order to test with specific core branch.
// This might be older than the latest permitted version based on the module definition.
// Its up to the module author to declare compatible CORE_RELEASE values in the .travis.yml.
if(isset($packageNew['require']['silverstripe/framework'])) {
	$packageNew['require']['silverstripe/framework'] = $coreBranchComposer;
}
if(isset($packageNew['require']['silverstripe/cms'])) {
	$packageNew['require']['silverstripe/cms'] = $coreBranchComposer;
}
$composer = json_encode($packageNew);

echo "Generated composer file:\n";
echo "$composer\n\n";

echo "Cloning installer@$coreBranch...\n";
`git clone --depth=100 --quiet -b $coreBranch git://github.com/silverstripe/silverstripe-installer.git $target`;

echo "Setting up project...\n";
`cp $dir/_config.php $target/mysite`;

echo "Replacing composer file...\n";
unlink("$target/composer.json");
file_put_contents("$target/composer.json", $composer);

echo "Running composer...\n";
passthru("composer install --prefer-dist --dev -d $target");