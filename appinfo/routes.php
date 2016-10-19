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

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\CernboxNice\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
	   ['name' => 'page#create_home_dir', 'url' => '/createhomedir/{username}', 'verb' => 'POST'],
    ]
];
