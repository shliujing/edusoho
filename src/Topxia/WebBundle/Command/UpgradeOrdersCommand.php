<?php
namespace Topxia\WebBundle\Command;

use Topxia\System;
use Topxia\Common\BlockToolkit;
use Topxia\Component\Payment\Payment;
use Topxia\Service\User\CurrentUser;
use Topxia\Service\Common\ServiceKernel;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class UpgradeOrdersCommand extends BaseCommand
{
    private $host = '';

    protected function configure()
    {
        $this->setName('util:upgrade-orders')
            ->addArgument('host', InputArgument::REQUIRED, '域名')
        	->addArgument('filePath', InputArgument::REQUIRED, '文件地址')
			->setDescription('用于命令行中执行模拟回调');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$this->initServiceKernel();
    	$this->host = $input->getArgument('host');
        $filePath = $input->getArgument('filePath');

        $file = file($filePath);
        foreach($file as $sn) {
            echo $sn;
            $order = $this->getOrderService()->getOrderBySn(trim($sn));
            $this->processOrder($order);
        }
    }

    protected function processOrder($order)
    {
        if(in_array($order['payment'], array('wxpay'))) {
            if($order['status'] == 'paid') {
                echo $order['status'];
                return;
            }

			$options = $this->getPaymentOptions($order['payment']);

			$payment = Payment::createTradeQueryRequest($order['payment'], $options);
			$payment->setParams($order);

			$result = $payment->tradeQuery();
			echo $result;

            $this->getOrderService()->updateOrder($order['id'], array('status'=>'created'));

            $this->postData($order['payment'], $result);
		}
    }

    private function postData($name, $data)
    {
        $url = "http://{$this->host}/pay/center/pay/{$name}/notify";

        $header = array();
        $header[] = "Content-type: text/xml"; 

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        echo $response;
        curl_close($ch);
    }

    private function initServiceKernel()
    {
        $serviceKernel = ServiceKernel::create('prod', false);
        $serviceKernel->setParameterBag($this->getContainer()->getParameterBag());
        $serviceKernel->setConnection($this->getContainer()->get('database_connection'));
        $currentUser = new CurrentUser();
        $currentUser->fromArray(array(
            'id'        => 0,
            'nickname'  => '游客',
            'currentIp' => '127.0.0.1',
            'roles'     => array()
        ));
        $serviceKernel->setCurrentUser($currentUser);
    }

    protected function getPaymentOptions($payment)
    {
        $settings = $this->getSettingService()->get('payment');

        $options = array(
            'key'    => $settings["{$payment}_key"],
            'secret' => $settings["{$payment}_secret"],
        );

        return $options;
    }

    protected function getOrderService()
    {
        return $this->getServiceKernel()->createService('Order.OrderService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }
}