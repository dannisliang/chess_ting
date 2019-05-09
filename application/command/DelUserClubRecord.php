<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/4/9
 * Time: 10:58
 */
namespace app\command;

use think\console\Input;
use think\console\Output;
use think\console\Command;
use app\model\UserClubRoomRecordModel;

class DelUserClubRecord extends Command{

    protected function configure()
    {
        $this->setName('DelUserClubRecord')->setDescription('用于删除三天前的玩家牌局记录，每日9：00执行');
    }

    protected function execute(Input $input, Output $output)
    {
        $dateTime = date("Y-m-d H:i:s", (time()-3600*24*3));
        $userClubRoomRecord = new UserClubRoomRecordModel();
        $userClubRoomRecord->delUserClubRecord($dateTime);
    }
}