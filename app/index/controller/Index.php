<?php
declare (strict_types=1);

namespace app\index\controller;

use app\BaseController;
use mysql_xdevapi\Result;
use think\event\AppInit;
use think\facade\Db;
use think\facade\Session;
use think\facade\View;
use think\route\Rule;

class Index extends BaseController
{
    public function index()
    {

    }

    public function regi()
    {
        return View::fetch('index/regi');
    }

    public function login()
    {
        return View::fetch('index/login');
    }

    public function regiNewUser()
    {
        $username = $this->request->param('id');
        $psw = $this->request->param('psw');
        $phone = $this->request->param('phone');
        $avatar = $this->request->param('avatar');  // 添加头像字段

        $id_result = Db::table('users')->where('user_name', $username)->find();

        if ($id_result === null) {
            $insert_result = Db::table('users')->insert([
                'user_name' => $username,
                'user_psw' => password_hash($psw, PASSWORD_DEFAULT),
                'user_phone' => $phone,
                'avatar' => $avatar,  // 添加头像字段到数据库
                'regi_time' => date('Y-m-d H:i:s')
            ]);
            if ($insert_result === 1) {
                return json()->data('reg_suc');
            } else {
                return json()->data('reg_fail');
            }
        } else {
            return json()->data('username_repeat');
        }
    }

    public function userLogin()
    {
        $username = $this->request->param('id');
        $psw = $this->request->param('psw');

        $id_result = Db::table('users')->where('user_name', $username)->find();

        //用户名不存在
        if ($id_result === null) {
            return json()->data('username_not_exist');
        } else {
            //登录成功的情况
            if (password_verify($psw, $id_result['user_psw'])) {
                Session::set('user_login_suc', $id_result);
                return json()->data('login_suc');
            } else {
                return json()->data('psw_wrong');
            }
        }
    }

    public function userReady()
    {
        return View::fetch('index/game_ready', [
            'game_id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ]);
    }

    public function waitGame()
    {
        return View::fetch('index/wait_game');
    }

    protected function updateRoles()
    {
        $roles = [
            '鹅' => ['大白鹅', '通灵者', '加拿大鹅', '侦探', '正义使者', '观鸟者', '殡仪员', '复仇者', '警长', '政治家', '追踪者', '模仿者', '监狱长', '恋人', '保镖', '阵营鹅', '大忽悠鹅'],

            '鸭' => ['静语者', '专业杀手', '爆炸王', '间谍', '派对狂', '连环杀手', '超能力者', '传教士', '刺客', '术士', '恋人', '食鸟鸭', '告密者', '变形鸭', '醉酒鸭', '老闭灯鸭'],

            '中立' => ['秃鹫', '呆呆鸟', '鹦鹉', '决斗呆呆鸟', '猎鹰', '鹈鹕', '乌鸦', '喜鹊', '和平鸽']
        ];
        Db::table('setting')->where('id', 1)->update([
            'roles' => json_encode($roles)
        ]);
        return 'suc';
    }

    protected function updateWithKnifeRoles()
    {
        $roles = [
            '鹅' => ['正义使者', '警长'],

            '鸭' => ['静语者', '专业杀手', '爆炸王', '间谍', '派对狂', '连环杀手', '超能力者', '传教士', '刺客', '术士', '恋人', '食鸟鸭', '告密者', '变形鸭', '醉酒鸭', '老闭灯鸭'],

            '中立' => ['猎鹰', '乌鸦']
        ];
        Db::table('setting')->where('id', 1)->update([
            'with_knife_roles' => json_encode($roles)
        ]);
        return 'knife_suc';
    }

    protected function updateEaterRoles()
    {
        $roles = ['鹈鹕'];
        Db::table('setting')->where('id', 1)->update([
            'eater_roles' => json_encode($roles)
        ]);
        return 'eater_suc';
    }

    protected function updateEatBodyRoles()
    {
        $roles = ['秃鹫', '食鸟鸭'];
        Db::table('setting')->where('id', 1)->update([
            'eat_body_roles' => json_encode($roles)
        ]);
        return 'eat_body_suc';
    }

    protected function updateAlarmRoles()
    {
        $roles = ['加拿大鹅'];
        Db::table('setting')->where('id', 1)->update([
            'alarm_roles' => json_encode($roles)
        ]);
        return 'alarm_suc';
    }

    public function updateBundleRoles()
    {
        $roles = ['恋人'];
        Db::table('setting')->where('id', 1)->update([
            'bundle_roles' => json_encode($roles)
        ]);
        return 'bundle_suc';
    }



    public function queryNewGame()
    {
        $g_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $result = Db::table('game')->where([
            'id' => $g_id,
            'status' => '等待中'
        ])->find();
        if ($result === null) {
            return redirect('/index/waitGame');
        } else {
            return redirect('/index/userReady');
        }

    }

    public function queryNewGameForAjax()
    {
        $g_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $result = Db::table('game')->where([
            'id' => $g_id,
            'status' => '等待中'
        ])->find();
        if ($result === null) {
            return 'none';
        } else {
            return 'exist';
        }
    }

    public function queryIfAtGame()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        if ($game === null) {
            return 'game_not_exist';
        } else {
            if ($game['status'] == '游戏中') {
                $if_at_game = false;
                $all_player = unserialize($game['all_user_info']);
                foreach ($all_player as $item) {
                    if ((int)$item['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
                        $if_at_game = true;
                        break;
                    }
                }
                if ($if_at_game) {
                    return 'at_game';
                } else {
                    return 'no_at_game';
                }
            } elseif ($game['status'] == '已结束') {
                return 'game_end';
            }
        }

    }

    protected function repeat_user_detect($all_user_info)
    {
        $if_exist = false;
        foreach ($all_user_info as $item) {
            if ($item['user_id'] == Session::get('user_login_suc')['id']) {
                $if_exist = true;
                break;
            }
        }
        return $if_exist;
    }

    //已拒绝返回true,未拒绝返回false
    protected function if_exist_deny_user()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id,
            'status' => '等待中'
        ])->find();

        $deny = $game['deny_users'];

