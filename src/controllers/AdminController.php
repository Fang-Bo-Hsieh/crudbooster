<?php namespace crocodicstudio\crudbooster\controllers;

use crocodicstudio\crudbooster\controllers\Controller;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use CRUDBooster;

class AdminController extends CBController {

	public function myIndex() {
//		$myId = CRUDBooster::myId();
//		logger()->debug('$myId='.$myId);
//		$idCmsPrivileges = CRUDBooster::myPrivilegeId();
//		logger()->debug('$idCmsPrivileges='.$idCmsPrivileges);
//		$menus = DB::table('cms_menus')->where('is_dashboard',1)->where('id_cms_privileges',$idCmsPrivileges)->first();
//		if($menus) {
//			logger()->debug('$menus='.json_encode($menus));
//			if($menus->type == 'Statistic') {
//				return App::call('\crocodicstudio\crudbooster\controllers\StatisticBuilderController@getDashboard');
//			}elseif ($menus->type == 'Module') {
//				$module = CRUDBooster::first('cms_moduls',['path'=>$menus->path]);
//				return App::call('App\Http\Controllers\\'.$module->controller.'@getIndex');
//			}elseif ($menus->type == 'Route') {
//				$action = str_replace("Controller","Controller@",$menus->path);
//				$action = str_replace(['Get','Post'],['get','post'],$action);
//				logger()->debug('$action='.$action);
////				return App::call('App\Http\Controllers\\'.$action);
//			}elseif ($menus->type == 'Controller & Method') {
//				return App::call('App\Http\Controllers\\'.$menus->path);
//			}elseif ($menus->type == 'URL') {
//				return redirect($menus->path);
//			}
//		}
	}

	function getIndex() {
		$data = array();			
//		$data['page_title']       = '<strong>Dashboard</strong>';
		return view('crudbooster::home',$data);
	}

	public function getLockscreen() {
		
		if(!CRUDBooster::myId()) {
			Session::flush();
			return redirect()->route('getLogin')->with('message',trans('crudbooster.alert_session_expired'));
		}
		
		Session::put('admin_lock',1);
		return view('crudbooster::lockscreen');
	}

	public function postUnlockScreen() {
		$id       = CRUDBooster::myId();
		$password = Request::input('password');		
		$users    = DB::table(config('crudbooster.USER_TABLE'))->where('id',$id)->first();		

		if(\Hash::check($password,$users->password)) {
			Session::put('admin_lock',0);	
			return redirect(CRUDBooster::adminPath());
		}else{
			echo "<script>alert('".trans('crudbooster.alert_password_wrong')."');history.go(-1);</script>";				
		}
	}	

	public function getLogin()
	{							

		if(CRUDBooster::myId()) {
			return redirect(CRUDBooster::adminPath());
		}

		return view('crudbooster::login');
	}
 
	public function postLogin() {		

		$validator = Validator::make(Request::all(),			
			[
			'email'=>'required|email|exists:'.config('crudbooster.USER_TABLE'),
			'password'=>'required'			
			]
		);
		
		if ($validator->fails()) 
		{
			$message = $validator->errors()->all();
			return redirect()->back()->with(['message'=>implode(', ',$message),'message_type'=>'danger']);
		}

		$email 		= Request::input("email");
		$password 	= Request::input("password");
		$users 		= DB::table(config('crudbooster.USER_TABLE'))->where("email",$email)->first(); 		

		if(\Hash::check($password,$users->password)) {
			$priv = DB::table("cms_privileges")->where("id",$users->id_cms_privileges)->first();

			$roles = DB::table('cms_privileges_roles')
			->where('id_cms_privileges',$users->id_cms_privileges)
			->join('cms_moduls','cms_moduls.id','=','id_cms_moduls')
			->select('cms_moduls.name','cms_moduls.path','is_visible','is_create','is_read','is_edit','is_delete')
			->get();
			
			$photo = ($users->photo)?asset($users->photo):asset('vendor/crudbooster/avatar.jpg');
			Session::put('admin_id',$users->id);			
			Session::put('admin_is_superadmin',$priv->is_superadmin);
			Session::put('admin_name',$users->name);	
			Session::put('admin_photo',$photo);
			Session::put('admin_privileges_roles',$roles);
			Session::put("admin_privileges",$users->id_cms_privileges);
			Session::put('admin_privileges_name',$priv->name);			
			Session::put('admin_lock',0);
			Session::put('theme_color',$priv->theme_color);
			Session::put("appname",CRUDBooster::getSetting('appname'));		

			CRUDBooster::insertLog(trans("crudbooster.log_login",['email'=>$users->email,'ip'=>Request::server('REMOTE_ADDR')]));		

			$cb_hook_session = new \App\Http\Controllers\CBHook;
			$cb_hook_session->afterLogin();

			// 取得不同角色權限的menu，找出dashboard
			/**
			 * crud booster 5.4 menu dashboard限制
			 * 要新增不同privileges的dashboard步驟:
			 * 1.先確定該角色權限是否有進入該模塊的權限
			 * 1.先新增一個menu dashboard要設為0 (否則id_cmd_privileges會為1，會刪掉superadmin的dashboard)
			 * 2.進入編輯頁，將dashboard設為1
			 */
			$dashboard = CRUDBooster::sidebarDashboard();
			return  redirect($dashboard->url);
			//return redirect(CRUDBooster::adminPath());
		}else{
			return redirect()->route('getLogin')->with('message', trans('crudbooster.alert_password_wrong'));			
		}		
	}

	public function getForgot() {	
		if(CRUDBooster::myId()) {
			return redirect(CRUDBooster::adminPath());
		}
			
		return view('crudbooster::forgot');
	}

	public function postForgot() {
		$validator = Validator::make(Request::all(),			
			[
			'email'=>'required|email|exists:'.config('crudbooster.USER_TABLE')			
			]
		);
		
		if ($validator->fails()) 
		{
			$message = $validator->errors()->all();
			return redirect()->back()->with(['message'=>implode(', ',$message),'message_type'=>'danger']);
		}	

		$rand_string = str_random(5);
		$password = \Hash::make($rand_string);

		DB::table(config('crudbooster.USER_TABLE'))->where('email',Request::input('email'))->update(array('password'=>$password));
 	
		$appname = CRUDBooster::getSetting('appname');		
		$user = CRUDBooster::first(config('crudbooster.USER_TABLE'),['email'=>g('email')]);	
		$user->password = $rand_string;
		CRUDBooster::sendEmail(['to'=>$user->email,'data'=>$user,'template'=>'forgot_password_backend']);

		CRUDBooster::insertLog(trans("crudbooster.log_forgot",['email'=>g('email'),'ip'=>Request::server('REMOTE_ADDR')]));

		return redirect()->route('getLogin')->with('message', trans("crudbooster.message_forgot_password"));

	}	

	public function getLogout() {
		
		$me = CRUDBooster::me();
		CRUDBooster::insertLog(trans("crudbooster.log_logout",['email'=>$me->email]));

		Session::flush();
		return redirect()->route('getLogin')->with('message',trans("crudbooster.message_after_logout"));
	}

}
