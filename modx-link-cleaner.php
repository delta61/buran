<?php
/**
 * MODX & Evolution CMS Link Cleaner
 * 10.04.2023
 * Delta
 * sergey.it@delta-ltd.ru
 *
 */

error_reporting(0);
ini_set('display_errors','off');

$bu = new BURAN('1.01-beta1');

if ( ! $bu->act) exit();

if ('info' == $bu->act) {
	$mres = $bu->lnksinfls();
	print '<pre>';
	print_r($mres);
	print '</pre>';
}

exit();

// ----------------------------------------------------------

class BURAN
{
	public $conf = array(
		'def' => array(
			'debug' => 0,

			'maxtime'     => 22,
			'maxmemory'   => 109715200, //1024*1024*200
			'maxitems'    => 99999,

			'etalon_ext' => '/.php/.html/.htm/.js/.inc/.css/.sass/.scss/.less/.tpl/.twig/.ini/.json/',

			'fls_archive_without_ext' => '', // '/.jpg/.jpeg/.png/',
			'fls_archive_without_dir' => array(
				// '/_buran/',
				// '/assets/images/',
				// '/assets/cache/images/',
				// '/box/',
			),
		),

		'db' => array(
			'maxitems' => 100000,
		),
	);

	// ----------------------------------------------------

