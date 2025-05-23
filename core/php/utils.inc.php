<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;

function include_file($_folder, $_fn, $_type, $_plugin = '') {
	if (strpos($_folder, '..') !== false || strpos($_fn, '..') !== false || strpos($_fn, '\\') !== false) {
		return;
	}
	if (strpos($_plugin, '..') !== false || strpos($_plugin, '/') !== false || strpos($_plugin, '\\') !== false) {
		return;
	}
	if (strpos($_type, '..') !== false || strpos($_type, '/') !== false || strpos($_type, '\\') !== false) {
		return;
	}
	$_rescue = false;
	if (isset($_GET['rescue']) && $_GET['rescue'] == 1) {
		$_rescue = true;
	}
	if ($_folder == '3rdparty') {
		$_fn .= '.' . $_type;
		$path = __DIR__ . '/../../' . $_folder . '/' . $_fn;
		$type = $_type;
	} elseif ($_folder == 'coreDOM') {
	    $_fn .= '.' . $_type;
		$path = __DIR__ . '/../../core/dom/' . $_fn;
		$type = $_type;
		$_folder = 'core/dom';
	} else {
		$config = array(
			'class' => array('/class', '.class.php', 'php'),
			'com' => array('/com', '.com.php', 'php'),
			'repo' => array('/repo', '.repo.php', 'php'),
			'config' => array('/config', '.config.php', 'php'),
			'modal' => array('/modal', '.php', 'php'),
			'modalhtml' => array('/modal', '.html', 'php'),
			'php' => array('/php', '.php', 'php'),
			'css' => array('/css', '.css', 'css'),
			'js' => array('/js', '.js', 'js'),
			'class.js' => array('/js', '.class.js', 'js'),
			'custom.js' => array('/custom', 'custom.js', 'js'),
			'custom.css' => array('/custom', 'custom.css', 'css'),
			'themes.js' => array('/themes', '.js', 'js'),
			'themes.css' => array('/themes', '.css', 'css'),
			'api' => array('/api', '.api.php', 'php'),
			'html' => array('/html', '.html', 'php'),
			'configuration' => array('', '.php', 'php'),
		);
		if (!isset($config[$_type])) {
			return;
		}
		$_folder .= $config[$_type][0];
		$_fn .= $config[$_type][1];
		$type = $config[$_type][2];
	}
	if ($_plugin != '') {
		$_folder = 'plugins/' . $_plugin . '/' . $_folder;
	}
	$path = __DIR__ . '/../../' . $_folder . '/' . $_fn;
	if (!file_exists($path) && $type == 'php') {
		throw new Exception(__('Fichier introuvable :', __FILE__) . ' ' . secureXSS($path), 35486);
	}
	if ($type == 'php') {
		if ($_type != 'class') {
			ob_start();
			require_once $path;
			if ($_rescue) {
				echo preg_replace("/{{(.*?)}}/s", '$1', ob_get_clean());
			} else {
				echo translate::exec(ob_get_clean(), $_folder . '/' . $_fn);
			}
			return;
		}
		require_once $path;
		return;
	}
	if ($type == 'css') {
		echo '<link href="' . $_folder . '/' . $_fn . '?md5=' . md5_file($path) . '" rel="stylesheet" />';
		return;
	}
	if ($type == 'js') {
		$md5 = md5_file($path);
		if (strpos($_folder, '3rdparty') !== false || strpos($_fn, '.min.js') !== false) {
			echo '<script type="text/javascript" src="' . $_folder . '/' . $_fn . '?md5=' . $md5 . '"></script>';
		} else {
			echo '<script type="text/javascript" src="core/php/getResource.php?file=' . $_folder . '/' . $_fn . '&md5=' . $md5 . '&lang=' . translate::getLanguage() . '"></script>';
		}
		return;
	}
}

function getTemplate($_folder, $_version, $_filename, $_plugin = '') {
	if (strpos($_plugin, '..') !== false || strpos($_plugin, '/') !== false || strpos($_plugin, '\\') !== false) {
		return;
	}
	if (strpos($_version, '..') !== false || strpos($_version, '/') !== false || strpos($_version, '\\') !== false) {
		return;
	}
	if (strpos($_filename, '..') !== false || strpos($_filename, '/') !== false || strpos($_filename, '\\') !== false) {
		return;
	}
	if (strpos($_folder, '..') !== false) {
		return;
	}
	$path = ($_plugin == '')
		? __DIR__ . '/../../' . $_folder . '/template/' . $_version . '/' . $_filename . '.html'
		: __DIR__ . '/../../plugins/' . $_plugin . '/core/template/' . $_version . '/' . $_filename . '.html';
	return (file_exists($path)) ? file_get_contents($path) : '';
}

function template_replace($_array, $_subject) {
	if (!isset($_array['#uid#'])) {
		$_array['#uid#'] = '';
	}
	if (strpos($_array['#uid#'], 'eqLogic') !== false && (!isset($_array['#calledFrom#']) || $_array['#calledFrom#'] != 'eqLogic')) {
		if (is_object($eqLogic = eqLogic::byId($_array['#id#'])) && ($eqLogic->getDisplay('widgetTmpl', 1) == 0 || $_subject == '')) {
			$reflected = new ReflectionClass($eqLogic->getEqType_name());
			$method = $reflected->getParentClass()->getMethod('toHtml');
			return $method->invokeArgs($eqLogic, [$_array['#version#']]);
		}
	}
	return str_replace(array_keys($_array), array_values($_array), $_subject);
}

function init($_name, $_default = '') {
	if (isset($_GET[$_name])) {
		return $_GET[$_name];
	}
	if (isset($_POST[$_name])) {
		return $_POST[$_name];
	}
	if (isset($_REQUEST[$_name])) {
		return $_REQUEST[$_name];
	}
	return $_default;
}

function sendVarToJS($_varName, $_value = '') {
	if (!is_array($_varName)) {
		$_varName = [$_varName => $_value];
	}
	$jsVar = '<script>';
	foreach ($_varName as $name => $value) {
		$value = (is_array($value)) ? 'JSON.parse("' . addslashes(json_encode($value, JSON_UNESCAPED_UNICODE)) . '")'	: '"' . $value . '"';
		if (strpos($name, '.') === false) {
			$jsVar .= 'var ' . $name . ' = ' . $value . "\n";
		} else {
			$jsVar .= $name . ' = ' . $value . "\n";
		}
	}
	$jsVar .= '</script>';
	echo $jsVar;
}

function resizeImage($contents, $width, $height) {
	$width_orig = imagesx($contents);
	$height_orig = imagesy($contents);
	$ratio_orig = $width_orig / $height_orig;
	$test = $width / $height > $ratio_orig;
	$dest_width = $test ? ceil($height * $ratio_orig) : $width;
	$dest_height = $test ? $height : ceil($width / $ratio_orig);

	$dest_image = imagecreatetruecolor($width, $height);
	$wh = imagecolorallocate($dest_image, 0xFF, 0xFF, 0xFF);
	imagefill($dest_image, 0, 0, $wh);

	$offcet_x = ($width - $dest_width) / 2;
	$offcet_y = ($height - $dest_height) / 2;
	if ($dest_image && $contents) {
		if (!imagecopyresampled($dest_image, $contents, $offcet_x, $offcet_y, 0, 0, $dest_width, $dest_height, $width_orig, $height_orig)) {
			error_log("Error image copy resampled");
			return false;
		}
	}
	// start buffering
	ob_start();
	imagejpeg($dest_image);
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}

