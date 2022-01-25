<?php
/**
 * Created by PhpStorm.
 * User: Taufan
 * Date: 14/11/2018
 * Time: 8:37
 */

namespace App\Http\Controllers\Tran;


use App\Http\Controllers\Controller;
use App\Http\Utilities\MyUtil;
use Illuminate\Http\Request;
use App\Models\TransModel;
use Illuminate\Support\Facades\Config;
use Ixudra\Curl\Facades\Curl;
use App\Http\Controllers\Log\Logging;
use App\Http\Controllers\Auth\AuthController;

class TransferController extends Controller
{
    private $corporateId;
    private $timeout;

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
        $this->corporateId = Config::get('constants.Auth.CorporateId');
        $this->timeout = Config::get('constants.timeout');
    }

    public function doTransfer(Request $request){
        $Auth = new AuthController();
        $Auth->Authentication = $request->header("auth-app");
        $Access = $Auth->authApp();

        if ($Access['code'] == "200"){
            $paramVal = json_decode($request->getContent(),true);

            $sourceAccount = $paramVal['SourceAccount'];
            $destAccount = $paramVal['DestinationAccount'];
            $reference = $paramVal['ReferenceNumber'];
            $dtTrans = date('Y-m-d');
            $currency = $paramVal['CurrencyCode'];
            $amount = $paramVal['Amount'];
            $remark1 = $paramVal['remark1'];
            $remark2 = $paramVal['remark2'];
            $method = 'POST';

            $Log = new Logging();

            $url = $Auth->baseUrl.'/banking/corporates/transfers';
            $relativeUrl = '/banking/corporates/transfers';

            $timestamp = date('Y-m-d\TH:i:s.vP', time());

            //for transaction id
            $maxID = TransModel::max('id');
            if(empty($maxID)){
                $maxID = 1;
            }
            $generateId = new MyUtil();
            $generateId->defaultId = $maxID;
            $transactionId = $generateId->customId();

            $body = array(
                "CorporateID" => strtoupper($this->corporateId),
                "SourceAccountNumber" => $sourceAccount,
                "TransactionID" => $transactionId,
                "TransactionDate" => $dtTrans,
                "ReferenceID" => $reference,
                "CurrencyCode" => $currency,
                "Amount" => $amount,
                "BeneficiaryAccountNumber" => $destAccount,
                "Remark1" => str_replace(' ','',$remark1),
                "Remark2" => str_replace(' ','',$remark2)
            );

            $jBody = json_encode($body);


            //get signature
            $arAuth = $Auth->getSignature($method,$relativeUrl,$jBody,$timestamp);
            $Signature = $arAuth['signature'];
            $Token = $arAuth['token'];

            //write log request
            $t = microtime(true);
            $micro = sprintf("%06d",($t - floor($t)) * 1000000);
            $d = new \DateTime( date('Y-m-d H:i:s.'.$micro, $t) );

            $Log->writeln($d->format("Y-m-d H:i:s.u"),"TRANSFER_REQUEST",$method." ".$relativeUrl."/".$jBody,"stream-".date('Ymd').".log");

            $response = Curl::to($url)
                ->withHeader('Content-Type: application/json')
                ->withHeader('Authorization: Bearer '.$Token)
                ->withHeader('X-BCA-Key: '.$Auth->apiKey)
                ->withHeader('X-BCA-Timestamp: '.$timestamp)
                ->withHeader('X-BCA-Signature: '.$Signature)
                ->withData($jBody)
                ->withTimeout($this->timeout)
                ->returnResponseObject()
                ->enableDebug($Log->pathTo.'debug-'.date('YmdHis').'.log')
                ->post();

            $content = $response->content;

            //write log response
            $t2 = microtime(true);
            $micro2 = sprintf("%06d",($t2 - floor($t2)) * 1000000);
            $d2 = new \DateTime( date('Y-m-d H:i:s.'.$micro2, $t2) );

            $Log->writeln($d2->format("Y-m-d H:i:s.u"),"TRANSFER_RESPONSE",$content,"stream-".date('Ymd').".log");

            $arContent = json_decode($content,true);

            if (isset($arContent['ErrorCode'])){
                $status = $arContent['ErrorCode']." ".$arContent['ErrorMessage']['Indonesian'];
            }elseif (isset($arContent['Status'])){
                $status = $arContent['Status'];
            }else{
                $status = '';
            }

            //save to database
            $Trans = new TransModel();
            $Trans->transaction_id = $transactionId;
            $Trans->corporate_id = $this->corporateId;
            $Trans->source_account_number = $sourceAccount;
            $Trans->destination_account_number = $destAccount;
            $Trans->reference_id = $reference;
            $Trans->currency_code = $currency;
            $Trans->amount = $amount;
            $Trans->remarks_1 = $remark1;
            $Trans->remarks_2 = $remark2;
            $Trans->transaction_date = $dtTrans;
            $Trans->status = $status;
            $Trans->save();

            return $content;

        }else{
            return \response($Access,200);
        }


    }

}