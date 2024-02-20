<?php

namespace app\admin\business\dy;

use app\admin\model\wxauth\Authlist;
use app\common\library\douyin\Douyin;

class Version
{
    /**
     * 提交代码
     * @throws \Exception
     */
    public function upload($authApp){

        $dy = Douyin::start();
        $result = $dy->xcx()->getTplList();
        $template = $result['template_list'][0];
        $templateId = $template['template_id'];
        $version = $template['user_version'];
        $desc = $template['user_desc'];
        $accessTokenV1 = $dy->utils()->getAuthorizerAccessTokenV1($authApp['id']);

        // $accessToken = $dy->utils()->getAuthorizerAccessToken($authApp['id']);
        // $path = $dy->xcx()->uploadMaterial($accessToken);
        // $data = $dy->xcx()->phoneNumberV1($accessTokenV1, $path);


        $dy->xcx()->uploadV1($accessTokenV1, $authApp['app_id'], $templateId, $version, $desc);
        $authApp->save(['xcx_audit'=>3,'user_version'=>$version]);
    }

    /**
     * 审核代码
     * @param Authlist|null $authApp
     * @return void
     * @throws \Exception
     */
    public function audit(?Authlist $authApp)
    {
        $dy = Douyin::start();
        $accessToken = $dy->utils()->getAuthorizerAccessTokenV1($authApp['id']);
        $dy->xcx()->auditV1($accessToken);
        $authApp->save(['xcx_audit'=>4]);
    }

    /**
     * 发布代码
     * @param Authlist|null $authApp
     * @return void
     * @throws \Exception
     */
    public function release(?Authlist $authApp)
    {
        $dy = Douyin::start();
        $accessToken = $dy->utils()->getAuthorizerAccessTokenV1($authApp['id']);
        $dy->xcx()->releaseV1($accessToken);
        $authApp->save(['xcx_audit'=>5]);
    }
}