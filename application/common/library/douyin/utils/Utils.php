<?php

namespace app\common\library\douyin\utils;

use app\common\library\douyin\Douyin;
use app\common\library\douyin\DyConfig;
use Exception;

class Utils
{
    use Common;
    /**
     * 获取component_ticket
     * @return bool|string
     * @throws \Exception
     */
    public function getComponentTicket(){
        return $this->_getComponentTicket();
    }


    /**
     * 获取第三方小程序access_token
     * @throws Exception
     */
    public function getComponentAccessToken(){
        return $this->_getComponentAccessToken();
    }

    /**
     * 验证消息签名
     * @param $timestamp, $nonce, $encrypt, $msgSignature
     *  @return bool
     */
    public static function verify($timestamp, $nonce, $encrypt, $msgSignature)
    {
        try {
            $values = array(DyConfig::TOKEN, $timestamp, $nonce, $encrypt);
            sort($values, SORT_STRING);
            $newMsgSignature = sha1(join("", $values));
            if ($newMsgSignature == $msgSignature) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            recordLog('dy-CheckSign', $e->getMessage());
            return false;
        }
    }


    /**
     * 对密文进行解密
     * @param $encrypted string 需要解密的字符串
     * @return bool|string
     * @throws Exception
     */
    public function decrypt(string $encrypted)
    {
        try {
            $encodingAesKey = DyConfig::AesKey;
            $aesKey = base64_decode($encodingAesKey . "=");
            // 使用BASE64对需要解密的字符串进行解码
            $ciphertextDec = base64_decode($encrypted);
            $iv = substr($aesKey, 0, 16);
            // 解密
            $decrypted = openssl_decrypt($ciphertextDec, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
        } catch (Exception $e) {
            throw new Exception('AesEncryptUtil AES解密串非法，小于16位;');
        }
        try {
            // 去除补位字符
            $result = $this->decode($decrypted);
            // 去除16位随机字符串,网络字节序和 tp appid
            if (strlen($result) < 16) {
                throw new Exception('AesEncryptUtil AES解密串非法，小于16位;');
            }
            // 去除16位随机字符串
            $content = substr($result, 16, strlen($result));

            // 获取消息体长度
            $lenList = unpack("N", substr($content, 16, 4));
            $postBodyLen = $lenList[1];

            // 获取消息体
            $postBodyMsg = substr($content, 20, $postBodyLen);

//            // 获取消息体的第三方平台 appid
//            $fromTpAppId = substr($content, 20 + $postBodyLen);
//            recordLog('dy-ticket', '第三方小程序应用APPID：' . $fromTpAppId);
        } catch (Exception $e) {
            throw new Exception('AesEncryptUtil AES解密串非法，小于16位;');
        }
        return $postBodyMsg;
    }


    /**
     * 对解密后的明文进行补位删除
     * @param $text
     * @return bool|string
     */
    private function decode($text)
    {
        $blockSize = 32;
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > $blockSize) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }
}