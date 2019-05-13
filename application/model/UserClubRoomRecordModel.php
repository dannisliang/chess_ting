<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2019/3/27
 * Time: 17:06
 */
namespace app\model;


use think\Model;

class UserClubRoomRecordModel extends Model
{
    protected $name = 'user_club_room_record';

    public function getUserClubRoomRecord($userId, $clubId){
        $timeEnd = date("Y-m-d H:i:s", bcsub(time(), bcmul(bcmul(3600, 24, 0), 3, 0), 0)); # 三天前
        return $this->where('user_id', '=', $userId)->where('club_id', '=', $clubId)->where('add_time', '>', $timeEnd)->select();
    }

    /**
     * 批量插入用户牌局记录
     * @param $data
     * @return int|string
     */
    public function insertAllUserRecord($data){
        return $this->insertAll($data);
    }

    public function delUserClubRecord($dateTime){
        return $this->where('add_time', '<', $dateTime)->delete();
    }


    public function getUsedRoomNum(){
        return $this->column('room_id');
    }

    public function getOneRecord($userId, $roomId){
        return $this->where('user_id', '=', $userId)->where('room_id', '=', $roomId)->find();
    }
}