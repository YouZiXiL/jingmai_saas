<?php

namespace app\web\library\ali;



class Util
{

    /**
     * 从证书中提取序列号
     * @param $certPath
     * @return string
     */
    public function getCertSN($certPath): string
    {
        $cert = file_get_contents($certPath);
        $ssl = openssl_x509_parse($cert);
        $SN = md5( $this->array2string(array_reverse($ssl['issuer'])) . $ssl['serialNumber']);
        return $SN;
    }
    /**
     * 从证书内容中提取序列号
     * @param $certContent
     * @return string
     */
    public function getCertSNFromContent($certContent): string
    {
        $ssl = openssl_x509_parse($certContent);
        $SN = md5($this->array2string(array_reverse($ssl['issuer'])) . $ssl['serialNumber']);
        return $SN;
    }

    /**
     * 提取根证书序列号
     * @param $certPath @证书路径
     * @return string|null
     */
    public function getRootCertSN($certPath): ?string
    {
        $certContent = file_get_contents($certPath);
        return $this->getRootCertSNFromContent($certContent);
    }
    /**
     * 提取根证书序列号
     * @param $certContent @证书内容
     * @return string|null
     */
    public function getRootCertSNFromContent($certContent): ?string
    {
        $array = explode("-----END CERTIFICATE-----", $certContent);
        $SN = null;
        for ($i = 0; $i < count($array) - 1; $i++) {
            $ssl[$i] = openssl_x509_parse($array[$i] . "-----END CERTIFICATE-----");
            if(strpos($ssl[$i]['serialNumber'],'0x') === 0){
                $ssl[$i]['serialNumber'] = $this->hex2dec($ssl[$i]['serialNumberHex']);
            }
            if ($ssl[$i]['signatureTypeLN'] == "sha1WithRSAEncryption" || $ssl[$i]['signatureTypeLN'] == "sha256WithRSAEncryption") {
                if ($SN == null) {
                    $SN = md5($this->array2string(array_reverse($ssl[$i]['issuer'])) . $ssl[$i]['serialNumber']);
                } else {

                    $SN = $SN . "_" . md5($this->array2string(array_reverse($ssl[$i]['issuer'])) . $ssl[$i]['serialNumber']);
                }
            }
        }
        return $SN;
    }
    /**
     * 0x转高精度数字
     * @param $hex
     * @return int|string
     */
    private function hex2dec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
        }
        return $dec;
    }

    /**
     * 从证书中提取公钥
     * @param $certPath
     * @return mixed
     */
    public function getPublicKey($certPath)
    {
        $cert = file_get_contents($certPath);
        $pkey = openssl_pkey_get_public($cert);
        $keyData = openssl_pkey_get_details($pkey);
        $public_key = str_replace('-----BEGIN PUBLIC KEY-----', '', $keyData['key']);
        $public_key = trim(str_replace('-----END PUBLIC KEY-----', '', $public_key));
        return $public_key;
    }

    /**
     * 从证书content中提取公钥
     * @param $content
     * @return mixed
     */
    public function getPublicKeyFromContent($content){
        $pkey = openssl_pkey_get_public($content);
        $keyData = openssl_pkey_get_details($pkey);
        $public_key = str_replace('-----BEGIN PUBLIC KEY-----', '', $keyData['key']);
        $public_key = trim(str_replace('-----END PUBLIC KEY-----', '', $public_key));
        return $public_key;
    }


    private function array2string($array): string
    {
        $string = [];
        if ($array && is_array($array)) {
            foreach ($array as $key => $value) {
                $string[] = $key . '=' . $value;
            }
        }
        return implode(',', $string);
    }





}