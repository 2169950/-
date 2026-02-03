<?php
/**
 * Coke 微信实名认证插件
 * 版权所有 2023-2026 Coke
 * 保留所有权利
 */
namespace certification\wechat_coke\logic;

class WechatCoke
{
    private $aop;
    public $_config;
    
    /**
     * 构造函数
     * 初始化配置信息
     */
    public function __construct()
    {
        try {
            $config = (new \certification\wechat_coke\WechatCokePlugin())->getConfig();
            $this->_config = $config;
            
            // 检查必要配置项是否存在
            $requiredFields = ['SecretId', 'SecretKey', 'RuleId'];
            foreach ($requiredFields as $field) {
                if (empty($this->_config[$field])) {
                    throw new \Exception("缺少必要的配置项: {$field}");
                }
            }
        } catch (\Exception $e) {
            error_log("WechatCoke初始化失败: " . $e->getMessage());
            // 如果配置加载失败，创建一个基本配置用于错误提示
            $this->_config = [
                'SecretId' => '',
                'SecretKey' => '',
                'RuleId' => '',
                'license_key' => ''
            ];
        }
    }
    
    /**
     * 获取检测认证信息
     * @param string $realname 真实姓名
     * @param string $idcard 身份证号码
     * @return array 认证结果数组
     */
    public function getDetectAuth($realname, $idcard)
    {
        // 检查授权信息
        $licenseKey = $this->_config['license_key'] ?? '';
        if (empty($licenseKey)) {
            return ["status" => 400, "msg" => "插件未授权，请联系管理员获取授权"];
        }
        
        // 检查必要配置项
        if (empty($this->_config['SecretId']) || empty($this->_config['SecretKey']) || empty($this->_config['RuleId'])) {
            return ["status" => 400, "msg" => "插件配置不完整，请检查SecretId、SecretKey和RuleId配置项"];
        }
        
        // 自动获取当前域名作为回调域名
        $callbackDomain = $this->getCurrentDomain();
        
        $redirectUrl = $callbackDomain . "/verified?action=personal&step=authstart&type=WechatCoke";
        
        try {
            $qcloud = new QcloudFaceid($this->_config["SecretId"], $this->_config["SecretKey"], $this->_config["RuleId"]);
            $result = $qcloud->GetRealNameAuthToken($realname, $idcard, $redirectUrl);
            if(!$result){
                return ["status" => 400, "msg" => "请求失败"];
            }elseif(isset($result['BizToken'])){
                return ["status" => 200, "data" => $result];
            }else{
                return ["status" => 400, "msg" => $result['Error']['Message'] ?? '未知错误'];
            }
        } catch (\Exception $e) {
            error_log("获取检测认证信息失败: " . $e->getMessage());
            return ["status" => 400, "msg" => "认证服务暂时不可用，请稍后再试"];
        }
    }
    
    /**
     * 获取当前域名
     * @return string 当前域名
     */
    private function getCurrentDomain()
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        return $protocol . $domain;
    }
    
    /**
     * 获取微信认证状态
     * @param string $certify_id 认证ID
     * @return array 认证状态结果
     */
    public function getWechatAuthStatus($certify_id)
    {
        // 检查授权信息
        $licenseKey = $this->_config['license_key'] ?? '';
        if (empty($licenseKey)) {
            return ["status" => 2, "msg" => "插件未授权，请联系管理员获取授权"];
        }
        
        $maxAttempts = 100; // 设置最大尝试次数
        $attempt = 0; // 初始化尝试次数
        $startTime = time(); // 记录开始时间

        // 使用渐进式延迟策略
        $delays = [1, 2, 3, 5, 10]; // 初期使用较短间隔快速响应成功结果，随后逐步增加间隔时间
        
        while ($attempt < $maxAttempts) {
            try {
                $qcloud = new QcloudFaceid($this->_config["SecretId"], $this->_config["SecretKey"], $this->_config["RuleId"]);
                $result = $qcloud->GetRealNameAuthResult($certify_id, '1'); // 使用InfoType参数获取文本类信息

                if (!$result) {
                    $status = 2;
                    $msg = "请求失败";
                    break; // 请求失败，直接退出
                } elseif (isset($result['Text'])) {
                    if (isset($result['Text']['ErrCode']) && $result['Text']['ErrCode'] == 0) {
                        $status = 1;
                        $msg = "认证通过";
                        break; // 认证成功，退出循环
                    } else {
                        $status = 2;
                        $msg = $result["Text"]["ErrMsg"] ?? "未认证";
                        
                        // 如果是确定性错误，提前终止轮询
                        if (isset($result['Error']) && in_array($result['Error']['Code'], [
                            'InvalidParameterValue.RuleIdNotExist',
                            'InvalidParameterValue.RuleIdDisabled',
                            'UnauthorizedOperation.Nonactivated',
                            'UnauthorizedOperation.RegionNotSupported'
                        ])) {
                            $msg = $result["Error"]["Message"];
                            break; // 不可恢复的错误，直接退出
                        }
                    }
                } elseif (isset($result['Error'])) {
                    $errorCode = $result["Error"]["Code"] ?? '';
                    $msg = $result["Error"]["Message"];
                    
                    // 对于不可恢复的错误，提前终止轮询
                    if (in_array($errorCode, [
                        'InvalidParameterValue.RuleIdNotExist',
                        'InvalidParameterValue.RuleIdDisabled',
                        'UnauthorizedOperation.Nonactivated',
                        'UnauthorizedOperation.ActivateError',
                        'AuthFailure.InvalidAuthorization'
                    ])) {
                        $status = 2;
                        break; // 不可恢复的错误，直接退出
                    }
                    
                    $status = 2;
                } else {
                    $msg = "认证状态未知";
                    $status = 2;
                }
            } catch (\Exception $e) {
                error_log("获取认证状态失败: " . $e->getMessage());
                $msg = "认证服务暂时不可用，请稍后再试";
                $status = 2;
                break;
            }

            $attempt++; // 增加尝试次数
            if ($attempt >= $maxAttempts) {
                $msg = "认证超时未完成，认证失败";
                break; // 达到最大尝试次数，退出循环
            }
            
            // 根据当前尝试次数选择延迟时间
            $delayIndex = min($attempt - 1, count($delays) - 1);
            $delay = $delays[$delayIndex];
            sleep($delay); // 渐进式延迟
        }

        return ["status" => $status, "msg" => $msg];
    }
}