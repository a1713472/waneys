<?php
declare (strict_types = 1);

namespace app\cdgames\controller;

use think\facade\Db;

class Index
{
    public function eAddMiniTaskScore()
    {
        $game_id = Db::table('setting')->where('id', 1)->value('current_game_id');

        $game = Db::table('game')->where([
            'id' => $game_id
        ])->find();

        $game_info = unserialize($game['game_info']);

        $game_info['mini_task_score'] += 10;

        Db::table('game')->where([
            'id' => $game_id
        ])->update([
            'game_info' => serialize($game_info)
        ]);
    }
}