function getmicrotime() {
	list($usec, $sec) = explode(" ", microtime());
	return ((float) $usec + (float) $sec);
}

function redirect($_url, $_forceType = null) {
	if ($_forceType == 'JS' || headers_sent() || isset($_GET['ajax'])) {
		echo '<script type="text/javascript">';
		echo "window.location.href='$_url';";
		echo '</script>';
	} else {
		exit(header("Location: $_url"));
	}
	return;
}

function convertDuration($time) {
	$result = '';
	$unities = array('j' => 86400, 'h' => 3600, 'min' => 60);
	foreach ($unities as $unity => $value) {
		if ($time >= $value || $result != '') {
			$result .= floor($time / $value) . $unity . ' ';
			$time %= $value;
		}
	}

	$result .= $time . 's';
	return $result;
}

function getClientIp() {
	$sources = array(
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR',
	);
	foreach ($sources as $source) {
		if (isset($_SERVER[$source])) {
			if (strpos($_SERVER[$source], ',') !== false) {
				return explode(',', $_SERVER[$source])[0];
			}
			return str_replace(' ','',$_SERVER[$source]);
		}
	}
	return '';
}

function mySqlIsHere() {
	require_once __DIR__ . '/../class/DB.class.php';
	return is_object(DB::getConnection());
}

function displayException($e) {
	$message = '<span id="span_errorMessage">' . $e->getMessage() . '</span>';
	if (DEBUG !== 0) {
		$message .= "<a class=\"pull-right bt_errorShowTrace cursor\" onclick=\"event.stopPropagation(); document.getElementById('pre_errorTrace').toggle()\">Show traces</a>";
		$message .= '<br/><pre id="pre_errorTrace" style="display : none;">' . print_r($e->getTraceAsString(), true) . '</pre>';
	}
	return $message;
}

function is_json($_string, $_default = null) {
	if ($_default !== null) {
		if (!is_string($_string)) {
			return $_default;
		}
		$return = json_decode($_string, true, 512, JSON_BIGINT_AS_STRING);
		if (!is_array($return)) {
			return $_default;
		}
		return $return;
	}
	return ((is_string($_string) && is_array(json_decode($_string, true, 512, JSON_BIGINT_AS_STRING)))) ? true : false;
}

function is_sha1($_string = '') {
	if ($_string == '') {
		return false;
	}
	return preg_match('/^[0-9a-f]{40}$/i', $_string);
}

function is_sha512($_string = '') {
	if ($_string == '') {
		return false;
	}
	return preg_match('/^[0-9a-f]{128}$/i', $_string);
}

function cleanPath($path) {
	$out = array();
	foreach (explode('/', $path) as $i => $fold) {
		if ($fold == '' || $fold == '.') {
			continue;
		}

		if ($fold == '..' && $i > 0 && end($out) != '..') {
			array_pop($out);
		} else {
			$out[] = $fold;
		}
	}
	return ($path[0] == '/' ? '/' : '') . join('/', $out);
}

function getRootPath() {
	return cleanPath(__DIR__ . '/../../');
}

function hadFileRight($_allowPath, $_path) {
	$path = cleanPath($_path);
	foreach ($_allowPath as $right) {
		if (strpos($right, '/') !== false || strpos($right, '\\') !== false) {
			if (strpos($right, '/') !== 0 || strpos($right, '\\') !== 0) {
				$right = getRootPath() . '/' . $right;
			}
			if (dirname($path) == $right || $path == $right) {
				return true;
			}
		} else {
			if (basename(dirname($path)) == $right || basename($path) == $right) {
				return true;
			}
		}
	}
	return false;
}

function getVersion($_name) {
	return jeedom::version();
}

// got from https://github.com/zendframework/zend-stdlib/issues/58
function polyfill_glob_brace($pattern, $flags) {
	static $next_brace_sub;
	if (!$next_brace_sub) {
		// Find the end of the sub-pattern in a brace expression.
		$next_brace_sub = function ($pattern, $current) {
			$length = strlen($pattern);
			$depth = 0;

			while ($current < $length) {
				if ('\\' === $pattern[$current]) {
					if (++$current === $length) {
						break;
					}
					$current++;
				} else {
					if (('}' === $pattern[$current] && $depth-- === 0) || (',' === $pattern[$current] && 0 === $depth)) {
						break;
					} elseif ('{' === $pattern[$current++]) {
						$depth++;
					}
				}
			}

			return $current < $length ? $current : null;
		};
	}

	$length = strlen($pattern);

	// Find first opening brace.
	for ($begin = 0; $begin < $length; $begin++) {
		if ('\\' === $pattern[$begin]) {
			$begin++;
		} elseif ('{' === $pattern[$begin]) {
			break;
		}
	}

	// Find comma or matching closing brace.
	if (null === ($next = $next_brace_sub($pattern, $begin + 1))) {
		return glob($pattern, $flags);
	}

	$rest = $next;

	// Point `$rest` to matching closing brace.
	while ('}' !== $pattern[$rest]) {
		if (null === ($rest = $next_brace_sub($pattern, $rest + 1))) {
			return glob($pattern, $flags);
		}
	}

	$paths = array();
	$p = $begin + 1;

	// For each comma-separated subpattern.
	do {
		$subpattern = substr($pattern, 0, $begin)
			. substr($pattern, $p, $next - $p)
			. substr($pattern, $rest + 1);

		if (($result = polyfill_glob_brace($subpattern, $flags))) {
			$paths = array_merge($paths, $result);
		}

		if ('}' === $pattern[$next]) {
			break;
		}

		$p = $next + 1;
		$next = $next_brace_sub($pattern, $p);
	} while (null !== $next);

	return array_values(array_unique($paths));
}

function glob_brace($pattern, $flags = 0) {
	if (defined("GLOB_BRACE")) {
		return glob($pattern, $flags + GLOB_BRACE);
	} else {
		return polyfill_glob_brace($pattern, $flags);
	}
}

