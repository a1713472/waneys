<?php

namespace app\index\middleware;

use think\facade\Session;

class Check
{
    public function handle($request, \Closure $next)
    {
        //登录态不存在
        if (!Session::has('user_login_suc')) {
            //如果是登录页则不再跳转
            if ($request->url() == '/index/login' || $request->url() == '/index/regi' || $request->url() == '/index/reginew' || $request->url() == '/index/userLogin') {
                return $next($request);
            }
//            echo $request->url();
//            return redirect('/czkfz_adm/index/login');
            return redirect('/index/login');
        }
        Session::set('user_login_suc', Session::get('user_login_suc'));
        return $next($request);
    }
}