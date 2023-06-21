<?php
namespace app\common\library\alipay\aop;
/**
 *   加密工具类
 *
 * User: jiehua
 * Date: 16/3/30
 * Time: 下午3:25
 */

class AopUtils{

    /**
     * 加密方法
     * @param string $str
     * @return string
     */
    function encrypt($str, $screct_key)
    {
        //AES, 128 模式加密数据 CBC
        $screct_key = base64_decode($screct_key);
        $str = trim($str);
        $str = addPKCS7Padding($str);

        //设置全0的IV

        $iv = str_repeat("\0", 16);
        $encrypt_str = openssl_encrypt($str, 'aes-128-cbc', $screct_key, OPENSSL_NO_PADDING, $iv);
        return base64_encode($encrypt_str);
    }

    /**
     * 解密方法
     * @param string $content 加密内容
     * @param string $escKey 加密内容
     * @return string 解密后的内容
     */
    function decrypt(string $content, string $escKey)
    {
        return openssl_decrypt(
            base64_decode($content),
            'AES-128-CBC',
            base64_decode($escKey),
            OPENSSL_RAW_DATA
        );
    }

    /**
     * 填充算法
     * @param string $source
     * @return string
     */
    function addPKCS7Padding($source)
    {
        $source = trim($source);
        $block = 16;

        $pad = $block - (strlen($source) % $block);
        if ($pad <= $block) {
            $char = chr($pad);
            $source .= str_repeat($char, $pad);
        }
        return $source;
    }

    /**
     * 移去填充算法
     * @param string $source
     * @return string
     */
    function stripPKSC7Padding($source)
    {
        $char = substr($source, -1);
        $num = ord($char);
        if ($num == 62) return $source;
        $source = substr($source, 0, -$num);
        return $source;
    }
}