function ls($folder = "", $pattern = "*", $recursivly = false, $options = array('files', 'folders')) {
	if ($folder) {
		$current_folder = realpath('.');
		if (in_array('quiet', $options)) {
			// If quiet is on, we will suppress the 'no such folder' error
			if (!file_exists($folder)) {
				return array();
			}
		}
		if (!is_dir($folder) || !chdir($folder)) {
			return array();
		}
	}
	$get_files = in_array('files', $options);
	$get_folders = in_array('folders', $options);
	$both = array();
	$folders = array();
	// Get the all files and folders in the given directory.
	if ($get_files) {
		foreach (glob_brace($pattern, GLOB_MARK) as $file) {
			if (!is_dir($folder . '/' . $file)) {
				$both[] = $file;
			}
		}
	}
	if ($recursivly || $get_folders) {
		$folders = glob("*", GLOB_ONLYDIR + GLOB_MARK);
	}

	//If a pattern is specified, make sure even the folders match that pattern.
	$matching_folders = array();
	if ($pattern !== '*') {
		$matching_folders = glob($pattern, GLOB_ONLYDIR + GLOB_MARK);
	}

	//Get just the files by removing the folders from the list of all files.
	$all = array_values(array_diff($both, $folders));
	if ($recursivly || $get_folders) {
		foreach ($folders as $this_folder) {
			if ($get_folders) {
				//If a pattern is specified, make sure even the folders match that pattern.
				if ($pattern !== '*') {
					if (in_array($this_folder, $matching_folders)) {
						$all[] = $this_folder;
					}
				} else {
					$all[] = $this_folder;
				}
			}

			if ($recursivly) {
				// Continue calling this function for all the folders
				$deep_items = ls($this_folder, $pattern, $recursivly, $options); # :RECURSION:
				foreach ($deep_items as $item) {
					$all[] = $this_folder . $item;
				}
			}
		}
	}

	if ($folder && is_dir($current_folder)) {
		chdir($current_folder);
	}

	if (in_array('datetime_asc', $options)) {
		global $current_dir;
		$current_dir = $folder;
		usort($all, function ($a, $b) {
			return filemtime($GLOBALS['current_dir'] . '/' . $a) < filemtime($GLOBALS['current_dir'] . '/' . $b);
		});
	}
	if (in_array('datetime_desc', $options)) {
		global $current_dir;
		$current_dir = $folder;
		usort($all, function ($a, $b) {
			return filemtime($GLOBALS['current_dir'] . '/' . $a) > filemtime($GLOBALS['current_dir'] . '/' . $b);
		});
	}

	return $all;
}

function removeCR($_string) {
	return trim(str_replace(array("\n", "\r\n", "\r", "\n\r"), '', $_string));
}

function rcopy($src, $dst, $_emptyDest = true, $_exclude = array(), $_noError = false, $_params = array()) {
	if (!file_exists($src)) {
		return true;
	}
	if ($_emptyDest) {
		rrmdir($dst);
	}
	if (is_dir($src)) {
		if (!file_exists($dst)) {
			@mkdir($dst);
		}
		$files = scandir($src);
		foreach ($files as $file) {
			if ($file != "." && $file != ".." && !in_array($file, $_exclude) && !in_array(realpath($src . '/' . $file), $_exclude)) {
				if (!rcopy($src . '/' . $file, $dst . '/' . $file, $_emptyDest, $_exclude, $_noError, $_params) && !$_noError) {
					return false;
				}
			}
		}
	} else {
		if (!in_array(basename($src), $_exclude) && !in_array(realpath($src), $_exclude)) {
			$srcSize = filesize($src);
			if (isset($_params['ignoreFileSizeUnder']) && $srcSize < $_params['ignoreFileSizeUnder']) {
				if (strpos(realpath($src), 'empty') !== false) {
					return true;
				}
				if (strpos(realpath($src), '.git') !== false) {
					return true;
				}
				if (strpos(realpath($src), '.html') !== false) {
					return true;
				}
				if (strpos(realpath($src), '.txt') !== false) {
					return true;
				}
				if (isset($_params['log']) && $_params['log']) {
					echo 'Ignore file ' . $src . ' because size is ' . $srcSize . "\n";
				}
				return true;
			}
			if (!copy($src, $dst)) {
				$output = array();
				$retval = 0;
				exec('sudo cp ' . $src . ' ' . $dst, $output, $retval);
				if ($retval != 0) {
					if (!$_noError) {
						return false;
					} else if (isset($_params['log']) && $_params['log']) {
						echo 'Error on copy ' . $src . ' to ' . $dst . "\n";
					}
				}
			}
			if ($srcSize != filesize($dst)) {
				if (!$_noError) {
					return false;
				} else if (isset($_params['log']) && $_params['log']) {
					echo 'Error on copy ' . $src . ' to ' . $dst . "\n";
				}
			}
			return true;
		}
	}
	return true;
}

function rmove($src, $dst, $_emptyDest = true, $_exclude = array(), $_noError = false, $_params = array()) {
	if (!file_exists($src)) {
		return true;
	}
	if ($_emptyDest) {
		rrmdir($dst);
	}
	if (is_dir($src)) {
		if (!file_exists($dst)) {
			@mkdir($dst);
		}
		$files = scandir($src);
		foreach ($files as $file) {
			if ($file != "." && $file != ".." && !in_array($file, $_exclude) && !in_array(realpath($src . '/' . $file), $_exclude)) {
				if (!rmove($src . '/' . $file, $dst . '/' . $file, $_emptyDest, $_exclude, $_noError, $_params) && !$_noError) {
					return false;
				}
			}
		}
	} else {
		if (!in_array(basename($src), $_exclude) && !in_array(realpath($src), $_exclude)) {
			$srcSize = filesize($src);
			if (isset($_params['ignoreFileSizeUnder']) && $srcSize < $_params['ignoreFileSizeUnder']) {
				if (strpos(realpath($src), 'empty') !== false) {
					return true;
				}
				if (strpos(realpath($src), '.git') !== false) {
					return true;
				}
				if (strpos(realpath($src), '.html') !== false) {
					return true;
				}
				if (strpos(realpath($src), '.txt') !== false) {
					return true;
				}
				if (isset($_params['log']) && $_params['log']) {
					echo 'Ignore file ' . $src . ' because size is ' . $srcSize . "\n";
				}
				return true;
			}
			if (!rename($src, $dst)) {
				$output = array();
				$retval = 0;
				exec('sudo mv ' . $src . ' ' . $dst, $output, $retval);
				if ($retval != 0) {
					if (!$_noError) {
						return false;
					} else if (isset($_params['log']) && $_params['log']) {
						echo 'Error on move ' . $src . ' to ' . $dst . "\n";
					}
				}
			}
			if ($srcSize != filesize($dst)) {
				if (!$_noError) {
					return false;
				} else if (isset($_params['log']) && $_params['log']) {
					echo 'Error on move ' . $src . ' to ' . $dst . "\n";
				}
			}
			return true;
		}
	}
	return true;
}

// removes files and non-empty directories
function rrmdir($dir) {
	if (is_dir($dir)) {
		$files = scandir($dir);
		foreach ($files as $file) {
			if ($file != "." && $file != "..") {
				rrmdir("$dir/$file");
			}
		}
		if (!rmdir($dir)) {
			$output = array();
			$retval = 0;
			exec('sudo rm -rf ' . $dir, $output, $retval);
			if ($retval != 0) {
				return false;
			}
		}
	} else if (file_exists($dir)) {
		if (!unlink($dir)) {
			$output = array();
			$retval = 0;
			exec('sudo rm -rf ' . $dir, $output, $retval);
			if ($retval != 0) {
				return false;
			}
		}
	}
	return true;
}

