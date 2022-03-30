<?php
/**
 * BURAN
 *
 * @copyright 2022 DELTA https://delta-ltd.ru/
 * @author    <sergey.it@delta-ltd.ru>
 */

error_reporting(0);
ini_set('display_errors','off');

$bu = new BURAN('4.0-beta-6');

$mres = $bu->auth($_GET['w']);
if ($mres['ok'] !== 'y') exit();

if ( ! $bu->act) exit();

if ('main' == $bu->act) {
	$bu->page_main();
	exit();
}
if ('backup' == $bu->act) {
	if (isset($_GET['do'])) {
		$bu->act_backup();
		exit();
	}
	$bu->page_backup();
	exit();
}
if ('etalon' == $bu->act) {
	if (isset($_GET['do'])) {
		$bu->act_etalon();
		exit();
	}
	$pntres = $bu->page_etalon();
	exit();
}

/*
if ('info' == $bu->act) {
	$mres = $bu->info();
	$bu->res['mres'][] = $mres;
}

if ('update' == $bu->act) {
	$file = $_GET['file'];
	$mres = $bu->update($file);
	$bu->res['mres'][] = $mres;
}
if ('setconfig' == $bu->act) {
	$data = $_POST['data'];
	$mres = $bu->setconfig($data);
	$bu->res['mres'][] = $mres;
}

if ('modx_unblock_admin_user' == $bu->act) {
	$mres = $bu->modx_unblock_admin_user();
	$bu->res['mres'][] = $mres;
}

if ('phpinfo' == $bu->act) {
	$bu->res_ctp = 'html';
	$bu->res['data'] = $bu->getphpinfo();
}

if ('fls_remove' == $bu->act) {
	$path = $_GET['path'];
	$mres = $bu->fls_remove($path);
	$bu->res['mres'][] = $mres;
}

if ('fls_explorer' == $bu->act) {
	$bu->res_ctp = 'html';
	$path = $_GET['path'];
	$bu->res['data'] = $bu->fls_explorer($path);
}

if ('fls_structure' == $bu->act) {
	$path = $_GET['path'];
	$mres = $bu->fls_structure($path);
	$bu->res['mres'][] = $mres;
}
*/

exit();

// ----------------------------------------------------------

class BURAN
{
	public $conf = array(
		'def' => array(
			'debug' => 0,

			'maxtime'     => 25,
			'maxmemory'   => 109715200, //1024*1024*200
			// 'maxitems'    => 15000,
			'maxitems'    => 5000,

			'flag_db_dump'             => true,
			'flag_files_backup'        => true,
			// 'files_backup_maxpartsize' => 209715200, //1024*1024*200
			'files_backup_maxpartsize' => 50715200, //1024*1024*200

			'etalon_ext' => '/.php/.htaccess/.html/.htm/.js/.inc/.css/.sass/.scss/.less/.tpl/.twig/.ini/.json/',

			'fls_archive_without_ext' => '', // '/.jpg/.jpeg/.png/',
			'fls_archive_without_dir' => array(
				// '/_buran/',
				// '/assets/images/',
				// '/assets/cache/images/',
				// '/box/',
			),

			'etalon_mode' => 'all', // [all, list, files]

			'etalon_dir'     => '/etalon',
			'backup_dir'     => '/backup',
			'etalon_db_dir'  => '/db',
			'etalon_fls_dir' => '/files',
			'etalon_lst_dir' => '/list',
			'etalon_cmpr_dir' => '/cmpr',

			'log_dir' => '/log',

			'max_etalon_txt_file' => 52428800, //1024*1024*50
		),

		'db' => array(
			// 'maxitems' => 100000,
			// 'db_dump_maxpartsize' => 52428800, //1024*1024*50
			'maxitems' => 10000,
			'db_dump_maxpartsize' => 2428800, //1024*1024*50
		),
	);

	// ----------------------------------------------------

