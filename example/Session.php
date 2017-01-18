<?php
/*
 * This file is part of the Session package.
 *
 * (c) Unit6 <team@unit6websites.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

date_default_timezone_set('Europe/London');

require realpath(dirname(__FILE__) . '/../autoload.php');
#require realpath(dirname(__FILE__) . '/../vendor/autoload.php');

use Unit6\Session;

$name = 'u6sid';

$handler = new Session\Handler\Native($name, []);

$options = [
    'namespace' => 'users.examples'
];

$session = new Session\Manager($handler, $options);

#var_dump($session, $session->getStatus()); exit;

$session->start();
#$session->destroy();var_dump($_SESSION, $session->getStatus(), $session->getId(), $session->getName()); exit;

$session->set('foo', 'bar');
$session->set('foo.date', date('r'));
#$session->set('message', 'Welcome', Session\Manager::EXPIRE_ON_GET);

#var_dump($_SESSION, $session->get('foo'), 'beforeStop'); exit;

#var_dump($session->get('message'));

#var_dump($session->get('message')); exit;

$session->stop();

var_dump($_SESSION, $session->get('foo'), 'afterStop'); exit;