function date_fr($date_en) {
	if (config::byKey('language', 'core', 'fr_FR') == 'en_US') {
		return $date_en;
	}
	$texte_long_en = array(
		'/(^|\W)Monday($|\W)/', '/(^|\W)Tuesday($|\W)/', '/(^|\W)Wednesday($|\W)/', '/(^|\W)Thursday($|\W)/',
		'/(^|\W)Friday($|\W)/', '/(^|\W)Saturday($|\W)/', '/(^|\W)Sunday($|\W)/', '/(^|\W)January($|\W)/',
		'/(^|\W)February($|\W)/', '/(^|\W)March($|\W)/', '/(^|\W)April($|\W)/', '/(^|\W)May($|\W)/',
		'/(^|\W)June($|\W)/', '/(^|\W)July($|\W)/', '/(^|\W)August($|\W)/', '/(^|\W)September($|\W)/',
		'/(^|\W)October($|\W )/', '/(^|\W)November($|\W)/', '/(^|\W)December($|\W)/',
	);
	$texte_short_day_en = array(
		'/(^|\W)Mon($|\W)/', '/(^|\W)Tue($|\W)/', '/(^|\W)Wed($|\W)/', '/(^|\W)Thu($|\W)/', '/(^|\W)Fri($|\W)/', '/(^|\W)Sat($|\W)/', '/(^|\W)Sun($|\W)/'
	);
	$texte_short_month_en = array(
		'/(^|\W)Jan($|\W)/', '/(^|\W)Feb($|\W)/', '/(^|\W)Mar($|\W)/', '/(^|\W)Apr($|\W)/', '/(^|\W)May($|\W)/', '/(^|\W)Jun($|\W)/', '/(^|\W)Jul($|\W)/',
		'/(^|\W)Aug($|\W)/', '/(^|\W)Sep($|\W)/', '/(^|\W)Oct($|\W)/', '/(^|\W)Nov($|\W)/', '/(^|\W)Dec($|\W)/',
	);

	switch (config::byKey('language', 'core', 'fr_FR')) {
		case 'fr_FR':
			$texte_long = array(
				'$1Lundi$2', '$1Mardi$2', '$1Mercredi$2', '$1Jeudi$2',
				'$1Vendredi$2', '$1Samedi$2', '$1Dimanche$2', '$1Janvier$2',
				'$1Février$2', '$1Mars$2', '$1Avril$2', '$1Mai$2',
				'$1Juin$2', '$1Juillet$2', '$1Août$2', '$1Septembre$2',
				'$1Octobre$2', '$1Novembre$2', '$1Décembre$2',
			);
			$texte_short_day = array(
				'$1Lun$2', '$1Mar$2', '$1Mer$2', '$1Jeu$2', '$1Ven$2', '$1Sam$2', '$1Dim$2'
			);
			$texte_short_month = array(
				'$1Janv.$2', '$1Févr.$2', '$1Mars$2', '$1Avril$2', '$1Mai$2', '$1Juin$2',
				'$1Juil.$2', '$1Août$2', '$1Sept.$2', '$1Oct.$2', '$1Nov.$2', '$1Déc.$2',
			);
			break;
		case 'de_DE':
			$texte_long = array(
				'$1Montag$2', '$1Dienstag$2', '$1Mittwoch$2', '$1Donnerstag$2',
				'$1Freitag$2', '$1Samstag$2', '$1Sonntag$2', '$1Januar$2',
				'$1Februar$2', '$1März$2', '$1April$2', '$1May$2',
				'$1Juni$2', '$1July$2', '$1August$2', '$1September$2',
				'$1October$2', '$1November$2', '$1December$2',
			);
			$texte_short_day = array(
				'$1Mon$2', '$1Die$2', '$1Mit$2', '$1Thu$2', '$1Don$2', '$1Sam$2', '$1Son$2'
			);
			$texte_short_month = array(
				'$1Jan$2', '$1Feb$2', '$1Mar$2', '$1Apr$2', '$1May$2', '$1Jun$2', '$1Jul$2',
				'$1Aug$2', '$1Sep$2', '$1Oct$2', '$1Nov$2', '$1Dec$2',
			);
			break;
		case 'es_ES':
			$texte_long = array(
				'$1Lunes$2', '$1Martes$2', '$1Miércoles$2', '$1Jueves$2',
				'$1Viernes$2', '$1Sábado$2', '$1Domingo$2', '$1Enero$2',
				'$1Febrero$2', '$1Marzo$2', '$1Abril$2', '$1Mayo$2',
				'$1Junio$2', '$1Julio$2', '$1Agosto$2', '$1Septiembre$2',
				'$1Octubre$2', '$1Noviembre$2', '$1Diciembre$2',
			);
			$texte_short_day = array(
				'$1Lun$2', '$1Mar$2', '$1Mie$2', '$1Jue$2', '$1Vie$2', '$1Sab$2', '$1Dom$2'
			);
			$texte_short_month = array(
				'$1Ener.$2', '$1Febr.$2', '$1Marz.$2', '$1Abr.$2', '$1May.$2', '$1Jun.$2',
				'$1Jul.$2', '$1Ago.$2', '$1Sept.$2', '$1Oct.$2', '$1Nov.$2', '$1Dic.$2',
			);
			break;
		default:
			return $date_en;
	}
	return preg_replace($texte_short_day_en, $texte_short_day, preg_replace($texte_short_month_en, $texte_short_month, preg_replace($texte_long_en, $texte_long, $date_en)));
}

