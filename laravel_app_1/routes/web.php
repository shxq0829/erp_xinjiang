<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('index');
});
//Route::get('/reservation', function () {
//    return view('index');
//});
Route::any('sendsmscode', ['uses' => 'Aj\SmscodeController@sendSmsCode']);
Route::any('save', ['uses' => 'Aj\SmscodeController@save']);
Route::any('modifyweight', ['uses' => 'Aj\SmscodeController@weight']);
Route::any('admin', ['uses' => 'Aj\SmscodeController@admin']);
Route::any('modifytotal', ['uses' => 'Aj\SmscodeController@modifyTotal']);
Route::any('addtotal', ['uses' => 'Aj\SmscodeController@addTotal']);
Route::any('delete', ['uses' => 'Aj\SmscodeController@deleteUser']);
//Route::any('setsession', ['uses' => 'Aj\SmscodeController@setSession']);
//Route::any('getsession', ['uses' => 'Aj\SmscodeController@getSession']);
Route::any('total', ['uses' => 'Aj\SmscodeController@total']);
Route::any('access', ['uses' => 'AccessController@access']);
Route::any('export', ['uses' => 'AccessController@export']);
Route::any('menu', ['uses' => 'AccessController@menu']);
Route::any('addquestion', ['uses' => 'AccessController@addQuestion']);
Route::any('getautoreply', ['uses' => 'AccessController@getAutoReply']);
Route::any('exportuser', ['uses' => 'ExportController@export']);
Route::any('reservation', ['uses' => 'IndexController@reservation']);
Route::any('reservationadmin', ['uses' => 'IndexController@reservationAdmin']);

Route::any('addClient', ['uses' => 'AccessController@addClient']);

// Admin 后台
Route::any('admin/wechat/login', ['uses' => 'AdminController@index']);
Route::any('aj/admin/wechat/login', ['uses' => 'AdminController@login']);

Route::any('aj/admin/wechat/logout', ['uses' => 'AdminController@logout']);

Route::any('admin/wechat/signin', ['uses' => 'AdminController@signIn']);

Route::any('admin/wechat/giftcode', ['uses' => 'AdminController@index']);
//Route::any('aj/admin/wechat/giftcode/upload', ['uses' => 'AdminController@uploadFile']);
Route::any('aj/admin/wechat/giftcode/list', ['uses' => 'AdminController@getGiftKeywordList']);
Route::any('aj/admin/wechat/giftcode/add', ['uses' => 'AdminController@uploadFile']);
Route::any('aj/admin/wechat/giftcode/delete', ['uses' => 'AdminController@deleteGiftKeyword']);

Route::any('admin/wechat/autoreply', ['uses' => 'AdminController@index']);
Route::any('aj/admin/wechat/autoreply/add', ['uses' => 'AdminController@addAutoReplyKeyword']);
Route::any('aj/admin/wechat/autoreply/delete', ['uses' => 'AdminController@deleteAutoReplyKeyword']);
Route::any('aj/admin/wechat/autoreply/list', ['uses' => 'AdminController@getAutoReplyList']);

Route::any('admin/wechat/dailyquestion', ['uses' => 'AdminController@index']);
Route::any('aj/admin/wechat/question/add', ['uses' => 'AdminController@addQuestion']);
Route::any('aj/admin/wechat/question/delete', ['uses' => 'AdminController@deleteQuestion']);
Route::any('aj/admin/wechat/question/list', ['uses' => 'AdminController@getQuestionList']);

Route::any('admin/wechat/clickreply', ['uses' => 'AdminController@index']);
Route::any('admin/wechat/custommenu', ['uses' => 'AdminController@index']);
Route::any('aj/admin/wechat/taset/add', ['uses' => 'AdminController@addTasetKeyword']);
Route::any('aj/admin/wechat/taset/delete', ['uses' => 'AdminController@deleteTasetKeyword']);
Route::any('aj/admin/wechat/taset/list', ['uses' => 'AdminController@getTasetList']);

Route::any('admin/wechat/mallexport', ['uses' => 'AdminController@index']);

Route::any('aj/admin/wechat/menu', ['uses' => 'AdminController@menu']);
Route::any('aj/admin/wechat/clearcache', ['uses' => 'AdminController@clearCache']);
Route::any('aj/admin/wechat/getmenu', ['uses' => 'AdminController@getMenu']);
Route::any('aj/admin/wechat/userexport', ['uses' => 'AdminController@userExport']);
Route::any('aj/admin/wechat/giftexport', ['uses' => 'AdminController@giftExport']);
Route::any('aj/admin/wechat/modifyBonus', ['uses' => 'AdminController@modifyBonus']);

Route::any('admin/wechat/followreply', ['uses' => 'AdminController@index']);

