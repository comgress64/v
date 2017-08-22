<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Request;


Route::get('/', function () {
	$res['status']='error';
	return $res;
});

Route::match(['get', 'post'], '/api/getkey', function(Request $request) {
	$ufield=config('app.ufield');
	$p=request()->all();
	if(!isset($p[$ufield])) {
		$res['status']='error';
		$res['error_description']='no client id given';
		return $res;
	}
	if(config('app.token') && isset($p['token']) && (config('app.token') != $p['token'])) {
		$res['status']='error';
		$res['error_description']='wrong token';
		return $res;
	}
	$id=$p[$ufield];
	if(isset($p['group_id'])) {
		$gid=strtolower($p['group_id']);
	}
	else {
		$res['status']='error';
		$res['error_description']='no gid provided';
		return $res;
	}
	$res[$ufield]=$id;
	$res['status']="ok";

	$retval="";

#check if user is suspended
	$filename=base_path() . "/public/clients/suspended/" . $id;
	if(file_exists($filename)) {
		$cmd='unsusp ' . $id;
		$ret=vpncommand($cmd);
		if($ret['status'] == 'error') {
			return $ret;
		}
		if(preg_match('/^OK.*/m', $ret['retval'])) {
			Log::info("unsuspended");
		}
		else {
			$res['status']='error';
			$res['error_description']="something went wrong while unsuspending ".$id;
			return $res;
		}
	}

#check if key exists
	$filename=base_path() . "/public/clients/" . $id . ".ovpn";
	if(file_exists($filename)) {
		$res['key_url']="/clients/" . $id . ".ovpn";
		Log::info("config file found in " . $filename);
		return $res;
	}

#generate new user
	$cmd='gen ' . $id . ' ' . $gid;
	$ret=vpncommand($cmd);
	if($ret['status'] == 'error') {
		return $ret;
	}
	if(preg_match('/^OK.*/m', $ret['retval'])) {
		$res['key_url']='/clients/' . $id . ".ovpn";
		if(preg_match('/ip pool updated: (.*)/m', $ret['retval'], $ip)) {
			if(!empty($ip[1])) {
				$res['ip']=$ip[1];
			}
		}
	}
	else {
		$res['status']='error';
		$res['error_description']='unknown error';
	}
	
	return $res;
});


Route::match(['get', 'post'], '/api/stopkey', function(Request $request) {
	$ufield=config('app.ufield');
	$p=request()->all();
	if(!isset($p[$ufield])) {
		$res['status']='error';
		$res['error_description']='no client id given';
		return $res;
	}
	if(config('app.token') && isset($p['token']) && (config('app.token') != $p['token'])) {
		$res['status']='error';
		$res['error_description']='wrong token';
		return $res;
	}
	if(!isset($p['action']) || ($p['action'] != 'suspend' && $p['action'] != 'remove')) {
		$res['status']='error';
		$res['error_description']='no correct action provided';
		return $res;
	}

#FIXME: allow only alphanumeric
	$id=$p[$ufield];

	$res['status']="ok";
	$retval="";

	$cmd="";
	if($p['action'] == 'suspend') {
		$cmd="susp";
	}
	else {
		$cmd="remove";
	}
	$cmd.=' ' . $id;
	$ret=vpncommand($cmd);
	if($ret['status'] == 'error') {
		return($ret);
	}
	if(preg_match('/^OK.*/m', $ret['retval'])) {
		$res['status']='ok';
	}
	else {
		$res['status']='error';
		$res['error_description']='unknown error';
	}
	return $res;
});

Route::match(['get', 'post'], '/api/setgroups', function(Request $request) {
	$ufield=config('app.ufield');
	$p=request()->all();
	if(!isset($p[$ufield]) || !isset($p['group_id']) || !isset($p['action'])) {
		$res['status']='error';
		$res['error_description']='wrong request parameters';
		return $res;
	}
	if(config('app.token') && isset($p['token']) && (config('app.token') != $p['token'])) {
		$res['status']='error';
		$res['error_description']='wrong token';
		return $res;
	}

	$res['status']='ok';

	$src=$p[$ufield];
	$action=$p['action'];

	if(preg_match('/^([0-9a-f]{4}:){7}[0-9a-f]{4}(\/[0-9]{1,3})?$/im', $src)) {
		$action="g" . $action; #add -> gadd, grm -> grm if we are dealing with ip address
		$src=strtolower($src);
	}

	$ports="";
	if(isset($p['ports'])) {
		$ports=strtolower(implode(',', $p['ports']));
	}
	$gid=strtolower($p['group_id']);

	$cmd='fw ' . $src . ' ' . $action . ' ' . $gid . ' ' .  $ports;
	$ret=vpncommand($cmd);
	if($ret['status'] == 'error') {
		return($ret);
	}

	if(preg_match('/^OK.*/m', $ret['retval'])) {
		$res['status']='ok';
	}
	else {
		$res['status']='error';
		$res['error_description']='unknown error';
	}

	return $res;
});

Route::match(['get', 'post'], '/api/getstatus', function(Request $request) {
	$ufield=config('app.ufield');
	$p=request()->all();
	if(!isset($p[$ufield])) {
		$res['status']='error';
		$res['error_description']='wrong request parameters';
		return $res;
	}
	if(config('app.token') && isset($p['token']) && (config('app.token') != $p['token'])) {
		$res['status']='error';
		$res['error_description']='wrong token';
		return $res;
	}

	$res['status']='nonexistent';

	$clients = preg_grep('/^fd[0-9a-f:]*,' . $p[$ufield] . ',/m', file(base_path() . "/public/clients/openvpn-status.log"));
	if($clients) {
		$res['status']='connected';
		return $res;
	}

	$filename=base_path() . "/public/clients/suspended/" . $p[$ufield];
	if(file_exists($filename)) {
		$res['status']='suspended';
		return $res;
	}

	$filename=base_path() . "/public/clients/" . $p[$ufield] . ".ovpn";
	if(file_exists($filename)) {
		$res['status']='disconnected';
		return $res;
	}

	return $res;
});

