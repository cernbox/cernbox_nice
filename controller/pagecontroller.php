<?php
/**
 * ownCloud - cernboxnice
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Gonzalez Labrador <hugo.gonzalez.labrador@cern.ch>
 * @copyright Hugo Gonzalez Labrador 2016
 */

namespace OCA\CernboxNice\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {


	private $secret;
	private $userId;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->secret = \OC::$server->getConfig()->getSystemValue("cernbox.nice.secret");
		$this->logger = \OC::$server->getLogger();
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function createHomeDir($username, $secret, $dirs) {
		// validate token is correct, else 401
		if(!$this->secret) {
			$this->logger->error("cernbox.nice.secret has not been defined in the config.php file");
			return new DataResponse(null, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if($this->secret !== $secret) {
			$this->logger->error("access to cernboxnice denied because secrets do not match");
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}
		if(!$username) {
			$this->logger->info("username cannot be empty");
			$error = array(
				"code" => 1,
				"msg" => "username cannot be empty"
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}
		$dirs = $this->sanitizeDirs($dirs);

		$uidAndGid = \OC\Files\ObjectStore\EosUtil::getUidAndGid($username);
		if($uidAndGid === false) {
			\OCP\Util::writeLog('cernboxnice', "user($username) has not a valid uid", \OCP\Util::ERROR);
			$error = array(
				"code" => 2,
				"msg" => "Your account ($username) has no computing group assigned. <br> Please use the CERN Account Service to fix this.  You may also check out <a href=\"https://cern.service-now.com/service-portal/article.do?n=KB0002981\">CERNBOX FAQ</a> for additional information. <br> If the problem persists then please report it via CERN Service Portal."
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}
		list($uid, $gid) = $uidAndGid;
		\OCP\Util::writeLog('cernboxnice', "user($username) has valid uid($uid) and gid($gid)", \OCP\Util::ERROR);
		
		$homedir = \OC\Files\ObjectStore\EosUtil::getEosPrefix() . substr($username, 0, 1) . "/" . $username . "/";
		$errorCode = -1;
		do
		{
			$cmd = "eos -r $uid $gid stat $homedir";
			list($result, $errorCode) = \OC\Files\ObjectStore\EosCmd::exec($cmd);
			if($errorCode != \OC\Files\ObjectStore\EosUtil::STAT_FILE_EXIST)
			{
				usleep(500000); // Half a second delay between request (microseconds)
			}
		}
		while($errorCode != \OC\Files\ObjectStore\EosUtil::STAT_FILE_EXIST && $errorCode != \OC\Files\ObjectStore\EosUtil::STAT_FILE_NOT_EXIST);
		
		if($errorCode === \OC\Files\ObjectStore\EosUtil::STAT_FILE_EXIST) { // path exists so we let the user access the system
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		}
			
		\OCP\Util::writeLog('cernboxnice', "user($username) does NOT have valid homedir($homedir)", \OCP\Util::ERROR);
		// the path does not exists so we create it
		// create home dir
		$script_path = \OCP\Config::getSystemValue("eos_configure_new_homedir_script_path", false);
		$eosMGMURL = \OCP\Config::getSystemValue("eos_mgm_url", false);
		$eosPrefix = \OCP\Config::getSystemValue("eos_prefix", false);
		$eosRecycle = \OCP\Config::getSystemValue("eos_recycle_dir", false);
		
		if(!$script_path || !$eosMGMURL || !$eosPrefix || !$eosRecycle) {
			\OCP\Util::writeLog('cernboxnice', "cannot find script for creating users. check config.php", \OCP\Util::ERROR);
			return new DataResponse(null, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		
		$result = null;
		$errcode = null;
		
		$cmd2 = "/bin/bash $script_path " . $eosMGMURL . ' ' . $eosPrefix . ' ' . $eosRecycle . ' ' . $username;
		exec($cmd2, $result, $errcode);
		if($errcode !== 0) {
			\OCP\Util::writeLog('cernboxnice', "error running the script to create the homedir($homedir) for user($username) CMD($cmd2) errcode($errcode)", \OCP\Util::ERROR);
			$error = array(
				"code" => 2,
				"msg" => "Your account ($username) has no computing group assigned. <br> Please use the CERN Account Service to fix this.  You may also check out <a href=\"https://cern.service-now.com/service-portal/article.do?n=KB0002981\">CERNBOX FAQ</a> for additional information. <br> If the problem persists then please report it via CERN Service Portal."
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}

		\OCP\Util::writeLog('cernboxnice', "homedir($homedir) created for user($username)", \OCP\Util::ERROR);

		// at this point the user home directory has been created
		// and we need to create the given directories
		$failedDirs = array();
		foreach($dirs as $dir) {
			// storageId is object::store:/eos/dev/user/ or object::user:labrador
			$eosDir = \OC\Files\ObjectStore\EosProxy::toEos("files/" . $dir, "object::user:$username");
			$eosDirEscaped = escapeshellarg($eosDir);
			$cmd = "eos -b -r $uid $gid mkdir $eosDirEscaped";
			list($result, $errcode) = \OC\Files\ObjectStore\EosCmd::exec($cmd);
			if($errcode !== 0){
				$failedDirs[] = $dir;
			}
		}
		
		if(count($failedDirs) > 0) {
			$error = array(
				"code" => 3,
				"msg" => $failedDirs,
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}
		
		return new DataResponse(null, Http::STATUS_CREATED);
	}

	private function sanitizeDirs($dirs) {
		$sanitizedDirs = array();
		if(is_array($dirs)) {
			foreach($dirs as $dir) {
				if(is_string($dir)) {
					$sanitizedDirs[] = $dir;	
				}
			}
		}	
		return $sanitizedDirs;
	}
}
