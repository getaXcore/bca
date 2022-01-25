<?php

namespace App\Http\Controllers\Auth;

use App\Models\AuthModel;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Ixudra\Curl\Facades\Curl;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Credential for Authorization to get Access Token from BCA API
     */
    public $apiKey;
    protected $apiSecret;
    protected $clientId;
    protected $clientSecret;
    protected $authBase64;
    public $baseUrl;
    protected $accessToken;
    protected $expireIn;
    protected $token;
    public $Authentication;
    public $Method;
    public $RelativeUrl;
    public $Timestamp;
    public $RequestBody;


    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');

        $this->apiKey = Config::get('constants.Auth.ApiKey');
        $this->apiSecret = Config::get('constants.Auth.ApiSecret');
        $this->clientId = Config::get('constants.Auth.ClientId');
        $this->clientSecret = Config::get('constants.Auth.ClientSecret');
        $this->authBase64 = Config::get('constants.Auth.AuthBase64');
        $this->baseUrl = Config::get('constants.Urls.Dev.BaseUrl');
        $this->token = Config::get('constants.Auth.AppToken');

        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    /**
     * Authorization to get Access Token from BCA API
     */
    public function auth(){
        $url = $this->baseUrl.Config::get('constants.Urls.Dev.TokenUrl');
        $response = Curl::to($url)
            ->withHeader('Content-Type: application/x-www-form-urlencoded')
            ->withHeader('Authorization: Basic '.$this->authBase64)
            ->withData(array('grant_type'=>'client_credentials'))
            ->returnResponseObject()
            //->enableDebug('/var/www/wordpress/bca/myApis/logs/stream.log')
            ->post();

        $content = json_decode($response->content);
        //$content = json_decode('{ "access_token":"2YotnFZFEjr1zCsicMWpAA", "token_type":"bearer", "expires_in":3600, "scope":"resource.WRITE resource.READ" }');

        $this->accessToken = $content->access_token;
        $this->expireIn = $content->expires_in; //in seconds

        $time = date('H:i:s');
        $timestamp = strtotime($time) + $this->expireIn;
        $uptime = date('H:i:s', $timestamp);


        $Auth = new AuthModel();
        $Auth->token = $this->accessToken;
        $Auth->created = date('Y-m-d H:i:s');
        $Auth->expired = date('Y-m-d').' '.$uptime;
        $Auth->save();

        return $content->access_token;

    }

    public function checkExpireOf(){
        $now = date('Y-m-d H:i:s');

        $Auth = AuthModel::orderBy('expired','desc')
            ->first();

       $DateTimeNow = new \DateTime($now);
        $DateTimeLast = new \DateTime($Auth->expired);

        if ($DateTimeNow <= $DateTimeLast){
            $isExpired = 0; //not expired
        }else{
            $isExpired = 1;
        }

        $content = array("isExpired" => $isExpired,"token"=>$Auth->token);

        return $content;
    }

    public function signature(Request $request){
        //$paramVal = json_decode($request,true);
        $Auth = AuthModel::orderBy('expired','desc')
            ->count();

        if ($Auth > 0){
            $data = $this->checkExpireOf();

           // print_r($data);

           if ($data['isExpired'] == 1){
                $token = $this->auth();
            }else{
                $token = $data['token'];
            }

        }else{
            $token = $this->auth();
        }

        //print_r($token);

        $HTTPMethod = "GET"; /*$paramVal['method'];*/
        $relativeUrl = "/banking/v3/corporates/h2hauto008/accounts/0613005827";/*$paramVal['rUrl'];*/
        $dBody = ""; /*$paramVal['dBody']*/
        $reqBody = strtolower(hash('sha256',$dBody,false));
        $timestamp = "2018-11-13T10:03:09.000+07:00"; /*$paramVal['timestamp'];*/

        $StringToSignin = $HTTPMethod.":".$relativeUrl.":".$token.":".$reqBody.":".$timestamp;

        $signature = hash_hmac('sha256',$StringToSignin,$this->apiSecret,false);

        $content = array('token'=>$token,'signature'=>$signature);

        return $content;

    }

    public function getSignature(){

        /*$Auth = AuthModel::orderBy('expired','desc')
            ->count();

        if ($Auth > 0){
            $data = $this->checkExpireOf();

            // print_r($data);

            if ($data['isExpired'] == 1){ //expired
                $token = $this->auth(); //get new token
            }else{
                $token = $data['token']; //get token from db
            }

        }else{
            $token = $this->auth();
        }*/
        $token = '';

        $HTTPMethod = $this->Method;
        $relativeUrl = $this->RelativeUrl;
        //$reqBody = strtolower(bin2hex(hash('sha256',$request_body,true)));
        $reqBody = strtolower(hash('sha256',$this->RequestBody,false));
        $timestamp = $this->Timestamp;

        $StringToSignin = $HTTPMethod.":".$relativeUrl.":".$token.":".$reqBody.":".$timestamp;

        $signature = hash_hmac('sha256',$StringToSignin,$this->apiSecret,false);

        $content = array('token'=>$token,'signature'=>$signature);

        return $content;

    }

    /**
     * Authorize App
     */
    public function authApp(){

        if ($this->Authentication == $this->token){
            $content = array("code"=>"200","message"=>"Access Acceptable");
        }else{
            $content = array("code"=>"403","message"=>"Access Forbidden");
        }

        return $content;
    }

}
