<?php
/**
 * Coke 微信实名认证插件
 * 版权所有 2023-2026 Coke
 * 保留所有权利
 */
namespace certification\wechat_coke;

class WechatCokePlugin extends \app\admin\lib\Plugin
{
    /**
     * 插件基本信息
     * @var array
     */
    public $info = [
        'name'        => 'WechatCoke',
        'title'       => '微信个人实名认证',
        'description' => '微信实名认证，腾讯云人脸识别，Coke开源版',
        'status'      => 1,
        'author'      => 'Coke',
        'version'     => '1.0',
        'help_url'    => 'https://cloud.tencent.com/product/faceid'
    ];

    /**
     * 安装插件
     * @return bool
     */
    public function install()
    {
        $sql = ["ALTER TABLE `shd_certifi_person` CHANGE `certify_id` `certify_id` varchar(64) NOT NULL DEFAULT '' COMMENT '认证证书'", "ALTER TABLE `shd_certifi_company` CHANGE `certify_id` `certify_id` varchar(64) NOT NULL DEFAULT '' COMMENT '认证证书'"];
        foreach ($sql as $v) {
            \think\Db::execute($v);
        }
        return true;
    }

    /**
     * 卸载插件
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 处理个人实名认证
     * @param array $certifi 认证信息数组，包含姓名和身份证号
     * @return string 返回认证界面HTML
     */
    public function personal($certifi)
    {
        $logic = new logic\WechatCoke();
        $res = $logic->getDetectAuth($certifi["name"], $certifi["card"]);
        $data = ["status" => 4, "auth_fail" => "", "certify_id" => "", "notes" => ""];
        if ($res["status"] == 200) {
            $resp = $res["data"];
            $certify_id = $resp["BizToken"];
            $data["certify_id"] = $certify_id;
            $url = htmlspecialchars_decode($resp["Url"]);
            $time = date("Y-m-d H:i:s", time());
            $data["notes"] = "微信记录号:" . $certify_id . ";\r\n" . "实名认证方式:" . $this->info["title"] . ";\r\n" . "实名认证接口提交时间:" . $time . "\r\n";
            $uid = request()->uid;
            $filename = md5($uid . "_zjmf_" . time()) . ".png";
            $file = WEB_ROOT . "upload/" . $filename;
            \cmf\phpqrcode\QRcode::png($url, $file);
            $base64 = base64EncodeImage($file);
            unlink($file);
            updatePersonalCertifiStatus($data);
            
            // 检测是否为移动设备
            $isMobile = $this->isMobile();
            $output = "<h5 class=\"pt-2 font-weight-bold h5 py-4\">请使用微信扫描二维码</h5>认证时间有3分钟，超时无效！！！";
            
            // 如果是移动设备但不是微信环境，显示免扫码提示
            if ($isMobile && !$this->isWechatH5()) {
                $output .= '<br><button type="submit" class="btn btn-primary w-xl submitBtn" onclick="window.location.href=\'' . htmlspecialchars($url, ENT_QUOTES) . '\';">可使用微信打开验证免扫码</button>';
            } elseif ($isMobile && $this->isWechatH5()) {
                // 如果是移动设备且是微信环境，直接跳转
                header("Location: " . $url);
                exit();
            }
            
            $output .= '<br><img height=\'200\' width=\'200\' src="' . $base64 . '" alt=""><br>扫描二维码完成验证后请返回此设备继续';
            return $output;
        }
        $data["auth_fail"] = $res["msg"] ?: "实名认证接口配置错误,请联系管理员";
        return "<h3 class=\"pt-2 font-weight-bold h2 py-4\"><img src=\"\" alt=\"\">" . $data["auth_fail"] . "</h3>";
    }
    
    /**
     * 获取插件收集的信息
     * @return array 返回空数组
     */
    public function collectionInfo()
    {
        return [];
    }
    
    /**
     * 获取认证状态
     * @param array $certifi 认证信息
     * @return array 认证状态
     */
    public function getStatus($certifi)
    {
        $logic = new logic\WechatCoke();
        $certify_id = $certifi["certify_id"];
        $res = $logic->getWechatAuthStatus($certify_id);
        return $res;
    }
    
    /**
     * 检测是否为移动设备
     * @return bool 是否为移动设备
     */
    private function isMobile()
    {
        $useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $ualist = ['android', 'midp', 'nokia', 'mobile', 'iphone', 'ipod', 'blackberry', 'windows phone', 'ipad'];
        foreach($ualist as $ua){
            if(strpos($useragent, $ua)!==false){
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检测是否为微信H5环境
     * @return bool 是否为微信环境
     */
    private function isWechatH5()
    {
        $useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return strpos($useragent, 'micromessenger') !== false;
    }
}

?>