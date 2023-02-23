<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 输出正常JSON
    @param string 提示信息
    @param array  输出数据
    @return json
     */
    function  jok ($msg='success',$data=null){
        $pam=$this->request->token();
        header("content:application/json;chartset=uft-8");
        if ($data){
            echo json_encode (["code"=>200,"msg"=>$msg,'data'=>$data]);
        }else {
            echo json_encode (["code"=>200,"msg"=>$msg]);
        }
        die ;
    }
    /**
     * 输出错误JSON
    @param string 错误信息
    @param int 错误代码
    @return json
     */
    function  jerr ($msg='error',$code=500,$data=false){
        header("content:application/json;chartset=uft-8");
        echo json_encode (["code"=>$code,"msg"=>$msg,"data"=>$data??[]]);
        die ;
    }
    function  curlHelper ($url,$data=null,$header=[],$cookies="",$method='GET'){
        $ch=curl_init();
        curl_setopt($ch,CURLOPT_URL ,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER ,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST ,false);
        $header[] = 'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 11_1_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Safari/537.36';
        curl_setopt($ch,CURLOPT_HTTPHEADER ,$header);
        curl_setopt($ch,CURLOPT_COOKIE ,$cookies);
        switch ($method){
            case  "GET":
                curl_setopt($ch,CURLOPT_HTTPGET ,true);
                break ;
            case  "POST":
                curl_setopt($ch,CURLOPT_POST ,true);
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            case  "PUT":
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST ,"PUT");
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            case  "DELETE":
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST ,"DELETE");
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            case  "PATCH":
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST ,"PATCH");
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            case  "TRACE":
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST ,"TRACE");
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            case  "OPTIONS":
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST ,"OPTIONS");
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            case  "HEAD":
                curl_setopt($ch,CURLOPT_CUSTOMREQUEST ,"HEAD");
                curl_setopt($ch,CURLOPT_POSTFIELDS ,$data);
                break ;
            default :
        }
        curl_setopt($ch,CURLOPT_RETURNTRANSFER ,1);
        curl_setopt($ch,CURLOPT_HEADER ,1);
        $response=curl_exec($ch);
        $output=[];
        $headerSize=curl_getinfo($ch,CURLINFO_HEADER_SIZE );
        // 根据头大小去获取头信息内容
        $output['header']=substr($response,0,$headerSize);
        $output['body']=substr($response,$headerSize,strlen($response)-$headerSize);
        $output['detail']=curl_getinfo($ch);
        curl_close($ch);
        return $output;
    }

    public function douyin(){
        $header = get_headers($_GET['url'],1);
        $realurl = $header['Location'][1]; //获取真实链接
        preg_match('/\d+/',$realurl,$arr);

        $dyapi = "https://www.iesdouyin.com/web/api/v2/aweme/iteminfo/?item_ids=".$arr['0'];
        $json = file_get_contents($dyapi);
        $json_content = json_decode($json);

        $item_list = $json_content->item_list[0];

        $video_uri = $item_list->video->play_addr->uri;
        $nomarkurl = 'https://aweme.snssdk.com/aweme/v1/play/?video_id='.$video_uri.'&ratio=720p&line=0';

        return json($nomarkurl);
    }


}