	function __construct($version)
	{
		$this->version = $version;
		$this->mfile   = '_buran_new.php';
		$this->mdir    = '/_buran/buran';
		$this->bunker  = 'bunker-yug.ru';
		$this->mua     = 'BuranModule/'.$version;
		$this->mhash   = md5(__FILE__);

		$this->actfile = false;

		$this->reqres = array();
		$this->res_wthtpl = true;

		$this->mct_start = microtime(true);

		$this->act = $_GET['a'] ? $_GET['a'] : $_GET['act'];

		$this->droot = dirname(dirname(__FILE__));
		$this->broot = dirname(__FILE__);

		$this->http  = (
			(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ||
			(isset($_SERVER['HTTP_PORT']) && $_SERVER['HTTP_PORT']     == '443') ||
			(isset($_SERVER['HTTP_HTTPS']) && $_SERVER['HTTP_HTTPS']   == 'on') ||
			(isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
				$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
				? 'https://' : 'http://');
		$domain = isset($_SERVER['HTTP_HOST'])
			? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
		$domain = explode(':',$domain);
		$domain = $domain[0];
		$this->www = '';
		if (strpos($domain,'www.') === 0) {
			$this->www = 'www.';
			$domain = substr($domain,4);
		}
		$this->domain = $domain;
		$this->scriptname = isset($_SERVER['SCRIPT_NAME'])
						? $_SERVER['SCRIPT_NAME']
						: $_SERVER['PHP_SELF'];
		$this->uri = $_SERVER['REQUEST_URI'];

		$this->usrip = $_SERVER['REMOTE_ADDR'];

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

		$this->targzisavailable = function_exists('gzopen') ? true : false;
		$this->tar = false;
		$this->zipisavailable = false;
		$this->zip = false;
		if (class_exists('ZipArchive')) {
			$zip = new ZipArchive();
			if ($zip && ($zip instanceof ZipArchive)) {
				$this->zipisavailable = true;
				$this->zip = $zip;
			}
		}

		$globinfo = $this->bufile('globinfo', 'get');
		if (
			$globinfo
			&& is_array($globinfo)
		) {
			$this->globinfo = $globinfo;
		} else {
			$this->globinfo = array(
				'dt' => time(),
			);
		}

		if (isset($_GET['uniq'])) {
			$uniq = $_GET['uniq'];
			$uniq = str_replace(array('/','..',' '),'',$uniq);
			$this->uniq = $uniq;

			$actfile = $this->bufile('acts', 'get', $this->uniq);
			if (
				$actfile
				&& is_array($actfile)
				&& $actfile['uniq'] == $this->uniq
			) $this->actfile = $actfile;
		}

		$userconfig = $this->bufile('config_value','get');
		if (is_array($userconfig)) {
			foreach ($this->conf AS $key => $row) {
				if ( ! $userconfig[$key] || ! is_array($userconfig[$key])) {
					continue;
				}
				$this->conf[$key] = array_merge($this->conf[$key],$userconfig[$key]);
			}
		}

		$res = ob_start(array($this,'ob_end'));
	}

	// --------------------------------------------

	function act_backup()
	{
		$method = 'act_backup';
		$errnum = '11';

		$minfo = $this->actfile['methods'][$method];
		if ( ! $minfo) {
			$minfo_new = true;
			$minfo = array(
				'mthd_nm' => 'Резервное копирование',
				'method' => $method,
				'state' => array(
					'failact_cnt' => 0,
					'step' => array(
						'num' => 1,
					),
				),
			);
		}
		$minfo['state']['failact_cnt']++;
		$minfo['res'] = array(
			'ok' => 'n',
			'errors' => array(),
			'notice' => array(),
		);

		if ($minfo['state']['step']['num'] === 1) {
			$step_flag = true;

			$subres = $this->db_dump();

			if (
				$subres['completed'] == 'y'
				|| (
					$subres['res']['ok'] != 'y'
					&& $subres['state']['failact_cnt'] >= 3
				)
			) $nextstep = true;
		}

		if ($minfo['state']['step']['num'] === 2) {
			$step_flag = true;

			$subres = $this->fls_archive();

			if (
				$subres['completed'] == 'y'
				|| (
					$subres['res']['ok'] != 'y'
					&& $subres['state']['failact_cnt'] >= 3
				)
			) $nextstep = true;
		}

		if ($step_flag) {
			if ($nextstep) {
				$minfo['state']['step']['num']++;
			}
		} else {
			$minfo['completed'] = 'y';
		}

		$minfo['res']['ok'] = 'y';
		$minfo['state']['failact_cnt'] = 0;
		$this->actfile['methods'][$method] = $minfo;
		return $minfo;
	}

	function fls_archive()
	{
		$method = 'fls_archive';
		$errnum = '12';

		$minfo = $this->actfile['methods'][$method];
		if ( ! $minfo) {
			$minfo_new = true;
			$minfo = array(
				'mthd_nm' => 'Архивация файлов',
				'method' => $method,
				'prgrsbr' => array(
					'max' => 0,
					'curr' => 0,
				),
				'state' => array(
					'failact_cnt' => 0,
					'd_queue' => array('/'),
					'f_queue' => array(),
					'part' => 0,
					'files' => 0,
				),
			);
		}
		$minfo['state']['failact_cnt']++;
		$minfo['res'] = array(
			'ok' => 'n',
			'errors' => array(),
			'notice' => array(),
		);

		if ($this->globinfo['methods'][$method]['files']) {
			$minfo['prgrsbr']['max'] = $this->globinfo['methods'][$method]['files'];
		}

		$dir = $this->conf('backup_dir').'/'.$this->uniq.'/fls/';
		$folder = $this->droot.$this->mdir.$dir;
		if ( ! file_exists($folder)) mkdir($folder,0755,true);

		$part = $minfo['state']['part'];
		$part++;

		$archfile = '/'.$this->domain.'_fls_'.$this->uniq;
		$archfilepart = $archfile.'_part'.str_pad($part,2,'0',STR_PAD_LEFT);
		
		if ($this->zip) {
			$archfilepart .= '.zip';
			if (file_exists($folder.$archfilepart)) {
				$minfo['res']['notice'][] = array('num'=>$errnum.'02');
				$this->actfile['methods'][$method] = $minfo;
				return $minfo;
			}
			$this->zip->open($folder.$archfilepart,ZipArchive::CREATE);
			$this->zipfile = $folder.$archfilepart;

		} else {
			$archfilepart .= '.tar';
			if ($this->targzisavailable) $archfilepart .= '.gz';
			if (file_exists($folder.$archfilepart)) {
				$minfo['res']['notice'][] = array('num'=>$errnum.'03');
				$this->actfile['methods'][$method] = $minfo;
				return $minfo;
			}
			$tarres = $this->tarOpen($folder.$archfilepart);
			if ( ! $tarres) $this->tar = false;
		}

		if ( ! $this->zip && ! $this->tar) {
			$minfo['res']['errors'][] = array('num'=>$errnum.'04');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}

		$this->max['cntr'][0] = array(
			'nm'  => 'maxitems',
			'max' => $this->conf('maxitems'),
			'cnt' => 0,
		);
		$this->max['cntr'][1] = array(
			'nm'  => 'partsize',
			'max' => $this->conf('files_backup_maxpartsize'),
			'cnt' => 0,
		);

		$ii = 0;
		while (true) {
			while (true) {
				$file = array_shift($minfo['state']['f_queue']);
				if ( ! $file) break;

				$ext = substr($file,strrpos($file,'.'));
				$ext = strtolower($ext);
				if (strpos($this->conf('fls_archive_without_ext'),
					'/'.$ext.'/') !== false) {
					continue;
				}

				$fs = filesize($this->droot.$file);

				if (
					$fs*0.9 >= $this->conf('files_backup_maxpartsize')
					&& $ii >= 1
				) continue;
				
				$ii++;
				$this->max['cntr'][0]['cnt']++;
				$this->max['cntr'][1]['cnt'] += $fs;
				$minfo['state']['files']++;
				
				if ($this->zip) {
					$this->zip->addFile(
						$this->droot.$file,
						'www.'.$this->domain.$file
					);
				} else {
					$this->tarAddFile(
						$this->droot.$file,
						'www.'.$this->domain.$file
					);
				}
				
				if ($this->max()) {
					$flag_max = true;
					break;
				}
				if ($ii % 2000 == 0) sleep(2);
			}
			if ($flag_max) break;

			$minfo['state']['f_queue'] = array();
			$nextdir = array_shift($minfo['state']['d_queue']);
			if ( ! $nextdir) break;
			if ( ! ($open = opendir($this->droot.$nextdir))) {
				$minfo['res']['errors'][] = array('num'=>$errnum.'05');
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
					$minfo['state']['d_queue'][] = $nextdir.$file.'/';
					continue;
				}
				if ( ! is_file($this->droot.$nextdir.$file)) {
					continue;
				}
				$minfo['state']['f_queue'][] = $nextdir.$file;
			}
		}

		if ($this->zip) {
			$this->zip->close();
		} else {
			$this->tarEmptyRow();
			$this->tarClose();
		}

		if ($this->max['flag']) {
		} else {
			$minfo['completed'] = 'y';

			$this->globinfo['methods'][$method]['files'] = $minfo['state']['files'];
		}

		$minfo['state']['part'] = $part;
		$minfo['prgrsbr']['curr'] = $minfo['state']['files'];

		$minfo['res']['ok'] = 'y';
		$minfo['state']['failact_cnt'] = 0;
		$this->actfile['methods'][$method] = $minfo;
		return $minfo;
	}

	function db_dump()
	{
		$method = 'db_dump';
		$errnum = '13';

		$maxitems = intval($this->conf('maxitems','db'));

		$minfo = $this->actfile['methods'][$method];
		if ( ! $minfo) {
			$minfo_new = true;
			$minfo = array(
				'mthd_nm' => 'Дамп базы данных',
				'method' => $method,
				'state' => array(
					'failact_cnt' => 0,
					'tbl'    => false,
					'limit'  => $maxitems,
					'offset' => 0,
					'keys'   => '',
					'part' => 1,
					'lastpartsize' => 0,
					'itms' => 0,
				),
			);
		}
		$minfo['state']['failact_cnt']++;
		$minfo['res'] = array(
			'ok' => 'n',
			'errors' => array(),
			'notice' => array(),
		);

		if ($this->globinfo['methods'][$method]['itms']) {
			$minfo['prgrsbr']['max'] = $this->globinfo['methods'][$method]['itms'];
		}

		$dir = $this->conf('backup_dir').'/'.$this->uniq.'/db/';
		$folder = $this->droot.$this->mdir.$dir;
		if ( ! file_exists($folder)) mkdir($folder,0755,true);

		$part = $minfo['state']['part'];

		$dumpfile = '/'.$this->domain.'_db_'.$this->uniq;
		$dumpfilepart = $dumpfile.'_part'.str_pad($part,2,'0',STR_PAD_LEFT);
		$dumpfilepart .= '.sql';

		if (file_exists($folder.$dumpfilepart)) {
			// $minfo['res']['notice'][] = array('num'=>$errnum.'02');
			// $this->actfile['methods'][$method] = $minfo;
			// return $minfo;
		}

		$fres = $this->cms();
		if ( ! $fres) {
			$minfo['res']['notice'][] = array('num'=>$errnum.'03');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}
		$fres = $this->db_access();
		if ( ! $fres) {
			$minfo['res']['notice'][] = array('num'=>$errnum.'04');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}
		$dbcres = $this->db_connect();
		if ($dbcres['ok'] != 'y') {
			$minfo['res']['errors'][] = array('num'=>$errnum.'05');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}

		$dbres = $this->db->query("SHOW TABLES");
		if ( ! $dbres) {
			$minfo['res']['errors'][] = array('num'=>$errnum.'06');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}

		$limit = intval($minfo['state']['limit']);
		$this->max['cntr'][0] = array(
			'nm'  => 'maxitems',
			'max' => $limit,
			'cnt' => 0,
		);

		$dump = "# -- start / ".date('d.m.Y, H:i:s')."\n\n";
		while ($row = $dbres->fetch_row()) {

			if (
				$minfo['state']['tbl']
				&& $row[0] != $minfo['state']['tbl']
			) continue;
			$ii_tbl = $row[0];
			$minfo['state']['tbl'] = false;

			if ( ! $minfo['state']['offset']) {
				$dump .= "# ---------------------------- `".$row[0]."`"."\n\n";

				$dbres2 = $this->db->query("SELECT *
					FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
					WHERE TABLE_NAME=N'{$row[0]}'
					ORDER BY IF(CONSTRAINT_NAME='PRIMARY',0,1),CONSTRAINT_NAME,ORDINAL_POSITION");
				if ( ! $dbres2) {
					$minfo['res']['errors'][] = array('num'=>$errnum.'07');
					continue;
				}
				$keys = "";
				$keys_nm = false;
				while ($row2 = $dbres2->fetch_assoc()) {
					if (
						$keys_nm
						&& $keys_nm !== $row2['CONSTRAINT_NAME']
					) break;
					$keys_nm = $row2['CONSTRAINT_NAME'];
					$keys .= ($keys?",":"")."`".$row2['COLUMN_NAME']."`";
				}
				$minfo['state']['keys'] = $keys;

				$dbres2 = $this->db->query("SHOW CREATE TABLE `{$row[0]}`");
				if ( ! $dbres2) {
					$minfo['res']['errors'][] = array('num'=>$errnum.'08');
					continue;
				}

				$row2 = $dbres2->fetch_row();
				$dump .= "DROP TABLE IF EXISTS `{$row[0]}`;"."\n";
				$dump .= $row2[1].";"."\n\n";
			}

			if (
				$this->exclude_tbl
				&& is_array($this->exclude_tbl)
				&& in_array($row[0], $this->exclude_tbl)
			) {
				continue;
			}

			$q = "SELECT * FROM `{$row[0]}`";
			if ($minfo['state']['keys']) $q .= " ORDER BY ".$minfo['state']['keys'];
			$q .= " LIMIT ".($limit+10)." OFFSET ".$minfo['state']['offset'];
			
			$foomem1 = memory_get_peak_usage(true);

			$dbres2 = $this->db->query($q);
			if ( ! $dbres2) {
				$minfo['res']['errors'][] = array('num'=>$errnum.'09');
				break;
			}

			$foomem2 = memory_get_peak_usage(true);
			$foomem_otn1 = $this->conf('maxmemory') / ($foomem2 > $foomem1 ? ($foomem2-$foomem1) : 1);
			$foomem_otn2 = $this->conf('maxmemory') / $foomem2;

			if ($foomem_otn1 < 1) {
				$limit = $limit * ($foomem_otn1/2);
			} elseif ($foomem_otn1 < 2) {
				$limit = $limit * 0.5;
			} elseif ($foomem_otn1 > 10 && $foomem_otn2 > 10) {
				$limit = $limit * 1.5;
			}
			$limit = intval($limit);
			if ( ! $limit || $limit > $maxitems) {
				$limit = $maxitems;
			}
			$minfo['state']['limit'] = $limit;

			$ii = 0;
			while ($row2 = $dbres2->fetch_assoc()) {
				$ii++;
				$this->max['cntr'][0]['cnt']++;
				$minfo['state']['itms']++;

				$dump .= "INSERT INTO `{$row[0]}` SET ";

				$first = true;
				foreach ($row2 AS $key => $val) {
					$val = $this->db->real_escape_string($val);
					$dump .= ($first ? "" : ",")."`{$key}`='{$val}'";
					$first = false;
				}
				$dump .= ";"."\n";

				if ($this->max()) {
					$flag_max = true;
					break;
				}
			}

			$dump .= "\n";

			if ( ! $this->max['flag']) {
				$minfo['state']['offset'] = 0;
				$minfo['state']['keys'] = '';
			}

			if ($this->max()) {
				$flag_max = true;
			}
			if ($flag_max) break;
		}
		$dump .= "# -- the end / ".date('d.m.Y, H:i:s')."\n\n";

		$minfo['state']['tbl'] = $ii_tbl;
		$minfo['state']['offset'] += $ii;

		$procfile = '_process';
		$fh = fopen($folder.$dumpfilepart.$procfile,($minfo_new?'wb':'ab'));
		if ( ! $fh) {
			$minfo['res']['errors'][] = array('num'=>$errnum.'10');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}
		$fwres = fwrite($fh,$dump);
		fclose($fh);
		if ( ! $fwres) {
			$minfo['res']['errors'][] = array('num'=>$errnum.'11');
			$this->actfile['methods'][$method] = $minfo;
			return $minfo;
		}

		$maxpartsize = intval($this->conf('db_dump_maxpartsize'));
		$partsize = filesize($folder.$dumpfilepart.$procfile);
		if (
			! $this->max['flag']
			|| $partsize > $maxpartsize
		) {
			$part++;

			$foores = rename(
				$folder.$dumpfilepart.$procfile,
				$folder.$dumpfilepart
			);
			if ( ! $foores) {
				$minfo['res']['errors'][] = array('num'=>$errnum.'12');
				$this->actfile['methods'][$method] = $minfo;
				return $minfo;
			}
		}

		if ($this->max['flag']) {
		} else {
			$minfo['completed'] = 'y';

			$this->globinfo['methods'][$method]['itms'] = $minfo['state']['itms'];
		}

		$minfo['state']['part'] = $part;
		$minfo['prgrsbr']['curr'] = $minfo['state']['itms'];

		$minfo['res']['ok'] = 'y';
		$minfo['state']['failact_cnt'] = 0;
		$this->actfile['methods'][$method] = $minfo;
		return $minfo;
	}

	function fls_etalon()
	{
		$method = 'fls_etalon';
		$res = array(
			'method' => $method,
			'ok' => 'n',
			'mthd_nm' => 'Эталон файлов',
		);

		if ( ! $this->actfile) {
			$this->reqres['errors'][] = array('num'=>'0802');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$state = $this->actfile['states'][$method];
		if ( ! $state) {
			$state_new = true;
			$state = array(
				'step' => array(
					'frst' => true,
				),
			);
		}

		$fls_dir = $this->conf('etalon_dir').$this->conf('etalon_fls_dir');
		$lst_dir = $this->conf('etalon_dir').$this->conf('etalon_lst_dir');
		$lst_folder = $this->droot.$this->mdir.$lst_dir;
		if ( ! file_exists($lst_folder)) mkdir($lst_folder,0755,true);

		$lst_file = '/etalon_list';
		$allfile  = '__last';
		$procfile = '_'.$this->uniq.'_process';

		$fszhs = array(
			'hash',
			'fctm',
			'size',
			'file',
		);

		if (file_exists($lst_folder.$lst_file.'_'.$this->uniq)) {
			$this->reqres['errors'][] = array('num'=>'0909');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$lst_file_all_fh = fopen($lst_folder.$lst_file.$procfile,
			($state['step']['frst'] ? 'wb' : 'ab')
		);
		if ($lst_file_all_fh) {
			if ($state['step']['frst']) {
				$fres = fputcsv($lst_file_all_fh,$fszhs);
			}
		} else {
			$this->reqres['errors'][] = array('num'=>'0905');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		if ($state['step']['frst']) {
			$state['step']['frst'] = false;
			$state['step']['d_queue'] = array('/');
			$state['step']['f_queue'] = array();
		}

		$this->cms();

		$this->max['cntr'][0] = array(
			'nm'  => 'maxitems',
			'max' => $this->conf('maxitems'),
			'cnt' => 0,
		);

		$ii = 0;
		while (true) {
			while (true) {
				$file = array_shift($state['step']['f_queue']);
				if ( ! $file) break;

				$ii++;
				if ($ii % 2000 == 0) sleep(2);
				$this->max['cntr'][0]['cnt']++;

				if ($this->max()) {
					$flag_max = true;
					break;
				}

				if (
					strpos($file,$this->mdir.$fls_dir.'/') === 0
					&& substr($file,-2) == '_0'
				) {
					unlink($this->droot.$file);
					continue;
				}

				$ext = substr($file,strrpos($file,'.'));
				$ext = strtolower($ext);
				$is_etalon_ext = strpos($this->conf('etalon_ext'),'/'.$ext.'/') !== false
					? true : false;

				$size = filesize($this->droot.$file);
				$hash = $size > $this->conf('maxmemory')
					? '' : md5_file($this->droot.$file);
				$fctm = $this->filetime($this->droot.$file);

				if ($size > $this->conf('max_etalon_txt_file')) {
					$is_etalon_ext = false;
				}

				if ($lst_file_all_fh) {
					$fszhs = array(
						$hash,
						$fctm,
						$size,
						$file,
					);
					$fres = fputcsv($lst_file_all_fh,$fszhs);
				}

				if ($is_etalon_ext) {
					if ($this->zip) {
						$this->zip->addFile(
							$this->droot.$file,
							'www.'.$this->domain.$fls_dir.$file
						);
					} else {
						$this->tarAddFile(
							$this->droot.$file,
							'www.'.$this->domain.$fls_dir.$file
						);
					}
				}
			}
			if ($flag_max) break;

			$state['step']['f_queue'] = array();
			$nextdir = array_shift($state['step']['d_queue']);
			if ( ! $nextdir) break;
			if ( ! ($open = opendir($this->droot.$nextdir))) {
				$this->reqres['errors'][] = array('num'=>'0902');
				continue;
			}
			while ($file = readdir($open)) {
				if (
					filetype($this->droot.$nextdir.$file) == 'link'
					|| $file == '.' || $file == '..'
					|| $file == '.th'
				) {
					continue;
				}
				if ($this->cache_dirs && is_array($this->cache_dirs)) {
					foreach ($this->cache_dirs AS $cdir) {
						if (strpos($nextdir.$file.'/',$cdir) === 0) continue 2;
					}
				}
				if (is_dir($this->droot.$nextdir.$file)) {
					$state['step']['d_queue'][] = $nextdir.$file.'/';
					continue;
				}
				if ( ! is_file($this->droot.$nextdir.$file)) {
					continue;
				}
				$state['step']['f_queue'][] = $nextdir.$file;
			}
		}

		if ($this->max['flag']) {
			$res['max'] = true;

		} else {
			copy(
				$lst_folder.$lst_file.$procfile,
				$lst_folder.$lst_file.'_'.$this->uniq
			);
			rename(
				$lst_folder.$lst_file.$procfile,
				$lst_folder.$lst_file.$allfile
			);

			if ($this->zip) {
				$this->zip->addFile(
					$lst_folder.$lst_file.$allfile,
					'www.'.$this->domain.$lst_dir.$lst_file.$allfile
				);
			} else {
				$this->tarAddFile(
					$lst_folder.$lst_file.$allfile,
					'www.'.$this->domain.$lst_dir.$lst_file.$allfile
				);
			}

			$res['completed'] = 'y';
		}

		if ($lst_file_all_fh) fclose($lst_file_all_fh);

		if ($this->zip) {
			$this->zip->close();
		} else {
			$this->tarEmptyRow();
			$this->tarClose();
		}

		$res['ok'] = 'y';
		$this->actfile['states'][$method] = $state;
		$this->reqres['mres'][] = $res;
		return $res;
	}

	function db_etalon()
	{
		$method = 'db_etalon';
		$res = array(
			'method' => $method,
			'ok' => 'n',
			'mthd_nm' => 'Эталон базы данных',
		);

		if ( ! $this->actfile) {
			$this->reqres['errors'][] = array('num'=>'0501');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$maxitems = intval($this->conf('maxitems','db'));

		$state = $this->actfile['states'][$method];
		if ( ! $state) {
			$state_new = true;
			$state = array(
				'tbl'    => false,
				'limit'  => $maxitems,
				'offset' => 0,
				'keys'   => '',
				'cnt'    => 0,
			);
		}
		$state['cnt']++;

		$dir = $this->conf('etalon_dir').$this->conf('etalon_db_dir');
		$folder = $this->droot.$this->mdir.$dir;
		if ( ! file_exists($folder)) mkdir($folder,0755,true);
		$res['dir'] = $dir;

		$db_file = '/etalon_db';
		$allfile  = '__last';
		$procfile = '_'.$this->uniq.'_process';

		$fszhs = array(
			'rtp',
			'hsh',
			'tbl',
			'kys',
			'cls',
		);

		if (file_exists($folder.$db_file.'_'.$this->uniq)) {
			$this->reqres['errors'][] = array('num'=>'0502');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$db_file_all_fh = fopen($folder.$db_file.$procfile,
			($state['cnt'] === 1 ? 'wb' : 'ab')
		);
		if ($db_file_all_fh) {
			if ($state['cnt'] === 1) {
				$fres = fputcsv($db_file_all_fh,$fszhs);
			}
		} else {
			$this->reqres['errors'][] = array('num'=>'0503');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$fres = $this->cms();
		if ( ! $fres) {
			$this->reqres['errors'][] = array('num'=>'0504');
			$this->reqres['mres'][] = $res;
			return $res;
		}
		$fres = $this->db_access();
		if ( ! $fres) {
			$this->reqres['errors'][] = array('num'=>'0505');
			$this->reqres['mres'][] = $res;
			return $res;
		}
		$dbcres = $this->db_connect();
		if ($dbcres['ok'] != 'y') {
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$dbres = $this->db->query("SHOW TABLES");
		if ( ! $dbres) {
			$this->reqres['errors'][] = array('num'=>'0506');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$limit = intval($state['limit']);
		$this->max['cntr'][0] = array(
			'nm'  => 'maxitems',
			'max' => $limit,
			'cnt' => 0,
		);

		while ($row = $dbres->fetch_row()) {

			if (
				$state['tbl']
				&& $row[0] != $state['tbl']
			) continue;
			$ii_tbl = $row[0];
			$state['tbl'] = false;

			if ( ! $state['offset']) {

				$dbres2 = $this->db->query("SELECT *
					FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
					WHERE TABLE_NAME=N'{$row[0]}'
					ORDER BY IF(CONSTRAINT_NAME='PRIMARY',0,1),CONSTRAINT_NAME,ORDINAL_POSITION");
				if ( ! $dbres2) {
					$this->reqres['errors'][] = array('num'=>'0507');
					continue;
				}
				$keys = "";
				$keys_nm = false;
				$keys_ar = array();
				while ($row2 = $dbres2->fetch_assoc()) {
					if (
						$keys_nm
						&& $keys_nm !== $row2['CONSTRAINT_NAME']
					) break;
					$keys_nm = $row2['CONSTRAINT_NAME'];
					$keys .= ($keys?",":"")."`".$row2['COLUMN_NAME']."`";
					$keys_ar[$row2['COLUMN_NAME']] = $row2['COLUMN_NAME'];
				}
				$state['keys'] = $keys;
				$state['keys_ar'] = $keys_ar;

				$dbres2 = $this->db->query("SHOW CREATE TABLE `{$row[0]}`");
				if ( ! $dbres2) {
					$this->reqres['errors'][] = array('num'=>'0508');
					continue;
				}
				$row2 = $dbres2->fetch_row();
				$tblhash = md5($row2[1]);

				$dbres2 = $this->db->query("SELECT * FROM `{$row[0]}` LIMIT 1");
				if ( ! $dbres2) {
					$this->reqres['errors'][] = array('num'=>'0509');
					continue;
				}
				$row2 = $dbres2->fetch_assoc();
				$cols = "";
				foreach ($row2 AS $col => $val) {
					$cols .= ($cols?",":"")."`{$col}`";
				}

				if ($db_file_all_fh) {
					$fszhs = array(
						'tbl',
						$tblhash,
						$row[0],
						$keys,
						$cols,
					);
					$fres = fputcsv($db_file_all_fh,$fszhs);
				}
			}

			if (
				$this->exclude_tbl
				&& is_array($this->exclude_tbl)
				&& in_array($row[0], $this->exclude_tbl)
			) {
				continue;
			}

			$q = "SELECT * FROM `{$row[0]}`";
			if ($state['keys']) $q .= " ORDER BY ".$state['keys'];
			$q .= " LIMIT ".($limit+10)." OFFSET ".$state['offset'];

			$foomem1 = memory_get_peak_usage(true);

			$dbres2 = $this->db->query($q);
			if ( ! $dbres2) {
				$this->reqres['errors'][] = array('num'=>'0510');
				break;
			}

			$foomem2 = memory_get_peak_usage(true);
			$foomem_otn1 = $this->conf('maxmemory') / ($foomem2 > $foomem1 ? ($foomem2-$foomem1) : 1);
			$foomem_otn2 = $this->conf('maxmemory') / $foomem2;

			if ($foomem_otn1 < 1) {
				$limit = $limit * ($foomem_otn1/2);
			} elseif ($foomem_otn1 < 2) {
				$limit = $limit * 0.5;
			} elseif ($foomem_otn1 > 10 && $foomem_otn2 > 10) {
				$limit = $limit * 1.5;
			}
			$limit = intval($limit);
			if ( ! $limit || $limit > $maxitems) {
				$limit = $maxitems;
			}
			$state['limit'] = $limit;

			$ii = 0;
			while ($row2 = $dbres2->fetch_assoc()) {
				$ii++;
				$this->max['cntr'][0]['cnt']++;

				$dbrow = "";
				$dbrow_keys = "";
				foreach ($row2 AS $key => $val) {
					$val = $this->db->real_escape_string($val);
					$dbrow .= ($dbrow?",":"")."`{$key}`='{$val}'";
					
					if ($state['keys_ar'][$key]) {
						$dbrow_keys .= ($dbrow_keys?",":"")."`{$key}`='{$val}'";
					}
				}
				$dbrow_hash = md5($dbrow);

				if ($db_file_all_fh) {
					$fszhs = array(
						'row',
						$dbrow_hash,
						$row[0],
						$dbrow_keys,
						'',
					);
					$fres = fputcsv($db_file_all_fh,$fszhs);
				}

				if ($this->max()) {
					$flag_max = true;
					break;
				}
			}

			if ( ! $this->max['flag']) {
				$state['offset'] = 0;
				$state['keys'] = '';
			}

			if ($this->max()) {
				$flag_max = true;
			}
			if ($flag_max) break;
		}

		$state['tbl'] = $ii_tbl;
		$state['offset'] += $ii;

		if ($this->max['flag']) {
			$res['max'] = true;

		} else {
			copy(
				$folder.$db_file.$procfile,
				$folder.$db_file.'_'.$this->uniq
			);
			rename(
				$folder.$db_file.$procfile,
				$folder.$db_file.$allfile
			);

			if ($this->zip) {
				$this->zip->addFile(
					$folder.$db_file.$allfile,
					'www.'.$this->domain.$dir.$db_file.$allfile
				);
			} else {
				$this->tarAddFile(
					$folder.$db_file.$allfile,
					'www.'.$this->domain.$dir.$db_file.$allfile
				);
			}

			$res['completed'] = 'y';
		}

		$res['ok'] = 'y';
		$this->actfile['states'][$method] = $state;
		$this->reqres['mres'][] = $res;
		return $res;
	}

	function act_etalon()
	{
		$method = 'act_etalon';
		$res = array(
			'method' => $method,
			'ok' => 'n',
		);

		if ( ! $this->actfile) {
			$this->reqres['errors'][] = array('num'=>'0802');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$state = $this->actfile['states'][$method];
		if ( ! $state) {
			$state_new = true;
			$state = array(
				'step' => array(
					'num' => 1,
				),
			);
		}
		$this->actfile['states'][$method] = $state;

		$dir = $this->conf('etalon_dir');
		$folder = $this->droot.$this->mdir.$dir;
		if ( ! file_exists($folder)) mkdir($folder,0755,true);
		$res['dir'] = $dir;

		$procfile = '_process';
		$archfile = '/'.$this->domain.'_etalon_'.$this->uniq;

		if ($this->zip) {
			$archfile .= '.zip';
			if (file_exists($folder.$archfile)) {
				$this->reqres['errors'][] = array('num'=>'1008');
				$this->reqres['mres'][] = $res;
				return $res;
			}
			$ziptp = $state['step']['num'] === 1
				? ZipArchive::CREATE | ZipArchive::OVERWRITE
				: ZipArchive::CREATE;
			$this->zip->open($folder.$archfile.$procfile,$ziptp);
			$this->zipfile = $folder.$archfile.$procfile;

		} else {
			$archfile .= '.tar';
			if ($this->targzisavailable) $archfile .= '.gz';
			if (file_exists($folder.$archfile)) {
				$this->reqres['errors'][] = array('num'=>'1009');
				$this->reqres['mres'][] = $res;
				return $res;
			}
			$tartp = $state['step']['num'] === 1 ? 'w' : 'a';
			$tarres = $this->tarOpen($folder.$archfile.$procfile,$tartp);
			if ( ! $tarres) $this->tar = false;
		}

		if ( ! $this->zip && ! $this->tar) {
			$this->reqres['errors'][] = array('num'=>'1010');
			$this->reqres['mres'][] = $res;
			return $res;
		}

		$res['archfile'] = $archfile;

		if ($state['step']['num'] === 1) {
			$step_flag = true;

			$subres = $this->db_etalon();

			if (
				$subres['completed'] == 'y'
				|| $subres['ok'] != 'y'
			) $nextstep = true;
		}

		if ($state['step']['num'] === 2) {
			$step_flag = true;

			$subres = $this->fls_etalon();

			if (
				$subres['completed'] == 'y'
				|| $subres['ok'] != 'y'
			) $nextstep = true;
		}

		if ($step_flag) {
			if ($nextstep) {
				$res['nextstep'] = true;
				$state = array(
					'step' => array(
						'num' => $state['step']['num']+1,
					),
				);
			}
		} else {
			$foores = rename(
				$folder.$archfile.$procfile,
				$folder.$archfile
			);
			if ( ! $foores) {
				$this->reqres['errors'][] = array('num'=>'1004');
				$this->reqres['mres'][] = $res;
				return $res;
			}

			$res['completed'] = 'y';
		}

		$res['ok'] = 'y';
		$this->actfile['states'][$method] = $state;
		$this->reqres['mres'][] = $res;
		return $res;
	}

	function fls_remove($path) {
		$res = array(
			'method' => 'fls_remove',
			'ok'     => 'n',
		);

		if (substr($path,0,1) != '/') {
			$this->reqres['errors'][] = array('num'=>'1101');
			return $res;
		}
		if (
			! is_file($this->droot.$path)
			|| ! file_exists($this->droot.$path)
		) {
			$this->reqres['errors'][] = array('num'=>'1102');
			return $res;
		}

		$rmres = unlink($this->droot.$path);
		if ( ! $rmres) {
			$this->reqres['errors'][] = array('num'=>'1103');
			return $res;
		}

		$res['ok'] = 'y';
		return $res;
	}

	function fls_explorer($path) {
		if (substr($path,0,1) != '/') {
			$this->reqres['errors'][] = array('num'=>'1201');
			return $res;
		}

		if (is_file($this->droot.$path)) {
			$ext = substr($path,strrpos($path,'.'));
			$ext = strtolower($ext);
			$is_etalon_ext = strpos($this->conf('etalon_ext'),'/'.$ext.'/') !== false
				? true : false;
			if ( ! $is_etalon_ext) return 'not-etalon-ext';
			$res = highlight_file($this->droot.$path,true);
			return $res;
		}

		if ( ! ($open = opendir($this->droot.$path))) {
			return;
		}
		while ($file = readdir($open)) {
			if (
				filetype($this->droot.$path.$file) == 'link'
				|| $file == '.' || $file == '..'
			) {
				continue;
			}

			$isfile = is_file($this->droot.$path.$file) ? true : false;

			$lnk = '<div><a href="'.$this->mfile.'?w='.$_GET['w'].'&a=fls_explorer&path='.urlencode($path.$file.($isfile?'':'/')).'">'.$path.$file.($isfile?'':'/').'</a></div>';
			if ($isfile) {
				$fls .= $lnk;

				// $fs = filesize($this->droot.$path.$file);
				// $fls .= '<div>'.$fs.'</div>';

			} else {
				$dirs .= $lnk;
			}
		}

		$dirs .= '<div>---</div>';

		return $dirs.$fls;
	}

	function fls_structure($path='/')
	{
		$method = 'fls_structure';
		$res = array(
			'method' => $method,
			'ok' => 'n',
		);

		if ( ! $path) $path = '/';
		if (substr($path,-1) != '/') $path .= '/';
		if (substr($path,0,1) != '/') $path = '/'.$path;

		$uniq = $this->uniq;

		$dir = $this->conf('etalon_dir');
		$folder = $this->droot.$this->mdir.$dir;
		if ( ! file_exists($folder)) mkdir($folder,0755,true);

		$statefile = $this->conf('etalon_dir').'/state_fls_strctr_'.$uniq;
		$state = $this->proccess_state($statefile,false,true);
		if ($state === false) {
			$this->reqres['errors'][] = array('num'=>'1301');
			return $res;
		} elseif ( ! $state || ! is_array($state)) {
			$state_new = true;
			$state = array(
				'files'     => 0,
				'd_queue'   => array($path),
				'f_queue'   => array(),
				'structure' => array(),
			);
		}

		$res['state'] = $state;
		$res['uniq']  = $uniq;
		$res['dir']   = $dir;

		$this->max['cntr'][0] = array(
			'nm'  => 'maxitems',
			'max' => $this->conf('maxitems'),
			'cnt' => 0,
		);

		$ii = 0;
		while (true) {
			while (true) {
				$file = array_shift($state['f_queue']);
				if ( ! $file) break;

				$ii++;
				$this->max['cntr'][0]['cnt']++;
				$state['files']++;

				$fs = filesize($this->droot.$file);

				$pdir = $file;
				$dpth = 0;
				do {
					$dpth++;
					$pdir = dirname($pdir);
					if ( ! isset($state['structure'][$pdir])) {
						$state['structure'][$pdir] = array(
							'fss' => 0,
							'cnt' => 0,
						);
					}
					$state['structure'][$pdir]['fss'] += $fs;
					$state['structure'][$pdir]['cnt'] ++;
				} while (
					$dpth < 99
					&& $pdir != '.' 
					&& $pdir != '/'
				);
				
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
				$this->reqres['errors'][] = array('num'=>'1302');
				continue;
			}
			while ($file = readdir($open)) {
				if (
					filetype($this->droot.$nextdir.$file) == 'link'
					|| $file == '.' || $file == '..'
				) {
					continue;
				}
				if (is_dir($this->droot.$nextdir.$file)) {
					$state['d_queue'][] = $nextdir.$file.'/';
					continue;
				}
				if ( ! is_file($this->droot.$nextdir.$file)) {
					continue;
				}
				$state['f_queue'][] = $nextdir.$file;
			}
		}

		if ($this->max['flag']) {
			$res['max'] = true;
			$foores = $this->proccess_state($statefile,$state,true);
			if ( ! $foores) {
				$this->reqres['errors'][] = array('num'=>'1303');
				return $res;
			}

		} else {
			$res['completed'] = 'y';
			$this->proccess_state($statefile,'rem');
		}

		$res['state'] = $state;
		$res['ok'] = 'y';
		return $res;
	}

	// -------------------------------------------------

	function page_main()
	{
		$res .= '<h1>Главная</h1>';

		$this->reqres['pntres'] .= $res;
		return true;
	}

	function page_etalon()
	{
		$res .= '<h1>Эталоны</h1>';

		if ( ! $this->uniq) {
			srand(time());
			$uniq_new = 'etln_'.date('Y-m-d_H-i-s').'_'.substr(md5($this->usrip.'-'.time().'-'.rand(0,9)),-6);
			$res .= '<div>
				<a href="'.$this->mfile.'?w='.$_GET['w'].'&a='.$_GET['a'].'&uniq='.$uniq_new.'">Новый эталон</a>
			</div>';
			$this->reqres['pntres'] .= $res;
			return true;
		}
		
		$res .= '<div style="color:#666;margin-bottom:50px;">'.$this->uniq.'</div>';

		$res .= '<div class="actform_res"></div>';
		$res .= '<div class="actform_log"></div>';

		$res .= '<div>Инфа об эталоне</div>';

		if ( ! $this->actfile) {
			$actfile = array(
				'uniq' => $this->uniq,
				'dt' => time(),
			);
			// $this->bufile('acts', 'set', $this->uniq, $actfile);
			$this->actfile = $actfile;
		}

		if ($actfile['completed']) {
			$res .= '<div>Завершено!</div>';
		} else {
			$res .= '<form class="actform" action="'.$this->mfile.'?w='.$_GET['w'].'&a='.$_GET['a'].'&uniq='.$this->uniq.'&do" method="get">
				<button class="sbmt" type="button">Запустить</button>
			</form>';
		}
		// $res .= '<code><pre>'.print_r($this->actfile,1).'</pre></code>';
		
		$this->reqres['pntres'] .= $res;
		return true;
	}

	function page_backup()
	{
		$res .= '<h1>Резервные копии</h1>';

		if ( ! $this->uniq) {
			srand(time());
			$uniq_new = 'bckp_'.date('Y-m-d_H-i-s').'_'.substr(md5($this->usrip.'-'.time().'-'.rand(0,9)),-6);
			$res .= '<div>
				<a href="'.$this->mfile.'?w='.$_GET['w'].'&a='.$_GET['a'].'&uniq='.$uniq_new.'">Новая резервная копия</a>
			</div>';
			$this->reqres['pntres'] .= $res;
			return true;
		}
		
		$res .= '<div style="color:#666;margin-bottom:50px;">'.$this->uniq.'</div>';

		$res .= '<div class="actform_res actform_rows"></div>';
		$res .= '<div class="actform_log actform_rows"></div>';

		$res .= '<div>Инфа о копии</div>';

		if ($this->actfile['completed']) {
			$res .= '<div>Завершено!</div>';
		} else {
			$res .= '<form class="actform" action="'.$this->mfile.'?w='.$_GET['w'].'&a='.$_GET['a'].'&uniq='.$this->uniq.'&do" method="get">
				<button class="sbmt" type="button">Запустить</button>
			</form>';
		}
		// $res .= '<code><pre>'.print_r($this->actfile,1).'</pre></code>';
		
		$this->reqres['pntres'] .= $res;
		return true;
	}

	function ob_end($data)
	{
		$errnum = '99';

		if ($this->conf('debug')) return false;

		$rmvmthdsprms = array(
			'act_backup' => array(
			),
			'fls_archive' => array(
				'd_queue',
				'f_queue',
			),
			'db_dump' => array(
			),
		);

		$actf = $this->actfile;
		if ( ! $actf || ! is_array($actf)) $actf = array();

		$actf['res'] = array(
			'tm' => time(),
			'ok' => 'y',
			'errors' => array(),
			'notice' => array(),
		);
		$actf['completed'] = 'y';
		if (is_array($actf['methods'])) {
			foreach ($actf['methods'] AS $minfo) {
				if ($minfo['res']['ok'] != 'y') {
					$actf['res']['ok'] = 'n';
				}
				if ($minfo['completed'] != 'y') {
					$actf['completed'] = 'n';
				}
			}
		}

		$res = $this->bufile('acts', 'set', $this->uniq, $actf);
		if ( ! $res) {
			$actf['res']['errors'][] = array('num'=>$errnum.'01');
			$actf['res']['ok'] = 'n';
			$actf['completed'] = 'n';
		}

		$reqres = '';
		if ($this->reqres['pntres']) {
			header('Content-Type: text/html; charset=utf-8');

			if ($this->res_wthtpl) {
				$reqres .= $this->template('top');
			}

			$reqres .= $this->reqres['pntres'];

			if ($this->res_wthtpl) {
				$reqres .= $this->template('bot');
			}

		} else {
			header('Content-Type: application/json; charset=utf-8');
			if (is_array($rmvmthdsprms)) {
				foreach ($rmvmthdsprms AS $mthd => $prms) {
					if ( ! is_array($prms)) continue;
					foreach ($prms AS $prm) {
						if ( ! isset($actf['state'][$prm])) continue;
						unset($actf['state'][$prm]);
					}
				}
			}
			$reqres = json_encode($actf);
		}

		$this->bufile('globinfo', 'set', false, $this->globinfo);

		return $reqres;
	}

	function template($side=false)
	{
		$baselink = $this->mfile.'?w='.$_GET['w'];

		if ('top' == $side) {
			$p .= <<<TPL
<!doctype html>
<html>
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
	
	<script src="_buran_new.js"></script>
	<link href="_buran_new.css" rel="stylesheet" />
	
</head>
<body>
<div class="mainwrp">
	<div class="mcols">
		<div class="mcol mcol_lft1">
			<div class="lftmenu">
				<div class="lmitm"><a href="{$baselink}&a=main">Главная</a></div>
				<div class="lmitm"><a href="{$baselink}&a=backup">Резервные копии</a></div>
				<div class="lmitm"><a href="{$baselink}&a=etalon">Эталоны</a></div>
			</div><!--mcol_lft1-->
		</div><!--mcol_lft1-->

		<div class="mcol mcol_rgt">

TPL;
		}

		if ('bot' == $side) {
			$p .= <<<TPL

			</div><!--mcol_rgt-->
	</div><!--mcols-->
</div><!--mainwrp-->

<svg id="svgicons" style="width:0;height:0;overflow:hidden;display:none;"
version="1.1"
baseProfile="full"
xmlns="http://www.w3.org/2000/svg"
xmlns:xlink="http://www.w3.org/1999/xlink"
xmlns:ev="http://www.w3.org/2001/xml-events"
><defs>

	<symbol id="svgi-callnumber" viewBox="0 0 338.502 338.502">
	</symbol>

</defs>
</svg>
</body>
</html>
TPL;
		}

		return $p;
	}

	function info()
	{
		$res = array(
			'method' => 'info',
			'ok' => 'n',
		);

		$this->cms();
		$this->db_access();

		$log_dir = $this->broot.$this->conf('log_dir').'/sendmail/';
		$logs = glob($log_dir.'*');
		if ($logs && is_array($logs)) {
			foreach ($logs AS $file) {
				$log = basename($file);
				$sendmail_log[$log] = false;
				$fh = fopen($file,'rb');
				if ( ! $fh) continue;
				$mailscnt = 0;
				$last_er = 0;
				while (($row = fgetcsv($fh,0,';')) !== false) {
					if (
						$row[1] == '+'
						&& $row[0] > time()-(60*60*24*7)
					) $mailscnt++;
					if ($row[1] == '-') $last_er = $row[0];
				}
				$mailscnt_sred = round($mailscnt/7,2);
				$sendmail_log[$log] = array(
					'mailscnt' => $mailscnt_sred,
					'last_er' => $last_er,
				);
			}
		}

		$pi = $this->getphpinfo(INFO_CONFIGURATION);
		preg_match_all("/\<td.*\>open_basedir\<\/td\>(\<td.*\>(.*)\<\/td\>)(\<td.*\>(.*)\<\/td\>)/U",$pi,$mtchs);

		$info = array(
			'module_ver'     => $this->version,
			'modulefile'     => __FILE__,
			'droot'          => $this->droot,
			'php_ver'        => PHP_VERSION,
			'php_uname'      => php_uname(),
			'php_sapi'       => php_sapi_name(),
			'sendmail_log'   => $sendmail_log,
			'ws'             => $this->http.$this->www.$this->domain,
			'curl'           => $this->curl_ext,
			'sock'           => $this->sock_ext,
			'fgc'            => $this->fgc_ext,
			'iswritable'     => $this->iswritable,
			'isreadable'     => $this->isreadable,
			'tarisavailable' => $this->tarisavailable,
			'zipisavailable' => $this->zipisavailable,
			'cms'            => array(
				'cms'      => $this->cms,
				'cms_ver'  => $this->cms_ver,
				'cms_date' => $this->cms_date,
				'cms_name' => $this->cms_name,
			),
			'openbasedir' => array(
				$mtchs[2][0],
				$mtchs[4][0],
			),
			'db_access'  => array(
				'host' => $this->db_host,
				'user' => $this->db_user,
				'pswd' => $this->db_pwd,
				'dbnm' => $this->db_name,
			),
		);

		$res['info'] = $info;
		$res['ok'] = 'y';
		return $res;
	}

	function getphpinfo($prms=-1)
	{
		ob_start();
		phpinfo($prms);
		$p = ob_get_contents();
		ob_end_clean();
		return $p;
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

		ob_start();
		@include_once($this->droot.'/core/docs/version.inc.php');
		ob_end_clean();
		if (isset($v) && $v['code_name']) {
			$this->cms      = 'revolution';
			$this->cms_ver  = $v['full_version'];
			$this->cms_date = '';
			$this->cms_name = $v['full_appname'];
			$this->cache_dirs = array(
				'/assets/cache/',
				'/core/cache/',
			);
			return true;
		}

		ob_start();
		@include_once($this->droot.'/configuration.php');
		ob_end_clean();
		if (class_exists('JConfig')) {
			$conf = new JConfig();
			if ($conf->host) {
				$this->cms      = 'joomla';
				$this->cms_ver  = '';
				$this->cms_date = '';
				$this->cms_name = '';
				return true;
			}
		}

		ob_start();
		@include_once($this->droot.'/config.php');
		ob_end_clean();
		if (defined('DB_DRIVER') && defined('DB_HOSTNAME') &&
			defined('DB_USERNAME') && defined('DB_PASSWORD') &&
			defined('DB_DATABASE')) {
			$this->cms      = 'opencart2';
			$this->cms_ver  = '';
			$this->cms_date = '';
			$this->cms_name = '';
			$this->cache_dirs = array(
				'/system/storage/cache/',
			);
			return true;
		}

		ob_start();
		@include_once($this->droot.'/bootstrap.php');
		ob_end_clean();
		if (defined('HOSTCMS')) {
			$this->cms      = 'hostcms';
			$this->cms_ver  = '';
			$this->cms_date = '';
			$this->cms_name = '';
			return true;
		}

		ob_start();
		@include_once($this->droot.'/wp-config.php');
		ob_end_clean();
		if (defined('DB_NAME') && defined('DB_USER') &&
			defined('DB_PASSWORD') && defined('DB_HOST')) {
			$this->cms      = 'wordpress';
			$this->cms_ver  = $wp_version;
			$this->cms_date = '';
			$this->cms_name = '';
			$this->cache_dirs = array(
				'/wp-content/cache/',
			);
			return true;
		}

		ob_start();
		@include($this->droot.'/sites/default/settings.php');
		ob_end_clean();
		if ($drupal_hash_salt && is_array($databases['default']['default'])) {
			$this->cms      = 'drupal';
			$this->cms_ver  = '';
			$this->cms_date = '';
			$this->cms_name = '';
			return true;
		} elseif (isset($db_url) && $db_url) {
			$this->cms      = 'drupal_old';
			$this->cms_ver  = '';
			$this->cms_date = '';
			$this->cms_name = '';
			return true;
		}

		ob_start();
		@include_once($this->droot.'/bitrix/modules/main/classes/general/version.php');
		ob_end_clean();
		if (defined('SM_VERSION')) {
			$this->cms      = 'bitrix';
			$this->cms_ver  = SM_VERSION;
			$this->cms_date = SM_VERSION_DATE;
			$this->cms_name = '';
			$this->cache_dirs = array(
				'/bitrix/cache/',
				'/bitrix/managed_cache/',
				'/bitrix/stack_cache/',
				'/bitrix/html_pages/',
			);
			$this->exclude_tbl = array(
				'b_xml_tree_import_1c',
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

		if ($this->cms == 'revolution') {
			ob_start();
			@include_once($this->droot.'/core/config/config.inc.php');
			ob_end_clean();
			$this->db_host    = $database_server;
			$this->db_user    = $database_user;
			$this->db_pwd     = $database_password;
			$this->db_name    = $dbase;
			$this->db_method  = 'SET NAMES';
			$this->db_charset = $database_connection_charset;
			return true;
		}
		
		if ($this->cms == 'joomla') {
			ob_start();
			@include_once($this->droot.'/configuration.php');
			ob_end_clean();
			$conf = new JConfig();
			$this->db_host = $conf->host;
			$this->db_user = $conf->user;
			$this->db_pwd  = $conf->password;
			$this->db_name = $conf->db;
			return true;
		}

		if ($this->cms == 'opencart2') {
			ob_start();
			@include_once($this->droot.'/config.php');
			ob_end_clean();
			$this->db_host = DB_HOSTNAME;
			$this->db_user = DB_USERNAME;
			$this->db_pwd  = DB_PASSWORD;
			$this->db_name = DB_DATABASE;
			return true;
		}

		if ($this->cms == 'hostcms') {
			ob_start();
			$ret = require($this->droot.'/modules/core/config/database.php');
			ob_end_clean();
			$this->db_host = $ret['default']['host'];
			$this->db_user = $ret['default']['username'];
			$this->db_pwd  = $ret['default']['password'];
			$this->db_name = $ret['default']['database'];
			return true;
		}

		if ($this->cms == 'wordpress') {
			ob_start();
			@include_once($this->droot.'/wp-config.php');
			ob_end_clean();
			$this->db_host = DB_HOST;
			$this->db_user = DB_USER;
			$this->db_pwd  = DB_PASSWORD;
			$this->db_name = DB_NAME;
			return true;
		}

		if ($this->cms == 'drupal') {
			ob_start();
			@include($this->droot.'/sites/default/settings.php');
			ob_end_clean();
			$this->db_host = $databases['default']['default']['host'];
			$this->db_user = $databases['default']['default']['username'];
			$this->db_pwd  = $databases['default']['default']['password'];
			$this->db_name = $databases['default']['default']['database'];
			return true;
		}

		if ($this->cms == 'drupal_old') {
			ob_start();
			@include($this->droot.'/sites/default/settings.php');
			ob_end_clean();
			$databases = parse_url($db_url);
			$this->db_host = $databases['host'];
			$this->db_user = $databases['user'];
			$this->db_pwd  = $databases['pass'];
			$this->db_name = substr($databases['path'],1);
			return true;
		}

		if ($this->cms == 'bitrix') {
			ob_start();
			@include_once($this->droot.'/bitrix/php_interface/dbconn.php');
			ob_end_clean();
			$this->db_host = $DBHost;
			$this->db_user = $DBLogin;
			$this->db_pwd  = $DBPassword;
			$this->db_name = $DBName;
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
			$this->reqres['errors'][] = array('num'=>'0701');
			return $res;
		}
		$dbres = $db->query("{$this->db_method} {$this->db_charset}");
		if ( ! $dbres) {
			$this->reqres['errors'][] = array('num'=>'0702');
			return $res;
		}
		$this->db = $db;
		$res['ok'] = 'y';
		return $res;
	}

	function bufile($type, $act='get', $prm='', $body=false, $base64=false)
	{
		$res = array(
			'method' => 'bufile',
		);
		$subfolder = '';
		$base64_e = $base64=='e' ? true : false;
		$base64_d = $base64=='d' ? true : false;
		$filepath = $act == 'file' ? true : false;
		$dirpath = $act == 'dir' ? true : false;
		$mkdir = false;
		$set = $act == 'set' ? true : false;
		$get = in_array($act,array('set','file','dir'))
			? false : true;
		$append = false;
		$exitline = false;

		$exitline_code = '<?php exit();/*'."\n";

		if ($get) $body = '';

		$folder = $this->droot.$this->mdir;

		switch ($type) {
			case 'module':
				$base64_d = true;
				$subfolder = '/../';
				$file = $this->mfile;
				break;

			case 'config':
				$exitline = true;
				$file = $type.'.php';
				break;
			case 'config_value':
				$serialize = true;
				$base64_e = true;
				$exitline = true;
				$file = 'config.php';
				break;

			case 'acts':
				$filedir = substr($prm,0,strpos($prm,'_'));
				$filedir = preg_replace("/[^a-z0-9]/",'',$filedir);
				if ($filedir) $filedir .= '/';
				$subfolder = '/acts/'.$filedir;
				$serialize = true;
				$base64_e = true;
				$exitline = true;
				$file = $prm.'.php';
				break;

			case 'globinfo':
				$serialize = true;
				$base64_e = true;
				$exitline = true;
				$file = $type.'.php';
				break;

			default:
				return false;
		}

		if ($subfolder) $folder .= $subfolder; else $folder .= '/';

		if (
			($dirpath || $filepath)
			&& $mkdir
			&& ! file_exists($folder)
		) mkdir($folder, 0755, true);

		if ($dirpath) return $folder;
		if ($filepath) return $folder.$file;

		if ($set) {
			if ($body === false) {
				if (file_exists($folder.$file)) {
					unlink($folder.$file);
					return true;
				}
			}

			if ( ! file_exists($folder)) {
				mkdir($folder,0755,true);
			}

			if ($serialize) $body = serialize($body);
			if ($base64_e) $body = base64_encode($body);
			if ($base64_d) $body = base64_decode($body);

			if ('module' == $type) {
				if (strpos($body, "<?php\n/**\n * BURAN") !== 0) {
					$this->reqres['errors'][] = array('num'=>'0601');
					return false;
				}
				$cres = copy($folder.$file,$folder.$file.'.back.php');
				if ( ! $cres) {
					$this->reqres['errors'][] = array('num'=>'0602');
					return false;
				}
			}

			if ('config' == $type) {
				$tmp = base64_decode($body);
				if ($body && $tmp) {
					$tmp = unserialize($tmp);
					if ( ! is_array($tmp)) {
						$this->reqres['errors'][] = array('num'=>'0603');
						return false;
					}
				} else {
					$this->reqres['errors'][] = array('num'=>'0604');
					return false;
				}
			}

			if ($append) {
				if ('test' == $type) $body = "\n---\n\n".$body;
			}

			if ($exitline) {
				$body = $exitline_code.$body;
			}

			$fh = fopen($folder.$file, ($append ? 'ab' : 'wb'));
			if ( ! $fh) {
				$this->reqres['errors'][] = array('num'=>'0605');
				return false;
			}
			$res = fwrite($fh, $body);
			if ($res === false) {
				$this->reqres['errors'][] = array('num'=>'0606');
				return false;
			}
			fclose($fh);

			$res = md5_file($folder.$file);
			return $res;
		}

		if ($get) {
			if ( ! file_exists($folder.$file)) return false;
			$fh = fopen($folder.$file,'rb');
			if ( ! $fh) return false;
			if ($exitline) {
				fseek($fh, strlen($exitline_code));
			}
			$body = '';
			while ( ! feof($fh)) $body .= fread($fh,1024*8);
			fclose($fh);

			if ($base64_e) $body = base64_decode($body);
			if ($serialize) $body = unserialize($body);

			return $body;
		}
	}

	function update($file='')
	{
		$res = array(
			'method' => 'update',
			'ok' => 'n',
		);
		$file = preg_replace("/[^a-z0-9\-_]/",'',$file);
		$file = '/buran/update/buran'.($file?'_'.$file:'');
		if ($this->curl_ext) {
			$options = array(
				CURLOPT_URL => $this->bunker_prcl.$this->bunker.$file,
				CURLOPT_RETURNTRANSFER => true,
			);
			$curl = curl_init();
			curl_setopt_array($curl,$options);
			$code = curl_exec($curl);
			$curl_errno = curl_errno($curl);
			curl_close($curl);
			if ($curl_errno) {
				$code = false;
				$this->reqres['errors'][] = array(
					'num' => '0501',
					'info' => $curl_errno,
				);
			}
		}
		if ( ! $code && $this->sock_ext) {
			$code = '';
			$headers = "GET ".$this->bunker_prcl.$this->bunker.$file." HTTP/1.0\n";
			$headers .= "Host: {$this->bunker}\n\n";
			$sockres = stream_socket_client($this->bunker.':80',$errno,$errstr,10);
			if ($sockres) {
				fwrite($sockres,$headers);
				while ( ! feof($sockres)) {
					$code .= fread($sockres,1024*1024); 
				}
				fclose($sockres);
				$code = $this->parse_response_headers($code);
				$code = $code[1];
			}
		}
		if ( ! $code && $this->fgc_ext) {
			$code = file_get_contents($this->bunker_prcl.$this->bunker.$file);
		}
		if ($code) {
			$fres = $this->bufile('module','set','',$code);
			if ($fres) $res['ok'] = 'y';
			else $this->reqres['errors'][] = array('num'=>'0502');
		} else {
			$this->reqres['errors'][] = array('num'=>'0503');
		}
		return $res;
	}

	function setconfig($data)
	{
		$res = array(
			'method' => 'setconfig',
			'ok' => 'n',
		);
		$fres = $this->bufile('config','set','',$data);
		if ($fres) $res['ok'] = 'y';
		else $this->reqres['errors'][] = array('num'=>'0401');
		return $res;
	}

	function modx_unblock_admin_user()
	{
		$res = array(
			'method' => 'modx_unblock_admin_user',
			'ok' => 'n',
		);
		$cms = $this->cms();
		if (
			! $cms
			|| (
				$this->cms != 'modx.evo'
				&& $this->cms != 'evolution'
			)
		) {
			$this->reqres['causes'][] = array('num'=>'0303');
			return $res;
		}
		$dbres = $this->db_access();
		if ( ! $dbres) {
			$this->reqres['errors'][] = array('num'=>'0301');
			return $res;
		}
		$dbres = $this->db_connect();
		if ($dbres['ok'] != 'y') return $res;
		$dbres = $this->db->query("UPDATE `{$this->db_table_prefix}user_attributes`
			SET blocked='0', blockeduntil='0', blockedafter='0'
			WHERE id=1 LIMIT 1");
		if ( ! $dbres) {
			$this->reqres['errors'][] = array('num'=>'0302');
			return $res;
		}
		$res['ok'] = 'y';
		return $res;
	}

	function auth($get_w)
	{
		$auth = 'n';
		session_name('buran');
		session_start();

		if (time() - $_SESSION['buran']['auth'][$get_w] < 60*30) {
			$auth = 'y';
			$this->reqres['auth'] = $auth;
			return $auth;
		}

		unset($_SESSION['buran']);
		$this->htaccess();

		$url = '/buran/key.php';
		$url .= '?h='.$this->domain;
		$url .= '&w='.$get_w;

		if ($this->curl_ext) {
			$options = array(
				CURLOPT_URL => $this->bunker_prcl.$this->bunker.$url,
				CURLOPT_RETURNTRANSFER => true,
			);
			$curl = curl_init();
			curl_setopt_array($curl,$options);
			$ww = curl_exec($curl);
			$curl_errno = curl_errno($curl);
			curl_close($curl);
			if ($curl_errno) {
				$ww = false;
				$this->reqres['errors'][] = array(
					'num' => '0201',
					'info' => $curl_errno,
				);
			}
		}
		if ( ! $ww && $this->sock_ext) {
			$headers = "GET ".$this->bunker_prcl.$this->bunker.$url." HTTP/1.0\n";
			$headers .= "Host: {$this->bunker}\n\n";
			$sockres = stream_socket_client($this->bunker.':80',$errno,$errstr,10);
			if ($sockres) {
				fwrite($sockres,$headers);
				while ( ! feof($sockres)) {
					$ww .= fread($sockres,1024*1024); 
				}
				fclose($sockres);
				$ww = $this->parse_response_headers($ww);
				$ww = $ww[1];
			} else {
				$this->reqres['errors'][] = array('num'=>'0202');
			}
		}
		if ( ! $ww && $this->fgc_ext) {
			$ww = file_get_contents($this->bunker_prcl.$this->bunker.$url);
		}
		if ($ww && $get_w && $ww === $get_w) {
			$_SESSION['buran']['auth'][$get_w] = time();
			$auth = 'y';
		}
		$this->reqres['auth'] = $auth;
		return $auth;
	}

	function parse_response_headers($data)
	{
		$data = str_replace("\r",'',$data);
		$data = explode("\n\n",$data,2);
		return $data;
	}

	function conf($name,$tp='def')
	{
		return isset($this->conf[$tp][$name]) ? $this->conf[$tp][$name] : NULL;
	}

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
			$this->reqres['max'] = array(
				'flg' => true,
				'mct' => $mct,
				'mem' => $memory,
				'cnt' => $this->max['cntr'],
			);
		}
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

	function htaccess()
	{
		$htaccess .= 'Order Deny,Allow'."\n";
		$htaccess .= 'Deny from all'."\n";
		$htaccess .= 'RewriteEngine On'."\n";
		$htaccess .= 'RewriteRule ^(.*)$ index.html [L,QSA]'."\n";
		$fh = fopen($this->droot.$this->mdir.'/.htaccess','wb');
		if ( ! $fh) return false;
		$res = fwrite($fh,$htaccess);
		fclose($fh);
		if ( ! $res) return false;

		$htaccess = 'AddDefaultCharset utf-8'."\n";
		$fh = fopen($this->droot.dirname($this->mdir).'/.htaccess','wb');
		if ( ! $fh) return false;
		$res = fwrite($fh,$htaccess);
		fclose($fh);
		if ( ! $res) return false;

		return true;
	}

	function tarAddFile($file, $tofile)
	{
		if ( ! $this->tar) return false;
		if (is_dir($file)) {
			$v_typeflag = '5';
			$v_size = 0;
		} else {
			$v_typeflag = '';
			$v_size = filesize($file);
		}
		$v_size = sprintf('%11s ', DecOct($v_size));

		$v_mtime_data = filemtime($file);
		$v_mtime = sprintf('%11s', DecOct($v_mtime_data));

		$v_binary_data_first = pack('a100a8a8a8a12A12', $tofile, '', '', '', $v_size, $v_mtime);
		$v_binary_data_last = pack('a1a100a6a2a32a32a8a8a155a12', $v_typeflag, '', '', '', '', '', '', '', '', '');

		$v_checksum = 0;
		for ($i=0; $i<148; $i++) $v_checksum += ord(substr($v_binary_data_first,$i,1));
		for ($i=148; $i<156; $i++) $v_checksum += ord(' ');
		for ($i=156, $j=0; $i<512; $i++, $j++) $v_checksum += ord(substr($v_binary_data_last,$j,1));
		$v_checksum = sprintf('%6s ', DecOct($v_checksum));
		$v_binary_data = pack('a8', $v_checksum);

		if ($this->targzisavailable) {
			$wrtres = gzwrite($this->tar,$v_binary_data_first,148);
		} else {
			$wrtres = fwrite($this->tar,$v_binary_data_first,148);
		}
		if ( ! $wrtres) return false;
		if ($this->targzisavailable) {
			$wrtres = gzwrite($this->tar,$v_binary_data,8);
		} else {
			$wrtres = fwrite($this->tar,$v_binary_data,8);
		}
		if ( ! $wrtres) return false;
		if ($this->targzisavailable) {
			$wrtres = gzwrite($this->tar,$v_binary_data_last,356);
		} else {
			$wrtres = fwrite($this->tar,$v_binary_data_last,356);
		}
		if ( ! $wrtres) return false;
		
		$v_file = fopen($file,'rb');
		if ( ! $v_file) return false;
		while ( ! feof($v_file)) {
			$v_buffer = fread($v_file,512);
			if ( ! $v_buffer) break;
			$v_binary_data = pack('a512',$v_buffer);
			if ($this->targzisavailable) {
				$wrtres = gzwrite($this->tar,$v_binary_data);
			} else {
				$wrtres = fwrite($this->tar,$v_binary_data);
			}
		}
		fclose($v_file);
		if ( ! $wrtres) return false;
		return true;
	}
	function tarEmptyRow()
	{
		if ( ! $this->tar) return false;
		$v_binary_data = pack('a512','');
		return gzwrite($this->tar,$v_binary_data);
	}
	function tarOpen($tarfile, $mode='w')
	{
		if ($this->targzisavailable) {
			$p_tar = gzopen($tarfile,$mode.'b9');
		} else {
			$p_tar = fopen($tarfile,$mode.'b');
		}
		if ( ! $p_tar) return false;
		$this->tar = $p_tar;
		return true;
	}
	function tarClose()
	{
		if ( ! $this->tar) return false;
		if ($this->targzisavailable) {
			$clsres = gzclose($this->tar);
		} else {
			$clsres = fclose($this->tar);
		}
		return $clsres;
	}
}
// ----------------------------------------------
// ----------------------------------------------
// ----------------------------------------------
// -----------------------------------