	function __construct($version)
	{
		$this->version = $version;
		$this->mfile   = 'modx-link-cleaner.php';
		$this->mdir    = '/_buran/buran';
		$this->bunker  = 'bunker-yug.ru';
		$this->mua     = 'BuranModule/'.$version;
		$this->mhash   = md5(__FILE__);

		$this->mct_start = microtime(true);

		$this->act = $_GET['a'] ? $_GET['a'] : $_GET['act'];

		$this->droot = dirname(dirname(__FILE__));
		$this->broot = dirname(__FILE__);

		$this->https = (
			(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ||
			(isset($_SERVER['HTTP_PORT']) && $_SERVER['HTTP_PORT']     == '443') ||
			(isset($_SERVER['HTTP_HTTPS']) && $_SERVER['HTTP_HTTPS']   == 'on') ||
			(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')) ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
				? true : false
		);
		$this->adrscheme = $this->https ? 'https://' : 'http://';
		$domain = isset($_SERVER['HTTP_HOST'])
				? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
		$domain = explode(':', $domain);
		$domain = $domain[0];
		$this->www = '';
		if (strpos($domain,'www.') === 0) {
			$this->www = 'www.';
			$domain = substr($domain, 4);
		}
		$this->domain = $domain;
		$this->scriptname = isset($_SERVER['SCRIPT_NAME'])
			? $_SERVER['SCRIPT_NAME']
			: $_SERVER['PHP_SELF'];
		$this->uri = $_SERVER['REQUEST_URI'];

		$this->bunker_prcl = substr($_SERVER['HTTP_ORIGIN'],0,strpos($_SERVER['HTTP_ORIGIN'],'://')+3);

		$this->iswritable = is_writable($this->droot.$this->mdir.'/')
			? true : false;
		$this->isreadable = is_readable($this->droot.$this->mdir.'/')
			? true : false;

		$this->curl_ext = extension_loaded('curl') &&
			function_exists('curl_init') ? true : false;

		$this->sock_ext = function_exists('stream_socket_client')
			? true : false;

		$this->fgc_ext = function_exists('file_get_contents')
			? true : false;

		if (isset($_GET['uniq'])) {
			$uniq = $_GET['uniq'];
			$uniq = str_replace(array('/','..',' '),'',$uniq);
			$this->uniq = $uniq;
		}
		if ( ! $this->uniq) $this->uniq = date('Y-m-d-H-i-s');

		header('Access-Control-Allow-Origin: *');
	}

	// ----------------------------------------------------

	function lnksinfls()
	{
		$res = array(
			'method' => 'lnksinfls',
			'ok'     => 'n',
		);

		$state_new = true;
		$state = array(
			'files'   => 0,
			'd_queue' => array('/'),
			'f_queue' => array(),
		);

		$res['state'] = $state;

		$this->max['cntr'][0] = array(
			'nm'  => 'maxitems',
			'max' => $this->conf('maxitems'),
			'cnt' => 0,
		);

		$ii = 0;
		$ii2 = 0;
		$wss = array();
		$lnkscnt = 0;
		$exts = array();
		while (true) {
			while (true) {
				$file = array_shift($state['f_queue']);
				if ( ! $file) break;

				$ext = substr($file,strrpos($file,'.'));
				$ext = strtolower($ext);
				if (strpos($this->conf('fls_archive_without_ext'),
					'/'.$ext.'/') !== false) {
					continue;
				}

				$etalon_ext = '/.json/.php/.md/.txt/.lock/.example/.js/.html/.textile/.tpl/.phtml/.xml/.default/.ini/.css/.yml/.htm/.inc/.sass/.scss/.less/.twig/';
				if (strpos($etalon_ext,'/'.$ext.'/') === false) {
					continue;
				}

				$fh = fopen($this->droot.$file,'rb');
				if ( ! $fh) {
					$res['errors'][] = array(
						'fl' => $file,
						'txt' => 'fopen',
					);
					continue;
				}
				$fldt = '';
				while ( ! feof($fh)) {
					$fldt .= fread($fh,1024*256);
				}
				fclose($fh);

				$regexp = "/http[s]?:\/\/([a-zA-Z0-9\.\-]+?)[a-zA-Z0-9\.\-\/]*?/isU";
				$mtchscnt = preg_match_all($regexp, $fldt, $mtchs);
				if ($mtchscnt) {
					$ii2++;
					if ($ii2 > 10) {
						$flag_max = true;
						break;
					}

					print '='.$file;
					// print '<pre>';
					// print_r($mtchs[0]);
					// print '</pre>';
					foreach ($mtchs[0] AS $key => $row) {
						$lnkscnt++;
						// $wss[$mtchs[0][$key]][$file] = 1;
						// $exts[$ext][$file][$mtchs[0][$key]] = 1;
					}

					$regexp2 = "/(http[s]?:\/\/)([a-zA-Z0-9\.\-]+?)(.?)/isU";
					$fldt_res = preg_replace($regexp2, '${1}'.$this->domain.'${3}', $fldt);

					print $fldt;
					print '=====================';
					print $fldt_res;
					print '=====================';
				}
				
				$ii++;
				$this->max['cntr'][0]['cnt']++;
				$state['files']++;
				
				if ($this->max()) {
					$flag_max = true;
					break;
				}
				if ($ii % 2000 == 0) sleep(2);
			}
			if ($flag_max) break;

			$state['f_queue'] = array();
			$nextdir = array_shift($state['d_queue']);
			if ( ! $nextdir) break;
			if ( ! ($open = opendir($this->droot.$nextdir))) {
				$res['errors'][] = array('num'=>'0802');
				continue;
			}
			while ($file = readdir($open)) {
				if (
					filetype($this->droot.$nextdir.$file) == 'link'
					|| $file == '.' || $file == '..'
					|| $file == '.th'
					|| $nextdir.$file == $this->mdir
				) {
					continue;
				}
				if (is_dir($this->droot.$nextdir.$file)) {
					$without_dir = $this->conf('fls_archive_without_dir');
					if (in_array($nextdir.$file.'/',$without_dir)) {
						continue;
					}
					$state['d_queue'][] = $nextdir.$file.'/';
					continue;
				}
				if ( ! is_file($this->droot.$nextdir.$file)) {
					continue;
				}
				$state['f_queue'][] = $nextdir.$file;
			}
		}

		print '<pre>';
		// print_r($exts);
		// print_r($wss);
		print '</pre>';

		$res['lnkscnt'] = $lnkscnt;

		if ($this->max['flag']) {
			$res['max'] = true;
		} else {
			$res['completed'] = 'y';
		}

		$res['state'] = $state;
		$res['ok'] = 'y';
		return $res;
	}

	// ----------------------------------------------------

	function max()
	{
		$mct = $this->mct_passed();
		$memory = memory_get_peak_usage(true);
		$res = false;
		if (is_array($this->max['cntr'])) {
			foreach ($this->max['cntr'] AS $row) {
				if ($row['cnt'] >= $row['max']) {
					$res = true;
					break;
				}
			}
		}
		if (
			$mct >= $this->conf('maxtime')
			|| $memory >= $this->conf('maxmemory')
		) $res = true;
		if ($res) {
			$this->max['flag'] = true;
			$this->res['max'] = array(
				'flg' => true,
				'mct' => $mct,
				'mem' => $memory,
				'cnt' => $this->max['cntr'],
			);
		}
		return $res;
	}

	function conf($name,$tp='def')
	{
		return isset($this->conf[$tp][$name]) ? $this->conf[$tp][$name] : NULL;
	}

	function proccess_state($proc, $data=false, $serz=false)
	{
		$file = $this->droot.$this->mdir.$proc;
		if ('rem' == $data) {
			$res = unlink($file);
			return $res;
		}
		if ($data === false) {
			if ( ! file_exists($file)) return;
			$fh = fopen($file,'rb');
			if ( ! $fh) return false;
			$res = '';
			while ( ! feof($fh)) {
				$res .= fread($fh,1024*256);
			}
			fclose($fh);
			if ($serz) $res = unserialize($res);
			return $res;
		}
		$fh = fopen($file,'wb');
		if ( ! $fh) return false;
		if ($serz) $data = serialize($data);
		$res = fwrite($fh,$data);
		if ( ! $res) return false;
		fclose($h);
		return true;
	}
	
	function filetime($file, $type='c')
	{
		if ( ! file_exists($file)) return false;
		switch ($type) {
			case 'a': $time = fileatime($file); break;
			case 'm': $time = filemtime($file); break;
			default: $time = filectime($file);
		}
		return $time ? $time : false;
	}
	
	function cms()
	{
		ob_start();
		@include_once($this->droot.'/manager/includes/version.inc.php');
		ob_end_clean();
		if (isset($modx_full_appname) && $modx_full_appname) {
			if (strpos($modx_full_appname, 'MODX') === 0) {
				$this->cms = 'modx.evo';
			} else {
				$this->cms = 'evolution';
			}
			$this->cms_ver  = $modx_version;
			$this->cms_date = $modx_release_date;
			$this->cms_name = $modx_full_appname;
			$this->cache_dirs = array(
				'/assets/cache/',
			);
			return true;
		}
		return false;
	}

	function db_access()
	{
		$this->db_method  = 'SET CHARACTER SET';
		$this->db_charset = 'utf8';

		if ($this->cms == 'modx.evo' || $this->cms == 'evolution') {
			ob_start();
			@include_once($this->droot.'/manager/includes/config.inc.php');
			ob_end_clean();
			$this->db_host    = $database_server;
			$this->db_user    = $database_user;
			$this->db_pwd     = $database_password;
			$this->db_name    = trim($dbase,"`");
			$this->db_method  = $database_connection_method;
			$this->db_charset = $database_connection_charset;
			$this->db_table_prefix = $table_prefix;
			return true;
		}
		return false;
	}

	function db_connect()
	{
		$res = array(
			'method' => 'db_connect',
			'ok' => 'n',
		);
		if ($this->db && ($this->db instanceof mysqli)) {
			$res['ok'] = 'y';
			return $res;
		}
		$db = new mysqli($this->db_host,$this->db_user,$this->db_pwd,$this->db_name);
		if ( ! $db || ! ($db instanceof mysqli)) {
			$this->res['errors'][] = array('num'=>'0701');
			return $res;
		}
		$dbres = $db->query("{$this->db_method} {$this->db_charset}");
		if ( ! $dbres) {
			$this->res['errors'][] = array('num'=>'0702');
			return $res;
		}
		$this->db = $db;
		$res['ok'] = 'y';
		return $res;
	}

	function mct_passed($m='start', $set_last=false)
	{
		$mct = microtime(true);
		if ('last' == $m) {
			if ( ! $this->mct_passed_last) {
				$this->mct_passed_last = $this->mct_start;
			}
			$res = $mct - $this->mct_passed_last;
		} else {
			$res = $mct - $this->mct_start;
		}
		if ($set_last) $this->mct_passed_last = $mct;
		$res = round($res,4);
		return $res;
	}

	function sizeType($st)
	{
		$sta = array(
			'b', 'Kb', 'Mb', 'Gb', 'Tb'
		);
		return $sta[$st];
	}
}
