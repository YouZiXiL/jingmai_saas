<?php

namespace app\web\library\ali;

use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Config;

class AliConfig
{
    public static $merchantCertPath;
    public static $alipayCertPath;
    public static $alipayRootCertPath;
    public static $util;

    public static function options($APPID): Factory
    {
        self::init();
        return Factory::setOptions(self::getOptions($APPID));
    }
    private static function init(){
        $basePath = root_path();
        self::$merchantCertPath = $basePath.'extend/alipay/appCertPublicKey_2021003182686889.crt';
        self::$alipayCertPath = $basePath .'extend/alipay/alipayCertPublicKey_RSA2.crt';
        self::$alipayRootCertPath = $basePath.'extend/alipay/alipayRootCert.crt';
        self::$util = new Util();
    }
    private static function getOptions($APPID): Config
    {
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = $APPID;
        $options->merchantPrivateKey = 'MIIEwAIBADANBgkqhkiG9w0BAQEFAASCBKowggSmAgEAAoIBAQCr0n2tjCOxC7tKyhQAIs4jV+sjdHTg2U62flAif2HMpmbw2vmlFJt0iCvh53lCBkKiufFlKw8hw5Uf6jjW3QyBIPZFOyIynD9T7z8/pKJE/NgkX9J84JP45pjUKav8ip2/M1s7f56MBH1Z1ZRpn/fxpD5MuBjrNFthE2y7FmT0NxAXhwCcpsSso3VlorclajOXGHiIu7IjjwUh9uStE1OzOsgp6XFOMWvnVi2WWgJSG5YTJAyq0esrr4tfyeGIWAeLxdfb4NXFgQpGw4TF1regX9F7mLQ5CzCJIV4In+OSk9gU5RFHHU1fAnbrwgkcFfJZZLSpxJv4LtD9tMHmjmuNAgMBAAECggEBAI8GWxlQcxFX+6KIzddDIaZn89KCiRv1p8DfKiNdN3Kb/Up2bKJOogyBoU2dWeFsxqEf+OHG0wS/Am4bkQ7B2DvQzU7Zp2DLkSd0GokGqRCWC/FTVioe4u2oPgU1XvWMCT10KXhAhiB1SEa0M6Msxr59gmJoSE3ZOkt3WOXvAUALC1jv8qfrBYpzGYhL+z4FOhQogetXB7Xjj1mjR8+plTazRF9DNwQKnEBrQ1FTNIqb1sivZ4vfzbQkrBIJIQoORlUJpMp1f2FdkRKk4N8IohVhPPPljDlJcu2Bs3Hn+cUslSeVYXx6ta+4mg777M8LPWL8j9185+NB8MgF+I9HFmECgYEA4/OfTkssdvaY4czzgi3vK7qVp4Zt5zYysuHLojDXu71ZN8feakDJuWEXwD7Ae25qFa7WlK9f8KIXQZrqSEyO4B/KKNBcEbBGyrmx/80Q1YSWgSyhy0irCfcHSD/k0rREuYE9ZYZGh4DJrFB9zl6pwCbJINgr67Txo36/qm7wBjkCgYEAwPbQ/ZW5l3WIXTE47hHT60f3TXWu4lNGa16iuCGSrlarj2xWCLssFuozR04jBe9yXndHaMpqF50U8sSefLqz3u/on3XJrJBXITFuD7m4Puk3bQzubzKLQbQ6lQhjw8RS8nb66zaN1D1ejfNoxuy0tcnzpBokR7CrIb7gqpdUL/UCgYEArYAOru70xw9myebPKTSBKE15/uqI2EUeTZ0i+y4l5dV3BMfx9/mcEKdgBok8xDYENMDAvqbFZUhaXyrkS2dKEDjiDDKbATOkjkTMwKB6wamLTIKGG04SMSF+v3UyW1WuQWunKZEMObLCzY7uUpcmqQRcbc1bkKpGiWS9yaFqu7ECgYEAo9XsBCMH/Q0Rxu5wA9KnN/FWjDILVqaV97vWNLhsrhFwgMnfEnK5MIuRFk3FdtijJonn25VhFsOMccN3PTfYWMUhoaKEpTu4frpVVIy/XsrtAG4mU8t6aUL5KmiBLEqYkr1qtiMPsNCaY7PzllKL7H2XnBGGFEhwRKqoXq82D7ECgYEAyJG8Ntzpt2qlM+fhZY74n/cJTQiPvvHc1HYt29r1UoVOv3zT6LUNh1q+1Vw5aKxLO61mShq8wtvTRO4L3xynEcqzZC8nZQyfqbFyL4/cd0bLQsqRPvykOIoNU2hc6XOhR3H3HJ+jXtQbNBUqUEKYYkNjANxG0Q3pMqytfambKQ4=';
//        $options->alipayPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAheWyEYj64iDCClDnWuyYdypueUBEq325c3fQRTFdMOzaTzeCfBOJnUPfrX6gAbDMqQaZJ/6Fg7edmPJ/DsWQMuMn3h4aVctG/azAFVF1mVx6kLMjttY2TNg4KmNVR+icWNQccmxG+bSW/Cm//Ww58A8hYqtA3L6zQc12HLDm2gF14kS7rZCc00hAXD61gy9ju1t4jer1bl86w8faNRQCY744HG+gdA+2yG2MhFkcuUhZYWn96FwBzL9uKqc0qGin3782dVWT+2o3vNVigwou0ejZarJhL/X9DODVSpkFPz/gOTZaVBQmpP1HXFPuRWqCOTLyp5bZX/DXLkvN4gWy3wIDAQAB';
        // 支付宝公钥证书文件路径
        $options->alipayCertPath = root_path() .'extend/alipay/alipayCertPublicKey_RSA2.crt';
        // 支付宝根证书文件路径
        $options->alipayRootCertPath = root_path().'extend/alipay/alipayRootCert.crt';
        // 应用公钥证书
        $options->merchantCertPath = root_path().'extend/alipay/appCertPublicKey_2021003182686889.crt';

        //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
        // $options->alipayPublicKey = '<-- 请填写您的支付宝公钥，例如：MIIBIjANBg... -->';
        //可设置异步通知接收服务地址（可选）
        $options->notifyUrl = request()->domain() . '/web/notice/ali';
        // 秘钥
        $options->encryptKey = 'W15T4J+wA/JPnMaPTMypLw==';

        // 应用公钥：MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAl9swb+iHKmL0F2ho7hu9mWiTTQibrBD3f63uknQQzbNYfey9KBmtbyljaQNRXhGi7ZoX6cGIvskWWrk1Nxd2yqN8rD4+eNBj11ZDIFpnduKPb7FTZMvOuIni+/8EvG5Yynj1LAUwAh5+OnM0tDtMrfr3Ak3XDyV57wjz//rx9vph8TcSVOkT+TfzUQVCLDtG7IaC1eMP8q25D5igRWGpdM7OeG6c0hBF+CQzV6hWGPIpt/2BW91YTr5mT8A7L6F4J4AIv47MKYVijFSKxvYFaeFa4iEzOe2dlwc4UfwJJz+PxiUWPzcN/9OpFW9+vGb12D1hTHTrWuqsTia9KO8zjwIDAQAB
        // 支付宝公钥：MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAheWyEYj64iDCClDnWuyYdypueUBEq325c3fQRTFdMOzaTzeCfBOJnUPfrX6gAbDMqQaZJ/6Fg7edmPJ/DsWQMuMn3h4aVctG/azAFVF1mVx6kLMjttY2TNg4KmNVR+icWNQccmxG+bSW/Cm//Ww58A8hYqtA3L6zQc12HLDm2gF14kS7rZCc00hAXD61gy9ju1t4jer1bl86w8faNRQCY744HG+gdA+2yG2MhFkcuUhZYWn96FwBzL9uKqc0qGin3782dVWT+2o3vNVigwou0ejZarJhL/X9DODVSpkFPz/gOTZaVBQmpP1HXFPuRWqCOTLyp5bZX/DXLkvN4gWy3wIDAQAB
        // 应用私钥：MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCX2zBv6IcqYvQXaGjuG72ZaJNNCJusEPd/re6SdBDNs1h97L0oGa1vKWNpA1FeEaLtmhfpwYi+yRZauTU3F3bKo3ysPj540GPXVkMgWmd24o9vsVNky864ieL7/wS8bljKePUsBTACHn46czS0O0yt+vcCTdcPJXnvCPP/+vH2+mHxNxJU6RP5N/NRBUIsO0bshoLV4w/yrbkPmKBFYal0zs54bpzSEEX4JDNXqFYY8im3/YFb3VhOvmZPwDsvoXgngAi/jswphWKMVIrG9gVp4VriITM57Z2XBzhR/AknP4/GJRY/Nw3/06kVb368ZvXYPWFMdOta6qxOJr0o7zOPAgMBAAECggEAZHvP011Eg5Gy/wJB3L1Sen6uadz2VadsfnozUYmnzNWOCtVqXWyQTOxQMZ7Waq605xB+HlnICKa7OhEv22KVYoVH7BnL8NnEFyung3MO36YPT9NA4YPTKF1la2ZWVfGWo3Pil9xe5igTUs4E4/YRSVa1uDqVwhoEU13TR2FgVxJRRrHj534bEoXkUqkKd1avVVb9ilMxBbdcwiLKPmkNsNKwF/8QGS6bVzNccnGgmZ0GwIvAZxxnD66vo8jN5QnPqGKdklcahQhuWb5JS80odxgr9MPskIvPlDZH7ZIDgbmsdrosbn5SmBDy9PBqZpbrUNAKI+b3+IlrGCEm4p6ucQKBgQDwWkJDCNgTM8OEhr+688jj3HY+qd7RPpLiXP6zWM6PetVa/rC3fW46+oludbif2JJeb26i/7Yn5pTrr8r0wouCwJsuwLHBzkE1ypn43u6QJDfailwhO7Y4pH9aU8ZrjhlR5sEjFixJLbc9rvy4PY/x5s+28xkMhC8ZL9SrPTsL0wKBgQChvgppflOZnUWLUrkPT8UHRGZXmP93l/e8NPWWus143awK16rW7+JTftKIflkmQ5l9fjcwI24S/xX3hdnx6o4V4WJ2AzpUiS0VwbaVxD2yDOeS2ZItLjsr8g8uMgE8VoS3T87ZyMR9ftezIxWRK+Q9PdT6dNZ7RxM0X8MxmU8P1QKBgAp0srs90ECNmOzT+9VDM7MN7Srust3Bbhxg1UeyDlJZkpyBxehFkZ0JNx9SCbUSc9Od319B5oe31TSnkhmxuOX4QQf4pAL9WQLhDG+yKwikOrXMHRPpCBVQuqWQTWpyZRGWC0LefRC152nMifvt2aw5UUHxM17DBVWAKi1E3aO3AoGAVGEecXa0Cnnf5BVd8jz9XxMyCRUKgcvINGm0jdQaiamrrWRh/gvbmQ+aqhawT6QImU2VYQm6zTJCtYUg9HIXehbBFSwN7Dg8SxNqO6vLO+47iYL0HZn2yLBZdxIjTuUcC74Y/ckdqRLZWN0+zSOGyORPDfKSSnID9NjYrjF665kCgYEAr8GvbM884o8ZJpCPZ/oIVizXkBUZZxeTGB6rkzjyqKxPUMJQpzmOQlkO1aH719T9zis6aUSBt3dq6AE8ytqgBd12PGsJYpUiuo/8ceTLixaZpjjjIO7IetjNld7x6T/aH8Na7CsexSplcsfchJWy2xaOVuCIlR+TI3/F4FlMbCk=
        // AES：W15T4J+wA/JPnMaPTMypLw==
        return $options;
    }
}