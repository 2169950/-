<?php
/**
 * Coke 微信实名认证插件
 * 版权所有 2023-2026 Coke
 * 保留所有权利
 */
namespace certification\wechat_coke\logic;

class QcloudFaceid {
	private $SecretId;
	private $SecretKey;
	private $RuleId;
	private $endpoint = "faceid.tencentcloudapi.com";
	private $service = "faceid";
	private $version = "2018-03-01";

	/**
	 * 构造函数
	 * @param string $SecretId 腾讯云SecretId
	 * @param string $SecretKey 腾讯云SecretKey
	 * @param string $RuleId 业务流程ID
	 */
	function __construct($SecretId, $SecretKey, $RuleId){
        $this->SecretId = $SecretId;
        $this->SecretKey = $SecretKey;
		$this->RuleId = $RuleId;
    }

	/**
	 * 获取实名认证令牌
	 * @param string $Name 姓名
	 * @param string $IdCard 身份证号码
	 * @param string $RedirectUrl 重定向地址
	 * @return mixed 实名认证令牌
	 */
	public function GetRealNameAuthToken($Name, $IdCard, $RedirectUrl){
		$action = 'DetectAuth';
		$param = [
			'RuleId' => $this->RuleId,
			'IdCard' => $IdCard,
			'Name' => $Name,
			'RedirectUrl' => $RedirectUrl
		];
		return $this->send_request($action, $param);
	}

	/**
	 * 获取实名认证结果
	 * @param string $BizToken 业务令牌
	 * @param string $infoType 信息类型
	 * @return mixed 实名认证结果
	 */
	public function GetRealNameAuthResult($BizToken, $infoType = '1'){
		$action = 'GetDetectInfoEnhanced';
		$param = [
			'BizToken' => $BizToken,
			'RuleId' => $this->RuleId,
			'InfoType' => $infoType
		];
		return $this->send_request($action, $param);
	}

	/**
	 * 发送请求到腾讯云
	 * @param string $action 操作名称
	 * @param array $param 参数数组
	 * @return mixed 请求结果
	 */
	private function send_request($action, $param){
		$payload = json_encode($param);
		$time = time();
		$authorization = $this->generateSign($payload, $time);
		$header = [
			'Authorization: '.$authorization,
			'Content-Type: application/json; charset=utf-8',
			'X-TC-Action: '.$action,
			'X-TC-Timestamp: '.$time,
			'X-TC-Version: '.$this->version,
			'X-TC-Region: ap-beijing'
		];
		return $this->curl_post($payload, $header);
	}

	/**
	 * 生成签名
	 * @param string $payload 请求体
	 * @param int $time 时间戳
	 * @return string 生成的签名
	 */
	private function generateSign($payload, $time){
		$algorithm = "TC3-HMAC-SHA256";

		// step 1: build canonical request string
		$httpRequestMethod = "POST";
		$canonicalUri = "/";
		$canonicalQueryString = "";
		$canonicalHeaders = "content-type:application/json; charset=utf-8\n"."host:".$this->endpoint."\n";
		$signedHeaders = "content-type;host";
		$hashedRequestPayload = hash("SHA256", $payload);
		$canonicalRequest = $httpRequestMethod."\n"
			.$canonicalUri."\n"
			.$canonicalQueryString."\n"
			.$canonicalHeaders."\n"
			.$signedHeaders."\n"
			.$hashedRequestPayload;
		
		// step 2: build string to sign
		$date = gmdate("Y-m-d", $time);
		$credentialScope = $date."/".$this->service."/tc3_request";
		$hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
		$stringToSign = $algorithm."\n"
			.$time."\n"
			.$credentialScope."\n"
			.$hashedCanonicalRequest;
		
		// step 3: sign string
		$secretDate = hash_hmac("SHA256", $date, "TC3".$this->SecretKey, true);
		$secretService = hash_hmac("SHA256", $this->service, $secretDate, true);
		$secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
		$signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

		// step 4: build authorization
		$authorization = $algorithm
			." Credential=".$this->SecretId."/".$credentialScope
			.", SignedHeaders=content-type;host, Signature=".$signature;

		return $authorization;
	}

	/**
	 * 执行cURL POST请求
	 * @param string $payload 请求体
	 * @param array $header 请求头
	 * @return mixed 响应数据
	 */
	private function curl_post($payload, $header){
		$url = 'https://'.$this->endpoint.'/';
		$ch=curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		$json=curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if($httpCode==200){
			$arr=json_decode($json,true);
			return $arr['Response'];
		}else{
			return [
				'Error' => [
					'Code' => 'RequestFailed',
					'Message' => 'HTTP error: '.$httpCode
				]
			];
		}
	}
}