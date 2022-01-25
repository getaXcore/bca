<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 05/11/2018
 * Time: 13:22
 */

namespace App\Http\Controllers\Inq;


use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Log\Logging;
use App\Models\AuthModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Ixudra\Curl\Facades\Curl;

class InquiryController extends Controller
{
    private $corporateId;
    private $timeout; //seconds

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->corporateId = Config::get('constants.Auth.CorporateId');
        $this->timeout = Config::get('constants.timeout');
    }

    public function getBalance(Request $request){
        $Auth = new AuthController();
        $Auth->Authentication = $request->header("auth-app");
        $Access = $Auth->authApp();

        if ($Access['code'] == "200"){
            $paramVal = json_decode($request->getContent(),true);
            $accounts = $paramVal['account'];
            $method = 'GET';

            $Log = new Logging();

            $list = '';

            if (count($accounts) > 1){

                for ($i=0;$i<count($accounts);$i++){
                    $list .= $accounts[$i]."%2C";
                }

                $rListAcc = rtrim($list,'%2C');
                $url = $Auth->baseUrl.'/banking/v3/corporates/'.$this->corporateId.'/accounts/'.$rListAcc;
                $relativeUrl = '/banking/v3/corporates/'.$this->corporateId.'/accounts/'.$rListAcc;

            }else{
                $url = $Auth->baseUrl.'/banking/v3/corporates/'.$this->corporateId.'/accounts/'.$accounts[0];
                $relativeUrl = '/banking/v3/corporates/'.$this->corporateId.'/accounts/'.$accounts[0];
            }

            $timestamp = date('Y-m-d\TH:i:s.vP', time());

            //get signature
            $arAuth = $Auth->getSignature($method,$relativeUrl,'',$timestamp);
            $Signature = $arAuth['signature'];
            $Token = $arAuth['token'];

            //write log request
            $t = microtime(true);
            $micro = sprintf("%06d",($t - floor($t)) * 1000000);
            $d = new \DateTime( date('Y-m-d H:i:s.'.$micro, $t) );

            //return $request->getContent();
            $Log->writeln($d->format("Y-m-d H:i:s.u"),"BALANCE_REQUEST",$method." ".$relativeUrl,"stream-".date('Ymd').".log");

            $response = Curl::to($url)
                ->withHeader('Content-Type: application/json')
                ->withHeader('Authorization: Bearer '.$Token)
                ->withHeader('X-BCA-Key: '.$Auth->apiKey)
                ->withHeader('X-BCA-Timestamp: '.$timestamp)
                ->withHeader('X-BCA-Signature: '.$Signature)
                ->withTimeout($this->timeout)
                ->returnResponseObject()
                ->enableDebug($Log->pathTo.'debug-'.date('YmdHis').'.log')
                ->get();

            $content = $response->content;

            //write log response
            $t2 = microtime(true);
            $micro2 = sprintf("%06d",($t2 - floor($t2)) * 1000000);
            $d2 = new \DateTime( date('Y-m-d H:i:s.'.$micro2, $t2) );

            $Log->writeln($d2->format("Y-m-d H:i:s.u"),"BALANCE_RESPONSE",$content,"stream-".date('Ymd').".log");

            return $content;
        }else{
            return \response($Access,200);
        }



    }
    public function getStatement(Request $request){
        $Auth = new AuthController();
        $Auth->Authentication = $request->header("auth-app");
        $Access = $Auth->authApp();

        if ($Access['code'] == "200"){
            $paramVal = json_decode($request->getContent(),true);
            $account = $paramVal['account'];
            $startDate = $paramVal['startDate'];
            $endDate = $paramVal['endDate'];
            $method = 'GET';

            $Log = new Logging();

            if (!empty($startDate) && !empty($endDate)){

                $url = $Auth->baseUrl.'/banking/v3/corporates/'.$this->corporateId.'/accounts/'.$account.'/statements?StartDate='.$startDate.'&EndDate='.$endDate;
                $relativeUrl = '/banking/v3/corporates/'.$this->corporateId.'/accounts/'.$account.'/statements?EndDate='.$endDate.'&StartDate='.$startDate;

                $timestamp = date('Y-m-d\TH:i:s.vP', time());

                //get signature
                $arAuth = $Auth->getSignature($method,$relativeUrl,'',$timestamp);
                $Signature = $arAuth['signature'];
                $Token = $arAuth['token'];

                //write log request
                $t = microtime(true);
                $micro = sprintf("%06d",($t - floor($t)) * 1000000);
                $d = new \DateTime( date('Y-m-d H:i:s.'.$micro, $t) );

                $Log->writeln($d->format("Y-m-d H:i:s.u"),"STATEMENT_REQUEST",$method." ".$relativeUrl,"stream-".date('Ymd').".log");

                $response = Curl::to($url)
                    ->withHeader('Content-Type: application/json')
                    ->withHeader('Authorization: Bearer '.$Token)
                    ->withHeader('X-BCA-Key: '.$Auth->apiKey)
                    ->withHeader('X-BCA-Timestamp: '.$timestamp)
                    ->withHeader('X-BCA-Signature: '.$Signature)
                    ->withTimeout($this->timeout)
                    ->returnResponseObject()
                    ->enableDebug($Log->pathTo.'debug-'.date('YmdHis').'.log')
                    ->get();

                $content = $response->content;

                //write log response
                $t2 = microtime(true);
                $micro2 = sprintf("%06d",($t2 - floor($t2)) * 1000000);
                $d2 = new \DateTime( date('Y-m-d H:i:s.'.$micro2, $t2) );

                $Log->writeln($d2->format("Y-m-d H:i:s.u"),"STATEMENT_RESPONSE",$content,"stream-".date('Ymd').".log");

                return $content;
            }
        }else{
            return \response($Access,200);
        }
    }
}