<?php

namespace app\common\library\douyin\utils;

use app\common\library\douyin\config\Config;
use Exception;
use think\Cache;
use think\exception\PDOException;

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
     * 将抖音推送过来的component_ticket存储到缓存（有效期3个小时）
     * @param $ticket
     * @return void
     * @throws \think\Exception
     * @throws PDOException
     */
    public function setComponentTicket($ticket){
        Cache::store('redis')->set('jx:dy:ticket', $ticket, 10500);
        // 更新数据库的component_ticket
        $ticketDb = db('dy_ticket')->where('id',1)->value('ticket');
        if($ticket != $ticketDb){
            db('dy_ticket')->where('id',1)->update(['ticket' => $ticket]);
        }

    }


    /**
     * 获取第三方小程序access_token
     * @throws Exception
     */
    public function getComponentAccessToken(){
        return $this->_getComponentAccessToken();
    }

    /**
     * 获取第三方小程序access_token V1版本
     * @throws Exception
     */
    public function getComponentAccessTokenV1(){
        return $this->_getComponentAccessTokenV1();
    }

    /**
     * 存储授权小程序接口调用凭证authorizer_access_token，有效期为2小时（7200秒）
     * @param int $authId int agent_auth表中的id
     * @param string $accessToken
     * @param int $expiresIn
     * @return void
     */
    public function setAuthorizerAccessToken(int $authId, string $accessToken, int $expiresIn = 7100){
        Cache::store('redis')->set('jx:dy:authorizer_access_token:'.$authId, $accessToken, $expiresIn);
    }


    /**
     * 存储授权小程序接口调用凭证authorizer_access_token，有效期为2小时（7200秒）V1版本
     * @param int $authId int agent_auth表中的id
     * @param string $accessToken
     * @param int $expiresIn
     * @return void
     */
    public function setAuthorizerAccessTokenV1(int $authId, string $accessToken, int $expiresIn){
        Cache::store('redis')->set('jx:dy:authorizer_access_token_v1:'.$authId, $accessToken, $expiresIn-200);
    }

    /**
     * 清除授权小程序接口调用凭证authorizer_access_token，有效期为2小时（7200秒）V1版本
     * @param int $authId int agent_auth表中的id
     * @return void
     */
    public function clearAuthorizerAccessTokenV1(int $authId){
        Cache::store('redis')->rm('jx:dy:authorizer_access_token_v1:'.$authId);
    }


    /**
     * 存储刷新令牌authorizer_refresh_token，有效期30天（259200秒）
     * @param int $authId
     * @param string $refreshToken
     * @param int $expiresIn
     * @return void
     */
    public function setAuthorizerRefreshToken(int $authId, string $refreshToken, int $expiresIn = 2591000){
        Cache::store('redis')->set('jx:dy:authorizer_refresh_token:'.$authId, $refreshToken, $expiresIn);
    }

    /**
     * 存储刷新令牌authorizer_refresh_token，有效期30天（259200秒） V1
     * @param int $authId
     * @param string $refreshToken
     * @param int $expiresIn
     * @return void
     */
    public function setAuthorizerRefreshTokenV1(int $authId, string $refreshToken, int $expiresIn = 2591000){
        Cache::store('redis')->set('jx:dy:authorizer_refresh_token_v1:'.$authId, $refreshToken, $expiresIn);
    }

    /**
     * 获取授权小程序接口调用凭证authorizer_access_token
     * @param int $authId int agent_auth表中的id
     * @return mixed
     * @throws Exception
     */
    public function getAuthorizerAccessToken(int $authId){
        // 获取access_token
        $token = Cache::store('redis')->get('jx:dy:authorizer_access_token:'.$authId);
        if($token) return $token;
        // 获取refresh_token
        $refreshToken = Cache::store('redis')->get('jx:dy:authorizer_refresh_token:' . $authId);
        if($refreshToken){
            $result = $this->_reAuthorizerAccessToken($refreshToken);
        }else{
            $appid = db('agent_auth')->where('id', $authId)->value('app_id');
            // 获取授权码
            $authCode = $this->retrieveAuthCode($appid);
            $result = $this->_getAuthorizerAccessTokenByCode($authCode);
        }
        $this->setAuthorizerAccessToken($authId, $result['authorizer_access_token']);
        $this->setAuthorizerRefreshToken($authId, $result['authorizer_refresh_token']);
        return $result['authorizer_access_token'];
    }

    /**
     * 获取授权小程序接口调用凭证authorizer_access_token V1版本
     * @param int $authId int agent_auth表中的id
     * @return mixed
     * @throws Exception
     */
    public function getAuthorizerAccessTokenV1(int $authId){
        // 获取access_tokenfa_agent_blacklist
        $token = Cache::store('redis')->get('jx:dy:authorizer_access_token_v1:'.$authId);
        if($token) return $token;
        $appid = db('agent_auth')->where('id', $authId)->value('app_id');
        // 获取授权码
        $authCode = $this->retrieveAuthCode($appid);

        $result = $this->_getAuthorizerAccessTokenByCodeV1($authCode);
        $this->setAuthorizerAccessTokenV1($authId, $result['authorizer_access_token'],  $result['expires_in']);
        return $result['authorizer_access_token'];
    }

    /**
     * 验证消息签名
     * @param $timestamp, $nonce, $encrypt, $msgSignature
     *  @return bool
     */
    public static function verify($timestamp, $nonce, $encrypt, $msgSignature)
    {
        try {
            $values = array(Config::TOKEN, $timestamp, $nonce, $encrypt);
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
            $encodingAesKey = Config::AesKey;
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

    /**
     *  敏感数据解密
     * @param $encryptedData
     * @param $iv
     * @param $sessionKey
     */
    public function decryptData($encryptedData, $iv, $sessionKey){
        $aesCipher=base64_decode($encryptedData);
        $aesKey = base64_decode($sessionKey);
        $iv = base64_decode($iv);
        $decrypted = openssl_decrypt($aesCipher, 'AES-128-CBC', $aesKey, 1, $iv);
        return  $decrypted ?json_decode($decrypted, true): false;
    }

}