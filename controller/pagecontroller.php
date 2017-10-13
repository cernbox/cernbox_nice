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
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {


	private $secret;
	private $userId;
	private $logger;
	private $instanceManager;
	private $dirsToCheck = ["Desktop" => false, "Documents" => false, "Music" => false, "Pictures" => false, "Videos" => false];

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->secret = \OC::$server->getConfig()->getSystemValue("cbox.nice.secret");
		$this->logger = \OC::$server->getLogger();
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
	}

	private function getSecret() {
		$headers = getallheaders();
		$auth = isset($headers['Authorization']) ? $headers['Authorization'] : null;
		if(!$auth) {
			return false;
		}
		$parts = explode(" ", $auth);
		if (count($parts) < 2) {
			return false;
		}
		return $parts[1];
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function checkHomeDir($username, $dirs) {
		// validate token is correct, else 401
		if(!$this->secret) {
			$this->logger->error("cernbox.nice.secret has not been defined in the config.php file");
			return new DataResponse(null, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if($this->secret !== $this->getSecret()) {
			var_dump($secret);
			$this->logger->error("access to cernboxnice denied because secrets do not match");
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}
		if(!$username) {
			$this->logger->error("username cannot be empty");
			$error = array(
				"code" => 1,
				"msg" => "username cannot be empty"
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}

		$uidAndGid = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		if($uidAndGid === false) {
			$this->logger->error("cernboxnice: user($username) has not a valid uid");
			$error = array(
				"code" => 2,
				"msg" => "Your account ($username) has no computing group assigned. <br> Please use the CERN Account Service to fix this.  You may also check out <a href=\"https://cern.service-now.com/service-portal/article.do?n=KB0002981\">CERNBOX FAQ</a> for additional information. <br> If the problem persists then please report it via CERN Service Portal."
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}

		$info = $this->instanceManager->get($username, 'files'); // home
		if($info === false)  { // home dir not exist
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		foreach($this->dirsToCheck as $dir => $exist) {
			$path = "files/" . $dir;
			$info = $this->instanceManager->get($username, $path);
			if($info !== false) {
				$this->dirsToCheck[$dir] = true;
			}
		}
		return new DataResponse(['dirs' => $this->dirsToCheck]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function createHomeDir($username, $dirs) {
		// validate token is correct, else 401
		if(!$this->secret) {
			$this->logger->error("cernbox.nice.secret has not been defined in the config.php file");
			return new DataResponse(null, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if($this->secret !== $this->getSecret()) {
			$this->logger->error("access to cernboxnice denied because secrets do not match");
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}
		if(!$username) {
			$this->logger->error("username cannot be empty");
			$error = array(
				"code" => 1,
				"msg" => "username cannot be empty"
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}

		$dirs = $this->sanitizeDirs($dirs);
		$uidAndGid = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		if($uidAndGid === false) {
			$this->logger->error("cernboxnice: user($username) has not a valid uid");
			$error = array(
				"code" => 2,
				"msg" => "Your account ($username) has no computing group assigned. <br> Please use the CERN Account Service to fix this.  You may also check out <a href=\"https://cern.service-now.com/service-portal/article.do?n=KB0002981\">CERNBOX FAQ</a> for additional information. <br> If the problem persists then please report it via CERN Service Portal."
			);
			return new DataResponse($error, Http::STATUS_BAD_REQUEST);
		}
		list($uid, $gid) = $uidAndGid;

		$failedDirs = [];
		$info = $this->instanceManager->get($username, 'files'); // home
		if($info === false)  { // home dir not exist
			$ok = $this->instanceManager->createHome($username);
			if($ok === false) {
				$this->logger->error("cannot create homedir for user $username");
				return new DataResponse(null, Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		foreach($dirs as $dir) {
			$path = "files/" . trim($dir, "/");
			$i = $this->instanceManager->get($username, $path);
			if ($i === false) { //create dir
				$ok = $this->instanceManager->createDir($username, $path);
				if($ok === false) { // failed to create the dir, report to client
					$failedDirs[] = $dir;
				}
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
