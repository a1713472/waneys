<?php
declare (strict_types=1);

namespace app\admin\controller;

use app\BaseController;
use think\captcha\facade\Captcha;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use think\facade\Cookie;

class Index extends BaseController
{
    public function login()
    {

        return View::fetch('index/login');
    }

    public function getlogininfo()
    {
        $name = $this->request->param('id');
        $psw = $this->request->param('psw');
//        $ct = $this->request->param('captcha');

        $result = $this->getUser($name, $psw);


        return $result;
    }

    protected function getUser($userid, $user_password)
    {

//        if (!Captcha::check($ct)) {
//            return 'ct_err';
//        };

        try {
            $result = Db::table('admin_user')->where([
                'adm_name' => $userid
            ])->findOrFail();
        } catch (\Exception $ex) {
            return 'name_err';
        }

        if (password_verify($user_password, $result['adm_psw'])) {
            Session::set('adm_login_suc', $userid);

            return 'suc';

        } else {

            return 'psr_err';
        }
    }

    public function mainpage()
    {
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_identity = Db::table('setting')->where('id', 1)->value('roles');


        return View::fetch('index/mainpage', [
            'if_game_ready' => $game == null ? 'false' : 'true',
            'all_identity' => $all_identity,
            'all_player_info' => $game == null ? 'null' : json_encode(unserialize($game['all_user_info']))
        ]);
    }

    public function settleAccountsPage()
    {
        $setting_result = Db::table('setting')->where('id', 1)->find();

        return View::fetch('index/sap', [
            'username' => Session::get('adm_login_suc'),
            'tow_hours_price' => $setting_result['price'],
            'auto_sa_time' => $setting_result['auto_sa_duration']
        ]);
    }

