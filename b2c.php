<?php
$appid = 'xxxxx';  //https://open.alipay.com 账户中心->密钥管理->开放平台密钥，填写添加了现金红包的应用的APPID
$alipayRootCertSn = 'xxxxx';     //支付宝根证书sn
$appCertSn = 'xxxxx';     //应用证书sn
$outTradeNo = uniqid();     //商户订单号，不能重复
$payAmount = 0.01;          //红包金额，单位:元
$orderName = '公益';          //红包标题
$signType = 'RSA2';			//签名算法类型，支持RSA2和RSA，推荐使用RSA2
$userid = '2088xxx'; //接收红包的支付宝用户id（2088开头的16位数字）
//商户私钥
$rsaPrivateKey='xxxxx';
$aliPay = new AlipayService();
$aliPay->setAppid($appid);
$aliPay->setAlipayRootCertSn($alipayRootCertSn);
$aliPay->setAppCertSn($appCertSn);
$aliPay->setRsaPrivateKey($rsaPrivateKey);
$aliPay->setNotifyUrl($notifyUrl);
$aliPay->setTotalFee($payAmount);
$aliPay->setOutTradeNo($outTradeNo);
$aliPay->setOrderName($orderName);
$aliPay->setUserId($userid);
$result = $aliPay->sendRedPacket();
if($result['alipay_fund_trans_uni_transfer_response']['code']==10000){
    echo '红包发送成功';
}else{
    echo '红包发送失败，原因：'.$result['alipay_fund_trans_uni_transfer_response']['msg'];
}
class AlipayService
{
    protected $appId;
    protected $alipayRootCertSn;
    protected $appCertSn;
    protected $notifyUrl;
    protected $charset;
    //私钥值
    protected $rsaPrivateKey;
    protected $totalFee;
    protected $outTradeNo;
    protected $orderName;
    protected $userid;
    public function __construct()
    {
        $this->charset = 'utf-8';
    }
    public function setAppid($appid)
    {
        $this->appId = $appid;
    }
    public function setAlipayRootCertSn($alipayRootCertSn)
    {
        $this->alipayRootCertSn = $alipayRootCertSn;
    }
    public function setAppCertSn($appCertSn)
    {
        $this->appCertSn = $appCertSn;
    }
    public function setNotifyUrl($notifyUrl)
    {
        $this->notifyUrl = $notifyUrl;
    }
    public function setRsaPrivateKey($saPrivateKey)
    {
        $this->rsaPrivateKey = $saPrivateKey;
    }
    public function setTotalFee($payAmount)
    {
        $this->totalFee = $payAmount;
    }
    public function setOutTradeNo($outTradeNo)
    {
        $this->outTradeNo = $outTradeNo;
    }
    public function setOrderName($orderName)
    {
        $this->orderName = $orderName;
    }
    public function setUserId($userid)
    {
        $this->userid = $userid;
    }
    /**
     * 发红包
     * @return array
     */
    public function sendRedPacket()
    {
        //请求参数
        $requestConfigs = array(
            'out_biz_no'=>$this->outTradeNo,
            'trans_amount'=>$this->totalFee, //单位 元
            'product_code'=>'STD_RED_PACKET',
            'biz_scene'=>'DIRECT_TRANSFER',
            'remark'=>$this->orderName,
            'order_title'=>$this->orderName,  //订单标题
            'payee_info'=>array(
                'identity'=>$this->userid,     //接受红包的用户id
                'identity_type'=>'ALIPAY_USER_ID',     //参与方的标识类型
            ),
            'business_params'=>array(
                'sub_biz_scene'=>'REDPACKET',       //子场景
            )
        );
        $commonConfigs = array(
            //公共参数
            'alipay_root_cert_sn' => $this->alipayRootCertSn,
            'app_cert_sn' => $this->appCertSn,
            'app_id' => $this->appId,
            'method' => 'alipay.fund.trans.uni.transfer',             //接口名称
            'format' => 'JSON',
            'charset'=>'utf-8',
            'sign_type'=>'RSA2',
            'timestamp'=>date('Y-m-d H:i:s'),
            'version'=>'1.0',
            'biz_content'=>json_encode($requestConfigs),
        );
        $commonConfigs["sign"] = $this->generateSign($commonConfigs, $commonConfigs['sign_type']);
        $result = $this->curlPost('https://openapi.alipay.com/gateway.do',$commonConfigs);
        return json_decode($result,true);
    }
    public function generateSign($params, $signType = "RSA") {
        return $this->sign($this->getSignContent($params), $signType);
    }
    protected function sign($data, $signType = "RSA") {
        $priKey=$this->rsaPrivateKey;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256); //OPENSSL_ALGO_SHA256是php5.4.8以上版本才支持
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }
    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }
    public function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, $this->charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }
    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
    public function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}