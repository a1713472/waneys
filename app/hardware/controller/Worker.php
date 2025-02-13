<?php
declare (strict_types=1);

namespace app\hardware\controller;

use app\BaseController;
use mysql_xdevapi\Result;
use think\event\AppInit;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use think\route\Rule;
use think\facade\Log;
use think\worker\Server;
use Workerman\Lib\Timer;

require_once root_path() . '/vendor/autoload.php';
require_once root_path() . '/vendor/workerman/workerman/Protocols/Eys.php';

class Worker extends Server
{
    protected $socket = 'Eys://0.0.0.0:2345';

    protected $uidConnections = [];

    //判断玩家是否带刀,返回true为带刀,false为不带刀
    protected function if_role_with_knife($player_identity)
    {
        $knife_roles = json_decode(Db::table('setting')->where('id', 1)->value('with_knife_roles'), true);
        $if_with_knife = false;
        foreach ($knife_roles as $roles) {
            if (in_array($player_identity, $roles)) {
                $if_with_knife = true;
                break;
            }
        }
        return $if_with_knife;
    }

    //返回被吃玩家的数量
    protected function eat_body_count($all_user)
    {
        $count = 0;
        foreach ($all_user as $user) {
            if ($user['status'] == 'eaten') {
                $count++;
            }
        }
        return $count;
    }

    //返回true表示该玩家已被吃掉,返回false表示该玩家没被吃掉
    protected function if_player_eated($player_info)
    {
        return $player_info['status'] == 'eaten';
    }

