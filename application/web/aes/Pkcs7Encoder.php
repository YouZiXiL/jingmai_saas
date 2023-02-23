<?php

namespace app\web\aes;

use Symfony\Component\Yaml\Dumper;
use think\Exception;

/**
 * PKCS7Encoder class
 *
 * 提供基于PKCS7算法的加解密接口.
 */
class Pkcs7Encoder
{
	public static $block_size = 32;

    public $key;
    public function __construct ($key)
    {

        $this->key = $key;

    }

	/**
	 * 对需要加密的明文进行填充补位

	 * @return
	 */
	function encode($text)
	{
		$block_size = PKCS7Encoder::$block_size;
		$text_length = strlen($text);
		//计算需要填充的位数
		$amount_to_pad = PKCS7Encoder::$block_size - ($text_length % PKCS7Encoder::$block_size);
		if ($amount_to_pad == 0) {
			$amount_to_pad =$block_size;
		}
		//获得补位所用的字符
		$pad_chr = chr($amount_to_pad);
		$tmp = "";
		for ($index = 0; $index < $amount_to_pad; $index++) {
			$tmp .= $pad_chr;
		}
		return $text . $tmp;
	}

	/**
	 * 对解密后的明文进行补位删除
	 * @param decrypted 解密后的明文
	 * @return 删除填充补位后的明文
	 */
	function decode($text)
	{
		$pad = ord(substr($text, -1));
		if ($pad < 1 || $pad > 32) {
			$pad = 0;
		}
		return substr($text, 0, (strlen($text) - $pad));
	}



	function Prpcrypt($k)
	{
		$this->key = base64_decode($k . "=");
	}

	/**
	 * 对明文进行加密
	 * @param string $text 需要加密的明文
	 * @return string 加密后的密文
	 */
	public function encrypt($text, $appid)
	{


			//获得16位随机字符串，填充到明文之前
			$random = $this->getRandomStr();

			$text = $random . pack("N", strlen($text)) . $text . $appid;

			// 网络字节序
			//$size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);

			//$module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
			$iv = substr($this->key, 0, 16);

			//使用自定义的填充方式对明文进行补位填充
			$text = $this->encode($text);

            $res=openssl_encrypt($text, "AES-256-CBC", base64_decode($this->key.'='), OPENSSL_RAW_DATA,$iv);

            if ($res){
                return array(ErrorCode::$OK, base64_encode($res));
            }else{
                return array(ErrorCode::$EncryptAESError, null);
            }

			//mcrypt_generic_init($module, $this->key, $iv);
			//加密
			//$encrypted = mcrypt_generic($module, $text);
			//mcrypt_generic_deinit($module);
			//mcrypt_module_close($module);

			//print(base64_encode($encrypted));
			//使用BASE64对加密后的字符串进行编码

	}

	/**
	 * 对密文进行解密
	 * @param string $encrypted 需要解密的密文
	 * @return array|string
	 */
	public function decrypt($encrypted, $appid)
	{



		try {
			//使用BASE64对需要解密的字符串进行解码



			$iv = substr($this->key, 0, 16);


			//解密

            $decrypted = openssl_decrypt($encrypted,'AES-256-CBC',base64_decode($this->key.'='),OPENSSL_ZERO_PADDING,$iv);



		} catch (Exception $e) {
			return array(ErrorCode::$DecryptAESError, null);
		}


        try {
            //去除补位字符

            $result = $this->decode($decrypted);

            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));

            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);

            $from_appid = substr($content, $xml_len + 4);
        } catch (Exception $e) {
            //print $e;
            return array(ErrorCode::$IllegalBuffer, null);
        }
        if ($from_appid != $appid)
            return array(ErrorCode::$ValidateAppidError, null);
        return array(0, $xml_content);

	}


	/**
	 * 随机生成16位字符串
	 * @return string 生成的字符串
	 */
	function getRandomStr()
	{

		$str = "";
		$str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
		$max = strlen($str_pol) - 1;
		for ($i = 0; $i < 16; $i++) {
			$str .= $str_pol[mt_rand(0, $max)];
		}
		return $str;
	}

}

?>