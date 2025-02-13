<?php

namespace app\hardware\controller;

use think\worker\Server;
use think\facade\Db;

require_once root_path() . '/vendor/autoload.php';

class Roundworker extends Server
{

}