function convertDayFromEn($_day) {
	$result = $_day;
	$daysMapping = array(
		'fr_FR' => array(
			'Monday' => 'Lundi', 'Mon' => 'Lundi',
			'monday' => 'lundi', 'mon' => 'lundi',
			'Tuesday' => 'Mardi', 'Tue' => 'Mardi',
			'tuesday' => 'mardi', 'tue' => 'mardi',
			'Wednesday' => 'Mercredi', 'Wed' => 'Mercredi',
			'wednesday' => 'mercredi', 'wed' => 'mercredi',
			'Thursday' => 'Jeudi', 'Thu' => 'Jeudi',
			'thursday' => 'jeudi', 'thu' => 'jeudi',
			'Friday' => 'Vendredi', 'Fri' => 'Vendredi',
			'friday' => 'vendredi', 'fri' => 'vendredi',
			'Saturday' => 'Samedi', 'Sat' => 'Samedi',
			'saturday' => 'samedi', 'sat' => 'samedi',
			'Sunday' => 'Dimanche', 'Sun' => 'Dimanche',
			'sunday' => 'dimanche', 'sun' => 'dimanche',
		),
		'de_DE' => array(
			'Monday' => 'Montag', 'Mon' => 'Montag',
			'monday' => 'montag', 'mon' => 'montag',
			'Tuesday' => 'Dienstag', 'Tue' => 'Dienstag',
			'tuesday' => 'dienstag', 'tue' => 'dienstag',
			'Wednesday' => 'Mittwoch', 'Wed' => 'Mittwoch',
			'wednesday' => 'mittwoch', 'wed' => 'mittwoch',
			'Thursday' => 'Donnerstag', 'Thu' => 'Donnerstag',
			'thursday' => 'donnerstag', 'thu' => 'donnerstag',
			'Friday' => 'Freitag', 'Fri' => 'Freitag',
			'friday' => 'freitag', 'fri' => 'freitag',
			'Saturday' => 'Samstag', 'Sat' => 'Samstag',
			'saturday' => 'samstag', 'sat' => 'samstag',
			'Sunday' => 'Sonntag', 'Sun' => 'Sonntag',
			'sunday' => 'sonntag', 'sun' => 'sonntag',
		),
		'es_ES' => array(
			'Monday' => 'Lunes', 'Mon' => 'Lunes',
			'monday' => 'lunes', 'mon' => 'lunes',
			'Tuesday' => 'Martes', 'Tue' => 'Martes',
			'tuesday' => 'martes', 'tue' => 'martes',
			'Wednesday' => 'Miércoles', 'Wed' => 'Miércoles',
			'wednesday' => 'miércoles', 'wed' => 'miércoles',
			'Thursday' => 'Jueves', 'Thu' => 'Jueves',
			'thursday' => 'jueves', 'thu' => 'jueves',
			'Friday' => 'Viernes', 'Fri' => 'Viernes',
			'friday' => 'viernes', 'fri' => 'viernes',
			'Saturday' => 'Sábado', 'Sat' => 'Sábado',
			'saturday' => 'sábado', 'sat' => 'sábado',
			'Sunday' => 'Domingo', 'Sun' => 'Domingo',
			'sunday' => 'domingo', 'sun' => 'domingo',
		),
	);
	$language = config::byKey('language', 'core', 'fr_FR');
	if (array_key_exists($language, $daysMapping)) {
		$daysArray = $daysMapping[$language];
		if (array_key_exists($_day, $daysArray)) {
			$result = $daysArray[$_day];
		}
	}
	return $result;
}

function create_zip($source_arr, $destination, $_excludes = array()) {
	if (is_string($source_arr)) {
		$source_arr = array($source_arr);
	}
	if (!extension_loaded('zip')) {
		throw new Exception('Extension php ZIP non chargée');
	}
	$zip = new ZipArchive();
	if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
		throw new Exception('Impossible de créer l\'archive ZIP dans le dossier de destination : ' . $destination);
	}
	foreach ($source_arr as $source) {
		if (!file_exists($source)) {
			continue;
		}
		$source = str_replace('\\', '/', realpath($source));
		if (is_dir($source) === true) {
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($files as $file) {
				if (strpos($file, $source) === false) {
					continue;
				}
				if ($file == $source . '/.' || $file == $source . '/..' || in_array(basename($file), $_excludes) || in_array(realpath($file), $_excludes)) {
					continue;
				}
				foreach ($_excludes as $exclude) {
					if (strpos($file, trim('/' . $exclude . '/', '/')) !== false) {
						continue (2);
					}
				}
				$file = str_replace('\\', '/', realpath($file));
				if (is_dir($file) === true) {
					$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
				} else if (is_file($file) === true) {
					$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
				}
			}
		} else if (is_file($source) === true) {
			$zip->addFromString(basename($source), file_get_contents($source));
		}
	}
	return $zip->close();
}

function br2nl($string) {
	return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
}

function calculPath($_path) {
	if (strpos($_path, '/') !== 0) {
		return __DIR__ . '/../../' . $_path;
	}
	return $_path;
}

function getDirectorySize($path) {
	$bytestotal = 0;
 	$path = realpath($path);
  	if($path!==false && $path!='' && file_exists($path) && !is_link($path)){
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
			try {
				$bytestotal += $object->getSize();
			} catch (\Throwable $th) {

			}
		}
	}
	return $bytestotal;
}

function sizeFormat($size) {
	$mod = 1024;
	$units = explode(' ', 'B KB MB GB TB PB');
	for ($i = 0; $size > $mod; $i++) {
		$size /= $mod;
	}
	return round($size, 2) . ' ' . $units[$i];
}

/**
 *
 * @param string $network
 * @param string $ip
 * @return boolean
 */
function netMatch($network, $ip) {
	$ip = trim($ip);
	if ($ip == trim($network)) {
		return true;
	}
	$network = str_replace(' ', '', $network);
	if (strpos($network, '*') !== false) {
		if (strpos($network, '/') !== false) {
			$asParts = explode('/', $network);
			if ($asParts[0]) {
				$network = $asParts[0];
			} else {
				$network = null;
			}
		}
		$nCount = substr_count($network, '*');
		$network = str_replace('*', '0', $network);
		if ($nCount == 1) {
			$network .= '/24';
		} elseif ($nCount == 2) {
			$network .= '/16';
		} elseif ($nCount == 3) {
			$network .= '/8';
		} elseif ($nCount > 3) {
			return true; // if *.*.*.*, then all, so matched
		}
	}

	$d = strpos($network, '-');
	if ($d === false) {
		if (strpos($network, '/') === false) {
			if ($ip == $network) {
				return true;
			}
			return false;
		}
		$ip_arr = explode('/', $network);
		if (!preg_match("@\d*\.\d*\.\d*\.\d*@", $ip_arr[0], $matches)) {
			$ip_arr[0] .= ".0"; // Alternate form 194.1.4/24
		}
		$network_long = ip2long($ip_arr[0]);
		$x = ip2long($ip_arr[1]);
		$mask = long2ip($x) == $ip_arr[1] ? $x : (0xffffffff << (32 - $ip_arr[1]));
		$ip_long = ip2long($ip);
		return ($ip_long & $mask) == ($network_long & $mask);
	} else {
		$from = trim(ip2long(substr($network, 0, $d)));
		$to = trim(ip2long(substr($network, $d + 1)));
		$ip = ip2long($ip);
		return ($ip >= $from && $ip <= $to);
	}
}

function getNtpTime() {
	$time_servers = array(
		'ntp2.emn.fr',
		'time-a.timefreq.bldrdoc.gov',
		'utcnist.colorado.edu',
		'time.nist.gov',
		'ntp.pads.ufrj.br',
	);
	$time_adjustment = 0;
	foreach ($time_servers as $time_server) {
		$fp = fsockopen($time_server, 37, $errno, $errstr, 1);
		if ($fp) {
			$data = NULL;
			while (!feof($fp)) {
				$data .= fgets($fp, 128);
			}
			fclose($fp);
			if (strlen($data) == 4) {
				$NTPtime = ord($data[0]) * pow(256, 3) + ord($data[1]) * pow(256, 2) + ord($data[2]) * 256 + ord($data[3]);
				$TimeFrom1990 = $NTPtime - 2840140800;
				$TimeNow = $TimeFrom1990 + 631152000;
				return date("m/d/Y H:i:s", $TimeNow + $time_adjustment);
			}
		}
	}
	return false;
}

function cast($sourceObject, $destination) {
	$obj_in = serialize($sourceObject);
	return unserialize('O:' . strlen($destination) . ':"' . $destination . '":' . substr($obj_in, $obj_in[2] + 7));
}

