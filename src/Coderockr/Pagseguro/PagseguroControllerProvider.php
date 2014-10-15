<?php

namespace Coderockr\Pagseguro;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use SimpleXMLElement;
use Exception;
use Buzz\Client\Curl;
use Buzz\Browser;


class PagseguroControllerProvider implements ControllerProviderInterface
{
    private $token;
    private $email;
    private $buyerInstance;
    private $couponClass;
    private $itemClass;
    private $transactionClass;
    private $em;

    public function setEntityManager($em)
    {
        $this->em = $em;
    }
    
    public function getToken()
    {
        return $this->token;
    }
     
    public function setToken($token)
    {
        return $this->token = $token;
    }

    public function getEmail()
    {
        return $this->email;
    }
     
    public function setEmail($email)
    {
        return $this->email = $email;
    }

    public function getBuyerInstance()
    {
        return $this->buyerInstance;
    }
     
    public function setBuyerInstance($buyerInstance)
    {
        if (!in_array('Coderockr\Pagseguro\BuyerInterface', class_implements($buyerInstance))) {
            throw new Exception("Class must implements BuyerInterface", 1);
        }
        return $this->buyerInstance = $buyerInstance;
    }

    public function getCouponClass()
    {
        return $this->couponClass;
    }
     
    public function setCouponClass($couponClass)
    {
        if (!in_array('Coderockr\Pagseguro\CouponInterface', class_implements($couponClass))) {
            throw new Exception("Class must implements CouponInterface", 1);
        }
        return $this->couponClass = $couponClass;
    }

    public function getItemClass()
    {
        return $this->itemClass;
    }
     
    public function setItemClass($itemClass)
    {
        if (!in_array('Coderockr\Pagseguro\ItemInterface', class_implements($itemClass))) {
            throw new Exception("Class must implements ItemInterface", 1);
        }
        return $this->itemClass = $itemClass;
    }

    public function getTransactionClass()
    {
        return $this->transactionClass;
    }
     
    public function setTransactionClass($transactionClass)
    {
        if (!in_array('Coderockr\Pagseguro\TransactionInterface', class_implements($transactionClass))) {
            throw new Exception("Class must implements TransactionInterface", 1);
        }
        return $this->transactionClass = $transactionClass;
    }

    public function connect(Application $app)
    {
        $this->setEntityManager($app['orm.em']);
        $controllers = $app['controllers_factory'];

        
        $controllers->get('/{item}/{coupon}', function (Application $app, $item, $coupon, Request $request) {
            //get item
            $item = $this->em->getRepository($this->getItemClass())->find($item);
            if (!$item) {
                throw new Exception("Item not found", 1);
            }
            if ($coupon) {
                //get item
                $coupon = $this->em->getRepository($this->getCouponClass())->find($coupon);
                if (!$coupon) {
                    throw new Exception("Coupon not found", 1);
                }
            }
            //save a transaction to get the id to use in reference
            $transaction = new $this->getTransactionClass();
            $transaction->setBuyer($this->getBuyerInstance());
            $transaction->setItem($item);
            $transaction->setCoupon($coupon);
            $transaction->save();

            $parameters = array(
                'email' => $this->getEmail(),
                'token' => $this->getToken(),
                'redirectURL' => $item->getRedirectUrl(),
                'currency' => 'BRL',
                'itemId1' => $item->getId(),
                'itemDescription1' => $item->getName(),
                'itemAmount1' => $item->getValue(),
                'itemQuantity1' => '1',
                'itemWeight1' => '0',
                'reference' => $transaction->getId()
            );
        if ($coupon) {
            $parameters['extraAmount'] = number_format(-1 * $coupon()->getValue(), 2);
        }

        $client = new Curl();
        $client->setTimeout(30);
        $browser = new Browser($client);
        $response = $browser->post(
            'https://ws.pagseguro.uol.com.br/v2/checkout/',
            array('Content-Type' => 'application/x-www-form-urlencoded; charset=ISO-8859-1'),
            http_build_query($parameters)
        );
        try {
            $responseXML = new SimpleXMLElement($response->getContent());    
        } catch (Exception $e) {
            $app['monolog']->addError($e->getMessage());
            throw new Exception("Ocorreu um erro na conexão com o servidor do PagSeguro.", 1);
        }
        
        if (count($responseXML->xpath('/errors')) > 0) {
            $app['monolog']->addError($responseXML->asXml());
            throw new Exception("Ocorreu um erro na conexão com o servidor do PagSeguro.", 1);
        }
            
        $code = array_shift($responseXML->xpath('/checkout/code'));

        return $app->redirect(
            'https://pagseguro.uol.com.br/v2/checkout/payment.html?code=' . $code
        );
        })->value('coupon', null);

        return $controllers;
    }   
}
