<?php
/*
 * This file is part of the Session package.
 *
 * (c) Unit6 <team@unit6websites.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require 'Session.php';

$html = <<<HTML
<!doctype html>
<html lang=en>
<head>
    <meta charset=utf-8>
    <title>Unit6\Session - Example</title>
</head>
<body>
    <p>I'm the content</p>
</body>
</html>
HTML;

echo $html;