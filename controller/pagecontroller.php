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
			return new DataResponse(null, Http::STATUS_INTERNAL);
		}
		if($this->secret !== $secret) {
			$this->logger->error("access to cernboxnice denied because secrets do not match");
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}
		if(!$username) {
			$this->logger->info("username cannot be empty");
			$error = array(
				"msg" => "username cannot be empty"
			);
			return new DataResponse($error, Http::STATUS_BADREQUEST);
		}
		$dirs = $this->sanitizeDirs($dirs);

		$ok = \OC::$server->getUserSession()->setUpNewUser($username);
		if($ok === true) {
			$this->logger->info("setup of new user $username was correct");
			return new DataResponse(["msg" => "homdir created for user $username"]);
		} else {
			$this->logger->info("setup of new user $username was bad");
			return new DataResponse(["msg" => $ok]);
		}

		/*

		// create home directory for user
		$homeDirExists = false;
		$errorCode = $this->homeDirExistsForUser($username);
		if($errorCode === 0) {
			$homeDirExists = true;
		} else {
			// homedir does not exist so we create it
			$errorCode = $this->createHomeDirForUser($username);
			
		}
		return new DataResponse(["username" => $username, "dirs" => $dirs, "secret" => $secret]);
		*/
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
	}
}
