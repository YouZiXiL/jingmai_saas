<?php
namespace app\common\library\alipay;

use app\common\library\alipay\aop\AopCertClient;


class AliConfig
{
    private static $instance;

    public static function options(): AopCertClient
    {
        if(!self::$instance)self::getOptions();
        return self::$instance;
    }

    private static function getOptions()
    {
        $appCertPath = root_path('extend/alipay/provider/appCertPublicKey_2021003176656290.crt');
        $alipayCertPath = root_path('extend/alipay/provider/alipayCertPublicKey_RSA2.crt');
        $rootCertPath = root_path('extend/alipay/provider/alipayRootCert.crt');
        $aop = new AopCertClient;
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = "2021003176656290";
        $aop->rsaPrivateKey = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC2XswBetlM3EWl0GdtNTVsRyppz+WBu+d3+Hw0mX8uNDrAQK2Ih5uW8j1q7a4Ea0tuG7otRGDLIIXKoqx7lZJbda48/2c515D1TkXJX5v6pwYCskzbqJ7JEN9WmNTOPBdhUvltSP6hWznKdcg9/1bIOfWipaU6/jqydf/sIKYVWmi1A8zl1F4lOzdB0bRU6FN5LGI/5ZWPL2mSbEZNVoxZel+C5NYA5oqj3WyfbSsjYWnOsnRURCBjug8QQ8mL8NNpcMRI/jjluYvMNOla18eYxiftUFVWU6EK9/OMuHhInhso+apFIGmx+bZl2s2SdZxaukf72b/GAGaHbHjV3evdAgMBAAECggEBAJ3fEGFTlJsJsC0rbO1H+3hBXwD8XKRjIqxjajoqisNXqWhWVSL0qYcRKoPPPy5KJ6Eo1ZFsORJ+k/NnwUK2p10PUYcyXYzgBHemi5kYcDGatwRMj2Yz4UkTlxMi+E2UDXVN2+h0ISFTlIKkVXT9/oUUT4S+OnwIKo3kwmgJl1yxUQfSeaKi0jwckbk3QhRKiIHtMf00q1/fUvRhnECecC9/B9kL2bsQeZFCkrgD7QSogocBtfVnBDXO4u1Z2LbLBoFGwWmPJsPV9flEXQmTduay5hb5AFr7X7obTMWc1TJmkMgFb7915TCidaYQvEHO68kVcdvMzp7BgllEGyAOo60CgYEA6J35A620rD/p7wWq+9T4CReOXEB8i+vCrf2XQtwkH5h1ocOEcB8kMKaUCCHLoVjX3iz0x/LkGj7csUvdL46WFWMDtp5s712x7YvYutiLLtaNU233eHv1l3a7dMSD/OpuCQy22hq716UIbVa+rBIiva+JVsHwcWBQB3c1dgfL4WsCgYEAyLPNzckF1vJ1Waml/HHI+/fGznryZVvYsR0wTgRZ4b8RdcLvBExABJ3c8myt/gJNn/nI/GuRkk7Q095FbSNtLIgUHt7ZiappI+gYwazvLNFSPqIkPO9S8DVXVdilNMaCnVdeEslfxVu3abw2JKhPvXQDUEKRTLh44wdJkYxikdcCgYEAwkFR90FzdLj3GZVJIX7LF4SlbOKpX4ulivkP/VSrkfsWmN0W1y8aYMprKpNWYso3kyFF1UhANq6yTBkHgDR5nyiNrE6GuCotcSts9TPqn1WqtbZKiEI0aKVNjAikFGyNMV99v4u9vCrka7KaDkYi3vcdIOdabMO6cVGawpNmLjsCgYAIZBwSonjwB6sIWKNr7oXsoutY7qL7wd9JaGMAoAXx/j8IoWHE2DtE9iSqrgiIOihsq9DPXN/1Mf5hHUXhzj1eQ0I0GDUYIFvM+IMwdb56LTY3EChbs9XP3SsNl8Uwc6w6T9DLEEhExBTjAs9wsOjEjvkkfeP9RSkotMFKqIfmeQKBgCEe5W7J4AeTuIVH+0b+uJtzmLh/X7lbFQfbhBuYXhxtMtcQ3ObQJJ5XT82NttjYr8+eiXWRR+/n76qLSb8TBIQDBTSKNrYUFMlXYqskVoHFVwmmKadoL5LmMBL+7JmnJXOsnk6SeQ5J8qbWcNZzBRDEAoAnXO6Nm8CNAj/uq46T';
        $aop->format = "json";
        $aop->signType= "RSA2";
        $aop->encryptKey = "uUu+jCQjwZjf1SmWKkk02Q==";
        $aop->appCertSN = $aop->getCertSN($appCertPath);
        $aop->alipayrsaPublicKey = $aop->getPublicKey($alipayCertPath);
        $aop->alipayRootCertSN = $aop->getRootCertSN($rootCertPath);
        self::$instance = $aop;
    }
}