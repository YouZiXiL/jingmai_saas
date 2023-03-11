<?php

namespace app\web\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Send extends Command
{
    protected function configure()
    {
        $this->setName('sendSms')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        db('test')->where('id',1)->update(['user_id'=>2]);
    }
}