<?php

namespace app\admin\middleware;

use think\facade\Session;

class Check
{
    public function handle($request, \Closure $next)
    {
        //登录态不存在
        if(!Session::has('adm_login_suc')){
            //如果是登录页则不再跳转
            if($request->url()=='/admin/login'||$request->url()=='/admin/getlogininfo'){
                return $next($request);
            }
//            echo $request->url();
//            return redirect('/czkfz_adm/index/login');
            return redirect('/admin/login');
        }
        Session::set('adm_login_suc',Session::get('adm_login_suc'));
        return $next($request);
    }
}