        if ($deny == null) {
            return false;
        } else {
            $deny_arr = unserialize($deny);
            $if_current_user_deny = false;
            foreach ($deny_arr as $item) {
                if ((int)(Session::get('user_login_suc')['id']) == (int)$item) {
                    $if_current_user_deny = true;
                    break;
                }
            }
            return $if_current_user_deny;
        }
    }

    //已存在返回true,未存在返回false
    protected function if_exist_reviewed_user()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id,
            'status' => '等待中'
        ])->find();

        $reviewed = $game['reviewed_users'];

        if ($reviewed == null) {
            return false;
        } else {
            $reviewed_arr = unserialize($reviewed);
            $if_current_user_reviewed_exist = false;
            foreach ($reviewed_arr as $item) {
                if ((int)(Session::get('user_login_suc')['id']) == (int)$item) {
                    $if_current_user_reviewed_exist = true;
                    break;
                }
            }
            return $if_current_user_reviewed_exist;
        }
    }

    //从玩家进入房间后记录玩家的账单
    protected function register_player_on_bill($user)
    {
        $curr_bill_id = Db::table('setting')->where('id', 1)->value('current_bill_id');
        $curr_timestamp = time();

        if ($curr_bill_id == null) {
            $players = [];
            $players['join_players'] = [];
            $tmp = [];
            $tmp['player_id'] = $user['id'];
            $tmp['player_name'] = $user['user_name'];
            $tmp['player_phone'] = $user['user_phone'];
            $tmp['join_timestamp'] = $curr_timestamp;
            $tmp['join_new_game_timestamp'] = $curr_timestamp;
            $tmp['remainder'] = $user['remainder'];
            $tmp['settle_account_stat'] = '未结';
            $tmp['curr_consume'] = 0;
            $tmp['recently_game_timestamp'] = null;
            $tmp['settle_account_timestamp'] = null;
            $tmp['if_view_bill'] = 0;
            $players['join_players'][] = $tmp;

            $curr_bill_id_new = Db::table('bill')->insertGetId([
                'players' => serialize($players),
                'create_time' => date('Y-m-d H:i:s')
            ]);

            Db::table('setting')->where('id', 1)->update([
                'current_bill_id' => $curr_bill_id_new
            ]);

            //已存在bill的id的情况
        } else {
            $curr_bill = Db::table('bill')->where('id', $curr_bill_id)->find();
            //如果当前账单未结账
            if ($curr_bill['if_all_over'] == 0) {
                $players = unserialize($curr_bill['players']);
                $join_players = $players['join_players'];

                //判断用户是否在账单里面
                $if_user_exist = false;
                foreach ($join_players as $k => $join_player) {

                    //如果用户在本次账单里面
                    if ($join_player['player_id'] == $user['id']) {
                        $join_time = time();
                        if ($join_player['settle_account_stat'] == '已结') {
                            $join_players[$k]['join_timestamp'] = $join_time;
                            $join_players[$k]['join_new_game_timestamp'] = $join_time;
                            $join_players[$k]['settle_account_stat'] = '未结';

                        } elseif ($join_player['settle_account_stat'] == '未结') {
                            $join_players[$k]['join_new_game_timestamp'] = $join_time;
                        }

                        $if_user_exist = true;

                        $players['join_players'] = $join_players;
                        break;
                    }
                }

                //用户不在账单里面的情况
                if (!$if_user_exist) {
                    $join_time = time();
                    $tmp = [];
                    $tmp['player_id'] = $user['id'];
                    $tmp['player_name'] = $user['user_name'];
                    $tmp['player_phone'] = $user['user_phone'];
                    $tmp['join_timestamp'] = $join_time;
                    $tmp['join_new_game_timestamp'] = $join_time;
                    $tmp['remainder'] = $user['remainder'];
                    $tmp['settle_account_stat'] = '未结';
                    $tmp['curr_consume'] = 0;
                    $tmp['recently_game_timestamp'] = null;
                    $tmp['settle_account_timestamp'] = null;
                    $tmp['if_view_bill'] = 0;
                    $players['join_players'][] = $tmp;

                }

                Db::table('bill')->where('id', $curr_bill_id)->update([
                    'players' => serialize($players)
                ]);

                //如果当前账单已结账
            } elseif ($curr_bill['if_all_over'] == 1) {
                $join_time = time();
                $players = [];
                $players['join_players'] = [];
                $tmp = [];
                $tmp['player_id'] = $user['id'];
                $tmp['player_name'] = $user['user_name'];
                $tmp['player_phone'] = $user['user_phone'];
                $tmp['join_timestamp'] = $join_time;
                $tmp['join_new_game_timestamp'] = $join_time;
                $tmp['remainder'] = $user['remainder'];
                $tmp['settle_account_stat'] = '未结';
                $tmp['curr_consume'] = 0;
                $tmp['recently_game_timestamp'] = null;
                $tmp['settle_account_timestamp'] = null;
                $tmp['if_view_bill'] = 0;
                $players['join_players'][] = $tmp;

                $curr_bill_id_new = Db::table('bill')->insertGetId([
                    'players' => serialize($players),
                    'create_time' => date('Y-m-d H:i:s')
                ]);

                Db::table('setting')->where('id', 1)->update([
                    'current_bill_id' => $curr_bill_id_new
                ]);
            }
        }

    }

    public function joinTheGame()
    {
        try {


            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            $user = Db::table('users')->where('id', (Session::get('user_login_suc'))['id'])->find();
            $game = Db::table('game')->where([
                'id' => $game_id,
                'status' => '等待中'
            ])->findOrFail();

            $all_player_info = unserialize($game['all_user_info']);
            $user_id = (int)(Session::get('user_login_suc')['id']);
            $user_name = Session::get('user_login_suc')['user_name'];
//            if ($this->if_exist_deny_user()) {
//                return 'has_refused';
//            }

//            if ($this->if_exist_reviewed_user()) {
//                return 'has_reviewed';
//            }
            $psw = $this->request->param('join_psw');

            if ($psw != $game['join_psw']) {
                return 'psw_error';
            }

            if ($this->repeat_user_detect($all_player_info)) {
                return 'repeat';
            }


            $game_info = unserialize($game['game_info']);
            $player_count = count($all_player_info);
            if ($player_count < (int)($game_info['player_count'])) {
                $player_info = [
                    'user_id' => $user_id,            //用户id
                    'username' => $user_name,         //用户名
                    'game_id' => null,             //游戏中的号牌
                    'group' => null,            //阵营
                    'identity' => null,      //身份
                    'vote_count' => 1,          //投票次数
                    'status' => 'alive',        //状态
                    'if_use_skill' => 0,           //是否已使用技能
                    'skill_data' => [],
                    'use_killing_time' => null,
                    'bundle_player' => null,
                    'use_eating_time' => null,
                    'eat_body_count' => 0,
                    'rd_body_count' => 0,
                    'out_round' => null,
                    'use_skill_time' => null,
                    'hardware_id' => null
                ];
                $all_player_info[] = $player_info;
                Db::table('game')->where('id', $game_id)->update([
                    'all_user_info' => serialize($all_player_info)
                ]);
//                $reviewed_arr = [];
//                $reviewed = $game['reviewed_users'];
//                if ($reviewed == null) {
//                    $reviewed_arr[] = Session::get('user_login_suc')['id'];
//                } else {
//                    $reviewed_arr = unserialize($reviewed);
//                    $reviewed_arr[] = Session::get('user_login_suc')['id'];
//                }

                $this->register_player_on_bill($user);
                return 'suc';
            } else {
                return 'fail';
            }


        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

    }

    public function freshGameStat()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = Db::table('game')->where('id', $game_id)->find();
        $all_player_info = unserialize($game['all_user_info']);
        $game_info = unserialize($game['game_info']);
        $player_count = count($all_player_info);
        return json()->data([
            'all' => $game_info['player_count'],
            'exist' => $player_count,
            'if_all_player_ready' => $player_count == (int)($game_info['player_count']) ? 'true' : 'false'
        ]);
    }

    public function queryRankingData()
    {
        $top_users = Db::table('users')->order('total_score', 'desc')->limit(0, 30)->select()->toArray();
        return json()->data($top_users);
    }

    public function gamePage()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = Db::table('game')->where('id', $game_id)->find();

        $all_player = unserialize($game['all_user_info']);

        $if_player_in_game = false;

        foreach ($all_player as $item) {
            if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                $if_player_in_game = true;
                break;
            }
        }

        if (!$if_player_in_game) {
            return redirect('/index/waitGame');
        }

        $group = null;
        $identity = null;
        $game_number = null;
        $out_arr = [];
        foreach ($all_player as $k => $item) {
            if ((int)$item['user_id'] === (int)(Session::get('user_login_suc')['id'])) {
                $game_number = $item['game_id'];
                $group = $item['group'];
                $identity = $item['identity'];

            }
            if ($item['status'] == 'out') {
                $out_arr[] = (int)$item['game_id'];
            }
        }

        $teammate = [];
        // 只有鸭阵营可以看到队友
        if ($group === '鸭') {
            foreach ($all_player as $item) {
                // 不是自己，且是同阵营的玩家
                if ((int)$item['user_id'] !== (int)(Session::get('user_login_suc')['id']) && $item['group'] === '鸭') {
                    $teammate[] = $item;
                }
                // 模仿者也可以看到
                if ((int)$item['user_id'] !== (int)(Session::get('user_login_suc')['id']) && $item['identity'] === '模仿者') {
                    $teammate[] = $item;
                }
            }
        }

        // 调试信息
        trace('Group: ' . $group);
        trace('Teammate count: ' . count($teammate));
        trace('Teammate data: ' . json_encode($teammate));

        return View::fetch('index/game_page', [
            'number' => $game_number,
            'group' => $group,
            'identity' => $identity,
            'out_arr' => json_encode($out_arr),
            'teammate' => $teammate
        ]);
    }

    public function queryGameStat()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = Db::table('game')->where('id', $game_id)->find();
        if ($game['status'] === '游戏中') {
            return 'game_start';
        } elseif ($game['status'] === '已结束') {
            return 'game_end';
        } elseif ($game['status'] === '等待中') {
            return 'game_wait';
        }
    }

    public function queryExistPlayer()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = Db::table('game')->where('id', $game_id)->find();
        if ($game === null) {
            return 'game_not_exist';
        } else {
            $player_id_arr = [];
            $all_player = unserialize($game['all_user_info']);
            foreach ($all_player as $k => $item) {
                if ($item['status'] === 'alive') {
                    $player_id_arr[] = $item['game_id'];
                }
            }
            return json()->data($player_id_arr);
        }
    }

    //如果重复返回true,不重复返回false
    protected function detect_if_repeat_vote($game_p, $game_id_p)
    {
        for ($i = 0; $i < count($game_p['vote_result']); $i++) {
            if ($game_p['vote_result'][$i]['if_this_round_end'] === false) {
                foreach ($game_p['vote_result'][$i]['vote_detail'] as $item) {
                    if ((int)$item['vote_from'] === (int)$game_id_p) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected function vote_func($game_p, $vote_count, $from_p, $to_p)
    {
        try {
            $vote_item = [];
            $vote_item['vote_from'] = $from_p;
            $vote_item['vote_to'] = $to_p;
            $vote_item['vote_count'] = $vote_count;

//        $temp = $game_p['vote_result'];
            for ($i = 0; $i < count($game_p['vote_result']); $i++) {
                if ($game_p['vote_result'][$i]['if_this_round_end'] === false) {
                    $game_p['vote_result'][$i]['vote_detail'][] = $vote_item;
                }
            }

            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            $game = Db::table('game')->where('id', $game_id)->update([
                'game_info' => serialize($game_p)
            ]);
            return true;
        } catch (\Exception $exception) {
            return false;
        }

//        foreach ($temp as $k => $item) {
//            if ($item['if_this_round_end'] === false) {
//                $item['vote_detail'][] = $vote_item;
//            }
//        }
    }

    //返回true表示投的自己,返回false表示投的别人
    protected function detect_if_vote_self($game_p, $to_p)
    {

        $all_player_info = unserialize($game_p['all_user_info']);
        $self_game_id = null;
        foreach ($all_player_info as $item) {
            if ((int)$item['user_id'] === (int)Session::get('user_login_suc')['id']) {
                $self_game_id = $item['game_id'];
                break;
            }
        }
        return (int)$self_game_id === (int)$to_p;
    }

    public function submitVote()
    {
        $to = $this->request->param('player_gameid');
        $user_id = Session::get('user_login_suc')['id'];
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = Db::table('game')->where('id', $game_id)->find();

        $player_info = unserialize($game['all_user_info']);
        foreach ($player_info as $item) {
            if ((int)($item['user_id']) == (int)$user_id && $item['status'] == 'out') {
                return 'you_are_out';
            }
        }

        foreach ($player_info as $item) {
            if ((int)$to == (int)$item['game_id'] && $item['status'] == 'out') {
                return 'target_is_out';
            }
        }

        $if_player_exist = false;
        if ($game === null) {
            return 'game_not_exist';
        } else {
            if ((unserialize($game['game_info']))['if_start_vote'] !== 0) {
                return 'not_vote_time';
            }

//            if ($this->detect_if_vote_self($game, $to)) {
//                return 'vote_self';
//            }
            $player_info = unserialize($game['all_user_info']);
            foreach ($player_info as $k => $player_item) {
                if ((int)$player_item['user_id'] === (int)$user_id) {
                    $if_player_exist = true;
                    $vote_player_count = (int)$player_item['vote_count'];
                    if ($this->detect_if_repeat_vote(unserialize($game['game_info']), $player_item['game_id'])) {
                        return 'vote_repeat';
                    } else {
                        $result = $this->vote_func(unserialize($game['game_info']), $vote_player_count, $player_item['game_id'], $to);
                        if ($result) {
                            return 'suc';
                        } else {
                            return 'fail';
                        }
                    }
                    break;
                } else {

                }
            }
            if ($if_player_exist === false) {
                return 'player_not_exist';
            }
        }
    }

    /**
     * 检查是否开始投票
     */
    public function queryIfStartVote()
    {
        try {
            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            $game = Db::table('game')->where('id', $game_id)->find();
            
            if (!$game) {
                return 'not_start';
            }

            $game_info = unserialize($game['game_info']);
            
            // 添加日志
            trace('Game info: ' . json_encode($game_info));
            
            if (isset($game_info['if_start_vote'])) {
                if ($game_info['if_start_vote'] === 0) {
                    return 'start';  // 可以投票
                } else if ($game_info['if_start_vote'] === 1) {
                    return 'not_start';  // 等待投票
                } else if ($game_info['if_start_vote'] === 2) {
                    return 'game_not_start';  // 游戏未开始
                } else if ($game_info['if_start_vote'] === 3) {
                    return 'game_started';  // 游戏已开始
                }
            }
            
            return 'not_start';
        } catch (\Exception $e) {
            trace('Error in queryIfStartVote: ' . $e->getMessage());
            return 'not_start';
        }
    }

    public function queryVotedPlayer()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = Db::table('game')->where('id', $game_id)->find();
        $player_info = unserialize($game['all_user_info']);
        $game_info = unserialize($game['game_info']);
        $voted_count = 0;
        foreach ($game_info['vote_result'] as $item) {
            if ($item['if_this_round_end'] === false) {
                $voted_count = count($item['vote_detail']);
            }
        }
        $alive_count = 0;
        foreach ($player_info as $item) {
            if ($item['status'] == 'alive') {
                $alive_count++;
            }
        }
        return json()->data([
            'total' => $alive_count,
            'voted' => $voted_count
        ]);
    }

    public function queryVoteDetail()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        $game_info = unserialize($result['game_info']);

        if (empty($game_info['vote_result'])) {
            return 'empty';
        }

        $item = $game_info['vote_result'][count($game_info['vote_result']) - 1];
        $detail = [];
        foreach ($item['vote_detail'] as $key2 => $vote_item) {

            if (array_key_exists($vote_item['vote_to'], $detail)) {
                $detail[$vote_item['vote_to']] += (int)($vote_item['vote_count']);
            } else {
                $detail[$vote_item['vote_to']] = (int)($vote_item['vote_count']);
            }
        }
        return json()->data([
            'detail' => $detail,
            'out_player' => $item['out']
        ]);
    }

    public function queryIfGameEnd()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $result = Db::table('game')->where('id', $game_id)->find();
        $game_info = unserialize($result['game_info']);
        
        if ($game_info['winner'] !== null) {
            $all_user = unserialize($result['all_user_info']);
            $user = Session::get('user_login_suc');
            
            foreach ($all_user as $player) {
                if ((int)$player['user_id'] === (int)$user['id']) {
                    // 计算表现分数
                    $performance = $this->calculatePerformance($player);
                    
                    // 判断是否获胜
                    $isWinner = $player['identity_info']['camp'] === $game_info['winner'];
                    
                    // 发放奖励
                    $rewards = $this->giveGameRewards($user['id'], $isWinner, $performance);
                    
                    return json([
                        'code' => 0,
                        'data' => [
                            'winner' => $game_info['winner'],
                            'isWinner' => $isWinner,
                            'rewards' => $rewards
                        ]
                    ]);
                }
            }
        }
        return 'none';
    }

    /**
     * 计算玩家表现分数
     */
    protected function calculatePerformance($player)
    {
        $score = 50; // 基础分数
        
        // 根据各种表现加分
        if (!$player['status'] === 'out') {
            $score += 20; // 存活奖励
        }
        
        if (isset($player['skill_used']) && $player['skill_used']) {
            $score += 10; // 技能使用奖励
        }
        
        // 投票表现加分
        if (isset($player['vote_correct']) && $player['vote_correct'] > 0) {
            $score += $player['vote_correct'] * 5; // 每次正确投票加5分
        }
        
        // 完成任务加分
        if (isset($player['tasks_completed']) && $player['tasks_completed'] > 0) {
            $score += $player['tasks_completed'] * 8; // 每个完成的任务加8分
        }
        
        return min(100, $score);
    }

    public function queryIfSelfOut()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $self_game_id = $this->request->param('game_id');
        $all_player = unserialize($result['all_user_info']);
        foreach ($all_player as $item) {
            if ((int)($item['game_id']) == (int)$self_game_id) {
                if ($item['status'] == 'out') {
                    return 'out';
                } else {
                    return 'not_out';
                }
            }
        }
        return 'self_not_exist';
    }

    public function queryIfSelfJoinGame()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $if_join_game = false;
        $all_player_info = unserialize($result['all_user_info']);
        foreach ($all_player_info as $item) {
            if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                $if_join_game = true;
                break;
            }
        }
        return $if_join_game ? 'suc' : 'fail';
    }

    public function queryIfdenyJoinGame()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $if_deny = false;
        if ($result['deny_users'] == null) {

        } else {
            $deny_arr = unserialize($result['deny_users']);
            foreach ($deny_arr as $item) {
                if ((int)$item == (int)(Session::get('user_login_suc')['id'])) {
                    $if_deny = true;
                    break;
                }
            }
        }
        return $if_deny ? 'deny' : 'not_deny';

    }

    public function querySelectedNumberPlayer()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        $all_player_info = unserialize($result['all_user_info']);
        $number_ready_player_count = 0;
        foreach ($all_player_info as $item) {
            if ($item['game_id'] !== null) {
                $number_ready_player_count++;
            }
        }
        return json()->data([
            'number_ready' => $number_ready_player_count,
            'player_count' => count($all_player_info),
            'all_player' => $all_player_info
        ]);
    }

    public function querySelectedNumberStat()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();
        $all_player_info = unserialize($result['all_user_info']);
        $arr = [];
        $self_number = null;
        foreach ($all_player_info as $k => $item) {
            $arr[] = $k + 1;
            if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                $self_number = $item['game_id'];
            }
        }
        return json()->data([
            'number' => $arr,
            'self_number' => $self_number
        ]);
    }

    /**
     * 更新选择的座位号
     */
    public function updateSelectedNumber()
    {
        try {
            $selectedNumber = input('post.sel');
            if (!$selectedNumber) {
                return 'fail';
            }

            $user = Session::get('user_login_suc');
            if (!$user) {
                return 'you_are_not_in_game';
            }

            // 检查座位是否已被占用
            $gameId = Db::table('setting')->where('id', 1)->value('current_game_id');
            $game = Db::table('game')->where('id', $gameId)->find();
            $allUsers = unserialize($game['all_user_info']);
            
            foreach ($allUsers as $player) {
                if (isset($player['game_id']) && $player['game_id'] == $selectedNumber) {
            return 'number_ready_other';
                }
            }

            // 更新用户的座位号
            foreach ($allUsers as &$player) {
                if ($player['user_id'] == $user['id']) {
                    $player['game_id'] = $selectedNumber;
                    break;
                }
            }

            // 保存更新后的用户信息
            Db::table('game')->where('id', $gameId)->update([
                'all_user_info' => serialize($allUsers)
            ]);

                return 'suc';
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
            return 'fail';
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


    public function exitLogin()
    {
        Session::delete('user_login_suc');
        return json()->data('suc');
    }

    //检测是否是发动技能时间,能发动返回true,不能发动返回false
    protected function detect_if_skill_time($result)
    {
        if (((unserialize($result['game_info']))['if_start_vote'] === 1 || (unserialize($result['game_info']))['if_start_vote'] === 3) && (unserialize($result['game_info']))['if_allow_use_skill'] === 1) {
            return true;
        } else {
            return false;
        }
    }

    //检测是否一轮中已发动技能,返回true表示已用过,返回false表示未用过
    protected function detect_if_used_skill($result)
    {
        $if_used = false;
        foreach (unserialize($result['all_user_info']) as $item) {
            if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                if ($item['if_use_skill'] == 0) {
                    $if_used = false;
                } elseif ($item['if_use_skill'] == 1) {
                    $if_used = true;
                }
            }
        }
        return $if_used;
    }

    //检测玩家是否已出局,已出局返回true,未出局返回false
    protected function detect_if_out($result)
    {
        $if_out = false;
        foreach (unserialize($result['all_user_info']) as $item) {
            if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                if ($item['status'] == 'alive') {
                    $if_out = false;
                } elseif ($item['status'] == 'out') {
                    $if_out = true;
                }
            }
        }
        return $if_out;
    }

    protected function update_player_if_use_skill($all_player, $target_item = null)
    {
        foreach ($all_player as $k => $item) {
            if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                $all_player[$k]['if_use_skill'] = 1;
                $all_player[$k]['use_skill_time'] = time();
                if ($target_item !== null) {
                    $all_player[$k]['skill_data']['target_id'] = (int)$target_item['game_id'];
                }
                break;
            }
        }
        Db::table('game')->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))->update([
            'all_user_info' => serialize($all_player)
        ]);
    }

    //是否与醉酒鸭的技能关联,返回true表示已关联,返回false表示未关联
    protected function if_zuijiuya_skill_link($all_user)
    {
        $target_user_game_id = null;

        foreach ($all_user as $user) {
            if ((int)$user['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
                $target_user_game_id = $user['game_id'];
                break;
            }
        }

        foreach ($all_user as $user) {
            if ($user['identity'] == '醉酒鸭') {
                if (isset($user['skill_data']['zuijiuya_target_id_arr'])) {
                    if (in_array((string)$target_user_game_id, $user['skill_data']['zuijiuya_target_id_arr'])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    //返回一个随机身份
    protected function random_identity()
    {
        $ran_ori = json_decode(Db::table('setting')->where('id', 1)->value('roles'), true);
        $random_identity_arr = array_merge($ran_ori['鹅'], $ran_ori['鸭'], $ran_ori['中立']);
        $ran_index = rand(0, count($random_identity_arr) - 1);
        return $random_identity_arr[$ran_index];
    }

    public function zhentanSkill()
    {
        $target = (int)$this->request->param('target_player');
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $target_item = null;
        foreach ($all_user as $item) {
            if ((int)($item['game_id']) == $target) {
                if ($item['status'] == 'out') {
                    return 'target_is_out';
                } else {
                    $target_item = $item;
                    break;
                }
            }
        }

        if ($target_item === null) {
            return 'not_find_player';
        } else {
            $this->update_player_if_use_skill($all_user, $target_item);
            if ($target_item['group'] == '鹅' || $target_item['identity'] == '变形鸭') {
                if ($this->if_zuijiuya_skill_link($all_user)) {
                    return 'bad';
                } else {
                    return 'good';
                }
            } else {
                if ($this->if_zuijiuya_skill_link($all_user)) {
                    return 'good';
                } else {
                    return 'bad';
                }
            }
        }
    }

    public function tonglingzheSkill()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $ya_count = 0;
        foreach ($all_user as $item) {
            if ($item['group'] == '鸭' && $item['status'] == 'out' && $item['identity'] != '变形鸭') {
                $ya_count++;
            }
        }
        $this->update_player_if_use_skill($all_user);
        if ($this->if_zuijiuya_skill_link($all_user)) {
            return '0';
        } else {
            return (string)$ya_count;
        }
    }

    public function binyiyuanSkill()
    {
        $target = (int)$this->request->param('target_player');
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $target_item = null;
        foreach ($all_user as $item) {
            if ((int)($item['game_id']) == $target) {
                if ($item['status'] == 'alive') {
                    $target_item = 'target_is_alive';
                    break;
                } else {
                    $this->update_player_if_use_skill($all_user);
                    if ($this->if_zuijiuya_skill_link($all_user)) {
                        $target_item = $this->random_identity();
                    } else {
                        if ($item['identity'] == '变形鸭') {
                            $target_item = '大白鹅';
                        } else {
                            $target_item = $item['identity'];
                        }
                    }
                    break;
                }
            }
        }
        if ($target_item === null) {
            return 'find_none';
        } else {
            return $target_item;
        }

    }

    public function chaonenglizheSkill()
    {
        $target = (int)$this->request->param('target_player');
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);
        $vote_count = count(array_filter($game_info['vote_result'], function ($v) {
            return $v['if_this_round_end'];
        }));

        if ($vote_count != 1 && $vote_count != 3) {
            return 'not_in_time';
        }

        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                    return 'target_is_self';
                } else {
                    if ($item['status'] == 'out') {
                        return 'target_is_have_out';
                    } else {
                        $all_user[$k]['status'] = 'out';
                        $this->update_player_if_use_skill($all_user);
                        return 'target_out';
                    }
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }

    public function jingzhangSkill()
    {
        $target = (int)$this->request->param('target_player');
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ((int)($item['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                    return 'target_is_self';
                } else {
                    if ($item['group'] == '鹅') {
                        $all_user[$k]['status'] = 'out';
                        foreach ($all_user as $k2 => $item2) {
                            if ((int)($item2['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                                $all_user[$k2]['status'] = 'out';
                            }
                        }
                        $this->update_player_if_use_skill($all_user, $item);
                        return 'target_is_e';
                    } else {
                        $all_user[$k]['status'] = 'out';
                        $this->update_player_if_use_skill($all_user, $item);
                        return 'target_is_not_e';
                    }
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }

    /**
     * 观鸟者技能
     */
    public function guanniaozheSkill()
    {
        try {
            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            $result = Db::table('game')->where([
                'id' => $game_id
            ])->find();

            // 检查是否可以使用技能
            if ($this->detect_if_out($result)) {
                return 'out';
            }

            if (!$this->detect_if_skill_time($result)) {
                return 'not_in_time';
            }

            if ($this->detect_if_used_skill($result)) {
                return 'used';
            }

            $all_user = unserialize($result['all_user_info']);
            
            // 检查当前用户是否是观鸟者
            $current_user = null;
            foreach ($all_user as $user) {
                if ((int)$user['user_id'] === (int)Session::get('user_login_suc')['id']) {
                    $current_user = $user;
                    break;
                }
            }
            
            if (!$current_user || $current_user['identity_info']['identity'] !== '观鸟者') {
                return json([
                    'code' => 1,
                    'msg' => '该身份暂无技能可用'
                ]);
            }

            // 计算存活的鸭阵营玩家数量
            $duck_count = 0;
            foreach ($all_user as $player) {
                if ($player['status'] !== 'out' && $player['identity_info']['camp'] === '鸭') {
                    $duck_count++;
                }
            }

            // 标记技能已使用
            $this->update_player_if_use_skill($all_user);

            return json([
                'code' => 0,
                'data' => $duck_count,
                'msg' => 'success'
            ]);

        } catch (\Exception $e) {
            \think\facade\Log::error('观鸟者技能错误: ' . $e->getMessage());
            return json([
                'code' => 1,
                'msg' => $e->getMessage()
            ]);
        }
    }

    public function sendIdentity()
    {
        $identity = [];
        $arr = json_decode(Db::table('setting')->where('id', 1)->value('roles'), true);
        if ($this->request->param('identity_type') == 'cike') {
            foreach ($arr as $k => $item) {
                if ($k != '鸭') {
                    foreach ($item as $item_iden) {
                        if ($item_iden != '大白鹅') {
                            $identity[] = $item_iden;
                        }
                    }
                }
            }
        } elseif ($this->request->param('identity_type') == 'wuya') {
            foreach ($arr as $k => $item) {
                foreach ($item as $item_iden) {
                    if ($item_iden != '大白鹅') {
                        $identity[] = $item_iden;
                    }
                }
            }
        }

        return json()->data($identity);
    }

    public function cikeSkill()
    {
        $target = (int)$this->request->param('target_player');
        $iden = $this->request->param('target_iden');

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ($item['status'] == 'out') {
                    return 'target_is_have_out';
                } else {
                    if ($item['identity'] == $iden) {
                        $all_user[$k]['status'] = 'out';
                        $this->update_player_if_use_skill($all_user, $item);
                        return 'suc';
                    } else {
                        foreach ($all_user as $k2 => $item2) {
                            if ((int)($item2['user_id']) == (int)(Session::get('user_login_suc')['id'])) {
                                $all_user[$k2]['status'] = 'out';
                                $this->update_player_if_use_skill($all_user, $item);
                                return 'fail';
                            }
                        }
                    }
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }

    public function jiandieSkill()
    {
        $target = (int)$this->request->param('target_player');
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ($item['status'] == 'out') {
                    return 'target_is_have_out';
                } else {
                    $this->update_player_if_use_skill($all_user);
                    return $item['identity'];
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }

    public function wuyaSkill()
    {
        $target = (int)$this->request->param('target_player');
        $iden = $this->request->param('target_iden');

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ($item['status'] == 'out') {
                    return 'target_is_have_out';
                } else {
                    if ($item['identity'] == $iden) {
                        $all_user[$k]['status'] = 'out';
                        $this->update_player_if_use_skill($all_user, $item);
                        return 'suc';
                    } else {
                        $this->update_player_if_use_skill($all_user, $item);
                        return 'fail';
                    }
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }
    public function hepinggeSkill()
{
    // 获取参数
    $targetPlayers = $this->request->param('target_players'); // "1,2"
    $targetIdentities = $this->request->param('target_identities'); // "狼人,预言家"

    // 将字符串拆分为数组
    $players = explode(',', $targetPlayers); // ['1', '2']
    $identities = explode(',', $targetIdentities); // ['狼人', '预言家']

    if (count($players) !== 2 || count($identities) !== 2) {
        return json(['status' => 'error', 'message' => '参数格式错误！']);
    }

    // 获取当前游戏数据
    $currentGameId = Db::table('setting')->where('id', 1)->value('current_game_id');
    if (!$currentGameId) {
        return json(['status' => 'error', 'message' => '游戏不存在！']);
    }

    $result = Db::table('game')->where('id', $currentGameId)->find();
    if (!$result) {
        return json(['status' => 'error', 'message' => '游戏数据未找到！']);
    }

    // 检测游戏状态
    if ($this->detect_if_out($result)) {
        return json(['status' => 'error', 'message' => '您已出局，无法使用技能！']);
    }

    if (!$this->detect_if_skill_time($result)) {
        return json(['status' => 'error', 'message' => '当前无法使用技能！']);
    }

    if ($this->detect_if_used_skill($result)) {
        return json(['status' => 'error', 'message' => '技能已使用！']);
    }

    // 解析玩家信息
    $all_user = unserialize($result['all_user_info']);
    if (!is_array($all_user)) {
        return json(['status' => 'error', 'message' => '玩家数据格式错误！']);
    }

    $infectedCount = 0;
    $results = [];

    foreach ($players as $index => $playerId) {
        $identity = $identities[$index];
        $found = false;

        foreach ($all_user as $key => $user) {
            if ((int)$user['game_id'] === (int)$playerId) {
                $found = true;

                // 检测玩家是否已感染或出局
                if ($user['status'] === 'infected') {
                    $results[] = "玩家 {$playerId} 已被感染！";
                } elseif ($user['status'] === 'out') {
                    $results[] = "玩家 {$playerId} 已出局！";
                } elseif ($user['identity'] === $identity) {
                    // 感染成功
                    $all_user[$key]['status'] = 'infected';
                    $infectedCount++;
                    $results[] = "玩家 {$playerId} 感染成功！";
                } else {
                    $results[] = "玩家 {$playerId} 感染失败！";
                }
            }
        }

        if (!$found) {
            $results[] = "玩家 {$playerId} 不存在！";
        }
    }

    // 更新技能使用状态和玩家数据
    $this->update_player_if_use_skill($all_user, $result);

    // 统计总感染人数
    $totalInfected = 0;
    foreach ($all_user as $user) {
        if ($user['status'] === 'infected') {
            $totalInfected++;
        }
    }

    // 判断是否达到胜利条件
    if ($totalInfected >= 4) {
        return json(['status' => 'success', 'message' => 'Victory']);
    }

    return json(['status' => 'success', 'message' => implode("\n", $results)]);
}



    public function jingyuzheSkill()
    {
        $target = (int)$this->request->param('target_player');

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ($item['status'] == 'out') {
                    return 'target_is_have_out';
                } else {
                    $game_info = unserialize($result['game_info']);
                    $game_info['bantalk_player'][] = $target;
                    $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
                    $game = Db::table('game')->where('id', $game_id)->update([
                        'game_info' => serialize($game_info)
                    ]);
                    $this->update_player_if_use_skill($all_user);
                    return 'suc';
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }
   
    public function detectIfSelfBantalk()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = unserialize(Db::table('game')->where('id', $game_id)->value('game_info'));

        return json()->data([
            'single' => $game['bantalk_player'],
            'all' => $game['if_all_bantalk']
        ]);
    }

    public function zhuizongzheSkill()
    {
        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $skill_data = null;
        $key = null;
        $target_ya = null;
        $target_ya_iden = null;
        $target_zhongli = null;
        $target_zhongli_iden = null;
        $target_ya_arr = [];
        $target_zhongli_arr = [];

        foreach ($all_user as $k => $item) {
            if ((int)$item['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
                $key = $k;
                $skill_data = $item['skill_data'];
                break;
            }
        }

        //鸭和中立范围确定
        foreach ($all_user as $item) {
            if ($item['group'] == '鸭') {
                if ($item['identity'] == '变形鸭') {
                    continue;
                }
                if (array_key_exists('ya_viewed', $skill_data)) {
                    if (in_array((int)($item['game_id']), $skill_data['ya_viewed'])) {
                        continue;
                    } else {
                        $target_ya_arr[] = (int)($item['game_id']);
                    }
                } else {
                    $all_user[$key]['skill_data']['ya_viewed'] = [];
                    $target_ya_arr[] = (int)($item['game_id']);
                }
            }
            if ($item['group'] == '中立') {
                if (array_key_exists('zhongli_viewed', $skill_data)) {
                    if (in_array((int)($item['game_id']), $skill_data['zhongli_viewed'])) {
                        continue;
                    } else {
                        $target_zhongli_arr[] = (int)($item['game_id']);
                    }
                } else {
                    $all_user[$key]['skill_data']['zhongli_viewed'] = [];
                    $target_zhongli_arr[] = (int)($item['game_id']);
                }
            }
        }

        //随机选择鸭和中立
        if (empty($target_ya_arr) && empty($target_zhongli_arr)) {
            return 'none';
        } else {
            if (!empty($target_ya_arr)) {
                $k_ya = rand(0, count($target_ya_arr) - 1);
                $target_ya = $target_ya_arr[$k_ya];
                $all_user[$key]['skill_data']['ya_viewed'][] = $target_ya;
            }
            if (!empty($target_zhongli_arr)) {
                $k_zhonghli = rand(0, count($target_zhongli_arr) - 1);
                $target_zhongli = $target_zhongli_arr[$k_zhonghli];
                $all_user[$key]['skill_data']['zhongli_viewed'][] = $target_zhongli;
            }

            foreach ($all_user as $item) {
                if ((int)($item['game_id']) == $target_ya) {
                    $target_ya_iden = $item['identity'];
                }
                if ((int)($item['game_id']) == $target_zhongli) {
                    $target_zhongli_iden = $item['identity'];
                }
            }

            $this->update_player_if_use_skill($all_user);

            if ($this->if_zuijiuya_skill_link($all_user)) {
                return json()->data('追踪者');
            } else {
                return json()->data([
                    'ya' => $target_ya_iden,
                    'zhongli' => $target_zhongli_iden
                ]);
            }
        }
    }

    public function jianyuzhangSkill()
    {
        $target = (int)$this->request->param('target_player');

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ($item['status'] == 'out') {
                    return 'target_is_have_out';
                } else {
                    $game_info = unserialize($result['game_info']);
                    $game_info['prisoner'][] = $target;
                    $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
                    $game = Db::table('game')->where('id', $game_id)->update([
                        'game_info' => serialize($game_info)
                    ]);
                    $this->update_player_if_use_skill($all_user);
                    return 'suc';
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }

    public function detectIfSelfPrison()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
        $game = unserialize(Db::table('game')->where('id', $game_id)->value('game_info'));

        return json()->data($game['prisoner']);
    }

    public function shushiSkill()
    {
        $target = (int)$this->request->param('target_player');

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $game_info = unserialize($result['game_info']);

        if (count($game_info['vote_result']) == 2 || count($game_info['vote_result']) == 5) {
            $game_info['if_all_bantalk'] = true;
            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            $game = Db::table('game')->where('id', $game_id)->update([
                'game_info' => serialize($game_info)
            ]);
            $this->update_player_if_use_skill($all_user);
            return 'suc';
        } else {
            return 'fail';
        }


    }

    public function gaomizheSkill()
    {
        $target = (int)$this->request->param('target_player');

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);


        $if_target_exist = false;
        foreach ($all_user as $k => $item) {
            if ((int)($item['game_id']) == $target) {
                $if_target_exist = true;
                if ($item['status'] == 'out') {
                    return 'target_is_have_out';
                } else {
                    $game_info = unserialize($result['game_info']);
                    $game_info['prisoner'][] = $target;
                    $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
                    $game = Db::table('game')->where('id', $game_id)->update([
                        'game_info' => serialize($game_info)
                    ]);
                    $this->update_player_if_use_skill($all_user);
                    return 'suc';
                }
            }
        }
        if (!$if_target_exist) {
            return 'target_not_exist';
        }
    }

    public function zhenyingeSkill()
    {
        $players = json_decode($this->request->param('target_players'), true);
        if (count($players) != 2) {
            return json()->data('target_not_exist');
        }

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $target_players = [];
        foreach ($all_user as $user) {
            if (in_array((string)$user['game_id'], $players)) {
                $target_players[] = $user;
            }

            if (count($target_players) == 2) {
                break;
            }
        }

        if (count($target_players) != 2) {
            return json()->data('target_not_exist');
        }

        foreach ($target_players as $p) {
            if ((int)(Session::get('user_login_suc')['id']) == (int)$p['user_id']) {
                return json()->data('deny_detect_self');
            }
        }

        $if_same = false;

        $this->update_player_if_use_skill($all_user);

        if ($target_players[0]['group'] == $target_players[1]['group']) {
            $if_same = true;
        } else {
            $if_same = false;
        }

        if ($this->if_zuijiuya_skill_link($all_user)) {
            $if_same = !$if_same;
        }

        return $if_same ? json()->data('same') : json()->data('not_same');
    }

    public function zuijiuyaSkill()
    {
        $players = json_decode($this->request->param('target_players'), true);
        if (count($players) != 2) {
            return json()->data('target_not_exist');
        }

        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $target_players = [];
        foreach ($all_user as $user) {
            if (in_array((string)$user['game_id'], $players)) {
                $target_players[] = $user;
            }

            if (count($target_players) == 2) {
                break;
            }
        }

        if (count($target_players) != 2) {
            return json()->data('target_not_exist');
        }

        foreach ($all_user as $k => $user) {
            if ((int)$user['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
                $all_user[$k]['skill_data']['zuijiuya_target_id_arr'] = $players;
                break;
            }
        }

        $this->update_player_if_use_skill($all_user);

        return json()->data('suc');
    }

    /**
 * 执行"喜鹊"技能的方法
 * 
 * 该方法用于处理喜鹊技能的逻辑，包括验证目标玩家、检查游戏状态、更新玩家技能使用状态等。
 * 
 * @return string 返回技能执行的结果，可能的值包括 'target_not_exist'、'out'、'not_in_time'、'used' 或 'suc'
 */
/**
 * 执行"醉鸡呀"技能的方法
 * 
 * 该方法用于处理醉鸡呀技能的逻辑，包括验证目标玩家、检查游戏状态、更新玩家技能使用状态等。
 * 
 * @return string 返回技能执行的结果，可能的值包括 'target_not_exist'、'out'、'not_in_time'、'used' 或 'suc'
 */
public function meipoSkill()
{
    // 从请求中获取目标玩家的游戏ID，并将其解码为数组
    $players = json_decode($this->request->param('target_players'), true);
    
    // 检查目标玩家数量是否为2，如果不是则返回错误信息
    if (count($players) != 2) {
        return json()->data('target_not_exist');
    }

    // 从数据库中获取当前游戏的信息
    $result = Db::table('game')->where([
        'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
    ])->find();

    // 检查游戏是否已经结束，如果是则返回错误信息
    if ($this->detect_if_out($result)) {
        return 'out';
    }

    // 检查是否在技能使用时间内，如果不是则返回错误信息
    if (!$this->detect_if_skill_time($result)) {
        return 'not_in_time';
    }

    // 检查玩家是否已经使用过技能，如果是则返回错误信息
    if ($this->detect_if_used_skill($result)) {
        return 'used';
    }

    // 反序列化所有用户的信息
    $all_user = unserialize($result['all_user_info']);

    // 初始化目标玩家数组
    $target_players = [];
    
    // 遍历所有用户，找到目标玩家并添加到目标玩家数组中
    foreach ($all_user as $user) {
        if (in_array((string)$user['game_id'], $players)) {
            $target_players[] = $user;
        }

        // 如果已经找到两个目标玩家，则停止遍历
        if (count($target_players) == 2) {
            break;
        }
    }

    // 检查目标玩家数量是否为2，如果不是则返回错误信息
    if (count($target_players) != 2) {
        return json()->data('target_not_exist');
    }

    // 遍历所有用户，找到当前登录用户
    foreach ($all_user as $k => $user) {
        if ((int)$user['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
            // 更新当前登录用户的技能数据，将目标玩家的ID存储在zuijiuya_target_id_arr字段中
            $all_user[$k]['skill_data']['target_bundle_player'] = $players;
            break;
        }
    }

    // 更新玩家的技能使用状态
    $this->update_player_if_use_skill($all_user);

    // 返回成功信息
    return json()->data('suc');
}
 public function baobiaoSkill()
    {
        $target_player = $this->request->param('target_player');


        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);

        $if_target_player_exsit = false;
        foreach ($all_user as $user) {
            if ((int)$user['game_id'] == (int)$target_player) {
                $if_target_player_exsit = true;
                break;
            }
        }

        if ($if_target_player_exsit) {
            foreach ($all_user as $k => $user) {
                if ((int)$user['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
                    $all_user[$k]['skill_data']['target_bundle_player'] = (int)$target_player;

                    $this->update_player_if_use_skill($all_user);

                    return 'suc';
                }
            }
        } else {
            return 'target_not_exist';
        }

    }


    public function baozhawangSkill()
    {
        $target_player = $this->request->param('target_player');


        $result = Db::table('game')->where([
            'id' => Db::table('setting')->where('id', 1)->value('current_game_id')
        ])->find();

        if ($this->detect_if_out($result)) {
            return 'out';
        }

        if (!$this->detect_if_skill_time($result)) {
            return 'not_in_time';
        }

        if ($this->detect_if_used_skill($result)) {
            return 'used';
        }

        $all_user = unserialize($result['all_user_info']);
        $game_info = unserialize($result['game_info']);

        if (!in_array(count($game_info['vote_result']), [0, 2, 4])) {
            return 'not_in_time';
        }

        $if_target_player_exsit = false;
        foreach ($all_user as $user) {
            if ((int)$user['game_id'] == (int)$target_player) {
                $if_target_player_exsit = true;
                break;
            }
        }

        if ($if_target_player_exsit) {
            foreach ($all_user as $k => $user) {
                if ((int)$user['user_id'] == (int)(Session::get('user_login_suc')['id'])) {
                    $all_user[$k]['skill_data']['target_bundle_player'] = (int)$target_player;

                    $this->update_player_if_use_skill($all_user);

                    return 'suc';
                }
            }
        } else {
            return 'target_not_exist';
        }

    }

    public function detectMnTaskScore()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        $game_info = unserialize($game['game_info']);

        return $game_info['mini_task_score'];
    }

    public function showAnotherLover()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $result = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        $all_user = unserialize($result['all_user_info']);

        foreach ($all_user as $user) {
            if ((int)$user['user_id'] != (int)(Session::get('user_login_suc')['id']) && $user['identity'] == '恋人') {
                return json()->data($user['game_id']);
            }
        }
        return json()->data('none');
    }

    public function detectIfSettleAccount()
    {
        $bill_id = Db::table('setting')->where('id', 1)->value('current_bill_id');

        $bill_result = Db::table('bill')->where([
            'id' => $bill_id
        ])->find();

        $allplayers = (unserialize($bill_result['players']))['join_players'];


        foreach ($allplayers as $player) {
            if ($player['player_id'] == Session::get('user_login_suc')['id']) {
                if ($player['settle_account_stat'] == '已结' && $player['if_view_bill'] == 0) {
                    $play_darution = number_format(($player['settle_account_timestamp'] - $player['join_timestamp']) / 3600, 2);
                    $consumption = $player['curr_consume'];
                    $remainder = $player['remainder'];

                    return json()->data([
                        'duration' => $play_darution,
                        'consumption' => $consumption,
                        'reamainder' => $remainder
                    ]);
                }
            }
        }
        return json()->data('none');

    }

    public function queryPersonBill()
    {
        $page = (int)$this->request->param('curr_page');
        $limit = (int)$this->request->param('limit');

        $result = Db::table('bill')->field('players,create_time')->order('create_time', 'desc')->select()->toArray();

        $price = Db::table('setting')->where('id', 1)->value('price');
        $second_price = (float)($price / 7200);

        $return_data = [];
        foreach ($result as $k => $item) {
            $players = (unserialize($item['players']))['join_players'];

            foreach ($players as $player) {
                if ($player['player_id'] == (int)(Session::get('user_login_suc')['id'])) {
                    $tmp = [];
                    $tmp['join_time'] = date('Y-m-d H:i:s', $player['join_timestamp']);
                    $tmp['curr_consume'] = ($player['settle_account_stat'] == '已结' ? $player['curr_consume'] : number_format(((time() - $player['join_timestamp']) * $second_price), 2));
                    $tmp['settle_account_stat'] = $player['settle_account_stat'];
                    $tmp['remainder'] = $player['remainder'];
                    $return_data[] = $tmp;
                    break;
                }
            }
        }

        $count = count($return_data);

        $return_data = array_splice($return_data, ($page - 1) * $limit, $limit);

        return json()->data([
            'data' => $return_data,
            'count' => $count
        ]);
    }

    public function updateIfUserViewBill()
    {

        $bill_id = Db::table('setting')->where('id', 1)->value('current_bill_id');

        $bill_result = Db::table('bill')->where([
            'id' => $bill_id
        ])->find();

        if ($bill_result != null) {
            $allplayer_ori = unserialize($bill_result['players']);
            $allplayer = $allplayer_ori['join_players'];
            foreach ($allplayer as $k => $player) {
                if ($player['player_id'] == (int)(Session::get('user_login_suc')['id'])) {
                    $allplayer[$k]['if_view_bill'] = 1;
                    break;
                }
            }
            $allplayer_ori['join_players'] = $allplayer;
            Db::table('bill')->where([
                'id' => $bill_id
            ])->update([
                'players' => serialize($allplayer_ori)
            ]);

            return json()->data('suc');
        } else {
            return json()->data('fail');
        }
    }

    public function vote()
    {
        return View::fetch('vote');
    }

    /**
     * 获取用户信息
     */
    public function getUsers()
    {
        try {
            // 从setting表获取当前游戏ID
            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            if (!$game_id) {
                return json(['code' => 1, 'msg' => '未找到游戏ID']);
            }

            // 获取当前游戏的所有玩家信息
            $game = Db::table('game')->where('id', $game_id)->find();
            if (!$game) {
                return json(['code' => 1, 'msg' => '未找到游戏信息']);
            }

            // 记录日志
            trace('Game data: ' . json_encode($game));

            // 检查all_user_info是否为空
            if (empty($game['all_user_info'])) {
                return json(['code' => 1, 'msg' => '玩家信息为空']);
            }

            $all_player = @unserialize($game['all_user_info']);
            if ($all_player === false) {
                trace('Unserialize failed. Raw data: ' . $game['all_user_info']);
                return json(['code' => 1, 'msg' => '玩家数据格式错误']);
            }

            trace('Unserialized player data: ' . json_encode($all_player));

            // 构建用户信息数组
            $userInfo = [];
            foreach ($all_player as $player) {
                // 检查所有可能的用户名字段
                $userName = null;
                if (isset($player['user_name'])) {
                    $userName = $player['user_name'];
                } elseif (isset($player['username'])) {
                    $userName = $player['username'];
                }

                // 检查所有可能的游戏ID字段
                $gameId = null;
                if (isset($player['game_id'])) {
                    $gameId = $player['game_id'];
                }

                // 如果找到了必要的信息，添加到结果中
                if ($gameId !== null && $userName !== null) {
                    $userInfo[] = [
                        'game_id' => $gameId,
                        'user_name' => $userName
                    ];
                }
            }

            if (empty($userInfo)) {
                // 如果没有找到有效的玩家信息，获取用户表中的信息
                $users = Db::table('users')->select()->toArray();
                foreach ($all_player as $player) {
                    if (isset($player['game_id']) && isset($player['user_id'])) {
                        foreach ($users as $user) {
                            if ((int)$user['id'] === (int)$player['user_id']) {
                                $userInfo[] = [
                                    'game_id' => $player['game_id'],
                                    'user_name' => $user['user_name']
                                ];
                                break;
                            }
                        }
                    }
                }
            }

            if (empty($userInfo)) {
                return json(['code' => 1, 'msg' => '没有有效的玩家信息']);
            }

            return json([
                'code' => 0,
                'users' => $userInfo
            ]);
        } catch (\Exception $e) {
            trace('Error in getUsers: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => '获取用户信息失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo()
    {
        try {
        $user = Session::get('user_login_suc');
        if (!$user) {
            return json(['code' => 1, 'msg' => '用户未登录']);
        }

            $userInfo = Db::table('users')
                ->where('id', $user['id'])
                ->field('user_name, user_phone, avatar')  // 使用正确的字段名
                ->find();

        if (!$userInfo) {
                return json(['code' => 1, 'msg' => '用户不存在']);
        }

        return json([
            'code' => 0,
            'data' => [
                    'username' => $userInfo['user_name'],
                    'phone' => $userInfo['user_phone'],  // 使用 user_phone
                    'avatar' => $userInfo['avatar'] ?: '/static/touxiang/default.jpg'
                ]
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取用户信息失败']);
        }
    }

    /**
     * 更新用户名
     */
    public function updateUserName()
    {
        try {
            $username = Session::get('user_name');
            $newName = input('post.newName');

            if (!$username) {
                return json(['code' => 1, 'msg' => '用户未登录']);
            }

            if (!$newName) {
                return json(['code' => 1, 'msg' => '用户名不能为空']);
            }

            // 检查新用户名是否已存在
            $exists = Db::table('users')
                ->where('user_name', $newName)
                ->where('user_name', '<>', $username)
                ->find();

            if ($exists) {
                return json(['code' => 1, 'msg' => '用户名已存在']);
            }

            // 更新用户名
            Db::table('users')
                ->where('user_name', $username)
                ->update(['user_name' => $newName]);

            // 更新session中的用户名
            Session::set('user_name', $newName);

            return json(['code' => 0, 'msg' => '更新成功']);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '更新失败']);
        }
    }

    /**
     * 获取历史对局
     */
    public function getHistoryMatches()
    {
        try {
        $user = Session::get('user_login_suc');
        if (!$user) {
            return json(['code' => 1, 'msg' => '用户未登录']);
        }

            // 获取用户参与的最近30局游戏
        $games = Db::table('game')
                ->field('id, create_time, all_user_info, game_info')
                ->whereNotNull('all_user_info')
                ->whereNotNull('game_info')
            ->order('create_time', 'desc')
                ->limit(30)  // 限制最近30局
                ->select()
                ->toArray();

        $matchHistory = [];
        foreach ($games as $game) {
                // 检查数据是否为空
                if (empty($game['all_user_info']) || empty($game['game_info'])) {
                    continue;
                }

                try {
                    // 解析玩家信息
            $allUserInfo = unserialize($game['all_user_info']);
                    $gameInfo = unserialize($game['game_info']);
                    
                    // 检查解析结果
                    if (!is_array($allUserInfo) || !is_array($gameInfo)) {
                        continue;
                    }

                    // 查找当前用户的游戏记录
            foreach ($allUserInfo as $player) {
                        if (isset($player['user_id']) && $player['user_id'] == $user['id']) {
                            // 获取游戏结果
                            $gameResult = '未知';
                            if (isset($gameInfo['winner'])) {
                                $playerGroup = $player['group'] ?? '';
                                if ($gameInfo['winner'] === $playerGroup) {
                                    $gameResult = '胜利';
                                } else if ($gameInfo['winner'] === '平局') {
                                    $gameResult = '平局';
                                } else {
                                    $gameResult = '失败';
                                }
                            }

                $matchHistory[] = [
                    'game_id' => $game['id'],
                                'game_time' => date('Y-m-d H:i', strtotime($game['create_time'])),
                                'player_number' => $player['game_id'] ?? '未知',
                                'identity' => $player['identity'] ?? '未知',
                                'group' => $player['group'] ?? '未知',
                                'status' => $gameResult
                            ];
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // 记录具体的解析错误，但继续处理下一条记录
                    trace("Error processing game {$game['id']}: " . $e->getMessage());
                    continue;
            }
        }

        return json([
            'code' => 0,
                'data' => $matchHistory,
                'msg' => empty($matchHistory) ? '暂无历史对局记录' : 'success'
        ]);

        } catch (\Exception $e) {
            trace('Error in getHistoryMatches: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => '获取历史对局失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取账单历史
     */
    public function getBillHistory()
    {
        $user = Session::get('user_login_suc');
        if (!$user) {
            return json(['code' => 1, 'msg' => '用户未登录']);
        }

        // 获取用户的账单记录
        $bills = Db::table('bill')
            ->where('players', 'like', '%"player_id":' . $user['id'] . '%')
            ->order('create_time', 'desc')
            ->select();

        $billHistory = [];
        foreach ($bills as $bill) {
            $players = unserialize($bill['players']);
            foreach ($players['join_players'] as $player) {
                if ($player['player_id'] == $user['id']) {
                    $billHistory[] = [
                        'bill_id' => $bill['id'],
                        'amount' => $player['amount'],
                        'create_time' => $bill['create_time'],
                        'game_id' => $bill['game_id'],
                        'status' => $player['if_view_bill'] ? '已查看' : '未查看'
                    ];
                    break;
                }
            }
        }

        return json([
            'code' => 0,
            'data' => $billHistory
        ]);
    }

    /**
     * 获取游戏配置
     */
    public function getGameConfig()
    {
        try {
            $config = Db::table('game_config')->where('id', 1)->find();
            return json([
                'success' => true,
                'data' => [
                    'draft_mode_enabled' => (bool)$config['draft_mode_enabled']
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 检查是否轮到当前玩家选择角色
     */
    public function checkDraftTurn()
    {
        try {
            $seatId = input('get.seatId');
            $currentGame = Db::table('game')
                ->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))
                ->find();

            if (!$currentGame) {
                return json(['success' => false, 'message' => '游戏不存在']);
            }

            // 初始化轮抽信息
            $gameInfo = isset($currentGame['game_info']) ? unserialize($currentGame['game_info']) : [];
            if (!isset($gameInfo['draft_info'])) {
                $allUsers = unserialize($currentGame['all_user_info']);
                $availableSeats = array_map(function($user) {
                    return $user['game_id'];
                }, $allUsers);
                
                // 随机打乱座位顺序
                shuffle($availableSeats);
                
                $gameInfo['draft_info'] = [
                    'current_seat' => $availableSeats[0], // 第一个选择的座位
                    'seat_order' => $availableSeats,      // 选择顺序
                    'current_index' => 0,                 // 当前选择的索引
                    'selected_roles' => []
                ];
                
                // 保存更新
                Db::table('game')->where('id', $currentGame['id'])->update([
                    'game_info' => serialize($gameInfo)
                ]);
            }

            $draftInfo = $gameInfo['draft_info'];
            
            return json([
                'success' => true,
                'isYourTurn' => (int)$seatId === (int)$draftInfo['current_seat'],
                'currentDraftSeat' => $draftInfo['current_seat']
            ]);
        } catch (\Exception $e) {
            return json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 获取下一个选择位置
     */
    private function getNextSeat($currentSeat, $currentGame)
    {
        $gameInfo = unserialize($currentGame['game_info']);
        $draftInfo = $gameInfo['draft_info'];
        
        // 获取当前座位在选择顺序中的索引
        $currentIndex = $draftInfo['current_index'];
        
        // 移动到下一个位置
        $nextIndex = $currentIndex + 1;
        
        // 如果已经是最后一个位置，返回null表示选择结束
        if ($nextIndex >= count($draftInfo['seat_order'])) {
            return null;
        }
        
        // 更新当前索引
        $gameInfo['draft_info']['current_index'] = $nextIndex;
        
        // 保存更新
        Db::table('game')->where('id', $currentGame['id'])->update([
            'game_info' => serialize($gameInfo)
        ]);
        
        // 返回下一个选择的座位号
        return $draftInfo['seat_order'][$nextIndex];
    }

    /**
     * 获取随机角色选项
     */
    public function getRandomRoles()
    {
        try {
            // 获取当前游戏信息
            $currentGame = Db::table('game')
                ->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))
                ->find();
            
            if (!$currentGame) {
                return json(['success' => false, 'message' => '游戏不存在']);
            }

            // 获取所有角色配置
            $roles = json_decode(Db::table('setting')->where('id', 1)->value('roles'), true);
            if (!$roles) {
                return json(['success' => false, 'message' => '角色配置不存在']);
            }

            // 获取已选择的角色
            $selectedRoles = [];
            
            // 从game_info中获取已选择的角色
            $gameInfo = isset($currentGame['game_info']) ? unserialize($currentGame['game_info']) : [];
            if (isset($gameInfo['draft_info']['selected_roles'])) {
                foreach ($gameInfo['draft_info']['selected_roles'] as $roleId) {
                    foreach ($roles as $camp => $campRoles) {
                        if (isset($campRoles[$roleId])) {
                            $selectedRoles[] = $campRoles[$roleId];
                            break;
                        }
                    }
                }
            }
            
            // 从all_user_info中获取已选择的角色
            $allUsers = unserialize($currentGame['all_user_info']);
            foreach ($allUsers as $user) {
                if (isset($user['identity']) && !in_array($user['identity'], $selectedRoles)) {
                    $selectedRoles[] = $user['identity'];
                }
            }

            // 按阵营收集可用角色
            $availableRolesByCamp = [
                '鹅' => [],
                '鸭' => [],
                '中立' => []
            ];

            foreach ($roles as $camp => $campRoles) {
                foreach ($campRoles as $id => $roleName) {
                    if (!in_array($roleName, $selectedRoles)) {
                        $availableRolesByCamp[$camp][] = [
                            'id' => $id,
                            'name' => $roleName,
                            'camp' => $camp,
                            'icon' => '/static/identity_img/' . $roleName . '.png'
                        ];
                    }
                }
            }

            // 从每个阵营中随机选择一个角色（如果有的话）
            $randomRoles = [];
            foreach ($availableRolesByCamp as $camp => $campRoles) {
                if (!empty($campRoles)) {
                    $randomIndex = array_rand($campRoles);
                    $randomRoles[] = $campRoles[$randomIndex];
                }
            }

            // 如果角色数量不足3个，从所有可用角色中随机补充
            $allAvailableRoles = array_merge(...array_values($availableRolesByCamp));
            while (count($randomRoles) < 3 && !empty($allAvailableRoles)) {
                $randomIndex = array_rand($allAvailableRoles);
                $role = $allAvailableRoles[$randomIndex];
                // 检查是否已经选择了这个角色
                $isDuplicate = false;
                foreach ($randomRoles as $selectedRole) {
                    if ($selectedRole['id'] === $role['id']) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if (!$isDuplicate) {
                    $randomRoles[] = $role;
                }
                unset($allAvailableRoles[$randomIndex]);
            }

            // 打乱最终的角色顺序
            shuffle($randomRoles);

            if (empty($randomRoles)) {
                return json(['success' => false, 'message' => '没有可用的角色']);
            }

            return json([
                'success' => true,
                'roles' => $randomRoles
            ]);
        } catch (\Exception $e) {
            trace('Get random roles error: ' . $e->getMessage(), 'error');
            return json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 选择角色
     */
    public function selectRole($params = null)
    {
        try {
            // 如果是从 selectRandomRole 调用，使用传入的参数
            if ($params) {
                $roleId = $params['roleId'];
                $roleName = $params['roleName'];
                $roleCamp = $params['roleCamp'];
                $seatId = $params['seatId'];
            } else {
                // 否则从请求中获取参数
                $roleId = input('post.roleId');
                $roleName = input('post.roleName');
                $roleCamp = input('post.roleCamp');
                $seatId = input('post.seatId');
            }
            
            trace('Select role request - Role ID: ' . $roleId . ', Name: ' . $roleName . ', Camp: ' . $roleCamp . ', Seat ID: ' . $seatId, 'info');
            
            $currentGame = Db::table('game')
                ->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))
                ->find();

            if (!$currentGame) {
                return json(['success' => false, 'message' => '游戏不存在']);
            }

            // 获取游戏信息
            $gameInfo = isset($currentGame['game_info']) ? unserialize($currentGame['game_info']) : [];
            if (!isset($gameInfo['draft_info'])) {
                return json(['success' => false, 'message' => '轮抽未初始化']);
            }

            $draftInfo = &$gameInfo['draft_info'];
            
            // 验证是否轮到该玩家选择
            if ((int)$seatId !== (int)$draftInfo['current_seat']) {
                return json(['success' => false, 'message' => '还未轮到您选择']);
            }

            // 获取角色配置
            $roles = json_decode(Db::table('setting')->where('id', 1)->value('roles'), true);
            
            // 验证角色信息
            $roleFound = false;
            foreach ($roles as $camp => $campRoles) {
                foreach ($campRoles as $id => $name) {
                    if ((string)$id === (string)$roleId && $name === $roleName && $camp === $roleCamp) {
                        $roleFound = true;
                        break 2;
                    }
                }
            }

            if (!$roleFound) {
                trace('Invalid role data - ID: ' . $roleId . ', Name: ' . $roleName . ', Camp: ' . $roleCamp, 'error');
                return json(['success' => false, 'message' => '角色信息无效']);
            }

            // 检查角色是否已被选择
            $allUsers = unserialize($currentGame['all_user_info']);
            foreach ($allUsers as $user) {
                if (isset($user['identity']) && $user['identity'] === $roleName) {
                    return json(['success' => false, 'message' => '该角色已被选择']);
                }
            }

            // 更新玩家身份 - 使用当前轮到的座位号
            $updated = false;
            foreach ($allUsers as &$user) {
                if ((int)$user['game_id'] === (int)$draftInfo['current_seat']) {
                    $user['identity'] = $roleName;
                    $user['group'] = $roleCamp;
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                trace('Failed to update user identity. Current seat: ' . $draftInfo['current_seat'] . ', Users: ' . json_encode($allUsers), 'error');
                return json(['success' => false, 'message' => '更新玩家身份失败']);
            }

            // 更新选择信息
            $draftInfo['selected_roles'][$draftInfo['current_seat']] = $roleId;
            $draftInfo['current_seat'] = $this->getNextSeat($draftInfo['current_seat'], $currentGame);

            // 保存更新
            $result = Db::table('game')->where('id', $currentGame['id'])->update([
                'game_info' => serialize($gameInfo),
                'all_user_info' => serialize($allUsers)
            ]);

            if (!$result) {
                trace('Failed to save game updates', 'error');
                return json(['success' => false, 'message' => '保存游戏更新失败']);
            }

            return json([
                'success' => true,
                'nextSeat' => $draftInfo['current_seat']
            ]);
        } catch (\Exception $e) {
            trace('Select role error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString(), 'error');
            return json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 随机选择角色
     */
    public function selectRandomRole()
    {
        try {
            $seatId = input('post.seatId');
            
            // 获取当前游戏信息
            $currentGame = Db::table('game')
                ->where('id', Db::table('setting')->where('id', 1)->value('current_game_id'))
                ->find();

            if (!$currentGame) {
                return json(['success' => false, 'message' => '游戏不存在']);
            }

            // 获取所有角色配置
            $roles = json_decode(Db::table('setting')->where('id', 1)->value('roles'), true);
            if (!$roles) {
                return json(['success' => false, 'message' => '角色配置不存在']);
            }

            // 获取已选择的角色
            $selectedRoles = [];
            $allUsers = unserialize($currentGame['all_user_info']);
            foreach ($allUsers as $user) {
                if (isset($user['identity'])) {
                    $selectedRoles[] = $user['identity'];
                }
            }

            // 收集所有可用角色
            $availableRoles = [];
            foreach ($roles as $camp => $campRoles) {
                foreach ($campRoles as $id => $roleName) {
                    if (!in_array($roleName, $selectedRoles)) {
                        $availableRoles[] = [
                            'id' => $id,
                            'name' => $roleName,
                            'camp' => $camp
                        ];
                    }
                }
            }

            if (empty($availableRoles)) {
                return json(['success' => false, 'message' => '没有可用的角色']);
            }

            // 随机选择一个角色
            $randomRole = $availableRoles[array_rand($availableRoles)];

            // 使用选择的角色调用 selectRole 函数
            return $this->selectRole([
                'roleId' => $randomRole['id'],
                'roleName' => $randomRole['name'],
                'roleCamp' => $randomRole['camp'],
                'seatId' => $seatId
            ]);

        } catch (\Exception $e) {
            trace('Random select role error: ' . $e->getMessage(), 'error');
            return json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 显示历史对局页面
     */
    public function matchHistory()
    {
        return view();
    }

    /**
     * 获取对局详情
     */
    public function getMatchDetail()
    {
        try {
            $gameId = input('get.game_id');
            if (!$gameId) {
                return json(['code' => 1, 'msg' => '参数错误']);
            }

            // 获取基本信息
            $game = Db::table('game')
                ->field('id, create_time, all_user_info, game_info')
                ->where('id', $gameId)
                ->find();

            if (!$game || empty($game['all_user_info'])) {
                return json(['code' => 1, 'msg' => '对局不存在']);
            }

            $allUserInfo = unserialize($game['all_user_info']);
            $gameInfo = unserialize($game['game_info']);
            
            if (!is_array($allUserInfo)) {
                return json(['code' => 1, 'msg' => '数据格式错误']);
            }

            // 整理玩家信息
            $players = [];
            foreach ($allUserInfo as $player) {
                if (isset($player['game_id'])) {
                    $players[] = [
                        'player_number' => $player['game_id'],
                        'identity' => $player['identity'] ?? '未知',
                        'group' => $player['group'] ?? '未知'
                    ];
                }
            }

            // 按玩家号码排序
            usort($players, function($a, $b) {
                return $a['player_number'] <=> $b['player_number'];
            });

            // 获取投票记录
            $voteHistory = [];
            if (isset($gameInfo['vote_detail']) && is_array($gameInfo['vote_detail'])) {  // 修改为 vote_detail
                foreach ($gameInfo['vote_detail'] as $round => $votes) {  // 修改为 vote_detail
                    if (is_array($votes)) {
                        $roundVotes = [];
                        foreach ($votes as $voter => $target) {
                            if (is_numeric($voter) && is_numeric($target)) {
                                $roundVotes[] = [
                                    'voter' => intval($voter),
                                    'target' => intval($target)
                                ];
                            }
                        }
                        if (!empty($roundVotes)) {
                            $voteHistory[] = [
                                'round' => intval($round) + 1,
                                'votes' => $roundVotes
                            ];
                        }
                    }
                }
            }

            // 获取游戏结果
            $winner = '';
            if (isset($gameInfo['winner'])) {
                $winner = $gameInfo['winner'] === '平局' ? '平局' : ($gameInfo['winner'] . '阵营胜利');
            }

            return json([
                'code' => 0,
                'data' => [
                    'game_id' => $game['id'],
                    'game_time' => date('Y-m-d H:i', strtotime($game['create_time'])),
                    'players' => $players,
                    'winner' => $winner,
                    'vote_history' => $voteHistory
                ],
                'msg' => 'success'
            ]);

        } catch (\Exception $e) {
            trace('Error in getMatchDetail: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => '获取对局详情失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 显示商城页面
     */
    public function shop()
    {
        return view();
    }

    /**
     * 获取商城商品列表
     */
    public function getShopItems()
    {
        try {
            $category = input('get.category', 'all');
            
            $query = Db::table('shop_items')
                ->field('id, name, price, image, description, stock, category, is_new')
                ->where('status', 1);
                
            if ($category !== 'all') {
                $query = $query->where('category', $category);
            }
            
            $items = $query->select();

            return json([
                'code' => 0,
                'data' => $items,
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取商品列表失败']);
        }
    }

    /**
     * 获取用户金币
     */
    public function getUserCoins()
    {
        try {
            $user = Session::get('user_login_suc');
            if (!$user) {
                return json(['code' => 1, 'msg' => '用户未登录']);
            }

            $coins = Db::table('users')
                ->where('id', $user['id'])
                ->value('coins');

            return json([
                'code' => 0,
                'data' => ['coins' => $coins ?: 0],
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取金币失败']);
        }
    }

    /**
     * 购买商品
     */
    public function buyShopItem()
    {
        try {
            $user = Session::get('user_login_suc');
            if (!$user) {
                return json(['code' => 1, 'msg' => '用户未登录']);
            }

            $itemId = input('post.item_id');
            if (!$itemId) {
                return json(['code' => 1, 'msg' => '参数错误']);
            }

            // 开启事务
            Db::startTrans();
            try {
                // 获取商品信息
                $item = Db::table('shop_items')
                    ->where('id', $itemId)
                    ->where('status', 1)
                    ->where('stock', '>', 0)
                    ->lock(true)
                    ->find();

                if (!$item) {
                    throw new \Exception('商品不存在或已售罄');
                }

                // 检查用户金币
                $userCoins = Db::table('users')
                    ->where('id', $user['id'])
                    ->value('coins');

                if ($userCoins < $item['price']) {
                    throw new \Exception('金币不足');
                }

                // 扣除金币
                Db::table('users')
                    ->where('id', $user['id'])
                    ->dec('coins', $item['price'])
                    ->update();

                // 减少库存
                Db::table('shop_items')
                    ->where('id', $itemId)
                    ->dec('stock')
                    ->update();

                // 记录购买记录
                Db::table('shop_orders')->insert([
                    'user_id' => $user['id'],
                    'item_id' => $itemId,
                    'price' => $item['price'],
                    'create_time' => date('Y-m-d H:i:s')
                ]);

                Db::commit();
                return json(['code' => 0, 'msg' => '购买成功']);
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 获取购买记录
     */
    public function getPurchaseHistory()
    {
        try {
            $user = Session::get('user_login_suc');
            if (!$user) {
                return json(['code' => 1, 'msg' => '用户未登录']);
            }

            $history = Db::table('shop_orders')
                ->alias('o')
                ->join('shop_items i', 'o.item_id = i.id')
                ->where('o.user_id', $user['id'])
                ->field('i.name, i.image, i.price, o.create_time as purchase_time')
                ->order('o.create_time', 'desc')
                ->limit(10)
                ->select();

            return json([
                'code' => 0,
                'data' => $history,
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取购买记录失败']);
        }
    }

    /**
     * 显示商城管理页面
     */
    public function shopAdmin()
    {
        // 检查管理员权限
        $user = Session::get('user_login_suc');
        if (!$user) {
            return '请先登录';
        }
        
        // 获取用户角色
        $userRole = Db::table('users')
            ->where('id', $user['id'])
            ->value('role');
        
        if ($userRole !== 'admin') {
            return '无权限访问';
        }
        
        return view();
    }

    /**
     * 获取商品列表（管理员）
     */
    public function getShopItemsAdmin()
    {
        try {
            $items = Db::table('shop_items')
                ->field('id, name, price, image, description, stock, category, status, is_new')
                ->select();

            return json([
                'code' => 0,
                'data' => $items,
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取商品列表失败']);
        }
    }

    /**
     * 获取单个商品信息
     */
    public function getShopItem()
    {
        try {
            $id = input('get.id');
            if (!$id) {
                return json(['code' => 1, 'msg' => '参数错误']);
            }

            $item = Db::table('shop_items')
                ->where('id', $id)
                ->find();

            return json([
                'code' => 0,
                'data' => $item,
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取商品信息失败']);
        }
    }

    /**
     * 保存商品信息
     */
    public function saveShopItem()
    {
        try {
            $id = input('post.id');
            $data = [
                'name' => input('post.name'),
                'price' => input('post.price'),
                'category' => input('post.category'),
                'stock' => input('post.stock'),
                'description' => input('post.description'),
                'is_new' => input('post.is_new', 0)
            ];

            // 处理图片上传
            $file = request()->file('image');
            if ($file) {
                $info = $file->move('static/shop/' . $data['category']);
                if ($info) {
                    $data['image'] = '/static/shop/' . $data['category'] . '/' . $info->getSaveName();
                }
            }

            if ($id) {
                // 更新
                Db::table('shop_items')->where('id', $id)->update($data);
            } else {
                // 新增
                $data['status'] = 1;
                Db::table('shop_items')->insert($data);
            }

            return json(['code' => 0, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '保存失败：' . $e->getMessage()]);
        }
    }

    /**
     * 上架/下架商品
     */
    public function toggleShopItem()
    {
        try {
            $id = input('post.id');
            $status = input('post.status');

            Db::table('shop_items')
                ->where('id', $id)
                ->update(['status' => $status]);

            return json(['code' => 0, 'msg' => '操作成功']);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '操作失败']);
        }
    }

    /**
     * 删除商品
     */
    public function deleteShopItem()
    {
        try {
            $id = input('post.id');
            Db::table('shop_items')->where('id', $id)->delete();
            return json(['code' => 0, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '删除失败']);
        }
    }

    /**
     * 检查是否是管理员
     */
    public function checkAdminRole()
    {
        try {
            $user = Session::get('user_login_suc');
            if (!$user) {
                return json(['code' => 1, 'msg' => '未登录']);
            }

            $role = Db::table('users')
                ->where('id', $user['id'])
                ->value('role');

            return json([
                'code' => 0,
                'data' => ['is_admin' => $role === 'admin'],
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '检查失败']);
        }
    }

    /**
     * 获取用户的投票框
     */
    public function getUserVoteFrame()
    {
        try {
            $user = Session::get('user_login_suc');
            if (!$user) {
                return json(['code' => 1, 'msg' => '未登录']);
            }

            // 从用户装备表获取当前装备的投票框
            $frame = Db::table('user_items')
                ->alias('ui')
                ->join('shop_items si', 'ui.item_id = si.id')
                ->where('ui.user_id', $user['id'])
                ->where('si.category', 'frame')
                ->where('ui.equipped', 1)
                ->value('si.image');

            return json([
                'code' => 0,
                'data' => ['frame' => $frame],
                'msg' => 'success'
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '获取失败']);
        }
    }

    /**
     * 增加经验值
     */
    protected function addExperience($userId, $exp)
    {
        try {
            // 获取用户当前经验和等级
            $user = Db::table('users')
                ->where('id', $userId)
                ->find();
            
            $newExp = $user['exp'] + $exp;
            
            // 检查是否可以升级
            $nextLevel = Db::table('level_config')
                ->where('exp_required', '<=', $newExp)
                ->order('level', 'desc')
                ->find();
                
            if ($nextLevel && $nextLevel['level'] > $user['level']) {
                // 升级并发放奖励
                Db::table('users')
                    ->where('id', $userId)
                    ->update([
                        'level' => $nextLevel['level'],
                        'exp' => $newExp,
                        'coins' => Db::raw('coins + ' . $nextLevel['rewards_coins']),
                        'silver_coins' => Db::raw('silver_coins + ' . $nextLevel['rewards_silver'])
                    ]);
                    
                return [
                    'levelUp' => true,
                    'newLevel' => $nextLevel['level'],
                    'rewards' => [
                        'coins' => $nextLevel['rewards_coins'],
                        'silver' => $nextLevel['rewards_silver']
                    ]
                ];
            } else {
                // 只增加经验
                Db::table('users')
                    ->where('id', $userId)
                    ->update([
                        'exp' => $newExp
                    ]);
                    
                return [
                    'levelUp' => false,
                    'expGained' => $exp
                ];
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('增加经验失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 游戏结束时奖励经验和金币
     */
    protected function giveGameRewards($userId, $isWinner, $performance = 0)
    {
        try {
            // 基础奖励
            $baseExp = 50;
            $baseCoins = 20;
            $baseSilver = 5;
            
            // 胜利加成
            if ($isWinner) {
                $baseExp *= 2;
                $baseCoins *= 2;
                $baseSilver *= 2;
            }
            
            // 表现加成 (0-100)
            $performanceBonus = $performance / 100;
            $baseExp = ceil($baseExp * (1 + $performanceBonus));
            $baseCoins = ceil($baseCoins * (1 + $performanceBonus));
            $baseSilver = ceil($baseSilver * (1 + $performanceBonus));
            
            // 更新用户金币
            Db::table('users')
                ->where('id', $userId)
                ->update([
                    'coins' => Db::raw('coins + ' . $baseCoins),
                    'silver_coins' => Db::raw('silver_coins + ' . $baseSilver)
                ]);
            
            // 增加经验并检查升级
            $expResult = $this->addExperience($userId, $baseExp);
            
            return [
                'exp' => $baseExp,
                'coins' => $baseCoins,
                'silver' => $baseSilver,
                'levelUpInfo' => $expResult
            ];
            
        } catch (\Exception $e) {
            \think\facade\Log::error('发放游戏奖励失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取每日任务
     */
    public function getDailyTasks()
    {
        try {
            $user = Session::get('user_login_suc');
            $today = date('Y-m-d');
            
            // 获取用户今日任务
            $tasks = Db::table('daily_tasks')
                ->where('user_id', $user['id'])
                ->whereTime('created_at', 'today')
                ->select();
                
            // 如果今天没有任务,则生成新任务
            if (empty($tasks)) {
                $tasks = $this->generateDailyTasks($user['id']);
            }
            
            return json(['code' => 0, 'data' => $tasks]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }
    
    /**
     * 生成每日任务
     */
    protected function generateDailyTasks($userId)
    {
        $taskTypes = Db::table('task_types')->select();
        $tasks = [];
        
        foreach ($taskTypes as $type) {
            $task = [
                'user_id' => $userId,
                'task_type' => $type['name'],
                'target' => $type['base_target'],
                'rewards_coins' => $type['base_coins'],
                'rewards_exp' => $type['base_exp']
            ];
            
            $taskId = Db::table('daily_tasks')->insertGetId($task);
            $task['id'] = $taskId;
            $tasks[] = $task;
        }
        
        return $tasks;
    }

    /**
     * 签到
     */
    public function signIn()
    {
        try {
            $user = Session::get('user_login_suc');
            $today = date('Y-m-d');
            
            // 检查今天是否已经签到
            $todaySign = Db::table('sign_in_records')
                ->where('user_id', $user['id'])
                ->whereTime('sign_date', 'today')
                ->find();
                
            if ($todaySign) {
                return json(['code' => 1, 'msg' => '今天已经签到过了']);
            }
            
            // 获取昨天的签到记录
            $yesterdaySign = Db::table('sign_in_records')
                ->where('user_id', $user['id'])
                ->whereTime('sign_date', 'yesterday')
                ->find();
                
            // 计算连续签到天数
            $daysStreak = $yesterdaySign ? $yesterdaySign['days_streak'] + 1 : 1;
            if ($daysStreak > 7) $daysStreak = 1;
            
            // 获取奖励配置
            $rewards = Db::table('sign_in_rewards')
                ->where('days', $daysStreak)
                ->find();
                
            // 记录签到
            Db::table('sign_in_records')->insert([
                'user_id' => $user['id'],
                'sign_date' => $today,
                'days_streak' => $daysStreak,
                'rewards_coins' => $rewards['coins'],
                'rewards_exp' => $rewards['exp']
            ]);
            
            // 发放奖励
            $this->addExperience($user['id'], $rewards['exp']);
            Db::table('users')
                ->where('id', $user['id'])
                ->update([
                    'coins' => Db::raw('coins + ' . $rewards['coins'])
                ]);
                
            return json([
                'code' => 0,
                'data' => [
                    'daysStreak' => $daysStreak,
                    'rewards' => $rewards
                ]
            ]);
            
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 获取用户状态信息
     */
    public function getUserStats()
    {
        try {
            $user = Session::get('user_login_suc');
            $stats = Db::table('users')
                ->where('id', $user['id'])
                ->field('level, coins, silver_coins, exp')
                ->find();
                
            return json([
                'code' => 0,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 获取签到信息
     */
    public function getSignInInfo()
    {
        try {
            $user = Session::get('user_login_suc');
            $today = date('Y-m-d');
            
            // 获取当前签到状态
            $currentStreak = Db::table('sign_in_records')
                ->where('user_id', $user['id'])
                ->whereTime('sign_date', 'today')
                ->find();
                
            // 获取所有奖励配置
            $rewards = Db::table('sign_in_rewards')
                ->select()
                ->toArray();
                
            // 如果今天没有签到记录，检查昨天的记录
            if (!$currentStreak) {
                $yesterdayStreak = Db::table('sign_in_records')
                    ->where('user_id', $user['id'])
                    ->whereTime('sign_date', 'yesterday')
                    ->find();
                    
                $currentDay = $yesterdayStreak ? ($yesterdayStreak['days_streak'] + 1) : 1;
                if ($currentDay > 7) $currentDay = 1;
            } else {
                $currentDay = $currentStreak['days_streak'];
            }
            
            // 构建奖励数据
            $rewardsData = [];
            foreach ($rewards as $reward) {
                $rewardsData[$reward['days']] = [
                    'coins' => $reward['coins'],
                    'exp' => $reward['exp']
                ];
            }
            
            // 确保有7天的数据
            for ($i = 1; $i <= 7; $i++) {
                if (!isset($rewardsData[$i])) {
                    $rewardsData[$i] = [
                        'coins' => 100 * $i,  // 默认奖励
                        'exp' => 50 * $i
                    ];
                }
            }
                    
            return json([
                'code' => 0,
                'data' => [
                    'currentDay' => $currentDay,
                    'hasSignedToday' => !empty($currentStreak),
                    'rewards' => $rewardsData
                ]
            ]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 每日面板页面
     */
    public function dailyPanel()
    {
        return View::fetch('index/daily_panel');
    }

    /**
     * 检查是否允许使用技能
     */
    public function queryIfAllowUseSkill()
    {
        try {
            $user = Session::get('user_login_suc');
            if (!$user) {
                return json()->data('not_in_game');
            }

            $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');
            if (!$game_id) {
                return json()->data('not_allow_use_skill');
            }

            $game = Db::table('game')->where('id', $game_id)->find();
            if (!$game) {
                return json()->data('not_allow_use_skill');
            }

            // 检查是否允许使用技能
            if ($game['if_allow_use_skill'] != 1) {
                return json()->data('not_allow_use_skill');
            }

            // 检查玩家是否在游戏中
            $all_user_info = unserialize($game['all_user_info']);
            $player_exists = false;
            foreach ($all_user_info as $player) {
                if ($player['user_id'] == $user['id']) {
                    $player_exists = true;
                    break;
                }
            }

            if (!$player_exists) {
                return json()->data('not_in_game');
            }

            return json()->data('allow');
        } catch (\Exception $e) {
            return json()->data('not_allow_use_skill');
        }
    }
}