function getIpFromString($_string) {
	$result = parse_url($_string);
	if (isset($result['host'])) {
		$_string = $result['host'];
	} else {
		$_string = str_replace(array('https://', 'http://'), '', $_string);
		if (strpos($_string, '/') !== false) {
			$_string = substr($_string, 0, strpos($_string, '/'));
		}
		if (strpos($_string, ':') !== false) {
			$_string = substr($_string, 0, strpos($_string, ':'));
		}
	}
	if (!filter_var($_string, FILTER_VALIDATE_IP)) {
		$_string = gethostbyname($_string);
	}
	return $_string;
}

function evaluate($_string) {
	if (!isset($GLOBALS['ExpressionLanguage'])) {
		$GLOBALS['ExpressionLanguage'] = new ExpressionLanguage();
	}
	$string = str_ireplace(array(' et ', ' and ', ' ou ', ' or ', ' xor '), array(' && ', ' && ', ' || ', ' || ', ' ^ '), $_string);
	if (strpos($string, '"') !== false || strpos($string, '\'') !== false) {
		$regex = "/(?:(?:\"(?:\\\\\"|[^\"])+\")|(?:'(?:\\\'|[^'])+'))/is";
		$r = preg_match_all($regex, $string, $matches);
		$c = count($matches[0]);
		for ($i = 0; $i < $c; $i++) {
			$string = str_replace($matches[0][$i], '--preparsed' . $i . '--', $string);
		}
	} else {
		$c = 0;
	}
	$expr = preg_replace("/([^=<>!])=([^=])/", "$1==$2", $string); // Replace all '=' by '==' and avoid '==' '===' '>=' '<=' '!=' '!=='
	if ($c > 0) {
		for ($i = 0; $i < $c; $i++) {
			$expr = str_replace('--preparsed' . $i . '--', $matches[0][$i], $expr);
		}
	}
	try {
		return $GLOBALS['ExpressionLanguage']->evaluate($expr);
	} catch (Exception $e) {
		//log::add('expression', 'debug', '[Parser 1] Expression : ' . $_string . ' tranformé en ' . $expr . ' => ' . $e->getMessage());
	}
	try {
		return $GLOBALS['ExpressionLanguage']->evaluate(str_replace('""', '"', $expr));
	} catch (Exception $e) {
		//log::add('expression', 'debug', '[Parser 2] Expression : ' . $_string . ' tranformé en ' . $expr . ' => ' . $e->getMessage());
	}
	return $_string;
}

/**
 *
 * @param string $_string
 * @return string
 */
