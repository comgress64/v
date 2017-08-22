<?php

function vpncommand($cmd) {
	$res['status']='ok';
	$retval="";
	if(config('app.useshell')) {
		$retval=exec("bash -l -c '/usr/bin/sudo " . $cmd . "'");
	}
	else {
		try {
			SSH::run('bash -l -c \'' . $cmd . '\'', function($line) use (&$retval) {
				$retval.=$line.PHP_EOL;
			});
		}
		catch(Exception $e) {
			$res['status']='error';
			$res['error_description']="ssh error: " . $e->getMessage();
			return $res;
		}
	}
	Log::info($retval);
	preg_match_all('/^ERROR.*/m', $retval, $error);
	if(!empty($error[0])) {
		$res['status']='error';
		$res['error_description']=$error[0];
		return $res;
	}
	
	$res['retval']=$retval;
	return $res;
}

?>
