<?php

namespace app\common\library\alipay;

use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Config;

class AliConfigB
{
    public static $alipayCertPath;
    public static $util;

    public static function options($APPID): Factory
    {
        return Factory::setOptions(self::getOptions($APPID));
    }

    private static function getOptions($aesKey): Config
    {
        $aesKey = $aesKey??'uUu+jCQjwZjf1SmWKkk02Q==';
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = '2021003176656290';
        $options->merchantPrivateKey = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC2XswBetlM3EWl0GdtNTVsRyppz+WBu+d3+Hw0mX8uNDrAQK2Ih5uW8j1q7a4Ea0tuG7otRGDLIIXKoqx7lZJbda48/2c515D1TkXJX5v6pwYCskzbqJ7JEN9WmNTOPBdhUvltSP6hWznKdcg9/1bIOfWipaU6/jqydf/sIKYVWmi1A8zl1F4lOzdB0bRU6FN5LGI/5ZWPL2mSbEZNVoxZel+C5NYA5oqj3WyfbSsjYWnOsnRURCBjug8QQ8mL8NNpcMRI/jjluYvMNOla18eYxiftUFVWU6EK9/OMuHhInhso+apFIGmx+bZl2s2SdZxaukf72b/GAGaHbHjV3evdAgMBAAECggEBAJ3fEGFTlJsJsC0rbO1H+3hBXwD8XKRjIqxjajoqisNXqWhWVSL0qYcRKoPPPy5KJ6Eo1ZFsORJ+k/NnwUK2p10PUYcyXYzgBHemi5kYcDGatwRMj2Yz4UkTlxMi+E2UDXVN2+h0ISFTlIKkVXT9/oUUT4S+OnwIKo3kwmgJl1yxUQfSeaKi0jwckbk3QhRKiIHtMf00q1/fUvRhnECecC9/B9kL2bsQeZFCkrgD7QSogocBtfVnBDXO4u1Z2LbLBoFGwWmPJsPV9flEXQmTduay5hb5AFr7X7obTMWc1TJmkMgFb7915TCidaYQvEHO68kVcdvMzp7BgllEGyAOo60CgYEA6J35A620rD/p7wWq+9T4CReOXEB8i+vCrf2XQtwkH5h1ocOEcB8kMKaUCCHLoVjX3iz0x/LkGj7csUvdL46WFWMDtp5s712x7YvYutiLLtaNU233eHv1l3a7dMSD/OpuCQy22hq716UIbVa+rBIiva+JVsHwcWBQB3c1dgfL4WsCgYEAyLPNzckF1vJ1Waml/HHI+/fGznryZVvYsR0wTgRZ4b8RdcLvBExABJ3c8myt/gJNn/nI/GuRkk7Q095FbSNtLIgUHt7ZiappI+gYwazvLNFSPqIkPO9S8DVXVdilNMaCnVdeEslfxVu3abw2JKhPvXQDUEKRTLh44wdJkYxikdcCgYEAwkFR90FzdLj3GZVJIX7LF4SlbOKpX4ulivkP/VSrkfsWmN0W1y8aYMprKpNWYso3kyFF1UhANq6yTBkHgDR5nyiNrE6GuCotcSts9TPqn1WqtbZKiEI0aKVNjAikFGyNMV99v4u9vCrka7KaDkYi3vcdIOdabMO6cVGawpNmLjsCgYAIZBwSonjwB6sIWKNr7oXsoutY7qL7wd9JaGMAoAXx/j8IoWHE2DtE9iSqrgiIOihsq9DPXN/1Mf5hHUXhzj1eQ0I0GDUYIFvM+IMwdb56LTY3EChbs9XP3SsNl8Uwc6w6T9DLEEhExBTjAs9wsOjEjvkkfeP9RSkotMFKqIfmeQKBgCEe5W7J4AeTuIVH+0b+uJtzmLh/X7lbFQfbhBuYXhxtMtcQ3ObQJJ5XT82NttjYr8+eiXWRR+/n76qLSb8TBIQDBTSKNrYUFMlXYqskVoHFVwmmKadoL5LmMBL+7JmnJXOsnk6SeQ5J8qbWcNZzBRDEAoAnXO6Nm8CNAj/uq46T';
        // 支付宝公钥证书文件路径
        $options->alipayCertPath = root_path('extend/alipay/provider/alipayCertPublicKey_RSA2.crt');
        // 支付宝根证书文件路径
        $options->alipayRootCertPath = root_path('extend/alipay/provider/alipayRootCert.crt');
        // 应用公钥证书
        $options->merchantCertPath = root_path('extend/alipay/provider/appCertPublicKey_2021003176656290.crt');

        //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
        // $options->alipayPublicKey = '<-- 请填写您的支付宝公钥，例如：MIIBIjANBg... -->';
        //可设置异步通知接收服务地址（可选）
        $options->notifyUrl = request()->domain() . '/web/notice/ali';
        // 秘钥
        $options->encryptKey = $aesKey;

        // 应用公钥：MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAl9swb+iHKmL0F2ho7hu9mWiTTQibrBD3f63uknQQzbNYfey9KBmtbyljaQNRXhGi7ZoX6cGIvskWWrk1Nxd2yqN8rD4+eNBj11ZDIFpnduKPb7FTZMvOuIni+/8EvG5Yynj1LAUwAh5+OnM0tDtMrfr3Ak3XDyV57wjz//rx9vph8TcSVOkT+TfzUQVCLDtG7IaC1eMP8q25D5igRWGpdM7OeG6c0hBF+CQzV6hWGPIpt/2BW91YTr5mT8A7L6F4J4AIv47MKYVijFSKxvYFaeFa4iEzOe2dlwc4UfwJJz+PxiUWPzcN/9OpFW9+vGb12D1hTHTrWuqsTia9KO8zjwIDAQAB
        // 支付宝公钥：MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAheWyEYj64iDCClDnWuyYdypueUBEq325c3fQRTFdMOzaTzeCfBOJnUPfrX6gAbDMqQaZJ/6Fg7edmPJ/DsWQMuMn3h4aVctG/azAFVF1mVx6kLMjttY2TNg4KmNVR+icWNQccmxG+bSW/Cm//Ww58A8hYqtA3L6zQc12HLDm2gF14kS7rZCc00hAXD61gy9ju1t4jer1bl86w8faNRQCY744HG+gdA+2yG2MhFkcuUhZYWn96FwBzL9uKqc0qGin3782dVWT+2o3vNVigwou0ejZarJhL/X9DODVSpkFPz/gOTZaVBQmpP1HXFPuRWqCOTLyp5bZX/DXLkvN4gWy3wIDAQAB
        // 应用私钥：MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCX2zBv6IcqYvQXaGjuG72ZaJNNCJusEPd/re6SdBDNs1h97L0oGa1vKWNpA1FeEaLtmhfpwYi+yRZauTU3F3bKo3ysPj540GPXVkMgWmd24o9vsVNky864ieL7/wS8bljKePUsBTACHn46czS0O0yt+vcCTdcPJXnvCPP/+vH2+mHxNxJU6RP5N/NRBUIsO0bshoLV4w/yrbkPmKBFYal0zs54bpzSEEX4JDNXqFYY8im3/YFb3VhOvmZPwDsvoXgngAi/jswphWKMVIrG9gVp4VriITM57Z2XBzhR/AknP4/GJRY/Nw3/06kVb368ZvXYPWFMdOta6qxOJr0o7zOPAgMBAAECggEAZHvP011Eg5Gy/wJB3L1Sen6uadz2VadsfnozUYmnzNWOCtVqXWyQTOxQMZ7Waq605xB+HlnICKa7OhEv22KVYoVH7BnL8NnEFyung3MO36YPT9NA4YPTKF1la2ZWVfGWo3Pil9xe5igTUs4E4/YRSVa1uDqVwhoEU13TR2FgVxJRRrHj534bEoXkUqkKd1avVVb9ilMxBbdcwiLKPmkNsNKwF/8QGS6bVzNccnGgmZ0GwIvAZxxnD66vo8jN5QnPqGKdklcahQhuWb5JS80odxgr9MPskIvPlDZH7ZIDgbmsdrosbn5SmBDy9PBqZpbrUNAKI+b3+IlrGCEm4p6ucQKBgQDwWkJDCNgTM8OEhr+688jj3HY+qd7RPpLiXP6zWM6PetVa/rC3fW46+oludbif2JJeb26i/7Yn5pTrr8r0wouCwJsuwLHBzkE1ypn43u6QJDfailwhO7Y4pH9aU8ZrjhlR5sEjFixJLbc9rvy4PY/x5s+28xkMhC8ZL9SrPTsL0wKBgQChvgppflOZnUWLUrkPT8UHRGZXmP93l/e8NPWWus143awK16rW7+JTftKIflkmQ5l9fjcwI24S/xX3hdnx6o4V4WJ2AzpUiS0VwbaVxD2yDOeS2ZItLjsr8g8uMgE8VoS3T87ZyMR9ftezIxWRK+Q9PdT6dNZ7RxM0X8MxmU8P1QKBgAp0srs90ECNmOzT+9VDM7MN7Srust3Bbhxg1UeyDlJZkpyBxehFkZ0JNx9SCbUSc9Od319B5oe31TSnkhmxuOX4QQf4pAL9WQLhDG+yKwikOrXMHRPpCBVQuqWQTWpyZRGWC0LefRC152nMifvt2aw5UUHxM17DBVWAKi1E3aO3AoGAVGEecXa0Cnnf5BVd8jz9XxMyCRUKgcvINGm0jdQaiamrrWRh/gvbmQ+aqhawT6QImU2VYQm6zTJCtYUg9HIXehbBFSwN7Dg8SxNqO6vLO+47iYL0HZn2yLBZdxIjTuUcC74Y/ckdqRLZWN0+zSOGyORPDfKSSnID9NjYrjF665kCgYEAr8GvbM884o8ZJpCPZ/oIVizXkBUZZxeTGB6rkzjyqKxPUMJQpzmOQlkO1aH719T9zis6aUSBt3dq6AE8ytqgBd12PGsJYpUiuo/8ceTLixaZpjjjIO7IetjNld7x6T/aH8Na7CsexSplcsfchJWy2xaOVuCIlR+TI3/F4FlMbCk=
        // AES：W15T4J+wA/JPnMaPTMypLw==
        return $options;
    }
}