<?php
/**
 * Coke 微信实名认证插件
 * 版权所有 2023-2026 Coke
 * 保留所有权利
 */
return [
    "amount" => [
        "title" => "金额", 
        "type" => "text", 
        "value" => 0, 
        "tip" => "支付金额"
    ], 
    "free" => [
        "title" => "免费认证次数", 
        "type" => "text", 
        "value" => 0, 
        "tip" => "免费认证次数"
    ],
    "RuleId" => [
        "title" => "业务流程ID", 
        "type" => "text", 
        "value" => "", 
        "tip" => ""
    ], 
    "SecretId" => [
        "title" => "SecretId", 
        "type" => "text", 
        "value" => "", 
        "tip" => ""
    ], 
    "SecretKey" => [
        "title" => "SecretKey", 
        "type" => "text", 
        "value" => "", 
        "tip" => ""
    ], 
    "callback_domain" => [
        "title" => "回调域名", 
        "type" => "text", 
        "value" => "", 
        "tip" => "用于接收认证结果的回调域名"
    ],
    "qq_number" => [
        "title" => "请输入Coke的QQ号", 
        "type" => "text", 
        "value" => "", 
        "tip" => "请输入Coke的QQ号进行授权验证"
    ]
];