function secureXSS($_string) {
	if ($_string === null) {
		return null;
	}
	return str_replace('&amp;', '&', htmlspecialchars($_string, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function minify($_buffer) {
	$search = array(
		'/\>[^\S ]+/s', // strip whitespaces after tags, except space
		'/[^\S ]+\</s', // strip whitespaces before tags, except space
		'/(\s)+/s', // shorten multiple whitespace sequences
	);
	$replace = array(
		'>',
		'<',
		'\\1',
	);
	return preg_replace($search, $replace, $_buffer);
}

function sanitizeAccent($_message) {
	$caracteres = array(
		'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', '@' => 'a',
		'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', '€' => 'e',
		'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
		'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
		'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'µ' => 'u',
		'Œ' => 'oe', 'œ' => 'oe',
		'$' => 's'
	);
	return preg_replace('#[^A-Za-z0-9 \n\.\'=\*:]+\#\)\(#', '', strtr($_message, $caracteres));
}

function isConnect($_right = '') {
	if (session_status() == PHP_SESSION_DISABLED || !isset($_SESSION) || !isset($_SESSION['user']) || !is_object($_SESSION['user'])) {
		return false;
	}
	$user = user::byId($_SESSION['user']->getId());
	if (!is_object($user)) {
		return false;
	}
	if (!$_SESSION['user']->is_Connected()) {
		return false;
	}
	if ($_right != '') {
		return ($_SESSION['user']->getProfils() == $_right);
	}
	return true;
}

function ZipErrorMessage($code) {
	switch ($code) {
		case 0:
			return 'No error';

		case 1:
			return 'Multi-disk zip archives not supported';

		case 2:
			return 'Renaming temporary file failed';

		case 3:
			return 'Closing zip archive failed';

		case 4:
			return 'Seek error';

		case 5:
			return 'Read error';

		case 6:
			return 'Write error';

		case 7:
			return 'CRC error';

		case 8:
			return 'Containing zip archive was closed';

		case 9:
			return 'No such file';

		case 10:
			return 'File already exists';

		case 11:
			return 'Can\'t open file';

		case 12:
			return 'Failure to create temporary file';

		case 13:
			return 'Zlib error';

		case 14:
			return 'Malloc failure';

		case 15:
			return 'Entry has been changed';

		case 16:
			return 'Compression method not supported';

		case 17:
			return 'Premature EOF';

		case 18:
			return 'Invalid argument';

		case 19:
			return 'Not a zip archive';

		case 20:
			return 'Internal error';

		case 21:
			return 'Zip archive inconsistent';

		case 22:
			return 'Can\'t remove file';

		case 23:
			return 'Entry has been deleted';

		default:
			return 'An unknown error has occurred(' . intval($code) . ')';
	}
}

function arg2array($_string) {
	$return = array();
	$re = '/[\/-]?(([a-zA-Z0-9áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœ_#]+)(?:[=:]("[^"]+"|[^\s"]+))?)(?:\s+|$)/';
	preg_match_all($re, $_string, $matches, PREG_SET_ORDER, 0);
	foreach ($matches as $match) {
		if (count($match) != 4) {
			continue;
		}
		$return[$match[2]] = $match[3];
	}
	return $return;
}

function strToHex($string) {
	$hex = '';
	$calculateStrLen = strlen($string);
	for ($i = 0; $i < $calculateStrLen; $i++) {
		$ord = ord($string[$i]);
		$hexCode = dechex($ord);
		$hex .= substr('0' . $hexCode, -2);
	}
	return strToUpper($hex);
}

function hex2rgb($hex) {
	$hex = str_replace("#", "", $hex);
	if (strlen($hex) == 3) {
		$r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
		$g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
		$b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
	} else {
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
	}
	return array($r, $g, $b);
}

function getDominantColor($_pathimg, $_level = null, $_smartMode = false) {
	$colors = array();
	$i = imagecreatefromjpeg($_pathimg);
	$imagesX = imagesx($i);
	$imagesY = imagesy($i);
	$ratio = $imagesX / $imagesY;
	$size = 270;
	$img = imagecreatetruecolor($size, $size / $ratio);
	imagecopyresized($img, $i, 0, 0, 0, 0, $size, $size / $ratio, $imagesX, $imagesY);
	$imagesX = imagesx($img);
	$imagesY = imagesy($img);
	for ($x = 0; $x < $imagesX; $x++) {
		for ($y = 0; $y < $imagesY; $y++) {
			$rgb = imagecolorat($img, $x, $y);
			if ($_smartMode) {
				$sum = (($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF);
				if ($sum < 150) {
					continue;
				}
				if ($sum > 650) {
					continue;
				}
			}
			if (!isset($colors[$rgb])) {
				$colors[$rgb] = array('value' => $rgb, 'nb' => 0);
			}
			$colors[$rgb]['nb']++;
		}
	}
	usort($colors, function ($a, $b) {
		return $b['nb'] - $a['nb'];
	});

	if ($_level == null) {
		if ($colors[0]['value'] == 0) {
			return '#' . substr("000000" . dechex($colors[1]['value']), -6);
		}
		return '#' . substr("000000" . dechex($colors[0]['value']), -6);
	}
	$return = array();
	$colors = array_slice($colors, 0, $_level * 50);
	$previous_color = -1;
	foreach ($colors as $color) {
		if ($_smartMode && $previous_color > 0 && colorsAreClose($previous_color, $color['value'], 50)) {
			continue;
		}
		$return[] = '#' . substr("000000" . dechex($color['value']), -6);
		$previous_color = $color['value'];
	}
	if (count($return) < $_level && count($return) > 0) {
		for ($i = 0; $i < ($_level - count($return)); $i++) {
			$return[] = $return[$i];
		}
	}
	return $return;
}

function colorsAreClose($_c1, $_c2, $_threshold) {
	$rDist = abs((($_c1 >> 16) & 0xFF) - (($_c2 >> 16) & 0xFF));
	$gDist = abs((($_c1 >> 8) & 0xFF) - (($_c2 >> 8) & 0xFF));
	$bDist = abs(($_c1 & 0xFF) - ($_c2 & 0xFF));
	return (($rDist + $gDist + $bDist) < $_threshold);
}

function sha512($_string) {
	return hash('sha512', $_string);
}

function findCodeIcon($_icon) {
	$icon = trim(str_replace(array('fa ', 'fas ', 'fab ', 'far ', 'icon ', '></i>', '<i', 'class="', '"', 'icon_green', 'icon_blue', 'icon_yellow', 'icon_orange', 'icon_red'), '', trim($_icon)));

	$re = '/.' . $icon . ':.*\n.*content:.*"(.*?)";/m';

	$css = file_get_contents(__DIR__ . '/../../3rdparty/font-awesome5/css/all.css');
	preg_match($re, $css, $matches);
	if (isset($matches[1])) {
		return array('icon' => trim($matches[1], '\\'), 'fontfamily' => 'Font Awesome 5 Free');
	}

	foreach (ls(__DIR__ . '/../css/icon', '*') as $dir) {
		if (is_dir(__DIR__ . '/../css/icon/' . $dir) && file_exists(__DIR__ . '/../css/icon/' . $dir . '/style.css')) {
			$css = file_get_contents(__DIR__ . '/../css/icon/' . $dir . '/style.css');
			preg_match($re, $css, $matches);
			if (isset($matches[1])) {
				return array('icon' => trim($matches[1], '\\'), 'fontfamily' => trim($dir, '/'));
			}
		}
	}
	return array('icon' => '', 'fontfamily' => '');
}

function addGraphLink($_from, $_from_type, $_to, $_to_type, &$_data, $_level, $_drill, $_display = array('dashvalue' => '5,3', 'lengthfactor' => 0.6)) {
	if (is_array($_to) && count($_to) == 0) {
		return;
	}
	if (!is_array($_to)) {
		if (!is_object($_to)) {
			return;
		}
		$_to = array($_to);
	}
	foreach ($_to as $to) {
		$to->getLinkData($_data, $_level, $_drill);
		if (isset($_data['link'][$_to_type . $to->getId() . '-' . $_from_type . $_from->getId()])) {
			continue;
		}
		if (isset($_data['link'][$_from_type . $_from->getId() . '-' . $_to_type . $to->getId()])) {
			continue;
		}
		$_data['link'][$_to_type . $to->getId() . '-' . $_from_type . $_from->getId()] = array(
			'from' => $_to_type . $to->getId(),
			'to' => $_from_type . $_from->getId(),
		);
		$_data['link'][$_to_type . $to->getId() . '-' . $_from_type . $_from->getId()] = array_merge($_data['link'][$_to_type . $to->getId() . '-' . $_from_type . $_from->getId()], $_display);
	}
	return $_data;
}

function getSystemMemInfo() {
	$data = explode("\n", file_get_contents("/proc/meminfo"));
	$meminfo = array();
	foreach ($data as $line) {
		$info = explode(":", $line);
		if (count($info) != 2) {
			continue;
		}
		$value = explode(' ', trim($info[1]));
		$meminfo[$info[0]] = trim($value[0]);
	}
	return $meminfo;
}

function strContain($_string, $_words) {
	foreach ($_words as $word) {
		if (strpos($_string, $word) !== false) {
			return true;
		}
	}
	return false;
}

function makeZipSupport() {
	$jeedom_folder = __DIR__ . '/../..';
	$folder = '/tmp/jeedom_support';
	$outputfile = $jeedom_folder . '/support/jeedom_support_' . date('Y-m-d_His') . '.tar.gz';
	if (file_exists($folder)) {
		rrmdir($folder);
	}
	mkdir($folder);
	system('cd ' . $jeedom_folder . '/log;cp -R * "' . $folder . '" > /dev/null;cp -R .[^.]* "' . $folder . '" > /dev/null');
	system('sudo dmesg >> ' . $folder . '/dmesg');
	system('sudo cp /var/log/messages "' . $folder . '/" > /dev/null');
	system('sudo chmod 777 -R "' . $folder . '" > /dev/null');
	system('cd ' . $folder . ';tar cfz "' . $outputfile . '" * > /dev/null;chmod 777 ' . $outputfile);
	rrmdir($folder);
	return realpath($outputfile);
}

function decodeSessionData($_data) {
	$return_data = array();
	$offset = 0;
	while ($offset < strlen($_data)) {
		if (!strstr(substr($_data, $offset), "|")) {
			throw new Exception("invalid data, remaining: " . substr($_data, $offset));
		}
		$pos = strpos($_data, "|", $offset);
		$num = $pos - $offset;
		$varname = substr($_data, $offset, $num);
		$offset += $num + 1;
		$data = unserialize(substr($_data, $offset));
		$return_data[$varname] = $data;
		$offset += strlen(serialize($data));
	}
	return $return_data;
}

function listSession() {
	$return = array();
	$sessions = explode("\n", com_shell::execute(system::getCmdSudo() . ' ls -t ' . session_save_path()));
	if (count($sessions) > 100) {
		throw new Exception(__('Trop de sessions, je ne peux pas lister :', __FILE__) . ' ' . count($sessions) . __('. Faire, pour les nettoyer :', __FILE__) . ' ' . '"sudo rm -rf ' . session_save_path() . ';sudo mkdir ' . session_save_path() . ';sudo chmod 777 ' . session_save_path() . '"');
	}
	foreach ($sessions as $session) {
		try {
			$data = com_shell::execute(system::getCmdSudo() . ' cat ' . session_save_path() . '/' . $session);
			if ($data == '') {
				continue;
			}
			try {
				$data_session = decodeSessionData($data);
			} catch (Exception $e) {
				continue;
			}
			if (!isset($data_session['user']) || !is_object($data_session['user'])) {
				continue;
			}
			$session_id = str_replace('sess_', '', $session);
			$return[$session_id] = array(
				'datetime' => date('Y-m-d H:i:s', com_shell::execute(system::getCmdSudo() . ' stat -c "%Y" ' . session_save_path() . '/' . $session)),
			);
			$return[$session_id]['login'] = $data_session['user']->getLogin();
			$return[$session_id]['user_id'] = $data_session['user']->getId();
			$return[$session_id]['ip'] = (isset($data_session['ip'])) ? $data_session['ip'] : '';
		} catch (Exception $e) {
		}
	}
	return $return;
}

function deleteSession($_id) {
	@unlink(session_save_path() . '/sess_' . $_id);
}

function unautorizedInDemo($_user = null) {
	if ($_user === null) {
		if (!isset($_SESSION) || !isset($_SESSION['user'])) {
			return;
		}
		$_user = $_SESSION['user'];
	}
	if (!is_object($_user)) {
		return;
	}
	if ($_user->getLogin() == 'demo') {
		throw new Exception(__('Cette action n\'est pas autorisée en mode démo', __FILE__));
	}
}

function checkAndFixCron($_cron) {
	$return = trim($_cron);
	$return = str_replace('*/ ', '* ', $return);
	preg_match_all('/([0-9]*\/\*)/m', $return, $matches, PREG_SET_ORDER, 0);
	if (count($matches) > 0) {
		return '';
	}
	preg_match_all('/(\*\/0)/m', $return, $matches, PREG_SET_ORDER, 0);
	if (count($matches) > 0) {
		return '';
	}
	$arrays = explode(' ', $return);
	if (count($arrays) > 5) {
		unset($arrays[5]);
		$return = implode(' ', $arrays);
	}
	return $return;
}

function cronIsDue($_cron,$_datetime = null){
	if (((new DateTime('today midnight +1 day'))->format('I') - (new DateTime('today midnight'))->format('I')) == -1 && date('I') == 1 && date('Gi') > 159) {
		return false;
	}
	if($_datetime == null){
		$_datetime = date('Y-m-d H:i:s');
	}
	$schedule = explode(' ',trim($_cron));
	if(count($schedule) == 6 && $schedule[5] !=  '*' && $schedule[5] != date('Y')){
		return false;
	}
	try {
		$c = new Cron\CronExpression(checkAndFixCron($_cron), new Cron\FieldFactory);
		return $c->isDue($_datetime);
	} catch (Exception $e) {

	} catch (Error $e) {

	}
	return false;
}

function getTZoffsetMin() {
	$tz = date_default_timezone_get();
	date_default_timezone_set("UTC");
	$seconds = timezone_offset_get(timezone_open($tz), new DateTime());
	date_default_timezone_set($tz);
	return ($seconds / 60);
}

function pageTitle($_page) {
	switch ($_page) {
		case 'overview':
			$return = __('Synthèse', __FILE__);
			break;
		case 'view':
			$return = __('Vues', __FILE__);
			break;
		case 'plan':
			$return = __('Designs', __FILE__);
			break;
		case 'plan3d':
			$return = __('Designs 3D', __FILE__);
			break;
		case 'eqAnalyse':
			$return = __('Equipements', __FILE__);
			break;
		case 'display':
			$return = __('Résumé', __FILE__);
			break;
		case 'history':
			$return = __('Historique', __FILE__);
			break;
		case 'timeline':
			$return = __('Timeline', __FILE__);
			break;
		case 'report':
			$return = __('Rapports', __FILE__);
			break;
		case 'replace':
			$return = __('Remplacement', __FILE__);
			break;
		case 'health':
			$return = __('Santé', __FILE__);
			break;
		case 'object':
			$return = __('Objets', __FILE__);
			break;
		case 'scenario':
			$return = __('Scénarios', __FILE__);
			break;
		case 'interact':
			$return = __('Interactions', __FILE__);
			break;
		case 'widgets':
			$return = __('Widgets', __FILE__);
			break;
		case 'plugin':
			$return = __('Gestion Plugins', __FILE__);
			break;
		case 'backup':
			$return = __('Sauvegardes', __FILE__);
			break;
		case 'administration':
			$return = __('Configuration', __FILE__);
			break;
		case 'database':
			$return = __('Base de données', __FILE__);
			break;
		case 'massedit':
			$return = __('Editeur en masse', __FILE__);
			break;
		case 'cron':
			$return = __('Moteur de tâches', __FILE__);
			break;
		case 'custom':
			$return = __('Personnalisation', __FILE__);
			break;
		case 'user':
			$return = __('Utilisateurs', __FILE__);
			break;
		case 'profils':
			$return = __('Préférences', __FILE__);
			break;
		case 'log':
			$return = __('Logs', __FILE__);
			break;
		case 'update':
			$return = __('Mises à jour', __FILE__);
			break;
		case 'panel':
			try {
				if (isset($_SERVER['REQUEST_URI'])) {
					$url = $_SERVER['REQUEST_URI'];
					$plugin = explode('m=', $url)[1];
					$plugin = explode('&', $plugin)[0];
					$return = __('Panel', __FILE__) . ' ' . ucfirst($plugin);
				} else {
					$return = __('Panel', __FILE__);
				}
				break;
			} catch (Exception $e) {
				$return = __('Panel', __FILE__);
				break;
			}
		default:
			$return = $_page;
			break;
	}
	return ucfirst($return);
}

function cleanComponanteName($_name) {
	$return =  strip_tags(str_replace(array('&', '#', ']', '[', '%', "\\", "/", "'", '"', "*"), '', $_name));
	$return = preg_replace('/\s+/', ' ', $return);
	return $return;
}

function startsWith($haystack, $needle) {
	return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
}
function endsWith($haystack, $needle) {
	return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}

function getWhiteListFolders($_plugin = 'all') {
	$pluginsAll = ($_plugin != 'all') ? array($_plugin) : plugin::listPlugin(true,    false,   true,  true);
	$result = array();
	foreach ($pluginsAll as $pluginId) {
		$plugin = plugin::byId($pluginId);
		if (!is_object($plugin)) continue;

		$publicFolders = $plugin->getWhiteListFolders();
		if (count($publicFolders) == 0) continue;

		$rootPath = realpath(plugin::getPluginPath($pluginId));
		if ($rootPath === false) continue;

		foreach ($publicFolders as $folder) {
			if (strpos($folder, '..') !== false) continue;
			$current = realpath($rootPath . '/' . $folder);
			if ($current != "" && !in_array($current, $result)) $result[] =  $current;
		}
	}
	return $result;
}

function implode_recursive($_array, $_separator, $_key = '') {
	$result = array();
	foreach ($_array as $i => $a) {
		if (is_array($a)) {
			$result = array_merge($result, implode_recursive($a, $_separator,  $_key . $i . $_separator));
		} else {
			$result = array_merge($result, array($_key . $i => $a));
		}
	}
	return $result;
}