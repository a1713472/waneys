<?php
// 应用公共文件
use think\facade\Db;

//遍历所有玩家判断是否有玩家绑定其他玩家并判定是否同时死亡,$all_user为序列化数据
function update_if_bundle_and_all_die(&$all_user, $game_info)
{
    foreach ($all_user as $k => $user) {
        if ($user['bundle_player'] !== null) {
            foreach ($all_user as $k2 => $user2) {
                if ((int)($user2['game_id']) == (int)$user['bundle_player']) {
                    if ($user['status'] == 'out' || $user2['status'] == 'out') {
                        $curr_round = count(array_filter($game_info['vote_result'], function ($v) {
                            if (isset($v['if_this_round_end'])) {
                                return $v['if_this_round_end'] == true;
                            } else {
                                return false;
                            }
                        }));
                        if ($user['out_round'] === null || $user2['out_round'] === null) {
                            $all_user[$k]['status'] = 'out';
                            $all_user[$k]['out_round'] = $curr_round;
                            $all_user[$k2]['status'] = 'out';
                            $all_user[$k2]['out_round'] = $curr_round;
                        }
                    }
                }
            }
        }
    }
}


//检测报警角色是否死亡并触发报警
function detect_alarm_role_status_and_send_command($all_user, &$game_info)
{
    $alarm_roles = json_decode(Db::table('setting')->where('id', 1)->value('alarm_roles'), true);

    foreach ($all_user as $user) {
        if (in_array($user['identity'], $alarm_roles)) {
            if ($user['status'] == 'out') {
                $curr_round = count(array_filter($game_info['vote_result'], function ($v) {
                    if (isset($v['if_this_round_end'])) {
                        return $v['if_this_round_end'] == true;
                    } else {
                        return false;
                    }
                }));

                if ((int)($user['out_round']) == $curr_round) {
                    $game_info['if_alarm'] = 1;
                    $game_info['alarm_round'] = $curr_round;
                } else {
                    $game_info['if_alarm'] = 0;
                }
            }
        }
    }
    if ($game_info['if_alarm'] == 1) {
        $game_info['if_just_alarm'] = true;
        //发送拉响警报指令
    }
}

//检测并更新警报状态,返回true表示拉响警报,返回false表示未拉响警报
function detect_if_alarm($game_info)
{

    $curr_round = count(array_filter($game_info['vote_result'], function ($v) {
        if (isset($v['if_this_round_end'])) {
            return $v['if_this_round_end'] == true;
        } else {
            return false;
        }
    }));

    $alarm_round = $game_info['alarm_round'];
    if ($game_info['if_alarm'] == 1) {
        if ($curr_round != (int)$alarm_round) {
            return false;
        } else {
            return true;
        }
    } else {
        return false;
    }
}
