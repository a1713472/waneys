<?php
namespace app\index\route;

use think\facade\Route;

Route::rule('/','index/waitGame');
Route::rule('regi','index/regi');
Route::rule('reginew','index/regiNewUser');
Route::rule('login','index/login');
Route::rule('userLogin','index/userLogin');
Route::rule('queryNewGame','index/queryNewGame');
Route::rule('queryNewGameForAjax','index/queryNewGameForAjax');
Route::rule('userReady','index/userReady');
Route::rule('waitGame','index/waitGame');
Route::rule('joinTheGame','index/joinTheGame');
Route::rule('freshGameStat','index/freshGameStat');
Route::rule('gamePage','index/gamePage');
Route::rule('queryExistPlayer','index/queryExistPlayer');
Route::rule('queryGameStat','index/queryGameStat');
Route::rule('submitVote','index/submitVote');
Route::rule('queryIfAtGame','index/queryIfAtGame');
Route::rule('queryIfStartVote','index/queryIfStartVote');
Route::rule('queryVotedPlayer','index/queryVotedPlayer');
Route::rule('queryVoteDetail','index/queryVoteDetail');
Route::rule('queryIfGameEnd','index/queryIfGameEnd');
Route::rule('queryIfSelfOut','index/queryIfSelfOut');
Route::rule('queryIfSelfJoinGame','index/queryIfSelfJoinGame');
Route::rule('queryIfdenyJoinGame','index/queryIfdenyJoinGame');
Route::rule('querySelectedNumberPlayer', 'index/querySelectedNumberPlayer', 'GET');
Route::rule('querySelectedNumberStat', 'index/querySelectedNumberStat', 'GET');
Route::rule('updateSelectedNumber', 'index/updateSelectedNumber', 'POST');
Route::rule('sendIdentity','index/sendIdentity');
Route::rule('detectIfSelfBantalk','index/detectIfSelfBantalk');
Route::rule('detectIfSelfPrison','index/detectIfSelfPrison');
Route::rule('exitLogin','index/exitLogin');
Route::rule('queryJoinPlayer','index/queryJoinPlayer');
Route::rule('queryIfAllowUseSkill','index/queryIfAllowUseSkill');

Route::rule('zhentanSkill','index/zhentanSkill');
Route::rule('tonglingzheSkill','index/tonglingzheSkill');
Route::rule('binyiyuanSkill','index/binyiyuanSkill');
Route::rule('chaonenglizheSkill','index/chaonenglizheSkill');
Route::rule('jingzhangSkill','index/jingzhangSkill');
Route::rule('guanniaozheSkill','index/guanniaozheSkill');
Route::rule('cikeSkill','index/cikeSkill');
Route::rule('jiandieSkill','index/jiandieSkill');
Route::rule('wuyaSkill','index/wuyaSkill');
Route::rule('hepinggeSkill','index/hepinggeSkill');
Route::rule('jingyuzheSkill','index/jingyuzheSkill');
Route::rule('zhuizongzheSkill','index/zhuizongzheSkill');
Route::rule('jianyuzhangSkill','index/jianyuzhangSkill');
Route::rule('shushiSkill','index/shushiSkill');
Route::rule('gaomizheSkill','index/gaomizheSkill');
Route::rule('zhenyingeSkill','index/zhenyingeSkill');
Route::rule('zuijiuyaSkill','index/zuijiuyaSkill');
Route::rule('baobiaoSkill','index/baobiaoSkill');
Route::rule('meipoSkill','index/meipoSkill');
Route::rule('baozhawangSkill','index/baozhawangSkill');

Route::rule('playersOperation','index/playersOperation');
Route::rule('detectMnTaskScore','index/detectMnTaskScore');
Route::rule('showAnotherLover','index/showAnotherLover');
Route::rule('detectIfSettleAccount','index/detectIfSettleAccount');
Route::rule('queryPersonBill','index/queryPersonBill');
Route::rule('updateIfUserViewBill','index/updateIfUserViewBill');
Route::rule('queryRankingData','index/queryRankingData');
Route::rule('vote','index/vote');
Route::get('getUsers', 'Index/getUsers');
Route::rule('getUserInfo', 'index/getUserInfo', 'GET');
Route::rule('getHistoryMatches', 'index/getHistoryMatches');
Route::rule('getBillHistory', 'index/getBillHistory');

// 轮抽模式相关路由
Route::rule('index/getGameConfig', 'index/getGameConfig', 'GET');
Route::rule('index/checkDraftTurn', 'index/checkDraftTurn', 'GET');
Route::rule('index/getRandomRoles', 'index/getRandomRoles', 'GET');
Route::rule('index/selectRole', 'index/selectRole', 'POST');
Route::rule('index/selectRandomRole', 'index/selectRandomRole', 'POST');

// 用户信息相关路由
Route::rule('updateUserName', 'index/updateUserName', 'POST');

Route::rule('matchHistory', 'index/matchHistory');
Route::rule('getMatchDetail', 'index/getMatchDetail');

Route::rule('shop', 'index/shop');
Route::rule('getShopItems', 'index/getShopItems');
Route::rule('getUserCoins', 'index/getUserCoins');
Route::rule('buyShopItem', 'index/buyShopItem');

// 商城管理相关路由
Route::rule('shopAdmin', 'index/shopAdmin');
Route::rule('getShopItemsAdmin', 'index/getShopItemsAdmin');
Route::rule('getShopItem', 'index/getShopItem');
Route::rule('saveShopItem', 'index/saveShopItem');
Route::rule('toggleShopItem', 'index/toggleShopItem');
Route::rule('deleteShopItem', 'index/deleteShopItem');
Route::rule('checkAdminRole', 'index/checkAdminRole');
Route::rule('getUserVoteFrame', 'index/getUserVoteFrame');
Route::rule('getUserStats', 'index/getUserStats');
Route::rule('getSignInInfo', 'index/getSignInInfo');
Route::rule('signIn', 'index/signIn');
Route::rule('getDailyTasks', 'index/getDailyTasks');
Route::rule('dailyPanel', 'index/dailyPanel');