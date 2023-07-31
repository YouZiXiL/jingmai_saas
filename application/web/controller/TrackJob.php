<?php

namespace app\web\controller;

use app\common\config\Channel;
use app\common\model\Order;
use think\Exception;
use think\Log;
use think\queue\Job;

class TrackJob
{
    public function fire(Job $job, $orderId)
    {
        // 执行任务的代码
        $isJobDone = $this->job($orderId);
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

    /**
     * 获取物流轨迹
     * @param $orderId
     * @return bool
     */
    private function job($orderId)
    {
        try {
            Log::info('执行track：'. json_encode($orderId) );
            $orders = Order::get(['id', $orderId]);
            Log::info('执行trackOrder：'. json_encode($orders->toArray(), JSON_UNESCAPED_UNICODE));
            if ($orders['channel_merchant'] == Channel::$yy) {
                Log::info('执行track：1');
                if(empty($orders['waybill'])) return false;
                if(!empty($orders['comments']) && $orders['comments'] != '无') return true;
                Log::info('执行track：2');
                $yunYang = new \app\common\business\YunYang();
                $res = $yunYang->queryTrance($orders['waybill']);
                Log::info('执行track：3-'.$res);
                $result = json_decode($res, true);
                if($result['code'] != 1){
                    Log::error("track-队列执行异常："."YY获取物流轨迹失败:" . $res);
                    return false;
                }
                $comments = $result['result'][0];
                if(empty($comments)) return false;
                $up_data = [
                    'comments' => $comments
                ];
                db('orders')->where('id',$orders['id'])->update($up_data);
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