    public function createNewGame()
    {
        try {
            $curr_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            if ($curr_id != null && Db::table('game')->where('id', $curr_id)->value('status') != '已结束') {
                return 'exist_gaming';
            }
            $player_count = (int)$this->request->param('player_count');
            $psw = $this->request->param('join_psw');
            $game_info = [
                'player_count' => $player_count,
                'vote_time_limit' => 0,
                'vote_result' => [],
                'if_start_vote' => 2,
                'winner' => null,
                'bantalk_player' => [],
                'prisoner' => [],
                'if_all_bantalk' => false,
                'if_allow_use_skill' => 0,
                'game_start_time' => null,
                'new_round_time' => null,
                'if_alarm' => 0,
                'alarm_round' => null,
                'mini_task_score' => 0,
                'game_id_index' => 1,
                'if_just_end_vote' => 0,
                'player_info_image' => [],
                'if_just_alarm' => false,
                'if_send_game_end' => 0
            ];
            $id = Db::table('game')->insertGetId([
                'all_user_info' => serialize([]),
                'deny_users' => serialize([]),
                'reviewed_users' => serialize([]),
                'create_time' => date('Y-m-d H:i:s'),
                'game_info' => serialize($game_info),
                'join_psw' => $psw
            ]);
            Db::table('setting')->where('id', 1)->update([
                'current_game_id' => $id
            ]);
            return json()->data([
                'stat' => 'suc',
                'game_id' => $id
            ]);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

    }

    protected function sessionTest()
    {
//        Session::set('name1', 'thinkphp1');
//        Session::set('name2', 'thinkphp2');

//        // 获取当前session
//        $session = session();
//
//        // 遍历session中的数据
//        foreach ($session as $key => $value) {
//            echo "Key: " . $key . ", Value: " . $value . "<br>";
//        }
        Cookie::set('name', 'value');
    }

    public function queryPlayerCount()
    {
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        $all_player_info = unserialize($game['all_user_info']);
        $player_count = count($all_player_info);

        if ($player_count == (unserialize($game['game_info']))['player_count']) {
            $if_all_player_number_ready = true;
            foreach ($all_player_info as $item) {
                if ($item['game_id'] === null) {
                    $if_all_player_number_ready = false;
                    break;
                }
            }
            if ($if_all_player_number_ready) {
                return json()->data([
                    'player_count' => $player_count,
                    'if_all_ready' => 'true',
                    'player_info' => $all_player_info
                ]);
            } else {
                return json()->data([
                    'player_count' => $player_count,
                    'if_all_ready' => 'false'
                ]);
            }

        } else {
            return json()->data([
                'player_count' => $player_count,
                'if_all_ready' => 'false'
            ]);
        }
    }

    protected function confirm_identity_vote(&$users)
    {
        foreach ($users as $k => $user) {
            if (in_array($user['identity'], ['猎鹰', '鹈鹕', '鹦鹉', '乌鸦'])) {
                $users[$k]['vote_count'] = 0;
            } elseif (in_array($user['identity'], ['传教士', '政治家'])) {
                $users[$k]['vote_count'] = 2;
            } else {
                $users[$k]['vote_count'] = 1;
            }
        }
    }

    public function confirmIdentity()
    {
        $data = $this->request->param('result');
        $identity_data = json_decode($data, true);
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        if ($game['status'] == '游戏中') {
            return 'gaming';
        } elseif ($game['status'] == '已结束') {
            return 'game_end';
        }

        $users = unserialize($game['all_user_info']);
        for ($j = 0; $j < count($users); $j++) {
            for ($i = 0; $i < count($identity_data); $i++) {
                if ((int)($identity_data[$i][0]) === (int)($users[$j]['game_id'])) {
                    $users[$j]['group'] = $identity_data[$i][1];
                    $users[$j]['identity'] = $identity_data[$i][2];
                    break;
                }
            }
        }

        $this->confirm_identity_vote($users);

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id'),
            'status' => '等待中'
        ])->update([
            'all_user_info' => serialize($users)
        ]);
        if ($result == 1) {
            return 'suc';
        } else {
            return 'fail';
        }
    }

    public function confirmVote()
    {
        $vote_data = json_decode($this->request->param('result'), true);
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($game['status'] == '已结束') {
            return 'game_end';
        }

        $users = unserialize($game['all_user_info']);
        for ($j = 0; $j < count($users); $j++) {
            for ($i = 0; $i < count($vote_data); $i++) {
                if ((int)($vote_data[$i][0]) === (int)($users[$j]['game_id'])) {
                    $users[$j]['vote_count'] = (int)$vote_data[$i][1];
                    break;
                }
            }
        }
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->update([
            'all_user_info' => serialize($users)
        ]);
        if ($result == 1) {
            return 'suc';
        } else {
            return 'fail';
        }
    }

    public function gameStart()
    {
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($game['status'] == '游戏中') {
            return 'gaming';
        }

        if ($game['status'] == '已结束') {
            return 'game_end';
        }

        $users = unserialize($game['all_user_info']);

        $game_info = unserialize($game['game_info']);

        $game_info['if_start_vote'] = 3;

        if (count($users) == 0) {
            return 'none_player';
        }

        $if_all_game_id_exist = true;
        foreach ($users as $k => $item) {
            if ($item['game_id'] === null) {
                $if_all_game_id_exist = false;
                break;
            }
        }

        if (!$if_all_game_id_exist) {
            return 'game_id_not_ready';
        }

        $if_all_identity_exist = true;
        foreach ($users as $k => $item) {
            if ($item['group'] == null || $item['identity'] == null) {
                $if_all_identity_exist = false;
                break;
            }
        }
        if ($if_all_identity_exist) {
            //可以开始游戏的情况
            $curr_time = time();
            $game_info['game_start_time'] = $curr_time;
            $game_info['new_round_time'] = $curr_time;
            $game_info['player_info_image'] = $users;
            $result = Db::table('game')->where([
                'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
            ])->update([
                'status' => '游戏中',
                'game_info' => serialize($game_info)
            ]);
            if ($result == 1) {
                return 'suc';
            } else {
                return 'started';
            }

        } else {
            return 'fail';
        }
    }

    public function voteTimeSet()
    {
        try {
            $time = (int)$this->request->param('time_data');
            $result = Db::table('game')->where([
                'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
            ])->find();

            if ($result === null) {
                return 'game_not_exist';
            } else {

                $game_info = unserialize($result['game_info']);
                $game_info['vote_time_limit'] = $time;
                Db::table('game')->where([
                    'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                ])->update([
                    'game_info' => serialize($game_info)
                ]);
                return 'suc';
            }
        } catch (\Exception $exception) {
            return 'fail';
        }
    }

    public function queryVoteTimeSetting()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($result === null) {
            return 'game_not_exist';
        } else {
            return (unserialize($result['game_info']))['vote_time_limit'];
        }
    }

    protected function update_player_total_score($game, $winner)
    {
        $players = unserialize($game['all_user_info']);
        $score = null;
        switch ($winner) {
            case '鹅':
            {
                $score = Db::table('setting')->where([
                    'id' => 1
                ])->value('e_win_score');
                break;
            }
            case '鸭':
            {
                $score = Db::table('setting')->where([
                    'id' => 1
                ])->value('ya_win_score');
                break;
            }
            case '秃鹫':
            case '呆呆鸟':
            case '鹦鹉':
            case '决斗呆呆鸟':
            case '猎鹰':
            case '鹈鹕':
            case '乌鸦':
            case '和平鸽':
                $score = Db::table('setting')->where([
                    'id' => 1
                ])->value('zhongli_win_score');
                break;
            case '恋人':
                $score = 1000;
                break;
        }
        if ($winner == '鹅' || $winner == '鸭') {
            foreach ($players as $player) {
                if ($player['group'] == $winner) {
                    Db::table('users')->where([
                        'id' => $player['user_id']
                    ])->inc('total_score', $score)->update();
                }
            }
        } else {
            foreach ($players as $player) {
                if ($player['identity'] == $winner) {
                    Db::table('users')->where([
                        'id' => $player['user_id']
                    ])->inc('total_score', $score)->update();
                }
            }
        }
    }

    protected function update_player_end_game_timestamp($users)
    {
        $id_arr = [];
        foreach ($users as $user) {
            $id_arr[] = $user['user_id'];
        }
        $bill_result = Db::table('bill')->where('id', Db::table('setting')->where('id', 1)->value('current_bill_id'))->find();
        $allplayers_ori = unserialize($bill_result['players']);
        $allplayers = $allplayers_ori['join_players'];
        $curr_timestamp = time();
        foreach ($allplayers as $k => $player) {
            if (in_array($player['player_id'], $id_arr)) {
                $allplayers[$k]['recently_game_timestamp'] = $curr_timestamp;
            }
        }

        $allplayers_ori['join_players'] = $allplayers;
        Db::table('bill')->where('id', Db::table('setting')->where('id', 1)->value('current_bill_id'))->update([
            'players' => serialize($allplayers_ori)
        ]);
    }

    public function endGame()
    {
        if (Db::table('game')->where([
                'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
            ])->value('status') == '已结束') {
            return 'have_end';
        }
        $winner = $this->request->param('winner');
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        $game_info = unserialize($result['game_info']);
        $game_info['winner'] = $winner;
        Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->update([
            'status' => '已结束',
            'game_info' => serialize($game_info)
        ]);
        $this->update_player_total_score($result, $winner);
        $this->update_player_end_game_timestamp(unserialize($result['all_user_info']));
        return 'end_suc';
    }

    public function queryGameLiveData()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        if ($result == null) {
            return 'game_not_exist';
        } else {
            $stat = $result['status'];
            $vote_round_count = 0;
            $voted_player_count = 0;
            $out_player_count = 0;
            $all_alive_count = 0;

            $game_info = unserialize($result['game_info']);
            $all_user = unserialize($result['all_user_info']);

            foreach ($game_info['vote_result'] as $item) {
                if ($item['if_this_round_end'] == true) {
                    $vote_round_count++;
                }
            }

            if ($game_info['if_start_vote'] == 0) {
                foreach ($game_info['vote_result'] as $item) {
                    if ($item['if_this_round_end'] == false) {
                        $voted_player_count = count($item['vote_detail']);
                    }
                }
            }
            $player_info = unserialize($result['all_user_info']);
            foreach ($player_info as $item) {
                if ($item['status'] == 'out' || $item['status'] == 'disappeared') {
                    $out_player_count++;
                } else {
                    if ($item['status'] == 'alive' || $item['status'] == 'eaten') {
                        $all_alive_count++;
                    }
                }
            }

            $number_ready = 0;
            foreach ($player_info as $item) {
                if ($item['game_id'] !== null) {
                    $number_ready++;
                }
            }

            //已禁言玩家
            $bantalk_str = '';
            if ($game_info['if_all_bantalk']) {
                $bantalk_str = '全员';
            } else {
                foreach ($game_info['bantalk_player'] as $item) {
                    $bantalk_str .= ($item . '号  ');
                }
            }

            //已监禁玩家
            $prison_str = '';
            foreach ($game_info['prisoner'] as $item) {
                $prison_str .= ($item . '号  ');
            }

            //实时监测已出局玩家并给后台弹窗提示
            $out_player_arr = [];
            if ($game_info['if_start_vote'] != 2) {
                $image = $game_info['player_info_image'];
                foreach ($player_info as $k => $player) {
                    if ($player['status'] == 'out' && $image[$k]['status'] == 'alive') {
                        $out_player_arr[] = $player['game_id'];
                    }
                }
                $game_info['player_info_image'] = $player_info;

                Db::table('game')->where([
                    'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                ])->update([
                    'game_info' => serialize($game_info)
                ]);
            }


            
           // ... existing code ...

// 初始化变量
$baobiao_bundle = [];
$baozhawang_bundle = [];
$meipo_bundle = [];

foreach ($all_user as $user) {
    if (isset($user['skill_data']['target_bundle_player'])) {
        if ($user['identity'] == '保镖') {
            $baobiao_bundle[] = $user['skill_data']['target_bundle_player']; // 使用[]来添加
        }   
        if ($user['identity'] == '爆炸王') {
            $baozhawang_bundle[] = $user['skill_data']['target_bundle_player'];
        }
        if ($user['identity'] == '喜鹊') {
            $meipo_bundle[] = $user['skill_data']['target_bundle_player'];
        }
    }
}


// 将数组转换为字符串，格式为 "X号玩家, Y号玩家, ..."
$baobiao_bundle_str = is_array($baobiao_bundle) && count($baobiao_bundle) > 0 
    ? implode('号玩家, ', $baobiao_bundle) . '号玩家' 
    : '';
$baozhawang_bundle_str = is_array($baozhawang_bundle) && count($baozhawang_bundle) > 0 
    ? implode('号玩家, ', $baozhawang_bundle) . '号玩家' 
    : '';
$meipo_bundle_str = is_array($meipo_bundle) && count($meipo_bundle) > 0 
    ? implode('号玩家, ', $meipo_bundle) . '号玩家' 
    : '';


return json()->data([
    'stat' => $stat,
    'vote_round_count' => $vote_round_count,
    'voted_player_count' => $voted_player_count,
    'out_player_count' => $out_player_count,
    'alive_player_count' => $all_alive_count,
    'ready_number' => $number_ready,
    'all_player_count' => $game_info['player_count'],
    'bantalk_str' => $bantalk_str,
    'prison_str' => $prison_str,
    'e_mini_task_score' => $game_info['mini_task_score'],
    'alert_out_player' => $out_player_arr,
    'baobiao_bundle' => $baobiao_bundle_str,
    'meipo_bundle' => $meipo_bundle_str,
    'baozhawang_bundle' => $baozhawang_bundle_str
]);

// ... existing code ...

        }
    }

    public function startVote()
    {

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($result['status'] != '游戏中') {
            return 'not_gaming';
        }

        $game_info = unserialize($result['game_info']);
        if ($game_info['if_start_vote'] == 0) {
            return 'have_vote';
        }
        $game_info['if_start_vote'] = 0;
        $temp = [
            'out' => null,
            'if_this_round_end' => false,
            'vote_detail' => []
        ];
        $game_info['vote_result'][] = $temp;
        Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->update([
            'game_info' => serialize($game_info)
        ]);
        return 'start_suc';
    }

    //重置醉酒鸭技能效果
    protected function reset_zuijiuya_skill_effect(&$all_user)
    {
        foreach ($all_user as $k => $user) {
            if ($user['identity'] == '醉酒鸭') {
                $all_user[$k]['skill_data']['zuijiuya_target_id_arr'] = [];
            }
        }
    }

    public function endVote()
    {

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        $game_info = unserialize($result['game_info']);
        $player_info = unserialize($result['all_user_info']);

        if ($result['status'] != '游戏中') {
            return 'not_gaming';
        }

        if ($game_info['if_start_vote'] == 1) {
            return 'have_voted_end';
        }
        $game_info['if_start_vote'] = 1;

        $detail = [];
        $out_game_id = null;
        $filter_arr = [];
        foreach ($game_info['vote_result'] as $key => $item) {
            if ($item['if_this_round_end'] === false) {
                $game_info['vote_result'][$key]['if_this_round_end'] = true;
                foreach ($item['vote_detail'] as $key2 => $vote_item) {

                    if (array_key_exists((int)($vote_item['vote_to']), $detail)) {
                        $detail[(int)($vote_item['vote_to'])] += (int)($vote_item['vote_count']);
                    } else {
                        $detail[(int)($vote_item['vote_to'])] = (int)($vote_item['vote_count']);
                    }
                }

                $max_vote_count = 0;

                foreach ($detail as $k => $detail_item) {
                    if ($detail_item > $max_vote_count) {
                        $max_vote_count = $detail_item;
                        $out_game_id = (int)$k;
                    }
                }
                $filter_arr = array_filter($detail, function ($v) use ($max_vote_count) {
                    return (int)$v == (int)$max_vote_count;
                });

                if (count($filter_arr) > 1) {
                    $game_info['vote_result'][$key]['out'] = null;
                } else {
                    $game_info['vote_result'][$key]['out'] = $out_game_id;
                }

            }
        }
        if ($out_game_id !== null && count($filter_arr) == 1) {
            foreach ($player_info as $k => $item) {
                if ((int)($item['game_id']) == $out_game_id) {
                    $player_info[$k]['status'] = 'out';
                    break;
                }
            }
        }

        //被禁言玩家解禁
        $game_info['bantalk_player'] = [];

        //玩家可以发动技能状态重置
        foreach ($player_info as $k => $item) {
            $player_info[$k]['if_use_skill'] = 0;
            $player_info[$k]['use_skill_time'] = null;
        }

        //全员禁言解禁
        $game_info['if_all_bantalk'] = false;

        //新轮次时间记录
        $game_info['new_round_time'] = time();

        //被吃角色的状态更新
        $this->detect_eater_and_update_eat_target($player_info, $game_info);

        //更新绑定角色状态
        update_if_bundle_and_all_die($player_info, $game_info);

        //检测警报角色被杀就拉响警报
        detect_alarm_role_status_and_send_command($player_info, $game_info);

        //更新新轮次变量
        $this->update_new_round_var_func($player_info, $game_info);

        //更新投票刚刚结束变量
        $game_info['if_just_end_vote'] = 1;

        //重置醉酒鸭技能效果
        $this->reset_zuijiuya_skill_effect($player_info);

        Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->update([
            'all_user_info' => serialize($player_info),
            'game_info' => serialize($game_info)
        ]);


        return 'end_suc';
    }

    //更新新轮次变量函数
    protected function update_new_round_var_func(&$all_user, &$game_info)
    {
        $this->update_eat_body_rd_count($all_user);
        if (!detect_if_alarm($game_info)) {
            $game_info['if_alarm'] = 0;
        }
    }

    //初始化新轮次吃尸体秃鹫的吃尸体量
    protected function update_eat_body_rd_count(&$all_user)
    {
        $eat_roles = json_decode(Db::table('setting')->where('id', 1)->value('eat_body_roles'), true);

        foreach ($all_user as $k => $user) {
            if (in_array($user['identity'], $eat_roles) && $user['identity'] == '秃鹫') {
                $all_user[$k]['rd_body_count'] = 0;
                break;
            }
        }
    }

    //判断吃人玩家的生死状态并更新被吃玩家的状态
    protected function detect_eater_and_update_eat_target(&$all_user, $game_info)
    {
        $eat_roles = json_decode(Db::table('setting')->where('id', 1)->value('eater_roles'), true);

        foreach ($all_user as $user) {
            if (in_array($user['identity'], $eat_roles)) {
                if ($user['status'] == 'alive') {
                    foreach ($all_user as $k => $user2) {
                        if ($user2['status'] == 'eaten') {
                            $all_user[$k]['status'] = 'out';
                            $curr_round = count(array_filter($game_info['vote_result'], function ($v) {
                                if (isset($v['if_this_round_end'])) {
                                    return $v['if_this_round_end'] == true;
                                } else {
                                    return false;
                                }
                            }));
                            $all_user[$k]['out_round'] = $curr_round;
                        }
                    }
                } else if ($user['status'] == 'out') {
                    foreach ($all_user as $k => $user2) {
                        if ($user2['status'] == 'eaten') {
                            $all_user[$k]['status'] = 'alive';
                        }
                    }
                }
            }
        }
    }

    public function queryJoinGameData()
    {
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        return (unserialize($game['game_info']))['player_count'];
    }

    public function getAllPlayerInfo()
    {
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($game['status'] == '游戏中') {
            return json()->data(unserialize($game['all_user_info']));
        } elseif ($game['status'] == '已结束') {
            return 'have_end';
        } elseif ($game['status'] == '等待中') {
            return 'at_wait';
        }
    }

    public function queryWinnerScore()
    {
        return json()->data([
            'es' => Db::table('setting')->where('id', 1)->value('e_win_score'),
            'ys' => Db::table('setting')->where('id', 1)->value('ya_win_score'),
            'zs' => Db::table('setting')->where('id', 1)->value('zhongli_win_score'),
            'mn_task' => Db::table('setting')->where('id', 1)->value('e_task_win_score')
        ]);
    }

    public function updateWinnerScore()
    {
        try {
            Db::table('setting')->where('id', 1)->update([
                'e_win_score' => (int)($this->request->param('es')),
                'ya_win_score' => (int)($this->request->param('ys')),
                'zhongli_win_score' => (int)($this->request->param('zs')),
                'e_task_win_score' => (int)($this->request->param('mn_task'))
            ]);
            return 'suc';
        } catch (\Exception $exception) {
            return json()->data($exception);
        }
    }

    public function hidePlayer()
    {
        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        $hide_target = $this->request->param('game_id');
        $all_player = unserialize($game['all_user_info']);
        foreach ($all_player as $k => $item) {
            if ((int)$hide_target == (int)($item['game_id'])) {
                if ($item['status'] == 'out') {
                    $all_player[$k]['status'] = 'alive';
                } else {
                    $all_player[$k]['status'] = 'out';
                }
                Db::table('game')->where([
                    'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
                ])->update([
                    'all_user_info' => serialize($all_player)
                ]);
                return 'suc';
            }
        }

        return 'target_player_not_exist';
    }

    public function passPlayerJoinGame()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($game['status'] != '等待中') {
            return 'game_not_in_wait';
        }

        $user_id = (int)$this->request->param('user_id');
        $user_name = $this->request->param('user_name');
        $all_player = unserialize($game['all_user_info']);
        $game_info = unserialize($game['game_info']);
        $reviewed_users = unserialize($game['reviewed_users']);

        $filter_reviewed_users = array_filter($reviewed_users, function ($v) use ($user_id) {
            return $user_id != (int)$v;
        });
        if (count($all_player) < $game_info['player_count']) {
            $player_info = [
                'user_id' => $user_id,            //用户id
                'username' => $user_name,         //用户名
                'game_id' => null,             //游戏中的号牌
                'group' => null,            //阵营
                'identity' => null,      //身份
                'vote_count' => 1,          //投票次数
                'status' => 'alive',        //状态
            ];
            $all_player[] = $player_info;
            Db::table('game')->where('id', $game_id)->update([
                'all_user_info' => serialize($all_player),
                'reviewed_users' => serialize($filter_reviewed_users)
            ]);
            return 'suc';
        } else {
            return 'player_full';
        }
    }

    public function refusePlayerJoinGame()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($game['status'] != '等待中') {
            return 'game_not_in_wait';
        }

        $user_id = (int)$this->request->param('user_id');

        $reviewed_users = unserialize($game['reviewed_users']);
        $deny_users = unserialize($game['deny_users']);
        $filter_reviewed_users = array_filter($reviewed_users, function ($v) use ($user_id) {
            return $user_id != (int)$v;
        });

        $deny_users[] = $user_id;

        Db::table('game')->where('id', $game_id)->update([
            'reviewed_users' => serialize($filter_reviewed_users),
            'deny_users' => serialize($deny_users)
        ]);
        return 'suc';

    }

    public function queryReviewedData()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        if ($game['status'] != '等待中') {
            return 'game_not_in_wait';
        } else {
            $result = [];
            $reviewed_users = unserialize($game['reviewed_users']);
            foreach ($reviewed_users as $item) {
                $tmp = [];
                $tmp['username'] = Db::table('users')->where('id', $item)->value('user_name');
                $tmp['userid'] = $item;
                $result[] = $tmp;
            }
            return json()->data($result);
        }
    }

    public function queryJoinPlayer()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        return json()->data(unserialize($game['all_user_info']));
    }

    public function queryPlayerSkill()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        return json()->data(unserialize($game['all_user_info']));
    }

    public function allowUseSkill()
    {
        try {
            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

            $game = Db::table('game')->where([
                'id' => $game_id
            ])->find();

            if ($game['status'] !== '游戏中') {
                return json()->data('fail');
            }

            $game_info = unserialize($game['game_info']);
            $game_info['if_allow_use_skill'] = 1;

            Db::table('game')->where([
                'id' => $game_id
            ])->update([
                'game_info' => serialize($game_info)
            ]);

            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function disAllowUseSkill()
    {
        try {
            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

            $game = Db::table('game')->where([
                'id' => $game_id
            ])->find();

            if ($game['status'] !== '游戏中') {
                return json()->data('fail');
            }

            $game_info = unserialize($game['game_info']);
            $game_info['if_allow_use_skill'] = 0;

            Db::table('game')->where([
                'id' => $game_id
            ])->update([
                'game_info' => serialize($game_info)
            ]);

            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function sendZhongliRoles()
    {
        $roles = Db::table('setting')->where('id', 1)->value('roles');

        return json()->data(json_decode($roles, true)['中立']);
    }

    public function simulateHardwareTest()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);
        return View::fetch('index/simulate_hardware_test', [
            'all_player' => json_encode($all_user),
            'game_info' => json_encode($game_info)
        ]);
    }

    public function initGameInfo()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);
        foreach ($all_user as $k => $user) {
            $all_user[$k]['out_round'] = null;
            $all_user[$k]['bundle_player'] = null;
        }
        $game_info['if_alarm'] = 0;
        $game_info['alarm_round'] = null;
        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'all_user_info' => serialize($all_user),
            'game_info' => serialize($game_info)
        ]);
        return 'suc' . time();
    }

    public function sendAllUsers()
    {
        $page = (int)$this->request->param('page');
        $limit = (int)$this->request->param('limit');
        $phone_number = $this->request->param('phone');
        $user_name = $this->request->param('username');

        if (($phone_number === '' || $phone_number === null) && ($user_name === '' || $user_name === null)) {
            $result = Db::table('users')->limit(($page - 1) * $limit, $limit)->select()->toArray();
            $count = Db::table('users')->count();
            return json()->data([
                'code' => 0,
                'msg' => 'suc',
                'count' => $count,
                'data' => $result
            ]);
        } else {
            if ($phone_number != null) {
                $result = Db::table('users')->where('user_phone', 'like', "%$phone_number%")->limit(($page - 1) * $limit, $limit)->select()->toArray();
                $count = Db::table('users')->where('user_phone', 'like', "%$phone_number%")->count();
                return json()->data([
                    'code' => 0,
                    'msg' => 'suc',
                    'count' => $count,
                    'data' => $result
                ]);
            } elseif ($user_name != null) {
                $result = Db::table('users')->where('user_name', 'like', "%$user_name%")->limit(($page - 1) * $limit, $limit)->select()->toArray();
                $count = Db::table('users')->where('user_name', 'like', "%$user_name%")->count();
                return json()->data([
                    'code' => 0,
                    'msg' => 'suc',
                    'count' => $count,
                    'data' => $result
                ]);
            }
        }
    }

    public function updateTargetUser()
    {
        $user_remainder = $this->request->param('remainder');
        $user_phone = $this->request->param('phone');
        $user_score = $this->request->param('score');
        $user_id = (int)$this->request->param('userid');
        $user_login_psw = $this->request->param('login_psw');
        $username = $this->request->param('username');

        $update_arr = [];
        if ($user_remainder !== '') {
            $update_arr['remainder'] = (float)$user_remainder;
        }
        if ($user_phone !== '') {
            $update_arr['user_phone'] = $user_phone;
        }
        if ($user_score !== '') {
            $update_arr['total_score'] = (int)$user_score;
        }
        if ($user_login_psw !== '') {
            $update_arr['user_psw'] = password_hash($user_login_psw, PASSWORD_DEFAULT);
        }
        if ($username !== '') {
            if (Db::table('users')->where('user_name', $username)->find() != null) {
                return json()->data('username_exist');
            }
            $update_arr['user_name'] = $username;
        }
        $result = Db::table('users')->where('id', $user_id)->update($update_arr);

        if ($result === 1) {
            return json()->data('suc');
        } else {
            return json()->data('data_not_modify');
        }
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

    public function sendAllBill()
    {
        $page = (int)$this->request->param('page');
        $limit = (int)$this->request->param('limit');

        $result = Db::table('bill')->order('create_time', 'desc')->limit(($page - 1) * $limit, $limit)->select()->toArray();
        $count = Db::table('bill')->count();

        return json()->data([
            'code' => 0,
            'msg' => 'suc',
            'count' => $count,
            'data' => $result
        ]);
    }

    public function sendTargetBill()
    {
        $page = (int)$this->request->param('page');
        $limit = (int)$this->request->param('limit');

        $bill_id = (int)$this->request->param('bill_id');
        $result = Db::table('bill')->where('id', $bill_id)->find();
        $all_players_ori = unserialize($result['players']);
        $all_players = $all_players_ori['join_players'];

        $price = Db::table('setting')->where('id', 1)->value('price');
        $second_price = (float)($price / 7200);

        foreach ($all_players as $k => $player) {
            $all_players[$k]['join_time'] = date('Y-m-d H:i:s', $player['join_timestamp']);
            if ($player['settle_account_stat'] == '未结') {
                $all_players[$k]['curr_consume'] = number_format(((time() - $player['join_timestamp']) * $second_price), 2);
            }
        }

        $target_players = array_slice($all_players, ($page - 1) * $limit, $limit);

        return json()->data([
            'code' => 0,
            'msg' => 'suc',
            'count' => count($all_players),
            'data' => $target_players
        ]);
    }

    public function playerSettleAccount()
    {
        try {
            $bill_id = (int)$this->request->param('bill_id');
            $user_id = (int)$this->request->param('user_id');

            $bill_result = Db::table('bill')->where('id', $bill_id)->find();

            $price = Db::table('setting')->where('id', 1)->value('price');
            $second_price = (float)($price / 7200);

            $allpalyers_ori = unserialize($bill_result['players']);
            $allplayers = $allpalyers_ori['join_players'];
            foreach ($allplayers as $k => $target_player) {
                if ($target_player['player_id'] == $user_id) {
                    if ($target_player['settle_account_stat'] == '未结') {
                        $allplayers[$k]['curr_consume'] = number_format(((time() - $target_player['join_timestamp']) * $second_price), 2);
                        $allplayers[$k]['settle_account_stat'] = '已结';
                        Db::table('users')->where('id', $user_id)->dec('remainder', round((float)str_replace(',', '', $allplayers[$k]['curr_consume'])))->update();
                        $allplayers[$k]['remainder'] = Db::table('users')->where('id', $user_id)->value('remainder');
                        $allplayers[$k]['settle_account_timestamp'] = time();
                        $allpalyers_ori['join_players'] = $allplayers;
                        Db::table('bill')->where('id', $bill_id)->update([
                            'players' => serialize($allpalyers_ori)
                        ]);
                        break;
                    } else {
                        return json()->data('sa_over');
                    }
                }
            }
            if ($this->detect_if_all_sa_over($allplayers)) {
                Db::table('bill')->where('id', $bill_id)->update([
                    'if_all_over' => 1
                ]);
            }

            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function batchSettleAccount()
    {
        try {
            $bill_id = (int)$this->request->param('bill_id');
            $player_info = json_decode($this->request->param('player_info'), true);

            $bill_result = Db::table('bill')->where('id', $bill_id)->find();

            $price = Db::table('setting')->where('id', 1)->value('price');
            $second_price = (float)($price / 7200);

            $allpalyers_ori = unserialize($bill_result['players']);
            $allplayers = $allpalyers_ori['join_players'];

            foreach ($allplayers as $k => $target_player) {
                if (in_array((int)$target_player['player_id'], $player_info['player_id'])) {
                    if ($target_player['settle_account_stat'] == '未结') {
                        $allplayers[$k]['curr_consume'] = number_format(((time() - $target_player['join_timestamp']) * $second_price), 2);
                        $allplayers[$k]['settle_account_stat'] = '已结';
                        Db::table('users')->where('id', (int)$target_player['player_id'])->dec('remainder', round((float)str_replace(',', '', $allplayers[$k]['curr_consume'])))->update();
                        $allplayers[$k]['remainder'] = Db::table('users')->where('id', (int)$target_player['player_id'])->value('remainder');
                        $allplayers[$k]['settle_account_timestamp'] = time();
                    }
                }
            }

            $allpalyers_ori['join_players'] = $allplayers;

            Db::table('bill')->where('id', $bill_id)->update([
                'players' => serialize($allpalyers_ori),
                'if_all_over' => ($this->detect_if_all_sa_over($allplayers) ? 1 : 0)
            ]);

            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function updatePrice()
    {
        try {
            Db::table('setting')->where('id', 1)->update([
                'price' => (int)($this->request->param('price'))
            ]);
            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function updateSaTime()
    {
        try {
            Db::table('setting')->where('id', 1)->update([
                'auto_sa_duration' => (int)($this->request->param('time'))
            ]);
            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function batchSetRemainderZero()
    {
        try {
            $bill_id = (int)$this->request->param('bill_id');
            $player_info = json_decode($this->request->param('player_info'), true);

            $bill_result = Db::table('bill')->where('id', $bill_id)->find();

            $allpalyers_ori = unserialize($bill_result['players']);
            $allplayers = $allpalyers_ori['join_players'];

            Db::table('users')->whereIn('id', $player_info['player_id'])->update([
                'remainder' => 0
            ]);

            foreach ($allplayers as $k => $target_player) {
                if (in_array((int)$target_player['player_id'], $player_info['player_id'])) {
                    $allplayers[$k]['remainder'] = Db::table('users')->where('id', (int)$target_player['player_id'])->value('remainder');
                }
            }

            $allpalyers_ori['join_players'] = $allplayers;

            Db::table('bill')->where('id', $bill_id)->update([
                'players' => serialize($allpalyers_ori)
            ]);

            return json()->data('suc');
        } catch (\Exception $exception) {
            return json()->data($exception->getMessage());
        }
    }

    public function deleteTwoYearsAgoBill()
    {
        $target_time = date('Y-m-d H:i:s', strtotime('-2 years'));
        $result = Db::table('bill')->whereTime('create_time', '<', $target_time)->delete();
        return json()->data($result);
    }
}