    //正常刀人流程
    protected function knife_kill_player(&$killer_info, &$killer_target_info, $game_info)
    {
        if ($killer_info['identity'] == '警长') {
            if ($killer_target_info['group'] == '鹅') {
                $killer_info['status'] = 'out';
                $killer_info['out_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                    if (isset($v['if_this_round_end'])) {
                        return $v['if_this_round_end'] == true;
                    } else {
                        return false;
                    }
                }));
                $killer_target_info['status'] = 'out';
                $killer_target_info['out_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                    if (isset($v['if_this_round_end'])) {
                        return $v['if_this_round_end'] == true;
                    } else {
                        return false;
                    }
                }));
                $killer_info['use_killing_time'] = time();
            } else {
                $killer_target_info['status'] = 'out';
                $killer_target_info['out_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                    if (isset($v['if_this_round_end'])) {
                        return $v['if_this_round_end'] == true;
                    } else {
                        return false;
                    }
                }));
                $killer_info['use_killing_time'] = time();
            }
        } else {
            $killer_target_info['status'] = 'out';
            $killer_target_info['out_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                if (isset($v['if_this_round_end'])) {
                    return $v['if_this_round_end'] == true;
                } else {
                    return false;
                }
            }));
            $killer_info['use_killing_time'] = time();
        }
    }

    protected function knife_beha($killer_id, $killer_target_id)
    {

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);

        $killer_info = null;
        $killer_target_info = null;
        foreach ($all_user as $user) {
            if ((int)($user['game_id']) == (int)$killer_id) {
                $killer_info = $user;
            }
            if ((int)($user['game_id']) == (int)$killer_target_id) {
                $killer_target_info = $user;
            }
        }

        $curr_time = time();

        if ($killer_info['status'] != 'alive') {
            //未存活状态刀人的情况
            return;
        }

        if (!($curr_time - $game_info['new_round_time'] >= 20)) {
            //未到时间刀人的情况
            return;
        }


        $if_killer_with_knife = false;
        //获取绑定的玩家并判断是否带刀
        if ($killer_info['bundle_player'] !== null) {
            $bundle_player = null;
            foreach ($all_user as $user) {
                if ((int)($user['game_id']) == (int)$killer_info['bundle_player']) {
                    $bundle_player = $user;
                    break;
                }
            }
            if ($this->if_role_with_knife($bundle_player['identity'])) {
                $if_killer_with_knife = true;
            } else {
                $if_killer_with_knife = false;
            }
        } else {
            $if_killer_with_knife = $this->if_role_with_knife($killer_info['identity']);
        }

        if ($if_killer_with_knife) {
            if ($killer_info['use_killing_time'] != null) {
                if ($curr_time - $killer_info['use_killing_time'] < 60) {
                    //刀人cd未到的情况
                    $killer_info['status'] = 'out';
                    $killer_info['out_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                        if (isset($v['if_this_round_end'])) {
                            return $v['if_this_round_end'] == true;
                        } else {
                            return false;
                        }
                    }));
                } else {
                    //正常刀人流程
                    $this->knife_kill_player($killer_info, $killer_target_info, $game_info);
                }
            } else {
                //正常刀人流程
                $this->knife_kill_player($killer_info, $killer_target_info, $game_info);
            }

        } else {
            //非带刀角色情况
            $killer_info['status'] = 'out';
            $killer_info['out_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                if (isset($v['if_this_round_end'])) {
                    return $v['if_this_round_end'] == true;
                } else {
                    return false;
                }
            }));
        }

        //更新玩家状态
        foreach ($all_user as $k => $user) {
            if ((int)($user['game_id']) == (int)$killer_id) {
                $all_user[$k] = $killer_info;
            }
            if ((int)($user['game_id']) == (int)$killer_target_id) {
                $all_user[$k] = $killer_target_info;
            }
        }

        update_if_bundle_and_all_die($all_user, $game_info);

        detect_alarm_role_status_and_send_command($all_user, $game_info);

        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'all_user_info' => serialize($all_user),
            'game_info' => serialize($game_info)
        ]);

    }

    protected function alarm_role_update($target, &$game_info)
    {
        $alarm_roles = json_decode(Db::table('setting')->where('id', 1)->value('alarm_roles'), true);

        if ($target['status'] == 'out' && in_array($target['identity'], $alarm_roles)) {

            $game_info['alarm_round'] = count($game_info['vote_result']);
        }
    }

    protected function eat_beha($eater_id, $eat_target_id)
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);
        $eat_roles = json_decode(Db::table('setting')->where('id', 1)->value('eater_roles'), true);

        $eater_info = null;
        $eat_target_info = null;
        foreach ($all_user as $user) {
            if ((int)($user['game_id']) == (int)$eater_id) {
                $eater_info = $user;
            }
            if ((int)($user['game_id']) == (int)$eat_target_id) {
                $eat_target_info = $user;
            }
        }

        $curr_time = time();

        if (!in_array($eater_info['identity'], $eat_roles)) {
            //非吃人角色的情况
            return;
        }

        if ($eater_info['status'] != 'alive') {
            //未存活状态吃人的情况
            return;
        }

        if (!($curr_time - $game_info['new_round_time'] >= 20)) {
            //未到时间吃人的情况
            return;
        }

        if ($eater_info['use_eating_time'] != null) {
            if ($curr_time - $eater_info['use_eating_time'] < 60) {
                //未满足吃人时间间隔的情况
                return;
            }
        }

        if ($this->if_player_eated($eat_target_info)) {
            //目标玩家已被吃掉
            return;
        } else {
            if ($this->eat_body_count($all_user) < 4) {
                $eat_target_info['status'] = 'eaten';
                $eater_info['use_eating_time'] = time();
            } else {
                //被吃人数已满
                return;
            }
        }

        //更新玩家状态
        foreach ($all_user as $k => $user) {
            if ((int)($user['game_id']) == (int)$eat_target_id) {
                $all_user[$k] = $eat_target_info;
            }
            if ((int)($user['game_id']) == (int)$eater_id) {
                $all_user[$k] = $eater_info;
            }
        }

        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'all_user_info' => serialize($all_user)
        ]);

    }

    protected function eat_body_beha($eater_id, $eat_target_id)
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);
        $eat_roles = json_decode(Db::table('setting')->where('id', 1)->value('eat_body_roles'), true);

        $eater_info = null;
        $eat_target_info = null;
        foreach ($all_user as $user) {
            if ((int)($user['game_id']) == (int)$eater_id) {
                $eater_info = $user;
            }
            if ((int)($user['game_id']) == (int)$eat_target_id) {
                $eat_target_info = $user;
            }
        }

        if ($eater_info['status'] != 'alive') {
            //非存活状态
            return;
        }

        if (in_array($eater_info['identity'], $eat_roles) && $eater_info['identity'] == '秃鹫' && $eater_info['rd_body_count'] < 3 && $eat_target_info['status'] == 'out') {
            $eat_target_info['status'] = 'disappeared';
            $eater_info['rd_body_count']++;
        }

        if (in_array($eater_info['identity'], $eat_roles) && $eater_info['identity'] == '食鸟鸭' && $eater_info['eat_body_count'] < 1 && $eat_target_info['status'] == 'out') {
            $eat_target_info['status'] = 'disappeared';
            $eater_info['eat_body_count']++;
        }

        //更新玩家状态
        foreach ($all_user as $k => $user) {
            if ((int)($user['game_id']) == (int)$eat_target_id) {
                $all_user[$k] = $eat_target_info;
            }
            if ((int)($user['game_id']) == (int)$eater_id) {
                $all_user[$k] = $eater_info;
            }
        }
        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'all_user_info' => serialize($all_user)
        ]);
    }

    protected function bundle_other_player($bundle_ori_id, $bundle_target_id)
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $bundle_roles = json_decode(Db::table('setting')->where('id', 1)->value('bundle_roles'), true);

        $all_user = unserialize($result['all_user_info']);

        foreach ($all_user as $k => $user) {
            if ((int)($user['game_id']) == (int)$bundle_ori_id) {
                if ($user['status'] != 'alive') {
                    //非存活状态
                    return;
                }
                if (in_array($user['identity'], $bundle_roles)) {
                    $all_user[$k]['bundle_player'] = (int)$bundle_target_id;
                } else {
                    return;
                }
            }
        }

        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'all_user_info' => serialize($all_user)
        ]);
    }

    protected function touch_dead_player($touch_player_id, $target_player_id)
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $eat_roles = json_decode(Db::table('setting')->where('id', 1)->value('eat_body_roles'), true);

        $game_info = unserialize($result['game_info']);
        $all_user = unserialize($result['all_user_info']);

        $touch_info = null;
        $touch_target_info = null;
        foreach ($all_user as $k => $user) {
            if ((int)($user['game_id']) == (int)$touch_player_id) {
                $touch_info = $user;
            }
            if ((int)($user['game_id']) == (int)$target_player_id) {
                $touch_target_info = $user;
            }
        }
        if ($touch_info['status'] != 'alive') {
            //非存活状态无法触发装置
            return;
        }
        if (!in_array($touch_info['identity'], $eat_roles)) {
            if ($touch_target_info['status'] == 'out') {
                $game_info['if_alarm'] = 1;
                $game_info['if_just_alarm'] = true;
                $game_info['alarm_round'] = count(array_filter($game_info['vote_result'], function ($v) {
                    if (isset($v['if_this_round_end'])) {
                        return $v['if_this_round_end'] == true;
                    } else {
                        return false;
                    }
                }));
            }
        }

        if ($game_info['if_alarm'] == 1) {
            //拉响警报情况
        }

        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'game_info' => serialize($game_info)
        ]);

    }

    //获取目标玩家
    protected function get_player_status($id)
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_user = unserialize($result['all_user_info']);

        foreach ($all_user as $user) {
            if ((int)($user['game_id']) == (int)$id) {
                return $user;
            }
        }
        return 'none';
    }

    //是否是吃人角色
    protected function if_eater_role($player_identity)
    {
        $eat_roles = json_decode(Db::table('setting')->where('id', 1)->value('eater_roles'), true);

        if (in_array($player_identity, $eat_roles)) {
            return true;
        } else {
            return false;
        }
    }

    //是否是吃尸体角色
    protected function if_eater_body_role($player_identity)
    {
        $eat_body_roles = json_decode(Db::table('setting')->where('id', 1)->value('eat_body_roles'), true);

        if (in_array($player_identity, $eat_body_roles)) {
            return true;
        } else {
            return false;
        }
    }

    //是否是绑定别人的角色
    protected function if_bundle_role($player)
    {
        $bundle_roles = json_decode(Db::table('setting')->where('id', 1)->value('bundle_roles'), true);

        if (in_array($player['identity'], $bundle_roles) && $player['bundle_player'] === null) {
            return true;
        } else {
            return false;
        }
    }

    //根据玩家状态和身份返回操作类型
    protected function get_ope_type($ori_id, $target_id, $all_user)
    {
        $ori_player = null;
        $target_player = null;

        foreach ($all_user as $user) {
            if ((int)($user['game_id']) == (int)$ori_id) {
                $ori_player = $user;
            }
            if ((int)($user['game_id']) == (int)$target_id) {
                $target_player = $user;
            }
        }
        if ($ori_player['status'] != 'alive') {
            return false;
        } else {
            if (!$this->if_eater_body_role($ori_player['identity']) && $target_player['status'] == 'out') {
                return 'touch';
            }
            if ($this->if_eater_body_role($ori_player['identity']) && $target_player['status'] == 'out') {
                return 'eat_body';
            }
            if ($this->if_eater_role($ori_player['identity']) && $target_player['status'] == 'alive') {
                return 'eat';
            }
            if ($this->if_bundle_role($ori_player) && $target_player['status'] == 'alive') {
                return 'bundle';
            }
            if ($target_player['status'] == 'alive' || $target_player['status'] == 'eaten') {
                return 'knife';
            }
            return false;
        }
    }

    protected function playersOperation($ori, $target)
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($result['status'] != '游戏中') {
            return 'none';
        }

        $game_info = unserialize($result['game_info']);
        $all_user = unserialize($result['all_user_info']);

        $ori_player = $ori;
        $target_player = $target;

        $ope_type = $this->get_ope_type($ori_player, $target_player, $all_user);

        if (!detect_if_alarm($game_info)) {
            if (!$ope_type) {
                return 'none';
            }
            switch ($ope_type) {
                case 'knife':
                {
                    $this->knife_beha($ori_player, $target_player);
                    break;
                }
                case 'eat':
                {
                    $this->eat_beha($ori_player, $target_player);
                    break;
                }
                case 'eat_body':
                {
                    $this->eat_body_beha($ori_player, $target_player);
                    break;
                }
                case 'bundle':
                {
                    $this->bundle_other_player($ori_player, $target_player);
                    break;
                }
                case 'touch':
                {
                    $this->touch_dead_player($ori_player, $target_player);
                    break;
                }
            }
            return json_encode([
                'ori' => $this->get_player_status($ori_player),
                'target' => $this->get_player_status($target_player),
                'ope_type' => $ope_type
            ]);
        }
        return 'alarm';
    }

    protected function hardwareDataManage()
    {
        // 假设我们使用 TCP/IP Socket 与物联网设备通信
        $host = '192.168.1.100'; // 物联网设备的 IP 地址
        $port = 12345; // 物联网设备监听的端口

        // 创建 Socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            Log::error("无法创建 socket: " . socket_strerror(socket_last_error()));
            exit;
        }

        // 连接到物联网设备
        $result = socket_connect($socket, $host, $port);
        if ($result === false) {
            Log::error("无法连接到物联网设备: " . socket_strerror(socket_last_error($socket)));
            socket_close($socket);
            exit;
        }

        // 发送数据到物联网设备
        $messageToSend = "Hello, IoT Device!";
        socket_write($socket, $messageToSend, strlen($messageToSend));

        // 接收来自物联网设备的数据
        $buffer = socket_read($socket, 1024); // 读取最多 1024 字节的数据

        // 关闭 Socket
        socket_close($socket);

        // 打印接收到的数据
        if ($buffer !== false) {
            echo "从物联网设备接收到的数据: " . $buffer . PHP_EOL;
        } else {
            Log::error("读取数据失败: " . socket_strerror(socket_last_error($socket)));
        }
    }

    protected function crc16_verify($ori_data)
    {
        $dataBytes = [];
        for ($i = 0; $i < strlen($ori_data); $i += 2) {
            $dataBytes[] = hexdec(substr($ori_data, $i, 2));
        }


        $data = function () use ($dataBytes) {
            $crc = 0xFFFF;
            $polynomial = 0xA001;  // This is the polynomial x^16 + x^15 + x^2 + 1

            foreach ($dataBytes as $byte) {
                $crc ^= $byte;
                for ($i = 0; $i < 8; $i++) {
                    if ($crc & 0x0001) {
                        $crc = (($crc >> 1) ^ $polynomial) & 0xFFFF;
                    } else {
                        $crc >>= 1;
                    }
                }
            }
            return $crc;
        };

        $data_tmp = sprintf('%04X', $data());  // Output the CRC as an uppercase hexadecimal string
        $after = substr($data_tmp, 2, 2);
        $before = substr($data_tmp, 0, 2);
        return $after . $before;
    }

    protected function detect_if_just_end_vote()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $game_info = unserialize($result['game_info']);

        return $game_info;
    }

    //如果存在相同的hardware_id则直接返回该玩家的座位号,否则返回false
    protected function detect_if_hardware_id_exist($hardware_id, $all_user)
    {
        foreach ($all_user as $user) {
            if ($user['hardware_id'] == $hardware_id) {
                return $user['game_id'];
            }
        }
        return false;
    }

    //存储指定座位号的玩家的hardware_id
    protected function save_player_hardware_id(&$all_user, $hardware_id, $game_id)
    {
        foreach ($all_user as $k => $user) {
            if ($user['game_id'] == $game_id) {
                $all_user[$k]['hardware_id'] = $hardware_id;
                break;
            }
        }
    }


    public function onMessage($connection, $data)
    {
        $transform_data = $data;

        if ($data == 'EDVT') {
            $connection->send("current vote round end");
            return;
        }

        if ($data == 'heart_link') {
//            $connection->send("heart_link");
            return;
        }

        switch ($transform_data['frame_header']) {
            //绑定座位号流程
            case 'FAAF':
            {
                $main_data = $transform_data['frame_header'] . $transform_data['uid'];
                $compute_crc16 = $this->crc16_verify($main_data);
                if ($compute_crc16 == $transform_data['crc16']) {

                    $game = Db::table('game')->where([
                        'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                    ])->find();
                    $game_info = unserialize($game['game_info']);
                    $all_user = unserialize($game['all_user_info']);

                    $tmp_game_id = $this->detect_if_hardware_id_exist($transform_data['uid'], $all_user);

                    if ($tmp_game_id !== false) {
                        $send_data_tmp = 'FBBF' . $transform_data['uid'] . sprintf('%02X', $tmp_game_id);
                        $send_crc16_tmp = $this->crc16_verify($send_data_tmp);

                        foreach ($this->worker->connections as $connection) {
                            $connection->send([
                                'stat' => 'ok',
                                'msg' => 'ok',
                                'game_data' => $send_data_tmp,
                                'crc16_data' => $send_crc16_tmp
                            ]);
                        }
                        return;
                    }

                    if ($game_info['game_id_index'] > $game_info['player_count']) {
                        foreach ($this->worker->connections as $connection) {
                            $connection->send([
                                'stat' => 'err',
                                'msg' => 'game_id overflow'
                            ]);
                        }

                        return;
                    }

                    $game_id = $game_info['game_id_index']++;

                    $this->save_player_hardware_id($all_user, $transform_data['uid'], $game_id);

                    $send_data = 'FBBF' . $transform_data['uid'] . sprintf('%02X', $game_id);
                    $send_crc16 = $this->crc16_verify($send_data);

                    Db::table('game')->where([
                        'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                    ])->update([
                        'game_info' => serialize($game_info),
                        'all_user_info' => serialize($all_user)
                    ]);

                    $this->uidConnections[$transform_data['uid']] = $connection;

                    foreach ($this->worker->connections as $connection) {
                        $connection->send([
                            'stat' => 'ok',
                            'msg' => 'ok',
                            'game_data' => $send_data,
                            'crc16_data' => $send_crc16
                        ]);
                    }

                    return;
                } else {

                    foreach ($this->worker->connections as $connection) {
                        $connection->send([
                            'stat' => 'err',
                            'msg' => 'compute crc16 error:' . $compute_crc16
                        ]);
                    }

                    return;
                }
                break;
            }
            //玩家操作流程
            case 'ACCA':
            {
                $compute_crc16 = $this->crc16_verify($transform_data['frame_header'] . $transform_data['player1'] . $transform_data['player2']);
                if ($compute_crc16 == $transform_data['crc16']) {

                    $ori_p = hexdec($transform_data['player1']);
                    $target_p = hexdec($transform_data['player2']);
                    $ope_after_data = $this->playersOperation($ori_p, $target_p);
                    if ($ope_after_data == 'alarm') {
                        $connection->send([
                            'stat' => 'alarm',
                            'msg' => 'alarm trigger'
                        ]);
                        return;
                    } elseif ($ope_after_data == 'none') {
                        foreach ($this->worker->connections as $connection) {
                            $connection->send([
                                'stat' => 'none',
                                'msg' => 'none operation'
                            ]);
                        }
                        return;
                    } else {
                        $ope_after_data = json_decode($ope_after_data, true);
                    }
                    $player1_stat = null;
                    $player2_stat = null;
                    $cd_time = null;
                    switch ($ope_after_data['ori']['status']) {
                        case 'alive':
                        {
                            $player1_stat = '01';
                            break;
                        }
                        case 'out':
                        {
                            $player1_stat = '00';
                            break;
                        }
                        case 'disappeared':
                        {
                            $player1_stat = '03';
                            break;
                        }
                        case 'eaten':
                        {
                            $player1_stat = '02';
                            break;
                        }
                    }
                    switch ($ope_after_data['target']['status']) {
                        case 'alive':
                        {
                            $player2_stat = '01';
                            break;
                        }
                        case 'out':
                        {
                            $player2_stat = '00';
                            break;
                        }
                        case 'disappeared':
                        {
                            $player2_stat = '03';
                            break;
                        }
                        case 'eaten':
                        {
                            $player2_stat = '02';
                            break;
                        }
                    }

                    switch ($ope_after_data['ope_type']) {
                        case 'knife':
                        case 'eat':
                            $cd_time = sprintf('%02X', 60);
                            break;
                        case 'eat_body':
                        case 'bundle':
                        case 'touch':
                            $cd_time = sprintf('%02X', 0);
                            break;
                    }

                    $game_data = $transform_data['frame_header'] . $transform_data['player1'] . $player1_stat . $cd_time . $transform_data['player2'] . $player2_stat . '00';

                    $send_crc16 = $this->crc16_verify($game_data);


                    foreach ($this->worker->connections as $connection) {
                        $connection->send([
                            'stat' => 'ok',
                            'msg' => 'ok',
                            'game_data' => $game_data,
                            'crc16_data' => $send_crc16
                        ]);
                    }

                    return;

                } else {

                    foreach ($this->worker->connections as $connection) {
                        $connection->send([
                            'stat' => 'err',
                            'msg' => 'compute crc16 error:' . $compute_crc16
                        ]);
                    }

                    return;
                }

                break;
            }
            case 'data_format_error':
            {
//                $connection->send([
//                    'stat' => 'err',
//                    'msg' => 'data_format_error'
//                ]);
                break;
            }
        }
//        Log::write('eys protocol data send suc', 'notice');
//        Log::save();
//        echo 'eys protocol data send suc';
    }

    protected function detect_if_all_sa_over($allplayers)
    {
        $if_all_player_sa_over = true;
        foreach ($allplayers as $player) {
            if ($player['settle_account_stat'] == '未结') {
                $if_all_player_sa_over = false;
                break;
            }
        }
        return $if_all_player_sa_over;
    }

    //如果$connection存在就返回hardware_id,不存在就返回false
    protected function get_target_hardware_id($connection)
    {
        foreach ($this->uidConnections as $hardware_id => $conitem) {
            if ($connection == $conitem) {
                return $hardware_id;
            }
        }
        return false;
    }

    public function onWorkerStart($worker)
    {

        //检测是否刚投票结束并给硬件发送新一轮的数据
        Timer::add(2, function () use ($worker) {
            $game_info = $this->detect_if_just_end_vote();
            if ($game_info['if_just_end_vote'] == 1) {
                foreach ($worker->connections as $connection) {
                    //假如有16位玩家,就发送16次数据
                    $result = Db::table('game')->where([
                        'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                    ])->find();

                    $alluser = unserialize($result['all_user_info']);

                    $hardware_tmp = $this->get_target_hardware_id($connection);
                    if ($hardware_tmp !== false) {
                        $game_id_tmp = $this->detect_if_hardware_id_exist($hardware_tmp, $alluser);
                        if ($game_id_tmp !== false) {
                            foreach ($alluser as $user) {
                                if ((int)$user['game_id'] == (int)$game_id_tmp) {
                                    $send_game_id = strtoupper(str_pad(dechex((int)$user['game_id']), 2, '0', STR_PAD_LEFT));
                                    $send_fresh_time = null;
                                    $send_status = null;

                                    //发送玩家状态
                                    switch ($user['status']) {
                                        case 'alive':
                                        {
                                            $send_status = '01';
                                            break;
                                        }
                                        case 'out':
                                        {
                                            $send_status = '00';
                                            break;
                                        }
                                        case 'disappeared':
                                        {
                                            $send_status = '03';
                                            break;
                                        }
                                        case 'eaten':
                                        {
                                            $send_status = '02';
                                            break;
                                        }
                                    }


                                    //根据角色发送技能刷新时间
                                    if ($this->if_role_with_knife($user['identity'])) {
                                        $send_fresh_time = strtoupper(dechex(60));
                                    } elseif ($this->if_eater_role($user['identity'])) {
                                        $send_fresh_time = strtoupper(dechex(30));
                                    } else {
                                        if ($user['bundle_player'] != null) {
                                            $bundle_player_info = null;
                                            foreach ($alluser as $user2) {
                                                if ((int)$user2['game_id'] == (int)$user['bundle_player']) {
                                                    $bundle_player_info = $user2;
                                                    break;
                                                }
                                            }
                                            if ($this->if_role_with_knife($bundle_player_info['identity'])) {
                                                $send_fresh_time = strtoupper(dechex(60));
                                            } else {
                                                $send_fresh_time = '00';
                                            }
                                        } else {
                                            $send_fresh_time = '00';
                                        }

                                    }

                                    $send_ori_data = 'ABBA' . $send_game_id . $send_fresh_time . $send_status;
                                    $crc_data = $this->crc16_verify($send_ori_data);
                                    $connection->send([
                                        'stat' => 'ok',
                                        'msg' => 'ok',
                                        'game_data' => $send_ori_data,
                                        'crc16_data' => $crc_data
                                    ]);
                                    break;
                                }
                            }
                        }
                    }


                }

                foreach ($worker->connections as $connection) {
                    $send_ori_data = 'FDDF010100';
                    $crc_data = $this->crc16_verify($send_ori_data);
                    $connection->send([
                        'stat' => 'ok',
                        'msg' => 'ok',
                        'game_data' => $send_ori_data,
                        'crc16_data' => $crc_data
                    ]);
                }

                $game_info['if_just_end_vote'] = 0;
                Db::table('game')->where([
                    'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                ])->update([
                    'game_info' => serialize($game_info)
                ]);
            }

        });

        //检测是否刚拉响警报就发送警报数据
        Timer::add(2, function () use ($worker) {
            $game_info = $this->detect_if_just_end_vote();
            if ($game_info['if_alarm'] == 1) {
                if ($game_info['if_just_alarm']) {
                    foreach ($worker->connections as $connection) {
                        $alarm_data = 'FDDF010101';
                        $crc16 = $this->crc16_verify($alarm_data);
                        $connection->send([
                            'stat' => 'ok',
                            'msg' => 'ok',
                            'game_data' => $alarm_data,
                            'crc16_data' => $crc16
                        ]);
                    }
                    $game_info['if_just_alarm'] = false;
                    Db::table('game')->where([
                        'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                    ])->update([
                        'game_info' => serialize($game_info)
                    ]);
                }
            }
        });

        //检测本局游戏是否已经结束并发送结束字符串
        Timer::add(2, function () use ($worker) {
            $game_info = $this->detect_if_just_end_vote();
            $game_stat = Db::table('game')->where([
                'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
            ])->value('status');
            if (isset($game_info['if_send_game_end']) && $game_info['if_send_game_end'] == 0 && $game_stat == '已结束') {
                foreach ($worker->connections as $connection) {
                    $connection->send('game_end');
                }
                $game_info['if_send_game_end'] = 1;
                Db::table('game')->where([
                    'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                ])->update([
                    'game_info' => serialize($game_info)
                ]);
            }
        });

        //玩家自动结账
        Timer::add(10, function () {
            $curr_bill_id = Db::table('setting')->where('id', 1)->value('current_bill_id');

            $result = Db::table('bill')->where('id', $curr_bill_id)->find();

            $allpalyers_ori = unserialize($result['players']);
            $allplayers = $allpalyers_ori['join_players'];

            $sa_time_ori = Db::table('setting')->where('id', 1)->value('auto_sa_duration');
            $sa_tiem_seconds = $sa_time_ori * 60;

            $price = Db::table('setting')->where('id', 1)->value('price');
            $second_price = (float)($price / 7200);

            foreach ($allplayers as $k => $target_player) {
                if ($target_player['recently_game_timestamp'] != null) {
                    if ($target_player['join_new_game_timestamp'] < $target_player['recently_game_timestamp'] && $target_player['recently_game_timestamp'] + $sa_tiem_seconds < time()) {
                        if ($target_player['settle_account_stat'] == '未结') {
                            $allplayers[$k]['curr_consume'] = number_format(((time() - $target_player['join_timestamp']) * $second_price), 2);
                            $allplayers[$k]['settle_account_stat'] = '已结';
                            Db::table('users')->where('id', (int)$target_player['player_id'])->dec('remainder', round((float)str_replace(',', '', $allplayers[$k]['curr_consume'])))->update();
                            $allplayers[$k]['remainder'] = Db::table('users')->where('id', (int)$target_player['player_id'])->value('remainder');
                            $allplayers[$k]['settle_account_timestamp'] = time();
                        }
                    }
                }
            }

            $allpalyers_ori['join_players'] = $allplayers;
            Db::table('bill')->where('id', $curr_bill_id)->update([
                'players' => serialize($allpalyers_ori),
                'if_all_over' => ($this->detect_if_all_sa_over($allplayers) ? 1 : 0)
            ]);

        });
    }
}
