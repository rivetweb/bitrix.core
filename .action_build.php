<?php

const DEST_DIR_RIGHTS = 0777;

const SRC_FILE_DEPS = 'vendor/.deps.log';
const REPLACES_FILE = 'vendor/.replaces.log';
const DEBUG_FILE = 'local/php_interface/debug-autoload.php';

const PHP_BEGIN_TAG = '<' . '?php';
const PHP_BEGIN_SHORTTAG = '<' . '?';
const PHP_END_TAG = '?' . '>';
const PHP_NAMESPACE = 'namespace';
const PHP_USE = 'use';

const PHP_AUTOLOAD_BEGIN = '
namespace {
	spl_autoload_register(function ($className) {
		global $_6uTpukc;
		if (!isset($_6uTpukc[$className]) || !is_callable($_6uTpukc[$className])) {
			return false;
		}
		return $_6uTpukc[$className]();
	}, true, true);

';
const PHP_AUTOLOAD_END = '}

';

define('PHP_BEGIN_TAG_LENGTH', strlen(PHP_BEGIN_TAG));
define('PHP_BEGIN_SHORTTAG_LENGTH', strlen(PHP_BEGIN_SHORTTAG));
define('PHP_END_TAG_LENGTH', strlen(PHP_END_TAG));
define('PHP_NAMESPACE_LENGTH', strlen(PHP_NAMESPACE));
define('PHP_USE_LENGTH', strlen(PHP_USE));

define('DEBUG_FILE_LENGTH', strlen(DEBUG_FILE));

$mainFile = 'index.php';
$destDir = '.outputwww/';

$useAllClassesFromAutoload = false;
if (!file_exists(SRC_FILE_DEPS)) {
	$useAllClassesFromAutoload = true;
	trigger_error("File " . SRC_FILE_DEPS . " with dependencies not exists. All classes from autoload will be used.\n", E_USER_WARNING);
}
$createSingleFile = false;
if (!empty($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'onefile') {
	$createSingleFile = true;
}

function FilterFixClassForAutoloader($content, $className, $srcClassFile) {
	global $classesLinksForFile, $classesLinksForAutoload;
	$linkClassName = isset($classesLinksForFile[$className])?
		$classesLinksForFile[$className] : $className;
	if ($className == $linkClassName) {
		$content = "
\$_6uTpukc['$className'] = function () {
	$content
};
		";
	} else {
		$classesLinksForAutoload[$className] = $linkClassName;
		return "// $className -> $srcClassFile\n";
	}
	return $content;
}

function FilterFixNamespace($contentLines) {
	$namespaceLine = PHP_NAMESPACE . " {\n";
	$useLines = [];
	foreach ($contentLines as $i => $line) {
		$line = trim($line);
		if (substr($line, 0, PHP_NAMESPACE_LENGTH) == PHP_NAMESPACE
				&& substr($line, -1) == ';') {
			$namespaceLine = substr($line, 0, -1) . " {\n";
			unset($contentLines[$i]);
		} else if (substr($line, 0, PHP_USE_LENGTH) == PHP_USE
				&& substr($line, -1) == ';') {
			$useLines[] = $line;
			unset($contentLines[$i]);
		}
	}
	rsort($useLines);
	foreach ($useLines as $line) {
		array_unshift($contentLines, $line);
	}
	array_unshift($contentLines, $namespaceLine);
	$contentLines[] = "}\n";
	return $contentLines;
}

function FilterVendorAutoload($contentLines) {
	$result = [];
	$found = false;
	foreach ($contentLines as $line) {
		if (!$found) {
			if (strpos($line, 'lib/php/Boot.class.php') !== false
					|| strpos($line, 'vendor/autoload.php') !== false) {
				$found = true;
				continue;
			}
		}
		$result[] = $line;
	}
	return $result;
}

function FilterOldHaxeAutoload($content) {
	//TODO!!! перенести в файл замен - vendor/.replaces.log
	$content = str_replace(
		"spl_autoload_register('_hx_autoload')",
		"//spl_autoload_register('_hx_autoload')",
		$content
	);
	$content = str_replace(
		'if(!file_exists($_hx_autload_cache_file)) {',
		'if(0 && !file_exists($_hx_autload_cache_file)) {',
		$content
	);
	$content = str_replace(
		'require($_hx_autload_cache_file);',
		'//require($_hx_autload_cache_file);',
		$content
	);
	return $content;
}

function FilterRawReplace($content) {
	global $replacesFrom, $replacesTo;

	return count($replacesFrom)?
		str_replace($replacesFrom, $replacesTo, $content)
		: $content;
}

function ProcessFile($srcClassFile, $destClassFile, $isClassFile = false, $className = null) {
	global $createSingleFile, $out;

	// ignore debug file
	if (substr($srcClassFile, -DEBUG_FILE_LENGTH) == DEBUG_FILE) {
		return;
	}
	if ($createSingleFile) {
		$content = file_get_contents($srcClassFile);
		//$content = FilterConvertNamespaces($content);
		$content = trim($content);
		// clear begin tag
		$phpEndTagLength = strlen(PHP_BEGIN_TAG);
		if (substr($content, 0, PHP_BEGIN_TAG_LENGTH) == PHP_BEGIN_TAG) {
			$content = substr($content, PHP_BEGIN_TAG_LENGTH);
		} else if (substr($content, 0, PHP_BEGIN_SHORTTAG_LENGTH) == PHP_BEGIN_SHORTTAG) {
			$content = substr($content, PHP_BEGIN_SHORTTAG_LENGTH);
		}
		// clear end tag
		if (substr($content, -PHP_END_TAG_LENGTH) == PHP_END_TAG) {
			$content = substr($content, 0, -PHP_BEGIN_TAG_LENGTH + 1);
		}
		// filter code
		$contentLines = FilterVendorAutoload(explode("\n", $content));
		$content = implode("\n", $contentLines);
		if ($isClassFile) {
			$content = FilterFixClassForAutoloader($content, $className, $srcClassFile);
		}
		$contentLines = explode("\n", $content);
		$contentLines = FilterFixNamespace($contentLines);
		$content = implode("\n", $contentLines);
		// filter some haxe code
		if (basename($srcClassFile) == 'Boot.class.php') {
			$content = FilterOldHaxeAutoload($content);
		}
		$content = FilterRawReplace($content);
		fwrite($out, $content . "\n");
		return;
	}

	$destClassDir = dirname($destClassFile);
	if (!is_dir($destClassDir)) {
		mkdir($destClassDir, DEST_DIR_RIGHTS, true);
	}
	$content = file_get_contents($srcClassFile);
	// filter some haxe code
	if (basename($srcClassFile) == 'Boot.class.php') {
		$content = FilterOldHaxeAutoload($content);
	}
	$content = FilterRawReplace($content);
	file_put_contents($destClassFile, $content);
}

$replacesFrom = [];
$replacesTo = [];
if (file_exists(REPLACES_FILE)) {
	foreach (explode("\n", file_get_contents(REPLACES_FILE)) as $v) {
		$v = trim($v);
		if ($v == '') {
			continue;
		}
		$tmp = explode("\t", $v);
		if (count($tmp) < 2) {
			continue;
		}
		$replacesFrom[] = $tmp[0];
		$replacesTo[] = $tmp[1];
	}
}

$srcDir = dirname(SRC_FILE_DEPS);
$classMapFile = $srcDir . '/composer/autoload_classmap.php';
if (!file_exists($classMapFile)) {
	die("Classmap file $classMapFile not exists, use:\n\tcomposer -o dump-autoload\n");
}
$classMap = require $classMapFile;
$fileForClass = [];
foreach ($classMap as $className => $fname) {
	$fileForClass[$fname][] = $className;
}
$classesLinksForFile = [];
$classesLinksForAutoload = [];
foreach ($fileForClass as $fname => $classes) {
	if (count($classes) > 1) {
		$firstClassName = array_shift($classes);
		$classesLinksForFile[$firstClassName] = $firstClassName;
		foreach ($classes as $className) {
			$classesLinksForFile[$className] = $firstClassName;
		}
	}
}
$filesMapFile = $srcDir . '/composer/autoload_files.php';
$filesMap = [];
if (file_exists($filesMapFile)) {
	$filesMap = require $filesMapFile;
}
$usedClasses = [];
if (!$useAllClassesFromAutoload) {
	foreach (file(SRC_FILE_DEPS) as $className) {
		$className = trim($className);
		if (trim($className) == '') {
			continue;
		}
		$usedClasses[$className] = 1;
	}
} else {
	$usedClasses = $classMap;
}
echo "Copy deps...\n";
if (is_dir($destDir)) {
	system('rm -Rf ' . $destDir);
}
mkdir($destDir, DEST_DIR_RIGHTS, true);
if ($createSingleFile) {
	$out = fopen($destDir . $mainFile, 'w');
	fwrite($out, PHP_BEGIN_TAG . "\n");
	if (!$out) {
		die("Can't write to file: " . $destDir . $mainFile);
	}
}
$l = strlen($baseDir);
// copy class files
$includedFiles = [];
foreach ($usedClasses as $className => $_) {
	if (!isset($classMap[$className])) {
		continue;
	}
	$srcClassFile = ltrim(substr($classMap[$className], $l), '/');
	if (!$createSingleFile && isset($includedFiles[$srcClassFile])) {
		continue;
	}
	$includedFiles[$srcClassFile] = 1;
	$destClassFile = $destDir . $srcClassFile;
	ProcessFile($srcClassFile, $destClassFile, true, $className);
}
if (!$useAllClassesFromAutoload) {
	// save fixed deps list
	file_put_contents(
		SRC_FILE_DEPS,
		implode("\n", array_keys($usedClasses)) . "\n"
	);
}
if ($createSingleFile) {
	fwrite($out, PHP_AUTOLOAD_BEGIN);
	foreach ($classesLinksForAutoload as $className => $linkClassName) {
		fwrite($out, "	\$_6uTpukc['$className'] = \$_6uTpukc['$linkClassName'];\n");
	}
	fwrite($out, PHP_AUTOLOAD_END);
}
// copy included files
foreach ($filesMap as $includedFile) {
	$srcIncludeFile = ltrim(substr($includedFile, $l), '/');
	$destIncludeFile = $destDir . $srcIncludeFile;
	ProcessFile($srcIncludeFile, $destIncludeFile);
}
ProcessFile($mainFile, $destDir . $mainFile);
if ($createSingleFile) {
	fclose($out);
}
// init autoloader
if (!$createSingleFile) {
	copy('composer.prod.json', $destDir . 'composer.json');
	chdir($destDir);
	system('composer -o dump-autoload');
}
