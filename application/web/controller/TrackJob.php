<?php

namespace app\web\controller;

use app\common\config\Channel;
use think\Exception;
use think\Log;
use think\queue\Job;

class TrackJob
{
    public function fire(Job $job, $data)
    {
        // 执行任务的代码
        $isJobDone = $this->job($data);
        if($isJobDone){
            $job->delete();
        } else {
            //通过这个方法可以检查这个任务已经重试了几次了
            $attempts = $job->attempts();
            if ($attempts == 0 || $attempts == 1) {
                // 重新发布这个任务
                $job->release(20); //$delay为延迟时间，延迟20S后继续执行
            } else{
                $job->release(200); // 延迟200S后继续执行
            }
        }

    }

    private function job($data)
    {

        try {
            Log::info('执行track：'. json_encode($data, JSON_UNESCAPED_UNICODE));
            if ($data['channel_merchant'] == Channel::$yy) {
                if(!empty($data['comments']) || $data['comments'] == '无') return true;
                $yunYang = new \app\common\business\YunYang();
                $res = $yunYang->queryTrance($data['waybill']);
                $result = json_decode($res, true);
                if($result['code'] != 1){
                    throw new Exception("YY获取物流轨迹失败:" . $res);
                }
                $comments = $result['result'][0];
                if(empty($comments)) return false;
                $up_data = [
                    'comments' => $comments
                ];
                db('orders')->where('id',$data['id'])->update($up_data);
                return true;
            }
            return true;
        }catch (Exception $e){
            Log::error("track-队列执行异常：". $e->getMessage() . PHP_EOL
                . $e->getTraceAsString() . PHP_EOL
            );
            return false;
        }

    }
}