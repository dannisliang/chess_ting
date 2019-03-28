<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/27
 * Time: 14:30
 */

namespace app\command;

use app\definition\Definition;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Obs\ObsClient;

class Test extends Command{

    protected function configure()
    {
        $this->setName('Test')->setDescription('测试用');
    }

    protected function execute(Input $input, Output $output)
    {

    }
}