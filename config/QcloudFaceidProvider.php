<?php
/**
 * Coke 微信实名认证插件
 * 版权所有 2023-2026 Coke
 * 保留所有权利
 */
namespace certification\wechat_coke\config;

use GuzzleHttp\Client;
use TencentCloud\Common\Credential;
use TencentCloud\Faceid\V20180301\FaceidClient;
use TencentCloud\Faceid\V20180301\Models\GetRealNameAuthResultRequest;

class QcloudFaceidProvider
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 获取实名认证结果
     * @param array $params 参数数组，包含SecretId、SecretKey等
     * @return string 认证结果JSON字符串
     */
    public function getRealNameAuthResult($params)
    {
        $cred = new Credential($params['SecretId'], $params['SecretKey']);
        $client = new FaceidClient($cred, "ap-beijing");
        $req = new GetRealNameAuthResultRequest();
        $req->fromJsonString(json_encode($params));
        $resp = $client->GetRealNameAuthResult($req);
        return $resp->toJsonString();
    }
}